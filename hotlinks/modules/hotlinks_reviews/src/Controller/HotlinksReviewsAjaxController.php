<?php

namespace Drupal\hotlinks_reviews\Controller;

use Drupal\Core\Controller\ControllerBase;
use Drupal\node\NodeInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;

/**
 * AJAX controller for Hotlinks Reviews.
 */
class HotlinksReviewsAjaxController extends ControllerBase {

  /**
   * AJAX callback for rating a hotlink.
   */
  public function rate(NodeInterface $node, Request $request) {
    if ($node->bundle() !== 'hotlink') {
      return new JsonResponse(['status' => 'error', 'message' => 'Invalid node type.'], 400);
    }

    if (!$this->currentUser()->hasPermission('rate hotlinks')) {
      return new JsonResponse(['status' => 'error', 'message' => 'Access denied.'], 403);
    }

    $rating = (int) $request->request->get('rating');
    if ($rating < 1 || $rating > 5) {
      return new JsonResponse(['status' => 'error', 'message' => 'Invalid rating.'], 400);
    }

    try {
      $user_id = $this->currentUser()->id();
      
      // Save rating to state
      $ratings = \Drupal::state()->get('hotlinks_reviews.ratings', []);
      $ratings[$node->id()][$user_id] = [
        'rating' => $rating,
        'timestamp' => \Drupal::time()->getRequestTime(),
      ];
      \Drupal::state()->set('hotlinks_reviews.ratings', $ratings);
      
      // Recalculate average
      $total = 0;
      $count = 0;
      foreach ($ratings[$node->id()] as $user_rating) {
        $total += $user_rating['rating'];
        $count++;
      }
      $average = $count > 0 ? round($total / $count, 2) : 0;
      
      // Update node
      $node->set('field_hotlink_avg_rating', $average);
      $node->set('field_hotlink_review_count', $count);
      $node->save();

      // Log activity
      \Drupal::logger('hotlinks')->info('User @user rated hotlink "@title" (@rating stars)', [
        '@user' => $this->currentUser()->getDisplayName(),
        '@title' => $node->getTitle(),
        '@rating' => $rating,
      ]);

      return new JsonResponse([
        'status' => 'success',
        'message' => 'Rating saved successfully.',
        'newAverage' => $average,
        'reviewCount' => $count,
      ]);

    } catch (\Exception $e) {
      \Drupal::logger('hotlinks_reviews')->error('Error saving rating: @message', ['@message' => $e->getMessage()]);
      return new JsonResponse(['status' => 'error', 'message' => 'Error saving rating.'], 500);
    }
  }

  /**
   * AJAX callback for submitting a review.
   */
  public function review(NodeInterface $node, Request $request) {
    if ($node->bundle() !== 'hotlink') {
      return new JsonResponse(['status' => 'error', 'message' => 'Invalid node type.'], 400);
    }

    if (!$this->currentUser()->hasPermission('review hotlinks')) {
      return new JsonResponse(['status' => 'error', 'message' => 'Access denied.'], 403);
    }

    $review_text = trim($request->request->get('review'));
    $rating = (int) $request->request->get('rating');
    
    if (empty($review_text)) {
      return new JsonResponse(['status' => 'error', 'message' => 'Review text is required.'], 400);
    }

    try {
      $user_id = $this->currentUser()->id();
      
      // Save review to state
      $reviews = \Drupal::state()->get('hotlinks_reviews.reviews', []);
      $reviews[$node->id()][$user_id] = [
        'review' => $review_text,
        'rating' => $rating,
        'timestamp' => \Drupal::time()->getRequestTime(),
        'user_name' => $this->currentUser()->getDisplayName(),
      ];
      \Drupal::state()->set('hotlinks_reviews.reviews', $reviews);

      return new JsonResponse([
        'status' => 'success',
        'message' => 'Review saved successfully.',
      ]);

    } catch (\Exception $e) {
      \Drupal::logger('hotlinks_reviews')->error('Error saving review: @message', ['@message' => $e->getMessage()]);
      return new JsonResponse(['status' => 'error', 'message' => 'Error saving review.'], 500);
    }
  }

}