<?php

namespace Drupal\hotlinks\Controller;

use Drupal\Core\Controller\ControllerBase;

class HotlinksController extends ControllerBase {
  public function overview() {
    return [
      '#markup' => $this->t('Hotlinks overview page placeholder.'),
    ];
  }
}
