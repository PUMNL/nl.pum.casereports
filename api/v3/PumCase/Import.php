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
function civicrm_api3_pum_case_import($params)
{
  $import = new CRM_Casereports_Import();
  // this part was required for import of main activities and briefing, no longer required so commented out
  //$daoCaseIds = CRM_Core_DAO::executeQuery('SELECT DISTINCT(case_id) FROM pum_my_main_activities');
  //while ($daoCaseIds->fetch()) {
  //$import->importAccepts($daoCaseIds->case_id);
  //$import->importBriefing($daoCaseIds->case_id);
 //}
  
  // retrieve all active projectintake cases
  $projectIntakeCaseTypeId = civicrm_api3('OptionValue', 'Getvalue', array(
    'option_group_id' => 'case_type',
    'name' => 'Projectintake',
    'return' => 'value'
  ));
  $caseQuery = "SELECT id FROM civicrm_case WHERE case_type_id LIKE %1 AND is_deleted = %2";
  $caseParams = array(
    1 => array('%'.$projectIntakeCaseTypeId.'%', 'String'),
    2 => array(0, 'Integer'));
  $caseDao = CRM_Core_DAO::executeQuery($caseQuery, $caseParams);
  while ($caseDao->fetch()) {
    // this was required for issue 3287
    //$import->importProjectIntake($caseDao->id);
    // this is required for issue 3498
    $import->setCaseRelations($caseDao->id);
  }
  return civicrm_api3_create_success(array('Existing pum case data imported into civicrm_pum_case_reports'), $params, 'PumCase', 'Import');
}

