<?php

namespace Drupal\hotlinks\Form;

use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;

class HotlinksSubmitForm extends FormBase {
  public function getFormId() {
    return 'hotlinks_submit_form';
  }

  public function buildForm(array $form, FormStateInterface $form_state) {
    $form['url'] = [
      '#type' => 'url',
      '#title' => $this->t('URL'),
      '#required' => TRUE,
    ];
    $form['submit'] = [
      '#type' => 'submit',
      '#value' => $this->t('Submit'),
    ];
    return $form;
  }

  public function submitForm(array &$form, FormStateInterface $form_state) {
    // Submit handling logic placeholder.
  }
}
