<?php

namespace Drupal\hotlinks_reviews\Controller;

use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Access\CsrfTokenGenerator;
use Drupal\Component\Utility\Html;
use Drupal\node\NodeInterface;
use Drupal\hotlinks_reviews\Service\HotlinksReviewsService;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * AJAX controller for Hotlinks Reviews using proper database service.
 */
class HotlinksReviewsAjaxController extends ControllerBase {

  /**
   * The CSRF token generator.
   *
   * @var \Drupal\Core\Access\CsrfTokenGenerator
   */
  protected $csrfToken;

  /**
   * The hotlinks reviews service.
   *
   * @var \Drupal\hotlinks_reviews\Service\HotlinksReviewsService
   */
  protected $reviewsService;

  /**
   * Constructs a new HotlinksReviewsAjaxController object.
   *
   * @param \Drupal\Core\Access\CsrfTokenGenerator $csrf_token
   *   The CSRF token generator.
   * @param \Drupal\hotlinks_reviews\Service\HotlinksReviewsService $reviews_service
   *   The reviews service.
   */
  public function __construct(CsrfTokenGenerator $csrf_token, HotlinksReviewsService $reviews_service) {
    $this->csrfToken = $csrf_token;
    $this->reviewsService = $reviews_service;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('csrf_token'),
      $container->get('hotlinks_reviews.service')
    );
  }

  /**
   * AJAX callback for rating a hotlink.
   */
  public function rate(NodeInterface $node, Request $request) {
    // Validate node type
    if ($node->bundle() !== 'hotlink') {
      return new JsonResponse([
        'status' => 'error', 
        'message' => $this->t('Invalid content type.')
      ], 400);
    }

    // Check permissions
    if (!$this->currentUser()->hasPermission('rate hotlinks')) {
      return new JsonResponse([
        'status' => 'error', 
        'message' => $this->t('Access denied.')
      ], 403);
    }

    // Validate CSRF token
    $token = $request->request->get('token');
    if (!$this->csrfToken->validate($token, 'hotlinks_rate_' . $node->id())) {
      return new JsonResponse([
        'status' => 'error', 
        'message' => $this->t('Invalid security token.')
      ], 403);
    }

    // Validate and sanitize rating
    $rating_input = $request->request->get('rating');
    if (!is_numeric($rating_input)) {
      return new JsonResponse([
        'status' => 'error', 
        'message' => $this->t('Rating must be a number.')
      ], 400);
    }

    $rating = (int) $rating_input;
    if ($rating < 1 || $rating > 5) {
      return new JsonResponse([
        'status' => 'error', 
        'message' => $this->t('Rating must be between 1 and 5.')
      ], 400);
    }

    try {
      // Use the service to submit the rating
      $result = $this->reviewsService->submitRating(
        $node->id(),
        $rating,
        $request->getClientIp(),
        $this->currentUser()->id()
      );

      // Log activity for security monitoring
      \Drupal::logger('hotlinks_reviews')->info('User @user (ID: @uid) rated hotlink "@title" (@rating stars) from IP @ip', [
        '@user' => $this->currentUser()->getDisplayName(),
        '@uid' => $this->currentUser()->id(),
        '@title' => $node->getTitle(),
        '@rating' => $rating,
        '@ip' => $request->getClientIp(),
      ]);

      return new JsonResponse([
        'status' => 'success',
        'message' => $this->t('Rating saved successfully.'),
        'data' => $result['data'],
      ]);

    } catch (\InvalidArgumentException $e) {
      return new JsonResponse([
        'status' => 'error', 
        'message' => $this->t('@error', ['@error' => $e->getMessage()])
      ], 400);

    } catch (\Exception $e) {
      \Drupal::logger('hotlinks_reviews')->error('Error saving rating for node @nid by user @uid: @message', [
        '@nid' => $node->id(),
        '@uid' => $this->currentUser()->id(),
        '@message' => $e->getMessage()
      ]);
      
      // Don't expose internal errors to users
      $message = $this->t('Unable to save rating. Please try again.');
      if (strpos($e->getMessage(), 'Rate limit') !== FALSE) {
        $message = $this->t('You are submitting too frequently. Please wait before rating again.');
      } elseif (strpos($e->getMessage(), 'already exists') !== FALSE) {
        $message = $this->t('You have already rated this link.');
      }
      
      return new JsonResponse([
        'status' => 'error', 
        'message' => $message
      ], 429);
    }
  }

  /**
   * AJAX callback for submitting a review.
   */
  public function review(NodeInterface $node, Request $request) {
    // Validate node type
    if ($node->bundle() !== 'hotlink') {
      return new JsonResponse([
        'status' => 'error', 
        'message' => $this->t('Invalid content type.')
      ], 400);
    }

    // Check permissions
    if (!$this->currentUser()->hasPermission('review hotlinks')) {
      return new JsonResponse([
        'status' => 'error', 
        'message' => $this->t('Access denied.')
      ], 403);
    }

    // Validate CSRF token
    $token = $request->request->get('token');
    if (!$this->csrfToken->validate($token, 'hotlinks_review_' . $node->id())) {
      return new JsonResponse([
        'status' => 'error', 
        'message' => $this->t('Invalid security token.')
      ], 403);
    }

    // Validate and sanitize review text
    $review_input = $request->request->get('review');
    if (!is_string($review_input)) {
      return new JsonResponse([
        'status' => 'error', 
        'message' => $this->t('Invalid review format.')
      ], 400);
    }

    $review_text = trim($review_input);
    if (empty($review_text)) {
      return new JsonResponse([
        'status' => 'error', 
        'message' => $this->t('Review text is required.')
      ], 400);
    }

    // Validate rating if provided
    $rating_input = $request->request->get('rating');
    $rating = NULL;
    if (!empty($rating_input)) {
      if (!is_numeric($rating_input)) {
        return new JsonResponse([
          'status' => 'error', 
          'message' => $this->t('Rating must be a number.')
        ], 400);
      }
      
      $rating = (int) $rating_input;
      if ($rating < 1 || $rating > 5) {
        return new JsonResponse([
          'status' => 'error', 
          'message' => $this->t('Rating must be between 1 and 5.')
        ], 400);
      }
    }

    // Get optional review title
    $review_title = $request->request->get('title');
    if ($review_title && !is_string($review_title)) {
      $review_title = NULL;
    }

    try {
      // Use the service to submit the review
      $result = $this->reviewsService->submitReview(
        $node->id(),
        $review_text,
        $request->getClientIp(),
        $rating,
        $review_title,
        $this->currentUser()->id()
      );

      // Log activity for security monitoring
      \Drupal::logger('hotlinks_reviews')->info('User @user (ID: @uid) submitted review for hotlink "@title" from IP @ip', [
        '@user' => $this->currentUser()->getDisplayName(),
        '@uid' => $this->currentUser()->id(),
        '@title' => $node->getTitle(),
        '@ip' => $request->getClientIp(),
      ]);

      $message = $result['data']['needsModeration'] ? 
        $this->t('Review submitted and is pending moderation.') :
        $this->t('Review saved successfully.');

      return new JsonResponse([
        'status' => 'success',
        'message' => $message,
        'data' => $result['data'],
      ]);

    } catch (\InvalidArgumentException $e) {
      return new JsonResponse([
        'status' => 'error', 
        'message' => $this->t('@error', ['@error' => $e->getMessage()])
      ], 400);

    } catch (\Exception $e) {
      \Drupal::logger('hotlinks_reviews')->error('Error saving review for node @nid by user @uid: @message', [
        '@nid' => $node->id(),
        '@uid' => $this->currentUser()->id(),
        '@message' => $e->getMessage()
      ]);
      
      // Don't expose internal errors to users
      $message = $this->t('Unable to save review. Please try again.');
      if (strpos($e->getMessage(), 'Rate limit') !== FALSE) {
        $message = $this->t('You are submitting too frequently. Please wait before submitting another review.');
      } elseif (strpos($e->getMessage(), 'already exists') !== FALSE) {
        $message = $this->t('You have already reviewed this link.');
      } elseif (strpos($e->getMessage(), 'spam') !== FALSE) {
        $message = $this->t('Review appears to contain spam. Please revise your review.');
      }
      
      return new JsonResponse([
        'status' => 'error', 
        'message' => $message
      ], 429);
    }
  }

  /**
   * Generate CSRF token for a specific action.
   */
  public function getToken(Request $request) {
    $action = $request->query->get('action');
    $node_id = $request->query->get('node_id');
    
    // Validate action
    if (!in_array($action, ['rate', 'review'])) {
      return new JsonResponse(['error' => 'Invalid action'], 400);
    }
    
    // Validate node ID
    if (!is_numeric($node_id)) {
      return new JsonResponse(['error' => 'Invalid node ID'], 400);
    }
    
    $token = $this->csrfToken->get('hotlinks_' . $action . '_' . $node_id);
    
    return new JsonResponse([
      'token' => $token,
      'expires' => time() + 3600, // 1 hour
    ]);
  }

  /**
   * Get rating and review data for a node.
   */
  public function getNodeData(NodeInterface $node, Request $request) {
    // Validate node type
    if ($node->bundle() !== 'hotlink') {
      return new JsonResponse([
        'status' => 'error', 
        'message' => $this->t('Invalid content type.')
      ], 400);
    }

    // Check permissions
    if (!$this->currentUser()->hasPermission('view hotlink ratings')) {
      return new JsonResponse([
        'status' => 'error', 
        'message' => $this->t('Access denied.')
      ], 403);
    }

    try {
      // Get statistics
      $stats = $this->reviewsService->getNodeStatistics($node->id());
      
      // Get user's existing rating/review if logged in
      $user_rating = NULL;
      $user_review = NULL;
      
      if ($this->currentUser()->isAuthenticated()) {
        $user_rating = $this->reviewsService->getUserRating($node->id());
        $user_review = $this->reviewsService->getUserReview($node->id());
      }
      
      // Get rating breakdown
      $rating_breakdown = $this->reviewsService->getRatingBreakdown($node->id());
      
      // Get recent reviews (limit to prevent large responses)
      $reviews = $this->reviewsService->getNodeReviews($node->id(), 5);
      
      return new JsonResponse([
        'status' => 'success',
        'data' => [
          'statistics' => $stats,
          'rating_breakdown' => $rating_breakdown,
          'user_rating' => $user_rating,
          'user_review' => $user_review,
          'recent_reviews' => $reviews,
        ],
      ]);

    } catch (\Exception $e) {
      \Drupal::logger('hotlinks_reviews')->error('Error getting node data for @nid: @message', [
        '@nid' => $node->id(),
        '@message' => $e->getMessage()
      ]);
      
      return new JsonResponse([
        'status' => 'error', 
        'message' => $this->t('Unable to load review data.')
      ], 500);
    }
  }

  /**
   * Get top-rated hotlinks.
   */
  public function getTopRated(Request $request) {
    // Check permissions
    if (!$this->currentUser()->hasPermission('access hotlinks')) {
      return new JsonResponse([
        'status' => 'error', 
        'message' => $this->t('Access denied.')
      ], 403);
    }

    try {
      $limit = min((int) $request->query->get('limit', 10), 50); // Cap at 50
      $min_rating = (float) $request->query->get('min_rating', 4.0);
      $min_votes = (int) $request->query->get('min_votes', 3);
      
      $top_rated = $this->reviewsService->getTopRatedHotlinks($limit, $min_rating, $min_votes);
      
      return new JsonResponse([
        'status' => 'success',
        'data' => $top_rated,
      ]);

    } catch (\Exception $e) {
      \Drupal::logger('hotlinks_reviews')->error('Error getting top rated hotlinks: @message', [
        '@message' => $e->getMessage()
      ]);
      
      return new JsonResponse([
        'status' => 'error', 
        'message' => $this->t('Unable to load top-rated hotlinks.')
      ], 500);
    }
  }

  /**
   * Moderate a review (admin only).
   */
  public function moderateReview(Request $request) {
    // Check permissions
    if (!$this->currentUser()->hasPermission('moderate hotlink reviews')) {
      return new JsonResponse([
        'status' => 'error', 
        'message' => $this->t('Access denied.')
      ], 403);
    }

    // Validate CSRF token
    $token = $request->request->get('token');
    if (!$this->csrfToken->validate($token, 'hotlinks_moderate')) {
      return new JsonResponse([
        'status' => 'error', 
        'message' => $this->t('Invalid security token.')
      ], 403);
    }

    $review_id = $request->request->get('review_id');
    $status = $request->request->get('status');

    // Validate inputs
    if (!is_numeric($review_id) || $review_id <= 0) {
      return new JsonResponse([
        'status' => 'error', 
        'message' => $this->t('Invalid review ID.')
      ], 400);
    }

    if (!in_array($status, ['approved', 'rejected', 'spam'])) {
      return new JsonResponse([
        'status' => 'error', 
        'message' => $this->t('Invalid status.')
      ], 400);
    }

    try {
      $result = $this->reviewsService->moderateReview(
        (int) $review_id,
        $status,
        $this->currentUser()->id()
      );

      if ($result) {
        // Log moderation activity
        \Drupal::logger('hotlinks_reviews')->info('User @user moderated review @review_id to @status', [
          '@user' => $this->currentUser()->getDisplayName(),
          '@review_id' => $review_id,
          '@status' => $status,
        ]);

        return new JsonResponse([
          'status' => 'success',
          'message' => $this->t('Review moderated successfully.'),
          'data' => [
            'review_id' => $review_id,
            'new_status' => $status,
          ],
        ]);
      } else {
        throw new \Exception('Moderation failed');
      }

    } catch (\Exception $e) {
      \Drupal::logger('hotlinks_reviews')->error('Error moderating review @review_id: @message', [
        '@review_id' => $review_id,
        '@message' => $e->getMessage()
      ]);
      
      return new JsonResponse([
        'status' => 'error', 
        'message' => $this->t('Unable to moderate review.')
      ], 500);
    }
  }

  /**
   * Get pending reviews for moderation.
   */
  public function getPendingReviews(Request $request) {
    // Check permissions
    if (!$this->currentUser()->hasPermission('moderate hotlink reviews')) {
      return new JsonResponse([
        'status' => 'error', 
        'message' => $this->t('Access denied.')
      ], 403);
    }

    try {
      $limit = min((int) $request->query->get('limit', 20), 100); // Cap at 100
      $offset = max((int) $request->query->get('offset', 0), 0);
      
      $pending_reviews = $this->reviewsService->getPendingReviews($limit, $offset);
      
      return new JsonResponse([
        'status' => 'success',
        'data' => [
          'reviews' => $pending_reviews,
          'limit' => $limit,
          'offset' => $offset,
        ],
      ]);

    } catch (\Exception $e) {
      \Drupal::logger('hotlinks_reviews')->error('Error getting pending reviews: @message', [
        '@message' => $e->getMessage()
      ]);
      
      return new JsonResponse([
        'status' => 'error', 
        'message' => $this->t('Unable to load pending reviews.')
      ], 500);
    }
  }
}