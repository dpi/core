<?php

namespace Drupal\Tests\taxonomy\Unit;

use Drupal\Core\Access\AccessResultInterface;
use Drupal\Core\Cache\Context\CacheContextsManager;
use Drupal\Core\DependencyInjection\ContainerBuilder;
use Drupal\Core\Entity\EntityTypeInterface;
use Drupal\Core\Extension\ModuleHandlerInterface;
use Drupal\Core\Language\LanguageInterface;
use Drupal\Core\Session\AccountInterface;
use Drupal\taxonomy\TermAccessControlHandler;
use Drupal\taxonomy\TermInterface;
use Drupal\Tests\UnitTestCase;

/**
 * Tests term entity access.
 *
 * @coversDefaultClass \Drupal\taxonomy\TermAccessControlHandler
 * @group taxonomy
 */
class TermEntityAccessControlHandlerTest extends UnitTestCase {

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
    $accessControl = new TermAccessControlHandler($entityType);
    $accessControl->setModuleHandler($moduleHandler);

    $language = $this->createMock(LanguageInterface::class);
    $language->expects($this->any())
      ->method('getId')
      ->will($this->returnValue('de'));

    $entity = $this->createMock(TermInterface::class);
    $entity->expects($this->once())
      ->method('language')
      ->willReturn($language);

    $account = $this->createMock(AccountInterface::class);
    $access = $accessControl->access($entity, $this->randomMachineName(), $account, TRUE);
    $this->assertInstanceOf(AccessResultInterface::class, $access);
  }

}
