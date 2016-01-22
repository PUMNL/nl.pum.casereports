CREATE TABLE IF NOT EXISTS civicrm_pum_case_reports (
  case_id INT(10) UNSIGNED NOT NULL DEFAULT '0',
  ma_expert_approval TINYINT(4) DEFAULT NULL,
  pq_approved_cc VARCHAR(128) DEFAULT NULL,
  pq_approved_sc VARCHAR(128) DEFAULT NULL,
  briefing_status VARCHAR(128) DEFAULT NULL,
  briefing_date DATE DEFAULT NULL,
  PRIMARY KEY (case_id),
  UNIQUE KEY case_id_UNIQUE (case_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;
