<?php
// $Id$

/**
 * @file
 * Provides rules integration for the signup module.
 */

/**
 * Implementation of hook_rules_event_info().
 * @ingroup rules
 */
function signup_rules_event_info() {
  return array(
    'signup_insert' => array(
      'label' => t('User signed up to a content'),
      'module' => 'Signup',
      'arguments' => signup_rules_events_signup_arguments(FALSE),
    ),
    'signup_update' => array(
      'label' => t('User updated signup of a content'),
      'module' => 'Signup',
      'arguments' => signup_rules_events_signup_arguments(FALSE),
    ),
    'signup_cancel' => array(
      'label' => t('User canceled signup of a content'),
      'module' => 'Signup',
      'arguments' => signup_rules_events_signup_arguments(TRUE),
    ),
  );
}

/**
 * Default arguments to signup rules events.
 */
function signup_rules_events_signup_arguments($have_node = FALSE) {
  return array(
    'signup' => array(
      'type' => 'signup',
      'label' => t('signup object'),
    ),
    'node' => array(
      'type' => 'node',
      'label' => t('node to which the user signed up'),
      'handler' => $have_node ? '' : 'signup_rules_events_argument_signup_node',
    ),
    'account' => array(
      'type' => 'user',
      'label' => t('acting user'),
      'handler' => 'signup_rules_events_argument_signup_user',
    ),
  );
}

/**
 * Load associated node for a signup object.
 */
function signup_rules_events_argument_signup_node($signup) {
  return node_load($signup->nid);
}

/**
 * Load associated user account for a signup object.
 */
function signup_rules_events_argument_signup_user($signup) {
  // If signup user is anonymous user get basic info from signup object.
  if (!$signup->uid) {
    $account = new stdClass();
    $account->uid = 0;
    $account->name = '';
    $account->mail = $signup->anon_mail;
    $account->roles = array(DRUPAL_ANONYMOUS_RID => 'anonymous user');
  }
  else {
    $account = user_load($signup->uid);
  }
  return $account;
}

/**
 * Implementation of hook_rules_data_type_info().
 * @ingroup rules
 */
function signup_rules_data_type_info() {
  return array(
    'signup' => array(
      'label' => t('signup'),
      'class' => 'rules_data_type_signup',
      'savable' => FALSE,
      'identifiable' => TRUE,
    ),
  );
}

/**
 * Implements the signup data type.
 * @ingroup rules
 */
class rules_data_type_signup extends rules_data_type {
  function load($sid) {
    return signup_load_signup($sid);
  }

  function get_identifier() {
    $signup = $this->get();
    return $signup->sid;
  }
}