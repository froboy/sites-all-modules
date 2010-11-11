<?php

function signup_guests_views_data() {
  $data['signup_log']['count_towards_limit'] = array(
    'title' => t('User: people signed up'),
    'help' => t('Total number of people signed up by the user (total includes user)'),
    'field' => array(
      'handler' => 'views_handler_field',
    ),
  );

  $data['signup_log']['guests'] = array(
    'title' => t('User: guests signed up'),
    'real field' => 'count_towards_limit',
    'help' => t('Total number of guests signed up by the user (user not included in total)'),
    'field' => array(
      'handler' => 'signup_guests_handler_field_signup_guests',
    ),
  );
  return $data;
}

function signup_guests_views_handlers() {
  return array(
    'info' => array(
      'path' => drupal_get_path('module', 'signup_guests') .'/views/handlers',
    ),
    'handlers' => array(
      'signup_guests_handler_field_signup_guests' => array(
        'parent' => 'views_handler_field',
      ),
    ),
  );
}