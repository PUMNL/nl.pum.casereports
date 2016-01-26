<?php
/**
 * PumCase.Import API (Job to import existing data into civicrm_pum_case_reports
 *
 * @param array $params
 * @return array API result descriptor
 * @see civicrm_api3_create_success
 * @see civicrm_api3_create_error
 * @throws API_Exception
 */
function civicrm_api3_pum_case_import($params) {
  $import = new CRM_Casereports_Import();
  $daoCaseIds = CRM_Core_DAO::executeQuery('SELECT DISTINCT(case_id) FROM pum_my_main_activities');
  while ($daoCaseIds->fetch()) {
    $import->importAccepts($daoCaseIds->case_id);
    $import->importBriefing($daoCaseIds->case_id);
  }
  return civicrm_api3_create_success(array('Existing pum case data imported into civicrm_pum_case_reports'), $params, 'PumCase', 'Import');
}

