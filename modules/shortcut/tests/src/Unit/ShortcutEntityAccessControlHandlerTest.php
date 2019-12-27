<?php

namespace Drupal\Tests\shortcut\Unit;

use Drupal\Core\Access\AccessResultInterface;
use Drupal\Core\Cache\Cache;
use Drupal\Core\Entity\EntityTypeInterface;
use Drupal\Core\Extension\ModuleHandlerInterface;
use Drupal\Core\Language\LanguageInterface;
use Drupal\Core\Session\AccountInterface;
use Drupal\shortcut\ShortcutAccessControlHandler;
use Drupal\shortcut\ShortcutInterface;
use Drupal\shortcut\ShortcutSetStorageInterface;
use Drupal\Tests\UnitTestCase;

/**
 * Tests shortcut entity access.
 *
 * @coversDefaultClass \Drupal\shortcut\ShortcutAccessControlHandler
 * @group shortcut
 */
class ShortcutEntityAccessControlHandlerTest extends UnitTestCase {

  /**
   * Test an operation not implemented by the access control handler.
   */
  public function testUnrecognisedOperation() {
    $entityType = $this->createMock(EntityTypeInterface::class);
    $moduleHandler = $this->createMock(ModuleHandlerInterface::class);
    $moduleHandler->expects($this->exactly(2))
      ->method('invokeAll')
      ->willReturn([]);
    $shortcutStorage = $this->createMock(ShortcutSetStorageInterface::class);

    $language = $this->createMock(LanguageInterface::class);
    $language->expects($this->any())
      ->method('getId')
      ->will($this->returnValue('de'));

    $entity = $this->createMock(ShortcutInterface::class);
    $entity->expects($this->once())
      ->method('language')
      ->willReturn($language);
    $entity->expects($this->once())
      ->method('getCacheMaxAge')
      ->willReturn(Cache::PERMANENT);
    $entity->expects($this->once())
      ->method('getCacheTags')
      ->will($this->returnValue([]));
    $entity->expects($this->once())
      ->method('getCacheContexts')
      ->willReturn([]);

    $account = $this->createMock(AccountInterface::class);

    $accessControl = new ShortcutAccessControlHandler($entityType, $shortcutStorage);
    $accessControl->setModuleHandler($moduleHandler);
    $access = $accessControl->access($entity, $this->randomMachineName(), $account, TRUE);
    $this->assertInstanceOf(AccessResultInterface::class, $access);
  }

}
