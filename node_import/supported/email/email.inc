<?php
// $Id: email.inc,v 1.1.2.3 2009/04/14 06:32:03 robrechtj Exp $

/**
 * @file
 * Support file for the CCK Email module.
 */

/**
 * Implementation of hook_node_import_fields().
 */
function email_node_import_fields($type) {
  $fields = array();

  foreach (node_import_cck_fields($type, 'email') as $fieldname => $fieldinfo) {
    foreach ($fieldinfo['columns'] as $colname => $colinfo) {
      $cck_fieldname = node_import_cck_name($fieldname, $colname);

      $fields[$cck_fieldname] = node_import_cck_field_defaults($fieldinfo);

      switch ($colname) {
        case 'email':
          $fields[$cck_fieldname]['title'] = $fieldinfo['widget']['label'];
          $fields[$cck_fieldname]['map_required'] = $fieldinfo['required'];
          break;

        default:
          $fields[$cck_fieldname]['title'] = t('Unsupported: ') . $fieldinfo['widget']['label'] .' - '. $colname;
          break;
      }
    }
  }

  return $fields;
}

