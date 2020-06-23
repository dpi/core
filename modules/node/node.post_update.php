<?php

/**
 * @file
 * Post update functions for Node.
 */

/**
 * Implements hook_removed_post_updates().
 */
function node_removed_post_updates() {
  return [
    'node_post_update_configure_status_field_widget' => '9.0.0',
    'node_post_update_node_revision_views_data' => '9.0.0',
  ];
}

/**
 * Update field names for multi-value base fields.
 */
function node_post_update_rebuild_node_revision_routes() {
}
