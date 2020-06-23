<?php

namespace Drupal\Tests\node\Functional;

use Drupal\language\Entity\ConfigurableLanguage;
use Drupal\node\Entity\Node;

/**
 * Provides tests for node routes.
 *
 * @group node
 */
class NodeRoutesTest extends NodeTestBase {

  /**
   * {@inheritdoc}
   */
  protected $defaultTheme = 'stark';

  public static $modules = [
    'block',
    'content_translation',
    'language',
    'node_routes_test',
  ];

  /**
   * Tests up-casting for revision routes.
   */
  public function testRevisionRoutes() {
    ConfigurableLanguage::createFromLangcode('it')->save();

    $this->placeBlock('node_routes_test_block');

    $account = $this->drupalCreateUser([
      'view article revisions',
      'revert article revisions',
      'delete article revisions',
      'edit any article content',
      'delete any article content',
      'translate any entity',
    ]);
    $this->drupalLogin($account);

    $node = Node::create([
      'type' => 'article',
      'title' => 'Foo',
      'status' => Node::PUBLISHED,
    ]);
    $node->save();
    $nid = $node->id();
    $initial_rid = $node->getRevisionId();

    $node->setTitle('Bar');
    $node->setNewRevision(TRUE);
    $node->save();
    $current_rid = $node->getRevisionId();

    $paths = [
      "node/$nid/revisions",
      "node/$nid/revisions/$current_rid/view",
      "node/$nid/revisions/$initial_rid/revert",
      "node/$nid/revisions/$initial_rid/revert/it",
      "node/$nid/revisions/$initial_rid/delete",
    ];

    foreach ($paths as $path) {
      $this->drupalGet($path);
      $this->assertSession()->pageTextContainsOnce('article page');
    }
  }

}
