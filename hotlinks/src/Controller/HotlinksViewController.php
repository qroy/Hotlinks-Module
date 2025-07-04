<?php

namespace Drupal\hotlinks\Controller;

use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\taxonomy\Entity\Term;
use Drupal\views\Views;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\Response;

/**
 * Controller for Hotlinks Views integration.
 */
class HotlinksViewController extends ControllerBase {

  /**
   * The entity type manager.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected $entityTypeManager;

  /**
   * Constructs a new HotlinksViewController object.
   *
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entity_type_manager
   *   The entity type manager.
   */
  public function __construct(EntityTypeManagerInterface $entity_type_manager) {
    $this->entityTypeManager = $entity_type_manager;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('entity_type.manager')
    );
  }

  /**
   * Display category page using Views.
   *
   * @param \Drupal\taxonomy\Entity\Term $category
   *   The taxonomy term for the category.
   *
   * @return array
   *   A render array.
   */
  public function categoryPage(Term $category) {
    $build = [];

    try {
      // Verify category belongs to hotlink_categories vocabulary
      if ($category->bundle() !== 'hotlink_categories') {
        throw new \InvalidArgumentException('Invalid category vocabulary');
      }

      // Build subcategories navigation - place at top with higher weight
      $children = $this->entityTypeManager
        ->getStorage('taxonomy_term')
        ->loadChildren($category->id());

      if (!empty($children)) {
        $build['subcategories'] = $this->buildSubcategoryNavigation($children);
        $build['subcategories']['#weight'] = -50; // Make sure it appears before the view
      }

      // Load the Views display
      $view = Views::getView('hotlinks_by_category');
      if ($view && $view->access('page_1')) {
        $view->setDisplay('page_1');
        $view->setArguments([$category->id()]);
        $view->preExecute();
        $view->execute();

        // Add the view output with proper weight
        $build['hotlinks_view'] = [
          '#type' => 'view',
          '#name' => 'hotlinks_by_category',
          '#display_id' => 'page_1',
          '#arguments' => [$category->id()],
          '#embed' => TRUE,
          '#weight' => 0,
        ];

        // Add category header information
        $build['category_header'] = [
          '#markup' => '<div class="category-header"><h1>' . $this->escapeOutput($category->getName()) . '</h1></div>',
          '#weight' => -60,
        ];
        
        // Add category description if it exists
        $description = $category->getDescription();
        if (!empty($description)) {
          $build['category_description'] = [
            '#markup' => '<div class="category-description">' . $description . '</div>',
            '#weight' => -55,
          ];
        }

        // Add additional category information
        $build['category_info'] = $this->buildCategoryInfo($category, $view);
      } else {
        $build['error'] = [
          '#markup' => '<p>' . $this->t('Unable to load category view.') . '</p>',
        ];
      }

      // Attach libraries
      $build['#attached']['library'][] = 'hotlinks/hotlinks.styles';
      if (\Drupal::moduleHandler()->moduleExists('hotlinks_reviews')) {
        $build['#attached']['library'][] = 'hotlinks_reviews/rating-display';
        $build['#attached']['library'][] = 'hotlinks_reviews/rating-widget';
      }

    } catch (\Exception $e) {
      $build['error'] = [
        '#markup' => '<p><strong>Error:</strong> ' . $this->t('Unable to load category page. Please check system logs.') . '</p>',
      ];
      \Drupal::logger('hotlinks')->error('Error in Views category controller: @message', ['@message' => $e->getMessage()]);
    }

    return $build;
  }

  /**
   * Display all hotlinks page using Views.
   *
   * @return array
   *   A render array.
   */
  public function allHotlinks() {
    $build = [];

    try {
      // Load the Views display for all hotlinks
      $view = Views::getView('hotlinks_by_category');
      if ($view && $view->access('page_2')) {
        $build['hotlinks_view'] = [
          '#type' => 'view',
          '#name' => 'hotlinks_by_category',
          '#display_id' => 'page_2',
          '#embed' => TRUE,
        ];

        // Add breadcrumb
        $build['breadcrumb'] = [
          '#markup' => '<nav class="breadcrumb"><a href="/hotlinks">' . $this->t('Hotlinks Index') . '</a> &gt; ' . $this->t('All Hotlinks') . '</nav>',
          '#weight' => -10,
        ];
      } else {
        $build['error'] = [
          '#markup' => '<p>' . $this->t('Unable to load hotlinks view.') . '</p>',
        ];
      }

      // Attach libraries
      $build['#attached']['library'][] = 'hotlinks/hotlinks.styles';
      if (\Drupal::moduleHandler()->moduleExists('hotlinks_reviews')) {
        $build['#attached']['library'][] = 'hotlinks_reviews/rating-display';
        $build['#attached']['library'][] = 'hotlinks_reviews/rating-widget';
      }

    } catch (\Exception $e) {
      $build['error'] = [
        '#markup' => '<p><strong>Error:</strong> ' . $this->t('Unable to load all hotlinks page.') . '</p>',
      ];
      \Drupal::logger('hotlinks')->error('Error in Views all hotlinks controller: @message', ['@message' => $e->getMessage()]);
    }

    return $build;
  }

  /**
   * Build subcategory navigation.
   *
   * @param array $children
   *   Array of child taxonomy terms.
   *
   * @return array
   *   A render array for subcategory navigation.
   */
  protected function buildSubcategoryNavigation(array $children) {
    $subcategory_links = [];

    // Sort children alphabetically
    $children_array = array_values($children);
    usort($children_array, function($a, $b) {
      return strcmp($a->getName(), $b->getName());
    });

    foreach ($children_array as $child) {
      try {
        // Get count for this subcategory using the same method as original controller
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
        '#type' => 'container',
        '#attributes' => ['class' => ['category-subcategories']],
        'title' => [
          '#markup' => '<h3>' . $this->t('Subcategories') . '</h3>',
        ],
        'links' => [
          '#theme' => 'item_list',
          '#items' => $subcategory_links,
          '#attributes' => ['class' => ['subcategory-nav-list']],
          '#list_type' => 'ul',
        ],
        '#weight' => -20,
      ];
    }

    return [];
  }

  /**
   * Build additional category information.
   *
   * @param \Drupal\taxonomy\Entity\Term $category
   *   The taxonomy term.
   * @param object $view
   *   The loaded view object.
   *
   * @return array
   *   A render array with category information.
   */
  protected function buildCategoryInfo(Term $category, $view) {
    $build = [];

    // Add category statistics
    $total_results = $view->total_rows ?? 0;
    
    if ($total_results > 0) {
      $build['stats'] = [
        '#markup' => '<div class="category-stats">' . 
          $this->formatPlural(
            $total_results,
            '1 hotlink in this category',
            '@count hotlinks in this category'
          ) . '</div>',
        '#weight' => 10,
      ];
    }

    // Add parent breadcrumb if this is a subcategory
    $term_storage = $this->entityTypeManager->getStorage('taxonomy_term');
    $parents = $term_storage->loadParents($category->id());
    
    if (!empty($parents)) {
      $breadcrumb_items = [];
      $breadcrumb_items[] = '<a href="/hotlinks">' . $this->t('Hotlinks Index') . '</a>';
      
      foreach ($parents as $parent) {
        $breadcrumb_items[] = '<a href="/hotlinks/category/' . $parent->id() . '">' . 
          $this->escapeOutput($parent->getName()) . '</a>';
      }
      
      $breadcrumb_items[] = $this->escapeOutput($category->getName());
      
      $build['breadcrumb'] = [
        '#markup' => '<nav class="breadcrumb">' . implode(' &gt; ', $breadcrumb_items) . '</nav>',
        '#weight' => -10,
      ];
    }

    return $build;
  }

  /**
   * Get total hotlinks count for a category including all subcategories.
   * 
   * @param int $category_id
   *   The category term ID.
   *
   * @return int
   *   The total count of hotlinks.
   */
  protected function getCategoryTotalCount($category_id) {
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
   * Title callback for category pages.
   *
   * @param \Drupal\taxonomy\Entity\Term $category
   *   The taxonomy term.
   *
   * @return string
   *   The page title.
   */
  public function categoryTitle(Term $category) {
    return $category->getName();
  }

  /**
   * Safely escape output to prevent XSS.
   *
   * @param string $text
   *   The text to escape.
   *
   * @return string
   *   The escaped text.
   */
  protected function escapeOutput($text) {
    return \Drupal\Component\Utility\Html::escape($text);
  }
}