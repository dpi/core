<?php

namespace Drupal\Tests\block_content\Unit;

use Drupal\block_content\BlockContentAccessControlHandler;
use Drupal\block_content\BlockContentInterface;
use Drupal\Core\Access\AccessResultInterface;
use Drupal\Core\Cache\Cache;
use Drupal\Core\Entity\EntityTypeInterface;
use Drupal\Core\Extension\ModuleHandlerInterface;
use Drupal\Core\Language\LanguageInterface;
use Drupal\Core\Session\AccountInterface;
use Drupal\Tests\UnitTestCase;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;

/**
 * Tests block_content entity access.
 *
 * @coversDefaultClass \Drupal\block_content\BlockContentAccessControlHandler
 * @group block_content
 */
class BlockContentEntityAccessControlHandlerTest extends UnitTestCase {

  /**
   * Tests the an operation not implemented by the access control handler.
   */
  public function testUnrecognisedOperation() {
    $entityType = $this->createMock(EntityTypeInterface::class);
    $eventDispatcher = $this->createMock(EventDispatcherInterface::class);
    $eventDispatcher->expects($this->never())
      ->method('dispatch');

    $moduleHandler = $this->createMock(ModuleHandlerInterface::class);
    $moduleHandler->expects($this->exactly(2))
      ->method('invokeAll')
      ->willReturn([]);
    $accessControl = new BlockContentAccessControlHandler($entityType, $eventDispatcher);
    $accessControl->setModuleHandler($moduleHandler);

    $language = $this->createMock(LanguageInterface::class);
    $language->expects($this->any())
      ->method('getId')
      ->will($this->returnValue('de'));
    $entity = $this->createMock(BlockContentInterface::class);
    $entity->expects($this->any())
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
    $access = $accessControl->access($entity, $this->randomMachineName(), $account, TRUE);
    $this->assertInstanceOf(AccessResultInterface::class, $access);
  }

}
