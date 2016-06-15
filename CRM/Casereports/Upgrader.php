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
   * Method to create a view used for report My Expert Applications
   *
   * @throws Exception when error from API
   * @access protected
   */
  protected function createViewMyExpertApplication() {
    try {
      $caseStatusOptionGroupId = civicrm_api3('OptionGroup', 'Getvalue', array('name' => 'case_status', 'return' => 'id'));
    } catch (CiviCRM_API3_Exception $ex) {
      throw new Exception(ts('Could not find an option group for case_status in '.__METHOD__
          .', contact your system administrator. Error from API OptionGroup Getvalue: ').$ex->getMessage());
    }
    try {
      $caseTypeOptionGroupId = civicrm_api3('OptionGroup', 'Getvalue', array('name' => 'case_type', 'return' => 'id'));
      try {
        $expertCaseTypeId = civicrm_api3('OptionValue', 'Getvalue',
          array('option_group_id' => $caseTypeOptionGroupId, 'name' => 'Expertapplication', 'return' => 'value'));
        try {
          $scRelationshipTypeId = civicrm_api3('RelationshipType', 'Getvalue', array('return' => 'id', 'name_a_b' => 'Sector Coordinator'));
          $rtRelationshipTypeId = civicrm_api3('RelationshipType', 'Getvalue', array('return' => 'id', 'name_a_b' => 'Recruitment Team Member'));
          $query = "CREATE OR REPLACE VIEW pum_expert_applications AS
            SELECT c1.id AS case_id, c1.status_id, stat.label AS status, stat.weight, exp.display_name AS expert_name,  
              exp.id AS expert_id, seg.label AS sector, seg.id AS sector_id, sc.id AS sector_coordinator_id, 
              sc.display_name AS sector_coordinator_name, screl.contact_id_b AS case_manager_id, rtrel.contact_id_b AS recruitment_team_id
            FROM civicrm_case c1
            JOIN civicrm_case_contact c2 ON c1.id = c2.case_id      
            LEFT JOIN civicrm_contact exp ON c2.contact_id = exp.id
            LEFT JOIN civicrm_contact_segment expcs ON exp.id = expcs.contact_id AND expcs.role_value = 'Expert' 
              AND expcs.is_main = 1 AND expcs.is_active = 1
            LEFT JOIN civicrm_segment seg ON expcs.segment_id = seg.id AND seg.parent_id IS NULL
            LEFT JOIN civicrm_contact_segment sccs ON seg.id = sccs.segment_id AND sccs.role_value = 'Sector Coordinator' 
              AND sccs.is_active = 1
            LEFT JOIN civicrm_contact sc ON sccs.contact_id = sc.id
            LEFT JOIN civicrm_option_value stat ON c1.status_id = stat.value AND stat.option_group_id = {$caseStatusOptionGroupId}
            LEFT JOIN civicrm_relationship screl ON c1.id = screl.case_id AND screl.relationship_type_id = {$scRelationshipTypeId} 
              AND screl.is_active = 1
            LEFT JOIN civicrm_relationship rtrel ON c1.id = rtrel.case_id AND rtrel.relationship_type_id = {$rtRelationshipTypeId} 
              AND rtrel.is_active = 1
            WHERE c1.case_type_id LIKE '%{$expertCaseTypeId}%' AND c1.is_deleted = 0";
          CRM_Core_DAO::executeQuery($query);
        } catch (CiviCRM_API3_Exception $ex) {}
      } catch (CiviCRM_API3_Exception $ex) {}
    } catch (CiviCRM_API3_Exception $ex) {
      throw new Exception(ts('Could not find an option group for case_type in '.__METHOD__
          .', contact your system administrator. Error from API OptionGroup Getvalue: ').$ex->getMessage());
    }
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
    $bcRelationshipTypeId = civicrm_api3('RelationshipType', 'Getvalue', array('name_a_b' => 'Business Coordinator', 'return' => 'id'));
    $gcRelationshipTypeId = civicrm_api3('RelationshipType', 'Getvalue', array('name_a_b' => 'Grant Coordinator', 'return' => 'id'));
    $anaRelationshipTypeId = civicrm_api3('RelationshipType', 'Getvalue', array('name_a_b' => 'Anamon', 'return' => 'id'));

    // create view for main activities report
    $query = "CREATE OR REPLACE VIEW pum_my_main_activities AS
    SELECT cc.id AS case_id, cc.case_type_id, cc.subject,cc.status_id AS case_status_id, cont.display_name AS customer_name, cont.id AS customer_id,
    ma.start_date, ma.end_date, exp.display_name AS expert, exprel.contact_id_b AS expert_id, rep.display_name AS representative,
    reprel.contact_id_b AS representative_id, pum.ma_expert_approval, pum.pq_approved_cc, pum.pq_approved_sc, pum.briefing_status,
    pum.briefing_date, adr.street_address, adr.city, adr.postal_code, cntry.name AS country_name, ccrel.contact_id_b AS country_coordinator_id,
    porel.contact_id_b AS project_officer_id, pmrel.contact_id_b AS project_manager_id, screl.contact_id_b AS sector_coordinator_id,
    corel.contact_id_b AS counsellor_id, ca.do_you_think_the_expert_matches__78 AS cust_approves_expert, bcrel.contact_id_b AS business_coordinator_id,
    gcrel.contact_id_b AS grant_coordinator_id, anarel.contact_id_b AS anamon_id
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
    LEFT JOIN civicrm_relationship bcrel ON cc.id = bcrel.case_id AND bcrel.relationship_type_id = {$bcRelationshipTypeId}
    LEFT JOIN civicrm_relationship gcrel ON cc.id = gcrel.case_id AND gcrel.relationship_type_id = {$gcRelationshipTypeId}
    LEFT JOIN civicrm_relationship anarel ON cc.id = anarel.case_id AND anarel.relationship_type_id = {$anaRelationshipTypeId}
    WHERE cc.is_deleted = 0";
    CRM_Core_DAO::executeQuery($query);
  }

  /**
   * Method to create view for pum projects
   */
  protected function createPumProjectsView() {
    $query = "CREATE OR REPLACE VIEW pum_projects_view AS
    SELECT proj.id AS project_id, proj.title AS project_name, proj.start_date, proj.end_date, proj.projectmanager_id,  
      prjmngr.display_name as projectmanager_name, proj.programme_id, proj.country_id, cntry.display_name AS country_name, 
      proj.customer_id, cst.display_name AS customer_name, prog.title AS programme_name, prog.manager_id AS programme_manager_id, 
      prgmngr.display_name AS programme_manager_name, proj.anamon_id AS anamon_id, 
      proj.sector_coordinator_id AS sector_coordinator_id, proj.country_coordinator_id AS country_coordinator_id,
      proj.project_officer_id AS project_officer_id
      FROM civicrm_project proj
      LEFT JOIN civicrm_programme prog ON proj.programme_id = prog.id
      LEFT JOIN civicrm_contact prjmngr ON proj.projectmanager_id = prjmngr.id
      LEFT JOIN civicrm_contact cntry ON proj.country_id = cntry.id
      LEFT JOIN civicrm_contact cst ON proj.customer_id = cst.id
      LEFT JOIN civicrm_contact prgmngr ON prog.manager_id = prgmngr.id
      WHERE proj.is_active = 1";
    CRM_Core_DAO::executeQuery($query);
  }

  /**
   * Method to create view for pum project intake
   */
  protected function createPumProjectIntakeView() {
    try {
      $caseStatusOptionGroupId = civicrm_api3('OptionGroup', 'Getvalue',
        array('name' => 'case_status', 'return' => 'id'));
    } catch (CiviCRM_API3_Exception $ex) {
      throw new Exception("Could not find a single option group with name case_status, contact your system administrator. 
      Error from API OptionGroup Getvalue: ".$ex->getMessage());
    }
    try {
      $repRelationshipTypeId = civicrm_api3('RelationshipType', 'Getvalue',
        array('name_a_b' => 'Representative is', 'return' => 'id'));
    } catch (CiviCRM_API3_Exception $ex) {
      throw new Exception("Could not find a single relationship type with name_a_b Representative is, contact your system administrator. 
      Error from API RelationshipType Getvalue: ".$ex->getMessage());
    }
    try {
      $projectIntakeCaseTypeId = civicrm_api3('OptionValue', 'Getvalue',
        array('option_group_id' => 'case_type', 'name' => 'Projectintake','return' => 'value'));
    } catch (CiviCRM_API3_Exception $ex) {
      throw new Exception("Could not find a single case type with name Projectintake, contact your system administrator. 
      Error from API OptionValue Getvalue: ".$ex->getMessage());
    }
    try {
      $openCaseActivityTypeId = civicrm_api3('OptionValue', 'Getvalue',
        array('option_group_id' => 'activity_type', 'name' => 'Open Case','return' => 'value'));
    } catch (CiviCRM_API3_Exception $ex) {
      throw new Exception("Could not find a single activity type with name Open Case, contact your system administrator. 
      Error from API OptionValue Getvalue: ".$ex->getMessage());
    }
    $query = "CREATE OR REPLACE VIEW pum_project_intake_view AS
      SELECT DISTINCT(cc.id) AS case_id, cc.subject AS case_subject, cc.status_id AS case_status_id, cus.id AS customer_id,
       oc.activity_date_time AS date_submission, cus.display_name AS customer_name, cuscntry.name AS customer_country_name, 
       ovsts.label AS case_status, reprel.contact_id_b AS representative_id, rep.display_name AS representative_name, 
       pum.assess_rep_date, pum.assess_rep_customer, pum.assess_cc_date, pum.assess_cc_customer, pum.assess_sc_date, 
       pum.assess_sc_customer, pum.assess_anamon_date, pum.assess_anamon_customer, proj.anamon_id, proj.country_coordinator_id, 
       proj.project_officer_id, proj.sector_coordinator_id, proj.projectmanager_id, prog.manager_id AS programme_manager_id
      FROM civicrm_case cc 
      JOIN civicrm_case_activity casact ON cc.id = casact.case_id
      JOIN civicrm_activity oc ON casact.activity_id = oc.id AND oc.is_current_revision = %1 AND oc.is_test = %5 AND oc.activity_type_id = %6
      LEFT JOIN civicrm_case_project cp ON cc.id = cp.case_id
  	  LEFT JOIN civicrm_project proj ON cp.project_id = proj.id
      LEFT JOIN civicrm_programme prog ON proj.programme_id = prog.id      
      LEFT JOIN civicrm_case_contact cascus ON cc.id = cascus.case_id
      LEFT JOIN civicrm_contact cus ON cascus.contact_id = cus.id
      LEFT JOIN civicrm_address cusadr ON cus.id = cusadr.contact_id AND is_primary = %1
      LEFT JOIN civicrm_country cuscntry ON cusadr.country_id = cuscntry.id
      LEFT JOIN civicrm_option_value ovsts ON cc.status_id = ovsts.value AND ovsts.option_group_id = %2
      LEFT JOIN civicrm_relationship reprel ON cc.id = reprel.case_id AND reprel.relationship_type_id = 
        %3 AND reprel.is_active = %1
      LEFT JOIN civicrm_contact rep ON reprel.contact_id_b = rep.id
      LEFT JOIN civicrm_pum_case_reports pum ON cc.id = pum.case_id
      WHERE cc.case_type_id LIKE %4 AND cc.is_deleted = %5";
    $params = array(
      1 => array(1, 'Integer'),
      2 => array($caseStatusOptionGroupId, 'Integer'),
      3 => array($repRelationshipTypeId, 'Integer'),
      4 => array('%'.$projectIntakeCaseTypeId.'%', 'String'),
      5 => array(0, 'Integer'),
      6 => array($openCaseActivityTypeId, 'Integer')
    );
    CRM_Core_DAO::executeQuery($query, $params);
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

  /**
   * Upgrade 1003 - create view for report Expert Application before contact segment
   * 
   * @throws Exception when error in API call
   */
  
  public function upgrade_1003() {
    $this->ctx->log->info('Applying update 1003 add view for report Expert Applications');
    try {
      $caseStatusOptionGroupId = civicrm_api3('OptionGroup', 'Getvalue', array('name' => 'case_status', 'return' => 'id'));
    } catch (CiviCRM_API3_Exception $ex) {
      throw new Exception(ts('Could not find an option group for case_status in '.__METHOD__
          .', contact your system administrator. Error from API OptionGroup Getvalue: ').$ex->getMessage());
    }
    try {
      $caseTypeOptionGroupId = civicrm_api3('OptionGroup', 'Getvalue', array('name' => 'case_type', 'return' => 'id'));
      try {
        $expertCaseTypeId = civicrm_api3('OptionValue', 'Getvalue', 
          array('option_group_id' => $caseTypeOptionGroupId, 'name' => 'Expertapplication', 'return' => 'value'));
        try {
          $scRelationshipTypeId = civicrm_api3('RelationshipType', 'Getvalue', array('return' => 'id', 'name_a_b' => 'Sector Coordinator'));
          $rtRelationshipTypeId = civicrm_api3('RelationshipType', 'Getvalue', array('return' => 'id', 'name_a_b' => 'Recruitment Team Member'));
          $query = "CREATE OR REPLACE VIEW pum_expert_applications AS
            SELECT c1.id AS case_id, c1.status_id, stat.label AS status, stat.weight, exp.display_name AS expert_name,  
              exp.id AS expert_id, screl.contact_id_b AS sector_coordinator_id, sc.display_name AS sector_coordinator_name, 
              screl.contact_id_b AS case_manager_id, rtrel.contact_id_b AS recruitment_team_id
            FROM civicrm_case c1
            JOIN civicrm_case_contact c2 ON c1.id = c2.case_id      
            LEFT JOIN civicrm_contact exp ON c2.contact_id = exp.id
            LEFT JOIN civicrm_option_value stat ON c1.status_id = stat.value AND stat.option_group_id = {$caseStatusOptionGroupId}
            LEFT JOIN civicrm_relationship screl ON c1.id = screl.case_id AND screl.relationship_type_id = {$scRelationshipTypeId} 
              AND screl.is_active = 1
            LEFT JOIN civicrm_relationship rtrel ON c1.id = rtrel.case_id AND rtrel.relationship_type_id = {$rtRelationshipTypeId} 
              AND rtrel.is_active = 1
            LEFT JOIN civicrm_contact sc ON screl.contact_id_b = sc.id
            WHERE c1.case_type_id LIKE '%{$expertCaseTypeId}%' AND c1.is_deleted = 0";
          CRM_Core_DAO::executeQuery($query);
        } catch (CiviCRM_API3_Exception $ex) {}
      } catch (CiviCRM_API3_Exception $ex) {}
    } catch (CiviCRM_API3_Exception $ex) {
      throw new Exception(ts('Could not find an option group for case_type in '.__METHOD__
          .', contact your system administrator. Error from API OptionGroup Getvalue: ').$ex->getMessage());
    }
    return true;
  }

  /**
   * Upgrade 1004 - create view for Opportunity report
   * @throws Exception when error in API call
   */

  public function upgrade_1004() {
    $this->ctx->log->info('Applying update 1004 add view for report Opportunities');
    try {
      $caseStatusOptionGroupId = civicrm_api3('OptionGroup', 'Getvalue', array('name' => 'case_status', 'return' => 'id'));
    } catch (CiviCRM_API3_Exception $ex) {
      throw new Exception(ts('Could not find an option group for case_status in '.__METHOD__
          .', contact your system administrator. Error from API OptionGroup Getvalue: ').$ex->getMessage());
    }
      $caseTypeOptionGroupId = civicrm_api3('OptionGroup', 'Getvalue', array('name' => 'case_type', 'return' => 'id'));
        $opportunityCaseTypeId = civicrm_api3('OptionValue', 'Getvalue',
          array('option_group_id' => $caseTypeOptionGroupId, 'name' => 'Opportunity', 'return' => 'value'));
        $accRelationshipTypeId = civicrm_api3('RelationshipType', 'Getvalue', array('return' => 'id', 'name_a_b' => 'Account Holder'));
        $opportunityCustomGroup = civicrm_api3('CustomGroup', 'Getsingle', array('name' => 'Opportunity Outline'));
        $quoteAmountColumn = civicrm_api3('CustomField', 'Getvalue', array('custom_group_id' => $opportunityCustomGroup['id'], 'name' => 'Quote_amount_', 'return' => 'column_name'));
        $deadlineColumn = civicrm_api3('CustomField', 'Getvalue', array('custom_group_id' => $opportunityCustomGroup['id'], 'name' => 'Deadline', 'return' => 'column_name'));
        $query = "CREATE OR REPLACE VIEW pum_opportunity AS
            SELECT cascont.case_id, cc.subject, contact.display_name AS client_name, 
            cascont.contact_id AS client_id, acchld.display_name AS account_name, acchld.id AS account_id, 
            {$quoteAmountColumn} AS quote_amount, {$deadlineColumn} AS deadline, cc.status_id, status.label AS status, status.weight
            FROM civicrm_case cc
            LEFT JOIN civicrm_case_contact cascont ON cc.id = cascont.case_id
            JOIN civicrm_contact contact ON cascont.contact_id = contact.id
            LEFT JOIN civicrm_relationship rel ON rel.case_id = cc.id 
              AND rel.relationship_type_id = {$accRelationshipTypeId}
            LEFT JOIN civicrm_contact acchld ON acchld.id = rel.contact_id_b
            LEFT JOIN {$opportunityCustomGroup['table_name']} opp ON opp.entity_id = cc.id
            LEFT JOIN civicrm_option_value status ON cc.status_id = status.value 
              AND status.option_group_id = {$caseStatusOptionGroupId}
            WHERE cc.case_type_id LIKE '%{$opportunityCaseTypeId}%' AND cc.is_deleted = 0";
          CRM_Core_DAO::executeQuery($query);
    return true;
  }

  /**
   * Upgrade 1005 - add business coordinator for main activity
   */

  public function upgrade_1005() {
    $this->ctx->log->info('Applying update 1005 add business coordinator to main activity view');
    $this->createViewMyMainActivities();
    return true;
  }

  /**
   * Upgrade 1006 - add grant coordinator and anamon for main activity
   */

  public function upgrade_1006() {
    $this->ctx->log->info('Applying update 1006 add grant coordinator and anamon to main activity view');
    $this->createViewMyMainActivities();
    return true;
  }

  /**
   * Upgrade 1010 - create view for report Expert Application
   */
  public function upgrade_1010() {
    $this->ctx->log->info('Applying update 1010 add view for report Expert Applications');
    $this->createViewMyExpertApplication();
    return true;
  }

  /**
   * Upgrade 1020 - create view for report Pum Projects
   */
  public function upgrade_1020() {
    $this->ctx->log->info('Applying update 1020 add view for report PUM Projects');
    $this->createPumProjectsView();
    return true;
  }

  /**
   * Upgrade 1025 - add data for report projectintake
   */
  public function upgrade_1025() {
    $this->ctx->log->info('Applying update 1025 add data and view for projectintake');
    // add columns assess_rep/cc/sc/anamon_date/customer
    if (CRM_Core_DAO::checkTableExists('civicrm_pum_case_reports')) {
      $columns = array(
        '0' => array('name' => 'assess_rep_date', 'type' => 'DATE'),
        '1' => array('name' => 'assess_rep_customer', 'type' => 'VARCHAR(45)'),
        '2' => array('name' => 'assess_cc_date', 'type' => 'DATE'),
        '3' => array('name' => 'assess_cc_customer', 'type' => 'VARCHAR(45)'),
        '4' => array('name' => 'assess_sc_date', 'type' => 'DATE'),
        '5' => array('name' => 'assess_sc_customer', 'type' => 'VARCHAR(45)'),
        '6' => array('name' => 'assess_anamon_date', 'type' => 'DATE'),
        '7' => array('name' => 'assess_anamon_customer', 'type' => 'VARCHAR(45)'),
      );
      foreach ($columns as $column) {
        if (!CRM_Core_DAO::checkFieldExists('civicrm_pum_case_reports', $column['name'])) {
          CRM_Core_DAO::executeQuery('ALTER TABLE civicrm_pum_case_reports ADD COLUMN ' . $column['name']
            . ' ' . $column['type'] . ' DEFAULT NULL');
        }
      }
    }
    $this->createPumProjectIntakeView();
    return true;
  }
}
