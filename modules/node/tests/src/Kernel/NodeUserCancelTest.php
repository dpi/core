<?php

namespace Drupal\Tests\node\Kernel;

use Drupal\KernelTests\KernelTestBase;
use Drupal\node\Entity\Node;
use Drupal\node\Entity\NodeType;
use Drupal\user\Entity\User;

/**
 * Tests node behavior when users are cancelled.
 *
 * @group node
 */
class NodeUserCancelTest extends KernelTestBase {

  /**
   * {@inheritdoc}
   */
  public static $modules = ['node', 'user', 'system', 'field'];

  /**
   * {@inheritdoc}
   */
  protected function setUp() {
    parent::setUp();
    $this->installEntitySchema('node');
    $this->installEntitySchema('user');
    $this->installSchema('system', 'sequences');
    $this->installSchema('node', ['node_access']);
  }

  /**
   * Confirm that user's content has been attributed to anonymous user.
   */
  public function testCancelReassignToAnonymous() {
    /** @var \Drupal\node\NodeStorageInterface $nodeStorage */
    $nodeStorage = \Drupal::entityTypeManager()->getStorage('node');

    $nodeType = NodeType::create([
      'type' => strtolower($this->randomMachineName()),
      'name' => $this->randomMachineName(),
      'revision' => TRUE,
    ]);
    $nodeType->save();

    $user1 = User::create(['name' => $this->randomMachineName()]);
    $user1->save();
    $user2 = User::create(['name' => $this->randomMachineName()]);
    $user2->save();

    $node = Node::create([
      'title' => $this->randomMachineName(),
      'type' => $nodeType->id(),
    ]);
    $node
      // Set to published to test 'user_cancel_reassign' does not unpublish.
      ->setPublished()
      ->setOwner($user1)
      ->setRevisionUser($user1)
      ->save();
    $firstRevisionId = $node->getRevisionId();
    $node->setNewRevision();
    $node->setRevisionUser($user2)->save();
    $secondRevisionId = $node->getRevisionId();

    $this->assertNotEquals($firstRevisionId, $secondRevisionId);

    \user_cancel([], $user1->id(), 'user_cancel_reassign');

    // Test default revision.
    // Reload node.
    /** @var \Drupal\node\NodeInterface $node */
    $node = $nodeStorage->load($node->id());
    // Confirm that user's content has been attributed to anonymous user.
    $this->assertEquals(0, $node->getOwnerId());
    $this->assertEquals($user2->id(), $node->getRevisionUserId());
    // Ensure content is not unpublished, which 'user_cancel_block_unpublish'
    // method handles.
    $this->assertTrue($node->isPublished());

    /** @var \Drupal\node\NodeInterface $firstRevision */
    $firstRevision = $nodeStorage->loadRevision($firstRevisionId);
    $this->assertEquals(0, $firstRevision->getOwnerId());
    $this->assertEquals(0, $firstRevision->getRevisionUserId());
    $this->assertTrue($firstRevision->isPublished());

    /** @var \Drupal\node\NodeInterface $secondRevision */
    $secondRevision = $nodeStorage->loadRevision($secondRevisionId);
    $this->assertEquals(0, $secondRevision->getOwnerId());
    $this->assertEquals($user2->id(), $secondRevision->getRevisionUserId());
    $this->assertTrue($secondRevision->isPublished());
  }

}
