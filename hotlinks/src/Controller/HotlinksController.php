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

    $tree = $this->entityTypeManager
      ->getStorage('taxonomy_term')
      ->loadTree('hotlink_categories', 0, NULL, TRUE);

    if (empty($tree)) {
      $build['no_categories'] = [
        '#markup' => '<p>' . $this->t('No categories created yet.') . '</p>',
      ];
      return $build;
    }

    $build['categories'] = [
      '#type' => 'container',
      '#attributes' => ['class' => ['hotlinks-categories']],
    ];

    $this->buildCategoryTree($build['categories'], $tree);
    $build['#attached']['library'][] = 'hotlinks/hotlinks.styles';

    return $build;
  }

  public function category(Term $category) {
    $build = [];

    $hotlinks = $this->getHotlinksByCategory($category->id(), TRUE);

    if (empty($hotlinks)) {
      $build['no_links'] = [
        '#markup' => '<p>' . $this->t('No hotlinks found.') . '</p>',
      ];
      return $build;
    }

    $build['links'] = [
      '#theme' => 'item_list',
      '#items' => [],
      '#attributes' => ['class' => ['hotlinks-list']],
    ];

    foreach ($hotlinks as $hotlink) {
      $item = $this->buildHotlinkItem($hotlink);
      $build['links']['#items'][] = ['#markup' => \Drupal::service('renderer')->render($item)];
    }

    $build['#attached']['library'][] = 'hotlinks/hotlinks.styles';
    return $build;
  }

  public function categoryTitle(Term $category) {
    return $this->t('Hotlinks: @category', ['@category' => $category->getName()]);
  }

  private function getHotlinksByCategory($category_id, $include_children = FALSE) {
    $category_ids = [$category_id];
    
    if ($include_children) {
      $child_terms = $this->entityTypeManager
        ->getStorage('taxonomy_term')
        ->loadTree('hotlink_categories', $category_id);
      
      foreach ($child_terms as $child_term) {
        $category_ids[] = $child_term->tid;
      }
    }

    $query = $this->entityTypeManager
      ->getStorage('node')
      ->getQuery()
      ->condition('type', 'hotlink')
      ->condition('status', 1)
      ->condition('field_hotlink_category', $category_ids, 'IN')
      ->sort('title', 'ASC')
      ->accessCheck(TRUE);

    $nids = $query->execute();

    if (empty($nids)) {
      return [];
    }

    return $this->entityTypeManager
      ->getStorage('node')
      ->loadMultiple($nids);
  }

  private function buildCategoryTree(array &$build, array $tree, $parent_id = 0, $depth = 0) {
    foreach ($tree as $term) {
      if ($term->parents[0] == $parent_id) {
        $term_id = $term->tid;
        
        $direct_hotlinks = $this->getHotlinksByCategory($term_id, FALSE);
        $total_hotlinks = $this->getHotlinksByCategory($term_id, TRUE);
        $total_count = count($total_hotlinks);
        
        $has_children = FALSE;
        foreach ($tree as $potential_child) {
          if (in_array($term_id, $potential_child->parents)) {
            $has_children = TRUE;
            break;
          }
        }

        if ($total_count > 0 || $has_children) {
          $category_classes = ['hotlinks-category'];
          if ($depth > 0) {
            $category_classes[] = 'hotlinks-subcategory';
          }

          $build[$term_id] = [
            '#type' => 'details',
            '#title' => $term->getName() . ' (' . $total_count . ')',
            '#open' => FALSE,
            '#attributes' => ['class' => $category_classes],
          ];

          if (!empty($direct_hotlinks)) {
            $build[$term_id]['links'] = [
              '#theme' => 'item_list',
              '#items' => [],
              '#attributes' => ['class' => ['hotlinks-list']],
            ];

            foreach ($direct_hotlinks as $hotlink) {
              $item = $this->buildHotlinkItem($hotlink);
              $build[$term_id]['links']['#items'][] = ['#markup' => \Drupal::service('renderer')->render($item)];
            }
          }

          if ($has_children) {
            $build[$term_id]['subcategories'] = [
              '#type' => 'container',
              '#attributes' => ['class' => ['hotlinks-subcategories']],
            ];
            
            $this->buildCategoryTree($build[$term_id]['subcategories'], $tree, $term_id, $depth + 1);
          }
        }
      }
    }
  }

  private function buildHotlinkItem($hotlink) {
    $url_field = $hotlink->get('field_hotlink_url')->first();
    $description_field = $hotlink->get('field_hotlink_description')->first();
    
    $link_title = $url_field->title ?: $hotlink->getTitle();
    $link_url = $url_field->getUrl();
    $description = $description_field ? $description_field->value : '';

    $item = [
      '#type' => 'container',
      '#attributes' => ['class' => ['hotlink-item']],
    ];

    $item['link'] = [
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

    return $item;
  }
}