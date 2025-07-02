<?php

namespace Drupal\hotlinks\Form;

use Drupal\Core\Form\ConfigFormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\file\Entity\File;

/**
 * Configure Hotlinks settings for this site.
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
    '#description' => $this->t('Configure the rating and review system.'),
  ];

  $form['reviews']['allow_anonymous_reviews'] = [
    '#type' => 'checkbox',
    '#title' => $this->t('Allow anonymous reviews'),
    '#default_value' => $config->get('allow_anonymous_reviews') ?? FALSE,
  ];

  $form['reviews']['moderate_reviews'] = [
    '#type' => 'checkbox',
    '#title' => $this->t('Moderate new reviews'),
    '#default_value' => $config->get('moderate_reviews') ?? TRUE,
  ];

  $form['reviews']['starfleet_approval'] = [
    '#type' => 'checkbox',
    '#title' => $this->t('Show Starfleet approval badges'),
    '#default_value' => $config->get('starfleet_approval') ?? TRUE,
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
      $api_key = $form_state->getValue('htmlcsstoimage_key');
      if (empty($api_key)) {
        $form_state->setErrorByName('htmlcsstoimage_key', $this->t('HTML/CSS to Image API key is required when using this service.'));
      }
    }
    
    // Validate test URL if test generation is requested
    if ($form_state->getValue('test_generate')) {
      $test_url = $form_state->getValue('test_url');
      if (empty($test_url)) {
        $form_state->setErrorByName('test_url', $this->t('Please enter a test URL.'));
      } elseif (!filter_var($test_url, FILTER_VALIDATE_URL)) {
        $form_state->setErrorByName('test_url', $this->t('Please enter a valid URL.'));
      }
    }
    
    parent::validateForm($form, $form_state);
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    $config = $this->config('hotlinks.settings');
    
    $config
      ->set('show_descriptions', $form_state->getValue('show_descriptions'))
      ->set('open_in_new_window', $form_state->getValue('open_in_new_window'))
      ->set('show_category_counts', $form_state->getValue('show_category_counts'))
      ->set('auto_thumbnail_service', $form_state->getValue('auto_thumbnail_service'))
      ->set('screenshotapi_key', $form_state->getValue('screenshotapi_key'))
      ->set('htmlcsstoimage_key', $form_state->getValue('htmlcsstoimage_key'))
      ->save();
      
    // Test thumbnail generation if requested
    if ($form_state->getValue('test_generate')) {
      $test_url = $form_state->getValue('test_url');
      $this->testThumbnailGeneration($test_url);
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
        ->set('allow_anonymous_reviews', $form_state->getValue('allow_anonymous_reviews'))
        ->set('moderate_reviews', $form_state->getValue('moderate_reviews'))
        ->set('starfleet_approval', $form_state->getValue('starfleet_approval'));
	}

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
    
    $url_field = $hotlink->get('field_hotlink_url');
    if ($url_field->isEmpty()) {
      $this->messenger()->addError($this->t('Hotlink "@title" has no URL.', ['@title' => $hotlink->getTitle()]));
      return;
    }
    
    $url = $url_field->first()->uri;
    
    try {
      $result = $this->generateThumbnailForEntity($hotlink, $url);
      
      if ($result) {
        $hotlink->save();
        $this->messenger()->addMessage($this->t('Successfully generated thumbnail for "@title".', [
          '@title' => $hotlink->getTitle()
        ]));
      } else {
        $this->messenger()->addError($this->t('Failed to generate thumbnail for "@title".', [
          '@title' => $hotlink->getTitle()
        ]));
      }
      
    } catch (\Exception $e) {
      $this->messenger()->addError($this->t('Error generating thumbnail: @error', [
        '@error' => $e->getMessage()
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
            '@url' => $thumbnail_url,
          ]));
        } else {
          $this->messenger()->addWarning($this->t('Thumbnail URL generated but returned HTTP @code: <a href="@url" target="_blank">@url</a>', [
            '@code' => $response->getStatusCode(),
            '@url' => $thumbnail_url,
          ]));
        }
      } else {
        $this->messenger()->addError($this->t('Test failed: Could not generate thumbnail URL.'));
      }
      
    } catch (\Exception $e) {
      $this->messenger()->addError($this->t('Test failed: @error', ['@error' => $e->getMessage()]));
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
   * Get thumbnail URL from configured service.
   */
  private function getThumbnailUrl($url, $service, $config) {
    // Clean up the URL first - remove any extra protocols or encoding
    $clean_url = $this->cleanUrl($url);
    
    switch ($service) {
      case 'thum_io':
        // Thum.io doesn't need URL encoding in the path, just clean URL
        return "https://image.thum.io/get/width/300/crop/400/noanimate/{$clean_url}";
        
      case 'screenshotapi':
        $params = [
          'url' => $clean_url,
          'width' => 300,
          'height' => 200,
          'output' => 'image',
          'file_type' => 'png',
          'wait_for_event' => 'load',
        ];
        
        $api_key = $config->get('screenshotapi_key');
        if (!empty($api_key)) {
          $params['token'] = $api_key;
        }
        
        return 'https://shot.screenshotapi.net/screenshot?' . http_build_query($params);
        
      case 'websiteshots':
        // WordPress mshots service
        $encoded_url = urlencode($clean_url);
        return "https://s0.wordpress.com/mshots/v1/{$encoded_url}?w=300&h=200";
        
      case 'screenshotmachine':
        // Alternative free service
        $params = [
          'url' => $clean_url,
          'dimension' => '1024x768',
          'format' => 'png',
          'cacheLimit' => '0',
        ];
        return 'https://api.screenshotmachine.com?' . http_build_query($params);
        
      case 'htmlcsstoimage':
        $api_key = $config->get('htmlcsstoimage_key');
        if (empty($api_key)) {
          throw new \Exception('HTML/CSS to Image API key not configured');
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
        throw new \Exception('Unknown thumbnail service: ' . $service);
    }
  }
  
  /**
   * Clean and validate URL for thumbnail services.
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
      throw new \Exception('Invalid URL: ' . $url);
    }
    
    $clean_url = $parsed['scheme'] . '://' . $parsed['host'];
    
    if (isset($parsed['port'])) {
      $clean_url .= ':' . $parsed['port'];
    }
    
    if (isset($parsed['path'])) {
      $clean_url .= $parsed['path'];
    }
    
    if (isset($parsed['query'])) {
      $clean_url .= '?' . $parsed['query'];
    }
    
    if (isset($parsed['fragment'])) {
      $clean_url .= '#' . $parsed['fragment'];
    }
    
    return $clean_url;
  }
  
  /**
   * Save thumbnail from URL to entity.
   */
  private function saveThumbnailFromUrl($entity, $thumbnail_url, $original_url) {
    try {
      $client = \Drupal::httpClient();
      
      // Download the thumbnail
      $response = $client->get($thumbnail_url, [
        'timeout' => 30,
        'headers' => [
          'User-Agent' => 'Drupal Hotlinks Module/1.0',
        ],
      ]);
      
      if ($response->getStatusCode() !== 200) {
        throw new \Exception('Failed to download thumbnail: HTTP ' . $response->getStatusCode());
      }
      
      $image_data = $response->getBody()->getContents();
      
      if (empty($image_data)) {
        throw new \Exception('Empty thumbnail data received');
      }
      
      // Generate filename
      $parsed_url = parse_url($original_url);
      $domain = isset($parsed_url['host']) ? preg_replace('/[^a-zA-Z0-9-]/', '_', $parsed_url['host']) : 'thumbnail';
      $filename = 'hotlink_' . $domain . '_' . time() . '.png';
      
      // Ensure directory exists
      $directory = 'public://hotlinks/thumbnails';
      \Drupal::service('file_system')->prepareDirectory($directory, \Drupal\Core\File\FileSystemInterface::CREATE_DIRECTORY);
      
      // Save file
      $file_uri = $directory . '/' . $filename;
      $file = \Drupal::service('file_system')->saveData($image_data, $file_uri, \Drupal\Core\File\FileSystemInterface::EXISTS_REPLACE);
      
      if ($file) {
        // Create file entity
        $file_entity = File::create([
          'uri' => $file,
          'status' => 1,
          'uid' => \Drupal::currentUser()->id(),
        ]);
        $file_entity->save();
        
        // Set the thumbnail field
        $entity->set('field_hotlink_thumbnail', [
          'target_id' => $file_entity->id(),
          'alt' => 'Thumbnail for ' . $entity->getTitle(),
          'title' => 'Auto-generated thumbnail',
        ]);
        
        \Drupal::logger('hotlinks')->info('Generated thumbnail for @title', [
          '@title' => $entity->getTitle(),
        ]);
        
        return TRUE;
        
      } else {
        throw new \Exception('Failed to save thumbnail file');
      }
      
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
        $url_field = $hotlink->get('field_hotlink_url');
        if (!$url_field->isEmpty()) {
          $url = $url_field->first()->uri;
          
          if ($force_regenerate) {
            // Clear existing thumbnail
            $hotlink->set('field_hotlink_thumbnail', NULL);
          }
          
          // Use the function from hotlinks.module if available
          if (function_exists('hotlinks_generate_thumbnail')) {
            hotlinks_generate_thumbnail($hotlink, $url);
          } else {
            // Fallback: create a new instance to use the method
            $form = new static(\Drupal::configFactory());
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