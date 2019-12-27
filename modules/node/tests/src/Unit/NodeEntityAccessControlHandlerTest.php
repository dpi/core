<?php

namespace Drupal\Tests\node\Unit;

use Drupal\Core\Access\AccessResultInterface;
use Drupal\Core\Access\AccessResultNeutral;
use Drupal\Core\Cache\Context\CacheContextsManager;
use Drupal\Core\DependencyInjection\ContainerBuilder;
use Drupal\Core\Entity\EntityTypeInterface;
use Drupal\Core\Extension\ModuleHandlerInterface;
use Drupal\Core\Language\LanguageInterface;
use Drupal\Core\Session\AccountInterface;
use Drupal\node\NodeAccessControlHandler;
use Drupal\node\NodeGrantDatabaseStorageInterface;
use Drupal\node\NodeInterface;
use Drupal\Tests\UnitTestCase;

/**
 * Tests node entity access.
 *
 * @coversDefaultClass \Drupal\node\NodeAccessControlHandler
 * @group node
 */
class NodeEntityAccessControlHandlerTest extends UnitTestCase {

  /**
   * Test an operation not implemented by the access control handler.
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
    $nodeGrants = $this->createMock(NodeGrantDatabaseStorageInterface::class);
    $nodeGrants->expects($this->once())
      ->method('access')
      ->willReturn(new AccessResultNeutral());

    $moduleHandler = $this->createMock(ModuleHandlerInterface::class);
    $moduleHandler->expects($this->exactly(2))
      ->method('invokeAll')
      ->willReturn([]);

    $language = $this->createMock(LanguageInterface::class);
    $language->expects($this->any())
      ->method('getId')
      ->will($this->returnValue('de'));

    $entity = $this->createMock(NodeInterface::class);
    $entity->expects($this->any())
      ->method('language')
      ->willReturn($language);
    $entity->expects($this->once())
      ->method('isPublished')
      ->willReturn(TRUE);
    $entity->expects($this->once())
      ->method('getOwnerId')
      ->willReturn(2);

    $account = $this->createMock(AccountInterface::class);
    $account->expects($this->any())
      ->method('hasPermission')
      ->withConsecutive([], ['access content'])
      ->willReturnOnConsecutiveCalls(FALSE, TRUE);

    $accessControl = new NodeAccessControlHandler($entityType, $nodeGrants);
    $accessControl->setModuleHandler($moduleHandler);
    $access = $accessControl->access($entity, $this->randomMachineName(), $account, TRUE);
    $this->assertInstanceOf(AccessResultInterface::class, $access);
  }

}
