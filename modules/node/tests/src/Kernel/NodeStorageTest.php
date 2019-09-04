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
   * Test getting revisions where a user is set as author.
   *
   * @covers ::userRevisionIds
   */
  public function testUserRevisionIds() {
    $nodeType = NodeType::create([
      'type' => strtolower($this->randomMachineName()),
      'name' => $this->randomMachineName(),
      'revision' => TRUE,
    ]);
    $nodeType->save();
    $node = Node::create([
      'title' => $this->randomMachineName(),
      'type' => $nodeType->id(),
    ]);
    $owner = User::create(['name' => $this->randomMachineName()]);
    $owner->save();
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
    $nodeType = NodeType::create([
      'type' => strtolower($this->randomMachineName()),
      'name' => $this->randomMachineName(),
      'revision' => TRUE,
    ]);
    $nodeType->save();
    $node = Node::create([
      'title' => $this->randomMachineName(),
      'type' => $nodeType->id(),
    ]);
    $user1 = User::create(['name' => $this->randomMachineName()]);
    $user1->save();
    $node
      ->setOwner($user1)
      ->setRevisionUser($user1)
      ->save();
    $firstRevisionId = $node->getRevisionId();

    // Create another revision owned by another user.
    $user2 = User::create(['name' => $this->randomMachineName()]);
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
