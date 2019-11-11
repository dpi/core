<?php

namespace Drupal\path\Plugin\Field\FieldType;

use Drupal\Core\Access\AccessResult;
use Drupal\Core\Entity\EntityMalformedException;
use Drupal\Core\Entity\Exception\UndefinedLinkTemplateException;
use Drupal\Core\Field\FieldItemList;
use Drupal\Core\Session\AccountInterface;
use Drupal\Core\TypedData\ComputedItemListTrait;

/**
 * Represents a configurable entity path field.
 */
class PathFieldItemList extends FieldItemList {

  use ComputedItemListTrait;

  /**
   * {@inheritdoc}
   */
  protected function computeValue() {
    // Default the langcode to the current language if this is a new entity or
    // there is no alias for an existent entity.
    // @todo Set the langcode to not specified for untranslatable fields
    //   in https://www.drupal.org/node/2689459.
    $value = ['langcode' => $this->getLangcode()];

    $entity = $this->getEntity();
    if (!$entity->isNew()) {
      try {
        $internalPath = $entity->toUrl()->getInternalPath();
      }
      catch (EntityMalformedException $exception) {
      }
      catch (UndefinedLinkTemplateException $exception) {
      }
      catch (\UnexpectedValueException $exception) {
      }
      if (isset($internalPath)) {
        /** @var \Drupal\Core\Path\AliasRepositoryInterface $path_alias_repository */
        $path_alias_repository = \Drupal::service('path_alias.repository');

        if ($path_alias = $path_alias_repository->lookupBySystemPath('/' . $internalPath, $this->getLangcode())) {
          $value = [
            'alias' => $path_alias['alias'],
            'pid' => $path_alias['id'],
            'langcode' => $path_alias['langcode'],
          ];
        }
      }
    }

    $this->list[0] = $this->createItem(0, $value);
  }

  /**
   * {@inheritdoc}
   */
  public function defaultAccess($operation = 'view', AccountInterface $account = NULL) {
    if ($operation == 'view') {
      return AccessResult::allowed();
    }
    return AccessResult::allowedIfHasPermissions($account, ['create url aliases', 'administer url aliases'], 'OR')->cachePerPermissions();
  }

  /**
   * {@inheritdoc}
   */
  public function delete() {
    // Delete all aliases associated with this entity in the current language.
    $entity = $this->getEntity();
    $path_alias_storage = \Drupal::entityTypeManager()->getStorage('path_alias');
    $entities = $path_alias_storage->loadByProperties([
      'path' => '/' . $entity->toUrl()->getInternalPath(),
      'langcode' => $entity->language()->getId(),
    ]);
    $path_alias_storage->delete($entities);
  }

}
