<?php
/**
 * Class for Activity processing
 *
 * @author Erik Hommel (CiviCooP) <erik.hommel@civicoop.org>
 * @date 22 Jan 2016
 * @license AGPL-3.0
 */

class CRM_Casereports_Activity {
  /**
   * Method to implement hook civicrm_post for Activity
   * If activity_type_id Accept Main Activity Proposal or Briefing Expert, process
   * data into civicrm_pum_case_reports
   *
   * @param $op
   * @param $objectRef
   * @access public
   * @static
   */
  public static function post($op, $objectRef) {
    if ($op == 'delete') {
      if (isset($objectRef->case_id) && !empty($objectRef->case_id)) {
        self::processDelete($objectRef->id, $objectRef->case_id);
      }
    } else {
      $config = CRM_Casereports_Config::singleton();
      switch ($objectRef->activity_type_id) {
        case $config->getMaAcceptActivityTypeId():
          self::processAccept($objectRef);
          break;
        case $config->getMaRejectActivityTypeId():
          self::processReject($objectRef->case_id);
          break;
        case $config->getBriefingActivityTypeId():
          self::processBriefing($objectRef);
          break;
      }
    }
  }

  /**
   * Method to process activity Accept Main Activity Proposal
   *
   * @param object $objectRef
   * @access private
   * @static
   */
  private static function processAccept($objectRef) {
    $approvalValues = self::buildApprovalValues($objectRef->id);
    if (self::caseExists($objectRef->case_id)) {
      $query = 'UPDATE civicrm_pum_case_reports SET ma_expert_approval = %1, pq_approved_cc = %2, pq_approved_sc = %3
        WHERE case_id = %4';
      $values = array(
        1 => array(1, 'Integer'),
        2 => array($approvalValues['cc'], 'String'),
        3 => array($approvalValues['sc'], 'String'),
        4 => array($objectRef->case_id, 'Integer')
      );
      CRM_Core_DAO::executeQuery($query, $values);
    } else {
      $query = 'INSERT INTO civicrm_pum_case_reports (case_id, ma_expert_approval, pq_approved_cc, pq_approved_sc)
        VALUES(%1, %2, %3, %4)';
      $values = array(
        1 => array($objectRef->case_id, 'Integer'),
        2 => array(1, 'Integer'),
        3 => array($approvalValues['cc'], 'String'),
        4 => array($approvalValues['sc'], 'String')
      );
      CRM_Core_DAO::executeQuery($query, $values);
    }
  }

  /**
   * Method to retrieve custom fields cc and sc approval from custom group extending activity
   * @param int $activityId
   * @return array $approvalValues
   */
  private static function buildApprovalValues($activityId) {
    $ccColumn = NULL;
    $scColumn = NULL;
    $approvalValues = array(
      'cc' => "n/a",
      'sc' => "n/a"
    );
    $config = CRM_Casereports_Config::singleton();
    $customGroup = $config->getMaAcceptCustomGroup();
    foreach ($customGroup['custom_fields'] as $customFieldId => $customField) {
      if ($customField['name'] == "Assessment_CC") {
        $ccColumn = $customField['column_name'];
      }
      if ($customField['name'] == "Assessment_SC") {
        $scColumn = $customField['column_name'];
      }
    }
    $query = "SELECT * FROM ".$customGroup['table_name']." WHERE entity_id = %1";
    $dao = CRM_Core_DAO::executeQuery($query, array(1 => array($activityId, 'Integer')));
    if ($dao->fetch()) {
      if (!empty($ccColumn)) {
        $approvalValues['cc'] = $dao->$ccColumn;
      }
      if (!empty($scColumn)) {
        $approvalValues['sc'] = $dao->$scColumn;
      }
    }
    return $approvalValues;
  }

  /**
   * Method to process activity Reject Main Activity Proposal
   *
   * @param int $caseId
   * @access private
   * @static
   */
  private static function processReject($caseId) {
    $values = array();
    $values['case_id'] = $caseId;
      $values['ma_expert_approval'] = 0;
    $values['pq_approved_cc'] = NULL;
    $values['pq_approved_sc'] = NULL;

    if (self::caseExists($caseId)) {
      $query = 'UPDATE civicrm_pum_case_reports SET ma_expert_approval = %1, pq_approved_cc = NULL, pq_approved_sc = NULL
        WHERE case_id = %2';
      $values = array(
        1 => array(0, 'Integer'),
        2 => array($caseId, 'Integer')
      );
      CRM_Core_DAO::executeQuery($query, $values);
    } else {
      $query = 'INSERT INTO civicrm_pum_case_reports (case_id, ma_expert_approval, pq_approved_cc, pq_approved_sc)
        VALUES(%1, %2, NULL, NULL)';
      $values = array(
        1 => array($caseId, 'Integer'),
        2 => array(0, 'Integer')
      );
      CRM_Core_DAO::executeQuery($query, $values);
    }
  }

  /**
   * Method to process activity Briefing Expert

   * @param $objectRef
   * @access private
   * @static
   */
  private static function processBriefing($objectRef) {
    if (self::caseExists($objectRef->case_id)) {
      $query = "UPDATE civicrm_pum_case_reports SET briefing_status = %1, briefing_date = %2 WHERE case_id = %3";
      $values = array(
        1 => array(self::setBriefingStatusColumn($objectRef->status_id), 'String'),
        2 => array(date('Ymd', strtotime($objectRef->activity_date_time)), 'String'),
        3 => array($objectRef->case_id, 'Integer')
      );
      CRM_Core_DAO::executeQuery($query, $values);
    } else {
      $query = "INSERT INTO civicrm_pum_case_reports (case_id, briefing_status, briefing_date) VALUES(%1, %2, %3)";
      $values = array(
        1 => array($objectRef->case_id, 'Integer'),
        2 => array(self::setBriefingStatusColumn($objectRef->status_id), 'String'),
        3 => array(date('Ymd', strtotime($objectRef->activity_date_time)), 'String')
      );
      CRM_Core_DAO::executeQuery($query, $values);
    }
  }

  /**
   * Method to get the label for the activity status
   *
   * @param $statusId
   * @return array|string
   * @throws Exception when option group or option value not found
   * @access private
   * @static
   */
  public static function setBriefingStatusColumn($statusId) {
    try {
      $optionGroupId = civicrm_api3('OptionGroup', 'Getvalue', array('name' => 'activity_status', 'return' => 'id'));
      $optionValueParams = array('option_group_id' => $optionGroupId, 'value' => $statusId, 'return' => 'label');
      try {
        return civicrm_api3('OptionValue', 'Getvalue', $optionValueParams);
      } catch (CiviCRM_API3_Exception $ex){
        throw new Exception('Could not find an activity status with value '.$statusId
          .', error from API OptionValue Getvalue: '.$ex->getMessage());
      }
    } catch (CiviCRM_API3_Exception $ex) {
      throw new Exception('Could not find option group with name activity_status,
        contact your system administrator. Error from API OptionGroup Getvalue: '.$ex->getMessage());
    }
  }

  /**
   * Method to check if there is a record for the case in civicrm_pum_case_reports
   *
   * @param $caseId
   * @return boolean
   * @access private
   * @static
   */
  public static function caseExists($caseId) {
    $countQry = "SELECT COUNT(*) FROM civicrm_pum_case_reports WHERE case_id = %1";
    $count = CRM_Core_DAO::singleValueQuery($countQry, array(1 => array($caseId, 'Integer')));
    if ($count == 0) {
      return FALSE;
    } else {
      return TRUE;
    }
  }

  /**
   * Method to set columns when activity is deleted
   *
   * @param $activityId
   * @param $caseId
   * @access private
   * @static
   */
  private static function processDelete($activityId, $caseId) {
    try {
      $activity = civicrm_api3('Activity', 'Getsingle', array('id' => $activityId));
      $config = CRM_Casereports_Config::singleton();
      if ($activity['activity_type_id'] == $config->getMaAcceptActivityTypeId() ||
        $activity['activity_type_id'] == $config->getMaRejectActivityTypeId()) {
        $update = "UPDATE civicrm_pum_case_reports SET ma_expert_approval = NULL, pq_approved_cc = NULL,
          pq_approved_sc = NULL WHERE case_id = %1";
        CRM_Core_DAO::executeQuery($update, array(1 => array($caseId, 'Integer')));
      }
      if ($activity['activity_type_id'] == $config->getBriefingActivityTypeId()) {
        $update = "UPDATE civicrm_pum_case_reports SET briefing_status = NULL, briefing_date = NULL WHERE case_id = %1";
        CRM_Core_DAO::executeQuery($update, array(1 => array($caseId, 'Integer')));
      }
    } catch (CiviCRM_API3_Exception $ex) {}
  }
}