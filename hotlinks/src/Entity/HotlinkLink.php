<?php
namespace Drupal\hotlinks\Entity;

use Drupal\Core\Config\Entity\ConfigEntityBase;
use Drupal\hotlinks\HotlinkLinkInterface;

/**
 * Defines the Hotlink link entity.
 *
 * @ConfigEntityType(
 *   id = "hotlinks_link",
 *   label = @Translation("Hotlink"),
 *   handlers = {
 *     "list_builder" = "Drupal\\hotlinks\\HotlinkLinkListBuilder",
 *     "form" = {
 *       "add" = "Drupal\\hotlinks\\Form\\HotlinkLinkForm",
 *       "edit" = "Drupal\\hotlinks\\Form\\HotlinkLinkForm",
 *       "delete" = "Drupal\\Core\\Entity\\EntityDeleteForm"
 *     }
 *   },
 *   config_prefix = "hotlinks_link",
 *   admin_permission = "administer hotlinks",
 *   entity_keys = {
 *     "id" = "id",
 *     "label" = "title"
 *   },
 *   config_export = {"id","title","url","category","weight"}
 * )
 */
class HotlinkLink extends ConfigEntityBase implements HotlinkLinkInterface {
  protected $id;
  protected $title;
  protected $url;
  protected $category;
  protected $weight = 0;
}
