<?php

namespace Drupal\hotlinks_reviews\Plugin\Field\FieldFormatter;

use Drupal\Core\Field\FieldItemListInterface;
use Drupal\Core\Field\FormatterBase;

/**
 * Plugin implementation of the 'hotlinks_user_rating_display' formatter.
 *
 * @FieldFormatter(
 *   id = "hotlinks_user_rating_display",
 *   label = @Translation("User Rating Display"),
 *   field_types = {
 *     "integer"
 *   }
 * )
 */
class HotlinksUserRatingDisplayFormatter extends FormatterBase {

  /**
   * {@inheritdoc}
   */
  public function viewElements(FieldItemListInterface $items, $langcode) {
    $elements = [];
    
    foreach ($items as $delta => $item) {
      $rating = (int) $item->value;
      
      if ($rating > 0) {
        $elements[$delta] = [
          '#markup' => '<div class="user-rating">' . 
            $this->t('Your rating: ') . 
            '<span class="user-rating-stars">' . str_repeat('★', $rating) . str_repeat('☆', 5 - $rating) . '</span>' .
            '</div>',
        ];
      } else {
        $elements[$delta] = [
          '#markup' => '<div class="user-rating">' . $this->t('You have not rated this link yet.') . '</div>',
        ];
      }
    }

    return $elements;
  }

}