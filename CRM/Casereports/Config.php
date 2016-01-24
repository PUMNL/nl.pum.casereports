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

  protected $_maAcceptActivityTypeId = NULL;
  protected $_maRejectActivityTypeId = NULL;
  protected $_maBriefingActivityTypeId = NULL;
  protected $_maAcceptCustomGroup = array();

  /**
   * CRM_Casereports_Config constructor.
   */
  function __construct() {
    $this->setActivityTypes(array(
      '_maAcceptActivityTypeId' => 'Accept Main Activity Proposal',
      '_maRejectActivityTypeId' => 'Reject Main Activity Proposal',
      '_maBriefingActivityTypeId' => 'Briefing Expert'
    ));
    $this->setCustomGroups(array(
     '_maAcceptCustomGroup' => 'Add_Keyqualifications'
    ));
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