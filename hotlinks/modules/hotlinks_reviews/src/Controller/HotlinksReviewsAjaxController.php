<?php

namespace Drupal\hotlinks_reviews\Controller;

use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Access\CsrfTokenGenerator;
use Drupal\Component\Utility\Html;
use Drupal\node\NodeInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * AJAX controller for Hotlinks Reviews with enhanced security.
 */
class HotlinksReviewsAjaxController extends ControllerBase {

  /**
   * The CSRF token generator.
   *
   * @var \Drupal\Core\Access\CsrfTokenGenerator
   */
  protected $csrfToken;

  /**
   * Constructs a new HotlinksReviewsAjaxController object.
   *
   * @param \Drupal\Core\Access\CsrfTokenGenerator $csrf_token
   *   The CSRF token generator.
   */
  public function __construct(CsrfTokenGenerator $csrf_token) {
    $this->csrfToken = $csrf_token;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('csrf_token')
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

    // Check if user can rate (not anonymous unless allowed)
    $config = \Drupal::config('hotlinks.settings');
    if ($this->currentUser()->isAnonymous() && !$config->get('allow_anonymous_reviews')) {
      return new JsonResponse([
        'status' => 'error', 
        'message' => $this->t('You must be logged in to rate links.')
      ], 403);
    }

    // Check if user has already rated and updates are not allowed
    $user_id = $this->currentUser()->id();
    if (!$config->get('allow_review_updates') && $this->getUserRating($node->id(), $user_id)) {
      return new JsonResponse([
        'status' => 'error', 
        'message' => $this->t('You have already rated this link.')
      ], 400);
    }

    try {
      // Save rating to state (temporary - will be replaced with proper DB)
      $ratings = \Drupal::state()->get('hotlinks_reviews.ratings', []);
      $ratings[$node->id()][$user_id] = [
        'rating' => $rating,
        'timestamp' => \Drupal::time()->getRequestTime(),
        'ip_address' => $request->getClientIp(), // For abuse prevention
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
      
      // Update node safely
      if ($node->hasField('field_hotlink_avg_rating')) {
        $node->set('field_hotlink_avg_rating', $average);
      }
      if ($node->hasField('field_hotlink_review_count')) {
        $node->set('field_hotlink_review_count', $count);
      }
      $node->save();

      // Log activity for security monitoring
      \Drupal::logger('hotlinks_reviews')->info('User @user (ID: @uid) rated hotlink "@title" (@rating stars) from IP @ip', [
        '@user' => $this->currentUser()->getDisplayName(),
        '@uid' => $user_id,
        '@title' => $node->getTitle(),
        '@rating' => $rating,
        '@ip' => $request->getClientIp(),
      ]);

      return new JsonResponse([
        'status' => 'success',
        'message' => $this->t('Rating saved successfully.'),
        'data' => [
          'newAverage' => $average,
          'reviewCount' => $count,
          'userRating' => $rating,
        ],
      ]);

    } catch (\Exception $e) {
      \Drupal::logger('hotlinks_reviews')->error('Error saving rating for node @nid by user @uid: @message', [
        '@nid' => $node->id(),
        '@uid' => $user_id,
        '@message' => $e->getMessage()
      ]);
      
      return new JsonResponse([
        'status' => 'error', 
        'message' => $this->t('Unable to save rating. Please try again.')
      ], 500);
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

    $review_text = Html::escape(trim($review_input));
    if (empty($review_text)) {
      return new JsonResponse([
        'status' => 'error', 
        'message' => $this->t('Review text is required.')
      ], 400);
    }

    // Check review length limits
    if (strlen($review_text) > 2000) {
      return new JsonResponse([
        'status' => 'error', 
        'message' => $this->t('Review text is too long. Maximum 2000 characters.')
      ], 400);
    }

    if (strlen($review_text) < 10) {
      return new JsonResponse([
        'status' => 'error', 
        'message' => $this->t('Review text is too short. Minimum 10 characters.')
      ], 400);
    }

    // Validate rating if provided
    $rating_input = $request->request->get('rating');
    $rating = 0;
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

    // Check if user can review (not anonymous unless allowed)
    $config = \Drupal::config('hotlinks.settings');
    if ($this->currentUser()->isAnonymous() && !$config->get('allow_anonymous_reviews')) {
      return new JsonResponse([
        'status' => 'error', 
        'message' => $this->t('You must be logged in to review links.')
      ], 403);
    }

    // Rate limiting check - prevent spam
    $user_id = $this->currentUser()->id();
    $ip_address = $request->getClientIp();
    if ($this->isRateLimited($user_id, $ip_address)) {
      return new JsonResponse([
        'status' => 'error', 
        'message' => $this->t('Too many reviews submitted. Please wait before submitting another.')
      ], 429);
    }

    try {
      // Save review to state (temporary - will be replaced with proper DB)
      $reviews = \Drupal::state()->get('hotlinks_reviews.reviews', []);
      $reviews[$node->id()][$user_id] = [
        'review' => $review_text,
        'rating' => $rating,
        'timestamp' => \Drupal::time()->getRequestTime(),
        'user_name' => Html::escape($this->currentUser()->getDisplayName()),
        'ip_address' => $ip_address,
        'status' => $config->get('moderate_reviews') ? 'pending' : 'approved',
      ];
      \Drupal::state()->set('hotlinks_reviews.reviews', $reviews);

      // Update rate limiting tracking
      $this->updateRateLimit($user_id, $ip_address);

      // Log activity for security monitoring
      \Drupal::logger('hotlinks_reviews')->info('User @user (ID: @uid) submitted review for hotlink "@title" from IP @ip', [
        '@user' => $this->currentUser()->getDisplayName(),
        '@uid' => $user_id,
        '@title' => $node->getTitle(),
        '@ip' => $ip_address,
      ]);

      $message = $config->get('moderate_reviews') ? 
        $this->t('Review submitted and is pending moderation.') :
        $this->t('Review saved successfully.');

      return new JsonResponse([
        'status' => 'success',
        'message' => $message,
        'data' => [
          'needsModeration' => (bool) $config->get('moderate_reviews'),
        ],
      ]);

    } catch (\Exception $e) {
      \Drupal::logger('hotlinks_reviews')->error('Error saving review for node @nid by user @uid: @message', [
        '@nid' => $node->id(),
        '@uid' => $user_id,
        '@message' => $e->getMessage()
      ]);
      
      return new JsonResponse([
        'status' => 'error', 
        'message' => $this->t('Unable to save review. Please try again.')
      ], 500);
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
   * Get user's existing rating for a node.
   */
  private function getUserRating($node_id, $user_id) {
    $ratings = \Drupal::state()->get('hotlinks_reviews.ratings', []);
    
    if (isset($ratings[$node_id][$user_id])) {
      return $ratings[$node_id][$user_id]['rating'];
    }
    
    return NULL;
  }

  /**
   * Check if user/IP is rate limited.
   */
  private function isRateLimited($user_id, $ip_address) {
    $rate_limit_data = \Drupal::state()->get('hotlinks_reviews.rate_limits', []);
    $current_time = time();
    $rate_limit_window = 300; // 5 minutes
    $max_submissions = 5; // Maximum submissions per window
    
    // Check user-based rate limiting
    if ($user_id > 0) {
      $user_key = 'user_' . $user_id;
      if (isset($rate_limit_data[$user_key])) {
        $submissions = array_filter($rate_limit_data[$user_key], function($timestamp) use ($current_time, $rate_limit_window) {
          return ($current_time - $timestamp) < $rate_limit_window;
        });
        
        if (count($submissions) >= $max_submissions) {
          return TRUE;
        }
      }
    }
    
    // Check IP-based rate limiting
    $ip_key = 'ip_' . $ip_address;
    if (isset($rate_limit_data[$ip_key])) {
      $submissions = array_filter($rate_limit_data[$ip_key], function($timestamp) use ($current_time, $rate_limit_window) {
        return ($current_time - $timestamp) < $rate_limit_window;
      });
      
      if (count($submissions) >= $max_submissions) {
        return TRUE;
      }
    }
    
    return FALSE;
  }

  /**
   * Update rate limiting tracking.
   */
  private function updateRateLimit($user_id, $ip_address) {
    $rate_limit_data = \Drupal::state()->get('hotlinks_reviews.rate_limits', []);
    $current_time = time();
    
    // Track by user ID
    if ($user_id > 0) {
      $user_key = 'user_' . $user_id;
      if (!isset($rate_limit_data[$user_key])) {
        $rate_limit_data[$user_key] = [];
      }
      $rate_limit_data[$user_key][] = $current_time;
      
      // Keep only recent entries (last 24 hours)
      $rate_limit_data[$user_key] = array_filter($rate_limit_data[$user_key], function($timestamp) use ($current_time) {
        return ($current_time - $timestamp) < 86400; // 24 hours
      });
    }
    
    // Track by IP
    $ip_key = 'ip_' . $ip_address;
    if (!isset($rate_limit_data[$ip_key])) {
      $rate_limit_data[$ip_key] = [];
    }
    $rate_limit_data[$ip_key][] = $current_time;
    
    // Keep only recent entries (last 24 hours)
    $rate_limit_data[$ip_key] = array_filter($rate_limit_data[$ip_key], function($timestamp) use ($current_time) {
      return ($current_time - $timestamp) < 86400; // 24 hours
    });
    
    \Drupal::state()->set('hotlinks_reviews.rate_limits', $rate_limit_data);
  }
}