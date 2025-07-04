<?php

namespace Drupal\hotlinks\TwigExtension;

use Drupal\Core\Entity\EntityTypeManagerInterface;
use Twig\Extension\AbstractExtension;
use Twig\TwigFilter;

/**
 * Twig extension for Hotlinks module.
 */
class HotlinksTwigExtension extends AbstractExtension {

  /**
   * The entity type manager.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected $entityTypeManager;

  /**
   * Constructs a new HotlinksTwigExtension object.
   *
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entity_type_manager
   *   The entity type manager.
   */
  public function __construct(EntityTypeManagerInterface $entity_type_manager) {
    $this->entityTypeManager = $entity_type_manager;
  }

  /**
   * {@inheritdoc}
   */
  public function getFilters() {
    return [
      new TwigFilter('taxonomy_term_name', [$this, 'getTaxonomyTermName']),
      new TwigFilter('taxonomy_term_description', [$this, 'getTaxonomyTermDescription']),
      new TwigFilter('taxonomy_term_url', [$this, 'getTaxonomyTermUrl']),
    ];
  }

  /**
   * Get taxonomy term name by ID.
   *
   * @param int $term_id
   *   The taxonomy term ID.
   *
   * @return string
   *   The term name or empty string if not found.
   */
  public function getTaxonomyTermName($term_id) {
    if (empty($term_id) || !is_numeric($term_id)) {
      return '';
    }

    try {
      $term = $this->entityTypeManager->getStorage('taxonomy_term')->load($term_id);
      if ($term) {
        return $term->getName();
      }
    } catch (\Exception $e) {
      \Drupal::logger('hotlinks')->error('Error loading term name for ID @id: @message', [
        '@id' => $term_id,
        '@message' => $e->getMessage(),
      ]);
    }

    return '';
  }

  /**
   * Get taxonomy term description by ID.
   *
   * @param int $term_id
   *   The taxonomy term ID.
   *
   * @return string
   *   The term description or empty string if not found.
   */
  public function getTaxonomyTermDescription($term_id) {
    if (empty($term_id) || !is_numeric($term_id)) {
      return '';
    }

    try {
      $term = $this->entityTypeManager->getStorage('taxonomy_term')->load($term_id);
      if ($term) {
        $description = $term->getDescription();
        // Return the processed description (handles text format)
        if (is_array($description) && isset($description['value'])) {
          return $description['value'];
        }
        return $description;
      }
    } catch (\Exception $e) {
      \Drupal::logger('hotlinks')->error('Error loading term description for ID @id: @message', [
        '@id' => $term_id,
        '@message' => $e->getMessage(),
      ]);
    }

    return '';
  }

  /**
   * Get taxonomy term URL by ID.
   *
   * @param int $term_id
   *   The taxonomy term ID.
   *
   * @return string
   *   The term URL or empty string if not found.
   */
  public function getTaxonomyTermUrl($term_id) {
    if (empty($term_id) || !is_numeric($term_id)) {
      return '';
    }

    try {
      $term = $this->entityTypeManager->getStorage('taxonomy_term')->load($term_id);
      if ($term) {
        return \Drupal\Core\Url::fromRoute('hotlinks.category', ['category' => $term_id])->toString();
      }
    } catch (\Exception $e) {
      \Drupal::logger('hotlinks')->error('Error generating term URL for ID @id: @message', [
        '@id' => $term_id,
        '@message' => $e->getMessage(),
      ]);
    }

    return '';
  }

  /**
   * {@inheritdoc}
   */
  public function getName() {
    return 'hotlinks_twig_extension';
  }
}