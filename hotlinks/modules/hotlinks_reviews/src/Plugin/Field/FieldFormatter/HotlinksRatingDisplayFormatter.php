<?php

namespace Drupal\hotlinks_reviews\Plugin\Field\FieldFormatter;

use Drupal\Core\Field\FieldItemListInterface;
use Drupal\Core\Field\FormatterBase;
use Drupal\Core\Form\FormStateInterface;

/**
 * Plugin implementation of the 'hotlinks_rating_display' formatter.
 *
 * @FieldFormatter(
 *   id = "hotlinks_rating_display",
 *   label = @Translation("Rating Display"),
 *   field_types = {
 *     "decimal",
 *     "integer"
 *   }
 * )
 */
class HotlinksRatingDisplayFormatter extends FormatterBase {

  /**
   * {@inheritdoc}
   */
  public static function defaultSettings() {
    return [
      'show_count' => TRUE,
      'show_numerical' => FALSE,
    ] + parent::defaultSettings();
  }

  /**
   * {@inheritdoc}
   */
  public function settingsForm(array $form, FormStateInterface $form_state) {
    $elements = parent::settingsForm($form, $form_state);

    $elements['show_count'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Show review count'),
      '#default_value' => $this->getSetting('show_count'),
    ];

    $elements['show_numerical'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Show numerical rating'),
      '#default_value' => $this->getSetting('show_numerical'),
      '#description' => $this->t('Display the numerical rating alongside stars.'),
    ];

    return $elements;
  }

  /**
   * {@inheritdoc}
   */
  public function viewElements(FieldItemListInterface $items, $langcode) {
    $elements = [];
    $entity = $items->getEntity();
    
    foreach ($items as $delta => $item) {
      $rating = (float) $item->value;
      $show_count = $this->getSetting('show_count');
      $show_numerical = $this->getSetting('show_numerical');
      
      // Get review count
      $count = 0;
      if ($show_count && $entity->hasField('field_hotlink_review_count')) {
        $count_field = $entity->get('field_hotlink_review_count');
        if (!$count_field->isEmpty()) {
          $count = $count_field->value ?: 0;
        }
      }

      if ($rating > 0) {
        $build = [
          '#type' => 'container',
          '#attributes' => ['class' => ['hotlinks-rating-display']],
        ];

        $build['stars'] = [
          '#theme' => 'hotlinks_rating_stars',
          '#rating' => $rating,
          '#count' => $show_count ? $count : 0,
          '#max_rating' => 5,
          '#compact' => FALSE,
        ];

        if ($show_numerical) {
          $build['numerical'] = [
            '#markup' => '<span class="rating-numerical">' . number_format($rating, 1) . '/5.0</span>',
          ];
        }

        $elements[$delta] = $build;
      } else {
        $elements[$delta] = [
          '#markup' => '<span class="hotlinks-no-ratings">' . $this->t('No ratings yet') . '</span>',
        ];
      }
    }

    return $elements;
  }

}