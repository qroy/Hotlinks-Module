<?php

namespace Drupal\hotlinks_reviews\Plugin\Field\FieldWidget;

use Drupal\Core\Field\FieldItemListInterface;
use Drupal\Core\Field\WidgetBase;
use Drupal\Core\Form\FormStateInterface;

/**
 * Plugin implementation of the 'hotlinks_rating_widget' widget.
 *
 * @FieldWidget(
 *   id = "hotlinks_rating_widget",
 *   label = @Translation("Star Rating Widget"),
 *   field_types = {
 *     "integer"
 *   }
 * )
 */
class HotlinksRatingWidget extends WidgetBase {

  /**
   * {@inheritdoc}
   */
  public static function defaultSettings() {
    return [
      'max_rating' => 5,
      'placeholder' => 'Click to rate...',
    ] + parent::defaultSettings();
  }

  /**
   * {@inheritdoc}
   */
  public function settingsForm(array $form, FormStateInterface $form_state) {
    $elements = [];

    $elements['max_rating'] = [
      '#type' => 'number',
      '#title' => $this->t('Maximum rating'),
      '#default_value' => $this->getSetting('max_rating'),
      '#min' => 3,
      '#max' => 10,
      '#description' => $this->t('The maximum rating value (number of stars).'),
    ];

    $elements['placeholder'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Placeholder text'),
      '#default_value' => $this->getSetting('placeholder'),
      '#description' => $this->t('Text to show when no rating is selected.'),
    ];

    return $elements;
  }

  /**
   * {@inheritdoc}
   */
  public function settingsSummary() {
    $summary = [];
    $max_rating = $this->getSetting('max_rating');
    $placeholder = $this->getSetting('placeholder');

    $summary[] = $this->t('Max rating: @max', ['@max' => $max_rating]);
    if (!empty($placeholder)) {
      $summary[] = $this->t('Placeholder: @placeholder', ['@placeholder' => $placeholder]);
    }

    return $summary;
  }

  /**
   * {@inheritdoc}
   */
  public function formElement(FieldItemListInterface $items, $delta, array $element, array &$form, FormStateInterface $form_state) {
    $max_rating = $this->getSetting('max_rating');
    $current_value = isset($items[$delta]->value) ? (int) $items[$delta]->value : 0;

    $element += [
      '#type' => 'container',
      '#attributes' => [
        'class' => ['hotlinks-rating-widget'],
        'data-max-rating' => $max_rating,
      ],
    ];

    // Hidden input to store the actual value
    $element['value'] = [
      '#type' => 'hidden',
      '#default_value' => $current_value,
      '#attributes' => [
        'data-rating-input' => 'true',
      ],
    ];

    // Create star elements
    $stars_html = '';
    for ($i = 1; $i <= $max_rating; $i++) {
      $class = $i <= $current_value ? 'selected' : 'empty';
      $stars_html .= '<span class="rating-star ' . $class . '" data-rating="' . $i . '">★</span>';
    }

    $element['stars'] = [
      '#markup' => $stars_html,
    ];

    // Label for current rating
    $element['label'] = [
      '#markup' => '<span class="rating-label">' . $this->getSetting('placeholder') . '</span>',
    ];

    $element['#attached']['library'][] = 'hotlinks_reviews/rating-widget';

    return $element;
  }

  /**
   * {@inheritdoc}
   */
  public function massageFormValues(array $values, array $form, FormStateInterface $form_state) {
    foreach ($values as &$value) {
      if (isset($value['value'])) {
        $value = ['value' => (int) $value['value']];
      }
    }
    return $values;
  }

}