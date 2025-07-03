<?php

namespace Drupal\hotlinks_reviews\Service;

use Drupal\Core\Database\Connection;
use Drupal\Core\Session\AccountInterface;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Component\Utility\Html;
use Drupal\Component\Utility\Xss;

/**
 * Service for handling hotlinks reviews and ratings database operations.
 */
class HotlinksReviewsService {

  /**
   * The database connection.
   *
   * @var \Drupal\Core\Database\Connection
   */
  protected $database;

  /**
   * The current user.
   *
   * @var \Drupal\Core\Session\AccountInterface
   */
  protected $currentUser;

  /**
   * The config factory.
   *
   * @var \Drupal\Core\Config\ConfigFactoryInterface
   */
  protected $configFactory;

  /**
   * The entity type manager.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected $entityTypeManager;

  /**
   * Constructs a new HotlinksReviewsService object.
   */
  public function __construct(
    Connection $database,
    AccountInterface $current_user,
    ConfigFactoryInterface $config_factory,
    EntityTypeManagerInterface $entity_type_manager
  ) {
    $this->database = $database;
    $this->currentUser = $current_user;
    $this->configFactory = $config_factory;
    $this->entityTypeManager = $entity_type_manager;
  }

  /**
   * Submit a rating for a hotlink.
   *
   * @param int $node_id
   *   The node ID of the hotlink.
   * @param int $rating
   *   The rating value (1-5).
   * @param string $ip_address
   *   The user's IP address.
   * @param int $user_id
   *   Optional user ID. Defaults to current user.
   *
   * @return array
   *   Result array with success status and data.
   *
   * @throws \Exception
   *   If validation fails or database operation fails.
   */
  public function submitRating($node_id, $rating, $ip_address, $user_id = NULL) {
    // Validate inputs
    if (!is_numeric($node_id) || $node_id <= 0) {
      throw new \InvalidArgumentException('Invalid node ID');
    }

    if (!is_numeric($rating) || $rating < 1 || $rating > 5) {
      throw new \InvalidArgumentException('Rating must be between 1 and 5');
    }

    $user_id = $user_id ?: $this->currentUser->id();

    // Validate IP address
    if (!filter_var($ip_address, FILTER_VALIDATE_IP)) {
      throw new \InvalidArgumentException('Invalid IP address');
    }

    // Check permissions and rate limiting
    $this->validateSubmissionPermissions($node_id, $user_id, $ip_address, 'rating');

    $transaction = $this->database->startTransaction();

    try {
      $time = time();

      // Check if rating already exists
      $existing_rating = $this->database->select('hotlinks_ratings', 'r')
        ->fields('r', ['id'])
        ->condition('node_id', (int) $node_id)
        ->condition('user_id', (int) $user_id)
        ->execute()
        ->fetchField();

      if ($existing_rating) {
        // Update existing rating
        $this->database->update('hotlinks_ratings')
          ->fields([
            'rating' => (int) $rating,
            'ip_address' => substr($ip_address, 0, 45),
            'updated' => $time,
          ])
          ->condition('id', $existing_rating)
          ->execute();
      } else {
        // Insert new rating
        $this->database->insert('hotlinks_ratings')
          ->fields([
            'node_id' => (int) $node_id,
            'user_id' => (int) $user_id,
            'rating' => (int) $rating,
            'ip_address' => substr($ip_address, 0, 45),
            'created' => $time,
            'updated' => $time,
          ])
          ->execute();
      }

      // Update statistics
      $this->updateNodeStatistics($node_id);

      // Track rate limiting
      $this->trackSubmission($user_id, $ip_address, 'rating', $node_id);

      // Get updated statistics
      $stats = $this->getNodeStatistics($node_id);

      // Update node fields
      $this->updateNodeFields($node_id, $stats);

      return [
        'success' => TRUE,
        'data' => [
          'newAverage' => $stats['average_rating'],
          'reviewCount' => $stats['total_ratings'],
          'userRating' => $rating,
        ],
      ];

    } catch (\Exception $e) {
      $transaction->rollBack();
      throw $e;
    }
  }

  /**
   * Submit a review for a hotlink.
   *
   * @param int $node_id
   *   The node ID of the hotlink.
   * @param string $review_text
   *   The review text.
   * @param string $ip_address
   *   The user's IP address.
   * @param int $rating
   *   Optional associated rating.
   * @param string $review_title
   *   Optional review title.
   * @param int $user_id
   *   Optional user ID. Defaults to current user.
   *
   * @return array
   *   Result array with success status and data.
   *
   * @throws \Exception
   *   If validation fails or database operation fails.
   */
  public function submitReview($node_id, $review_text, $ip_address, $rating = NULL, $review_title = NULL, $user_id = NULL) {
    // Validate inputs
    if (!is_numeric($node_id) || $node_id <= 0) {
      throw new \InvalidArgumentException('Invalid node ID');
    }

    $review_text = trim($review_text);
    if (empty($review_text)) {
      throw new \InvalidArgumentException('Review text is required');
    }

    $config = $this->configFactory->get('hotlinks.settings');
    $min_length = $config->get('min_review_length') ?: 10;
    $max_length = $config->get('max_review_length') ?: 2000;

    if (strlen($review_text) < $min_length) {
      throw new \InvalidArgumentException("Review text must be at least {$min_length} characters");
    }

    if (strlen($review_text) > $max_length) {
      throw new \InvalidArgumentException("Review text must not exceed {$max_length} characters");
    }

    // Sanitize review text
    $review_text = Xss::filter($review_text);

    // Check for spam
    if ($config->get('enable_spam_detection') && $this->isSpam($review_text)) {
      throw new \InvalidArgumentException('Review appears to be spam');
    }

    $user_id = $user_id ?: $this->currentUser->id();

    // Validate IP address
    if (!filter_var($ip_address, FILTER_VALIDATE_IP)) {
      throw new \InvalidArgumentException('Invalid IP address');
    }

    // Validate optional rating
    if ($rating !== NULL) {
      if (!is_numeric($rating) || $rating < 1 || $rating > 5) {
        throw new \InvalidArgumentException('Rating must be between 1 and 5');
      }
    }

    // Check permissions and rate limiting
    $this->validateSubmissionPermissions($node_id, $user_id, $ip_address, 'review');

    $transaction = $this->database->startTransaction();

    try {
      $time = time();
      $user_name = NULL;

      // Get user display name if not anonymous
      if ($user_id > 0) {
        $user = $this->entityTypeManager->getStorage('user')->load($user_id);
        if ($user) {
          $user_name = Html::escape($user->getDisplayName());
        }
      }

      // Determine review status
      $status = $config->get('moderate_reviews') ? 'pending' : 'approved';

      // Get associated rating ID if rating was provided
      $rating_id = NULL;
      if ($rating !== NULL) {
        // First submit the rating
        $this->submitRating($node_id, $rating, $ip_address, $user_id);
        
        // Get the rating ID
        $rating_record = $this->database->select('hotlinks_ratings', 'r')
          ->fields('r', ['id'])
          ->condition('node_id', $node_id)
          ->condition('user_id', $user_id)
          ->execute()
          ->fetchField();
          
        $rating_id = $rating_record ?: NULL;
      }

      // Insert or update review
      $existing_review = $this->database->select('hotlinks_reviews', 'rv')
        ->fields('rv', ['id'])
        ->condition('node_id', (int) $node_id)
        ->condition('user_id', (int) $user_id)
        ->execute()
        ->fetchField();

      if ($existing_review) {
        // Update existing review
        $this->database->update('hotlinks_reviews')
          ->fields([
            'rating_id' => $rating_id,
            'review_text' => $review_text,
            'review_title' => $review_title ? Html::escape(substr($review_title, 0, 255)) : NULL,
            'status' => $status,
            'user_name' => $user_name,
            'ip_address' => substr($ip_address, 0, 45),
            'user_agent' => substr($_SERVER['HTTP_USER_AGENT'] ?? '', 0, 65535),
            'updated' => $time,
          ])
          ->condition('id', $existing_review)
          ->execute();
      } else {
        // Insert new review
        $this->database->insert('hotlinks_reviews')
          ->fields([
            'node_id' => (int) $node_id,
            'user_id' => (int) $user_id,
            'rating_id' => $rating_id,
            'review_text' => $review_text,
            'review_title' => $review_title ? Html::escape(substr($review_title, 0, 255)) : NULL,
            'status' => $status,
            'user_name' => $user_name,
            'ip_address' => substr($ip_address, 0, 45),
            'user_agent' => substr($_SERVER['HTTP_USER_AGENT'] ?? '', 0, 65535),
            'created' => $time,
            'updated' => $time,
          ])
          ->execute();
      }

      // Update statistics only if review is approved
      if ($status === 'approved') {
        $this->updateNodeStatistics($node_id);
      }

      // Track rate limiting
      $this->trackSubmission($user_id, $ip_address, 'review', $node_id);

      return [
        'success' => TRUE,
        'data' => [
          'needsModeration' => $status === 'pending',
          'status' => $status,
        ],
      ];

    } catch (\Exception $e) {
      $transaction->rollBack();
      throw $e;
    }
  }

  /**
   * Get user's rating for a specific node.
   *
   * @param int $node_id
   *   The node ID.
   * @param int $user_id
   *   Optional user ID. Defaults to current user.
   *
   * @return int|null
   *   The user's rating or NULL if no rating exists.
   */
  public function getUserRating($node_id, $user_id = NULL) {
    $user_id = $user_id ?: $this->currentUser->id();

    $rating = $this->database->select('hotlinks_ratings', 'r')
      ->fields('r', ['rating'])
      ->condition('node_id', $node_id)
      ->condition('user_id', $user_id)
      ->execute()
      ->fetchField();

    return $rating ? (int) $rating : NULL;
  }

  /**
   * Get user's review for a specific node.
   *
   * @param int $node_id
   *   The node ID.
   * @param int $user_id
   *   Optional user ID. Defaults to current user.
   *
   * @return array|null
   *   The user's review data or NULL if no review exists.
   */
  public function getUserReview($node_id, $user_id = NULL) {
    $user_id = $user_id ?: $this->currentUser->id();

    $review = $this->database->select('hotlinks_reviews', 'r')
      ->fields('r')
      ->condition('node_id', $node_id)
      ->condition('user_id', $user_id)
      ->execute()
      ->fetchAssoc();

    return $review ?: NULL;
  }

  /**
   * Get statistics for a specific node.
   *
   * @param int $node_id
   *   The node ID.
   *
   * @return array
   *   Statistics array.
   */
  public function getNodeStatistics($node_id) {
    $stats = $this->database->select('hotlinks_statistics', 's')
      ->fields('s')
      ->condition('node_id', $node_id)
      ->execute()
      ->fetchAssoc();

    if (!$stats) {
      // Initialize statistics if they don't exist
      $this->updateNodeStatistics($node_id);
      $stats = $this->database->select('hotlinks_statistics', 's')
        ->fields('s')
        ->condition('node_id', $node_id)
        ->execute()
        ->fetchAssoc();
    }

    return $stats ?: [
      'node_id' => $node_id,
      'total_ratings' => 0,
      'total_reviews' => 0,
      'average_rating' => 0,
      'rating_sum' => 0,
      'rating_1_count' => 0,
      'rating_2_count' => 0,
      'rating_3_count' => 0,
      'rating_4_count' => 0,
      'rating_5_count' => 0,
      'last_updated' => time(),
    ];
  }

  /**
   * Get rating breakdown for a specific node.
   *
   * @param int $node_id
   *   The node ID.
   *
   * @return array
   *   Rating breakdown array.
   */
  public function getRatingBreakdown($node_id) {
    $stats = $this->getNodeStatistics($node_id);

    return [
      5 => (int) $stats['rating_5_count'],
      4 => (int) $stats['rating_4_count'],
      3 => (int) $stats['rating_3_count'],
      2 => (int) $stats['rating_2_count'],
      1 => (int) $stats['rating_1_count'],
    ];
  }

  /**
   * Get approved reviews for a specific node.
   *
   * @param int $node_id
   *   The node ID.
   * @param int $limit
   *   Optional limit. Defaults to 10.
   * @param int $offset
   *   Optional offset. Defaults to 0.
   *
   * @return array
   *   Array of review records.
   */
  public function getNodeReviews($node_id, $limit = 10, $offset = 0) {
    $query = $this->database->select('hotlinks_reviews', 'r')
      ->fields('r')
      ->condition('node_id', $node_id)
      ->condition('status', 'approved')
      ->orderBy('created', 'DESC')
      ->range($offset, $limit);

    return $query->execute()->fetchAll(\PDO::FETCH_ASSOC);
  }

  /**
   * Check if user can submit rating/review.
   *
   * @param int $node_id
   *   The node ID.
   * @param int $user_id
   *   The user ID.
   * @param string $ip_address
   *   The IP address.
   * @param string $action_type
   *   The action type ('rating' or 'review').
   *
   * @throws \Exception
   *   If user cannot submit.
   */
  protected function validateSubmissionPermissions($node_id, $user_id, $ip_address, $action_type) {
    $config = $this->configFactory->get('hotlinks.settings');

    // Check if anonymous reviews are allowed
    if ($user_id == 0 && !$config->get('allow_anonymous_reviews')) {
      throw new \Exception('Anonymous submissions not allowed');
    }

    // Check rate limiting
    if ($this->isRateLimited($user_id, $ip_address, $action_type)) {
      throw new \Exception('Rate limit exceeded');
    }

    // Check if user already has a submission and updates are not allowed
    if (!$config->get('allow_review_updates')) {
      if ($action_type === 'rating' && $this->getUserRating($node_id, $user_id)) {
        throw new \Exception('Rating already exists and updates not allowed');
      }
      if ($action_type === 'review' && $this->getUserReview($node_id, $user_id)) {
        throw new \Exception('Review already exists and updates not allowed');
      }
    }

    // Verify node exists and is a hotlink
    $node = $this->entityTypeManager->getStorage('node')->load($node_id);
    if (!$node || $node->bundle() !== 'hotlink') {
      throw new \Exception('Invalid node or not a hotlink');
    }
  }

  /**
   * Check if user/IP is rate limited.
   *
   * @param int $user_id
   *   The user ID.
   * @param string $ip_address
   *   The IP address.
   * @param string $action_type
   *   The action type.
   *
   * @return bool
   *   TRUE if rate limited.
   */
  protected function isRateLimited($user_id, $ip_address, $action_type) {
    $config = $this->configFactory->get('hotlinks.settings');
    $max_submissions = $config->get('rate_limit_submissions') ?: 5;
    $time_window = $config->get('rate_limit_window') ?: 300;
    $current_time = time();
    $cutoff_time = $current_time - $time_window;

    // Check user-based rate limiting
    if ($user_id > 0) {
      $user_submissions = $this->database->select('hotlinks_rate_limits', 'rl')
        ->condition('user_id', $user_id)
        ->condition('action_type', $action_type)
        ->condition('created', $cutoff_time, '>')
        ->countQuery()
        ->execute()
        ->fetchField();

      if ($user_submissions >= $max_submissions) {
        return TRUE;
      }
    }

    // Check IP-based rate limiting
    $ip_submissions = $this->database->select('hotlinks_rate_limits', 'rl')
      ->condition('ip_address', $ip_address)
      ->condition('action_type', $action_type)
      ->condition('created', $cutoff_time, '>')
      ->countQuery()
      ->execute()
      ->fetchField();

    return $ip_submissions >= $max_submissions;
  }

  /**
   * Track a submission for rate limiting.
   *
   * @param int $user_id
   *   The user ID.
   * @param string $ip_address
   *   The IP address.
   * @param string $action_type
   *   The action type.
   * @param int $node_id
   *   Optional node ID.
   */
  protected function trackSubmission($user_id, $ip_address, $action_type, $node_id = NULL) {
    $this->database->insert('hotlinks_rate_limits')
      ->fields([
        'user_id' => $user_id,
        'ip_address' => substr($ip_address, 0, 45),
        'action_type' => $action_type,
        'node_id' => $node_id,
        'created' => time(),
      ])
      ->execute();
  }

  /**
   * Update statistics for a specific node.
   *
   * @param int $node_id
   *   The node ID.
   */
  public function updateNodeStatistics($node_id) {
    // Calculate rating statistics
    $rating_query = $this->database->select('hotlinks_ratings', 'r')
      ->condition('r.node_id', (int) $node_id);
    $rating_query->addExpression('COUNT(*)', 'total_ratings');
    $rating_query->addExpression('SUM(rating)', 'rating_sum');
    $rating_query->addExpression('AVG(rating)', 'average_rating');
    $rating_query->addExpression('SUM(CASE WHEN rating = 1 THEN 1 ELSE 0 END)', 'rating_1_count');
    $rating_query->addExpression('SUM(CASE WHEN rating = 2 THEN 1 ELSE 0 END)', 'rating_2_count');
    $rating_query->addExpression('SUM(CASE WHEN rating = 3 THEN 1 ELSE 0 END)', 'rating_3_count');
    $rating_query->addExpression('SUM(CASE WHEN rating = 4 THEN 1 ELSE 0 END)', 'rating_4_count');
    $rating_query->addExpression('SUM(CASE WHEN rating = 5 THEN 1 ELSE 0 END)', 'rating_5_count');

    $rating_stats = $rating_query->execute()->fetchAssoc();

    // Calculate review statistics
    $review_query = $this->database->select('hotlinks_reviews', 'rv')
      ->condition('rv.node_id', (int) $node_id)
      ->condition('rv.status', 'approved');
    $review_query->addExpression('COUNT(*)', 'total_reviews');

    $review_stats = $review_query->execute()->fetchAssoc();

    // Merge statistics and handle null values
    $stats = [
      'node_id' => (int) $node_id,
      'total_ratings' => (int) ($rating_stats['total_ratings'] ?: 0),
      'total_reviews' => (int) ($review_stats['total_reviews'] ?: 0),
      'average_rating' => round((float) ($rating_stats['average_rating'] ?: 0), 2),
      'rating_sum' => (int) ($rating_stats['rating_sum'] ?: 0),
      'rating_1_count' => (int) ($rating_stats['rating_1_count'] ?: 0),
      'rating_2_count' => (int) ($rating_stats['rating_2_count'] ?: 0),
      'rating_3_count' => (int) ($rating_stats['rating_3_count'] ?: 0),
      'rating_4_count' => (int) ($rating_stats['rating_4_count'] ?: 0),
      'rating_5_count' => (int) ($rating_stats['rating_5_count'] ?: 0),
      'last_updated' => time(),
    ];

    // Check if statistics record exists
    $existing_stats = $this->database->select('hotlinks_statistics', 's')
      ->fields('s', ['node_id'])
      ->condition('node_id', (int) $node_id)
      ->execute()
      ->fetchField();

    if ($existing_stats) {
      // Update existing statistics
      $this->database->update('hotlinks_statistics')
        ->fields($stats)
        ->condition('node_id', (int) $node_id)
        ->execute();
    } else {
      // Insert new statistics
      $this->database->insert('hotlinks_statistics')
        ->fields($stats)
        ->execute();
    }
  }

  /**
   * Update node fields with current statistics.
   *
   * @param int $node_id
   *   The node ID.
   * @param array $stats
   *   Optional statistics array. If not provided, will be fetched.
   */
  protected function updateNodeFields($node_id, $stats = NULL) {
    if (!$stats) {
      $stats = $this->getNodeStatistics($node_id);
    }

    try {
      $node = $this->entityTypeManager->getStorage('node')->load($node_id);
      if ($node && $node->bundle() === 'hotlink') {
        $updated = FALSE;

        if ($node->hasField('field_hotlink_avg_rating')) {
          $node->set('field_hotlink_avg_rating', $stats['average_rating']);
          $updated = TRUE;
        }

        if ($node->hasField('field_hotlink_review_count')) {
          $node->set('field_hotlink_review_count', $stats['total_ratings']);
          $updated = TRUE;
        }

        if ($updated) {
          $node->save();
        }
      }
    } catch (\Exception $e) {
      \Drupal::logger('hotlinks_reviews')->warning('Failed to update node fields for @nid: @error', [
        '@nid' => $node_id,
        '@error' => $e->getMessage(),
      ]);
    }
  }

  /**
   * Basic spam detection for review text.
   *
   * @param string $text
   *   The text to check.
   *
   * @return bool
   *   TRUE if text appears to be spam.
   */
  protected function isSpam($text) {
    $spam_patterns = [
      '/viagra|cialis|pharmacy/i',
      '/casino|gambling|poker/i',
      '/loan|credit|money/i',
      '/weight.?loss|diet.?pills/i',
      '/https?:\/\/[^\s]{30,}/i', // Long URLs
      '/(.)\1{10,}/', // Repeated characters
      '/buy.{1,10}now/i',
      '/click.{1,10}here/i',
      '/free.{1,10}(money|cash|prize)/i',
    ];

    foreach ($spam_patterns as $pattern) {
      if (preg_match($pattern, $text)) {
        return TRUE;
      }
    }

    // Check for excessive links
    $link_count = preg_match_all('/https?:\/\//', $text);
    if ($link_count > 2) {
      return TRUE;
    }

    // Check for excessive capitalization
    $caps_ratio = (strlen($text) - strlen(strtolower($text))) / strlen($text);
    if ($caps_ratio > 0.5 && strlen($text) > 20) {
      return TRUE;
    }

    return FALSE;
  }

  /**
   * Get top-rated hotlinks.
   *
   * @param int $limit
   *   Number of results to return.
   * @param float $min_rating
   *   Minimum average rating.
   * @param int $min_votes
   *   Minimum number of votes.
   *
   * @return array
   *   Array of node IDs and statistics.
   */
  public function getTopRatedHotlinks($limit = 10, $min_rating = 4.0, $min_votes = 3) {
    $query = $this->database->select('hotlinks_statistics', 's')
      ->fields('s')
      ->condition('average_rating', $min_rating, '>=')
      ->condition('total_ratings', $min_votes, '>=')
      ->orderBy('average_rating', 'DESC')
      ->orderBy('total_ratings', 'DESC')
      ->range(0, $limit);

    return $query->execute()->fetchAll(\PDO::FETCH_ASSOC);
  }

  /**
   * Get recently reviewed hotlinks.
   *
   * @param int $limit
   *   Number of results to return.
   * @param int $days
   *   Number of days to look back.
   *
   * @return array
   *   Array of review records with node information.
   */
  public function getRecentlyReviewed($limit = 10, $days = 7) {
    $cutoff_time = time() - ($days * 86400);

    $query = $this->database->select('hotlinks_reviews', 'r')
      ->fields('r')
      ->condition('status', 'approved')
      ->condition('created', $cutoff_time, '>')
      ->orderBy('created', 'DESC')
      ->range(0, $limit);

    return $query->execute()->fetchAll(\PDO::FETCH_ASSOC);
  }

  /**
   * Moderate a review.
   *
   * @param int $review_id
   *   The review ID.
   * @param string $status
   *   The new status ('approved', 'rejected', 'spam').
   * @param int $moderator_id
   *   Optional moderator user ID. Defaults to current user.
   *
   * @return bool
   *   TRUE if successful.
   *
   * @throws \Exception
   *   If operation fails.
   */
  public function moderateReview($review_id, $status, $moderator_id = NULL) {
    $moderator_id = $moderator_id ?: $this->currentUser->id();

    if (!in_array($status, ['approved', 'rejected', 'spam'])) {
      throw new \InvalidArgumentException('Invalid review status');
    }

    $transaction = $this->database->startTransaction();

    try {
      // Get the review to find the node ID
      $review = $this->database->select('hotlinks_reviews', 'r')
        ->fields('r', ['node_id', 'status'])
        ->condition('id', $review_id)
        ->execute()
        ->fetchAssoc();

      if (!$review) {
        throw new \Exception('Review not found');
      }

      $old_status = $review['status'];
      $node_id = $review['node_id'];

      // Update the review
      $this->database->update('hotlinks_reviews')
        ->fields([
          'status' => $status,
          'moderated_by' => $moderator_id,
          'moderated_at' => time(),
          'updated' => time(),
        ])
        ->condition('id', $review_id)
        ->execute();

      // Update statistics if status changed between approved and not approved
      $was_approved = $old_status === 'approved';
      $is_approved = $status === 'approved';

      if ($was_approved !== $is_approved) {
        $this->updateNodeStatistics($node_id);
      }

      return TRUE;

    } catch (\Exception $e) {
      $transaction->rollBack();
      throw $e;
    }
  }

  /**
   * Get pending reviews for moderation.
   *
   * @param int $limit
   *   Number of results to return.
   * @param int $offset
   *   Offset for pagination.
   *
   * @return array
   *   Array of pending review records.
   */
  public function getPendingReviews($limit = 20, $offset = 0) {
    $query = $this->database->select('hotlinks_reviews', 'r')
      ->fields('r')
      ->condition('status', 'pending')
      ->orderBy('created', 'ASC')
      ->range($offset, $limit);

    return $query->execute()->fetchAll(\PDO::FETCH_ASSOC);
  }

  /**
   * Clean up old rate limiting data.
   *
   * @param int $days
   *   Number of days to keep. Defaults to 7.
   *
   * @return int
   *   Number of records deleted.
   */
  public function cleanupRateLimitData($days = 7) {
    $cutoff_time = time() - ($days * 86400);

    return $this->database->delete('hotlinks_rate_limits')
      ->condition('created', $cutoff_time, '<')
      ->execute();
  }

  /**
   * Get submission statistics for analytics.
   *
   * @param int $days
   *   Number of days to analyze.
   *
   * @return array
   *   Statistics array.
   */
  public function getSubmissionStats($days = 30) {
    $cutoff_time = time() - ($days * 86400);

    // Get rating statistics
    $rating_stats = $this->database->select('hotlinks_ratings', 'r')
      ->condition('created', $cutoff_time, '>')
      ->execute()
      ->fetchAll();

    $rating_count = count($rating_stats);
    $rating_avg = $rating_count > 0 ? array_sum(array_column($rating_stats, 'rating')) / $rating_count : 0;

    // Get review statistics
    $review_count = $this->database->select('hotlinks_reviews', 'rv')
      ->condition('created', $cutoff_time, '>')
      ->countQuery()
      ->execute()
      ->fetchField();

    $approved_reviews = $this->database->select('hotlinks_reviews', 'rv')
      ->condition('created', $cutoff_time, '>')
      ->condition('status', 'approved')
      ->countQuery()
      ->execute()
      ->fetchField();

    return [
      'period_days' => $days,
      'total_ratings' => $rating_count,
      'average_rating' => round($rating_avg, 2),
      'total_reviews' => $review_count,
      'approved_reviews' => $approved_reviews,
      'pending_reviews' => $review_count - $approved_reviews,
    ];
  }
}