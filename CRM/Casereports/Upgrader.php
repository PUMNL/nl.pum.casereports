<?php

/**
 * Collection of upgrade steps.
 */
class CRM_Casereports_Upgrader extends CRM_Casereports_Upgrader_Base {

  // By convention, functions that look like "function upgrade_NNNN()" are
  // upgrade tasks. They are executed in order (like Drupal's hook_update_N).

  /**
   * Create table civicrm_pum_case_reports on install
   */
  public function install() {
    $this->executeSqlFile('sql/createCaseReportsTable.sql');
  }
}
