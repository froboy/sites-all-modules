// $Id: select_or_other.js,v 1.1.2.1.2.6 2010/01/29 01:12:20 danielb Exp $

function select_or_other_check_and_show(ele, page_init) {
  var speed;
  if (page_init) {
    speed = 0;
  } else {
    speed = "normal";
    ele = $(ele).parents("div.select-or-other")[0];
  }
  if ($(ele).find("select.select-or-other-select option:selected[value=select_or_other], input.select-or-other-select:checked[value=select_or_other]").length) {
    $(ele).find("input.select-or-other-other").show(speed);
  }
  else {
    $(ele).find("input.select-or-other-other").hide(speed);
  }
}

/**
 * The Drupal behaviors for the Select (or other) field.
 */
Drupal.behaviors.select_or_other = function(context) {
  $("div.select-or-other:not('.select-or-other-processed')", context)
    .addClass('select-or-other-processed').each(function () {
    select_or_other_check_and_show(this, true);
  });
  $("input.select-or-other-select:not('.select-or-other-processed')", context)
    .addClass('select-or-other-processed').click(function () {
    select_or_other_check_and_show(this, false);
  });
  $("select.select-or-other-select:not('.select-or-other-processed')", context)
    .addClass('select-or-other-processed').change(function () {
    select_or_other_check_and_show(this, false);
  });
};

