CREATE VIEW infolog AS
SELECT info_id,info_type,info_subject,info_des,
  owner.account_lid AS info_owner,
  info_responsible, /* ToDo */
  info_access,
  egw_categories.cat_name,
  from_unixtime(info_datemodified) AS info_datemodified,
  (CASE info_startdate WHEN 0 THEN NULL ELSE from_unixtime(info_startdate) END) AS info_startdate,
  (CASE info_enddate WHEN 0 THEN NULL ELSE from_unixtime(info_enddate) END) AS info_enddate,
  info_id_parent, /* ToDo */
  info_planned_time,info_replanned_time,info_used_time,
  info_status,
  modifier.account_lid AS info_modifier,
  info_percent,
  (CASE info_datecompleted WHEN 0 THEN NULL ELSE from_unixtime(info_datecompleted) END) AS info_datecompleted,
  info_location,info_uid,info_cc,
  from_unixtime(info_created) AS info_created,
  creator.account_lid AS info_creator
FROM `egw_infolog` 
JOIN egw_accounts owner ON abs(info_owner)=owner.account_id
JOIN egw_accounts creator ON info_creator=creator.account_id
LEFT JOIN egw_categories ON info_cat=cat_id
LEFT JOIN egw_accounts modifier ON info_modifier=modifier.account_id