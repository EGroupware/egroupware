<?php
  /**************************************************************************\
  * phpGroupWare                                                             *
  * http://www.phpgroupware.org                                              *
  * --------------------------------------------                             *
  *  This program is free software; you can redistribute it and/or modify it *
  *  under the terms of the GNU General Public License as published by the   *
  *  Free Software Foundation; either version 2 of the License, or (at your  *
  *  option) any later version.                                              *
  \**************************************************************************/

  /* $Id$ */

  $db->query('insert into config (config_name, config_value) values ('default_tplset', 'default');
  $db->query('insert into config (config_name, config_value) values ('temp_dir', '/path/to/tmp');
  $db->query('insert into config (config_name, config_value) values ('files_dir', '/path/to/dir/phpgroupware/files');
  $db->query('insert into config (config_name, config_value) values ('encryptkey', 'change this phrase 2 something else');
  $db->query('insert into config (config_name, config_value) values ('site_title', 'phpGroupWare');
  $db->query('insert into config (config_name, config_value) values ('hostname', 'local.machine.name');
  $db->query('insert into config (config_name, config_value) values ('webserver_url', '/phpgroupware');
  $db->query('insert into config (config_name, config_value) values ('db_host', 'localhost');
  $db->query('insert into config (config_name, config_value) values ('db_name', 'phpGroupWare_dev');
  $db->query('insert into config (config_name, config_value) values ('db_user', 'phpgroupware');
  $db->query('insert into config (config_name, config_value) values ('db_pass', 'phpgr0upwar3');
  $db->query('insert into config (config_name, config_value) values ('db_type', 'mysql');
  $db->query('insert into config (config_name, config_value) values ('auth_type', 'sql');
  $db->query('insert into config (config_name, config_value) values ('ldap_host', 'localhost');
  $db->query('insert into config (config_name, config_value) values ('ldap_context', 'o=phpGroupWare');
  $db->query('insert into config (config_name, config_value) values ('usecookies', 'True');
  $db->query('insert into config (config_name, config_value) values ('mail_server', 'localhost');
  $db->query('insert into config (config_name, config_value) values ('mail_server_type', 'imap');
  $db->query('insert into config (config_name, config_value) values ('imap_server_type', 'Cyrus');
  $db->query('insert into config (config_name, config_value) values ('mail_suffix', 'yourdomain.com');         
  $db->query('insert into config (config_name, config_value) values ('mail_login_type', 'standard');
  $db->query('insert into config (config_name, config_value) values ('smtp_server', 'localhost');
  $db->query('insert into config (config_name, config_value) values ('smtp_port', '25');
  $db->query('insert into config (config_name, config_value) values ('nntp_server', 'yournewsserver.com');
  $db->query('insert into config (config_name, config_value) values ('nntp_port', '119');
  $db->query('insert into config (config_name, config_value) values ('nntp_sender', 'complaints@yourserver.com');
  $db->query('insert into config (config_name, config_value) values ('nntp_organization', 'phpGroupWare');
  $db->query('insert into config (config_name, config_value) values ('nntp_admin', 'admin@yourserver.com');
  $db->query('insert into config (config_name, config_value) values ('nntp_login_username', '');
  $db->query('insert into config (config_name, config_value) values ('nntp_login_password', '');
  $db->query('insert into config (config_name, config_value) values ('charset', 'iso-8859-1');
  $db->query('insert into config (config_name, config_value) values ('default_ftp_server', 'localhost');
  $db->query('insert into config (config_name, config_value) values ('httpproxy_server', '');
  $db->query('insert into config (config_name, config_value) values ('httpproxy_port', '');
  $db->query('insert into config (config_name, config_value) values ('showpoweredbyon', 'bottom');
  $db->query('insert into config (config_name, config_value) values ('checkfornewversion', 'False');
  
  $db->query('insert into applications (app_name, app_title, app_enabled, app_order, app_tables) values ('admin', 'Administration', 1, 1, NULL);
  $db->query('insert into applications (app_name, app_title, app_enabled, app_order, app_tables) values ('tts', 'Trouble Ticket System', 0, 2, NULL);
  $db->query('insert into applications (app_name, app_title, app_enabled, app_order, app_tables) values ('inv', 'Inventory', 0, 3, NULL);
  $db->query('insert into applications (app_name, app_title, app_enabled, app_order, app_tables) values ('chat', 'Chat', 0, 4, NULL);
  $db->query('insert into applications (app_name, app_title, app_enabled, app_order, app_tables) values ('headlines', 'Headlines', 0, 5, 'news_sites,news_headlines,users_headlines');
  $db->query('insert into applications (app_name, app_title, app_enabled, app_order, app_tables) values ('filemanager', 'File manager', 1, 6, NULL);
  $db->query('insert into applications (app_name, app_title, app_enabled, app_order, app_tables) values ('addressbook', 'Address Book', 1, 7, 'addressbook');
  $db->query('insert into applications (app_name, app_title, app_enabled, app_order, app_tables) values ('todo', 'ToDo List', 1, 8, 'todo');
  $db->query('insert into applications (app_name, app_title, app_enabled, app_order, app_tables) values ('calendar', 'Calendar', 1, 9, 'webcal_entry,webcal_entry_users,webcal_entry_groups,webcal_repeats');
  $db->query('insert into applications (app_name, app_title, app_enabled, app_order, app_tables) values ('email', 'Email', 1, 10,NULL);
  $db->query('insert into applications (app_name, app_title, app_enabled, app_order, app_tables) values ('nntp', 'NNTP', 1, 11, 'newsgroups,users_newsgroups');
  $db->query('insert into applications (app_name, app_title, app_enabled, app_order, app_tables) values ('cron_apps', 'cron_apps', 0, 0, NULL);
  
  $db->query('insert into accounts (account_lid,account_pwd,account_firstname,account_lastname,account_permissions,account_groups,account_status) values ('demo','81dc9bdb52d04dc20036dbd8313ed055','Demo','Account',':admin:email:todo:addressbook:calendar:',',1,','A');
  
  $db->query('insert into groups (group_name) values ('Default');
  
  $db->query('insert into preferences (preference_owner, preference_name, preference_value, preference_appname) values ('demo','maxmatchs','10','common');
  $db->query('insert into preferences (preference_owner, preference_name, preference_value, preference_appname) values ('demo','mainscreen_showbirthdays','True','common');
  $db->query('insert into preferences (preference_owner, preference_name, preference_value, preference_appname) values ('demo','mainscreen_showevents','True','common');
  $db->query('insert into preferences (preference_owner, preference_name, preference_value, preference_appname) values ('demo','timeformat','12','common');
  $db->query('insert into preferences (preference_owner, preference_name, preference_value, preference_appname) values ('demo','dateformat','m/d/Y','common');
  $db->query('insert into preferences (preference_owner, preference_name, preference_value, preference_appname) values ('demo','theme','default','common');
  $db->query('insert into preferences (preference_owner, preference_name, preference_value, preference_appname) values ('demo','tz_offset','0','common');
?>