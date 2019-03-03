<?php

namespace Drupal\Tests\contact\Unit;

use Drupal\contact\ContactMessageAccessControlHandler;
use Drupal\contact\MessageInterface;
use Drupal\Core\Access\AccessResultInterface;
use Drupal\Core\Entity\EntityTypeInterface;
use Drupal\Core\Extension\ModuleHandlerInterface;
use Drupal\Core\Language\LanguageInterface;
use Drupal\Core\Session\AccountInterface;
use Drupal\Tests\UnitTestCase;

/**
 * Tests contact_message entity access.
 *
 * @coversDefaultClass \Drupal\contact\ContactMessageAccessControlHandler
 * @group contact
 */
class ContactMessageEntityAccessControlHandlerTest extends UnitTestCase {

  /**
   * Tests the an operation not implemented by the access control handler.
   */
  public function testUnrecognisedOperation() {
    $entityType = $this->createMock(EntityTypeInterface::class);
    $moduleHandler = $this->createMock(ModuleHandlerInterface::class);
    $moduleHandler->expects($this->exactly(2))
      ->method('invokeAll')
      ->willReturn([]);
    $accessControl = new ContactMessageAccessControlHandler($entityType);
    $accessControl->setModuleHandler($moduleHandler);

    $language = $this->createMock(LanguageInterface::class);
    $language->expects($this->any())
      ->method('getId')
      ->will($this->returnValue('de'));
    $entity = $this->createMock(MessageInterface::class);
    $entity->expects($this->any())
      ->method('language')
      ->willReturn($language);

    $account = $this->createMock(AccountInterface::class);
    $access = $accessControl->access($entity, $this->randomMachineName(), $account, TRUE);
    $this->assertInstanceOf(AccessResultInterface::class, $access);
  }

}
