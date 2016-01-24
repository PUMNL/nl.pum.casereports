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
    $query = 'SELECT ma.'.$this->_ccColumn.', ma.'.$this->_scColumn.' FROM civicrm_case_activity cact
      JOIN civicrm_activity act ON cact.activity_id = act.id AND act.is_current_revision = %1 AND act.is_deleted = %2
      LEFT JOIN '.$this->_acceptTableName.' ma ON cact.activity_id = ma.entity_id
      WHERE act.activity_type_id = %3 AND cact.case_id = %4';
    $params = array(
      1 => array(1, 'Integer'),
      2 => array(0, 'Integer'),
      3 => array($this->_acceptActivityTypeId, 'Integer'),
      4 => array($caseId, 'Integer')
    );

    $dao = CRM_Core_DAO::executeQuery($query, $params);
    while ($dao->fetch()) {
      $ccColumn = $this->_ccColumn;
      $scColumn = $this->_scColumn;
      if (CRM_Casereports_Activity::caseExists($caseId) == TRUE) {
        if (!empty($dao->$ccColumn) && !empty($dao->$scColumn)) {
          $update = 'UPDATE civicrm_pum_case_reports SET ma_expert_approval = %1, pq_approved_cc = %2,
          pq_approved_sc = %3 WHERE case_id = %4';
          $values = array(
            1 => array(1, 'Integer'),
            2 => array($dao->$ccColumn, 'String'),
            3 => array($dao->$scColumn, 'String'),
            4 => array($caseId, 'Integer')
          );
        } else {
          if (empty($dao->$ccColumn) && empty($dao->$scColumn)) {
            $update = 'UPDATE civicrm_pum_case_reports SET ma_expert_approval = %1, pq_approved_cc = NULL,
              pq_approved_sc = NULL WHERE case_id = %2';
            $values = array(
              1 => array(1, 'Integer'),
              2 => array($caseId, 'Integer')
            );
        } else {
            if (empty($dao->$ccColumn)) {
              $update = 'UPDATE civicrm_pum_case_reports SET ma_expert_approval = %1, pq_approved_cc = NULL,
              pq_approved_sc = %2 WHERE case_id = %3';
              $values = array(
                1 => array(1, 'Integer'),
                2 => array($dao->$scColumn, 'String'),
                3 => array($caseId, 'Integer')
              );
            } else {
              $update = 'UPDATE civicrm_pum_case_reports SET ma_expert_approval = %1, pq_approved_cc = %2,
              pq_approved_sc = NULL WHERE case_id = %3';
              $values = array(
                1 => array(1, 'Integer'),
                2 => array($dao->$ccColumn, 'String'),
                3 => array($caseId, 'Integer')
              );
            }
          }
        }
        CRM_Core_DAO::executeQuery($update, $values);
      } else {
        if (!empty($dao->$ccColumn) && !empty($dao->$scColumn)) {
          $insert = 'INSERT INTO civicrm_pum_case_reports (case_id, ma_expert_approval, pq_approved_cc, pq_approved_sc)
          VALUES(%1, %2, %3, %4)';
          $values = array(
            1 => array($caseId, 'Integer'),
            2 => array(1, 'Integer'),
            3 => array($dao->$ccColumn, 'String'),
            4 => array($dao->$scColumn, 'String')
          );
        } else {
          if (empty($dao->$ccColumn) && empty($dao->$scColumn)) {
            $insert = 'INSERT INTO civicrm_pum_case_reports (case_id, ma_expert_approval) VALUES(%1, %2)';
            $values = array(
              1 => array($caseId, 'Integer'),
              2 => array(1, 'Integer')
            );
          } else {
            if (empty($dao->$ccColumn)) {
              $insert = 'INSERT INTO civicrm_pum_case_reports (case_id, ma_expert_approval, pq_approved_sc) VALUES(%1, %2, %3)';
              $values = array(
                1 => array($caseId, 'Integer'),
                2 => array(1, 'Integer'),
                3 => array($dao->$scColumn, 'String')
              );
            } else {
              $insert = 'INSERT INTO civicrm_pum_case_reports (case_id, ma_expert_approval, pq_approved_cc) VALUES(%1, %2, %3)';
              $values = array(
                1 => array($caseId, 'Integer'),
                2 => array(1, 'Integer'),
                3 => array($dao->$ccColumn, 'String')
              );
            }
          }
        }
        CRM_Core_DAO::executeQuery($insert, $values);
      }
    }
  }

  /**
   * Method to import reject main activity proposal for case_id
   *
   * @param $caseId
   */
  public function importRejects($caseId) {
    $query = 'SELECT COUNT(*) AS countReject FROM civicrm_case_activity cact
      JOIN civicrm_activity act ON cact.activity_id = act.id AND act.is_current_revision = %1 AND act.is_deleted = %2
      WHERE act.activity_type_id = %3 AND cact.case_id = %4';
    $params = array(
      1 => array(1, 'Integer'),
      2 => array(0, 'Integer'),
      3 => array($this->_rejectActivityTypeId, 'Integer'),
      4 => array($caseId, 'Integer')
    );
    $dao = CRM_Core_DAO::executeQuery($query, $params);
    if ($dao->fetch()) {
      if ($dao->countReject > 0) {
        if (CRM_Casereports_Activity::caseExists($caseId) == TRUE) {
          $update = 'UPDATE civicrm_pum_case_reports SET ma_expert_approval = %1, pq_approved_cc = NULL,
            pq_approved_sc = NULL WHERE case_id = %2';
          $values = array(
            1 => array(0, 'Integer'),
            2 => array($caseId, 'Integer')
          );
          CRM_Core_DAO::executeQuery($update, $values);
        } else {
          $insert = 'INSERT INTO civicrm_pum_case_reports (case_id, ma_expert_approval, pq_approved_cc, pq_approved_sc)
            VALUES(%1, %2, NULL, NULL)';
          $values = array(
            1 => array($caseId, 'Integer'),
            2 => array(0, 'Integer')
          );
          CRM_Core_DAO::executeQuery($insert, $values);
        }
      }
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