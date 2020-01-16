<?php

/**
 * Class CRM_Casereports_Form_Report_PumProjects for PUM report Projects
 *
 * @author Erik Hommel (CiviCooP) <erik.hommel@civicoop.org>
 * @date 2 June 2016
 * @license AGPL-3.0

 */
class CRM_Casereports_Form_Report_PumProjects extends CRM_Report_Form {

  protected $_summary = NULL;
  protected $_add2groupSupported = FALSE;
  protected $_customGroupExtends = array();
  protected $_userSelectList = array();
  protected $_countrySelectList = array();
  protected $_customerSelectList = array();
  protected $_userId = NULL;

  /**
   * Constructor method
   */
  function __construct() {
    $this->setReportUserContext();
    $this->setUserSelectList();
    $this->setCountrySelectList();
    $this->setCustomerSelectList();

    $this->_columns = array(
      'project' => array(
        'alias' => 'project',
        'fields' => array(
          'project_id' => array(
            'no_display' => TRUE,
            'required' => TRUE,
            ),
          'project_name' => array(
            'title' => ts('Project Name'),
            'required' => TRUE,
            ),
          'start_date' => array(
            'title' => ts('Start Date'),
            'default' => TRUE,
            ),
          'end_date' => array(
           'title' => ts('End Date'),
            'default' => TRUE,
            ),
          'projectmanager_id' => array(
            'no_display' => TRUE,
            'required' => TRUE,
            ),
          'projectmanager_name' => array(
            'title' => ts('Project Manager'),
            'default' => TRUE,
          ),
          'programme_id' => array(
            'no_display' => TRUE,
            'required' => TRUE,
            ),
          'programme_name' => array(
            'title' => ts('Programme'),
            'default' => TRUE,
          ),
          'country_id' => array(
            'no_display' => TRUE,
            'required' => TRUE,
            ),
          'customer_id' => array(
            'no_display' => TRUE,
            'required' => TRUE,
            ),
          'customer_name' => array(
            'title' => ts('Customer or Country'),
            'default' => TRUE,
          ),
          'country_name' => array(
            'no_display' => TRUE,
            'required' => TRUE,
          ),
          'country_coordinator_id' => array(
            'no_display' => TRUE,
            'required' => TRUE,
          ),
          'project_officer_id' => array(
            'no_display' => TRUE,
            'required' => TRUE,
          ),
          'sector_coordinator_id' => array(
            'no_display' => TRUE,
            'required' => TRUE,
          ),
          'programme_manager_id' => array(
            'no_display' => TRUE,
            'required' => TRUE,
          ),
        ),
        'filters' => array(
          'user_id' => array(
            'title' => ts('Projects for user'),
            'default' => 1,
            'pseudofield' => 1,
            'type' => CRM_Utils_Type::T_INT,
            'operatorType' => CRM_Report_Form::OP_SELECT,
            'options' => $this->_userSelectList,
          ),
          'project_name' => array(
            'title' => ts('Project'),
            'type' => CRM_Utils_Type::T_STRING,
            'operator' => 'like',
          ),
          'country_id' => array(
            'title' => ts('Country'),
            'operatorType' => CRM_Report_Form::OP_MULTISELECT,
            'options' => $this->_countrySelectList,
          ),
          'start_date' => array(
            'title' => ts('Start Date'),
            'operatorType' => CRM_Report_Form::OP_DATE,
            'type' => CRM_Utils_Type::T_DATE,
          ),
          'end_date' => array(
            'title' => ts('End Date'),
            'operatorType' => CRM_Report_Form::OP_DATE,
            'type' => CRM_Utils_Type::T_DATE,
          ),
          'customer_id' => array(
            'title' => ts('Customer'),
            'operatorType' => CRM_Report_Form::OP_MULTISELECT,
            'options' => $this->_customerSelectList,
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
    $this->_from = "FROM pum_projects_view {$this->_aliases['project']}";
  }

  /**
   * Overridden parent method to build where clause
   */
  function where() {
    $clauses = array();
    // issue 2847 only select if end date is empty or later than or today
    $nowDate = date('Y-m-d');
    $clauses[] = "({$this->_aliases['project']}.end_date IS NULL OR {$this->_aliases['project']}.end_date >= '{$nowDate}')";
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
                $pum = $this->_aliases['project'];
                $clause = "({$pum}.programme_manager_id = {$value}
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
    $this->_columnHeaders['my_role'] = array('title' => 'My Role','type' => CRM_Utils_Type::T_STRING,);
    $this->_columnHeaders['drill_down'] = array('title' => '','type' => CRM_Utils_Type::T_STRING,);
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
      if (array_key_exists('project_project_id', $row)) {
        $drillUrl = CRM_Utils_System::url('civicrm/pumdrill', 'pumEntity=project&pid='.$row['project_project_id'], $this->_absoluteUrl);
        $rows[$rowNum]['drill_down'] = 'Drill Down';
        $rows[$rowNum]['drill_down_link'] = $drillUrl;
        $rows[$rowNum]['drill_down_hover'] = ts("Drill Down Project");
      }

      $myRoles = $this->setMyRole($row);
      $rows[$rowNum]['my_role'] = implode('; ', $myRoles);

      if (array_key_exists('project_start_date', $row) && (!empty($row['project_start_date']))) {
        $rows[$rowNum]['project_start_date'] = date('j F Y', strtotime($row['project_start_date']));
      }

      if (array_key_exists('project_end_date', $row) && (!empty($row['project_end_date']))) {
        $rows[$rowNum]['project_end_date'] = date('j F Y', strtotime($row['project_end_date']));
      }

      if (array_key_exists('project_project_name', $row)) {
        $projectUrl = CRM_Utils_System::url('civicrm/pumproject', 'action=view&pid='.$row['project_project_id'], $this->_absoluteUrl);
        $rows[$rowNum]['project_project_name_link'] = $projectUrl;
        $rows[$rowNum]['project_project_name_hover'] = ts('View Project');
      }

      if (array_key_exists('project_programme_name', $row)) {
        $programmeUrl = CRM_Utils_System::url('civicrm/pumprogramme', 'action=view&pid='.$row['project_programme_id'], $this->_absoluteUrl);
        $rows[$rowNum]['project_programme_name_link'] = $programmeUrl;
        $rows[$rowNum]['project_programme_name_hover'] = ts('View Programme');
      }

      if (CRM_Utils_Array::value('project_projectmanager_name', $rows[$rowNum])) {
        $projectManagerurl = CRM_Utils_System::url("civicrm/contact/view" , "action=view&reset=1&cid=". $row['project_projectmanager_id'], $this->_absoluteUrl);
        $rows[$rowNum]['project_projectmanager_name_link'] = $projectManagerurl;
        $rows[$rowNum]['project_projectmanager_name_hover'] = ts("View Project Manager");
      }

      if (CRM_Utils_Array::value('project_country_id', $rows[$rowNum]) && !empty($row['project_country_id'])) {
        $countryUrl = CRM_Utils_System::url("civicrm/contact/view" , "action=view&reset=1&cid=". $row['project_country_id'], $this->_absoluteUrl);
        $rows[$rowNum]['project_customer_name'] = $row['project_country_name'];
        $rows[$rowNum]['project_customer_name_link'] = $countryUrl;
        $rows[$rowNum]['project_customer_name_hover'] = ts("View Country");
      }

      if (CRM_Utils_Array::value('project_customer_id', $rows[$rowNum]) && !empty($row['project_customer_id'])) {
        $customerUrl = CRM_Utils_System::url("civicrm/contact/view" , "action=view&reset=1&cid=". $row['project_customer_id'], $this->_absoluteUrl);
        $rows[$rowNum]['project_customer_name_link'] = $customerUrl;
        $rows[$rowNum]['project_customer_name_hover'] = ts("View Customer");
      }
    }
  }

  /**
   * Overridden parent method to set the found rows on distinct case_id
   */
  function setPager($rowCount = self::ROW_COUNT_LIMIT) {
    if ($this->_limit && ($this->_limit != '')) {
      $sql              = "SELECT COUNT(DISTINCT({$this->_aliases['project']}.project_id)) ".$this->_from." ".$this->_where;
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
   * Method to get the country list for the user filter
   */
  private function setCountrySelectList() {
    $config = CRM_Threepeas_Config::singleton();
    $countryParams = array(
      'contact_sub_type' => $config->countryContactType,
      'contact_is_deleted' => 0,
      'options' => array('limit' => 0),
      'return' => 'display_name'
    );
    try {
      $countryContacts = civicrm_api3('Contact', 'Get', $countryParams);
      foreach ($countryContacts['values'] as $contactId => $contactValues) {
        $this->_countrySelectList[$contactId] = $contactValues['display_name'];
      }
    } catch (CiviCRM_API3_Exception $ex) {}
  }

  /**
   * Method to get the customer list for the user filter
   */
  private function setCustomerSelectList() {
    $config = CRM_Threepeas_Config::singleton();
    $customerParams = array(
      'contact_sub_type' => $config->customerContactType,
      'contact_is_deleted' => 0,
      'options' => array('limit' => 0),
      'return' => 'display_name'
    );
    try {
      $customerContacts = civicrm_api3('Contact', 'Get', $customerParams);
      foreach ($customerContacts['values'] as $contactId => $contactValues) {
        $this->_customerSelectList[$contactId] = $contactValues['display_name'];
      }
    } catch (CiviCRM_API3_Exception $ex) {}
  }

  /**
   * Method to set the roles of the selected user
   *
   * @param $row
   * @return array
   */
  private function setMyRole($row) {
    $myRoles = array();
    if ($row['project_country_coordinator_id'] == $this->_userId) {
      $myRoles[] = "Country Coordinator";
    }
    if ($row['project_project_officer_id'] == $this->_userId) {
      $myRoles[] = "Project Officer";
    }
    if ($row['project_sector_coordinator_id'] == $this->_userId) {
      $myRoles[] = "Sector Coordinator";
    }
    if ($row['project_projectmanager_id'] == $this->_userId) {
      $myRoles[] = "Project Manager";
    }
    if ($row['project_programme_manager_id'] == $this->_userId) {
      $myRoles[] = "Programme Manager";
    }
    return $myRoles;
  }

  /**
   * Overridden parent method orderBy (issue 2995 order by status on weight)
   */
  function orderBy() {
    $this->_orderBy  = "";
    $this->_orderByArray[] = $this->_aliases['project'].".project_id DESC";
    if(!empty($this->_orderByArray) && !$this->_rollup == 'WITH ROLLUP'){
      $this->_orderBy = "ORDER BY " . implode(', ', $this->_orderByArray);
    }
    $this->assign('sections', $this->_sections);
  }

  /**
   * Set report url as user context
   *
   */
  private function setReportUserContext() {
    $session = CRM_Core_Session::singleton();
    $instanceId = CRM_Core_DAO::singleValueQuery('SELECT id FROM civicrm_report_instance WHERE report_id = %1',
      array(1 => array('nl.pum.casereports/pumprojects', 'String')));
    if (!empty($instanceId)) {
      $session->pushUserContext(CRM_Utils_System::url('civicrm/report/instance/'.$instanceId, 'reset=1', true));
    } else {
      $session->pushUserContext(CRM_Utils_System::url('civicrm/dashboard/', 'reset=1', true));
    }
  }


}
