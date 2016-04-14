<?php

class CRM_Casereports_Form_Report_Opportunities extends CRM_Report_Form {

  protected $_addressField = FALSE;
  protected $_emailField = FALSE;
  protected $_summary = NULL;
  protected $_customGroupExtends = array('');
  protected $_customGroupGroupBy = FALSE;
  protected $_opportunityCaseTypeId = NULL;
  protected $_userSelectList = array();
  protected $_caseStatusList = array();
  protected $_deletedLabelsList = array();

  /**
   * CRM_Casereports_Form_Report_Opportunities constructor.
   */
  function __construct() {
    $this->setUserSelectList();
    $this->_caseStatusList = CRM_Case_PseudoConstant::caseStatus();

    $this->_columns = array(
      'pum_opportunity' => array(
        'alias' => 'opp',
        'fields' => array(
          'case_id' => array(
            'required' => TRUE,
            'no_display' => TRUE,
            'no_repeat' => TRUE
          ),
          'status_id' => array(
            'no_display' => TRUE,
            'required' => TRUE,
          ),
          'client_id' => array(
            'no_display' => TRUE,
            'required' => TRUE,
          ),
          'account_id' => array(
            'no_display' => TRUE,
            'required' => TRUE,
          ),
          'client_name' => array(
            'title' => ts("Client"),
            'default' => TRUE
          ),
          'account_name' => array(
            'title' => ts("Case Manager"),
            'default' => TRUE
          ),
          'subject' => array(
            'title' => ts('Subject'),
            'no_repeat' => TRUE,
            'default' => TRUE,
          ),
          'status' => array(
            'title' => ts('Status'),
            'no_repeat' => TRUE,
            'default' => TRUE,
          ),
          'deadline' => array(
            'title' => ts('Deadline'),
            'no_repeat' => TRUE,
            'type' => CRM_Utils_Type::T_DATE,
            'default' => TRUE,
          ),
          'quote_amount' => array(
            'title' => ts('Quote Amount'),
            'no_repeat' => TRUE,
            'type' => CRM_Utils_Type::T_MONEY,
            'default' => TRUE,
          ),
        ),
        'filters' => array(
          'account_id' => array(
            'title' => ts('Opportunities for User'),
            'default' => 0,
            'pseudofield' => 1,
            'type' => CRM_Utils_Type::T_INT,
            'operatorType' => CRM_Report_Form::OP_MULTISELECT,
            'options' => $this->_userSelectList,
          ),
          'status_id' => array('title' => ts('Status'),
            'operatorType' => CRM_Report_Form::OP_MULTISELECT,
            'options' => $this->_caseStatusList,
          ),
        ),
          'order_bys' =>
            array(
              'case_status' =>
                array(
                  'title' => ts('Case Status'),
                  'name' => 'status',
                  'default' => 1,
                ),
            ),
        ),
    );
    $this->_groupFilter = FALSE;
    $this->_tagFilter = FALSE;
    parent::__construct();
  }

  /**
   * Overridden parent method before any form processing
   */

  function preProcess() {
    $this->assign('reportTitle', ts('Opportunities Report'));
    parent::preProcess();
  }

  /**
   * Method to build select part of query
   */
  function select() {
    $select = array();
    $this->_columnHeaders = array();
    foreach ($this->_columns as $tableName => $table) {
      if (array_key_exists('fields', $table)) {
        foreach ($table['fields'] as $fieldName => $field) {
          if (CRM_Utils_Array::value('required', $field) ||
            CRM_Utils_Array::value($fieldName, $this->_params['fields'])) {
            $select[] = "{$field['dbAlias']} as {$tableName}_{$fieldName}";
            $this->_columnHeaders["{$tableName}_{$fieldName}"]['type'] = CRM_Utils_Array::value('type', $field);
            if (isset($field['title'])) {
              $this->_columnHeaders["{$tableName}_{$fieldName}"]['title'] = $field['title'];
            }
          }
        }
      }
    }
    $this->_select = "SELECT " . implode(', ', $select) . " ";
  }

  /**
   * Method to build form part of query
   */
  function from() {
    $this->_from = "FROM pum_opportunity {$this->_aliases['pum_opportunity']}";
  }

  /**
   * Method to build where part of query
   */
  function where() {
    $clauses = array();
    foreach ($this->_columns as $tableName => $table) {
      if (array_key_exists('filters', $table)) {
        foreach ($table['filters'] as $fieldName => $field) {
          $clause = NULL;
          $op = CRM_Utils_Array::value("{$fieldName}_op", $this->_params);
          // if user id value contains 0 for current user, replace value with current user
          if ($fieldName == 'account_id') {
            foreach ($this->_params['account_id_value'] as $paramKey => $userIdValue) {
              if ($userIdValue == 0) {
                $session = CRM_Core_Session::singleton();
                $this->_params['account_id_value'][$paramKey] = $session->get('userID');
              }
            }
          }
          $clause = $this->whereClause($field, $op, CRM_Utils_Array::value("{$fieldName}_value", $this->_params),
            CRM_Utils_Array::value("{$fieldName}_min", $this->_params),
            CRM_Utils_Array::value("{$fieldName}_max", $this->_params));
          if (!empty($clause)) {
            $clauses[] = $clause;
          }
        }
      }
    }
    if (empty($clauses)) {
      $this->_where = "";
    } else {
      $this->_where = "WHERE " . implode(' AND ', $clauses);
    }
  }

  /**
   * Overridden parent method to process report request and build data
   */
  function postProcess() {

    $this->beginPostProcess();
    $sql = $this->buildQuery(TRUE);

    $rows = array();
    $this->buildRows($sql, $rows);

    $this->formatDisplay($rows);
    $this->doTemplateAssignment($rows);
    $this->endPostProcess($rows);
  }

  /**
   * Overridden parent method to alter the rows before the display
   * @param $rows
   */
  function alterDisplay(&$rows) {
    // custom code to alter rows
    $entryFound = FALSE;
    foreach ($rows as $rowNum => $row) {
      if (array_key_exists('pum_opportunity_client_name', $row) && $rows[$rowNum]['pum_opportunity_client_name'] &&
        array_key_exists('pum_opportunity_client_id', $row)
      ) {
        $url = CRM_Utils_System::url("civicrm/contact/view",
          'reset=1&cid=' . $row['pum_opportunity_client_id'],
          $this->_absoluteUrl
        );
        $rows[$rowNum]['pum_opportunity_client_name_link'] = $url;
        $rows[$rowNum]['pum_opportunity_client_name_hover'] = ts("View Contact Summary for this Client.");
        $entryFound = TRUE;
      }

      if (array_key_exists('pum_opportunity_account_name', $row) && $rows[$rowNum]['pum_opportunity_account_name'] &&
        array_key_exists('pum_opportunity_account_id', $row)
      ) {
        $url = CRM_Utils_System::url("civicrm/contact/view",
          'reset=1&cid=' . $row['pum_opportunity_account_id'],
          $this->_absoluteUrl
        );
        $rows[$rowNum]['pum_opportunity_account_name_link'] = $url;
        $rows[$rowNum]['pum_opportunity_account_name_hover'] = ts("View Contact Summary for this Case Manager.");
        $entryFound = TRUE;
      }

      if (array_key_exists('pum_opportunity_subject', $row) && $rows[$rowNum]['pum_opportunity_subject'] &&
        array_key_exists('pum_opportunity_case_id', $row) && array_key_exists('pum_opportunity_client_id', $row)) {
        // build manage case url
        $caseUrl = CRM_Utils_System::url("civicrm/contact/view/case", 'reset=1&action=view&cid='
          . $row['pum_opportunity_client_id'] . '&id=' . $row['pum_opportunity_case_id'], $this->_absoluteUrl);
        $rows[$rowNum]['pum_opportunity_subject_link'] = $caseUrl;
        $rows[$rowNum]['pum_opportunity_subject_hover'] = ts("Manage Main Activity");
        $entryFound = TRUE;
      }

      if (!$entryFound) {
        break;
      }
    }
  }

  /**
   * Method to get the users list for the user filter
   *
   * @access private
   */
  private function setUserSelectList() {
    $result = array();
    $roleId = db_query("SELECT rid FROM {role} where (name = 'Business Development')")
      ->fetchField();
    $userQuery = db_select('users_roles', 'ur')
      ->condition('ur.rid', $roleId, '=')
      ->fields('ur', array('uid'));
    $users = $userQuery->execute();
    foreach ($users as $user) {
      try {
        $contactId = (int) civicrm_api3('UFMatch', 'Getvalue', array('uf_id' => $user->uid, 'return' => 'contact_id'));
        try {
      $contactName = (string) civicrm_api3('Contact', 'Getvalue', array('id' => $contactId, 'return' => 'display_name'));
          $result[$contactId] = $contactName;
        } catch (CiviCRM_API3_Exception $ex) {}
      } catch (CiviCRM_API3_Exception $ex) {}
    }
    asort($result);
    $this->_userSelectList = array(0 => 'current user') + $result;
  }
}
