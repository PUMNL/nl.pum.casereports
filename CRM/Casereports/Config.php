<?php
/**
 * Class following Singleton pattern for specific extension configuration
 * for PUM Case Reports
 *
 * @author Erik Hommel (CiviCooP) <erik.hommel@civicoop.org>
 * @date 22 Jan 2016
 * @license AGPL-3.0
 */

class CRM_Casereports_Config {
  /*
   * singleton pattern
   */
  static private $_singleton = NULL;

  // properties for main activity
  protected $_maAcceptActivityTypeId = NULL;
  protected $_maRejectActivityTypeId = NULL;
  protected $_maBriefingActivityTypeId = NULL;
  protected $_maAcceptCustomGroup = array();

  // properties for project intake
  protected $_assessRepActivityTypeId = NULL;
  protected $_assessCCActivityTypeId = NULL;
  protected $_assessSCActivityTypeId = NULL;
  protected $_assessPrOfActivityTypeId = NULL;
  protected $_assessRepCustomGroupId = NULL;
  protected $_assessRepCustomTable = NULL;
  protected $_assessCCCustomTable = NULL;
  protected $_assessSCCustomTable = NULL;
  protected $_assessAnamonCustomTable = NULL;
  protected $_assessRepCustomColumn = NULL;
  protected $_assessCCCustomColumn = NULL;
  protected $_assessSCCustomColumn = NULL;
  protected $_assessAnamonCustomColumn = NULL;

  /**
   * CRM_Casereports_Config constructor.
   */
  function __construct() {
    $this->setActivityTypes(array(
      '_maAcceptActivityTypeId' => 'Accept Main Activity Proposal',
      '_maRejectActivityTypeId' => 'Reject Main Activity Proposal',
      '_maBriefingActivityTypeId' => 'Briefing Expert',
      '_assessRepActivityTypeId' => 'Assessment Project Request by Rep',
      '_assessCCActivityTypeId' => 'Intake Customer by CC',
      '_assessSCActivityTypeId' => 'Intake Customer by SC',
      '_assessPrOfActivityTypeId' => 'Intake Customer by PrOf',
    ));
    $this->setCustomGroups(array(
      '_maAcceptCustomGroup' => 'Add_Keyqualifications',
    ));
    $this->setAssessIntakeCustomData();
  }

  /**
   * Getter for Assessment Project Intake by Anamon by Representative Custom Column Name
   *
   * @return array
   * @access public
   */
  public function getAssessAnamonCustomColumn() {
    return $this->_assessAnamonCustomColumn;
  }

  /**
   * Getter for Assessment Project Intake by Country Coordinator by Representative Custom Column Name
   *
   * @return array
   * @access public
   */
  public function getAssessCCCustomColumn() {
    return $this->_assessCCCustomColumn;
  }

  /**
   * Getter for Assessment Project Intake by Anamon by Representative Custom Column Name
   *
   * @return array
   * @access public
   */
  public function getAssessRepCustomColumn() {
    return $this->_assessRepCustomColumn;
  }

  /**
   * Getter for Assessment Project Intake by Anamon by Sector Coordinator Custom Column Name
   *
   * @return array
   * @access public
   */
  public function getAssessSCCustomColumn() {
    return $this->_assessSCCustomColumn;
  }

  /**
   * Getter for Assessment Project Intake by Anamon by Representative Custom Table Name
   *
   * @return array
   * @access public
   */
  public function getAssessAnamonCustomTable() {
    return $this->_assessAnamonCustomTable;
  }

  /**
   * Getter for Assessment Project Intake by Country Coordinator by Representative Custom Table Name
   *
   * @return array
   * @access public
   */
  public function getAssessCCCustomTable() {
    return $this->_assessCCCustomTable;
  }

  /**
   * Getter for Assessment Project Intake by Anamon by Representative Custom Table Name
   *
   * @return array
   * @access public
   */
  public function getAssessRepCustomTable() {
    return $this->_assessRepCustomTable;
  }

  /**
   * Getter for Assessment Project Intake by Anamon by Representative Custom Group Id
   *
   * @return array
   * @access public
   */
  public function getAssessRepCustomGroupId() {
    return $this->_assessRepCustomGroupId;
  }

  /**
   * Getter for Assessment Project Intake by Anamon by Sector Coordinator Custom Table Name
   *
   * @return array
   * @access public
   */
  public function getAssessSCCustomTable() {
    return $this->_assessSCCustomTable;
  }

  /**
   * Getter for Assessment Project Intake by Rep (open case activity)
   *
   * @return array
   * @access public
   */
  public function getAssessRepActivityTypeId() {
    return $this->_assessRepActivityTypeId;
  }

  /**
   * Getter for Assessment Project Intake by CC
   *
   * @return array
   * @access public
   */
  public function getAssessCCActivityTypeId() {
    return $this->_assessCCActivityTypeId;
  }

  /**
   * Getter for Assessment Project Intake by SC
   *
   * @return array
   * @access public
   */
  public function getAssessSCActivityTypeId() {
    return $this->_assessSCActivityTypeId;
  }

  /**
   * Getter for Assessment Project Intake by SC
   *
   * @return array
   * @access public
   */
  public function getAssessPrOfActivityTypeId() {
    return $this->_assessPrOfActivityTypeId;
  }

  /**
   * Getter for Briefing Expert activity id
   *
   * @return int
   * @access public
   */
  public function getBriefingActivityTypeId() {
    return $this->_maBriefingActivityTypeId;
  }

  /**
   * Getter for Reject Main Activity Proposal activity id
   *
   * @return int
   * @access public
   */
  public function getMaRejectActivityTypeId() {
    return $this->_maRejectActivityTypeId;
  }

  /**
   * Getter for Accept Main Activity Proposal activity id
   *
   * @return int
   * @access public
   */
  public function getMaAcceptActivityTypeId() {
    return $this->_maAcceptActivityTypeId;
  }
  /**
   * Getter for Accept Main Activity Proposal Custom Group
   *
   * @param $key
   * @return array
   * @access public
   */
  public function getMaAcceptCustomGroup($key = null) {
    if (empty($key) || !isset($this->_maAcceptCustomGroup[$key])) {
      return $this->_maAcceptCustomGroup;
    } else {
      return $this->_maAcceptCustomGroup[$key];
    }
  }

  /**
   * Method to set arrays for custom groups with custom fields
   * @param $customGroups
   * @throws Exception when unable to find custom groups
   * @access private
   */
  private function setCustomGroups($customGroups) {
    foreach ($customGroups as $property => $customGroupName) {
      try {
        $customGroup = civicrm_api3('CustomGroup', 'Getsingle', array('name' => $customGroupName));
        try {
          $customFields = civicrm_api3('CustomField', 'Get', array('custom_group_id' => $customGroup['id']));
          $customGroup['custom_fields'] = $customFields['values'];
          $this->$property = $customGroup;
        } catch (CiviCRM_API3_Exception $ex) {}
      } catch (CiviCRM_API3_Exception $ex) {
        throw new Exception('Could not find a custom group with name '.$customGroupName
          .', error from API CustomGroup Getsingle: '.$ex->getMessage());
      }
    }
  }

  /**
   * Method to set id's for activity types
   *
   * @param array $activityTypes holding property names and related activity type names
   * @throws Exception when unable to find option group or option value
   * @access private
   */
  private function setActivityTypes($activityTypes) {
    try {
      $optionGroupId = civicrm_api3('OptionGroup', 'Getvalue', array('name' => 'activity_type', 'return' => 'id'));
      foreach ($activityTypes as $property => $activityTypeName) {
        $optionValueParams = array(
          'option_group_id' => $optionGroupId,
          'name' => $activityTypeName,
          'return' => 'value'
        );
        try {
          $activityTypeId = civicrm_api3('OptionValue', 'Getvalue', $optionValueParams);
          $this->$property = $activityTypeId;
        } catch (CiviCRM_API3_Exception $ex) {
          throw new Exception('Could not find activity type with name '.$activityTypeName
            .', error from API OptionValue Getvalue: '.$ex->getMessage());
        }
      }
    } catch (CiviCRM_API3_Exception $ex) {
      throw new Exception('Could not find option group with name activity_type,
        contact your system administrator. Error from API OptionGroup Getvalue: '.$ex->getMessage());
    }
  }

  /**
   * Method to get the table and column names of the custom fields for Assessement ProjectIntake
   *
   * @access private
   */
  private function setAssessIntakeCustomData() {
    $customGroupNames = array(
      '_assessSCCustomTable' => array(
        'group_name' => 'Intake_Customer_by_SC',
        'field_property' => '_assessSCCustomColumn',
        'field_name' => 'Conclusion_Do_you_want_to_approve_this_customer_'),
      '_assessRepCustomTable' => array(
        'group_name' => 'Intake',
        'field_property' => '_assessRepCustomColumn',
        'field_name' => 'Assessment_Rep'),
      '_assessCCCustomTable' => array(
        'group_name' => 'Intake_Customer_by_CC',
        'field_property' => '_assessCCCustomColumn',
        'field_name' => 'Conclusion_Do_you_want_to_approve_this_customer_'),
      '_assessAnamonCustomTable' => array(
        'group_name' => 'Intake_Customer_by_Anamon',
        'field_property' => '_assessAnamonCustomColumn',
        'field_name' => 'Do_you_approve_the_project_')
    );
    try {
      foreach ($customGroupNames as $tableProperty => $element) {
        $customGroup = civicrm_api3('CustomGroup', 'Getsingle', array('name' => $element['group_name']));
        $this->$tableProperty = $customGroup['table_name'];
        if ($tableProperty == "_assessRepCustomTable") {
          $this->_assessRepCustomGroupId = $customGroup['id'];
        }
        $fieldProperty = $element['field_property'];
        $this->$fieldProperty = civicrm_api3('CustomField', 'Getvalue', array(
          'custom_group_id' => $customGroup['id'],
          'name' => $element['field_name'],
          'return' => 'column_name'
        ));
      }
    } catch (CiviCRM_API3_Exception $ex) {}
  }

  /**
   * Function to return singleton object
   *
   * @return object $_singleton
   * @access public
   * @static
   */
  public static function &singleton() {
    if (self::$_singleton === NULL) {
      self::$_singleton = new CRM_Casereports_Config();
    }
    return self::$_singleton;
  }
}