<?php

/**
 * Class CRM_Casereports_Form_Report_MainActivities for PUM report MainActivities
 *
 * @author Erik Hommel (CiviCooP) <erik.hommel@civicoop.org>
 * @date 1 Feb 2016
 * @license AGPL-3.0

 */
class CRM_Casereports_Form_Report_MainActivities extends CRM_Report_Form {

  protected $_summary = NULL;
  protected $_add2groupSupported = FALSE;
  protected $_customGroupExtends = array();
  protected $_userSelectList = array();
  protected $_caseTypes = array();
  protected $_caseStatus = array();
  protected $_deletedLabels = array();

  /**
   * Constructor method
   */
  function __construct() {
    $this->_caseTypes    = CRM_Case_PseudoConstant::caseType();
    $this->_caseStatus = CRM_Case_PseudoConstant::caseStatus();
    $this->setUserSelectList();

    $this->_deletedLabels = array('' => ts('- select -'), 0 => ts('No'), 1 => ts('Yes'));

    $this->_columns = array(
      'pum_main' =>
        array(
          'fields' =>
            array(
              'case_id' =>
                array(
                  'no_display' => TRUE,
                  'required' => TRUE,
                ),
              'customer_name' =>
                array(
                  'name' => 'customer_name',
                  'title' => ts('Client'),
                  'required' => TRUE,
                ),
              'customer_id' =>
                array(
                  'name' => 'customer_id',
                  'no_display' => TRUE,
                  'required' => TRUE
                ),
              'country_name' =>
                array(
                  'name' => 'country_name',
                  'title' => ts('Country'),
                  'default' => TRUE,
                ),
              'representative' =>
                array(
                  'name' => 'representative',
                  'title' => ts('Representative'),
                  'default' => TRUE,
                ),
              'representative_id' =>
                array(
                  'name' => 'representative_id',
                  'no_display' => TRUE,
                  'required' => TRUE
                ),
              'case_type' =>
                array(
                  'name' => 'case_type_id',
                  'title' => ts('Case Type'),
                  'default' => TRUE,
                ),
              'case_status' =>
                array(
                  'name' => 'case_status_id',
                  'title' => ts('Case Status'),
                  'default' => TRUE,
                ),
              'expert' =>
                array(
                  'name' => 'expert',
                  'title' => ts('Expert'),
                  'default' => TRUE,
                ),
              'expert_id' =>
                array(
                  'name' => 'expert_id',
                  'no_display' => TRUE,
                  'required' => TRUE
                ),
              'start_date' =>
                array(
                  'name' => 'start_date',
                  'title' => ts('Activity Start Date'),
                  'default' => TRUE,
                ),
              'end_date' =>
                array(
                  'name' => 'end_date',
                  'title' => ts('Activity End Date'),
                  'default' => TRUE,
                ),
              'ma_expert_approval' =>
                array(
                  'name' => 'ma_expert_approval',
                  'title' => ts('Expert approves Main. Act'),
                  'default' => TRUE
                ),
              'pq_approved_sc' =>
                array(
                  'name' => 'pq_approved_sc',
                  'title' => ts('PQ approved by SC'),
                  'default' => TRUE
                ),
              'pq_approved_cc' =>
                array(
                  'name' => 'pq_approved_cc',
                  'title' => ts('PQ approved by CC'),
                  'default' => TRUE
                ),
              'cust_approves_expert' =>
                array(
                  'name' => 'cust_approves_expert',
                  'title' => ts('Customer approves Expert'),
                  'default' => TRUE
                ),
              'briefing_date' =>
                array(
                  'name' => 'briefing_date',
                  'title' => ts('Briefing Date'),
                  'default' => TRUE
                ),
              'briefing_status' =>
                array(
                  'name' => 'briefing_status',
                  'title' => ts('Briefing Status'),
                  'default' => TRUE,
                ),
            ),
          'filters' => array(
            'user_id' => array(
              'title' => ts('Main Activities for User'),
              'default' => 0,
              'pseudofield' => 1,
              'type' => CRM_Utils_Type::T_INT,
              'operatorType' => CRM_Report_Form::OP_SELECT,
              'options' => $this->_userSelectList,
            ),
            'case_type_id' => array('title' => ts('Case Type'),
              'operatorType' => CRM_Report_Form::OP_MULTISELECT,
              'options' => $this->_caseTypes,
            ),
            'case_status_id' => array('title' => ts('Status'),
              'operatorType' => CRM_Report_Form::OP_MULTISELECT,
              'options' => $this->_caseStatus,
            ),
          ),
          'order_bys' =>
            array(
              'start_date' =>
                array(
                  'title' => ts('Activity Start Date'),
                  'name' => 'start_date',
                  'default' => 1,
                ),
            ),
        ),
      'case_status_weight' =>
        array(
          'dao' => 'CRM_Core_DAO_OptionValue',
          'fields' =>
            array(
              'case_status_label' =>
                array(
                  'name' => 'label',
                  'no_display' => TRUE,
                  'required' => TRUE,
                ),
              'weight' =>
                array(
                  'no_display' => TRUE,
                  'required' => TRUE,
                ),
            ),
          'order_bys' =>
            array(
              'case_status_label' =>
                array(
                  'title' => ts('Case Status'),
                  'name' => 'label',
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
   * Overridden parent method to build from part of query
   */

  function from() {
    $caseStatusOptionGroupId = civicrm_api3("OptionGroup", "getvalue",
      array('return' => "id", 'name' => "case_status"));
    $csw = $this->_aliases['case_status_weight'];
    $pum = $this->_aliases['pum_main'];

    $this->_from = "FROM pum_my_main_activities ".$pum;
    if ($this->isTableSelected('case_status_weight')) {
      $this->_from .= "
        LEFT JOIN civicrm_option_value {$csw} ON {$pum}.case_status_id = {$csw}.value AND {$csw}.option_group_id =
          {$caseStatusOptionGroupId} AND {$csw}.is_active = 1";
    }
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
            if ($fieldName == 'case_type_id') {
              $value = CRM_Utils_Array::value("{$fieldName}_value", $this->_params);
              if (!empty($value)) {
                $clause = "( {$field['dbAlias']} REGEXP '[[:<:]]" . implode('[[:>:]]|[[:<:]]', $value) . "[[:>:]]' )";
              }
              $op = NULL;
            }
            if ($fieldName == 'user_id') {
              $value = $this->setUserClause();
              if (!empty($value)) {
                $pum = $this->_aliases['pum_main'];
                $clause = "({$pum}.expert_id = {$value} OR {$pum}.representative_id = {$value}
                OR {$pum}.country_coordinator_id = {$value} OR {$pum}.project_officer_id = {$value}
                OR {$pum}.project_manager_id = {$value} OR {$pum}.sector_coordinator_id = {$value}
                OR {$pum}.counsellor_id = {$value})";
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
      if (array_key_exists('pum_main_case_id', $row) && array_key_exists('pum_main_customer_id', $row)) {
        $caseUrl = CRM_Utils_System::url("civicrm/contact/view/case", 'reset=1&action=view&cid='
          . $row['pum_main_customer_id'] . '&id=' . $row['pum_main_case_id'], $this->_absoluteUrl);
        $rows[$rowNum]['manage_case'] = ts('Manage');
        $rows[$rowNum]['manage_case_link'] = $caseUrl;
        $rows[$rowNum]['manage_case_hover'] = ts("Manage Case");
      }

      if (array_key_exists('pum_main_case_type', $row)) {
        $value   = $row['pum_main_case_type'];
        $typeIds = explode(CRM_Core_DAO::VALUE_SEPARATOR, $value);
        $value   = array();
        foreach ($typeIds as $typeId) {
          if ($typeId) {
            $value[$typeId] = $this->_caseTypes[$typeId];
          }
        }
        $rows[$rowNum]['pum_main_case_type'] = implode(', ', $value);
      }

      if (array_key_exists('pum_main_ma_expert_approval', $row) && empty($row['pum_main_ma_expert_approval'])) {
        $rows[$rowNum]['pum_main_ma_expert_approval'] = "n/a";
      }

      if (array_key_exists('pum_main_case_status', $row)) {
        $rows[$rowNum]['pum_main_case_status'] = $this->_caseStatus[$row['pum_main_case_status']];
      }

      if (array_key_exists('pum_main_start_date', $row) && (!empty($row['pum_main_start_date']))) {
        $rows[$rowNum]['pum_main_start_date'] = date('j F Y', strtotime($row['pum_main_start_date']));
      }

      if (array_key_exists('pum_main_end_date', $row) && (!empty($row['pum_main_end_date']))) {
        $rows[$rowNum]['pum_main_end_date'] = date('j F Y', strtotime($row['pum_main_end_date']));
      }

      if (array_key_exists('pum_main_briefing_date', $row) && (!empty($row['pum_main_briefing_date']))) {
        $rows[$rowNum]['pum_main_briefing_date'] = date('j F Y', strtotime($row['pum_main_briefing_date']));
      }

      if (CRM_Utils_Array::value('pum_main_expert', $rows[$rowNum])) {
        $url = CRM_Utils_System::url("civicrm/contact/view" , "action=view&reset=1&cid=". $row['pum_main_expert_id'], $this->_absoluteUrl);
        $rows[$rowNum]['pum_main_expert_link'] = $url;
        $rows[$rowNum]['pum_main_expert_hover'] = ts("View Expert");
      }

      if (CRM_Utils_Array::value('pum_main_representative', $rows[$rowNum])) {
        $url = CRM_Utils_System::url("civicrm/contact/view" , "action=view&reset=1&cid=". $row['pum_main_representative_id'], $this->_absoluteUrl);
        $rows[$rowNum]['pum_main_representative_link'] = $url;
        $rows[$rowNum]['pum_main_representative_hover'] = ts("View Representative");
      }

      if (CRM_Utils_Array::value('pum_main_customer_name', $rows[$rowNum])) {
        $url = CRM_Utils_System::url("civicrm/contact/view" , "action=view&reset=1&cid=". $row['pum_main_customer_id'], $this->_absoluteUrl);
        $rows[$rowNum]['pum_main_customer_name_link'] = $url;
        $rows[$rowNum]['pum_main_customer_name_hover'] = ts("View Customer");
      }
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
   * Overridden parent method to set the found rows on distinct case_id
   */
  function setPager($rowCount = self::ROW_COUNT_LIMIT) {
    if ($this->_limit && ($this->_limit != '')) {
      $sql              = "SELECT COUNT(DISTINCT({$this->_aliases['pum_main']}.case_id)) ".$this->_from." ".$this->_where;
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
   * Overridden parent method orderBy (issue 2995 order by status on weight)
   */
  function orderBy() {
    $this->_orderBy  = "";
    $this->_sections = array();
    $this->storeOrderByArray();
    foreach ($this->_orderByArray as $arrayKey => $arrayValue) {
      if ($arrayValue == "tus_weight_civireport.label ASC") {
        $this->_orderByArray[$arrayKey] = $this->_aliases['case_status_weight'].".weight";
      }
    }
    if(!empty($this->_orderByArray) && !$this->_rollup == 'WITH ROLLUP'){
      $this->_orderBy = "ORDER BY " . implode(', ', $this->_orderByArray);
    }
    $this->assign('sections', $this->_sections);
  }
}
