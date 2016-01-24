<?php
// This file declares a managed database record of type "ReportTemplate".
// The record will be automatically inserted, updated, or deleted from the
// database as appropriate. For more details, see "hook_civicrm_managed" at:
// http://wiki.civicrm.org/confluence/display/CRMDOC42/Hook+Reference
return array (
  0 => 
  array (
    'name' => 'CRM_Casereports_Form_Report_MainActivities',
    'entity' => 'ReportTemplate',
    'params' => 
    array (
      'version' => 3,
      'label' => 'MainActivities',
      'description' => 'MainActivities (nl.pum.casereports)',
      'class_name' => 'CRM_Casereports_Form_Report_MainActivities',
      'report_url' => 'nl.pum.casereports/mainactivities',
      'component' => 'CiviCase',
    ),
  ),
);