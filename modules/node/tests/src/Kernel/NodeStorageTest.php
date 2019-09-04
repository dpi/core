<?php

namespace Drupal\Tests\node\Kernel;

use Drupal\KernelTests\KernelTestBase;
use Drupal\node\Entity\Node;
use Drupal\node\Entity\NodeType;
use Drupal\user\Entity\User;

/**
 * Tests node storage.
 *
 * @group node
 * @coversDefaultClass \Drupal\node\NodeStorage
 */
class NodeStorageTest extends KernelTestBase {

  /**
   * {@inheritdoc}
   */
  public static $modules = ['node', 'user', 'system', 'field'];

  /**
   * An unsaved node for testing.
   *
   * @var \Drupal\node\NodeInterface
   */
  protected $testNode;

  /**
   * {@inheritdoc}
   */
  protected function setUp() {
    parent::setUp();
    $this->installEntitySchema('node');
    $this->installEntitySchema('user');
    $this->installSchema('system', 'sequences');
    $this->installSchema('node', ['node_access']);

    $nodeType = NodeType::create([
      'type' => 'test',
      'name' => 'test',
      'revision' => TRUE,
    ]);
    $nodeType->save();
    $this->testNode = Node::create([
      'title' => 'test node',
      'type' => $nodeType->id(),
    ]);
  }

  /**
   * Test getting revisions where a user is set as author.
   *
   * @covers ::userRevisionIds
   */
  public function testUserRevisionIds() {
    $owner = User::create(['name' => 'test user']);
    $owner->save();

    $node = $this->testNode;
    $node->setOwner($owner)->save();

    /** @var \Drupal\node\NodeStorageInterface $nodeStorage */
    $nodeStorage = \Drupal::entityTypeManager()->getStorage('node');
    $revisionIds = $nodeStorage->userRevisionIds($owner);
    $this->assertEquals([$node->getRevisionId()], $revisionIds);
  }

  /**
   * Test getting revisions created by a user.
   *
   * @covers ::userRevisionAuthorRevisionIds
   */
  public function testUserRevisionAuthorRevisionIds() {
    $user1 = User::create(['name' => 'test user 1']);
    $user1->save();

    $node = $this->testNode;
    $node
      ->setOwner($user1)
      ->setRevisionUser($user1)
      ->save();
    $firstRevisionId = $node->getRevisionId();

    // Create another revision owned by another user.
    $user2 = User::create(['name' => 'test user 2']);
    $user2->save();
    $node->setNewRevision();
    $node->setRevisionUser($user2);
    $node->save();
    $secondRevisionId = $node->getRevisionId();

    $this->assertNotEquals($firstRevisionId, $secondRevisionId);

    /** @var \Drupal\node\NodeStorageInterface $nodeStorage */
    $nodeStorage = \Drupal::entityTypeManager()->getStorage('node');

    $revisionIds = $nodeStorage->userRevisionAuthorRevisionIds($user1);
    $this->assertEquals([$firstRevisionId], $revisionIds);
    $revisionIds = $nodeStorage->userRevisionAuthorRevisionIds($user2);
    $this->assertEquals([$secondRevisionId], $revisionIds);
  }

}
