<?php

namespace Drupal\layout_builder\Controller;

use Drupal\Core\DependencyInjection\ClassResolverInterface;
use Drupal\Core\DependencyInjection\ContainerInjectionInterface;
use Drupal\Core\Entity\EntityInterface;
use Drupal\layout_builder\LayoutTempstoreRepositoryInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Defines a controller to move a block.
 *
 * @internal
 */
class MoveBlockController implements ContainerInjectionInterface {

  use LayoutRebuildTrait;

  /**
   * The layout tempstore repository.
   *
   * @var \Drupal\layout_builder\LayoutTempstoreRepositoryInterface
   */
  protected $layoutTempstoreRepository;

  /**
   * LayoutController constructor.
   *
   * @param \Drupal\layout_builder\LayoutTempstoreRepositoryInterface $layout_tempstore_repository
   *   The layout tempstore repository.
   * @param \Drupal\Core\DependencyInjection\ClassResolverInterface $class_resolver
   *   The class resolver.
   */
  public function __construct(LayoutTempstoreRepositoryInterface $layout_tempstore_repository, ClassResolverInterface $class_resolver) {
    $this->layoutTempstoreRepository = $layout_tempstore_repository;
    $this->classResolver = $class_resolver;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('layout_builder.tempstore_repository'),
      $container->get('class_resolver')
    );
  }

  /**
   * Moves a block to another region.
   *
   * @param \Drupal\Core\Entity\EntityInterface $entity
   *   The entity.
   * @param int $delta_from
   *   The delta of the original section.
   * @param int $delta_to
   *   The delta of the destination section.
   * @param string $region_from
   *   The original region for this block.
   * @param string $region_to
   *   The new region for this block.
   * @param string $block_uuid
   *   The UUID for this block.
   * @param string|null $preceding_block_uuid
   *   (optional) If provided, the UUID of the block to insert this block after.
   *
   * @return \Drupal\Core\Ajax\AjaxResponse
   *   An AJAX response.
   */
  public function build(EntityInterface $entity, $delta_from, $delta_to, $region_from, $region_to, $block_uuid, $preceding_block_uuid = NULL) {
    /** @var \Drupal\layout_builder\SectionStorageInterface $field_list */
    $field_list = $entity->layout_builder__layout;
    $section = $field_list->getSection($delta_from);

    $component = $section->getComponent($block_uuid);
    $section->removeComponent($block_uuid);

    // If the block is moving from one section to another, update the original
    // section and load the new one.
    if ($delta_from !== $delta_to) {
      $section = $field_list->getSection($delta_to);
    }

    // If a preceding block was specified, insert after that. Otherwise add the
    // block to the front.
    $component->setRegion($region_to);
    if (isset($preceding_block_uuid)) {
      $section->insertAfterComponent($preceding_block_uuid, $component);
    }
    else {
      $section->appendComponent($component);
    }

    $this->layoutTempstoreRepository->set($entity);
    return $this->rebuildLayout($entity);
  }

}
