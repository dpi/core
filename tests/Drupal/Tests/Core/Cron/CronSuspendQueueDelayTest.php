<?php

namespace Drupal\Tests\Core\Cron;

use Drupal\Component\Datetime\TimeInterface;
use Drupal\Core\Cron;
use Drupal\Core\DependencyInjection\ContainerBuilder;
use Drupal\Core\Extension\ModuleHandlerInterface;
use Drupal\Core\Lock\LockBackendInterface;
use Drupal\Core\Logger\LoggerChannelFactoryInterface;
use Drupal\Core\Queue\QueueFactory;
use Drupal\Core\Queue\QueueInterface;
use Drupal\Core\Queue\QueueWorkerInterface;
use Drupal\Core\Queue\QueueWorkerManagerInterface;
use Drupal\Core\Queue\SuspendQueueException;
use Drupal\Core\Session\AccountSwitcherInterface;
use Drupal\Core\Site\Settings;
use Drupal\Core\State\StateInterface;
use Drupal\Tests\UnitTestCase;
use Psr\Log\LoggerInterface;

/**
 * Test Cron handling of suspended queues with a delay.
 *
 * @group Cron
 * @covers \Drupal\Core\Queue\SuspendQueueException
 * @coversDefaultClass \Drupal\Core\Cron
 */
final class CronSuspendQueueDelayTest extends UnitTestCase {

  /**
   * Constructor arguments for \Drupal\Core\Cron.
   *
   * @var object[]|\PHPUnit\Framework\MockObject\MockObject[]
   */
  protected $cronConstructorArguments;

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();
    $this->cronConstructorArguments = [
      'module_handler' => $this->createMock(ModuleHandlerInterface::class),
      'lock_backend' => $this->createMock(LockBackendInterface::class),
      'queue_factory' => $this->createMock(QueueFactory::class),
      'state' => $this->createMock(StateInterface::class),
      'account_switcher' => $this->createMock(AccountSwitcherInterface::class),
      'logger' => $this->createMock(LoggerInterface::class),
      'queue_manager' => $this->createMock(QueueWorkerManagerInterface::class),
      'time' => $this->createMock(TimeInterface::class),
      'settings' => new Settings([]),
    ];

    // Capture logs to watchdog_exception().
    $loggerFactory = $this->createMock(LoggerChannelFactoryInterface::class);
    $container = new ContainerBuilder();
    $container->set('logger.factory', $loggerFactory);
    \Drupal::setContainer($container);

    $loggerFactory->expects($this->atLeast(1))
      ->method('get')
      ->with('cron')
      ->willReturn($this->createMock(LoggerInterface::class));
  }

  /**
   * Tests a queue is reprocessed again after other queues.
   *
   * Two queues are created:
   *  - test_worker_a
   *  - test_worker_b
   *
   * Queues and items are processed:
   *  - test_worker_a:
   *    - item throws SuspendQueueException with 2.0 delay.
   *  - test_worker_b:
   *    - item executes normally.
   *  - test_worker_a:
   *    - item throws SuspendQueueException with 3.0 delay.
   *  - test_worker_a:
   *    - no items remaining, quits.
   */
  public function testSuspendQueue(): void {
    [
      'queue_factory' => $queueFactory,
      'queue_manager' => $queueManager,
      'time' => $time,
    ] = $this->cronConstructorArguments;

    $cron = $this->getMockBuilder(Cron::class)
      ->onlyMethods(['usleep'])
      ->setConstructorArgs($this->cronConstructorArguments)
      ->getMock();

    $cron->expects($this->exactly(2))
      ->method('usleep')
      ->withConsecutive(
        [$this->equalTo(2000000)],
        [$this->equalTo(3000000)],
      );

    $queueManager->expects($this->once())
      ->method('getDefinitions')
      ->willReturn([
        'test_worker_a' => [
          'id' => 'test_worker_a',
          'cron' => 300,
        ],
        'test_worker_b' => [
          'id' => 'test_worker_b',
          'cron' => 300,
        ],
      ]);

    $queueA = $this->createMock(QueueInterface::class);
    $queueB = $this->createMock(QueueInterface::class);
    $queueFactory->expects($this->exactly(2))
      ->method('get')
      ->willReturnMap([
        ['test_worker_a', FALSE, $queueA],
        ['test_worker_b', FALSE, $queueB],
      ]);

    // Expect this queue to be processed twice.
    $queueA->expects($this->exactly(3))
      ->method('claimItem')
      ->willReturnOnConsecutiveCalls(
      // First run will suspend for 2 seconds.
        (object) ['data' => 'test_data_a1'],
        // Second run will suspend for 3 seconds.
        (object) ['data' => 'test_data_a2'],
        // This will terminate the queue normally.
        FALSE,
      );
    // Expect this queue to be processed once.
    $queueB->expects($this->exactly(2))
      ->method('claimItem')
      ->willReturnOnConsecutiveCalls(
        (object) ['data' => 'test_data_b1'],
        // This will terminate the queue normally.
        FALSE,
      );

    $workerA = $this->createMock(QueueWorkerInterface::class);
    $workerB = $this->createMock(QueueWorkerInterface::class);
    $queueManager->expects($this->any())
      ->method('createInstance')
      ->willReturnMap([
        ['test_worker_a', [], $workerA],
        ['test_worker_b', [], $workerB],
      ]);

    $workerA->expects($this->exactly(2))
      ->method('processItem')
      ->with($this->anything())
      ->willReturnOnConsecutiveCalls(
        $this->throwException(new SuspendQueueException('', 0, NULL, 2.0)),
        $this->throwException(new SuspendQueueException('', 0, NULL, 3.0))
      );
    $workerB->expects($this->once())
      ->method('processItem')
      ->with('test_data_b1');

    $time->expects($this->any())
      ->method('getCurrentTime')
      ->willReturn(60);

    $cron->run();
  }

  /**
   * Tests queues may be re-processed by whether delay exceeds threshold.
   *
   * Cron will pause and reprocess a queue after a delay if a worker throws
   * a SuspendQueueException with a delay time not exceeding the maximum wait
   * setting.
   *
   * @param float $threshold
   *   The configured threshold.
   * @param float $suspendQueueDelay
   *   An interval in seconds a worker will suspend the queue.
   * @param bool $expectQueueDelay
   *   Whether to expect cron to sleep and re-process the queue.
   *
   * @dataProvider providerSuspendQueueThreshold
   */
  public function testSuspendQueueThreshold(float $threshold, float $suspendQueueDelay, bool $expectQueueDelay): void {
    $this->cronConstructorArguments['settings'] = new Settings([
      'queue_suspend_maximum_wait' => $threshold,
    ]);
    [
      'queue_factory' => $queueFactory,
      'queue_manager' => $queueManager,
    ] = $this->cronConstructorArguments;

    $cron = $this->getMockBuilder(Cron::class)
      ->onlyMethods(['usleep'])
      ->setConstructorArgs($this->cronConstructorArguments)
      ->getMock();

    $cron->expects($expectQueueDelay ? $this->once() : $this->never())
      ->method('usleep');

    $queueManager->expects($this->once())
      ->method('getDefinitions')
      ->willReturn([
        'test_worker' => [
          'id' => 'test_worker',
          'cron' => 300,
        ],
      ]);

    $queue = $this->createMock(QueueInterface::class);
    $queueFactory->expects($this->once())
      ->method('get')
      ->willReturnMap([
        ['test_worker', FALSE, $queue],
      ]);
    $queue->expects($this->exactly($expectQueueDelay ? 2 : 1))
      ->method('claimItem')
      ->willReturnOnConsecutiveCalls(
        (object) ['data' => 'test_data'],
        FALSE,
      );

    $worker = $this->createMock(QueueWorkerInterface::class);
    $queueManager->expects($this->exactly(1))
      ->method('createInstance')
      ->with('test_worker')
      ->willReturn($worker);

    $worker->expects($this->once())
      ->method('processItem')
      ->with($this->anything())
      ->willReturnOnConsecutiveCalls(
        $this->throwException(new SuspendQueueException('', 0, NULL, $suspendQueueDelay)),
      );

    $cron->run();
  }

  /**
   * Data for testing.
   *
   * @return array
   *   Scenarios for testing.
   */
  public function providerSuspendQueueThreshold(): array {
    $scenarios = [];
    $scenarios['cron will wait for the queue, and rerun'] = [
      15.0,
      10.0,
      TRUE,
    ];
    $scenarios['cron will not wait for the queue, and exit'] = [
      15.0,
      20.0,
      FALSE,
    ];
    return $scenarios;
  }

}
