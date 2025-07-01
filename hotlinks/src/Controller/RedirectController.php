<?php
namespace Drupal\hotlinks\Controller;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Drupal\node\Entity\Node;

class RedirectController {
  public function redirect($nid) {
    $node = Node::load($nid);
    if ($node && $node->bundle() == 'link') {
      $count = $node->get('field_hotlink_click_count')->value;
      $node->set('field_hotlink_click_count',$count+1)->save();
      return new RedirectResponse($node->get('field_hotlink_url')->uri);
    }
    throw new \Symfony\Component\HttpKernel\Exception\NotFoundHttpException();
  }
}
