<?php

class signup_guests_handler_field_signup_guests extends views_handler_field {
  function render($values) {
    return ($values->{$this->field_alias} - 1);
    
    
    if (empty($values->{$this->aliases['signup_uid']})) {
      return check_plain($values->{$this->aliases['signup_anon_mail']});
    }
    else {
      return check_plain($values->{$this->aliases['users_mail']});
    }
  }
}