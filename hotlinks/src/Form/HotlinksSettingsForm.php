<?php

namespace Drupal\hotlinks\Form;

use Drupal\Core\Form\ConfigFormBase;
use Drupal\Core\Form\FormStateInterface;

class HotlinksSettingsForm extends ConfigFormBase {

  public function getFormId() {
    return 'hotlinks_settings_form';
  }

  protected function getEditableConfigNames() {
    return ['hotlinks.settings'];
  }

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
    ];

    $form['display']['open_in_new_window'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Open links in new window'),
      '#default_value' => $config->get('open_in_new_window') ?? TRUE,
    ];

    $form['display']['show_category_counts'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Show category counts'),
      '#default_value' => $config->get('show_category_counts') ?? TRUE,
    ];

    return parent::buildForm($form, $form_state);
  }

  public function submitForm(array &$form, FormStateInterface $form_state) {
    $this->config('hotlinks.settings')
      ->set('show_descriptions', $form_state->getValue('show_descriptions'))
      ->set('open_in_new_window', $form_state->getValue('open_in_new_window'))
      ->set('show_category_counts', $form_state->getValue('show_category_counts'))
      ->save();

    parent::submitForm($form, $form_state);
  }
}