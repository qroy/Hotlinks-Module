<?php

namespace Drupal\hotlinks\Form;

use Drupal\Core\Form\ConfigFormBase;
use Drupal\Core\Form\FormStateInterface;

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
      '#default_value' => $config->get('auto_thumbnail_service') ?: 'thum_io',
      '#options' => [
        'thum_io' => $this->t('Thum.io (Free)'),
        'screenshotapi' => $this->t('ScreenshotAPI (Free tier available)'),
        'websiteshots' => $this->t('WebsiteShots (Free with watermark)'),
        'htmlcsstoimage' => $this->t('HTML/CSS to Image (Requires API key)'),
      ],
      '#description' => $this->t('Choose which service to use for auto-generating thumbnails. Thum.io works immediately without any setup.'),
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
    
    $form['thumbnails']['info'] = [
      '#type' => 'details',
      '#title' => $this->t('Service Information'),
      '#open' => FALSE,
    ];
    
    $form['thumbnails']['info']['service_details'] = [
      '#markup' => '
        <h4>' . $this->t('Service Comparison:') . '</h4>
        <ul>
          <li><strong>Thum.io:</strong> ' . $this->t('Free service, no registration required. Good quality screenshots.') . '</li>
          <li><strong>ScreenshotAPI:</strong> ' . $this->t('50 free screenshots/month, paid plans available. High quality with customization options.') . '</li>
          <li><strong>WebsiteShots:</strong> ' . $this->t('Free with small watermark. Basic screenshot functionality.') . '</li>
          <li><strong>HTML/CSS to Image:</strong> ' . $this->t('Paid service with excellent quality and fast response times.') . '</li>
        </ul>
        <p><em>' . $this->t('Thumbnails are generated automatically when you save a hotlink without an existing thumbnail.') . '</em></p>
      ',
    ];
    
    $form['thumbnails']['actions'] = [
      '#type' => 'fieldset',
      '#title' => $this->t('Bulk Actions'),
    ];
    
    $form['thumbnails']['actions']['regenerate_thumbnails'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Regenerate ALL existing thumbnails'),
      '#description' => $this->t('<strong>Warning:</strong> This will replace all existing thumbnails (including manually uploaded ones) with auto-generated screenshots. This action cannot be undone.'),
      '#default_value' => FALSE,
    ];
    
    // Count existing hotlinks
    $hotlink_count = \Drupal::entityQuery('node')
      ->condition('type', 'hotlink')
      ->condition('status', 1)
      ->accessCheck(TRUE)
      ->count()
      ->execute();
      
    if ($hotlink_count > 0) {
      $form['thumbnails']['actions']['count_info'] = [
        '#markup' => '<p>' . $this->t('Found @count hotlinks that could have thumbnails regenerated.', ['@count' => $hotlink_count]) . '</p>',
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
      
    // Regenerate thumbnails if requested
    if ($form_state->getValue('regenerate_thumbnails')) {
      $this->regenerateThumbnails();
      $this->messenger()->addWarning($this->t('Thumbnail regeneration has been queued. This may take several minutes to complete.'));
    }

    parent::submitForm($form, $form_state);
  }
  
  /**
   * Regenerate all thumbnails using batch processing.
   */
  private function regenerateThumbnails() {
    $nids = \Drupal::entityQuery('node')
      ->condition('type', 'hotlink')
      ->condition('status', 1)
      ->accessCheck(TRUE)
      ->execute();
    
    if (!empty($nids)) {
      $batch = [
        'title' => $this->t('Regenerating thumbnails...'),
        'operations' => [],
        'finished' => '\Drupal\hotlinks\Form\HotlinksSettingsForm::batchFinished',
        'progress_message' => $this->t('Processing @current of @total hotlinks...'),
      ];
      
      foreach ($nids as $nid) {
        $batch['operations'][] = [
          '\Drupal\hotlinks\Form\HotlinksSettingsForm::batchRegenerateThumbnail', 
          [$nid]
        ];
      }
      
      batch_set($batch);
    } else {
      $this->messenger()->addMessage($this->t('No hotlinks found to regenerate thumbnails for.'));
    }
  }
  
  /**
   * Batch operation to regenerate a single thumbnail.
   */
  public static function batchRegenerateThumbnail($nid, &$context) {
    $hotlink = \Drupal::entityTypeManager()->getStorage('node')->load($nid);
    
    if ($hotlink) {
      // Clear existing thumbnail
      $hotlink->set('field_hotlink_thumbnail', NULL);
      
      // Generate new thumbnail (this will call the hook_entity_presave)
      $hotlink->save();
      
      $context['message'] = t('Regenerated thumbnail for: @title', [
        '@title' => $hotlink->getTitle()
      ]);
      
      $context['results'][] = $nid;
    }
  }
  
  /**
   * Batch finished callback.
   */
  public static function batchFinished($success, $results, $operations) {
    if ($success) {
      $message = \Drupal::translation()->formatPlural(
        count($results),
        'Regenerated thumbnail for 1 hotlink.',
        'Regenerated thumbnails for @count hotlinks.'
      );
      \Drupal::messenger()->addMessage($message);
    } else {
      \Drupal::messenger()->addError(t('Thumbnail regeneration encountered errors.'));
    }
  }

}