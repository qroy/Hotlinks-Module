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
      // Get all hotlinks with proper error handling
      $query = $this->entityTypeManager
        ->getStorage('node')
        ->getQuery()
        ->condition('type', 'hotlink')
        ->condition('status', 1)
        ->accessCheck(TRUE);

      $nids = $query->execute();
      
      if (empty($nids)) {
        $build['no_hotlinks'] = [
          '#markup' => '<p>' . $this->t('No hotlinks found. <a href="@url">Create your first hotlink</a>.', [
            '@url' => \Drupal\Core\Url::fromRoute('node.add', ['node_type' => 'hotlink'])->toString(),
          ]) . '</p>',
        ];
        return $build;
      }

      $hotlinks = $this->entityTypeManager->getStorage('node')->loadMultiple($nids);

      // Group hotlinks by category with better error handling
      $hotlinks_by_category = $this->groupHotlinksByCategory($hotlinks);

      // Get all categories with error handling
      $categories = $this->loadCategories();

      if (empty($categories)) {
        $build['no_categories'] = [
          '#markup' => '<p>' . $this->t('No categories found. <a href="@url">Create categories</a> to organize your hotlinks.', [
            '@url' => \Drupal\Core\Url::fromRoute('entity.taxonomy_vocabulary.overview_form', ['taxonomy_vocabulary' => 'hotlink_categories'])->toString(),
          ]) . '</p>',
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
        '#markup' => '<p><strong>Error:</strong> ' . $this->t('Unable to load hotlinks index. Please check system logs.') . '</p>',
      ];
      \Drupal::logger('hotlinks')->error('Error in index: @message', ['@message' => $e->getMessage()]);
    }

    return $build;
  }

  public function category(Term $category) {
    $build = [];

    try {
      // Verify category belongs to hotlink_categories vocabulary
      if ($category->bundle() !== 'hotlink_categories') {
        throw new \InvalidArgumentException('Invalid category vocabulary');
      }

      // Get all hotlinks with proper query optimization
      $query = $this->entityTypeManager
        ->getStorage('node')
        ->getQuery()
        ->condition('type', 'hotlink')
        ->condition('status', 1)
        ->accessCheck(TRUE)
        ->sort('title', 'ASC');

      $nids = $query->execute();
      
      if (empty($nids)) {
        $build['no_hotlinks'] = [
          '#markup' => '<p>' . $this->t('No hotlinks found in any category.') . '</p>',
        ];
        return $build;
      }

      $hotlinks = $this->entityTypeManager->getStorage('node')->loadMultiple($nids);

      // Filter hotlinks for this specific category and its children
      $category_hotlinks = $this->filterHotlinksByCategory($hotlinks, $category->id(), TRUE);

      // Get child categories
      $children = $this->entityTypeManager
        ->getStorage('taxonomy_term')
        ->loadChildren($category->id());

      // Build category page in Star Trek Wormhole style
      $build['category_header'] = [
        '#markup' => '<h2>' . $this->escapeOutput($category->getName()) . '</h2>',
      ];

      // Add category description if available
      $description = $category->getDescription();
      if (!empty($description)) {
        $build['category_description'] = [
          '#markup' => '<div class="category-description">' . $description . '</div>',
        ];
      }

      // Show subcategories at top (like Star Trek Wormhole)
      if (!empty($children)) {
        $hotlinks_by_category = $this->groupHotlinksByCategory($hotlinks);
        $build['subcategories'] = [
          '#type' => 'container',
          '#attributes' => ['class' => ['category-subcategories']],
          'content' => $this->buildSubcategoryList($children, $hotlinks_by_category),
        ];
      }

      // Show links in this category using proper view mode rendering
      if (!empty($category_hotlinks)) {
        $build['links'] = [
          '#type' => 'container',
          '#attributes' => ['class' => ['category-links']],
          'title' => [
            '#markup' => '<h3>' . $this->t('Links in @category', ['@category' => $category->getName()]) . '</h3>',
          ],
          'list' => [
            '#theme' => 'item_list',
            '#items' => [],
            '#attributes' => ['class' => ['hotlinks-list']],
          ],
        ];

        // Use the view builder to render hotlinks with proper display mode
        $view_builder = $this->entityTypeManager->getViewBuilder('node');
        
        foreach ($category_hotlinks as $hotlink) {
          try {
            // Render using the hotlinks_index view mode for consistency
            $rendered_hotlink = $view_builder->view($hotlink, 'hotlinks_index');
            
            // Ensure rating libraries are attached
            $rendered_hotlink['#attached']['library'][] = 'hotlinks_reviews/rating-display';
            
            // Add AJAX rating widget if user can rate and reviews module is enabled
            if (\Drupal::moduleHandler()->moduleExists('hotlinks_reviews') && 
                \Drupal::currentUser()->hasPermission('rate hotlinks')) {
              
              $reviews_service = \Drupal::service('hotlinks_reviews.service');
              $user_rating = $reviews_service->getUserRating($hotlink->id());
              
              // Add interactive rating widget
              $rendered_hotlink['rating_widget'] = [
                '#theme' => 'hotlinks_rating_widget',
                '#rating' => $user_rating ?: 0,
                '#max_rating' => 5,
                '#name' => 'user_rating_' . $hotlink->id(),
                '#node_id' => $hotlink->id(),
                '#weight' => 10,
                '#attached' => [
                  'library' => ['hotlinks_reviews/rating-widget'],
                  'drupalSettings' => [
                    'hotlinksReviews' => [
                      'nodeId' => $hotlink->id(),
                      'useStarTrekLabels' => \Drupal::config('hotlinks.settings')->get('use_star_trek_labels') ?: FALSE,
                    ],
                  ],
                ],
              ];
            }
            
            $build['links']['list']['#items'][] = ['#markup' => \Drupal::service('renderer')->render($rendered_hotlink)];
            
          } catch (\Exception $e) {
            \Drupal::logger('hotlinks')->error('Error rendering hotlink @id in category: @error', [
              '@id' => $hotlink->id(),
              '@error' => $e->getMessage(),
            ]);
            // Continue with other hotlinks even if one fails
          }
        }
      } else {
        $build['no_links'] = [
          '#markup' => '<p>' . $this->t('No links found in this category.') . '</p>',
        ];
      }

      // Attach required libraries
      $build['#attached']['library'][] = 'hotlinks/hotlinks.styles';
      if (\Drupal::moduleHandler()->moduleExists('hotlinks_reviews')) {
        $build['#attached']['library'][] = 'hotlinks_reviews/rating-display';
        $build['#attached']['library'][] = 'hotlinks_reviews/rating-widget';
      }

    } catch (\Exception $e) {
      $build['error'] = [
        '#markup' => '<p><strong>Error:</strong> ' . $this->t('Unable to load category page. Please check system logs.') . '</p>',
      ];
      \Drupal::logger('hotlinks')->error('Error in category: @message', ['@message' => $e->getMessage()]);
    }

    return $build;
  }

  public function categoryTitle(Term $category) {
    return $this->t('@category', ['@category' => $category->getName()]);
  }

  /**
   * Load categories with error handling.
   */
  private function loadCategories() {
    try {
      $categories = $this->entityTypeManager
        ->getStorage('taxonomy_term')
        ->loadByProperties(['vid' => 'hotlink_categories']);
      
      return $categories;
    } catch (\Exception $e) {
      \Drupal::logger('hotlinks')->error('Error loading categories: @message', ['@message' => $e->getMessage()]);
      return [];
    }
  }

  /**
   * Build front page categories in Star Trek Wormhole style.
   */
  private function buildFrontPageCategories($categories, $hotlinks_by_category) {
    $build = [];

    // Get parent categories (those without parents) with optimized loading
    $parent_categories = [];
    $term_storage = $this->entityTypeManager->getStorage('taxonomy_term');
    
    foreach ($categories as $category) {
      try {
        $parents = $term_storage->loadParents($category->id());
        if (empty($parents)) {
          $parent_categories[] = $category;
        }
      } catch (\Exception $e) {
        \Drupal::logger('hotlinks')->warning('Error checking parents for category @id: @message', [
          '@id' => $category->id(),
          '@message' => $e->getMessage(),
        ]);
      }
    }

    // Sort parent categories alphabetically
    usort($parent_categories, function($a, $b) {
      return strcmp($a->getName(), $b->getName());
    });

    foreach ($parent_categories as $parent_category) {
      $parent_id = $parent_category->id();
      
      try {
        // Get children with error handling
        $children = $term_storage->loadChildren($parent_id);

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
          '#title' => $this->escapeOutput($parent_category->getName()),
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
            // Use the helper function for child count too
            $child_count = $this->getCategoryTotalCount($child->id());
            
            $subcategory_links[] = [
              '#type' => 'link',
              '#title' => $this->escapeOutput($child->getName()) . ' (' . $child_count . ')',
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
        
      } catch (\Exception $e) {
        \Drupal::logger('hotlinks')->error('Error building category @name: @message', [
          '@name' => $parent_category->getName(),
          '@message' => $e->getMessage(),
        ]);
        // Continue with other categories even if one fails
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
      try {
        // Use the helper function to get total count including any sub-subcategories
        $child_count = $this->getCategoryTotalCount($child->id());
        
        $subcategory_links[] = [
          '#type' => 'link',
          '#title' => $this->escapeOutput($child->getName()) . ' (' . $child_count . ')',
          '#url' => \Drupal\Core\Url::fromRoute('hotlinks.category', ['category' => $child->id()]),
          '#attributes' => ['class' => ['subcategory-nav-link']],
        ];
      } catch (\Exception $e) {
        \Drupal::logger('hotlinks')->warning('Error building subcategory link for @name: @message', [
          '@name' => $child->getName(),
          '@message' => $e->getMessage(),
        ]);
      }
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
   * Fixed to prevent double-counting and improve performance.
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
      // Use DISTINCT to prevent double-counting when hotlinks are in multiple categories
      $query = $node_storage->getQuery()
        ->condition('type', 'hotlink')
        ->condition('status', 1)
        ->accessCheck(TRUE);
      
      // Only add category condition if hotlink has the category field
      $sample_hotlink = $node_storage->loadByProperties(['type' => 'hotlink']);
      if (!empty($sample_hotlink)) {
        $sample = reset($sample_hotlink);
        if ($sample->hasField('field_hotlink_category')) {
          $query->condition('field_hotlink_category', $category_ids, 'IN');
        } else {
          \Drupal::logger('hotlinks')->warning('Hotlink nodes missing category field');
          return 0;
        }
      }

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
   * Group hotlinks by their categories with improved error handling.
   */
  private function groupHotlinksByCategory($hotlinks) {
    $grouped = [];

    foreach ($hotlinks as $hotlink) {
      try {
        // Check if the category field exists
        if (!$hotlink->hasField('field_hotlink_category')) {
          \Drupal::logger('hotlinks')->warning('Hotlink @nid missing category field', ['@nid' => $hotlink->id()]);
          continue;
        }

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
   * Filter hotlinks by category with support for including children.
   */
  private function filterHotlinksByCategory($hotlinks, $category_id, $include_children = FALSE) {
    $filtered = [];
    $target_categories = [$category_id];

    // If including children, get all descendant category IDs
    if ($include_children) {
      try {
        $term_storage = $this->entityTypeManager->getStorage('taxonomy_term');
        $child_terms = $term_storage->loadTree('hotlink_categories', $category_id);
        foreach ($child_terms as $child_term) {
          $target_categories[] = $child_term->tid;
        }
      } catch (\Exception $e) {
        \Drupal::logger('hotlinks')->error('Error loading child categories for @id: @message', [
          '@id' => $category_id,
          '@message' => $e->getMessage(),
        ]);
      }
    }

    foreach ($hotlinks as $hotlink) {
      try {
        // Check if the category field exists
        if (!$hotlink->hasField('field_hotlink_category')) {
          continue;
        }

        $category_field = $hotlink->get('field_hotlink_category');
        if (!$category_field->isEmpty()) {
          foreach ($category_field as $category_item) {
            if (in_array($category_item->target_id, $target_categories)) {
              $filtered[] = $hotlink;
              break; // Don't add the same hotlink multiple times
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
   * Safely escape output to prevent XSS.
   */
  private function escapeOutput($text) {
    return \Drupal\Component\Utility\Html::escape($text);
  }
}