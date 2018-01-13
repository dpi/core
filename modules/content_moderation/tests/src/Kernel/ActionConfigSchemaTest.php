<?php

namespace Drupal\Tests\content_moderation\Kernel;

use Drupal\node\Entity\NodeType;
use Drupal\system\Entity\Action;
use Drupal\Tests\SchemaCheckTestTrait;
use Drupal\KernelTests\KernelTestBase;
use Drupal\workflows\Entity\Workflow;

/**
 * Ensures the change state action has valid config schema.
 *
 * @group content_moderation
 */
class ActionConfigSchemaTest extends KernelTestBase {

  use SchemaCheckTestTrait;

  /**
   * {@inheritdoc}
   */
  public static $modules = [
    'content_moderation',
    'node',
    'user',
    'system',
    'workflows',
  ];

  /**
   * {@inheritdoc}
   */
  protected function setUp() {
    parent::setUp();
    $this->installEntitySchema('node');
    $this->installSchema('node', 'node_access');
    $this->installEntitySchema('content_moderation_state');
    $this->installConfig('content_moderation');

    $node_type = NodeType::create([
      'type' => 'page',
      'label' => 'Page',
    ]);
    $node_type->save();
    $workflow = Workflow::load('editorial');
    $workflow->getTypePlugin()->addEntityTypeAndBundle('node', 'page');
    $workflow->save();

    $action = Action::create([
      'id' => 'change_moderation_state_to_draft',
      'type' => 'node',
      'label' => t('Change moderation state to Draft'),
      'configuration' => [
        'workflow' => 'editorial',
        'state' => 'draft',
      ],
      'plugin' => 'moderation_state_change:node',
    ]);
    $action->save();

    $action = Action::create([
      'id' => 'change_moderation_state_to_published',
      'type' => 'node',
      'label' => t('Change moderation state to Published'),
      'configuration' => [
        'workflow' => 'editorial',
        'state' => 'published',
      ],
      'plugin' => 'moderation_state_change:node',
    ]);
    $action->save();
  }

  /**
   * Tests whether the change_moderation_state action config schema is valid.
   */
  public function testValidActionConfigSchema() {

    // Test change_moderation_state_to_draft configuration.
    $config = $this->config('system.action.change_moderation_state_to_draft');
    $this->assertEquals($config->get('id'), 'change_moderation_state_to_draft');
    $this->assertConfigSchema(\Drupal::service('config.typed'), $config->getName(), $config->get());

    // Test change_moderation_state_to_published configuration.
    $config = $this->config('system.action.change_moderation_state_to_published');
    $this->assertEquals($config->get('id'), 'change_moderation_state_to_published');
    $this->assertConfigSchema(\Drupal::service('config.typed'), $config->getName(), $config->get());
  }

}
