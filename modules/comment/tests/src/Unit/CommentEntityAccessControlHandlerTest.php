<?php

namespace Drupal\Tests\comment\Unit;

use Drupal\comment\CommentAccessControlHandler;
use Drupal\comment\CommentInterface;
use Drupal\Core\Access\AccessResultInterface;
use Drupal\Core\Cache\Context\CacheContextsManager;
use Drupal\Core\DependencyInjection\ContainerBuilder;
use Drupal\Core\Entity\EntityTypeInterface;
use Drupal\Core\Extension\ModuleHandlerInterface;
use Drupal\Core\Language\LanguageInterface;
use Drupal\Core\Session\AccountInterface;
use Drupal\Tests\UnitTestCase;

/**
 * Tests comment entity access.
 *
 * @coversDefaultClass \Drupal\comment\CommentAccessControlHandler
 * @group comment
 */
class CommentEntityAccessControlHandlerTest extends UnitTestCase {

  /**
   * Tests the an operation not implemented by the access control handler.
   */
  public function testUnrecognisedOperation() {
    // Cache utility calls container directly.
    $cacheContextsManager = $this->getMockBuilder(CacheContextsManager::class)
      ->disableOriginalConstructor()
      ->getMock();
    $cacheContextsManager->method('assertValidTokens')->willReturn(TRUE);
    $container = new ContainerBuilder();
    $container->set('cache_contexts_manager', $cacheContextsManager);
    \Drupal::setContainer($container);

    $entityType = $this->createMock(EntityTypeInterface::class);
    $moduleHandler = $this->createMock(ModuleHandlerInterface::class);
    $moduleHandler->expects($this->exactly(2))
      ->method('invokeAll')
      ->willReturn([]);
    $accessControl = new CommentAccessControlHandler($entityType);
    $accessControl->setModuleHandler($moduleHandler);

    $language = $this->createMock(LanguageInterface::class);
    $language->expects($this->any())
      ->method('getId')
      ->will($this->returnValue('de'));
    $entity = $this->createMock(CommentInterface::class);
    $entity->expects($this->any())
      ->method('language')
      ->willReturn($language);

    $account = $this->createMock(AccountInterface::class);
    $access = $accessControl->access($entity, $this->randomMachineName(), $account, TRUE);
    $this->assertInstanceOf(AccessResultInterface::class, $access);
  }

}
