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

  function v7122000to8032000(){
    global $currentver, $db;
    $didupgrade = True;
    if ($currentver == "7122000"){
      echo "  <tr bgcolor=\"e6e6e6\">\n";
//      echo "    <td>Upgrade from 7122000 to 8032000 is completed.</td>\n";
      echo "    <td>Upgrading from 7122000 is not yet ready.<br> You can do this manually if you choose, otherwise dump your tables and start over.</td>\n";
      echo "  </tr>\n";
      $currentver = "8032000";
    }
  }
  function v8032000to8072000(){
    global $currentver, $db;
    $didupgrade = True;
    if ($currentver == "8032000"){
      echo "  <tr bgcolor=\"e6e6e6\">\n";
//      echo "    <td>Upgrade from 8032000 to 8072000 is completed.</td>\n";
      echo "    <td>Upgrading from 8032000 is not yet ready.<br> You can do this manually if you choose, otherwise dump your tables and start over.</td>\n";
      echo "  </tr>\n";
      $currentver = "8072000";
    }
  }
  
  function v8072000to8212000(){
    global $currentver, $db;
    $didupgrade = True;
    if ($currentver == "8072000"){

      $sql = "CREATE TABLE applications ("
        ."app_name varchar(25) NOT NULL,"
        ."app_title varchar(50),"
        ."app_enabled int,"
        ."UNIQUE app_name (app_name)"
      .")";
      $db->query($sql);

      $db->query("insert into applications (app_name, app_title, app_enabled) values ('admin', 'Administration', 1)");
      $db->query("insert into applications (app_name, app_title, app_enabled) values ('tts', 'Trouble Ticket System', 1)");
      $db->query("insert into applications (app_name, app_title, app_enabled) values ('inv', 'Inventory', 1)");
      $db->query("insert into applications (app_name, app_title, app_enabled) values ('chat', 'Chat', 1)");
      $db->query("insert into applications (app_name, app_title, app_enabled) values ('headlines', 'Headlines', 1)");
      $db->query("insert into applications (app_name, app_title, app_enabled) values ('filemanager', 'File manager', 1)");
      $db->query("insert into applications (app_name, app_title, app_enabled) values ('ftp', 'FTP', 1)");
      $db->query("insert into applications (app_name, app_title, app_enabled) values ('addressbook', 'Address Book', 1)");
      $db->query("insert into applications (app_name, app_title, app_enabled) values ('todo', 'ToDo List', 1)");
      $db->query("insert into applications (app_name, app_title, app_enabled) values ('calendar', 'Calendar', 1)");
      $db->query("insert into applications (app_name, app_title, app_enabled) values ('email', 'Email', 1)");
      $db->query("insert into applications (app_name, app_title, app_enabled) values ('nntp', 'NNTP', 1)");
      $db->query("insert into applications (app_name, app_title, app_enabled) values ('bookmarks', 'Bookmarks', 0)");
      $db->query("insert into applications (app_name, app_title, app_enabled) values ('cron_apps', 'cron_apps', 0)");
      $db->query("insert into applications (app_name, app_title, app_enabled) values ('napster', 'Napster', 0)");
    
      echo "  <tr bgcolor=\"e6e6e6\">\n";
      echo "    <td>Upgrade from 8072000 to 8212000 is completed.</td>\n";
      echo "  </tr>\n";
      $currentver = "8212000";
    }
  }
  function v8212000to9052000(){
    global $currentver, $db;
    $didupgrade = True;
    if ($currentver == "8212000"){
      $db->query("alter table chat_channel change name name varchar(10) not null");
      $db->query("alter table chat_messages change channel channel char(20) not null");
      $db->query("alter table chat_messages change loginid loginid varchar(20) not null");
      $db->query("alter table chat_currentin change loginid loginid varchar(25) not null");
      $db->query("alter table chat_currentin change channel channel char(20)");
      $db->query("alter table chat_privatechat change user1 user1 varchar(25) not null");
      $db->query("alter table chat_privatechat change user2 user2 varchar(25) not null");

      echo "  <tr bgcolor=\"e6e6e6\">\n";
      echo "    <td>Upgrade from 8212000 to 9052000 is completed.</td>\n";
      echo "  </tr>\n";
      $currentver = "9052000";
    }
  }
  function v9052000to9072000(){
    global $currentver, $db;
    $didupgrade = True;
    if ($currentver == "9052000"){
      echo "  <tr bgcolor=\"e6e6e6\">\n";
//      echo "    <td>Upgrade from 9052000 to 9072000 is completed.</td>\n";
      echo "    <td>Upgrading from 9052000 is not available.<br> I dont believe there were any changes, so this should be fine.</td>\n";
      echo "  </tr>\n";
      $currentver = "9072000";
    }
  }
  function v9072000to0_9_1(){
    global $currentver, $phpgw_info, $db;
    $didupgrade = True;
    if ($currentver == "9072000"){

      $db->query("alter table accounts     change con               account_id             int(11)     DEFAULT '0' NOT NULL auto_increment");
      $db->query("alter table accounts     change loginid           account_lid            varchar(25) NOT NULL");
      $db->query("alter table accounts     change passwd            account_pwd            varchar(32) NOT NULL");
      $db->query("alter table accounts     change firstname         account_firstname      varchar(50)");
      $db->query("alter table accounts     change lastname          account_lastname       varchar(50)");
      $db->query("alter table accounts     change permissions       account_permissions    text");
      $db->query("alter table accounts     change groups            account_groups         varchar(30)");
      $db->query("alter table accounts     change lastlogin         account_lastlogin      int(11)");
      $db->query("alter table accounts     change lastloginfrom     account_lastloginfrom  varchar(255)");
      $db->query("alter table accounts     change lastpasswd_change account_lastpwd_change int(11)");
      $db->query("alter table accounts     change status            account_status         enum('A','L') DEFAULT 'A' NOT NULL");
      $db->query("alter table applications add    app_order         int");
      $db->query("alter table applications add    app_tables        varchar(255)");
      $db->query("alter table applications add    app_version       varchar(20) not null default '0.0'");
      $db->query("alter table preferences  change owner             preference_owner       varchar(20)");
      $db->query("alter table preferences  change name              preference_name        varchar(50)");
      $db->query("alter table preferences  change value             preference_value       varchar(50)");
      $db->query("alter table preferences  add    preference_appname                       varchar(50) default ''");
      $db->query("alter table sessions     change sessionid         session_id             varchar(255) NOT NULL");
      $db->query("alter table sessions     change loginid           session_lid            varchar(20)");
      $db->query("alter table sessions     change passwd            session_pwd            varchar(255)");
      $db->query("alter table sessions     change ip                session_ip             varchar(255)");
      $db->query("alter table sessions     change logintime         session_logintime      int(11)");
      $db->query("alter table sessions     change dla               session_dla            int(11)");

      $db->query("update applications set app_order=1,app_tables=NULL where app_name='admin'");
      $db->query("update applications set app_order=2,app_tables=NULL where app_name='tts'");
      $db->query("update applications set app_order=3,app_tables=NULL where app_name='inv'");
      $db->query("update applications set app_order=4,app_tables=NULL where app_name='chat'");
      $db->query("update applications set app_order=5,app_tables='news_sites,news_headlines,users_headlines' where app_name='headlines'");
      $db->query("update applications set app_order=6,app_tables=NULL where app_name='filemanager'");
      $db->query("update applications set app_order=7,app_tables='addressbook' where app_name='addressbook'");
      $db->query("update applications set app_order=8,app_tables='todo' where app_name='todo'");
      $db->query("update applications set app_order=9,app_tables='webcal_entry,webcal_entry_users,webcal_entry_groups,webcal_repeats' where app_name='calendar'");
      $db->query("update applications set app_order=10,app_tables=NULL where app_name='email'");
      $db->query("update applications set app_order=11,app_tables='newsgroups,users_newsgroups' where app_name='nntp'");
      $db->query("update applications set app_order=0,app_tables=NULL where app_name='cron_apps'");
      $sql = "CREATE TABLE config ("
        ."config_name     varchar(25) NOT NULL,"
        ."config_value    varchar(100),"
        ."UNIQUE config_name (config_name)"
      .")";
      $db->query($sql);  

      $db->query("insert into config (config_name, config_value) values ('default_tplset', 'default')");
      $db->query("insert into config (config_name, config_value) values ('temp_dir', '/path/to/tmp')");
      $db->query("insert into config (config_name, config_value) values ('files_dir', '/path/to/dir/phpgroupware/files')");
      $db->query("insert into config (config_name, config_value) values ('encryptkey', 'change this phrase 2 something else'");
      $db->query("insert into config (config_name, config_value) values ('site_title', 'phpGroupWare')");
      $db->query("insert into config (config_name, config_value) values ('hostname', 'local.machine.name')");
      $db->query("insert into config (config_name, config_value) values ('webserver_url', '/phpgroupware')");
      $db->query("insert into config (config_name, config_value) values ('auth_type', 'sql')");
      $db->query("insert into config (config_name, config_value) values ('ldap_host', 'localhost')");
      $db->query("insert into config (config_name, config_value) values ('ldap_context', 'o=phpGroupWare')");
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
      $db->query("insert into config (config_name, config_value) values ('default_ftp_server', 'localhost')");
      $db->query("insert into config (config_name, config_value) values ('httpproxy_server', '')");
      $db->query("insert into config (config_name, config_value) values ('httpproxy_port', '')");
      $db->query("insert into config (config_name, config_value) values ('showpoweredbyon', 'bottom')");
      $db->query("insert into config (config_name, config_value) values ('checkfornewversion', 'False')");

      echo "  <tr bgcolor=\"e6e6e6\">\n";
      echo "    <td>Upgrade from 9072000 to 0.9.1 is completed.</td>\n";
      echo "  </tr>\n";
      $currentver = "0.9.1";
    }
  }

  function v0_9_1to0_9_2(){
    global $currentver, $phpgw_info, $db;
    $didupgrade = True;
    if ($currentver == "0.9.1"){

      $db->query("alter table access_log change lo lo varchar(255)");
      $db->query("alter table addressbook  change ab_id ab_id int(11) NOT NULL auto_increment");
      $db->query("alter table addressbook add ab_company_id int(10) unsigned");
      $db->query("alter table addressbook add ab_title varchar(60)");
      $db->query("alter table addressbook add ab_address2 varchar(60)");

      $sql = "CREATE TABLE customers (
          company_id int(10) unsigned NOT NULL auto_increment,
          company_name varchar(255),
          website varchar(80),
          ftpsite varchar(80),
          industry_type varchar(50),
          status varchar(30),
          software varchar(40),
          lastjobnum int(10) unsigned,
          lastjobfinished date,
          busrelationship varchar(30),
          notes text,
          PRIMARY KEY (company_id)
        );";
      $db->query($sql);  

      $db->query("update lang set lang='da' where lang='dk'");
      $db->query("update lang set lang='ko' where lang='kr'");

      $db->query("update preferences set preference_name='da' where preference_name='dk'");
      $db->query("update preferences set preference_name='ko' where preference_name='kr'");

	//add weather support
      $db->query("insert into applications (app_name, app_title, app_enabled, app_order, app_tables, app_version) values ('weather', 'Weather', 1, 12, NULL, '".$phpgw_info["server"]["version"]."')");
      $db->query("INSERT INTO lang (message_id, app_name, lang, content) VALUES( 'weather','Weather','en','weather')");

      echo "  <tr bgcolor=\"e6e6e6\">\n";
      echo "    <td>Upgrade from 0.9.1 to 0.9.2 is completed.</td>\n";
      echo "  </tr>\n";
      $currentver = "0.9.2";
    }
  }

  function update_owner($table,$field){
    global $db;
    $db->query("select distinct($field) from $table");
    if ($db->num_rows()) {
      while($db->next_record()) {
	$owner[count($owner)] = $db->f($field);
      }
      for($i=0;$i<count($owner);$i++) {
        $db->query("select account_id from accounts where account_lid='".$owner[$i]."'");
	$db->next_record();
	$db->query("update $table set $field=".$db->f("account_id")." where $field='".$owner[$i]."'");
      }
    }
    $db->query("alter table $table change $field $field int(11) NOT NULL");
  }

  function v0_9_2to0_9_3pre5(){
    global $currentver, $phpgw_info, $db;
    $didupgrade = True;

    // The 0.9.3pre1 is only temp until release
    if ($currentver == "0.9.2" || $currentver == "0.9.3pre1" || $currentver == "0.9.3pre2" || $currentver == "0.9.3pre3" || $currentver == "0.9.3pre4") {
      if ($currentver == "0.9.2" || $currentver == "0.9.3pre1") {
	update_owner("addressbook","ab_owner");
	update_owner("todo","todo_owner");
	update_owner("webcal_entry","cal_create_by");
	update_owner("webcal_entry_user","cal_login");
	$currentver = "0.9.3pre2";
       }
      if ($currentver == "0.9.3pre2") {
	$db->query("select owner, newsgroup from users_newsgroups");
	if($db->num_rows()) {
	  while($db->next_record()) {
	    $owner[count($owner)] = $db->f("owner");
	    $newsgroup[count($newsgroup)] = $db->f("newsgroup");
	  }
	  for($i=0;$i<count($owner);$i++) {
	    $db->query("insert into preferences (preference_owner,preference_name,"
		       ."preference_value,preference_appname) values ('".$owner[$i]."','".$newsgroup[$i]."','True',"
		       ."'nntp')");
 	  }
	  $db->query("drop table users_newsgroups");
	  $db->query("update applications set app_tables='newsgroups' where app_name='nntp'");
	}
        $currentver = "0.9.3pre3";
      }
      if ($currentver == "0.9.3pre3") {
     	$db->query("alter table todo add todo_id_parent int DEFAULT 0 NOT NULL");
         $currentver = "0.9.3pre4";
      }

      if ($currentver == "0.9.3pre4") {
     	$db->query("create table temp as select * from config");
     	$db->query("drop table config");
     	$db->query("create table config config_name varchar(255) NOT NULL UNIQUE, config_value varchar(100) NOT NULL");
     	$db->query("insert into config select * from temp");
     	$db->query("drop table config");
        $currentver = "0.9.3pre5";
      }
      

       echo "  <tr bgcolor=\"e6e6e6\">\n";
       echo "    <td>Upgrade from 0.9.2 to $currentver is completed.</td>\n";
       echo "  </tr>\n";
    }
  }

  echo "<table border=\"0\" align=\"center\">\n";
  echo "  <tr bgcolor=\"486591\">\n";
  echo "    <td colspan=\"2\"><font color=\"fefefe\">&nbsp;<b>Table Upgrades</b></font></td>\n";
  echo "  </tr>\n";
  v7122000to8032000();
  v8032000to8072000();
  v8072000to8212000();
  v8212000to9052000();
  v9052000to9072000();
  v9072000to0_9_1();
  v0_9_1to0_9_2();
  v0_9_2to0_9_3pre5();
  $db->query("update applications set app_version='".$phpgw_info["server"]["version"]."' where (app_name='admin' or app_name='filemanager' or app_name='addressbook' or app_name='todo' or app_name='calendar' or app_name='email' or app_name='nntp' or app_name='cron_apps')");

  $db->query("update config set config_value='" . $phpgw_info["server"]["version"] . "' where "
           . "config_name='phpgroupware_api_version'");


  if (!$didupgrade == True){
    echo "  <tr bgcolor=\"e6e6e6\">\n";
    echo "    <td>No table changes were needed. The script only updated your version setting.</td>\n";
    echo "  </tr>\n";
  }

  echo "</table>\n";
?>
