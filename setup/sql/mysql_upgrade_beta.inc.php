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

  function v0_9_1to0_9_2(){
    global $currentver, $phpgw_info, $db;
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

	    //install weather support
      $db->query("insert into applications (app_name, app_title, app_enabled, app_order, app_tables, app_version) values ('weather', 'Weather', 1, 12, NULL, '".$phpgw_info["server"]["version"]."')");
      $db->query("INSERT INTO lang (message_id, app_name, lang, content) VALUES( 'weather','Weather','en','weather')");

      echo "  <tr bgcolor=\"e6e6e6\">\n";
      echo "    <td>Upgrade from 0.9.1 to 0.9.2 is completed.</td>\n";
      echo "  </tr>\n";
      $currentver = "0.9.2";
      update_version_table();
    }
  }

  function v0_9_2to0_9_3update_owner($table,$field){
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

  function v0_9_2to0_9_3(){
    global $currentver, $phpgw_info, $db;

    // The 0.9.3pre1 is only temp until release
    if ($currentver == "0.9.2" || $currentver == "0.9.3pre1" || $currentver == "0.9.3pre2" || $currentver == "0.9.3pre3" || $currentver == "0.9.3pre4" || $currentver == "0.9.3pre5" || $currentver == "0.9.3pre6") {
      if ($currentver == "0.9.2" || $currentver == "0.9.3pre1") {
	      v0_9_2to0_9_3update_owner("addressbook","ab_owner");
	      v0_9_2to0_9_3update_owner("todo","todo_owner");
	      v0_9_2to0_9_3update_owner("webcal_entry","cal_create_by");
	      v0_9_2to0_9_3update_owner("webcal_entry_user","cal_login");
	      $currentver = "0.9.3pre2";
        update_version_table();
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
        update_version_table();
      }
      if ($currentver == "0.9.3pre3") {
	      $db->query("alter table todo add todo_id_parent int(11) DEFAULT '0' NOT NULL");
        $currentver = "0.9.3pre4";
        update_version_table();
      }
     
      if ($currentver == "0.9.3pre4") {
        $db->query("alter table config change config_name config_name varchar(255) NOT NULL");

        // I decied too hold off on this table until 0.9.4pre1 (jengo)
//        $db->query("create table domains (domain_id int NOT NULL auto_increment, domain_name varchar(255),"
//         . "domain_database varchar(255),domain_status enum('Active,Disabled'),primary key(domain_id))");
        $currentver = "0.9.3pre5";
        update_version_table();
      }

      if ($currentver == "0.9.3pre5") {
         $db->query("CREATE TABLE categories (
                      cat_id          int(9) DEFAULT '0' NOT NULL auto_increment,
                      account_id      int(11) DEFAULT '0' NOT NULL,
                      app_name        varchar(25) NOT NULL,
                      cat_name        varchar(150) NOT NULL,
                      cat_description text NOT NULL,
                      PRIMARY KEY (cat_id))"
                   );
         $currentver = "0.9.3pre6";
         update_version_table();
      }

      if ($currentver == "0.9.3pre6") {
         $db->query("alter table addressbook add ab_url varchar(255)");
         $db->query("insert into applications (app_name, app_title, app_enabled, app_order, app_tables, app_version) values ('transy', 'Translation Management', 0, 13, NULL, '".$phpgw_info["server"]["version"]."')");
         $currentver = "0.9.3pre7";
         update_version_table();
      }

      echo "  <tr bgcolor=\"e6e6e6\">\n";
      echo "    <td>Upgrade from 0.9.2 to $currentver is completed.</td>\n";
      echo "  </tr>\n";
    }
  }

  v0_9_1to0_9_2();
  v0_9_2to0_9_3();
  
?>