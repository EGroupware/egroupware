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

	$phpgw_baseline = array(
		"webcal_entry" => array(
			"fd" => array(
				"cal_id" => array("type" => "auto", "nullable" => false),
				"cal_group_id" => array("type" => "int", "precision" => 4),
				"cal_create_by" => array("type" => "varchar", "precision" => 25, "nullable" => false),
				"cal_date" => array("type" => "int", "precision" => 4, "nullable" => false, "default" => "0"),
				"cal_time" => array("type" => "int", "precision" => 4),
				"cal_mod_date" => array("type" => "int", "precision" => 4),
				"cal_mod_time" => array("type" => "int", "precision" => 4),
				"cal_duration" => array("type" => "int", "precision" => 4, "nullable" => false, "default" => "0"),
				"cal_priority" => array("type" => "int", "precision" => 4, "default" => "2"),
				"cal_type" => array("type" => "varchar", "precision" => 10),
				"cal_access" => array("type" => "char", "precision" => 10),
				"cal_name" => array("type" => "varchar", "precision" => 80, "nullable" => false),
				"cal_description" => array("type" => "text")
			),
			"pk" => array("cal_id"),
			"fk" => array(),
			"ix" => array(),
			"uc" => array()
		),
		"webcal_entry_repeats" => array(
			"fd" => array(
				"cal_id" => array("type" => "int", "precision" => 4, "nullable" => false, "default" => "0"),
				"cal_type" => array("type" => "varchar", "precision" => 20, "nullable" => false, "default" => "daily"),
				"cal_end" => array("type" => "int", "precision" => 4),
				"cal_frequency" => array("type" => "int", "precision" => 4, "default" => "1"),
				"cal_days" => array("type" => "char", "precision" => 7)
			),
			"pk" => array(),
			"fk" => array(),
			"ix" => array(),
			"uc" => array()
		),
		"webcal_entry_user" => array(
			"fd" => array(
				"cal_id" => array("type" => "int", "precision" => 4, "nullable" => false, "default" => "0"),
				"cal_login" => array("type" => "varchar", "precision" => 25, "nullable" => false),
				"cal_status" => array("type" => "char", "precision" => 1, "default" => "A")
			),
			"pk" => array("cal_id", "cal_login"),
			"fk" => array(),
			"ix" => array(),
			"uc" => array()
		),
		"webcal_entry_groups" => array(
			"fd" => array(
				"cal_id" => array("type" => "int", "precision" => 4),
				"groups" => array("type" => "varchar", "precision" => 255)
			),
			"pk" => array(),
			"fk" => array(),
			"ix" => array(),
			"uc" => array()
		)
	);
?>
