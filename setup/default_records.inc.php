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

  function add_default_server_config(){
    global $db, $phpgw_info;
    $db->query("insert into config (config_name, config_value) values ('default_tplset', 'default')");
    $db->query("insert into config (config_name, config_value) values ('temp_dir', '/path/to/tmp')");
    $db->query("insert into config (config_name, config_value) values ('files_dir', '/path/to/dir/phpgroupware/files')");
    $db->query("insert into config (config_name, config_value) values ('encryptkey', 'change this phrase 2 something else')");
    $db->query("insert into config (config_name, config_value) values ('site_title', 'phpGroupWare')");
    $db->query("insert into config (config_name, config_value) values ('hostname', 'local.machine.name')");
    $db->query("insert into config (config_name, config_value) values ('webserver_url', '/phpgroupware')");
    $db->query("insert into config (config_name, config_value) values ('auth_type', 'sql')");
    $db->query("insert into config (config_name, config_value) values ('ldap_host', 'localhost')");
    $db->query("insert into config (config_name, config_value) values ('ldap_context', 'o=phpGroupWare')");
    $db->query("insert into config (config_name, config_value) values ('ldap_encryption_type', 'DES')");
    $db->query("insert into config (config_name, config_value) values ('ldap_root_dn', 'cn=Manager,dc=my-domain,dc=com')");
    $db->query("insert into config (config_name, config_value) values ('ldap_root_pw', 'secret')");
    $db->query("insert into config (config_name, config_value) values ('usecookies', 'True')");
    $db->query("insert into config (config_name, config_value) values ('mail_server', 'localhost')");
    $db->query("insert into config (config_name, config_value) values ('mail_server_type', 'imap')");
    $db->query("insert into config (config_name, config_value) values ('imap_server_type', 'Cyrus')");
    $db->query("insert into config (config_name, config_value) values ('mail_suffix', 'yourdomain.com')");         
    $db->query("insert into config (config_name, config_value) values ('mail_login_type', 'standard')");
    $db->query("insert into config (config_name, config_value) values ('smtp_server', 'localhost')");
    $db->query("insert into config (config_name, config_value) values ('smtp_port', '25')");
    $db->query("insert into config (config_name, config_value) values ('nntp_server', 'yournewsserver.com')");
    $db->query("insert into config (config_name, config_value) values ('nntp_port', '119')");
    $db->query("insert into config (config_name, config_value) values ('nntp_sender', 'complaints@yourserver.com')");
    $db->query("insert into config (config_name, config_value) values ('nntp_organization', 'phpGroupWare')");
    $db->query("insert into config (config_name, config_value) values ('nntp_admin', 'admin@yourserver.com')");
    $db->query("insert into config (config_name, config_value) values ('nntp_login_username', '')");
    $db->query("insert into config (config_name, config_value) values ('nntp_login_password', '')");
    $db->query("insert into config (config_name, config_value) values ('charset', 'iso-8859-1')");
    $db->query("insert into config (config_name, config_value) values ('default_ftp_server', 'localhost')");
    $db->query("insert into config (config_name, config_value) values ('httpproxy_server', '')");
    $db->query("insert into config (config_name, config_value) values ('httpproxy_port', '')");
    $db->query("insert into config (config_name, config_value) values ('showpoweredbyon', 'bottom')");
    $db->query("insert into config (config_name, config_value) values ('checkfornewversion', 'False')");
  }

  if ($useglobalconfigsettings == "on"){
    if (is_file($basedir)){
      include ($phpgw_info["server"]["include_root"]."/globalconfig.inc.php");
      $db->query("insert into config (config_name, config_value) values ('default_tplset', '".$phpgw_info["server"]["default_tplset"]."')");
      $db->query("insert into config (config_name, config_value) values ('temp_dir', '".$phpgw_info["server"]["temp_dir"]."')");
      $db->query("insert into config (config_name, config_value) values ('files_dir', '".$phpgw_info["server"]["files_dir"]."')");
      $db->query("insert into config (config_name, config_value) values ('encryptkey', '".$phpgw_info["server"]["encryptkey"]."')");
      $db->query("insert into config (config_name, config_value) values ('site_title', '".$phpgw_info["server"]["site_title"]."')");
      $db->query("insert into config (config_name, config_value) values ('hostname', '".$phpgw_info["server"]["hostname"]."')");
      $db->query("insert into config (config_name, config_value) values ('webserver_url', '".$phpgw_info["server"]["webserver_url"].")");
      $db->query("insert into config (config_name, config_value) values ('auth_type', '".$phpgw_info["server"]["auth_type"]."')");
      $db->query("insert into config (config_name, config_value) values ('ldap_host', '".$phpgw_info["server"]["ldap_host"]."')");
      $db->query("insert into config (config_name, config_value) values ('ldap_context', '".$phpgw_info["server"]["ldap_context"]."')");
      $db->query("insert into config (config_name, config_value) values ('usecookies', '".$phpgw_info["server"]["usecookies"]."')");
      $db->query("insert into config (config_name, config_value) values ('mail_server', '".$phpgw_info["server"]["mail_server"]."')");
      $db->query("insert into config (config_name, config_value) values ('mail_server_type', '".$phpgw_info["server"]["mail_server_type"]."')");
      $db->query("insert into config (config_name, config_value) values ('imap_server_type', '".$phpgw_info["server"]["imap_server_type"]."')");
      $db->query("insert into config (config_name, config_value) values ('mail_suffix', '".$phpgw_info["server"]["mail_suffix"]."')");         
      $db->query("insert into config (config_name, config_value) values ('mail_login_type', '".$phpgw_info["server"]["mail_login_type"]."')");
      $db->query("insert into config (config_name, config_value) values ('smtp_server', '".$phpgw_info["server"]["smtp_server"]."')");
      $db->query("insert into config (config_name, config_value) values ('smtp_port', '".$phpgw_info["server"]["smtp_port"]."')");
      $db->query("insert into config (config_name, config_value) values ('nntp_server', '".$phpgw_info["server"]["nntp_server"]."')");
      $db->query("insert into config (config_name, config_value) values ('nntp_port', '".$phpgw_info["server"]["nntp_port"]."')");
      $db->query("insert into config (config_name, config_value) values ('nntp_sender', '".$phpgw_info["server"]["nntp_sender"]."')");
      $db->query("insert into config (config_name, config_value) values ('nntp_organization', '".$phpgw_info["server"]["nntp_organization"]."')");
      $db->query("insert into config (config_name, config_value) values ('nntp_admin', '".$phpgw_info["server"]["nntp_admin"]."')");
      $db->query("insert into config (config_name, config_value) values ('nntp_login_username', '".$phpgw_info["server"]["nntp_login_username"]."')");
      $db->query("insert into config (config_name, config_value) values ('nntp_login_password', '".$phpgw_info["server"]["nntp_login_password"]."')");
      $db->query("insert into config (config_name, config_value) values ('charset', '".$phpgw_info["server"]["charset"]."')");
      $db->query("insert into config (config_name, config_value) values ('default_ftp_server', '".$phpgw_info["server"]["default_ftp_server"]."')");
      $db->query("insert into config (config_name, config_value) values ('httpproxy_server', '".$phpgw_info["server"]["httpproxy_server"]."')");
      $db->query("insert into config (config_name, config_value) values ('httpproxy_port', '".$phpgw_info["server"]["httpproxy_port"]."')");
      $db->query("insert into config (config_name, config_value) values ('showpoweredbyon', '".$phpgw_info["server"]["showpoweredbyon"]."')");
      $db->query("insert into config (config_name, config_value) values ('checkfornewversion', '".$phpgw_info["server"]["checkfornewversion"]."')");
    }else{
      echo "<table border=\"0\" align=\"center\">\n";
      echo "  <tr bgcolor=\"486591\">\n";
      echo "    <td colspan=\"2\"><font color=\"fefefe\">&nbsp;<b>Error</b></font></td>\n";
      echo "  </tr>\n";
      echo "  <tr bgcolor=\"e6e6e6\">\n";
      echo "    <td>Could not find your old globalconfig.inc.php.<br> You will be required to configure your installation manually.</td>\n";
      echo "  </tr>\n";
      echo "</table>\n";
      add_default_server_config();
    }
  }else{
    add_default_server_config();
  }
  $db->query("insert into applications (app_name, app_title, app_enabled, app_order, app_tables, app_version) values ('admin', 'Administration', 1, 1, NULL, '".$phpgw_info["server"]["version"]."')");
  $db->query("insert into applications (app_name, app_title, app_enabled, app_order, app_tables, app_version) values ('tts', 'Trouble Ticket System', 0, 2, NULL, '0.0.0')");
  $db->query("insert into applications (app_name, app_title, app_enabled, app_order, app_tables, app_version) values ('inv', 'Inventory', 0, 3, NULL, '0.0.0')");
  $db->query("insert into applications (app_name, app_title, app_enabled, app_order, app_tables, app_version) values ('chat', 'Chat', 0, 4, NULL, '0.0.0')");
  $db->query("insert into applications (app_name, app_title, app_enabled, app_order, app_tables, app_version) values ('headlines', 'Headlines', 0, 5, 'news_sites,news_headlines,users_headlines', '0.0.0')");
  $db->query("insert into applications (app_name, app_title, app_enabled, app_order, app_tables, app_version) values ('filemanager', 'File manager', 1, 6, NULL, '".$phpgw_info["server"]["version"]."')");
  $db->query("insert into applications (app_name, app_title, app_enabled, app_order, app_tables, app_version) values ('addressbook', 'Address Book', 1, 7, 'addressbook', '".$phpgw_info["server"]["version"]."')");
  $db->query("insert into applications (app_name, app_title, app_enabled, app_order, app_tables, app_version) values ('todo', 'ToDo List', 1, 8, 'todo', '".$phpgw_info["server"]["version"]."')");
  $db->query("insert into applications (app_name, app_title, app_enabled, app_order, app_tables, app_version) values ('calendar', 'Calendar', 1, 9, 'webcal_entry,webcal_entry_users,webcal_entry_groups,webcal_repeats', '".$phpgw_info["server"]["version"]."')");
  $db->query("insert into applications (app_name, app_title, app_enabled, app_order, app_tables, app_version) values ('email', 'Email', 1, 10,NULL, '".$phpgw_info["server"]["version"]."')");
  $db->query("insert into applications (app_name, app_title, app_enabled, app_order, app_tables, app_version) values ('nntp', 'NNTP', 1, 11, 'newsgroups', '".$phpgw_info["server"]["version"]."')");
  $db->query("insert into applications (app_name, app_title, app_enabled, app_order, app_tables, app_version) values ('cron_apps', 'cron_apps', 0, 0, NULL, '".$phpgw_info["server"]["version"]."')");
  $db->query("insert into applications (app_name, app_title, app_enabled, app_order, app_tables, app_version) values ('weather', 'Weather', 1, 12, NULL, '".$phpgw_info["server"]["version"]."')");
  
  $db->query("insert into accounts (account_lid,account_pwd,account_firstname,account_lastname,account_permissions,account_groups,account_status) values ('demo','81dc9bdb52d04dc20036dbd8313ed055','Demo','Account',':admin:email:todo:addressbook:calendar:',',1,','A')");
  
  $db->query("insert into groups (group_name) values ('Default')");
  
  $db->query("insert into preferences (preference_owner, preference_name, preference_value, preference_appname) values ('demo','maxmatchs','10','common')");
  $db->query("insert into preferences (preference_owner, preference_name, preference_value, preference_appname) values ('demo','mainscreen_showbirthdays','True','common')");
  $db->query("insert into preferences (preference_owner, preference_name, preference_value, preference_appname) values ('demo','mainscreen_showevents','True','common')");
  $db->query("insert into preferences (preference_owner, preference_name, preference_value, preference_appname) values ('demo','timeformat','12','common')");
  $db->query("insert into preferences (preference_owner, preference_name, preference_value, preference_appname) values ('demo','dateformat','m/d/Y','common')");
  $db->query("insert into preferences (preference_owner, preference_name, preference_value, preference_appname) values ('demo','theme','default','common')");
  $db->query("insert into preferences (preference_owner, preference_name, preference_value, preference_appname) values ('demo','tz_offset','0','common')");
  $db->query("insert into preferences (preference_owner, preference_name, preference_value, preference_appname) values ('demo','lang','en','common')");
  $db->query("insert into preferences (preference_owner, preference_name, preference_value, preference_appname) values ('demo','firstname','True','addressbook')");
  $db->query("insert into preferences (preference_owner, preference_name, preference_value, preference_appname) values ('demo','lastname','True','addressbook')");
  $db->query("insert into preferences (preference_owner, preference_name, preference_value, preference_appname) values ('demo','company','True','addressbook')");
    
?>
