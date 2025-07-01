<?php
namespace Drupal\hotlinks\Entity;

use Drupal\Core\Config\Entity\ConfigEntityBase;
use Drupal\hotlinks\HotlinkCategoryInterface;

/**
 * Defines the Hotlink category entity.
 *
 * @ConfigEntityType(
 *   id = "hotlinks_category",
 *   label = @Translation("Hotlink category"),
 *   handlers = {
 *     "list_builder" = "Drupal\\hotlinks\\HotlinkCategoryListBuilder",
 *     "form" = {
 *       "add" = "Drupal\\hotlinks\\Form\\HotlinkCategoryForm",
 *       "edit" = "Drupal\\hotlinks\\Form\\HotlinkCategoryForm",
 *       "delete" = "Drupal\\Core\\Entity\\EntityDeleteForm"
 *     }
 *   },
 *   config_prefix = "hotlinks_category",
 *   admin_permission = "administer hotlinks",
 *   entity_keys = {
 *     "id" = "id",
 *     "label" = "label",
 *     "weight" = "weight"
 *   },
 *   config_export = {"id","label","weight"}
 * )
 */
class HotlinkCategory extends ConfigEntityBase implements HotlinkCategoryInterface {
  protected $id;
  protected $label;
  protected $weight = 0;
}
