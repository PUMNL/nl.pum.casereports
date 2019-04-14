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
   * @param $objectId
   * @param $objectRef
   * @access public
   * @static
   */
  public static function post($op, $objectId, $objectRef) {
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
        case $config->getAssessRepActivityTypeId():
          self::processAssessData('assess_rep', $objectId, $objectRef);
          break;
        case $config->getAssessCCActivityTypeId():
          self::processAssessData('assess_cc', $objectId, $objectRef);
          break;
        case $config->getAssessSCActivityTypeId():
          self::processAssessData('assess_sc', $objectId, $objectRef);
          break;
        case $config->getAssessPrOfActivityTypeId():
          self::processAssessData('assess_anamon', $objectId, $objectRef);
          break;
      }
    }
  }

  /**
   * Method to update assess rep result in report table
   *
   * @param $op
   * @param $groupId
   * @param $entityId
   * @param $params
   * @access public
   * @static
   */
  public static function custom($op, $groupId, $entityId, $params) {
    $config = CRM_Casereports_Config::singleton();
    if ($groupId == $config->getAssessRepCustomGroupId()) {
      if ($op == 'delete') {
        if (self::caseExists($entityId)) {
          $query = "UPDATE civicrm_pum_case_reports SET assess_rep_customer = NULL WHERE case_id = %1";
          CRM_Core_DAO::executeQuery($query, array(1 => array($entityId, 'Integer')));
        }
      } else {
        foreach ($params as $paramValues) {
          if ($paramValues['column_name'] == $config->getAssessRepCustomColumn()) {
            $value = $paramValues['value'];
          }
        }
        if (self::caseExists($entityId)) {
          $query = "UPDATE civicrm_pum_case_reports SET assess_rep_customer = %1 WHERE case_id = %2";
        } else {
          $query = "INSERT INTO civicrm_pum_case_reports (case_id, assess_rep_customer) VALUES(%2, %1)";
        }
        $queryParams = array(
          1 => array($value, 'String'),
          2 => array($entityId, 'Integer'));
        CRM_Core_DAO::executeQuery($query, $queryParams);
      }
    }
  }

  /**
   * Method to set the activity date time and assessment value in civicrm_pum_case_reports
   *
   * @param $columnName
   * @param $activityId
   * @param $objectRef
   * @access private
   * @static
   */
  private static function processAssessData($columnName, $activityId,  $objectRef) {
    if (isset($objectRef->activity_date_time) && isset($objectRef->case_id)) {
      // retrieve the actual assessment column from the correct custom table and column
      if ($columnName != 'assess_rep') {
        $customValue = self::getAssessResult($columnName, $activityId);
      } else {
        $customValue = "";
      }
      if (self::caseExists($objectRef->case_id)) {
        if ($columnName == 'assess_rep') {
          $query = "UPDATE civicrm_pum_case_reports SET " . $columnName . "_date = %1 WHERE case_id = %3";
        } else {
          $query = "UPDATE civicrm_pum_case_reports SET " . $columnName . "_date = %1, " . $columnName . "_customer = %2 WHERE case_id = %3";
        }
      } else {
        if ($columnName == 'assess_rep') {
          $query = "INSERT INTO civicrm_pum_case_reports (case_id, ".$columnName."_date) VALUES(%3, %1)";
        } else {
          $query = "INSERT INTO civicrm_pum_case_reports (case_id, ".$columnName."_customer, ".$columnName."_date) VALUES(%3, %2, %1)";
        }
      }
      $params = array(
        1 => array(date('Y-m-d H:i:s', strtotime($objectRef->activity_date_time)), 'String'),
        2 => array($customValue, 'String'),
        3 => array($objectRef->case_id, 'Integer')
      );
      CRM_Core_DAO::executeQuery($query, $params);
    }
  }

  /**
   * Method to get the assessement custom field
   *
   * @param $columnName
   * @param $entityId
   * @return string
   * @access private
   * @static
   */
  private static function getAssessResult($columnName, $entityId) {
    $result = "";
    $config = CRM_Casereports_Config::singleton();
    switch ($columnName) {
      case 'assess_sc':
        $tableName = $config->getAssessSCCustomTable();
        $columnName = $config->getAssessSCCustomColumn();
        break;
      case 'assess_rep':
        $tableName = $config->getAssessRepCustomTable();
        $columnName = $config->getAssessRepCustomColumn();
        break;
      case 'assess_cc':
        $tableName = $config->getAssessCCCustomTable();
        $columnName = $config->getAssessCCCustomColumn();
        break;
      case 'assess_anamon':
        $tableName = $config->getAssessAnamonCustomTable();
        $columnName = $config->getAssessAnamonCustomColumn();
        break;
      default:
        $tableName = "";
        $columnName = "";
    }
    if (!empty($columnName) && !empty($tableName)) {
      $query = "SELECT ".$columnName." FROM ".$tableName." WHERE entity_id = %1";
      $result = CRM_Core_DAO::singleValueQuery($query, array(1 => array($entityId, 'Integer')));
    }
    return $result;
  }

  /**
   * Method to process activity Accept Main Activity Proposal
   *
   * @param object $objectRef
   * @access private
   * @static
   */
  private static function processAccept($objectRef) {
    if (self::caseExists($objectRef->case_id)) {
      $query = 'UPDATE civicrm_pum_case_reports SET ma_expert_approval = %1
        WHERE case_id = %2';
      $values = array(
        1 => array('Yes', 'String'),
        2 => array($objectRef->case_id, 'Integer')
      );
      CRM_Core_DAO::executeQuery($query, $values);
    } else {
      $query = 'INSERT INTO civicrm_pum_case_reports (case_id, ma_expert_approval)
        VALUES(%1, %2)';
      $values = array(
        1 => array($objectRef->case_id, 'Integer'),
        2 => array('Yes', 'String'),
      );
      CRM_Core_DAO::executeQuery($query, $values);
    }
  }

  /**
   * Method to process activity Reject Main Activity Proposal
   *
   * @param int $caseId
   * @access private
   * @static
   */
  private static function processReject($caseId) {
    if (self::caseExists($caseId)) {
      $query = 'UPDATE civicrm_pum_case_reports SET ma_expert_approval = %1
        WHERE case_id = %2';
      $values = array(
        1 => array('No', 'String'),
        2 => array($caseId, 'Integer')
      );
      CRM_Core_DAO::executeQuery($query, $values);
    } else {
      $query = 'INSERT INTO civicrm_pum_case_reports (case_id, ma_expert_approval)
        VALUES(%1, %2)';
      $values = array(
        1 => array($caseId, 'Integer'),
        2 => array('No', 'String')
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
      if (isset($objectRef->activity_date_time) && !empty($objectRef->activity_date_time)) {
        $query = "UPDATE civicrm_pum_case_reports SET briefing_status = %1, briefing_date = %2 WHERE case_id = %3";
        $values = array(
          1 => array(self::setBriefingStatusColumn($objectRef->status_id), 'String'),
          2 => array(date('Ymd', strtotime($objectRef->activity_date_time)), 'String'),
          3 => array($objectRef->case_id, 'Integer')
        );
      } else {
        $query = "UPDATE civicrm_pum_case_reports SET briefing_status = %1 WHERE case_id = %2";
        $values = array(
          1 => array(self::setBriefingStatusColumn($objectRef->status_id), 'String'),
          2 => array($objectRef->case_id, 'Integer')
        );
      }
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
      switch ($activity['activity_type_id']) {
        case $config->getMaAcceptActivityTypeId():
          $update = "UPDATE civicrm_pum_case_reports SET ma_expert_approval = %1 WHERE case_id = %2";
          CRM_Core_DAO::executeQuery($update, array(
            1 => array('n/a', 'String'),
            2 => array($caseId, 'Integer')));
          break;
        case $config->getBriefingActivityTypeId():
          $update = "UPDATE civicrm_pum_case_reports SET briefing_status = NULL, briefing_date = NULL WHERE case_id = %1";
          CRM_Core_DAO::executeQuery($update, array(1 => array($caseId, 'Integer')));
          break;
        case $config->getAssessRepActivityTypeId():
          $update = "UPDATE civicrm_pum_case_reports SET assess_rep_date = NULL , assess_rep_customer = NULL WHERE case_id = %1";
          CRM_Core_DAO::executeQuery($update, array(1 => array($caseId, 'Integer')));
          break;
        case $config->getAssessCCActivityTypeId():
          $update = "UPDATE civicrm_pum_case_reports SET assess_cc_date = NULL, assess_cc_customer = NULL WHERE case_id = %1";
          CRM_Core_DAO::executeQuery($update, array(1 => array($caseId, 'Integer')));
          break;
        case $config->getAssessSCActivityTypeId():
          $update = "UPDATE civicrm_pum_case_reports SET assess_sc_date = NULL, assess_sc_customer = NULL WHERE case_id = %1";
          CRM_Core_DAO::executeQuery($update, array(1 => array($caseId, 'Integer')));
          break;
        case $config->getAssessPrOfActivityTypeId():
          $update = "UPDATE civicrm_pum_case_reports SET assess_anamon_date = NULL, assess_anamon_customer = NULL WHERE case_id = %1";
          CRM_Core_DAO::executeQuery($update, array(1 => array($caseId, 'Integer')));
          break;
      }

    } catch (CiviCRM_API3_Exception $ex) {}
  }
}