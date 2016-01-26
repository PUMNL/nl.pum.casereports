<?php
/**
 * Class for importing current data
 *
 * @author Erik Hommel (CiviCooP) <erik.hommel@civicoop.org>
 * @date 23 Jan 2016
 * @license AGPL-3.0
 */

class CRM_Casereports_Import {

  public $_ccColumn = NULL;
  public $_scColumn = NULL;
  public $_acceptTableName = NULL;
  public $_acceptActivityTypeId = NULL;
  public $_rejectActivityTypeId = NULL;
  public $_briefingActivityTypeId = NULL;

  /**
   * CRM_Casereports_Import constructor.
   */
  function __construct() {
    $config = CRM_Casereports_Config::singleton();
    $this->_acceptActivityTypeId = $config->getMaAcceptActivityTypeId();
    $this->_rejectActivityTypeId = $config->getMaRejectActivityTypeId();
    $this->_briefingActivityTypeId = $config->getBriefingActivityTypeId();
    $customGroup = $config->getMaAcceptCustomGroup();
    $this->_acceptTableName = $customGroup['table_name'];
    foreach ($customGroup['custom_fields'] as $customFieldId => $customField) {
      if ($customField['name'] == 'Assessment_SC') {
        $this->_scColumn = $customField['column_name'];
      }
      if ($customField['name'] == 'Assessment_CC') {
        $this->_ccColumn = $customField['column_name'];
      }
    }
  }

  /**
   * Method to import accept main activity proposal custom data for case_id
   *
   * @param $caseId
   */
  public function importAccepts($caseId) {
    $query = "SELECT act.activity_type_id, act.id
      FROM civicrm_case_activity cas JOIN civicrm_activity act ON cas.activity_id = act.id
      WHERE act.is_current_revision = %1 AND act.activity_type_id IN (%2, %3) AND cas.case_id = %4
      ORDER BY act.activity_date_time DESC, act.id DESC";
    $params = array(
      1 => array(1, 'Integer'),
      2 => array($this->_acceptActivityTypeId, 'Integer'),
      3 => array($this->_rejectActivityTypeId, 'Integer'),
      4 => array($caseId, 'Integer')
    );
    $dao = CRM_Core_DAO::executeQuery($query, $params);
    // set status based on first activity retrieved
    if ($dao->fetch()) {
      // if accept -> get custom data and write or update case record
      if ($dao->activity_type_id == $this->_acceptActivityTypeId) {
        $queryCustom = 'SELECT '.$this->_ccColumn.', '.$this->_scColumn.' FROM '.$this->_acceptTableName.' WHERE entity_id = %1';
        $paramsCustom = array(1 => array($dao->id, 'Integer'));
        $daoCustom = CRM_Core_DAO::executeQuery($queryCustom, $paramsCustom);
        if ($daoCustom->fetch()) {
          $updateClauses = array();
          $ccColumn = $this->_ccColumn;
          $scColumn = $this->_scColumn;
          $values[1] = array('Yes', 'String');
          $updateClauses[] = "ma_expert_approval = %1";
          $values[2] = array($caseId, 'Integer');
          if (isset($daoCustom->$ccColumn) && !empty($daoCustom->$ccColumn)) {
            $values[3] = array($daoCustom->$ccColumn, 'String');
            $updateClauses[] = "pq_approved_cc = %3";
          }
          if (isset($daoCustom->$scColumn) && !empty($daoCustom->$scColumn)) {
            $values[4] = array($daoCustom->$scColumn, 'String');
            $updateClauses[] = "pq_approved_sc = %4";
          }
          if (CRM_Casereports_Activity::caseExists($caseId)) {
            $pumQuery = 'UPDATE civicrm_pum_case_reports SET '.implode(', ', $updateClauses).'  WHERE case_id = %2';
          } else {
            $updateClauses[] = "case_id = %2";
            $pumQuery = 'INSERT INTO civicrm_pum_case_reports SET '.implode(', ', $updateClauses);
          }
          CRM_Core_DAO::executeQuery($pumQuery, $values);
        }
      }
      // if reject -> write or update case record
      if ($dao->activity_type_id == $this->_rejectActivityTypeId) {
        $values = array(
          1 => array('No', 'String'),
          2 => array($caseId, 'Integer')
        );
        if (CRM_Casereports_Activity::caseExists($caseId)) {
          $pumQuery = 'UPDATE civicrm_pum_case_reports SET ma_expert_approval = %1, pq_approved_cc = NULL,
            pq_approved_sc = NULL WHERE case_id = %2';
        } else {
          $pumQuery = 'INSERT INTO civicrm_pum_case_reports (ma_expert_approval, case_id, pq_approved_cc, pq_approved_sc) VALUES(%1, %2, NULL, NULL)';
        }
        CRM_Core_DAO::executeQuery($pumQuery, $values);
      }
    } else {
      // if not found, write or update case record
      $values = array(
        1 => array('n/a', 'String'),
        2 => array($caseId, 'Integer')
      );
      if (CRM_Casereports_Activity::caseExists($caseId) == TRUE) {
        $pumQuery = 'UPDATE civicrm_pum_case_reports SET ma_expert_approval = %1, pq_approved_cc = NULL,
          pq_approved_sc = NULL WHERE case_id = %2';
      } else {
        $pumQuery = 'INSERT INTO civicrm_pum_case_reports (ma_expert_approval, case_id, pq_approved_cc, pq_approved_sc) VALUES(%1, %2, NULL, NULL)';
      }
      CRM_Core_DAO::executeQuery($pumQuery, $values);
    }
  }

  /**
   * Method to import briefing expert data for case_id
   *
   * @param $caseId
   */
  public function importBriefing($caseId) {
    $query = 'SELECT act.status_id, act.activity_date_time FROM civicrm_case_activity cact
      JOIN civicrm_activity act ON cact.activity_id = act.id AND act.is_current_revision = %1 AND act.is_deleted = %2
      WHERE act.activity_type_id = %3 AND cact.case_id = %4';
    $params = array(
      1 => array(1, 'Integer'),
      2 => array(0, 'Integer'),
      3 => array($this->_briefingActivityTypeId, 'Integer'),
      4 => array($caseId, 'Integer')
    );
    $dao = CRM_Core_DAO::executeQuery($query, $params);
    while ($dao->fetch()) {
      if (CRM_Casereports_Activity::caseExists($caseId) == TRUE) {
        $update = 'UPDATE civicrm_pum_case_reports SET briefing_status = %1, briefing_date = %2 WHERE case_id = %3';
        $values = array(
          1 => array(CRM_Casereports_Activity::setBriefingStatusColumn($dao->status_id), 'String'),
          2 => array(date('Ymd', strtotime($dao->activity_date_time)), 'String'),
          3 => array($caseId, 'Integer')
        );
        CRM_Core_DAO::executeQuery($update, $values);
      } else {
        $insert = 'INSERT INTO civicrm_pum_case_reports (case_id, briefing_status, briefing_date)
          VALUES(%1, %2, %3)';
        $values = array(
          1 => array($caseId, 'Integer'),
          2 => array(CRM_Casereports_Activity::setBriefingStatusColumn($dao->status_id), 'String'),
          3 => array(date('Ymd', strtotime($dao->activity_date_time)), 'String')
        );
        CRM_Core_DAO::executeQuery($insert, $values);
      }
    }
  }
}