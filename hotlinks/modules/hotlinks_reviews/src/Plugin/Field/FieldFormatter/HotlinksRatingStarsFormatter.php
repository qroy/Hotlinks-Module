<?php

namespace Drupal\hotlinks_reviews\Plugin\Field\FieldFormatter;

use Drupal\Core\Field\FieldItemListInterface;
use Drupal\Core\Field\FormatterBase;
use Drupal\Core\Form\FormStateInterface;

/**
 * Plugin implementation of the 'hotlinks_rating_stars' formatter.
 *
 * @FieldFormatter(
 *   id = "hotlinks_rating_stars",
 *   label = @Translation("Rating Stars"),
 *   field_types = {
 *     "decimal",
 *     "integer"
 *   }
 * )
 */
class HotlinksRatingStarsFormatter extends FormatterBase {

  /**
   * {@inheritdoc}
   */
  public static function defaultSettings() {
    return [
      'max_rating' => 5,
      'show_count' => TRUE,
      'compact' => FALSE,
    ] + parent::defaultSettings();
  }

  /**
   * {@inheritdoc}
   */
  public function settingsForm(array $form, FormStateInterface $form_state) {
    $elements = parent::settingsForm($form, $form_state);

    $elements['max_rating'] = [
      '#type' => 'number',
      '#title' => $this->t('Maximum rating'),
      '#default_value' => $this->getSetting('max_rating'),
      '#min' => 3,
      '#max' => 10,
    ];

    $elements['show_count'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Show review count'),
      '#default_value' => $this->getSetting('show_count'),
    ];

    $elements['compact'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Compact display'),
      '#default_value' => $this->getSetting('compact'),
      '#description' => $this->t('Use smaller stars for compact layouts.'),
    ];

    return $elements;
  }

  /**
   * {@inheritdoc}
   */
  public function settingsSummary() {
    $summary = [];
    $max_rating = $this->getSetting('max_rating');
    $show_count = $this->getSetting('show_count');
    $compact = $this->getSetting('compact');

    $summary[] = $this->t('Max: @max stars', ['@max' => $max_rating]);
    
    if ($show_count) {
      $summary[] = $this->t('Show count');
    }
    
    if ($compact) {
      $summary[] = $this->t('Compact');
    }

    return $summary;
  }

  /**
   * {@inheritdoc}
   */
  public function viewElements(FieldItemListInterface $items, $langcode) {
    $elements = [];
    $entity = $items->getEntity();
    
    foreach ($items as $delta => $item) {
      $rating = (float) $item->value;
      $max_rating = $this->getSetting('max_rating');
      $show_count = $this->getSetting('show_count');
      $compact = $this->getSetting('compact');
      
      // Get review count if showing count
      $count = 0;
      if ($show_count && $entity->hasField('field_hotlink_review_count')) {
        $count_field = $entity->get('field_hotlink_review_count');
        if (!$count_field->isEmpty()) {
          $count = $count_field->value ?: 0;
        }
      }

      // Create the element
      $elements[$delta] = [
        '#theme' => 'hotlinks_rating_stars',
        '#rating' => $rating,
        '#count' => $count,
        '#max_rating' => $max_rating,
        '#compact' => $compact,
        '#attributes' => [
          'class' => ['field-rating-stars'],
        ],
        '#attached' => [
          'library' => ['hotlinks_reviews/rating-display'],
        ],
        '#cache' => [
          'tags' => $entity->getCacheTags(),
        ],
      ];
    }

    return $elements;
  }

}