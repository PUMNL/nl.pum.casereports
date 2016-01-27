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
    LEFT JOIN civicrm_value_main_activity_info ma ON cc.id = ma.entity_id LEFT JOIN civicrm_address adr ON cont.id = adr.contact_id AND is_primary = 1
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
   * Upgrade 1001 change column ma_expert_approval for n/a value
   *
   * @return bool
   */
  public function upgrade_1001() {
    $this->ctx->log->info('Applying update 1001 alter table ma_expert_approval in civicrm_pum_case_reports');
    $config = CRM_Casereports_Config::singleton();
    if (CRM_Core_DAO::checkTableExists('civicrm_pum_case_reports')) {
      CRM_Core_DAO::executeQuery('ALTER TABLE civicrm_pum_case_reports CHANGE COLUMN ma_expert_approval
        ma_expert_approval VARCHAR(15) NULL DEFAULT NULL');
      // set values 'n/a'
      $naQuery = 'UPDATE civicrm_pum_case_reports SET ma_expert_approval = %1 WHERE case_id NOT IN(
        SELECT DISTINCT(case_id) FROM civicrm_case_activity cact JOIN civicrm_activity act ON cact.activity_id = act.id
        WHERE activity_type_id IN(%2, %3) AND is_current_revision = %4)';
      $naParams = array(
        1 => array('n/a', 'String'),
        2 => array($config->getMaAcceptActivityTypeId(), 'Integer'),
        3 => array($config->getMaRejectActivityTypeId(), 'Integer'),
        4 => array(1, 'Integer')
      );
      CRM_Core_DAO::executeQuery($naQuery, $naParams);
      // now set all remaining 1's to yes and 0's to no
      $yesQuery = 'UPDATE civicrm_pum_case_reports SET ma_expert_approval = %1 WHERE ma_expert_approval = %2';
      $yesParams = array(
        1 => array('Yes', 'String'),
        2 => array('1', 'String')
      );
      CRM_Core_DAO::executeQuery($yesQuery, $yesParams);
      $noQuery = 'UPDATE civicrm_pum_case_reports SET ma_expert_approval = %1 WHERE ma_expert_approval = %2';
      $noParams = array(
        1 => array('No', 'String'),
        2 => array('0', 'String')
      );
      CRM_Core_DAO::executeQuery($noQuery, $noParams);
    }
    return true;
  }
  /**
   * Upgrade 1002 set default n/a
   *
   * @return bool
   */
  public function upgrade_1002() {
    $this->ctx->log->info('Applying update 1002 set default for ma_expert_approval in civicrm_pum_case_reports');
    if (CRM_Core_DAO::checkTableExists('civicrm_pum_case_reports')) {
      CRM_Core_DAO::executeQuery('ALTER TABLE civicrm_pum_case_reports CHANGE COLUMN ma_expert_approval
        ma_expert_approval VARCHAR(15) NULL DEFAULT "n/a"');
      // set values 'n/a'
      $naQuery = 'UPDATE civicrm_pum_case_reports SET ma_expert_approval = %1 WHERE ma_expert_approval IS NULL';
      $naParams = array(1 => array('n/a', 'String'));
      CRM_Core_DAO::executeQuery($naQuery, $naParams);
    }
    return true;
  }
}
