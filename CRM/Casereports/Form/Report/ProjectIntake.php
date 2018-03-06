<?php

/**
 * Class CRM_Casereports_Form_Report_ProjectIntake for PUM report Project Intake
 *
 * @author Erik Hommel (CiviCooP) <erik.hommel@civicoop.org>
 * @date 14 June 2016
 * @license AGPL-3.0

 */
class CRM_Casereports_Form_Report_ProjectIntake extends CRM_Report_Form {

  protected $_summary = NULL;
  protected $_add2groupSupported = FALSE;
  protected $_customGroupExtends = array();
  protected $_userSelectList = array();
  protected $_countrySelectList = array();
  protected $_caseStatusSelectList = array();
  protected $_userId = NULL;

  /**
   * Constructor method
   */
  function __construct() {
    $this->setUserSelectList();
    $this->setCountrySelectList();
    $this->setCaseStatusSelectList();

    $this->_columns = array(
      'projectintake' => array(
        'alias' => 'pi',
        'fields' => array(
          'case_id' => array(
            'no_display' => TRUE,
            'required' => TRUE,
            ),
          'customer_id' => array(
            'no_display' => TRUE,
            'required' => TRUE,
            ),
          'customer_name' => array(
            'title' => ts('Client'),
            'required' => TRUE,
            ),
          'customer_country_name' => array(
            'title' => ts('Client Country'),
            'default' => TRUE,
            ),
          'case_subject' => array(
           'title' => ts('Case Subject'),
            'default' => TRUE,
            ),
          'case_status' => array(
            'title' => ts('Case Status'),
            'default' => TRUE,
            ),
          'date_submission' => array(
            'title' => ts('Date Submission'),
            'required' => TRUE,
          ),
          'representative_id' => array(
            'no_display' => TRUE,
            'required' => TRUE,
            ),
          'representative_name' => array(
            'title' => ts('Representative'),
            'default' => TRUE,
          ),
          'assess_rep_date' => array(
            'title' => ts('Date Assessment Rep'),
            'default' => TRUE,
            ),
          'assess_rep_customer' => array(
            'title' => ts('Customer Approved by Rep'),
            'default' => TRUE,
            ),
          'assess_cc_date' => array(
            'title' => ts('Date Intake CC'),
            'default' => TRUE,
            ),
          'assess_cc_customer' => array(
            'title' => ts('Customer Approved by CC'),
            'default' => TRUE,
            ),
          'assess_sc_date' => array(
            'title' => ts('Date Intake SC'),
            'default' => TRUE,
            ),
          'assess_sc_customer' => array(
            'title' => ts('Customer Approved by SC'),
            'default' => TRUE,
            ),
          'assess_anamon_date' => array(
            'title' => ts('Date Intake Anamon'),
            'default' => TRUE,
            ),
          'assess_anamon_customer' => array(
            'title' => ts('Customer Approved by Anamon'),
            'default' => TRUE,
            ),
        ),
        'filters' => array(
          'user_id' => array(
            'title' => ts('Project Intake for user'),
            'default' => 1,
            'pseudofield' => 1,
            'type' => CRM_Utils_Type::T_INT,
            'operatorType' => CRM_Report_Form::OP_SELECT,
            'options' => $this->_userSelectList,
          ),
          'country_id' => array(
            'title' => ts('Country'),
            'operatorType' => CRM_Report_Form::OP_MULTISELECT,
            'options' => $this->_countrySelectList,
          ),
          'case_status_id' => array(
            'title' => ts('Case Status'),
            'operatorType' => CRM_Report_Form::OP_MULTISELECT,
            'options' => $this->_caseStatusSelectList,
          ),
          'date_submission' => array(
            'title' => ts('Date Submission'),
            'operatorType' => CRM_Report_Form::OP_DATE,
            'type' => CRM_Utils_Type::T_DATE,
          ),
        ),
        'order_bys' => array(
          'customer_name' => array(
            'title' => 'Customer',
          ),
          'customer_country_name' => array(
            'title' => 'Country',
          ),
          'date_submission' => array(
            'title' => 'Date Submission',
          ),
        ),
      ),
    );

    parent::__construct();
  }

  /**
   * Overridden parent method to build from part of query
   */

  function from() {
    $this->_from = "FROM `pum_project_intake_view` {$this->_aliases['projectintake']}";
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
              $this->setUserClause();
              $value = $this->_userId;
              if (!empty($value)) {
                $pum = $this->_aliases['projectintake'];
                $clause = "({$pum}.anamon_id = {$value} OR {$pum}.programme_manager_id = {$value}
                OR {$pum}.country_coordinator_id = {$value} OR {$pum}.project_officer_id = {$value}
                OR {$pum}.projectmanager_id = {$value} OR {$pum}.sector_coordinator_id = {$value})";
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
    $this->_columnHeaders['manage_case'] = array('title' => 'Manage','type' => CRM_Utils_Type::T_STRING,);
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
      // client clickable
      if (array_key_exists('projectintake_customer_name', $row) && !empty($row['projectintake_customer_id'])) {
        $customerUrl = CRM_Utils_System::url("civicrm/contact/view" , "action=view&reset=1&cid=". $row['projectintake_customer_id'],
          $this->_absoluteUrl);
        $rows[$rowNum]['projectintake_customer_name_link'] = $customerUrl;
        $rows[$rowNum]['projectintake_customer_name_hover'] = ts("View Client");
      }
      // representative clickable
      if (array_key_exists('projectintake_representative_name', $row) && !empty($row['projectintake_representative_id'])) {
        $repUrl = CRM_Utils_System::url("civicrm/contact/view" , "action=view&reset=1&cid=". $row['projectintake_representative_id'],
          $this->_absoluteUrl);
        $rows[$rowNum]['projectintake_representative_name_link'] = $repUrl;
        $rows[$rowNum]['projectintake_representative_hover'] = ts("View Representative");
      }
      // manage case link
      if (isset($row['projectintake_case_id'])) {
        $caseUrl = CRM_Utils_System::url("civicrm/contact/view/case", 'reset=1&action=view&cid=' . $row['projectintake_customer_id']
          . '&id=' . $row['projectintake_case_id'], $this->_absoluteUrl);
        $rows[$rowNum]['manage_case'] = ts('Manage');
        $rows[$rowNum]['manage_case_link'] = $caseUrl;
        $rows[$rowNum]['manage_case_hover'] = ts("Manage Case");
      }
      // date fields formatting
      if (isset($row['projectintake_date_submission']) && !empty($row['projectintake_date_submission'])) {
        $rows[$rowNum]['projectintake_date_submission'] = date('j F Y', strtotime($row['projectintake_date_submission']));
      }
      if (isset($row['projectintake_assess_rep_date']) && !empty($row['projectintake_assess_rep_date'])) {
        $rows[$rowNum]['projectintake_assess_rep_date'] = date('j F Y', strtotime($row['projectintake_assess_rep_date']));
      }
      if (isset($row['projectintake_assess_cc_date']) && !empty($row['projectintake_assess_cc_date'])) {
        $rows[$rowNum]['projectintake_assess_cc_date'] = date('j F Y', strtotime($row['projectintake_assess_cc_date']));
      }
      if (isset($row['projectintake_assess_sc_date']) && !empty($row['projectintake_assess_sc_date'])) {
        $rows[$rowNum]['projectintake_assess_sc_date'] = date('j F Y', strtotime($row['projectintake_assess_sc_date']));
      }
      if (isset($row['projectintake_assess_anamon_date']) && !empty($row['projectintake_assess_anamon_date'])) {
        $rows[$rowNum]['projectintake_assess_anamon_date'] = date('j F Y', strtotime($row['projectintake_assess_anamon_date']));
      }
    }
  }

  /**
   * Overridden parent method to set the found rows on distinct case_id
   */
  function setPager($rowCount = self::ROW_COUNT_LIMIT) {
    if ($this->_limit && ($this->_limit != '')) {
      $sql              = "SELECT COUNT(DISTINCT({$this->_aliases['projectintake']}.case_id)) ".$this->_from." ".$this->_where;
      $this->_rowsFound = CRM_Core_DAO::singleValueQuery($sql);
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
   * Method to add the user clause for where
   */
  private function setUserClause() {
    if (!isset($this->_params['user_id_value']) || empty($this->_params['user_id_value'])) {
      $session = CRM_Core_Session::singleton();
      $this->_userId = $session->get('userID');
    } else {
      $this->_userId = $this->_params['user_id_value'];
    }
  }

  /**
   * Method to get the users list for the user filter
   *
   * @access private
   */
  private function setUserSelectList() {
    if (method_exists('CRM_Groupsforreports_GroupReport', 'getGroupMembersForReport')) {
      $allContacts = CRM_Groupsforreports_GroupReport::getGroupMembersForReport(__CLASS__);
      $sortedContacts = array();
      foreach ($allContacts as $contact) {
        $sortedContacts[$contact] = CRM_Threepeas_Utils::getContactName($contact);
      }
      asort($sortedContacts);
      $this->_userSelectList = array(0 => 'current user') + $sortedContacts;
    }
  }

  /** 
   * Method to get the country list
   */
  private function setCountrySelectList() {
    $this->_countrySelectList = CRM_Core_PseudoConstant::country();
  }

  /** 
   * Method to get the case status list for the user filter
   */
  private function setCaseStatusSelectList() {
    try {
      $caseStatusOptions = civicrm_api3('OptionValue', 'Get', array('option_group_id' => 'case_status'));
      foreach ($caseStatusOptions['values'] as $caseStatusValues) {
        $this->_caseStatusSelectList[$caseStatusValues['value']] = $caseStatusValues['label'];
      }
    } catch (CiviCRM_API3_Exception $ex) {}
    asort($this->_caseStatusSelectList);
  }

  /**
   * Method to order the list by date
   */
  function orderBy() {
    $this->_orderBy  = "";
    $this->_orderByArray[] = $this->_aliases['projectintake'].".date_submission ASC";
    if(!empty($this->_orderByArray) && !$this->_rollup == 'WITH ROLLUP'){
      $this->_orderBy = "ORDER BY " . implode(', ', $this->_orderByArray);
    }
  }
}
