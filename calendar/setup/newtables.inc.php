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
  
  // NOTE: Please use spaces to seperate the field names.  It makes copy and pasting easier.

  $sql = "CREATE TABLE calendar_entry (
    cal_id		int(11) DEFAULT '0' NOT NULL auto_increment,
    cal_owner 		int(11) DEFAULT '0' NOT NULL,
    cal_group		varchar(255),
    cal_datetime	int(11),
    cal_mdatetime	int(11),
    cal_edatetime 	int(11),
    cal_priority 	int(11) DEFAULT '2' NOT NULL,
    cal_type		varchar(10),
    cal_access		varchar(10),
    cal_name		varchar(80) NOT NULL,
    cal_description 	text,
    PRIMARY KEY (cal_id)
  )";
  $db->query($sql);  

  $sql = "CREATE TABLE calendar_entry_repeats (
    cal_id		int(11) DEFAULT '0' NOT NULL,
    cal_type		enum('daily','weekly','monthlyByDay','monthlyByDate','yearly') DEFAULT 'daily' NOT NULL,
    cal_use_end		int DEFAULT '0',
    cal_end		int(11),
    cal_frequency	int(11) DEFAULT '1',
    cal_days		char(7)
  )";
  $db->query($sql);  

  $sql = "CREATE TABLE calendar_entry_user (
    cal_id       int(11) DEFAULT '0' NOT NULL,
    cal_login    int(11) DEFAULT '0' NOT NULL,
    cal_status   char(1) DEFAULT 'A',
    PRIMARY KEY (cal_id, cal_login)
  )";
  $db->query($sql);  

  $currentver = "0.9.8pre5";
  $oldversion = $currentver;
  update_app_version("calendar");
?>
