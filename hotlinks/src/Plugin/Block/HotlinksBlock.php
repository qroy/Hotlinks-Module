<?php
namespace Drupal\hotlinks\Plugin\Block;
use Drupal\Core\Block\BlockBase;
use Drupal\Core\Cache\Cache;
use Drupal\Component\Utility\Xss;

/**
 * Provides a block to list hotlinks grouped by category.
 *
 * @Block(
 *   id = "hotlinks_block",
 *   admin_label = @Translation("Hotlinks list"),
 * )
 */
class HotlinksBlock extends BlockBase {
  /**
   * {@inheritdoc}
   */
  public function build() {
    $items = [];
    // Load published Link nodes with access check.
    $nids = \Drupal::entityQuery('node')
      ->condition('type', 'link')
      ->accessCheck(TRUE)
      ->execute();
    $nodes = \Drupal::entityTypeManager()->getStorage('node')->loadMultiple($nids);

    // Group by category term name.
    foreach ($nodes as $node) {
      /** @var \Drupal\taxonomy\Entity\Term $term */
      foreach ($node->get('field_hotlink_category')->referencedEntities() as $term) {
        $items[$term->label()][] = $node;
      }
    }

    // Sort categories alphabetically.
    ksort($items);
    $render = [];

    foreach ($items as $category => $links) {
      $list = [];
      // Sort links by title.
      usort($links, function($a, $b) {
        return strcasecmp($a->label(), $b->label());
      });
      foreach ($links as $link) {
        $url = '/hotlinks/redirect/' . $link->id();
        $title = Xss::filter($link->label());
        $markup = '<a href="' . $url . '">' . $title . '</a>';
        $list[] = ['#markup' => $markup];
      }
      $render[] = [
        '#type' => 'container',
        'title' => ['#markup' => '<h3>' . Xss::filter($category) . '</h3>'],
        'links' => [
          '#theme' => 'item_list',
          '#items' => $list,
        ],
      ];
    }

    return [
      '#type' => 'container',
      '#attributes' => ['class' => ['hotlinks-list']],
      'content' => $render,
      '#cache' => [
        'tags' => ['node_list', 'taxonomy_term_list'],
      ],
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function getCacheTags() {
    return Cache::mergeTags(parent::getCacheTags(), ['node_list', 'taxonomy_term_list']);
  }
}
