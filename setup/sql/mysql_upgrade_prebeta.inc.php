<?php
  /**************************************************************************\
  * phpGroupWare - Setup                                                     *
  * http://www.phpgroupware.org                                              *
  * --------------------------------------------                             *
  *  This program is free software; you can redistribute it and/or modify it *
  *  under the terms of the GNU General Public License as published by the   *
  *  Free Software Foundation; either version 2 of the License, or (at your  *
  *  option) any later version.                                              *
  \**************************************************************************/

  /* $Id$ */

  $test[] = "7122000";
  function upgrade7122000(){
    global $phpgw_info, $phpgw_setup;
    echo "Upgrading from 7122000 is not yet ready.<br> You can do this manually if you choose, otherwise dump your tables and start over.<br>\n";
    $phpgw_info["setup"]["prebeta"] = True;
    $phpgw_info["setup"]["currentver"]["phpgwapi"] = "8032000";
  }

  $test[] = "8032000";
  function upgrade8032000(){
    global $phpgw_info, $phpgw_setup;
    echo "Upgrading from 8032000 is not yet ready.<br> You can do this manually if you choose, otherwise dump your tables and start over.<br>\n";
    $phpgw_info["setup"]["prebeta"] = True;
    $phpgw_info["setup"]["currentver"]["phpgwapi"] = "8072000";
  }

  $test[] = "8072000";
  function upgrade8072000(){
    global $phpgw_info, $phpgw_setup;
    $sql = "CREATE TABLE applications ("
      ."app_name varchar(25) NOT NULL,"
      ."app_title varchar(50),"
      ."app_enabled int,"
      ."UNIQUE app_name (app_name)"
    .")";
    $phpgw_setup->db->query($sql);

    $phpgw_setup->db->query("insert into applications (app_name, app_title, app_enabled) values ('admin', 'Administration', 1)");
    $phpgw_setup->db->query("insert into applications (app_name, app_title, app_enabled) values ('tts', 'Trouble Ticket System', 1)");
    $phpgw_setup->db->query("insert into applications (app_name, app_title, app_enabled) values ('inv', 'Inventory', 1)");
    $phpgw_setup->db->query("insert into applications (app_name, app_title, app_enabled) values ('chat', 'Chat', 1)");
    $phpgw_setup->db->query("insert into applications (app_name, app_title, app_enabled) values ('headlines', 'Headlines', 1)");
    $phpgw_setup->db->query("insert into applications (app_name, app_title, app_enabled) values ('filemanager', 'File manager', 1)");
    $phpgw_setup->db->query("insert into applications (app_name, app_title, app_enabled) values ('ftp', 'FTP', 1)");
    $phpgw_setup->db->query("insert into applications (app_name, app_title, app_enabled) values ('addressbook', 'Address Book', 1)");
    $phpgw_setup->db->query("insert into applications (app_name, app_title, app_enabled) values ('todo', 'ToDo List', 1)");
    $phpgw_setup->db->query("insert into applications (app_name, app_title, app_enabled) values ('calendar', 'Calendar', 1)");
    $phpgw_setup->db->query("insert into applications (app_name, app_title, app_enabled) values ('email', 'Email', 1)");
    $phpgw_setup->db->query("insert into applications (app_name, app_title, app_enabled) values ('nntp', 'NNTP', 1)");
    $phpgw_setup->db->query("insert into applications (app_name, app_title, app_enabled) values ('bookmarks', 'Bookmarks', 0)");
    $phpgw_setup->db->query("insert into applications (app_name, app_title, app_enabled) values ('cron_apps', 'cron_apps', 0)");
    $phpgw_setup->db->query("insert into applications (app_name, app_title, app_enabled) values ('napster', 'Napster', 0)");
    $phpgw_info["setup"]["prebeta"] = True;
    $phpgw_info["setup"]["currentver"]["phpgwapi"] = "8212000";
  }

  $test[] = "8212000";
  function upgrade8212000(){
    global $phpgw_info, $phpgw_setup;
    $phpgw_setup->db->query("alter table chat_channel change name name varchar(10) not null");
    $phpgw_setup->db->query("alter table chat_messages change channel channel char(20) not null");
    $phpgw_setup->db->query("alter table chat_messages change loginid loginid varchar(20) not null");
    $phpgw_setup->db->query("alter table chat_currentin change loginid loginid varchar(25) not null");
    $phpgw_setup->db->query("alter table chat_currentin change channel channel char(20)");
    $phpgw_setup->db->query("alter table chat_privatechat change user1 user1 varchar(25) not null");
    $phpgw_setup->db->query("alter table chat_privatechat change user2 user2 varchar(25) not null");
    $phpgw_info["setup"]["prebeta"] = True;
    $phpgw_info["setup"]["currentver"]["phpgwapi"] = "9052000";
  }

  $test[] = "9052000";
  function upgrade9052000(){
    global $phpgw_info, $phpgw_setup;
    echo "Upgrading from 9052000 is not available.<br> I dont believe there were any changes, so this should be fine.<br>\n";
    $phpgw_info["setup"]["prebeta"] = True;
    $phpgw_info["setup"]["currentver"]["phpgwapi"] = "9072000";
  }

  $test[] = "9072000";
  function upgrade9072000(){
    global $phpgw_info, $phpgw_setup;
    $phpgw_setup->db->query("alter table accounts     change con               account_id             int(11)     DEFAULT '0' NOT NULL auto_increment");
    $phpgw_setup->db->query("alter table accounts     change loginid           account_lid            varchar(25) NOT NULL");
    $phpgw_setup->db->query("alter table accounts     change passwd            account_pwd            varchar(32) NOT NULL");
    $phpgw_setup->db->query("alter table accounts     change firstname         account_firstname      varchar(50)");
    $phpgw_setup->db->query("alter table accounts     change lastname          account_lastname       varchar(50)");
    $phpgw_setup->db->query("alter table accounts     change permissions       account_permissions    text");
    $phpgw_setup->db->query("alter table accounts     change groups            account_groups         varchar(30)");
    $phpgw_setup->db->query("alter table accounts     change lastlogin         account_lastlogin      int(11)");
    $phpgw_setup->db->query("alter table accounts     change lastloginfrom     account_lastloginfrom  varchar(255)");
    $phpgw_setup->db->query("alter table accounts     change lastpasswd_change account_lastpwd_change int(11)");
    $phpgw_setup->db->query("alter table accounts     change status            account_status         enum('A','L') DEFAULT 'A' NOT NULL");
    $phpgw_setup->db->query("alter table applications add    app_order         int");
    $phpgw_setup->db->query("alter table applications add    app_tables        varchar(255)");
    $phpgw_setup->db->query("alter table applications add    app_version       varchar(20) not null default '0.0'");
    $phpgw_setup->db->query("alter table preferences  change owner             preference_owner       varchar(20)");
    $phpgw_setup->db->query("alter table preferences  change name              preference_name        varchar(50)");
    $phpgw_setup->db->query("alter table preferences  change value             preference_value       varchar(50)");
    $phpgw_setup->db->query("alter table preferences  add    preference_appname                       varchar(50) default ''");
    $phpgw_setup->db->query("alter table sessions     change sessionid         session_id             varchar(255) NOT NULL");
    $phpgw_setup->db->query("alter table sessions     change loginid           session_lid            varchar(20)");
    $phpgw_setup->db->query("alter table sessions     change passwd            session_pwd            varchar(255)");
    $phpgw_setup->db->query("alter table sessions     change ip                session_ip             varchar(255)");
    $phpgw_setup->db->query("alter table sessions     change logintime         session_logintime      int(11)");
    $phpgw_setup->db->query("alter table sessions     change dla               session_dla            int(11)");

    $phpgw_setup->db->query("alter table todo         change con               todo_id                int(11)");
    $phpgw_setup->db->query("alter table todo         change owner             todo_owner             varchar(25)");
    $phpgw_setup->db->query("alter table todo         change access            todo_access            varchar(255)");
    $phpgw_setup->db->query("alter table todo         change des               todo_des               text");
    $phpgw_setup->db->query("alter table todo         change pri               todo_pri               int(11)");
    $phpgw_setup->db->query("alter table todo         change status            todo_status            int(11)");
    $phpgw_setup->db->query("alter table todo         change datecreated       todo_datecreated       int(11)");
    $phpgw_setup->db->query("alter table todo         change datedue           todo_datedue           int(11)");

    // The addressbook section is missing.
    
    $phpgw_setup->db->query("update applications set app_order=1,app_tables=NULL where app_name='admin'");
    $phpgw_setup->db->query("update applications set app_order=2,app_tables=NULL where app_name='tts'");
    $phpgw_setup->db->query("update applications set app_order=3,app_tables=NULL where app_name='inv'");
    $phpgw_setup->db->query("update applications set app_order=4,app_tables=NULL where app_name='chat'");
    $phpgw_setup->db->query("update applications set app_order=5,app_tables='news_sites,news_headlines,users_headlines' where app_name='headlines'");
    $phpgw_setup->db->query("update applications set app_order=6,app_tables=NULL where app_name='filemanager'");
    $phpgw_setup->db->query("update applications set app_order=7,app_tables='addressbook' where app_name='addressbook'");
    $phpgw_setup->db->query("update applications set app_order=8,app_tables='todo' where app_name='todo'");
    $phpgw_setup->db->query("update applications set app_order=9,app_tables='webcal_entry,webcal_entry_users,webcal_entry_groups,webcal_repeats' where app_name='calendar'");
    $phpgw_setup->db->query("update applications set app_order=10,app_tables=NULL where app_name='email'");
    $phpgw_setup->db->query("update applications set app_order=11,app_tables='newsgroups,users_newsgroups' where app_name='nntp'");
    $phpgw_setup->db->query("update applications set app_order=0,app_tables=NULL where app_name='cron_apps'");
    $sql = "CREATE TABLE config ("
      ."config_name     varchar(25) NOT NULL,"
      ."config_value    varchar(100),"
      ."UNIQUE config_name (config_name)"
    .")";
    $phpgw_setup->db->query($sql);  

    $phpgw_setup->db->query("insert into config (config_name, config_value) values ('template_set', 'default')");
    $phpgw_setup->db->query("insert into config (config_name, config_value) values ('temp_dir', '/path/to/tmp')");
    $phpgw_setup->db->query("insert into config (config_name, config_value) values ('files_dir', '/path/to/dir/phpgroupware/files')");
    $phpgw_setup->db->query("insert into config (config_name, config_value) values ('encryptkey', 'change this phrase 2 something else'");
    $phpgw_setup->db->query("insert into config (config_name, config_value) values ('site_title', 'phpGroupWare')");
    $phpgw_setup->db->query("insert into config (config_name, config_value) values ('hostname', 'local.machine.name')");
    $phpgw_setup->db->query("insert into config (config_name, config_value) values ('webserver_url', 'http://www.domain.com/phpgroupware')");
    $phpgw_setup->db->query("insert into config (config_name, config_value) values ('auth_type', 'sql')");
    $phpgw_setup->db->query("insert into config (config_name, config_value) values ('ldap_host', 'localhost')");
    $phpgw_setup->db->query("insert into config (config_name, config_value) values ('ldap_context', 'o=phpGroupWare')");
    $phpgw_setup->db->query("insert into config (config_name, config_value) values ('usecookies', 'True')");
    $phpgw_setup->db->query("insert into config (config_name, config_value) values ('mail_server', 'localhost')");
    $phpgw_setup->db->query("insert into config (config_name, config_value) values ('mail_server_type', 'imap')");
    $phpgw_setup->db->query("insert into config (config_name, config_value) values ('imap_server_type', 'Cyrus')");
    $phpgw_setup->db->query("insert into config (config_name, config_value) values ('mail_suffix', 'yourdomain.com')");         
    $phpgw_setup->db->query("insert into config (config_name, config_value) values ('mail_login_type', 'standard')");
    $phpgw_setup->db->query("insert into config (config_name, config_value) values ('smtp_server', 'localhost')");
    $phpgw_setup->db->query("insert into config (config_name, config_value) values ('smtp_port', '25')");
    $phpgw_setup->db->query("insert into config (config_name, config_value) values ('nntp_server', 'yournewsserver.com')");
    $phpgw_setup->db->query("insert into config (config_name, config_value) values ('nntp_port', '119')");
    $phpgw_setup->db->query("insert into config (config_name, config_value) values ('nntp_sender', 'complaints@yourserver.com')");
    $phpgw_setup->db->query("insert into config (config_name, config_value) values ('nntp_organization', 'phpGroupWare')");
    $phpgw_setup->db->query("insert into config (config_name, config_value) values ('nntp_admin', 'admin@yourserver.com')");
    $phpgw_setup->db->query("insert into config (config_name, config_value) values ('nntp_login_username', '')");
    $phpgw_setup->db->query("insert into config (config_name, config_value) values ('nntp_login_password', '')");
    $phpgw_setup->db->query("insert into config (config_name, config_value) values ('default_ftp_server', 'localhost')");
    $phpgw_setup->db->query("insert into config (config_name, config_value) values ('httpproxy_server', '')");
    $phpgw_setup->db->query("insert into config (config_name, config_value) values ('httpproxy_port', '')");
    $phpgw_setup->db->query("insert into config (config_name, config_value) values ('showpoweredbyon', 'bottom')");
    $phpgw_setup->db->query("insert into config (config_name, config_value) values ('checkfornewversion', 'False')");
    $phpgw_info["setup"]["currentver"]["phpgwapi"] = "0.9.1";
  }
?>