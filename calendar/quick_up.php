<?php
  /**************************************************************************\
  * phpGroupWare - Calendar                                                  *
  * http://www.phpgroupware.org                                              *
  * Based on Webcalendar by Craig Knudsen <cknudsen@radix.net>               *
  *          http://www.radix.net/~cknudsen                                  *
  * --------------------------------------------                             *
  *  This program is free software; you can redistribute it and/or modify it *
  *  under the terms of the GNU General Public License as published by the   *
  *  Free Software Foundation; either version 2 of the License, or (at your  *
  *  option) any later version.                                              *
  \**************************************************************************/

  /* $Id$ */

  $phpgw_info["flags"] = array("currentapp" => "admin", "enable_nextmatchs_class" => True);
  include("../header.inc.php");

    $db = $phpgw->db;
    $db2 = $phpgw->db;
	$sql = "CREATE TABLE calendar_entry (
	  cal_id		int(11) DEFAULT '0' NOT NULL auto_increment,
	  cal_owner		int(11) DEFAULT '0' NOT NULL,
	  cal_group		varchar(255),
	  cal_datetime		int(11),
	  cal_mdatetime		int(11),
	  cal_duration		int(11) DEFAULT '0' NOT NULL,
	  cal_priority		int(11) DEFAULT '2' NOT NULL,
	  cal_type		varchar(10),
	  cal_access		varchar(10),
	  cal_name		varchar(80) NOT NULL,
	  cal_description	text,
	  PRIMARY KEY (cal_id)
	)";
	$db->query($sql,__LINE__,__FILE__);
	$db->query("SELECT count(*) FROM webcal_entry",__LINE__,__FILE__);
	$db->next_record();
	if($db->f(0)) {
	  $db->query("SELECT cal_id,cal_owner,cal_duration,cal_priority,cal_type,cal_access,cal_name,cal_description,cal_id,cal_date,cal_time,cal_mod_date,cal_mod_time FROM webcal_entry ORDER BY cal_id",__LINE__,__FILE__);
	  while($db->next_record()) {
	    $cal_id = $db->f("cal_id");
	    $cal_owner = $db->f("cal_owner");
	    $cal_duration = $db->f("cal_duration");
	    $cal_priority = $db->f("cal_priority");
	    $cal_type = $db->f("cal_type");
	    $cal_access = $db->f("cal_access");
	    $cal_name = $db->f("cal_name");
	    $cal_description = $db->f("cal_description");
	    $datetime = mktime(intval(strrev(substr(strrev($db->f("cal_time")),4))),intval(strrev(substr(strrev($db->f("cal_time")),2,2))),intval(strrev(substr(strrev($db->f("cal_time")),0,2))),intval(substr($db->f("cal_date"),4,2)),intval(substr($db->f("cal_date"),6,2)),intval(substr($db->f("cal_date"),0,4)));
	    $moddatetime = mktime(intval(strrev(substr(strrev($db->f("cal_mod_time")),4))),intval(strrev(substr(strrev($db->f("cal_mod_time")),2,2))),intval(strrev(substr(strrev($db->f("cal_mod_time")),0,2))),intval(substr($db->f("cal_mod_date"),4,2)),intval(substr($db->f("cal_mod_date"),6,2)),intval(substr($db->f("cal_mod_date"),0,4)));
	    $db2->query("SELECT groups FROM webcal_entry_groups WHERE cal_id=".$cal_id,__LINE__,__FILE__);
	    $db2->next_record();
	    $cal_group = $db2->f("groups");
	    $db2->query("INSERT INTO calendar_entry(cal_id,cal_owner,cal_group,cal_datetime,cal_mdatetime,cal_duration,cal_priority,cal_type,cal_access,cal_name,cal_description) "
		       ."VALUES(".$cal_id.",'".$cal_owner."','".$cal_group."',".$datetime.",".$moddatetime.",".$cal_duration.",".$cal_priority.",'".$cal_type."','".$cal_access."','".$cal_name."','".$cal_description."')",__LINE__,__FILE__);
	  }
	}
	$db->query("DROP TABLE webcal_entry_groups");
	$db->query("DROP TABLE webcal_entry");

	$sql = "CREATE TABLE calendar_entry_user (
			cal_id		int(11) DEFAULT '0' NOT NULL,
			cal_login	int(11) DEFAULT '0' NOT NULL,
			cal_status	char(1) DEFAULT 'A',
			PRIMARY KEY (cal_id, cal_login)
	)";
	$db->query($sql,__LINE__,__FILE__);
	$db->query("SELECT count(*) FROM webcal_entry_user",__LINE__,__FILE__);
	$db->next_record();
	if($db->f(0)) {
	  $db->query("SELECT cal_id,cal_login,cal_status FROM webcal_entry_user ORDER BY cal_id",__LINE__,__FILE__);
	  while($db->next_record()) {
	    $cal_id = $db->f("cal_id");
	    $cal_login = $db->f("cal_login");
	    $cal_status = $db->f("cal_status");
	    $db2->query("INSERT INTO calendar_entry_user(cal_id,cal_login,cal_status) VALUES(".$cal_id.",".$cal_login.",'".$cal_status."')",__LINE__,__FILE__);
	  }
	}
	$db->query("DROP TABLE webcal_entry_user",__LINE__,__FILE__);

	$sql = "CREATE TABLE calendar_entry_repeats (
			cal_id		int(11) DEFAULT 0 NOT NULL,
			cal_type	enum('daily','weekly','monthlyByDay','monthlyByDate','yearly') DEFAULT 'daily' NOT NULL,
			cal_use_end	int DEFAULT '0',
			cal_end		int(11),
			cal_frequency	int DEFAULT '1',
			cal_days	char(7)
	)";
	$db->query($sql,__LINE__,__FILE__);
	$db->query("SELECT count(*) FROM webcal_entry_repeats",__LINE__,__FILE__);
	$db->next_record();
	if($db->f(0)) {
	  $db->query("SELECT cal_id,cal_type,cal_end,cal_frequency,cal_days FROM webcal_entry_repeats ORDER BY cal_id",__LINE__,__FILE__);
	  while($db->next_record()) {
	    $cal_id = $db->f("cal_id");
	    $cal_type = $db->f("cal_type");
	    if(isset($db->Record["cal_end"])) {
	      $enddate = mktime(0,0,0,intval(substr($db->f("cal_end"),4,2)),intval(substr($db->f("cal_end"),6,2)),intval(substr($db->f("cal_end"),0,4)));
	      $useend = 1;
	    } else {
	      $enddate = 0;
	      $useend = 0;
	    }
	    $cal_frequency = $db->f("cal_frequency");
	    $cal_days = $db->f("cal_days");
	    $db2->f("INSERT INTO calendar_entry_repeats(cal_id,cal_type,cal_use_end,cal_end,cal_frequency,cal_days) VALUES(".$cal_id.",'".$cal_type."',".$useend.",".$enddate.",".$cal_frequency.",'".$cal_days."')",__LINE__,__FILE__);
	  }
	}
	$db->query("DROP TABLE webcal_entry_repeats",__LINE__,__FILE__);
	$db->query("UPDATE applications SET app_tables='calendar_entry,calendar_entry_user,calendar_entry_repeats', app_version='0.9.7pre2' WHERE app_name='calendar'",__LINE__,__FILE__);
	$db->query("UPDATE applications set app_version='0.9.7pre2' where app_name='admin'",__LINE__,__FILE__);

?>  
