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
    $this->createViewMyMainActivities();
    $this->importExistingForMainActivities();
  }

  /**
   * Method to create a view used for report Main Activities
   *
   * @access protected
   */
  protected function createViewMyMainActivities() {
    $expertRelationshipTypeId = civicrm_api3('RelationshipType', 'Getvalue', array('name_a_b' => 'Expert', 'return' => 'id'));
    $repRelationshipTypeId = civicrm_api3('RelationshipType', 'Getvalue', array('name_a_b' => 'Representative is', 'return' => 'id'));
    $ccRelationshipTypeId = civicrm_api3('RelationshipType', 'Getvalue', array('name_a_b' => 'Country Coordinator is', 'return' => 'id'));
    $projectOfficerRelationshipTypeId = civicrm_api3('RelationshipType', 'Getvalue', array('name_a_b' => 'Project Officer for', 'return' => 'id'));
    $projectManagerRelationshipTypeId = civicrm_api3('RelationshipType', 'Getvalue', array('name_a_b' => 'Projectmanager', 'return' => 'id'));
    $scRelationshipTypeId = civicrm_api3('RelationshipType', 'Getvalue', array('name_a_b' => 'Sector Coordinator', 'return' => 'id'));
    $counsellorRelationshipTypeId = civicrm_api3('RelationshipType', 'Getvalue', array('name_a_b' => 'Counsellor', 'return' => 'id'));

    // create view for main activities report
    $query = "CREATE OR REPLACE VIEW pum_my_main_activities AS
    SELECT cc.id AS case_id, cc.case_type_id, cc.subject,cc.status_id AS case_status_id, cont.display_name AS customer_name, cont.id AS customer_id,
    ma.start_date, ma.end_date, exp.display_name AS expert, exprel.contact_id_b AS expert_id, rep.display_name AS representative,
    reprel.contact_id_b AS representative_id, pum.ma_expert_approval, pum.pq_approved_cc, pum.pq_approved_sc, pum.briefing_status,
    pum.briefing_date, adr.street_address, adr.city, adr.postal_code, cntry.name AS country_name, ccrel.contact_id_b AS country_coordinator_id,
    porel.contact_id_b AS project_officer_id, pmrel.contact_id_b AS project_manager_id, screl.contact_id_b AS sector_coordinator_id,
    corel.contact_id_b AS counsellor_id, ca.do_you_think_the_expert_matches__78 AS cust_approves_expert
    FROM civicrm_case cc JOIN civicrm_case_contact ccc ON cc.id = ccc.case_id JOIN civicrm_contact cont ON ccc.contact_id = cont.id
    LEFT JOIN civicrm_value_main_activity_info ma ON cc.id = ma.entity_id LEFT JOIN civicrm_address adr ON cont.id = adr.contact_id
    LEFT JOIN civicrm_value_customer_dis_agreement_of_proposed_expert_17 ca ON cc.id = ca.entity_id
    LEFT JOIN civicrm_country cntry ON adr.country_id = cntry.id LEFT JOIN civicrm_pum_case_reports pum ON cc.id = pum.case_id
    LEFT JOIN civicrm_relationship exprel ON cc.id = exprel.case_id AND exprel.relationship_type_id = {$expertRelationshipTypeId}
    LEFT JOIN civicrm_contact exp ON exprel.contact_id_b = exp.id LEFT JOIN civicrm_relationship reprel ON cc.id = reprel.case_id
    AND reprel.relationship_type_id = {$repRelationshipTypeId} LEFT JOIN civicrm_contact rep ON reprel.contact_id_b = rep.id
    LEFT JOIN civicrm_relationship ccrel ON cc.id = ccrel.case_id AND ccrel.relationship_type_id = {$ccRelationshipTypeId}
    LEFT JOIN civicrm_relationship porel ON cc.id = porel.case_id AND porel.relationship_type_id = {$projectOfficerRelationshipTypeId}
    LEFT JOIN civicrm_relationship pmrel ON cc.id = pmrel.case_id AND pmrel.relationship_type_id = {$projectManagerRelationshipTypeId}
    LEFT JOIN civicrm_relationship screl ON cc.id = screl.case_id AND screl.relationship_type_id = {$scRelationshipTypeId}
    LEFT JOIN civicrm_relationship corel ON cc.id = corel.case_id AND corel.relationship_type_id = {$counsellorRelationshipTypeId}
    WHERE cc.is_deleted = 0";
    CRM_Core_DAO::executeQuery($query);
  }

  /**
   * Method to import current data into civicrm_pum_case_reports table
   */
  protected function importExistingForMainActivities() {
    $import = new CRM_Casereports_Import();
    $daoCaseIds = CRM_Core_DAO::executeQuery('SELECT DISTINCT(case_id) FROM pum_my_main_activities');
    while ($daoCaseIds->fetch()) {
      $import->importAccepts($daoCaseIds->case_id);
      $import->importRejects($daoCaseIds->case_id);
      $import->importBriefing($daoCaseIds->case_id);
    }
  }
}
