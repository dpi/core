<?php

namespace Drupal\Tests\system\Unit\Flood;

use Drupal\Core\Flood\MemoryBackend;
use Drupal\Tests\UnitTestCase;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;

/**
 * Tests memory flood backend.
 *
 * @coversDefaultClass \Drupal\Core\Flood\MemoryBackend
 * @group system
 */
class MemoryBackendTest extends UnitTestCase {

  /**
   * A memory backend for testing.
   *
   * @var \Drupal\Core\Flood\MemoryBackend
   */
  protected $testMemoryBackend;

  /**
   * A request for testing.
   *
   * @var \PHPUnit\Framework\MockObject\MockObject|\Symfony\Component\HttpFoundation\Request
   */
  protected $testRequest;

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();

    $requestStack = $this->createMock(RequestStack::class);
    $this->testRequest = $this->createMock(Request::class);
    $requestStack->expects($this->any())
      ->method('getCurrentRequest')
      ->willReturn($this->testRequest);

    $this->testMemoryBackend = new class($requestStack) extends MemoryBackend {

      /**
       * The current time for testing.
       *
       * @var float
       */
      protected $currentMicroTime;

      /**
       * {@inheritdoc}
       */
      protected function getCurrentMicroTime(): float {
        return $this->currentMicroTime;
      }

      /**
       * Set the current time.
       *
       * @param float $currentTime
       *   The current time.
       */
      public function setCurrentMicroTime(float $currentTime): void {
        $this->currentMicroTime = $currentTime;
      }

      /**
       * Get all flood events.
       *
       * @return array
       *   All flood events.
       */
      public function getEvents(): array {
        return $this->events;
      }

    };

    $this->testMemoryBackend->setCurrentMicroTime(0.0);
  }

  /**
   * Tests event registration, and no more events allowed after threshold.
   *
   * @covers ::isAllowed
   */
  public function testIsAllowed() {
    $this->testRequest->expects($this->never())->method('getClientIp');
    $eventName = 'test_event_name';
    $identifier = 'test_identifier';
    $window = 10;
    $this->assertTrue($this->testMemoryBackend->isAllowed($eventName, 1, $window, $identifier));
    $this->testMemoryBackend->register($eventName, $window, $identifier);
    $this->assertFalse($this->testMemoryBackend->isAllowed($eventName, 1, $window, $identifier));
  }

  /**
   * Tests when no identifier is passed.
   */
  public function testDefaultIdentifier() {
    $eventName = 'test_event_name';
    $window = 10;
    $ip = '1.2.3.4';
    $identifier = NULL;

    $this->testRequest->expects($this->exactly(3))
      ->method('getClientIp')
      ->willReturn($ip);
    $this->testMemoryBackend->register($eventName, $window, $identifier);
    $this->assertCount(1, $this->testMemoryBackend->getEvents()[$eventName][$ip]);
    $this->testMemoryBackend->isAllowed($eventName, $window, $identifier);
    $this->testMemoryBackend->clear($eventName, $identifier);
    $this->assertCount(0, $this->testMemoryBackend->getEvents()[$eventName]);
  }

  /**
   * Tests pre-expired events are accepted.
   *
   * Even if an expired event is registered, isAllowed will still return
   * false until the event is garbage collected.
   *
   * @covers ::isAllowed
   */
  public function testIsAllowedExpired() {
    $this->testRequest->expects($this->never())->method('getClientIp');
    $eventName = 'test_event_name';
    $identifier = 'test_identifier';
    $window = 10;
    $windowExpired = -1;

    // Register expired event.
    $this->testMemoryBackend->register($eventName, $windowExpired, $identifier);
    // Verify event is not allowed.
    $this->assertFalse($this->testMemoryBackend->isAllowed($eventName, 1, $window, $identifier));
    // Run cron and verify event is now allowed.
    $this->testMemoryBackend->garbageCollection();
    $this->assertTrue($this->testMemoryBackend->isAllowed($eventName, 1, $window, $identifier));
  }

  /**
   * Tests events are garbage collected.
   *
   * @covers ::garbageCollection
   */
  public function testGarbageCollection() {
    $this->testRequest->expects($this->never())->method('getClientIp');
    $this->testMemoryBackend->setCurrentMicroTime(1.0);
    $eventName = 'test_event_name';
    $identifier = 'test_identifier';
    $window = 10;

    $this->assertCount(0, $this->testMemoryBackend->getEvents());
    $this->testMemoryBackend->register($eventName, $window, $identifier);
    $this->assertCount(1, $this->testMemoryBackend->getEvents()[$eventName][$identifier]);

    // Progress time before window, event still exists after garbage collection.
    $this->testMemoryBackend->setCurrentMicroTime(6.0);
    $this->testMemoryBackend->garbageCollection();
    $this->assertCount(1, $this->testMemoryBackend->getEvents()[$eventName][$identifier]);

    // Progress time after window, event deleted after garbage collection.
    $this->testMemoryBackend->setCurrentMicroTime(12.0);
    $this->testMemoryBackend->garbageCollection();
    $this->assertCount(0, $this->testMemoryBackend->getEvents()[$eventName][$identifier]);
  }

  /**
   * Tests clearing events.
   *
   * @covers ::clear
   */
  public function testClear() {
    $this->testRequest->expects($this->never())->method('getClientIp');
    $eventName = 'test_event_name';
    $identifier = 'test_identifier';
    $window = 10;
    $this->testMemoryBackend->register($eventName, $window, $identifier);
    $this->assertCount(1, $this->testMemoryBackend->getEvents()[$eventName][$identifier]);
    $this->testMemoryBackend->clear($eventName, $identifier);
    $this->assertCount(0, $this->testMemoryBackend->getEvents()[$eventName]);
  }

}
