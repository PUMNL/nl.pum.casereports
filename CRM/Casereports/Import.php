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

  public $_assessRepActivityTypeId = NULL;
  public $_assessCCActivityTypeId = NULL;
  public $_assessSCActivityTypeId = NULL;
  public $_assessAnamonActivityTypeId = NULL;
  public $_assessRepCustomTable = NULL;
  public $_assessSCCustomTable = NULL;
  public $_assessCCCustomTable = NULL;
  public $_assessAnamonCustomTable = NULL;
  public $_assessRepCustomColumn = NULL;
  public $_assessSCCustomColumn = NULL;
  public $_assessCCCustomColumn = NULL;
  public $_assessAnamonCustomColumn = NULL;

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
    $this->_assessRepActivityTypeId = $config->getAssessRepActivityTypeId();
    $this->_assessSCActivityTypeId = $config->getAssessSCActivityTypeId();
    $this->_assessCCActivityTypeId = $config->getAssessCCActivityTypeId();
    $this->_assessAnamonActivityTypeId = $config->getAssessAnamonActivityTypeId();
    $this->_assessRepCustomTable = $config->getAssessRepCustomTable();
    $this->_assessSCCustomTable = $config->getAssessSCCustomTable();
    $this->_assessCCCustomTable = $config->getAssessCCCustomTable();
    $this->_assessAnamonCustomTable = $config->getAssessAnamonCustomTable();
    $this->_assessRepCustomColumn = $config->getAssessRepCustomColumn();
    $this->_assessSCCustomColumn = $config->getAssessSCCustomColumn();
    $this->_assessCCCustomColumn = $config->getAssessCCCustomColumn();
    $this->_assessAnamonCustomColumn = $config->getAssessAnamonCustomColumn();
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

  /**
   * Method to import project intake data into civicrm_pum_case_reports for Project Intake report
   *
   * @param $caseId
   * @access public
   */
  public function importProjectIntake($caseId) {
    // get relevant activities
    $actQuery = "SELECT act.id, act.activity_date_time, act.activity_type_id
FROM civicrm_case_activity ca JOIN civicrm_activity act ON ca.activity_id = act.id AND act.is_current_revision = %6
 AND act.is_test = %7 AND act.is_deleted = %7 AND act.activity_type_id IN (%1, %2, %3, %4) WHERE ca.case_id = %5";
    $actParams = array(
      1 => array($this->_assessSCActivityTypeId, 'Integer'),
      2 => array($this->_assessRepActivityTypeId, 'Integer'),
      3 => array($this->_assessCCActivityTypeId, 'Integer'),
      4 => array($this->_assessAnamonActivityTypeId, 'Integer'),
      5 => array($caseId, 'Integer'),
      6 => array(1, 'Integer'),
      7 => array(0, 'Integer'));
    $actDao = CRM_Core_DAO::executeQuery($actQuery, $actParams);
    $pumFields = array();
    $pumParams = array();
    $pumParams[1] = array($caseId, 'Integer');
    $pumIndex = 1;
    while ($actDao->fetch()) {
      switch ($actDao->activity_type_id) {
        case $this->_assessSCActivityTypeId:
          if (!empty($actDao->activity_date_time)) {
            $pumIndex++;
            $clause = "assess_sc_date = %".$pumIndex;
            if (!in_array($clause, $pumFields)) {
              $pumFields[] = "assess_sc_date = %" . $pumIndex;
              $pumParams[$pumIndex] = array(date('Y-m-d', strtotime($actDao->activity_date_time)), 'String');
            }
          }
          $scQuery = "SELECT ".$this->_assessSCCustomColumn." FROM ".$this->_assessSCCustomTable." WHERE entity_id = %1";
          $scCustomer = CRM_Core_DAO::singleValueQuery($scQuery, array(1 => array($actDao->id, 'Integer')));
          if (!empty($scCustomer)) {
            $clause = "assess_sc_customer = %".$pumIndex;
            if (!in_array($clause, $pumFields)) {
              $pumFields[] = "assess_sc_customer = %" . $pumIndex;
              $pumParams[$pumIndex] = array($scCustomer, 'String');
            }
          }
          break;
        case $this->_assessRepActivityTypeId:
          if (!empty($actDao->activity_date_time)) {
            $clause = "assess_rep_date = %".$pumIndex;
            if (!in_array($clause, $pumFields)) {
              $pumFields[] = "assess_rep_date = %" . $pumIndex;
              $pumParams[$pumIndex] = array(date('Y-m-d', strtotime($actDao->activity_date_time)), 'String');
            }
          }
          $repQuery = "SELECT ".$this->_assessRepCustomColumn." FROM ".$this->_assessRepCustomTable." WHERE entity_id = %1";
          $repCustomer = CRM_Core_DAO::singleValueQuery($repQuery, array(1 => array($caseId, 'Integer')));
          if (!empty($repCustomer)) {
            $clause = "assess_rep_customer = %".$pumIndex;
            if (!in_array($clause, $pumFields)) {
              $pumFields[] = "assess_rep_customer = %" . $pumIndex;
              $pumParams[$pumIndex] = array($repCustomer, 'String');
            }
          }
          break;
        case $this->_assessCCActivityTypeId:
          if (!empty($actDao->activity_date_time)) {
            $clause = "assess_cc_date = %".$pumIndex;
            if (!in_array($clause, $pumFields)) {
              $pumFields[] = "assess_cc_date = %" . $pumIndex;
              $pumParams[$pumIndex] = array(date('Y-m-d', strtotime($actDao->activity_date_time)), 'String');
            }
          }
          $ccQuery = "SELECT ".$this->_assessCCCustomColumn." FROM ".$this->_assessCCCustomTable." WHERE entity_id = %1";
          $ccCustomer = CRM_Core_DAO::singleValueQuery($ccQuery, array(1 => array($actDao->id, 'Integer')));
          if (!empty($ccCustomer)) {
            $clause = "assess_cc_customer = %".$pumIndex;
            if (!in_array($clause, $pumFields)) {
              $pumFields[] = "assess_cc_customer = %" . $pumIndex;
              $pumParams[$pumIndex] = array($ccCustomer, 'String');
            }
          }
          break;
        case $this->_assessAnamonActivityTypeId:
          if (!empty($actDao->activity_date_time)) {
            $clause = "assess_anamon_date = %".$pumIndex;
            if (!in_array($clause, $pumFields)) {
              $pumFields[] = "assess_anamon_date = %" . $pumIndex;
              $pumParams[$pumIndex] = array(date('Y-m-d', strtotime($actDao->activity_date_time)), 'String');
            }
          }
          $anamonQuery = "SELECT ".$this->_assessAnamonCustomColumn." FROM ".$this->_assessAnamonCustomTable." WHERE entity_id = %1";
          $anaMonCustomer = CRM_Core_DAO::singleValueQuery($anamonQuery, array(1 => array($actDao->id, 'Integer')));
          if (!empty($anaMonCustomer)) {
            $clause = "assess_anamon_customer = %".$pumIndex;
            if (!in_array($clause, $pumFields)) {
              $pumFields[] = "assess_anamon_customer = %" . $pumIndex;
              $pumParams[$pumIndex] = array($anaMonCustomer, 'String');
            }
          }
          break;
      }
    }
    if (CRM_Casereports_Activity::caseExists($caseId)) {
      if (!empty($pumFields)) {
        $update = "UPDATE civicrm_pum_case_reports SET " . implode(', ', $pumFields) . " WHERE case_id = %1";
        CRM_Core_DAO::executeQuery($update, $pumParams);
      }
    } else {
      if (!empty($pumFields)) {
        $insert = "INSERT INTO civicrm_pum_case_reports SET case_id = %1, " . implode(', ', $pumFields);
        CRM_Core_DAO::executeQuery($insert, $pumParams);
      } else {
        $insert = "INSERT INTO civicrm_pum_case_reports SET case_id = %1";
        CRM_Core_DAO::executeQuery($insert, $pumParams);
      }
    }
  }
}