<?php

/**
 * Class CRM_Casereports_Form_Report_ExpertApplications for PUM report ExpertApplications
 *
 * @author Erik Hommel (CiviCooP) <erik.hommel@civicoop.org>
 * @date 5 Apr 2016
 * @license AGPL-3.0
 */

class CRM_Casereports_Form_Report_ExpertApplications extends CRM_Report_Form {

  protected $_summary = NULL;
  protected $_add2groupSupported = FALSE;
  protected $_customGroupExtends = array();
  protected $_userSelectList = array();
  protected $_caseStatusList = array();
  protected $_sectorList = array();
  protected $_deletedLabels = array();

  /**
   * Constructor method
   */
  function __construct() {
    $this->_caseStatusList = CRM_Case_PseudoConstant::caseStatus();
    $this->setUserSelectList();
    $this->setSectorList();

    $this->_deletedLabels = array('' => ts('- select -'), 0 => ts('No'), 1 => ts('Yes'));

    $this->_columns = array(
      'pum_expert' =>
        array(
          'fields' =>
            array(
              'case_id' =>
                array(
                  'no_display' => TRUE,
                  'required' => TRUE,
                ),
              'expert_name' =>
                array(
                  'name' => 'expert_name',
                  'title' => ts('Expert'),
                  'required' => TRUE,
                ),
              'sector_coordinator_name' =>
                array(
                  'name' => 'sector_coordinator_name',
                  'title' => ts('Sector Coordinator'),
                  'required' => TRUE
                ),
              /*'sector_id' =>
                array(
                  'name' => 'sector',
                  'title' => ts('Sector'),
                  'default' => TRUE,
                ),*/
              'status' =>
                array(
                  'name' => 'status',
                  'title' => ts('Status'),
                  'default' => TRUE,
                ),
              'sector_coordinator_id' =>
                array(
                  'no_display' => TRUE,
                  'required' => TRUE
                ),
              'expert_id' =>
                array(
                  'no_display' => TRUE,
                  'required' => TRUE
                ),
            ),
          'filters' => array(
            'user_id' => array(
              'title' => ts('Expert Applications for User'),
              'default' => 0,
              'pseudofield' => 1,
              'type' => CRM_Utils_Type::T_INT,
              'operatorType' => CRM_Report_Form::OP_SELECT,
              'options' => $this->_userSelectList,
            ),
            'status_id' => array(
              'title' => ts('Status'),
              'operatorType' => CRM_Report_Form::OP_MULTISELECT,
              'options' => $this->_caseStatusList,
            ),
            /*'sector_id' => array(
              'title' => ts('Sector'),
              'operatorType' => CRM_Report_Form::OP_MULTISELECT,
              'options' => $this->_sectorList,
            ),*/
          ),
          'order_bys' =>
            array(
              'status' =>
                array(
                  'title' => ts('Case Status'),
                  'name' => 'status',
                  'default' => 1,
                ),
            ),
        ),
    );

    parent::__construct();
  }

  /**
   * Overridden parent method to build select part of query
   */
  function select() {
    $select = array();
    $this->_columnHeaders = array();
    $this->_columnHeaders['pum_expert_case_id']['type'] = null;
    foreach ($this->_columns as $tableName => $table) {
      if (array_key_exists('fields', $table)) {
        foreach ($table['fields'] as $fieldName => $field) {
          if ($fieldName != 'case_id') {
            if (CRM_Utils_Array::value('required', $field) ||
              CRM_Utils_Array::value($fieldName, $this->_params['fields'])
            ) {
              $select[] = "{$field['dbAlias']} AS {$tableName}_{$fieldName}";
              $this->_columnHeaders["{$tableName}_{$fieldName}"]['type'] = CRM_Utils_Array::value('type', $field);
              if (isset($field['title'])) {
                $this->_columnHeaders["{$tableName}_{$fieldName}"]['title'] = $field['title'];
              }
            }
          }
        }
      }
    }
    $this->_select = "SELECT DISTINCT(".$this->_aliases['pum_expert'].".case_id) AS pum_expert_case_id, " . implode(', ', $select) . " ";
  }

  /**
   * Overridden parent method to build from part of query
   */

  function from() {
    $this->_from = "FROM pum_expert_applications ".$this->_aliases['pum_expert'];
  }

  /**
   * Overridden parent method to build where clause
   */
  function where() {
    $clauses = array();
    $this->_having = '';
    foreach ($this->_columns as $tableName => $table) {
      if (array_key_exists('filters', $table)) {
        foreach ($table['filters'] as $fieldName => $field) {
          $clause = NULL;
          if (CRM_Utils_Array::value("operatorType", $field) & CRM_Report_Form::OP_DATE) {
            $relative = CRM_Utils_Array::value("{$fieldName}_relative", $this->_params);
            $from     = CRM_Utils_Array::value("{$fieldName}_from", $this->_params);
            $to       = CRM_Utils_Array::value("{$fieldName}_to", $this->_params);

            $clause = $this->dateClause($field['dbAlias'], $relative, $from, $to,
              CRM_Utils_Array::value('type', $field)
            );
          } else {
            $op = CRM_Utils_Array::value("{$fieldName}_op", $this->_params);
            if ($fieldName == 'user_id') {
              $value = $this->setUserClause();
              if (!empty($value)) {
                $clause = "({$this->_aliases['pum_expert']}.case_manager_id = {$value} 
                  OR {$this->_aliases['pum_expert']}.recruitment_team_id = {$value})";
              }
              $op = NULL;
            }

            if ($op) {
              $clause = $this->whereClause($field,
                $op,
                CRM_Utils_Array::value("{$fieldName}_value", $this->_params),
                CRM_Utils_Array::value("{$fieldName}_min", $this->_params),
                CRM_Utils_Array::value("{$fieldName}_max", $this->_params)
              );
            }
          }

          if (!empty($clause)) {
            $clauses[] = $clause;
          }
        }
      }
    }
    if (empty($clauses)) {
      $this->_where = "WHERE ( 1 ) ";
    } else {
      $this->_where = "WHERE " . implode(' AND ', $clauses);
    }
  }

  /**
   * Overridden parent method to set the column headers
   */
  function modifyColumnHeaders() {
    $this->_columnHeaders['manage_case'] = array('title' => '','type' => CRM_Utils_Type::T_STRING,);
  }

  /**
   * Overridden parent method to process criteria into report with data
   */
  function postProcess() {

    $this->beginPostProcess();

    $sql = $this->buildQuery(TRUE);

    $rows = $graphRows = array();
    $this->buildRows($sql, $rows);

    $this->formatDisplay($rows);
    $this->doTemplateAssignment($rows);
    $this->endPostProcess($rows);
  }

  /**
   * Overridden parent method to alter the display of each row
   * @param array $rows
   */
  function alterDisplay(&$rows) {

    foreach ($rows as $rowNum => $row) {
      // build manage case url
      if (array_key_exists('pum_expert_case_id', $row) && array_key_exists('pum_expert_expert_id', $row)) {
        $caseUrl = CRM_Utils_System::url("civicrm/contact/view/case", 'reset=1&action=view&cid='
          . $row['pum_expert_expert_id'] . '&id=' . $row['pum_expert_case_id'], $this->_absoluteUrl);
        $rows[$rowNum]['manage_case'] = ts('Manage');
        $rows[$rowNum]['manage_case_link'] = $caseUrl;
        $rows[$rowNum]['manage_case_hover'] = ts("Manage Case");
      }

      if (CRM_Utils_Array::value('pum_expert_expert_id', $rows[$rowNum])) {
        $url = CRM_Utils_System::url("civicrm/contact/view" , "action=view&reset=1&cid=". $row['pum_expert_expert_id'], $this->_absoluteUrl);
        $rows[$rowNum]['pum_expert_expert_name_link'] = $url;
        $rows[$rowNum]['pum_expert_expert_name_hover'] = ts("View Expert");
      }

      if (CRM_Utils_Array::value('pum_expert_sector_coordinator_id', $rows[$rowNum])) {
        $url = CRM_Utils_System::url("civicrm/contact/view" , "action=view&reset=1&cid=". $row['pum_expert_sector_coordinator_id'], $this->_absoluteUrl);
        $rows[$rowNum]['pum_expert_sector_coordinator_name_link'] = $url;
        $rows[$rowNum]['pum_expert_sector_coordinator_name_hover'] = ts("View Sector Coordinator");
      }
    }
  }

  /**
   * Method to get the users list for the user filter
   *
   * @access private
   */
  private function setUserSelectList() {
    $allContacts = $this->getGroupMembers('Sector_Coordinators_55') + $this->getGroupMembers('Recruitment_Team_13');
    $sortedContacts = array();
    foreach ($allContacts as $contact) {
      $sortedContacts[$contact] = CRM_Threepeas_Utils::getContactName($contact);
    }
    asort($sortedContacts);
    $this->_userSelectList = array(0 => 'current user') + $sortedContacts;
  }

  /**
   * Overridden parent method to set the found rows on distinct case_id
   */
  function setPager($rowCount = self::ROW_COUNT_LIMIT) {
    if ($this->_limit && ($this->_limit != '')) {
      $sql              = "SELECT COUNT(DISTINCT({$this->_aliases['pum_expert']}.case_id)) ".$this->_from." ".$this->_where;
      $params           = array(
        'total' => $this->_rowsFound,
        'rowCount' => $rowCount,
        'status' => ts('Records') . ' %%StatusMessage%%',
        'buttonBottom' => 'PagerBottomButton',
        'buttonTop' => 'PagerTopButton',
        'pageID' => $this->get(CRM_Utils_Pager::PAGE_ID),
      );
      $pager = new CRM_Utils_Pager($params);
      $this->assign_by_ref('pager', $pager);
    }
  }

  /**
   * Method to get sector coordinators
   *
   * @return array
   */
  private function getGroupMembers($groupName) {
    $result = array();
    try {
      $groupId = civicrm_api3('Group', 'Getvalue', array('name' => $groupName, 'return' => 'id'));
      $groupContactParams = array('group_id' => $groupId, 'options' => array('limit' => 9999));
      try {
        $groupMembers = civicrm_api3('GroupContact', 'Get', $groupContactParams);
        foreach ($groupMembers['values'] as $groupMember) {
          $result[$groupMember['contact_id']] = $groupMember['contact_id'];
        }
      } catch (CiviCRM_API3_Exception $ex) {}
    } catch (CiviCRM_API3_Exception $ex) {}
    return $result;
  }

  /**
   * Method to get the list of sectors
   *
   * @return array
   */
  private function setSectorList() {
    $this->_sectorList = array();
    try {
      $sectors = civicrm_api3('Segment', 'Get', array('parent_id' => 'null'));
      foreach ($sectors['values'] as $sector) {
        $this->_sectorList[$sector['id']] = $sector['label'];

      }
    } catch (CiviCRM_API3_Exception $ex) {}
    asort($this->_sectorList);
  }

  /**
   * Overridden parent method to build the report rows
   *
   * @param string $sql
   * @param array $rows
   * @access public
   */
  function buildRows($sql, &$rows) {
    $rows = array();
    $dao = CRM_Core_DAO::executeQuery($sql);
    $this->modifyColumnHeaders();
    while ($dao->fetch()) {
      $row = array();
      foreach ($this->_columnHeaders as $key => $value) {
        if (property_exists($dao, $key)) {
          $row[$key] = $dao->$key;
        }
      }
      $rows[] = $row;
    }
  }

  /**
   * Method to add the user clause for where
   */
  private function setUserClause() {
    if (!isset($this->_params['user_id_value']) || empty($this->_params['user_id_value'])) {
      $session = CRM_Core_Session::singleton();
      $userId = $session->get('userID');
    } else {
      $userId = $this->_params['user_id_value'];
    }
    return $userId;
  }

  /**
   * Overridden parent method orderBy
   */
  function orderBy() {
    $this->_orderBy  = "";
    $this->_sections = array();
    $this->storeOrderByArray();
    foreach ($this->_orderByArray as $arrayKey => $arrayValue) {
      if ($arrayValue == "rt_civireport.status ASC") {
        $this->_orderByArray[$arrayKey] = "rt_civireport.weight";
      }
    }
    if(!empty($this->_orderByArray) && !$this->_rollup == 'WITH ROLLUP'){
      $this->_orderBy = "ORDER BY " . implode(', ', $this->_orderByArray);
    }
    $this->assign('sections', $this->_sections);
  }
}
