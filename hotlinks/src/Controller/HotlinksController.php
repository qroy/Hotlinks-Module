<?php

namespace Drupal\hotlinks\Controller;

use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\taxonomy\Entity\Term;
use Symfony\Component\DependencyInjection\ContainerInterface;

class HotlinksController extends ControllerBase {

  protected $entityTypeManager;

  public function __construct(EntityTypeManagerInterface $entity_type_manager) {
    $this->entityTypeManager = $entity_type_manager;
  }

  public static function create(ContainerInterface $container) {
    return new static($container->get('entity_type.manager'));
  }

  public function index() {
    $build = [];

    try {
      // Get all hotlinks
      $query = $this->entityTypeManager
        ->getStorage('node')
        ->getQuery()
        ->condition('type', 'hotlink')
        ->condition('status', 1)
        ->accessCheck(TRUE);

      $nids = $query->execute();
      $hotlinks = !empty($nids) ? $this->entityTypeManager->getStorage('node')->loadMultiple($nids) : [];

      // Group hotlinks by category
      $hotlinks_by_category = $this->groupHotlinksByCategory($hotlinks);

      // Get all categories
      $categories = $this->entityTypeManager
        ->getStorage('taxonomy_term')
        ->loadByProperties(['vid' => 'hotlink_categories']);

      if (empty($categories)) {
        $build['no_categories'] = [
          '#markup' => '<p>' . $this->t('No categories found.') . '</p>',
        ];
        return $build;
      }

      // Build Star Trek Wormhole style front page
      $build['link_index'] = [
        '#type' => 'container',
        '#attributes' => ['class' => ['hotlinks-index']],
        'title' => [
          '#markup' => '<h2>Link Index</h2>',
        ],
        'categories' => $this->buildFrontPageCategories($categories, $hotlinks_by_category),
      ];

      $build['#attached']['library'][] = 'hotlinks/hotlinks.styles';

    } catch (\Exception $e) {
      $build['error'] = [
        '#markup' => '<p><strong>Error:</strong> ' . $e->getMessage() . '</p>',
      ];
      \Drupal::logger('hotlinks')->error('Error in index: @message', ['@message' => $e->getMessage()]);
    }

    return $build;
  }

  public function category(Term $category) {
    $build = [];

    try {
      // Get all hotlinks
      $query = $this->entityTypeManager
        ->getStorage('node')
        ->getQuery()
        ->condition('type', 'hotlink')
        ->condition('status', 1)
        ->accessCheck(TRUE)
        ->sort('title', 'ASC');

      $nids = $query->execute();
      $hotlinks = !empty($nids) ? $this->entityTypeManager->getStorage('node')->loadMultiple($nids) : [];

      // Group hotlinks by category
      $hotlinks_by_category = $this->groupHotlinksByCategory($hotlinks);

      // Get category links
      $category_hotlinks = $this->filterHotlinksByCategory($hotlinks, $category->id());

      // Check if this is a parent category
      $children = $this->entityTypeManager
        ->getStorage('taxonomy_term')
        ->loadChildren($category->id());

      // Build category page in Star Trek Wormhole style
      $build['category_header'] = [
        '#markup' => '<h2>' . $category->getName() . '</h2>',
      ];

      // Show subcategories at top (like Star Trek Wormhole)
      if (!empty($children)) {
        $build['subcategories'] = [
          '#type' => 'container',
          '#attributes' => ['class' => ['category-subcategories']],
          'content' => $this->buildSubcategoryList($children, $hotlinks_by_category),
        ];
      }

      // Show links in this category
      if (!empty($category_hotlinks)) {
        $build['links'] = [
          '#type' => 'container',
          '#attributes' => ['class' => ['category-links']],
          'title' => [
            '#markup' => '<h3>Links in ' . $category->getName() . '</h3>',
          ],
          'list' => [
            '#theme' => 'item_list',
            '#items' => [],
            '#attributes' => ['class' => ['hotlinks-list']],
          ],
        ];

        foreach ($category_hotlinks as $hotlink) {
          $item = $this->buildHotlinkItem($hotlink);
          $build['links']['list']['#items'][] = ['#markup' => \Drupal::service('renderer')->render($item)];
        }
      } else {
        $build['no_links'] = [
          '#markup' => '<p>' . $this->t('No links found in this category.') . '</p>',
        ];
      }

      $build['#attached']['library'][] = 'hotlinks/hotlinks.styles';

    } catch (\Exception $e) {
      $build['error'] = [
        '#markup' => '<p><strong>Error:</strong> ' . $e->getMessage() . '</p>',
      ];
      \Drupal::logger('hotlinks')->error('Error in category: @message', ['@message' => $e->getMessage()]);
    }

    return $build;
  }

  public function categoryTitle(Term $category) {
    return $this->t('@category', ['@category' => $category->getName()]);
  }

  /**
   * Build front page categories in Star Trek Wormhole style.
   */
  private function buildFrontPageCategories($categories, $hotlinks_by_category) {
    $build = [];

    // Get parent categories (those without parents)
    $parent_categories = [];
    foreach ($categories as $category) {
      $parents = $this->entityTypeManager
        ->getStorage('taxonomy_term')
        ->loadParents($category->id());

      if (empty($parents)) {
        $parent_categories[] = $category;
      }
    }

    // Sort parent categories alphabetically
    usort($parent_categories, function($a, $b) {
      return strcmp($a->getName(), $b->getName());
    });

    foreach ($parent_categories as $parent_category) {
      $parent_id = $parent_category->id();
      
      // Get children
      $children = $this->entityTypeManager
        ->getStorage('taxonomy_term')
        ->loadChildren($parent_id);

      // Use the helper function to get total count including subcategories
      $total_count = $this->getCategoryTotalCount($parent_id);

      // Build parent category entry (show even if empty, like Star Trek Wormhole)
      $build[$parent_id] = [
        '#type' => 'container',
        '#attributes' => ['class' => ['category-entry']],
      ];

      // Folder icon and parent category link
      $parent_link = [
        '#type' => 'link',
        '#title' => $parent_category->getName(),
        '#url' => \Drupal\Core\Url::fromRoute('hotlinks.category', ['category' => $parent_id]),
        '#attributes' => ['class' => ['category-main-link']],
      ];

      $build[$parent_id]['main'] = [
        '#markup' => '📁 ' . \Drupal::service('renderer')->render($parent_link) . ' (' . $total_count . ')',
      ];

      // Subcategories list (like Star Trek Wormhole format)
      if (!empty($children)) {
        $subcategory_links = [];
        
        // Sort children alphabetically
        $children_array = array_values($children);
        usort($children_array, function($a, $b) {
          return strcmp($a->getName(), $b->getName());
        });

        foreach ($children_array as $child) {
          // Use the helper function for child count too (includes any sub-subcategories)
          $child_count = $this->getCategoryTotalCount($child->id());
          
          $subcategory_links[] = [
            '#type' => 'link',
            '#title' => $child->getName() . ' (' . $child_count . ')',
            '#url' => \Drupal\Core\Url::fromRoute('hotlinks.category', ['category' => $child->id()]),
            '#attributes' => ['class' => ['subcategory-link']],
          ];
        }

        if (!empty($subcategory_links)) {
          $subcategory_markup = '';
          foreach ($subcategory_links as $index => $link) {
            if ($index > 0) {
              $subcategory_markup .= ', ';
            }
            $subcategory_markup .= \Drupal::service('renderer')->render($link);
          }
          $subcategory_markup .= ', ...';

          $build[$parent_id]['subcategories'] = [
            '#markup' => '<div class="subcategories-line">' . $subcategory_markup . '</div>',
          ];
        }
      }

      // Add description from taxonomy term if it exists
      $description = $parent_category->getDescription();
      if (!empty($description)) {
        $build[$parent_id]['description'] = [
          '#markup' => '<div class="category-description">' . $description . '</div>',
        ];
      }
    }

    return $build;
  }

  /**
   * Build subcategory list for category pages.
   */
  private function buildSubcategoryList($children, $hotlinks_by_category) {
    $subcategory_links = [];
    
    // Sort children alphabetically
    $children_array = array_values($children);
    usort($children_array, function($a, $b) {
      return strcmp($a->getName(), $b->getName());
    });

    foreach ($children_array as $child) {
      // Use the helper function to get total count including any sub-subcategories
      $child_count = $this->getCategoryTotalCount($child->id());
      
      $subcategory_links[] = [
        '#type' => 'link',
        '#title' => $child->getName() . ' (' . $child_count . ')',
        '#url' => \Drupal\Core\Url::fromRoute('hotlinks.category', ['category' => $child->id()]),
        '#attributes' => ['class' => ['subcategory-nav-link']],
      ];
    }

    if (!empty($subcategory_links)) {
      return [
        '#theme' => 'item_list',
        '#items' => $subcategory_links,
        '#attributes' => ['class' => ['subcategory-nav-list']],
        '#list_type' => 'ul',
      ];
    }

    return [];
  }

  /**
   * Get total hotlinks count for a category including all subcategories.
   * 
   * This uses the same logic as the helper function in hotlinks.module.
   */
  private function getCategoryTotalCount($category_id) {
    try {
      $term_storage = $this->entityTypeManager->getStorage('taxonomy_term');
      $node_storage = $this->entityTypeManager->getStorage('node');
      
      // Get all child term IDs using loadTree for better performance
      $child_terms = $term_storage->loadTree('hotlink_categories', $category_id);
      $category_ids = [$category_id];
      
      // Add all descendant category IDs
      foreach ($child_terms as $child_term) {
        $category_ids[] = $child_term->tid;
      }

      // Query for hotlinks in any of these categories
      $query = $node_storage->getQuery()
        ->condition('type', 'hotlink')
        ->condition('status', 1)
        ->condition('field_hotlink_category', $category_ids, 'IN')
        ->accessCheck(TRUE);

      return $query->count()->execute();
      
    } catch (\Exception $e) {
      \Drupal::logger('hotlinks')->error('Error calculating category count for @id: @message', [
        '@id' => $category_id,
        '@message' => $e->getMessage(),
      ]);
      return 0;
    }
  }

  /**
   * Group hotlinks by their categories.
   */
  private function groupHotlinksByCategory($hotlinks) {
    $grouped = [];

    foreach ($hotlinks as $hotlink) {
      try {
        $category_field = $hotlink->get('field_hotlink_category');
        if (!$category_field->isEmpty()) {
          foreach ($category_field as $category_item) {
            $category_id = $category_item->target_id;
            if (!isset($grouped[$category_id])) {
              $grouped[$category_id] = [];
            }
            $grouped[$category_id][] = $hotlink;
          }
        }
      } catch (\Exception $e) {
        \Drupal::logger('hotlinks')->error('Error grouping hotlink @nid: @message', [
          '@nid' => $hotlink->id(),
          '@message' => $e->getMessage(),
        ]);
      }
    }

    return $grouped;
  }

  /**
   * Filter hotlinks by category.
   */
  private function filterHotlinksByCategory($hotlinks, $category_id) {
    $filtered = [];

    foreach ($hotlinks as $hotlink) {
      try {
        $category_field = $hotlink->get('field_hotlink_category');
        if (!$category_field->isEmpty()) {
          foreach ($category_field as $category_item) {
            if ($category_item->target_id == $category_id) {
              $filtered[] = $hotlink;
              break;
            }
          }
        }
      } catch (\Exception $e) {
        \Drupal::logger('hotlinks')->error('Error filtering hotlink @nid: @message', [
          '@nid' => $hotlink->id(),
          '@message' => $e->getMessage(),
        ]);
      }
    }

    return $filtered;
  }

  /**
   * Build a hotlink item display using custom display mode.
   */
  private function buildHotlinkItem($hotlink) {
    try {
      // Check if the custom view mode exists and is enabled
      $view_modes = \Drupal::service('entity_display.repository')->getViewModes('node');
      
      if (isset($view_modes['hotlinks_index'])) {
        // Use the custom 'hotlinks_index' view mode
        $view_builder = \Drupal::entityTypeManager()->getViewBuilder('node');
        return $view_builder->view($hotlink, 'hotlinks_index');
      } else {
        // Fallback to custom rendering if view mode doesn't exist
        return $this->buildHotlinkItemFallback($hotlink);
      }

    } catch (\Exception $e) {
      \Drupal::logger('hotlinks')->error('Error building hotlink item: @message', ['@message' => $e->getMessage()]);
      
      // Fallback to basic display
      return $this->buildHotlinkItemFallback($hotlink);
    }
  }

  /**
   * Fallback method to build hotlink item display.
   */
  private function buildHotlinkItemFallback($hotlink) {
    try {
      $url_field = $hotlink->get('field_hotlink_url');
      $description_field = $hotlink->get('field_hotlink_description');

      if ($url_field->isEmpty()) {
        return ['#markup' => '<div class="hotlink-item">Invalid hotlink - no URL</div>'];
      }

      $url_item = $url_field->first();
      $link_title = $url_item->title ?: $hotlink->getTitle();
      $link_url = $url_item->getUrl();

      $description = '';
      if (!$description_field->isEmpty()) {
        $description_item = $description_field->first();
        $description = $description_item->value;
      }

      $item = [
        '#type' => 'container',
        '#attributes' => ['class' => ['hotlink-item', 'node--view-mode-hotlinks-index']],
      ];

      // Check if thumbnail field exists before trying to use it
      if ($hotlink->hasField('field_hotlink_thumbnail')) {
        $thumbnail_field = $hotlink->get('field_hotlink_thumbnail');
        
        // Add thumbnail if available
        if (!$thumbnail_field->isEmpty()) {
          $thumbnail_item = $thumbnail_field->first();
          $file = $thumbnail_item->entity;
          
          if ($file) {
            $item['thumbnail'] = [
              '#theme' => 'image_style',
              '#style_name' => 'thumbnail',
              '#uri' => $file->getFileUri(),
              '#alt' => $thumbnail_item->alt ?: $link_title,
              '#attributes' => ['class' => ['hotlink-thumbnail']],
            ];
          }
        } else {
          // Default thumbnail placeholder
          $item['thumbnail'] = [
            '#markup' => '<div class="hotlink-thumbnail-placeholder">🔗</div>',
          ];
        }

        $item['content'] = [
          '#type' => 'container',
          '#attributes' => ['class' => ['hotlink-content']],
        ];

        // Title links to node (like the custom view mode would)
        $item['content']['title'] = [
          '#type' => 'link',
          '#title' => $hotlink->getTitle(),
          '#url' => $hotlink->toUrl(),
          '#attributes' => ['class' => ['hotlink-node-link']],
        ];

        $item['content']['url'] = [
          '#type' => 'link',
          '#title' => $link_title,
          '#url' => $link_url,
          '#attributes' => [
            'target' => '_blank',
            'rel' => 'noopener noreferrer',
            'class' => ['hotlink-url'],
          ],
        ];

        if ($description) {
          $item['content']['description'] = [
            '#markup' => '<div class="hotlink-description">' . $description . '</div>',
          ];
        }
      } else {
        // Very basic fallback for when thumbnail field doesn't exist
        $item['title'] = [
          '#type' => 'link',
          '#title' => $hotlink->getTitle(),
          '#url' => $hotlink->toUrl(),
          '#attributes' => ['class' => ['hotlink-node-link']],
        ];

        $item['url'] = [
          '#type' => 'link',
          '#title' => $link_title,
          '#url' => $link_url,
          '#attributes' => [
            'target' => '_blank',
            'rel' => 'noopener noreferrer',
            'class' => ['hotlink-url'],
          ],
        ];

        if ($description) {
          $item['description'] = [
            '#markup' => '<div class="hotlink-description">' . $description . '</div>',
          ];
        }
      }

      return $item;

    } catch (\Exception $e) {
      \Drupal::logger('hotlinks')->error('Error in fallback hotlink display: @message', ['@message' => $e->getMessage()]);
      return ['#markup' => '<div class="hotlink-item-error">Error loading hotlink: ' . $hotlink->getTitle() . '</div>'];
    }
  }
}