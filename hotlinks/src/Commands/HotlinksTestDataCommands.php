<?php

namespace Drupal\hotlinks\Commands;

use Drupal\hotlinks\Service\HotlinksTestDataService;
use Drush\Commands\DrushCommands;
use Drush\Exceptions\UserAbortException;

/**
 * Drush commands for Hotlinks test data management.
 */
class HotlinksTestDataCommands extends DrushCommands {

  /**
   * The test data service.
   *
   * @var \Drupal\hotlinks\Service\HotlinksTestDataService
   */
  protected $testDataService;

  /**
   * Constructs a new HotlinksTestDataCommands object.
   *
   * @param \Drupal\hotlinks\Service\HotlinksTestDataService $test_data_service
   *   The test data service.
   */
  public function __construct(HotlinksTestDataService $test_data_service) {
    $this->testDataService = $test_data_service;
  }

  /**
   * Generate test data for the Hotlinks module.
   *
   * @param array $options
   *   Command options.
   *
   * @option categories
   *   Generate test categories.
   * @option hotlinks
   *   Generate test hotlinks.
   * @option users
   *   Generate test users.
   * @option reviews
   *   Generate test reviews (requires hotlinks_reviews module).
   * @option thumbnails
   *   Generate placeholder thumbnails (requires GD extension).
   * @option all
   *   Generate all types of test data.
   *
   * @command hotlinks:generate-test-data
   * @aliases hlgtd,hotlinks-test-data
   * @usage hotlinks:generate-test-data --all
   *   Generate all types of test data.
   * @usage hotlinks:generate-test-data --categories --hotlinks
   *   Generate only categories and hotlinks.
   */
  public function generateTestData(array $options = [
    'categories' => FALSE,
    'hotlinks' => FALSE,
    'users' => FALSE,
    'reviews' => FALSE,
    'thumbnails' => FALSE,
    'all' => FALSE,
  ]) {
    
    // If --all is specified, enable all options
    if ($options['all']) {
      $options['categories'] = TRUE;
      $options['hotlinks'] = TRUE;
      $options['users'] = TRUE;
      $options['reviews'] = TRUE;
      $options['thumbnails'] = TRUE;
    }
    
    // If no specific options are set, default to all
    if (!$options['categories'] && !$options['hotlinks'] && !$options['users'] && !$options['reviews'] && !$options['thumbnails']) {
      $options['categories'] = TRUE;
      $options['hotlinks'] = TRUE;
      $options['users'] = TRUE;
      $options['reviews'] = TRUE;
      $options['thumbnails'] = TRUE;
    }

    // Check for reviews module
    if ($options['reviews'] && !\Drupal::moduleHandler()->moduleExists('hotlinks_reviews')) {
      $this->logger()->warning('Reviews module not enabled. Skipping review generation.');
      $options['reviews'] = FALSE;
    }

    // Check for GD extension
    if ($options['thumbnails'] && !extension_loaded('gd')) {
      $this->logger()->warning('GD extension not available. Skipping thumbnail generation.');
      $options['thumbnails'] = FALSE;
    }

    $this->logger()->notice('Starting test data generation...');

    try {
      $results = $this->testDataService->generateTestData($options);

      // Report results
      $messages = [];
      if ($results['categories'] > 0) {
        $messages[] = dt('Created @count categories', ['@count' => $results['categories']]);
      }
      if ($results['hotlinks'] > 0) {
        $messages[] = dt('Created @count hotlinks', ['@count' => $results['hotlinks']]);
      }
      if ($results['users'] > 0) {
        $messages[] = dt('Created @count users', ['@count' => $results['users']]);
      }
      if ($results['reviews'] > 0) {
        $messages[] = dt('Created @count reviews', ['@count' => $results['reviews']]);
      }

      if (!empty($messages)) {
        $this->logger()->success('Test data generated successfully: ' . implode(', ', $messages));
      } else {
        $this->logger()->notice('No new test data was created. Data may already exist.');
      }

      // Report any errors
      if (!empty($results['errors'])) {
        foreach ($results['errors'] as $error) {
          $this->logger()->error('Error: ' . $error);
        }
        return 1; // Exit with error code
      }

      return 0; // Success

    } catch (\Exception $e) {
      $this->logger()->error('Failed to generate test data: ' . $e->getMessage());
      return 1; // Exit with error code
    }
  }

  /**
   * Remove test data from the Hotlinks module.
   *
   * @option yes
   *   Skip confirmation prompt.
   *
   * @command hotlinks:remove-test-data
   * @aliases hlrtd,hotlinks-remove-test-data
   * @usage hotlinks:remove-test-data
   *   Remove all test data (with confirmation).
   * @usage hotlinks:remove-test-data --yes
   *   Remove all test data without confirmation.
   */
  public function removeTestData(array $options = ['yes' => FALSE]) {
    
    // Confirmation prompt unless --yes is specified
    if (!$options['yes']) {
      $confirmed = $this->io()->confirm(
        'This will permanently delete all test data including categories, hotlinks, users, and reviews. Are you sure?',
        FALSE
      );
      
      if (!$confirmed) {
        throw new UserAbortException('Operation cancelled.');
      }
    }

    $this->logger()->notice('Starting test data removal...');

    try {
      $results = $this->testDataService->removeTestData();

      // Report results
      $messages = [];
      if ($results['hotlinks'] > 0) {
        $messages[] = dt('Removed @count hotlinks', ['@count' => $results['hotlinks']]);
      }
      if ($results['categories'] > 0) {
        $messages[] = dt('Removed @count categories', ['@count' => $results['categories']]);
      }
      if ($results['users'] > 0) {
        $messages[] = dt('Removed @count users', ['@count' => $results['users']]);
      }
      if ($results['reviews'] > 0) {
        $messages[] = dt('Cleaned up review database');
      }

      if (!empty($messages)) {
        $this->logger()->success('Test data removed successfully: ' . implode(', ', $messages));
      } else {
        $this->logger()->notice('No test data found to remove.');
      }

      // Report any errors
      if (!empty($results['errors'])) {
        foreach ($results['errors'] as $error) {
          $this->logger()->error('Error: ' . $error);
        }
        return 1; // Exit with error code
      }

      return 0; // Success

    } catch (\Exception $e) {
      $this->logger()->error('Failed to remove test data: ' . $e->getMessage());
      return 1; // Exit with error code
    }
  }

  /**
   * Check if test data exists.
   *
   * @command hotlinks:test-data-status
   * @aliases hltds,hotlinks-test-status
   * @usage hotlinks:test-data-status
   *   Check if test data exists in the system.
   */
  public function testDataStatus() {
    $this->logger()->notice('Checking test data status...');

    try {
      // Check categories
      $test_categories = ['Technology', 'Science', 'News & Media', 'Education', 'Entertainment', 'Health & Wellness'];
      $existing_categories = \Drupal::entityTypeManager()
        ->getStorage('taxonomy_term')
        ->loadByProperties([
          'vid' => 'hotlink_categories',
          'name' => $test_categories,
        ]);

      $this->logger()->info(dt('Found @count test categories', ['@count' => count($existing_categories)]));

      // Check hotlinks
      $existing_hotlinks = \Drupal::entityTypeManager()
        ->getStorage('node')
        ->getQuery()
        ->condition('type', 'hotlink')
        ->accessCheck(FALSE)
        ->count()
        ->execute();

      $this->logger()->info(dt('Found @count total hotlinks', ['@count' => $existing_hotlinks]));

      // Check users
      $test_users = ['alex_dev', 'sarah_designer', 'mike_admin', 'jenny_student', 'carlos_researcher', 'lisa_writer', 'tom_engineer', 'emma_scientist'];
      $existing_users = \Drupal::entityTypeManager()