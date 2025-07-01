<?php
namespace Drupal\hotlinks\Form;
use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;

class ReportLinkForm extends FormBase {
  public function getFormId() { return 'hotlinks_report_form'; }
  public function buildForm(array $form, FormStateInterface $form_state, $nid = NULL) {
    $form['nid'] = ['#type'=>'value','#value'=>$nid];
    $form['message'] = ['#type'=>'textarea','#title'=>$this->t('Reason')];
    $form['submit'] = ['#type'=>'submit','#value'=>$this->t('Report')];
    return $form;
  }
  public function submitForm(array &$form, FormStateInterface $form_state) {
    $values = $form_state->getValues();
    \Drupal::database()->insert('hotlinks_reports')->fields([
      'nid'=>$values['nid'],'uid'=>\Drupal::currentUser()->id(),'message'=>$values['message'],'created'=>REQUEST_TIME
    ])->execute();
    \Drupal::messenger()->addStatus($this->t('Report submitted.'));
  }
}
