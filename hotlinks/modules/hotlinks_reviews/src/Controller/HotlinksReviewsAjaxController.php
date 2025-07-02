<?php

namespace Drupal\hotlinks_reviews\Controller;

use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\node\NodeInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;

/**
 * AJAX controller for hotlinks reviews.
 */
class HotlinksReviewsAjaxController extends ControllerBase {

  /**
   * The entity type manager.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected $entityTypeManager;

  /**
   * Constructs a new HotlinksReviewsAjaxController object.
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
   * AJAX callback for rating a hotlink.
   */
  public function rate(NodeInterface $node, Request $request) {
    // Verify this is a hotlink node
    if ($node->bundle() !== 'hotlink') {
      return new JsonResponse([
        'status' => 'error',
        'message' => $this->t('Invalid node type.'),
      ], 400);
    }

    // Check permissions
    if (!$this->currentUser()->hasPermission('rate hotlinks')) {
      return new JsonResponse([
        'status' => 'error',
        'message' => $this->t('Access denied.'),
      ], 403);
    }

    // Get rating from request
    $rating = (int) $request->request->get('rating');
    
    if ($rating < 1 || $rating > 5) {
      return new JsonResponse([
        'status' => 'error',
        'message' => $this->t('Invalid rating value.'),
      ], 400);
    }

    try {
      // In a full implementation, you would:
      // 1. Check if user already rated this node
      // 2. Insert/update rating in a separate user_ratings table
      // 3. Recalculate average rating
      // 4. Update the node's aggregate fields
      
      // For this example, we'll simulate the process
      $user_id = $this->currentUser()->id();
      
      // Check if user can rate this node
      if (!hotlinks_reviews_user_can_rate($node->id(), $user_id)) {
        return new JsonResponse([
          'status' => 'error',
          'message' => $this->t('You cannot rate this link.'),
        ], 403);
      }

      // Simulate saving the rating (in production, save to user_ratings table)
      $this->saveUserRating($node->id(), $user_id, $rating);
      
      // Recalculate averages
      $new_average = $this->recalculateAverageRating($node->id());
      $review_count = $this->getReviewCount($node->id());
      
      // Update the node
      $node->set('field_hotlink_avg_rating', $new_average);
      $node->set('field_hotlink_review_count', $review_count);
      $node->save();

      // Generate new average rating HTML
      $view_builder = $this->entityTypeManager->getViewBuilder('node');
      $average_display = $view_builder->viewField($node->get('field_hotlink_avg_rating'), [
        'type' => 'hotlinks_rating_stars',
        'settings' => [
          'show_count' => TRUE,
          'compact' => FALSE,
        ],
      ]);

      return new JsonResponse([
        'status' => 'success',
        'message' => $this->t('Rating saved successfully.'),
        'newAverage' => $new_average,
        'reviewCount' => $review_count,
        'newAverageHtml' => \Drupal::service('renderer')->render($average_display),
      ]);

    } catch (\Exception $e) {
      \Drupal::logger('hotlinks_reviews')->error('Error saving rating: @message', [
        '@message' => $e->getMessage(),
      ]);

      return new JsonResponse([
        'status' => 'error',
        'message' => $this->t('An error occurred while saving your rating.'),
      ], 500);
    }
  }

  /**
   * AJAX callback for submitting a review.
   */
  public function review(NodeInterface $node, Request $request) {
    // Verify this is a hotlink node
    if ($node->bundle() !== 'hotlink') {
      return new JsonResponse([
        'status' => 'error',
        'message' => $this->t('Invalid node type.'),
      ], 400);
    }

    // Check permissions
    if (!$this->currentUser()->hasPermission('review hotlinks')) {
      return new JsonResponse([
        'status' => 'error',
        'message' => $this->t('Access denied.'),
      ], 403);
    }

    // Get review data from request
    $review_text = trim($request->request->get('review'));
    $rating = (int) $request->request->get('rating');

    if (empty($review_text)) {
      return new JsonResponse([
        'status' => 'error',
        'message' => $this->t('Review text is required.'),
      ], 400);
    }

    if ($rating < 1 || $rating > 5) {
      return new JsonResponse([
        'status' => 'error',
        'message' => $this->t('Invalid rating value.'),
      ], 400);
    }

    try {
      $user_id = $this->currentUser()->id();
      
      // Save the review (in production, save to reviews table)
      $this->saveUserReview($node->id(), $user_id, $rating, $review_text);
      
      // Update aggregates
      $new_average = $this->recalculateAverageRating($node->id());
      $review_count = $this->getReviewCount($node->id());
      
      // Update the node
      $node->set('field_hotlink_avg_rating', $new_average);
      $node->set('field_hotlink_review_count', $review_count);
      $node->save();

      return new JsonResponse([
        'status' => 'success',
        'message' => $this->t('Review submitted successfully.'),
        'newAverage' => $new_average,
        'reviewCount' => $review_count,
      ]);

    } catch (\Exception $e) {
      \Drupal::logger('hotlinks_reviews')->error('Error saving review: @message', [
        '@message' => $e->getMessage(),
      ]);

      return new JsonResponse([
        'status' => 'error',
        'message' => $this->t('An error occurred while saving your review.'),
      ], 500);
    }
  }

  /**
   * Save a user rating (simulation).
   */
  private function saveUserRating($node_id, $user_id, $rating) {
    // In a full implementation, this would insert/update a user_ratings table
    // For now, we'll use a simple approach with the state system
    $ratings = \Drupal::state()->get('hotlinks_reviews.ratings', []);
    $ratings[$node_id][$user_id] = [
      'rating' => $rating,
      'timestamp' => time(),
    ];
    \Drupal::state()->set('hotlinks_reviews.ratings', $ratings);
  }

  /**
   * Save a user review (simulation).
   */
  private function saveUserReview($node_id, $user_id, $rating, $review_text) {
    // Save rating
    $this->saveUserRating($node_id, $user_id, $rating);
    
    // Save review text
    $reviews = \Drupal::state()->get('hotlinks_reviews.reviews', []);
    $reviews[$node_id][$user_id] = [
      'rating' => $rating,
      'review' => $review_text,
      'timestamp' => time(),
      'status' => \Drupal::config('hotlinks.settings')->get('moderate_reviews') ? 'pending' : 'approved',
    ];
    \Drupal::state()->set('hotlinks_reviews.reviews', $reviews);
  }

  /**
   * Recalculate average rating for a node.
   */
  private function recalculateAverageRating($node_id) {
    $ratings = \Drupal::state()->get('hotlinks_reviews.ratings', []);
    
    if (!isset($ratings[$node_id]) || empty($ratings[$node_id])) {
      return 0;
    }

    $total = 0;
    $count = 0;
    
    foreach ($ratings[$node_id] as $user_rating) {
      $total += $user_rating['rating'];
      $count++;
    }

    return $count > 0 ? round($total / $count, 2) : 0;
  }

  /**
   * Get review count for a node.
   */
  private function getReviewCount($node_id) {
    $ratings = \Drupal::state()->get('hotlinks_reviews.ratings', []);
    return isset($ratings[$node_id]) ? count($ratings[$node_id]) : 0;
  }

  /**
   * Get rating breakdown for a node.
   */
  private function getRatingBreakdown($node_id) {
    $ratings = \Drupal::state()->get('hotlinks_reviews.ratings', []);
    $breakdown = [1 => 0, 2 => 0, 3 => 0, 4 => 0, 5 => 0];
    
    if (isset($ratings[$node_id])) {
      foreach ($ratings[$node_id] as $user_rating) {
        $rating = $user_rating['rating'];
        if ($rating >= 1 && $rating <= 5) {
          $breakdown[$rating]++;
        }
      }
    }
    
    return $breakdown;
  }

  /**
   * Get all reviews for a node (future feature).
   */
  private function getNodeReviews($node_id, $status = 'approved') {
    $reviews = \Drupal::state()->get('hotlinks_reviews.reviews', []);
    $node_reviews = [];
    
    if (isset($reviews[$node_id])) {
      foreach ($reviews[$node_id] as $user_id => $review) {
        if ($review['status'] === $status) {
          $review['user_id'] = $user_id;
          $node_reviews[] = $review;
        }
      }
    }
    
    // Sort by timestamp (newest first)
    usort($node_reviews, function($a, $b) {
      return $b['timestamp'] - $a['timestamp'];
    });
    
    return $node_reviews;
  }

  /**
   * Check if user has already rated a node.
   */
  private function hasUserRated($node_id, $user_id) {
    $ratings = \Drupal::state()->get('hotlinks_reviews.ratings', []);
    return isset($ratings[$node_id][$user_id]);
  }

  /**
   * Get user's existing rating for a node.
   */
  private function getUserRating($node_id, $user_id) {
    $ratings = \Drupal::state()->get('hotlinks_reviews.ratings', []);
    
    if (isset($ratings[$node_id][$user_id])) {
      return $ratings[$node_id][$user_id]['rating'];
    }
    
    return 0;
  }

  /**
   * Delete a user's rating (future feature).
   */
  private function deleteUserRating($node_id, $user_id) {
    $ratings = \Drupal::state()->get('hotlinks_reviews.ratings', []);
    
    if (isset($ratings[$node_id][$user_id])) {
      unset($ratings[$node_id][$user_id]);
      
      // If no more ratings for this node, remove the node entry
      if (empty($ratings[$node_id])) {
        unset($ratings[$node_id]);
      }
      
      \Drupal::state()->set('hotlinks_reviews.ratings', $ratings);
      return TRUE;
    }
    
    return FALSE;
  }

  /**
   * Get statistics for admin dashboard (future feature).
   */
  public function getStatistics() {
    $ratings = \Drupal::state()->get('hotlinks_reviews.ratings', []);
    $reviews = \Drupal::state()->get('hotlinks_reviews.reviews', []);
    
    $stats = [
      'total_nodes_with_ratings' => count($ratings),
      'total_ratings' => 0,
      'total_reviews' => 0,
      'average_rating' => 0,
      'rating_distribution' => [1 => 0, 2 => 0, 3 => 0, 4 => 0, 5 => 0],
    ];
    
    $total_rating_sum = 0;
    
    foreach ($ratings as $node_id => $node_ratings) {
      foreach ($node_ratings as $user_rating) {
        $stats['total_ratings']++;
        $rating = $user_rating['rating'];
        $total_rating_sum += $rating;
        $stats['rating_distribution'][$rating]++;
      }
    }
    
    foreach ($reviews as $node_id => $node_reviews) {
      $stats['total_reviews'] += count($node_reviews);
    }
    
    if ($stats['total_ratings'] > 0) {
      $stats['average_rating'] = round($total_rating_sum / $stats['total_ratings'], 2);
    }
    
    return $stats;
  }

}