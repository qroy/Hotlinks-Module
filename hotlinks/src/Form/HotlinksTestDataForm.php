<?php

namespace Drupal\hotlinks\Form;

use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\hotlinks\Service\HotlinksTestDataService;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Form for managing test data generation and removal.
 */
class HotlinksTestDataForm extends FormBase {

  /**
   * The test data service.
   *
   * @var \Drupal\hotlinks\Service\HotlinksTestDataService
   */
  protected $testDataService;

  /**
   * Constructs a new HotlinksTestDataForm object.
   *
   * @param \Drupal\hotlinks\Service\HotlinksTestDataService $test_data_service
   *   The test data service.
   */
  public function __construct(HotlinksTestDataService $test_data_service) {
    $this->testDataService = $test_data_service;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('hotlinks.test_data')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'hotlinks_test_data_form';
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state) {
    $form['description'] = [
      '#markup' => '<p>' . $this->t('Use this form to generate or remove test data for the Hotlinks module. This is useful for development, testing, and demonstrations.') . '</p>',
    ];

    $form['generate'] = [
      '#type' => 'fieldset',
      '#title' => $this->t('Generate Test Data'),
      '#description' => $this->t('Create sample categories, hotlinks, users, and reviews for testing purposes.'),
    ];

    $form['generate']['categories'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Generate test categories'),
      '#description' => $this->t('Create 6 main categories (Technology, Science, News & Media, Education, Entertainment, Health & Wellness) with subcategories.'),
      '#default_value' => TRUE,
    ];

    $form['generate']['hotlinks'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Generate test hotlinks'),
      '#description' => $this->t('Create 25+ sample hotlinks with realistic URLs, descriptions, and category assignments.'),
      '#default_value' => TRUE,
    ];

    $form['generate']['users'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Generate test users'),
      '#description' => $this->t('Create 8 test users for review system testing (alex_dev, sarah_designer, mike_admin, etc.).'),
      '#default_value' => TRUE,
    ];

    $reviews_enabled = \Drupal::moduleHandler()->moduleExists('hotlinks_reviews');
    
    $form['generate']['reviews'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Generate test reviews and ratings'),
      '#description' => $reviews_enabled ? 
        $this->t('Create sample reviews and ratings for the test hotlinks using the test users.') :
        $this->t('Reviews module not enabled - this option will be ignored.'),
      '#default_value' => $reviews_enabled,
      '#disabled' => !$reviews_enabled,
    ];

    $form['generate']['thumbnails'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Generate placeholder thumbnails'),
      '#description' => $this->t('Create simple placeholder thumbnail images for test hotlinks (requires GD extension).'),
      '#default_value' => extension_loaded('gd'),
      '#disabled' => !extension_loaded('gd'),
    ];

    if (!extension_loaded('gd')) {
      $form['generate']['thumbnails']['#description'] .= ' ' . $this->t('<strong>GD extension not available.</strong>');
    }

    $form['generate']['generate_data'] = [
      '#type' => 'submit',
      '#value' => $this->t('Generate Test Data'),
      '#submit' => ['::generateData'],
      '#button_type' => 'primary',
    ];

    $form['remove'] = [
      '#type' => 'fieldset',
      '#title' => $this->t('Remove Test Data'),
      '#description' => $this->t('<strong>Warning:</strong> This will permanently delete all test data. This action cannot be undone.'),
    ];

    $form['remove']['confirm_removal'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('I understand this will permanently delete test data'),
      '#description' => $this->t('Check this box to confirm you want to remove all test data.'),
      '#default_value' => FALSE,
    ];

    $form['remove']['remove_data'] = [
      '#type' => 'submit',
      '#value' => $this->t('Remove All Test Data'),
      '#submit' => ['::removeData'],
      '#states' => [
        'disabled' => [
          ':input[name="confirm_removal"]' => ['checked' => FALSE],
        ],
      ],
      '#attributes' => [
        'class' => ['button--danger'],
      ],
    ];

    $form['info'] = [
      '#type' => 'fieldset',
      '#title' => $this->t('Test Data Information'),
      '#collapsible' => TRUE,
      '#collapsed' => TRUE,
    ];

    $form['info']['data_details'] = [
      '#markup' => $this->getTestDataDetails(),
    ];

    // Add some styling
    $form['#attached']['library'][] = 'hotlinks/hotlinks.admin';

    return $form;
  }

  /**
   * Get detailed information about test data.
   *
   * @return string
   *   HTML markup with test data details.
   */
  protected function getTestDataDetails() {
    $output = '<h4>' . $this->t('Categories to be created:') . '</h4>';
    $output .= '<ul>';
    $output .= '<li><strong>Technology</strong> (Programming, Web Development, Mobile Development, AI & Machine Learning, DevOps, Open Source)</li>';
    $output .= '<li><strong>Science</strong> (Physics, Biology, Chemistry, Space & Astronomy, Environmental Science)</li>';
    $output .= '<li><strong>News & Media</strong> (Tech News, World News, Local News, Podcasts, Magazines)</li>';
    $output .= '<li><strong>Education</strong> (Online Courses, Universities, Tutorials, Documentation, Libraries)</li>';
    $output .= '<li><strong>Entertainment</strong> (Movies & TV, Music, Games, Books, Art & Culture)</li>';
    $output .= '<li><strong>Health & Wellness</strong> (Medical Resources, Fitness, Nutrition, Mental Health)</li>';
    $output .= '</ul>';

    $output .= '<h4>' . $this->t('Sample hotlinks include:') . '</h4>';
    $output .= '<ul>';
    $output .= '<li>GitHub, Stack Overflow, MDN Web Docs</li>';
    $output .= '<li>NASA, Nature, National Geographic</li>';
    $output .= '<li>BBC News, TechCrunch, NPR</li>';
    $output .= '<li>Coursera, MIT OpenCourseWare, Codecademy</li>';
    $output .= '<li>IMDb, Spotify, Steam</li>';
    $output .= '<li>WebMD, MyFitnessPal, Headspace</li>';
    $output .= '</ul>';

    $output .= '<h4>' . $this->t('Test users:') . '</h4>';
    $output .= '<p>alex_dev, sarah_designer, mike_admin, jenny_student, carlos_researcher, lisa_writer, tom_engineer, emma_scientist</p>';

    if (\Drupal::moduleHandler()->moduleExists('hotlinks_reviews')) {
      $output .= '<h4>' . $this->t('Reviews system:') . '</h4>';
      $output .= '<p>' . $this->t('Each hotlink will receive 2-5 reviews with ratings from 3-5 stars and realistic review text.') . '</p>';
    }

    return $output;
  }

  /**
   * Submit handler for generating test data.
   */
  public function generateData(array &$form, FormStateInterface $form_state) {
    $options = [
      'categories' => (bool) $form_state->getValue('categories'),
      'hotlinks' => (bool) $form_state->getValue('hotlinks'),
      'users' => (bool) $form_state->getValue('users'),
      'reviews' => (bool) $form_state->getValue('reviews'),
      'thumbnails' => (bool) $form_state->getValue('thumbnails'),
    ];

    try {
      $results = $this->testDataService->generateTestData($options);

      $messages = [];
      if ($results['categories'] > 0) {
        $messages[] = $this->formatPlural(
          $results['categories'],
          'Created 1 category.',
          'Created @count categories.'
        );
      }

      if ($results['hotlinks'] > 0) {
        $messages[] = $this->formatPlural(
          $results['hotlinks'],
          'Created 1 hotlink.',
          'Created @count hotlinks.'
        );
      }

      if ($results['users'] > 0) {
        $messages[] = $this->formatPlural(
          $results['users'],
          'Created 1 user.',
          'Created @count users.'
        );
      }

      if ($results['reviews'] > 0) {
        $messages[] = $this->formatPlural(
          $results['reviews'],
          'Created 1 review.',
          'Created @count reviews.'
        );
      }

      if (!empty($messages)) {
        $this->messenger()->addMessage($this->t('Test data generated successfully: @results', [
          '@results' => implode(' ', $messages),
        ]));
      } else {
        $this->messenger()->addMessage($this->t('No new test data was created. Data may already exist.'));
      }

      if (!empty($results['errors'])) {
        foreach ($results['errors'] as $error) {
          $this->messenger()->addError($this->t('Error: @error', ['@error' => $error]));
        }
      }

    } catch (\Exception $e) {
      $this->messenger()->addError($this->t('Failed to generate test data: @error', [
        '@error' => $e->getMessage(),
      ]));
      \Drupal::logger('hotlinks')->error('Test data generation failed: @error', [
        '@error' => $e->getMessage(),
      ]);
    }
  }

  /**
   * Submit handler for removing test data.
   */
  public function removeData(array &$form, FormStateInterface $form_state) {
    if (!$form_state->getValue('confirm_removal')) {
      $this->messenger()->addError($this->t('You must confirm the removal of test data.'));
      return;
    }

    try {
      $results = $this->testDataService->removeTestData();

      $messages = [];
      if ($results['hotlinks'] > 0) {
        $messages[] = $this->formatPlural(
          $results['hotlinks'],
          'Removed 1 hotlink.',
          'Removed @count hotlinks.'
        );
      }

      if ($results['categories'] > 0) {
        $messages[] = $this->formatPlural(
          $results['categories'],
          'Removed 1 category.',
          'Removed @count categories.'
        );
      }

      if ($results['users'] > 0) {
        $messages[] = $this->formatPlural(
          $results['users'],
          'Removed 1 user.',
          'Removed @count users.'
        );
      }

      if ($results['reviews'] > 0) {
        $messages[] = $this->t('Cleaned up review database.');
      }

      if (!empty($messages)) {
        $this->messenger()->addMessage($this->t('Test data removed successfully: @results', [
          '@results' => implode(' ', $messages),
        ]));
      } else {
        $this->messenger()->addMessage($this->t('No test data found to remove.'));
      }

      if (!empty($results['errors'])) {
        foreach ($results['errors'] as $error) {
          $this->messenger()->addError($this->t('Error: @error', ['@error' => $error]));
        }
      }

    } catch (\Exception $e) {
      $this->messenger()->addError($this->t('Failed to remove test data: @error', [
        '@error' => $e->getMessage(),
      ]));
      \Drupal::logger('hotlinks')->error('Test data removal failed: @error', [
        '@error' => $e->getMessage(),
      ]);
    }
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    // This method is required by FormBase but not used since we have custom submit handlers.
  }
}