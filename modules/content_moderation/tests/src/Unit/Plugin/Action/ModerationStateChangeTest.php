<?php

namespace Drupal\Tests\content_moderation\Unit\Plugin\Action;

use Drupal\content_moderation\ModerationInformationInterface;
use Drupal\content_moderation\Plugin\Action\ModerationStateChange;
use Drupal\content_moderation\Plugin\Field\ModerationStateFieldItemList;
use Drupal\Core\Access\AccessResultAllowed;
use Drupal\Core\Access\AccessResultForbidden;
use Drupal\Core\DependencyInjection\ContainerBuilder;
use Drupal\Core\Session\AccountInterface;
use Drupal\node\NodeInterface;
use Drupal\Tests\UnitTestCase;
use Drupal\workflows\StateInterface;
use Drupal\workflows\TransitionInterface;
use Drupal\workflows\WorkflowInterface;
use Drupal\workflows\WorkflowTypeInterface;

/**
 * @coversDefaultClass \Drupal\content_moderation\Plugin\Action\ModerationStateChange
 * @group content_moderation
 */
class ModerationStateChangeTest extends UnitTestCase {

  /**
   * The mocked node.
   *
   * @var \Drupal\node\NodeInterface|\PHPUnit_Framework_MockObject_MockObject
   */
  protected $node;

  /**
   * The moderation info service.
   *
   * @var \Drupal\content_moderation\ModerationInformationInterface|\PHPUnit_Framework_MockObject_MockObject
   */
  protected $moderationInfo;

  /**
   * The moderation info service.
   *
   * @var \Drupal\workflows\WorkflowInterface|\PHPUnit_Framework_MockObject_MockObject
   */
  protected $workflow;

  /**
   * The cache contexts manager.
   *
   * @var \Drupal\Core\Cache\Context\CacheContextsManager|\PHPUnit_Framework_MockObject_MockObject
   */
  protected $cacheContextsManager;

  /**
   * {@inheritdoc}
   */
  protected function setUp() {
    parent::setUp();

    $this->cacheContextsManager = $this->getMockBuilder('Drupal\Core\Cache\Context\CacheContextsManager')
      ->disableOriginalConstructor()
      ->getMock();

    $this->cacheContextsManager->method('assertValidTokens')->willReturn(TRUE);
    $container = new ContainerBuilder();
    $container->set('cache_contexts_manager', $this->cacheContextsManager);
    \Drupal::setContainer($container);
  }

  /**
   * Tests the execute method.
   */
  public function testExecuteModerationStateChange() {
    $this->moderationInfo = $this->getMock(ModerationInformationInterface::class);

    $this->node = $this
      ->getMockBuilder(NodeInterface::class)
      ->disableOriginalConstructor()
      ->getMock();
    $entity_type_id = $this->returnValue('node');
    $entity_id = $this->returnValue(1);
    $this->node->expects($this->any())
      ->method('getEntityTypeId')
      ->willReturn($entity_type_id);

    $this->node->expects($this->any())
      ->method('id')
      ->willReturn($entity_id);

    $this->node->expects($this->once())
      ->method('save');

    $moderation_state = $this
      ->getMockBuilder(ModerationStateFieldItemList::class)
      ->disableOriginalConstructor()
      ->getMock();
    $moderation_state->expects($this->once())
      ->method('__set')
      ->with('value', 'foobar');
    $this->node->moderation_state = $moderation_state;

    $this->moderationInfo->expects($this->once())
      ->method('getLatestRevision')
      ->with($entity_type_id, $entity_id)
      ->will($this->returnValue($this->node));

    $config = ['state' => 'foobar'];
    $plugin = new ModerationStateChange($config, 'moderation_state_change', ['type' => 'node'], $this->moderationInfo);

    $plugin->execute($this->node);
  }

  /**
   * Data provider for the the access method test.
   */
  public function accessModerationStateChangeDataProvider() {
    $this->moderationInfo = $this->getMock(ModerationInformationInterface::class);

    $this->node = $this
      ->getMockBuilder(NodeInterface::class)
      ->disableOriginalConstructor()
      ->getMock();

    $entity_type_id = $this->returnValue('node');
    $entity_id = $this->returnValue(1);

    $this->node->expects($this->any())
      ->method('getEntityTypeId')
      ->willReturn($entity_type_id);

    $this->node->expects($this->any())
      ->method('id')
      ->willReturn($entity_id);

    $this->workflow = $this->getMock(WorkflowInterface::class);
    $this->workflow->expects($this->any())
      ->method('getCacheContexts')
      ->willReturn([]);
    $this->workflow->expects($this->any())
      ->method('getCacheTags')
      ->willReturn([]);
    $this->workflow->expects($this->any())
      ->method('getCacheMaxAge')
      ->willReturn(0);

    $moderation_info = clone $this->moderationInfo;

    // No object given.
    $data['no-object-given'] = [$moderation_info, NULL, FALSE];

    // Invalid object given.
    $moderation_info = clone $this->moderationInfo;

    $data['invalid-object-given'] = [$moderation_info, new \stdClass(), FALSE];

    // Object has no workflow.
    $moderation_info = clone $this->moderationInfo;
    $node = clone $this->node;

    $moderation_info->expects($this->once())
      ->method('getWorkflowForEntity')
      ->with($node)
      ->will($this->returnValue(NULL));

    $data['no-workflow'] = [$moderation_info, $node, FALSE];

    // Different workflow.
    $workflow = clone $this->workflow;

    $workflow->expects($this->once())
      ->method('id')
      ->willReturn('bar');

    $moderation_info = clone $this->moderationInfo;
    $node = clone $this->node;

    $moderation_info->expects($this->once())
      ->method('getWorkflowForEntity')
      ->with($node)
      ->will($this->returnValue($workflow));

    $data['different-workflow'] = [$moderation_info, $node, FALSE];

    // Same workflow but no node update access.
    $workflow = clone $this->workflow;

    $workflow->expects($this->once())
      ->method('id')
      ->willReturn('foo');

    $moderation_info = clone $this->moderationInfo;
    $node = clone $this->node;

    $moderation_info->expects($this->once())
      ->method('getWorkflowForEntity')
      ->with($node)
      ->will($this->returnValue($workflow));

    $node->moderation_state = (object) ['value' => 'foobar'];

    $moderation_info->expects($this->once())
      ->method('getLatestRevision')
      ->with($entity_type_id, $entity_id)
      ->will($this->returnValue($node));

    $forbidden_access = new AccessResultForbidden();
    $node->expects($this->once())
      ->method('access')
      ->with('update', NULL, TRUE)
      ->willReturn($forbidden_access);

    $workflow_type = $this->getMock(WorkflowTypeInterface::class);
    $state = $this->getMock(StateInterface::class);

    $workflow_type->expects($this->once())
      ->method('getState')
      ->with('foobar')
      ->willReturn($state);

    $state->expects($this->once())
      ->method('canTransitionTo')
      ->with('bar')
      ->willReturn(FALSE);

    $workflow->expects($this->once())
      ->method('getTypePlugin')
      ->willReturn($workflow_type);

    $data['no-update-access'] = [$moderation_info, $node, FALSE];

    // Same workflow with node update access and no valid transition.
    $workflow = clone $this->workflow;

    $workflow->expects($this->once())
      ->method('id')
      ->willReturn('foo');

    $moderation_info = clone $this->moderationInfo;
    $node = clone $this->node;

    $moderation_info->expects($this->once())
      ->method('getWorkflowForEntity')
      ->with($node)
      ->will($this->returnValue($workflow));

    $entity_type_id = $this->returnValue('node');
    $entity_id = $this->returnValue(1);

    $node->moderation_state = (object) ['value' => 'foobar'];

    $moderation_info->expects($this->once())
      ->method('getLatestRevision')
      ->with($entity_type_id, $entity_id)
      ->will($this->returnValue($node));

    $allowed_access = new AccessResultAllowed();
    $node->expects($this->once())
      ->method('access')
      ->with('update', NULL, TRUE)
      ->willReturn($allowed_access);

    $workflow_type = $this->getMock(WorkflowTypeInterface::class);
    $state = $this->getMock(StateInterface::class);

    $workflow_type->expects($this->once())
      ->method('getState')
      ->with('foobar')
      ->willReturn($state);

    $state->expects($this->once())
      ->method('canTransitionTo')
      ->with('bar')
      ->willReturn(FALSE);

    $workflow->expects($this->once())
      ->method('getTypePlugin')
      ->willReturn($workflow_type);

    $data['invalid-transition'] = [$moderation_info, $node, FALSE];

    // Same workflow with update access, with valid transition and no transition
    // access.
    $workflow = clone $this->workflow;
    $workflow->expects($this->exactly(2))
      ->method('id')
      ->willReturn('foo');

    $moderation_info = clone $this->moderationInfo;
    $node = clone $this->node;

    $moderation_info->expects($this->once())
      ->method('getWorkflowForEntity')
      ->with($node)
      ->will($this->returnValue($workflow));

    $entity_type_id = $this->returnValue('node');
    $entity_id = $this->returnValue(1);

    $node->moderation_state = (object) ['value' => 'foobar'];

    $moderation_info->expects($this->once())
      ->method('getLatestRevision')
      ->with($entity_type_id, $entity_id)
      ->will($this->returnValue($node));

    $account = $this->getMock(AccountInterface::class);

    $account->expects($this->once())
      ->method('hasPermission')
      ->with('use foo transition bar')
      ->willReturn(FALSE);

    $node->expects($this->once())
      ->method('access')
      ->with('update', $account, TRUE)
      ->willReturn($allowed_access);

    $workflow_type = $this->getMock(WorkflowTypeInterface::class);
    $state = $this->getMock(StateInterface::class);

    $workflow_type->expects($this->once())
      ->method('getState')
      ->with('foobar')
      ->willReturn($state);

    $state->expects($this->once())
      ->method('canTransitionTo')
      ->with('bar')
      ->willReturn(TRUE);
    $transition = $this->getMock(TransitionInterface::class);
    $transition->expects($this->once())
      ->method('id')
      ->willReturn('bar');

    $state->expects($this->once())
      ->method('getTransitionTo')
      ->with('bar')
      ->willReturn($transition);

    $workflow->expects($this->once())
      ->method('getTypePlugin')
      ->willReturn($workflow_type);

    $data['no-transition-access'] = [$moderation_info, $node, FALSE, $account];

    // Same workflow with no update access, with valid transition and transition
    // access.
    $workflow = clone $this->workflow;
    $workflow->expects($this->exactly(2))
      ->method('id')
      ->willReturn('foo');

    $moderation_info = clone $this->moderationInfo;
    $node = clone $this->node;

    $moderation_info->expects($this->once())
      ->method('getWorkflowForEntity')
      ->with($node)
      ->will($this->returnValue($workflow));

    $entity_type_id = $this->returnValue('node');
    $entity_id = $this->returnValue(1);

    $node->moderation_state = (object) ['value' => 'foobar'];

    $moderation_info->expects($this->once())
      ->method('getLatestRevision')
      ->with($entity_type_id, $entity_id)
      ->will($this->returnValue($node));

    $account = $this->getMock(AccountInterface::class);

    $account->expects($this->once())
      ->method('hasPermission')
      ->with('use foo transition bar')
      ->willReturn(TRUE);

    $node->expects($this->once())
      ->method('access')
      ->with('update', $account, TRUE)
      ->willReturn($forbidden_access);

    $workflow_type = $this->getMock(WorkflowTypeInterface::class);
    $state = $this->getMock(StateInterface::class);

    $workflow_type->expects($this->once())
      ->method('getState')
      ->with('foobar')
      ->willReturn($state);

    $state->expects($this->once())
      ->method('canTransitionTo')
      ->with('bar')
      ->willReturn(TRUE);
    $transition = $this->getMock(TransitionInterface::class);
    $transition->expects($this->once())
      ->method('id')
      ->willReturn('bar');

    $state->expects($this->once())
      ->method('getTransitionTo')
      ->with('bar')
      ->willReturn($transition);

    $workflow->expects($this->once())
      ->method('getTypePlugin')
      ->willReturn($workflow_type);

    $data['no-update-access-transition-access'] = [
      $moderation_info,
      $node,
      FALSE,
      $account,
    ];

    // Same workflow with update access, with valid transition and transition
    // access.
    $workflow = clone $this->workflow;
    $workflow->expects($this->exactly(2))
      ->method('id')
      ->willReturn('foo');

    $moderation_info = clone $this->moderationInfo;
    $node = clone $this->node;

    $moderation_info->expects($this->once())
      ->method('getWorkflowForEntity')
      ->with($node)
      ->will($this->returnValue($workflow));

    $entity_type_id = $this->returnValue('node');
    $entity_id = $this->returnValue(1);

    $node->moderation_state = (object) ['value' => 'foobar'];

    $moderation_info->expects($this->once())
      ->method('getLatestRevision')
      ->with($entity_type_id, $entity_id)
      ->will($this->returnValue($node));

    $account = $this->getMock(AccountInterface::class);

    $account->expects($this->once())
      ->method('hasPermission')
      ->with('use foo transition bar')
      ->willReturn(TRUE);

    $node->expects($this->once())
      ->method('access')
      ->with('update', $account, TRUE)
      ->willReturn($allowed_access);

    $workflow_type = $this->getMock(WorkflowTypeInterface::class);
    $state = $this->getMock(StateInterface::class);

    $workflow_type->expects($this->once())
      ->method('getState')
      ->with('foobar')
      ->willReturn($state);

    $state->expects($this->once())
      ->method('canTransitionTo')
      ->with('bar')
      ->willReturn(TRUE);
    $transition = $this->getMock(TransitionInterface::class);
    $transition->expects($this->once())
      ->method('id')
      ->willReturn('bar');

    $state->expects($this->once())
      ->method('getTransitionTo')
      ->with('bar')
      ->willReturn($transition);

    $workflow->expects($this->once())
      ->method('getTypePlugin')
      ->willReturn($workflow_type);

    $data['transition-access'] = [$moderation_info, $node, TRUE, $account];

    return $data;
  }

  /**
   * Tests the access method.
   *
   * @param \Drupal\content_moderation\ModerationInformationInterface|\PHPUnit_Framework_MockObject_MockObject $moderation_info
   *   The moderation info service.
   * @param \Drupal\node\NodeInterface|\PHPUnit_Framework_MockObject_MockObject|\StdClass|null $node
   *   The mocked node.
   * @param bool $result
   *   The access result.
   * @param \Drupal\Core\Session\AccountInterface|null $account
   *   (optional) The user for which to check access, or NULL to check access
   *   for the current user. Defaults to NULL.
   *
   * @dataProvider accessModerationStateChangeDataProvider
   */
  public function testAccessModerationStateChange(ModerationInformationInterface $moderation_info, $node, $result, AccountInterface $account = NULL) {
    $config = ['workflow' => 'foo', 'state' => 'bar'];
    $plugin = new ModerationStateChange($config, 'moderation_state_change', ['type' => 'node'], $moderation_info);
    $this->assertEquals($result, $plugin->access($node, $account));
  }

}
