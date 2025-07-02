<?php

namespace Drupal\hotlinks\Form;

use Drupal\Core\Form\ConfigFormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Component\Utility\Html;
use Drupal\Component\Utility\UrlHelper;
use Drupal\file\Entity\File;

/**
 * Configure Hotlinks settings for this site with enhanced security.
 */
class HotlinksSettingsForm extends ConfigFormBase {

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'hotlinks_settings_form';
  }

  /**
   * {@inheritdoc}
   */
  protected function getEditableConfigNames() {
    return ['hotlinks.settings'];
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state) {
    $reviews_enabled = \Drupal::moduleHandler()->moduleExists('hotlinks_reviews');
    $config = $this->config('hotlinks.settings');

    $form['display'] = [
      '#type' => 'fieldset',
      '#title' => $this->t('Display Settings'),
    ];

    $form['display']['show_descriptions'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Show descriptions'),
      '#default_value' => $config->get('show_descriptions') ?? TRUE,
      '#description' => $this->t('Display link descriptions in the hotlinks index.'),
    ];

    $form['display']['open_in_new_window'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Open links in new window'),
      '#default_value' => $config->get('open_in_new_window') ?? TRUE,
      '#description' => $this->t('Open hotlinks in a new browser window/tab.'),
    ];

    $form['display']['show_category_counts'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Show category counts'),
      '#default_value' => $config->get('show_category_counts') ?? TRUE,
      '#description' => $this->t('Display the number of links in each category.'),
    ];

    $form['thumbnails'] = [
      '#type' => 'fieldset',
      '#title' => $this->t('Auto Thumbnail Settings'),
      '#description' => $this->t('Configure automatic thumbnail generation for hotlinks.'),
    ];
    
    $form['thumbnails']['auto_thumbnail_service'] = [
      '#type' => 'select',
      '#title' => $this->t('Thumbnail Service'),
      '#default_value' => $config->get('auto_thumbnail_service') ?: 'screenshotapi',
      '#options' => [
        'screenshotapi' => $this->t('ScreenshotAPI (Recommended - Free tier available)'),
        'websiteshots' => $this->t('WebsiteShots (Free with small watermark)'),
        'screenshotmachine' => $this->t('ScreenshotMachine (Free)'),
        'thum_io' => $this->t('Thum.io (Free but sometimes unreliable)'),
        'htmlcsstoimage' => $this->t('HTML/CSS to Image (Premium - requires API key)'),
      ],
      '#description' => $this->t('Choose which service to use for auto-generating thumbnails. ScreenshotAPI is recommended as it\'s most reliable.'),
    ];
    
    $form['thumbnails']['screenshotapi_key'] = [
      '#type' => 'textfield',
      '#title' => $this->t('ScreenshotAPI Key'),
      '#default_value' => $config->get('screenshotapi_key'),
      '#description' => $this->t('Optional API key for ScreenshotAPI. Leave empty to use free tier (limited requests). Get your key at <a href="@url" target="_blank">screenshotapi.net</a>.', [
        '@url' => 'https://screenshotapi.net',
      ]),
      '#states' => [
        'visible' => [
          ':input[name="auto_thumbnail_service"]' => ['value' => 'screenshotapi'],
        ],
      ],
      '#attributes' => [
        'autocomplete' => 'off',
        'data-security' => 'api-key',
      ],
    ];
    
    $form['thumbnails']['htmlcsstoimage_key'] = [
      '#type' => 'textfield',
      '#title' => $this->t('HTML/CSS to Image API Key'),
      '#default_value' => $config->get('htmlcsstoimage_key'),
      '#description' => $this->t('Required API key for HTML/CSS to Image service. Get your key at <a href="@url" target="_blank">htmlcsstoimage.com</a>.', [
        '@url' => 'https://htmlcsstoimage.com',
      ]),
      '#states' => [
        'visible' => [
          ':input[name="auto_thumbnail_service"]' => ['value' => 'htmlcsstoimage'],
        ],
        'required' => [
          ':input[name="auto_thumbnail_service"]' => ['value' => 'htmlcsstoimage'],
        ],
      ],
      '#attributes' => [
        'autocomplete' => 'off',
        'data-security' => 'api-key',
      ],
    ];
    
    // Test thumbnail generation
    $form['thumbnails']['test'] = [
      '#type' => 'details',
      '#title' => $this->t('Test Thumbnail Generation'),
      '#open' => FALSE,
    ];
    
    $form['thumbnails']['test']['test_url'] = [
      '#type' => 'url',
      '#title' => $this->t('Test URL'),
      '#description' => $this->t('Enter a URL to test thumbnail generation with the current service.'),
      '#placeholder' => 'https://example.com',
      '#attributes' => [
        'pattern' => 'https?://.+',
        'data-security' => 'url-validation',
      ],
    ];
    
    $form['thumbnails']['test']['test_generate'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Generate test thumbnail'),
      '#description' => $this->t('Check this box and save to test thumbnail generation with the URL above.'),
      '#default_value' => FALSE,
    ];
    
    $form['thumbnails']['info'] = [
      '#type' => 'details',
      '#title' => $this->t('Service Information'),
      '#open' => FALSE,
    ];
    
    $form['thumbnails']['info']['service_details'] = [
      '#markup' => '
        <h4>' . $this->t('Service Comparison:') . '</h4>
        <ul>
          <li><strong>ScreenshotAPI:</strong> ' . $this->t('Most reliable. 50 free screenshots/month, then paid plans. High quality with customization options.') . '</li>
          <li><strong>WebsiteShots:</strong> ' . $this->t('WordPress.com service. Free with small watermark. Usually reliable.') . '</li>
          <li><strong>ScreenshotMachine:</strong> ' . $this->t('Free service with no API key required. Good alternative option.') . '</li>
          <li><strong>Thum.io:</strong> ' . $this->t('Free service, no registration required. Sometimes has reliability issues.') . '</li>
          <li><strong>HTML/CSS to Image:</strong> ' . $this->t('Premium service with excellent quality and fast response times. Requires paid API key.') . '</li>
        </ul>
        <p><em>' . $this->t('Thumbnails are generated automatically when you save a hotlink without an existing thumbnail.') . '</em></p>
        <p><strong>' . $this->t('Troubleshooting:') . '</strong></p>
        <ul>
          <li>' . $this->t('If one service fails, try switching to another service') . '</li>
          <li>' . $this->t('Make sure your server has cURL enabled') . '</li>
          <li>' . $this->t('Check that the public://hotlinks/thumbnails directory is writable') . '</li>
          <li>' . $this->t('Verify your API keys are correct (for paid services)') . '</li>
          <li>' . $this->t('Check the Drupal logs for any error messages') . '</li>
          <li>' . $this->t('Use the "Test Thumbnail Generation" feature to debug issues') . '</li>
        </ul>
      ',
    ];
    
    $form['thumbnails']['actions'] = [
      '#type' => 'fieldset',
      '#title' => $this->t('Bulk Actions'),
    ];
    
    $form['thumbnails']['actions']['generate_one_missing'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Generate thumbnail for ONE hotlink without thumbnail (for testing)'),
      '#description' => $this->t('This will immediately generate a thumbnail for just one hotlink that doesn\'t have one, so you can test if the system is working.'),
      '#default_value' => FALSE,
    ];
    
    $form['thumbnails']['actions']['regenerate_thumbnails'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Regenerate ALL existing thumbnails'),
      '#description' => $this->t('<strong>Warning:</strong> This will replace all existing thumbnails (including manually uploaded ones) with auto-generated screenshots. This action cannot be undone.'),
      '#default_value' => FALSE,
    ];
    
    $form['thumbnails']['actions']['generate_missing_thumbnails'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Generate thumbnails for hotlinks that don\'t have any'),
      '#description' => $this->t('This will generate thumbnails only for hotlinks that currently have no thumbnail image.'),
      '#default_value' => FALSE,
    ];
    
    // Count existing hotlinks
    $hotlink_count = \Drupal::entityQuery('node')
      ->condition('type', 'hotlink')
      ->condition('status', 1)
      ->accessCheck(TRUE)
      ->count()
      ->execute();
      
    $missing_count = \Drupal::entityQuery('node')
      ->condition('type', 'hotlink')
      ->condition('status', 1)
      ->condition('field_hotlink_thumbnail', NULL, 'IS NULL')
      ->accessCheck(TRUE)
      ->count()
      ->execute();
      
    if ($hotlink_count > 0) {
      $form['thumbnails']['actions']['count_info'] = [
        '#markup' => '<p>' . $this->t('Found @total hotlinks total, @missing without thumbnails.', [
          '@total' => $hotlink_count,
          '@missing' => $missing_count,
        ]) . '</p>',
      ];
    }

    // Reviews settings (only if Reviews submodule is enabled)
    if ($reviews_enabled) {
      $form['reviews'] = [
        '#type' => 'fieldset',
        '#title' => $this->t('Reviews & Ratings'),
        '#description' => $this->t('Configure the rating and review system with security settings.'),
      ];

      $form['reviews']['allow_anonymous_reviews'] = [
        '#type' => 'checkbox',
        '#title' => $this->t('Allow anonymous reviews'),
        '#default_value' => $config->get('allow_anonymous_reviews') ?? FALSE,
        '#description' => $this->t('Warning: Anonymous reviews may require additional spam protection.'),
      ];

      $form['reviews']['moderate_reviews'] = [
        '#type' => 'checkbox',
        '#title' => $this->t('Moderate new reviews'),
        '#default_value' => $config->get('moderate_reviews') ?? TRUE,
        '#description' => $this->t('Recommended for security. Reviews will be held for approval before being displayed.'),
      ];

      $form['reviews']['starfleet_approval'] = [
        '#type' => 'checkbox',
        '#title' => $this->t('Show Starfleet approval badges'),
        '#default_value' => $config->get('starfleet_approval') ?? TRUE,
        '#description' => $this->t('Display special badges for highly-rated content.'),
      ];

      $form['reviews']['use_star_trek_labels'] = [
        '#type' => 'checkbox',
        '#title' => $this->t('Use Star Trek themed rating labels'),
        '#default_value' => $config->get('use_star_trek_labels') ?? FALSE,
        '#description' => $this->t('Use Star Trek themed descriptions for rating levels.'),
      ];

      $form['reviews']['security'] = [
        '#type' => 'details',
        '#title' => $this->t('Security Settings'),
        '#open' => FALSE,
      ];

      $form['reviews']['security']['rate_limit_submissions'] = [
        '#type' => 'number',
        '#title' => $this->t('Maximum submissions per time window'),
        '#default_value' => $config->get('rate_limit_submissions') ?? 5,
        '#min' => 1,
        '#max' => 50,
        '#description' => $this->t('Maximum number of ratings/reviews a user can submit within the time window.'),
      ];

      $form['reviews']['security']['rate_limit_window'] = [
        '#type' => 'select',
        '#title' => $this->t('Rate limiting time window'),
        '#default_value' => $config->get('rate_limit_window') ?? 300,
        '#options' => [
          60 => $this->t('1 minute'),
          300 => $this->t('5 minutes'),
          600 => $this->t('10 minutes'),
          1800 => $this->t('30 minutes'),
          3600 => $this->t('1 hour'),
        ],
        '#description' => $this->t('Time window for rate limiting. Shorter windows provide better spam protection.'),
      ];

      $form['reviews']['security']['max_review_length'] = [
        '#type' => 'number',
        '#title' => $this->t('Maximum review length (characters)'),
        '#default_value' => $config->get('max_review_length') ?? 2000,
        '#min' => 100,
        '#max' => 10000,
        '#description' => $this->t('Maximum length for review text. Longer reviews may contain spam.'),
      ];

      $form['reviews']['security']['min_review_length'] = [
        '#type' => 'number',
        '#title' => $this->t('Minimum review length (characters)'),
        '#default_value' => $config->get('min_review_length') ?? 10,
        '#min' => 5,
        '#max' => 100,
        '#description' => $this->t('Minimum length for review text to prevent spam and low-quality submissions.'),
      ];

      $form['reviews']['security']['enable_spam_detection'] = [
        '#type' => 'checkbox',
        '#title' => $this->t('Enable basic spam detection'),
        '#default_value' => $config->get('enable_spam_detection') ?? TRUE,
        '#description' => $this->t('Automatically detect and block obvious spam in reviews.'),
      ];
    }

    return parent::buildForm($form, $form_state);
  }

  /**
   * {@inheritdoc}
   */
  public function validateForm(array &$form, FormStateInterface $form_state) {
    // Validate HTML/CSS to Image API key if that service is selected
    if ($form_state->getValue('auto_thumbnail_service') === 'htmlcsstoimage') {
      $api_key = trim($form_state->getValue('htmlcsstoimage_key'));
      if (empty($api_key)) {
        $form_state->setErrorByName('htmlcsstoimage_key', $this->t('HTML/CSS to Image API key is required when using this service.'));
      } elseif (!$this->validateApiKey($api_key)) {
        $form_state->setErrorByName('htmlcsstoimage_key', $this->t('API key format is invalid.'));
      }
    }

    // Validate ScreenshotAPI key if provided
    $screenshot_key = trim($form_state->getValue('screenshotapi_key'));
    if (!empty($screenshot_key) && !$this->validateApiKey($screenshot_key)) {
      $form_state->setErrorByName('screenshotapi_key', $this->t('ScreenshotAPI key format is invalid.'));
    }
    
    // Validate test URL if test generation is requested
    if ($form_state->getValue('test_generate')) {
      $test_url = trim($form_state->getValue('test_url'));
      if (empty($test_url)) {
        $form_state->setErrorByName('test_url', $this->t('Please enter a test URL.'));
      } elseif (!$this->validateUrl($test_url)) {
        $form_state->setErrorByName('test_url', $this->t('Please enter a valid URL.'));
      }
    }

    // Validate review security settings
    if (\Drupal::moduleHandler()->moduleExists('hotlinks_reviews')) {
      $max_length = $form_state->getValue('max_review_length');
      $min_length = $form_state->getValue('min_review_length');
      
      if ($min_length >= $max_length) {
        $form_state->setErrorByName('min_review_length', $this->t('Minimum review length must be less than maximum review length.'));
      }
      
      if ($min_length < 5) {
        $form_state->setErrorByName('min_review_length', $this->t('Minimum review length should be at least 5 characters to prevent spam.'));
      }
      
      if ($max_length > 10000) {
        $form_state->setErrorByName('max_review_length', $this->t('Maximum review length should not exceed 10,000 characters for performance reasons.'));
      }
    }
    
    parent::validateForm($form, $form_state);
  }

  /**
   * Validate API key format.
   */
  private function validateApiKey($api_key) {
    // Basic API key validation - alphanumeric plus common symbols
    if (!preg_match('/^[a-zA-Z0-9_\-\.]{8,128}$/', $api_key)) {
      return FALSE;
    }
    
    // Check for obvious fake keys
    $fake_patterns = [
      '/^(test|fake|invalid|demo)/i',
      '/^(your_api_key|api_key_here)/i',
      '/^[0]{8,}$/', // All zeros
      '/^[1]{8,}$/', // All ones
    ];
    
    foreach ($fake_patterns as $pattern) {
      if (preg_match($pattern, $api_key)) {
        return FALSE;
      }
    }
    
    return TRUE;
  }

  /**
   * Enhanced URL validation with security checks.
   */
  private function validateUrl($url) {
    // Basic URL validation
    if (!UrlHelper::isValid($url, TRUE)) {
      return FALSE;
    }
    
    // Security checks
    $parsed = parse_url($url);
    if (!$parsed || !isset($parsed['scheme'], $parsed['host'])) {
      return FALSE;
    }
    
    // Only allow HTTP and HTTPS
    if (!in_array($parsed['scheme'], ['http', 'https'])) {
      return FALSE;
    }
    
    // Block localhost and private IP ranges for security
    $host = $parsed['host'];
    if (in_array($host, ['localhost', '127.0.0.1', '::1'])) {
      return FALSE;
    }
    
    // Block private IP ranges
    if (filter_var($host, FILTER_VALIDATE_IP)) {
      if (!filter_var($host, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE)) {
        return FALSE;
      }
    }
    
    return TRUE;
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    $config = $this->config('hotlinks.settings');
    
    // Sanitize and save basic settings
    $config
      ->set('show_descriptions', (bool) $form_state->getValue('show_descriptions'))
      ->set('open_in_new_window', (bool) $form_state->getValue('open_in_new_window'))
      ->set('show_category_counts', (bool) $form_state->getValue('show_category_counts'))
      ->set('auto_thumbnail_service', Html::escape($form_state->getValue('auto_thumbnail_service')))
      ->set('screenshotapi_key', Html::escape(trim($form_state->getValue('screenshotapi_key'))))
      ->set('htmlcsstoimage_key', Html::escape(trim($form_state->getValue('htmlcsstoimage_key'))));
      
    // Test thumbnail generation if requested
    if ($form_state->getValue('test_generate')) {
      $test_url = trim($form_state->getValue('test_url'));
      if ($this->validateUrl($test_url)) {
        $this->testThumbnailGeneration($test_url);
      }
    }
    
    // Generate ONE missing thumbnail for testing
    if ($form_state->getValue('generate_one_missing')) {
      $this->generateOneMissingThumbnail();
    }
      
    // Regenerate all thumbnails if requested
    if ($form_state->getValue('regenerate_thumbnails')) {
      $this->regenerateThumbnails(TRUE);
      $this->messenger()->addWarning($this->t('Thumbnail regeneration has been queued. This may take several minutes to complete.'));
    }
    
    // Generate missing thumbnails if requested
    if ($form_state->getValue('generate_missing_thumbnails')) {
      $this->regenerateThumbnails(FALSE);
      $this->messenger()->addMessage($this->t('Missing thumbnail generation has been queued.'));
    }

    // Reviews settings (only if submodule is enabled)
    if (\Drupal::moduleHandler()->moduleExists('hotlinks_reviews')) {
      $config
        ->set('allow_anonymous_reviews', (bool) $form_state->getValue('allow_anonymous_reviews'))
        ->set('moderate_reviews', (bool) $form_state->getValue('moderate_reviews'))
        ->set('starfleet_approval', (bool) $form_state->getValue('starfleet_approval'))
        ->set('use_star_trek_labels', (bool) $form_state->getValue('use_star_trek_labels'))
        ->set('rate_limit_submissions', (int) $form_state->getValue('rate_limit_submissions'))
        ->set('rate_limit_window', (int) $form_state->getValue('rate_limit_window'))
        ->set('max_review_length', (int) $form_state->getValue('max_review_length'))
        ->set('min_review_length', (int) $form_state->getValue('min_review_length'))
        ->set('enable_spam_detection', (bool) $form_state->getValue('enable_spam_detection'));
    }

    $config->save();
    parent::submitForm($form, $form_state);
  }
  
  /**
   * Generate thumbnail for one hotlink without thumbnail (for testing).
   */
  private function generateOneMissingThumbnail() {
    // Find one hotlink without a thumbnail
    $nids = \Drupal::entityQuery('node')
      ->condition('type', 'hotlink')
      ->condition('status', 1)
      ->condition('field_hotlink_thumbnail', NULL, 'IS NULL')
      ->accessCheck(TRUE)
      ->range(0, 1)
      ->execute();
      
    if (empty($nids)) {
      $this->messenger()->addMessage($this->t('No hotlinks found without thumbnails.'));
      return;
    }
    
    $nid = reset($nids);
    $hotlink = \Drupal::entityTypeManager()->getStorage('node')->load($nid);
    
    if (!$hotlink) {
      $this->messenger()->addError($this->t('Could not load hotlink.'));
      return;
    }
    
    if (!$hotlink->hasField('field_hotlink_url')) {
      $this->messenger()->addError($this->t('Hotlink "@title" is missing URL field.', ['@title' => $hotlink->getTitle()]));
      return;
    }
    
    $url_field = $hotlink->get('field_hotlink_url');
    if ($url_field->isEmpty()) {
      $this->messenger()->addError($this->t('Hotlink "@title" has no URL.', ['@title' => $hotlink->getTitle()]));
      return;
    }
    
    $url = $url_field->first()->uri;
    
    if (!$this->validateUrl($url)) {
      $this->messenger()->addError($this->t('Hotlink "@title" has invalid URL.', ['@title' => $hotlink->getTitle()]));
      return;
    }
    
    try {
      $result = $this->generateThumbnailForEntity($hotlink, $url);
      
      if ($result) {
        $hotlink->save();
        $this->messenger()->addMessage($this->t('Successfully generated thumbnail for "@title".', [
          '@title' => Html::escape($hotlink->getTitle())
        ]));
      } else {
        $this->messenger()->addError($this->t('Failed to generate thumbnail for "@title".', [
          '@title' => Html::escape($hotlink->getTitle())
        ]));
      }
      
    } catch (\Exception $e) {
      $this->messenger()->addError($this->t('Error generating thumbnail: @error', [
        '@error' => Html::escape($e->getMessage())
      ]));
      \Drupal::logger('hotlinks')->error('Thumbnail generation error: @error', [
        '@error' => $e->getMessage()
      ]);
    }
  }
  
  /**
   * Test thumbnail generation with a specific URL.
   */
  private function testThumbnailGeneration($test_url) {
    try {
      $config = $this->config('hotlinks.settings');
      $service = $config->get('auto_thumbnail_service') ?: 'thum_io';
      
      $thumbnail_url = $this->getThumbnailUrl($test_url, $service, $config);
      
      if (!empty($thumbnail_url)) {
        // Test if we can actually fetch the image
        $client = \Drupal::httpClient();
        $response = $client->head($thumbnail_url, ['timeout' => 10]);
        
        if ($response->getStatusCode() === 200) {
          $this->messenger()->addMessage($this->t('Test successful! Thumbnail URL generated and accessible: <a href="@url" target="_blank">@url</a>', [
            '@url' => Html::escape($thumbnail_url),
          ]));
        } else {
          $this->messenger()->addWarning($this->t('Thumbnail URL generated but returned HTTP @code: <a href="@url" target="_blank">@url</a>', [
            '@code' => $response->getStatusCode(),
            '@url' => Html::escape($thumbnail_url),
          ]));
        }
      } else {
        $this->messenger()->addError($this->t('Test failed: Could not generate thumbnail URL.'));
      }
      
    } catch (\Exception $e) {
      $this->messenger()->addError($this->t('Test failed: @error', ['@error' => Html::escape($e->getMessage())]));
      \Drupal::logger('hotlinks')->error('Thumbnail test failed: @error', ['@error' => $e->getMessage()]);
    }
  }
  
  /**
   * Generate thumbnail for a specific entity.
   */
  private function generateThumbnailForEntity($entity, $url) {
    $config = $this->config('hotlinks.settings');
    $service = $config->get('auto_thumbnail_service') ?: 'thum_io';
    
    try {
      $thumbnail_url = $this->getThumbnailUrl($url, $service, $config);
      
      if (empty($thumbnail_url)) {
        throw new \Exception('No thumbnail URL generated');
      }
      
      return $this->saveThumbnailFromUrl($entity, $thumbnail_url, $url);
      
    } catch (\Exception $e) {
      \Drupal::logger('hotlinks')->error('Failed to generate thumbnail for @url: @error', [
        '@url' => $url,
        '@error' => $e->getMessage(),
      ]);
      return FALSE;
    }
  }
  
  /**
   * Get thumbnail URL from configured service with enhanced security.
   */
  private function getThumbnailUrl($url, $service, $config) {
    // Clean and validate URL first
    $clean_url = $this->cleanUrl($url);
    
    switch ($service) {
      case 'thum_io':
        return "https://image.thum.io/get/width/300/crop/400/noanimate/" . urlencode($clean_url);
        
      case 'screenshotapi':
        $params = [
          'url' => $clean_url,
          'width' => 300,
          'height' => 200,
          'output' => 'image',
          'file_type' => 'png',
          'wait_for_event' => 'load',
        ];
        
        $api_key = trim($config->get('screenshotapi_key'));
        if (!empty($api_key) && $this->validateApiKey($api_key)) {
          $params['token'] = $api_key;
        }
        
        return 'https://shot.screenshotapi.net/screenshot?' . http_build_query($params);
        
      case 'websiteshots':
        $encoded_url = urlencode($clean_url);
        return "https://s0.wordpress.com/mshots/v1/{$encoded_url}?w=300&h=200";
        
      case 'screenshotmachine':
        $params = [
          'url' => $clean_url,
          'dimension' => '1024x768',
          'format' => 'png',
          'cacheLimit' => '0',
        ];
        return 'https://api.screenshotmachine.com?' . http_build_query($params);
        
      case 'htmlcsstoimage':
        $api_key = trim($config->get('htmlcsstoimage_key'));
        if (empty($api_key) || !$this->validateApiKey($api_key)) {
          throw new \Exception('HTML/CSS to Image API key not configured or invalid');
        }
        
        $client = \Drupal::httpClient();
        $response = $client->post('https://hcti.io/v1/image', [
          'auth' => [$api_key, ''],
          'form_params' => [
            'url' => $clean_url,
            'viewport_width' => 1280,
            'viewport_height' => 720,
            'device_scale' => 1,
          ],
          'timeout' => 30,
        ]);
        
        $data = json_decode($response->getBody(), TRUE);
        return $data['url'] ?? '';
        
      default:
        throw new \Exception('Unknown thumbnail service: ' . Html::escape($service));
    }
  }
  
  /**
   * Clean and validate URL for thumbnail services with enhanced security.
   */
  private function cleanUrl($url) {
    // Remove any existing URL encoding
    $url = urldecode($url);
    
    // Ensure the URL has a protocol
    if (!preg_match('/^https?:\/\//', $url)) {
      $url = 'https://' . $url;
    }
    
    // Parse and rebuild URL to ensure it's clean
    $parsed = parse_url($url);
    if (!$parsed || !isset($parsed['host'])) {
      throw new \Exception('Invalid URL: ' . Html::escape($url));
    }
    
    // Security check - validate the host
    if (!$this->validateUrl($url)) {
      throw new \Exception('URL failed security validation: ' . Html::escape($url));
    }
    
    $clean_url = $parsed['scheme'] . '://' . $parsed['host'];
    
    if (isset($parsed['port'])) {
      $clean_url .= ':' . (int) $parsed['port'];
    }
    
    if (isset($parsed['path'])) {
      // Sanitize path
      $clean_url .= '/' . ltrim($parsed['path'], '/');
    }
    
    if (isset($parsed['query'])) {
      // Sanitize query parameters
      parse_str($parsed['query'], $query_params);
      $clean_query = http_build_query($query_params);
      if (!empty($clean_query)) {
        $clean_url .= '?' . $clean_query;
      }
    }
    
    if (isset($parsed['fragment'])) {
      $clean_url .= '#' . urlencode($parsed['fragment']);
    }
    
    return $clean_url;
  }
  
  /**
   * Save thumbnail from URL to entity with enhanced security.
   */
  private function saveThumbnailFromUrl($entity, $thumbnail_url, $original_url) {
    try {
      // Validate thumbnail URL
      if (!$this->validateUrl($thumbnail_url)) {
        throw new \Exception('Thumbnail URL failed security validation');
      }
      
      $client = \Drupal::httpClient();
      
      // Download the thumbnail with security headers
      $response = $client->get($thumbnail_url, [
        'timeout' => 30,
        'headers' => [
          'User-Agent' => 'Drupal Hotlinks Module/1.0',
          'Accept' => 'image/png,image/jpeg,image/gif,image/webp',
        ],
        'max_redirects' => 3,
      ]);
      
      if ($response->getStatusCode() !== 200) {
        throw new \Exception('Failed to download thumbnail: HTTP ' . $response->getStatusCode());
      }
      
      // Check content type
      $content_type = $response->getHeaderLine('Content-Type');
      if (!preg_match('/^image\/(png|jpeg|gif|webp)/', $content_type)) {
        throw new \Exception('Invalid content type: ' . Html::escape($content_type));
      }
      
      // Check content length
      $content_length = $response->getHeaderLine('Content-Length');
      if ($content_length && $content_length > 5242880) { // 5MB limit
        throw new \Exception('Image too large: ' . number_format($content_length) . ' bytes');
      }
      
      $image_data = $response->getBody()->getContents();
      
      if (empty($image_data)) {
        throw new \Exception('Empty thumbnail data received');
      }
      
      // Validate image data
      $image_info = getimagesizefromstring($image_data);
      if (!$image_info) {
        throw new \Exception('Invalid image data');
      }
      
      // Check image dimensions for security
      list($width, $height) = $image_info;
      if ($width > 2000 || $height > 2000) {
        throw new \Exception('Image dimensions too large: ' . $width . 'x' . $height);
      }
      
      if ($width < 50 || $height < 50) {
        throw new \Exception('Image dimensions too small: ' . $width . 'x' . $height);
      }
      
      // Generate secure filename
      $parsed_url = parse_url($original_url);
      $domain = isset($parsed_url['host']) ? 
        preg_replace('/[^a-zA-Z0-9-]/', '_', $parsed_url['host']) : 
        'thumbnail';
      
      // Sanitize domain name
      $domain = substr($domain, 0, 50); // Limit length
      $filename = 'hotlink_' . $domain . '_' . time() . '_' . bin2hex(random_bytes(4)) . '.png';
      
      // Ensure directory exists
      $directory = 'public://hotlinks/thumbnails';
      $file_system = \Drupal::service('file_system');
      if (!$file_system->prepareDirectory($directory, \Drupal\Core\File\FileSystemInterface::CREATE_DIRECTORY)) {
        throw new \Exception('Cannot create thumbnail directory');
      }
      
      // Save file with proper security
      $file_uri = $directory . '/' . $filename;
      $file = $file_system->saveData($image_data, $file_uri, \Drupal\Core\File\FileSystemInterface::EXISTS_REPLACE);
      
      if (!$file) {
        throw new \Exception('Failed to save thumbnail file');
      }
      
      // Create file entity
      $file_entity = File::create([
        'uri' => $file,
        'status' => 1,
        'uid' => \Drupal::currentUser()->id(),
        'filename' => $filename,
        'filemime' => $image_info['mime'],
        'filesize' => strlen($image_data),
      ]);
      $file_entity->save();
      
      // Set the thumbnail field
      $entity->set('field_hotlink_thumbnail', [
        'target_id' => $file_entity->id(),
        'alt' => 'Thumbnail for ' . Html::escape($entity->getTitle()),
        'title' => 'Auto-generated thumbnail',
      ]);
      
      \Drupal::logger('hotlinks')->info('Generated thumbnail for @title from @service', [
        '@title' => $entity->getTitle(),
        '@service' => $this->config('hotlinks.settings')->get('auto_thumbnail_service'),
      ]);
      
      return TRUE;
      
    } catch (\Exception $e) {
      \Drupal::logger('hotlinks')->error('Failed to save thumbnail for @title: @error', [
        '@title' => $entity->getTitle(),
        '@error' => $e->getMessage(),
      ]);
      return FALSE;
    }
  }
  
  /**
   * Regenerate thumbnails using batch processing.
   */
  private function regenerateThumbnails($force_regenerate = FALSE) {
    $query = \Drupal::entityQuery('node')
      ->condition('type', 'hotlink')
      ->condition('status', 1)
      ->accessCheck(TRUE);
      
    if (!$force_regenerate) {
      // Only process hotlinks without thumbnails
      $query->condition('field_hotlink_thumbnail', NULL, 'IS NULL');
    }
    
    $nids = $query->execute();
    
    if (!empty($nids)) {
      $batch = [
        'title' => $force_regenerate ? 
          $this->t('Regenerating all thumbnails...') : 
          $this->t('Generating missing thumbnails...'),
        'operations' => [],
        'finished' => '\Drupal\hotlinks\Form\HotlinksSettingsForm::batchFinished',
        'progress_message' => $this->t('Processing @current of @total hotlinks...'),
      ];
      
      foreach ($nids as $nid) {
        $batch['operations'][] = [
          '\Drupal\hotlinks\Form\HotlinksSettingsForm::batchRegenerateThumbnail', 
          [$nid, $force_regenerate]
        ];
      }
      
      batch_set($batch);
    } else {
      $message = $force_regenerate ? 
        $this->t('No hotlinks found to regenerate thumbnails for.') : 
        $this->t('All hotlinks already have thumbnails.');
      $this->messenger()->addMessage($message);
    }
  }
  
  /**
   * Batch operation to regenerate a single thumbnail.
   */
  public static function batchRegenerateThumbnail($nid, $force_regenerate, &$context) {
    $hotlink = \Drupal::entityTypeManager()->getStorage('node')->load($nid);
    
    if ($hotlink && $hotlink->bundle() === 'hotlink') {
      try {
        if (!$hotlink->hasField('field_hotlink_url')) {
          throw new \Exception('Hotlink missing URL field');
        }
        
        $url_field = $hotlink->get('field_hotlink_url');
        if (!$url_field->isEmpty()) {
          $url = $url_field->first()->uri;
          
          // Validate URL before processing
          $form = new static(\Drupal::configFactory());
          if (!$form->validateUrl($url)) {
            throw new \Exception('Invalid URL: ' . $url);
          }
          
          if ($force_regenerate) {
            // Clear existing thumbnail
            $hotlink->set('field_hotlink_thumbnail', NULL);
          }
          
          // Use the function from hotlinks.module if available
          if (function_exists('hotlinks_generate_thumbnail')) {
            hotlinks_generate_thumbnail($hotlink, $url);
          } else {
            // Fallback: create a new instance to use the method
            $result = $form->generateThumbnailForEntity($hotlink, $url);
            if (!$result) {
              throw new \Exception('Thumbnail generation failed');
            }
          }
          
          // Save the hotlink
          $hotlink->save();
          
          $context['message'] = t('Generated thumbnail for: @title', [
            '@title' => $hotlink->getTitle()
          ]);
          
          $context['results']['success'][] = $nid;
        } else {
          $context['results']['skipped'][] = $nid;
          $context['message'] = t('Skipped @title (no URL)', [
            '@title' => $hotlink->getTitle()
          ]);
        }
      } catch (\Exception $e) {
        $context['results']['failed'][] = $nid;
        $context['message'] = t('Failed to generate thumbnail for: @title', [
          '@title' => $hotlink->getTitle()
        ]);
        \Drupal::logger('hotlinks')->error('Batch thumbnail generation failed for @nid: @error', [
          '@nid' => $nid,
          '@error' => $e->getMessage(),
        ]);
      }
    }
  }
  
  /**
   * Batch finished callback.
   */
  public static function batchFinished($success, $results, $operations) {
    if ($success) {
      $success_count = isset($results['success']) ? count($results['success']) : 0;
      $failed_count = isset($results['failed']) ? count($results['failed']) : 0;
      $skipped_count = isset($results['skipped']) ? count($results['skipped']) : 0;
      
      if ($success_count > 0) {
        $message = \Drupal::translation()->formatPlural(
          $success_count,
          'Generated thumbnail for 1 hotlink.',
          'Generated thumbnails for @count hotlinks.'
        );
        \Drupal::messenger()->addMessage($message);
      }
      
      if ($failed_count > 0) {
        $message = \Drupal::translation()->formatPlural(
          $failed_count,
          'Failed to generate thumbnail for 1 hotlink.',
          'Failed to generate thumbnails for @count hotlinks.'
        );
        \Drupal::messenger()->addWarning($message);
      }
      
      if ($skipped_count > 0) {
        $message = \Drupal::translation()->formatPlural(
          $skipped_count,
          'Skipped 1 hotlink (no URL).',
          'Skipped @count hotlinks (no URL).'
        );
        \Drupal::messenger()->addMessage($message);
      }
      
    } else {
      \Drupal::messenger()->addError(t('Thumbnail generation encountered errors.'));
    }
  }
}