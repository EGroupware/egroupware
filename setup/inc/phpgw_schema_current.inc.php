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

$phpgw_tables = array(
		"config" => array(
			"fd" => array(
				"config_name" => array("type" => "varchar", "precision" => 255, "nullable" => false),
				"config_value" => array("type" => "varchar", "precision" => 100)
			),
			"pk" => array(),
			"ix" => array(),
			"fk" => array(),
			"uc" => array()
		),
		"applications" => array(
			"fd" => array(
				"app_name" => array("type" => "varchar", "precision" => 25, "nullable" => false),
				"app_title" => array("type" => "varchar", "precision" => 50),
				"app_enabled" => array("type" => "int", "precision" => 4),
				"app_order" => array("type" => "int", "precision" => 4),
				"app_tables" => array("type" => "varchar", "precision" => 255),
				"app_version" => array("type" => "varchar", "precision" => 20, "nullable" => false, "default" => "0.0")
			),
			"pk" => array(),
			"ix" => array(),
			"fk" => array(),
			"uc" => array("app_name")
		),
		"accounts" => array(
			"fd" => array(
				"account_id" => array("type" => "auto", "nullable" => false),
				"account_lid" => array("type" => "varchar", "precision" => 25, "nullable" => false),
				"account_pwd" => array("type" => "varchar", "precision" => 32, "nullable" => false),
				"account_firstname" => array("type" => "varchar", "precision" => 50),
				"account_lastname" => array("type" => "varchar", "precision" => 50),
				"account_permissions" => array("type" => "text"),
				"account_groups" => array("type" => "varchar", "precision" => 30),
				"account_lastlogin" => array("type" => "int", "precision" => 4),
				"account_lastloginfrom" => array("type" => "varchar", "precision" => 255),
				"account_lastpwd_change" => array("type" => "int", "precision" => 4),
				"account_status" => array("type" => "char", "precision" => 1, "nullable" => false, "default" => "A")
			),
			"pk" => array("account_id"),
			"ix" => array(),
			"fk" => array(),
			"uc" => array("account_lid")
		),
		"groups" => array(
			"fd" => array(
				"group_id" => array("type" => "auto", "nullable" => false),
				"group_name" => array("type" => "varchar", "precision" => 255),
				"group_apps" => array("type" => "varchar", "precision" => 255)
			),
			"pk" => array("group_id"),
			"ix" => array(),
			"fk" => array(),
			"uc" => array()
		),
		"preferences" => array(
			"fd" => array(
				"preference_owner" => array("type" => "int", "precision" => 4, "nullable" => false),
				"preference_value" => array("type" => "text")
			),
			"pk" => array(),
			"ix" => array(),
			"fk" => array(),
			"uc" => array()
		),
		"phpgw_sessions" => array(
			"fd" => array(
				"session_id" => array("type" => "varchar", "precision" => 255, "nullable" => false),
				"session_lid" => array("type" => "varchar", "precision" => 255),
				"session_ip" => array("type" => "varchar", "precision" => 255),
				"session_logintime" => array("type" => "int", "precision" => 4),
				"session_dla" => array("type" => "int", "precision" => 4),
				"session_info" => array("type" => "text")
			),
			"pk" => array(),
			"ix" => array(),
			"fk" => array(),
			"uc" => array("session_id")
		),
		"phpgw_acl" => array(
			"fd" => array(
				"acl_appname" => array("type" => "varchar", "precision" => 50),
				"acl_location" => array("type" => "varchar", "precision" => 255),
				"acl_account" => array("type" => "int", "precision" => 4),
				"acl_account_type" => array("type" => "char", "precision" => 1),
				"acl_rights" => array("type" => "int", "precision" => 4)
			),
			"pk" => array(),
			"ix" => array(),
			"fk" => array(),
			"uc" => array()
		),
		"phpgw_app_sessions" => array(
			"fd" => array(
				"sessionid" => array("type" => "varchar", "precision" => 255, "nullable" => false),
				"loginid" => array("type" => "varchar", "precision" => 20),
				"app" => array("type" => "varchar", "precision" => 20),
				"content" => array("type" => "text")
			),
			"pk" => array(),
			"ix" => array(),
			"fk" => array(),
			"uc" => array()
		),
		"phpgw_access_log" => array(
			"fd" => array(
				"sessionid" => array("type" => "varchar", "precision" => 255),
				"loginid" => array("type" => "varchar", "precision" => 30),
				"ip" => array("type" => "varchar", "precision" => 30),
				"li" => array("type" => "int", "precision" => 4),
				"lo" => array("type" => "varchar", "precision" => 255)
			),
			"pk" => array(),
			"ix" => array(),
			"fk" => array(),
			"uc" => array()
		),
		"profiles" => array(
			"fd" => array(
				"con" => array("type" => "auto", "nullable" => false),
				"owner" => array("type" => "varchar", "precision" => 20),
				"title" => array("type" => "varchar", "precision" => 255),
				"phone_number" => array("type" => "varchar", "precision" => 255),
				"comments" => array("type" => "text"),
				"picture_format" => array("type" => "varchar", "precision" => 255),
				"picture" => array("type" => "blob")
			),
			"pk" => array("con"),
			"ix" => array(),
			"fk" => array(),
			"uc" => array()
		),
		"addressbook" => array(
			"fd" => array(
				"ab_id" => array("type" => "auto", "nullable" => false),
				"ab_owner" => array("type" => "varchar", "precision" => 25),
				"ab_access" => array("type" => "varchar", "precision" => 10),
				"ab_firstname" => array("type" => "varchar", "precision" => 255),
				"ab_lastname" => array("type" => "varchar", "precision" => 255),
				"ab_email" => array("type" => "varchar", "precision" => 255),
				"ab_hphone" => array("type" => "varchar", "precision" => 255),
				"ab_wphone" => array("type" => "varchar", "precision" => 255),
				"ab_fax" => array("type" => "varchar", "precision" => 255),
				"ab_pager" => array("type" => "varchar", "precision" => 255),
				"ab_mphone" => array("type" => "varchar", "precision" => 255),
				"ab_ophone" => array("type" => "varchar", "precision" => 255),
				"ab_street" => array("type" => "varchar", "precision" => 255),
				"ab_city" => array("type" => "varchar", "precision" => 255),
				"ab_state" => array("type" => "varchar", "precision" => 255),
				"ab_zip" => array("type" => "varchar", "precision" => 255),
				"ab_bday" => array("type" => "varchar", "precision" => 255),
				"ab_notes" => array("type" => "text"),
				"ab_company" => array("type" => "varchar", "precision" => 255),
				"ab_company_id" => array("type" => "int", "precision" => 4),
				"ab_title" => array("type" => "varchar", "precision" => 60),
				"ab_address2" => array("type" => "varchar", "precision" => 60),
				"ab_url" => array("type" => "varchar", "precision" => 255)
			),
			"pk" => array("ab_id"),
			"ix" => array(),
			"fk" => array(),
			"uc" => array()
		),
		"customers" => array(
			"fd" => array(
				"company_id" => array("type" => "auto", "nullable" => false),
				"company_name" => array("type" => "varchar", "precision" => 255),
				"website" => array("type" => "varchar", "precision" => 80),
				"ftpsite" => array("type" => "varchar", "precision" => 80),
				"industry_type" => array("type" => "varchar", "precision" => 50),
				"status" => array("type" => "varchar", "precision" => 30),
				"software" => array("type" => "varchar", "precision" => 40),
				"lastjobnum" => array("type" => "int", "precision" => 4),
				"lastjobfinished" => array("type" => "date"),
				"busrelationship" => array("type" => "varchar", "precision" => 30),
				"notes" => array("type" => "text")
			),
			"pk" => array("company_id"),
			"ix" => array(),
			"fk" => array(),
			"uc" => array()
		),
		"todo" => array(
			"fd" => array(
				"todo_id" => array("type" => "auto", "nullable" => false),
				"todo_id_parent" => array("type" => "int", "precision" => 4, "nullable" => false, "default" => "0"),
				"todo_owner" => array("type" => "varchar", "precision" => 25),
				"todo_access" => array("type" => "varchar", "precision" => 10),
				"todo_des" => array("type" => "text"),
				"todo_pri" => array("type" => "int", "precision" => 4),
				"todo_status" => array("type" => "int", "precision" => 4),
				"todo_datecreated" => array("type" => "int", "precision" => 4),
				"todo_startdate" => array("type" => "int", "precision" => 4),
				"todo_enddate" => array("type" => "int", "precision" => 4)
			),
			"pk" => array("todo_id"),
			"ix" => array(),
			"fk" => array(),
			"uc" => array()
		),
		"calendar_entry" => array(
			"fd" => array(
				"cal_id" => array("type" => "auto", "nullable" => false),
				"cal_owner" => array("type" => "int", "precision" => 4, "nullable" => false, "default" => "0"),
				"cal_group" => array("type" => "varchar", "precision" => 255),
				"cal_datetime" => array("type" => "int", "precision" => 4),
				"cal_mdatetime" => array("type" => "int", "precision" => 4),
				"cal_edatetime" => array("type" => "int", "precision" => 4),
				"cal_priority" => array("type" => "int", "precision" => 4, "default" => "2", "nullable" => false),
				"cal_type" => array("type" => "varchar", "precision" => 10),
				"cal_access" => array("type" => "varchar", "precision" => 10),
				"cal_name" => array("type" => "varchar", "precision" => 80, "nullable" => false),
				"cal_description" => array("type" => "text")
			),
			"pk" => array("cal_id"),
			"ix" => array(),
			"fk" => array(),
			"uc" => array()
		),
		"calendar_entry_repeats" => array(
			"fd" => array(
				"cal_id" => array("type" => "int", "precision" => 4, "default" => "0", "nullable" => false),
				"cal_type" => array("type" => "varchar", "precision" => 20, "default" => "daily", "nullable" => false),
				"cal_use_end" => array("type" => "int", "precision" => 4, "default" => "0"),
				"cal_frequency" => array("type" => "int", "precision" => 4, "default" => "1"),
				"cal_days" => array("type" => "char", "precision" => 7)
			),
			"pk" => array(),
			"ix" => array(),
			"fk" => array(),
			"uc" => array()
		),
		"calendar_entry_user" => array(
			"fd" => array(
				"cal_id" => array("type" => "int", "precision" => 4, "nullable" => false, "default" => "0"),
				"cal_login" => array("type" => "int", "precision" => 4, "nullable" => false, "default" => "0"),
				"cal_status" => array("type" => "char", "precision" => 1, "default" => "A")
			),
			"pk" => array("cal_id", "cal_login"),
			"ix" => array(),
			"fk" => array(),
			"uc" => array()
		),
		"newsgroups" => array(
			"fd" => array(
				"con" => array("type" => "auto", "nullable" => false),
				"name" => array("type" => "varchar", "precision" => 255, "nullable" => false),
				"messagecount" => array("type" => "int", "precision" => 4, "nullable" => false),
				"lastmessage" => array("type" => "int", "precision" => 4, "nullable" => false),
				"active" => array("type" => "char", "precision" => 1, "nullable" => false, "default" => "N"),
				"lastread" => array("type" => "int", "precision" => 4)
			),
			"pk" => array("con"),
			"ix" => array(),
			"fk" => array(),
			"uc" => array("name")
		),
		"news_msg" => array(
			"fd" => array(
				"con" => array("type" => "int", "precision" => 4, "nullable" => false),
				"msg" => array("type" => "int", "precision" => 4, "nullable" => false),
				"uid" => array("type" => "varchar", "precision" => 255, "default" => ""),
				"udate" => array("type" => "int", "precision" => 4, "default" => "0"),
				"path" => array("type" => "varchar", "precision" => 255, "default" => ""),
				"fromadd" => array("type" => "varchar", "precision" => 255, "default" => ""),
				"toadd" => array("type" => "varchar", "precision" => 255, "default" => ""),
				"ccadd" => array("type" => "varchar", "precision" => 255, "default" => ""),
				"bccadd" => array("type" => "varchar", "precision" => 255, "default" => ""),
				"reply_to" => array("type" => "varchar", "precision" => 255, "default" => ""),
				"sender" => array("type" => "varchar", "precision" => 255, "default" => ""),
				"return_path" => array("type" => "varchar", "precision" => 255, "default" => ""),
				"subject" => array("type" => "varchar", "precision" => 255, "default" => ""),
				"message_id" => array("type" => "varchar", "precision" => 255, "default" => ""),
				"reference" => array("type" => "varchar", "precision" => 255, "default" => ""),
				"in_reply_to" => array("type" => "varchar", "precision" => 255, "default" => ""),
				"follow_up_to" => array("type" => "varchar", "precision" => 255, "default" => ""),
				"nntp_posting_host" => array("type" => "varchar", "precision" => 255, "default" => ""),
				"nntp_posting_date" => array("type" => "varchar", "precision" => 255, "default" => ""),
				"x_complaints_to" => array("type" => "varchar", "precision" => 255, "default" => ""),
				"x_trace" => array("type" => "varchar", "precision" => 255, "default" => ""),
				"x_abuse_info" => array("type" => "varchar", "precision" => 255, "default" => ""),
				"x_mailer" => array("type" => "varchar", "precision" => 255, "default" => ""),
				"organization" => array("type" => "varchar", "precision" => 255, "default" => ""),
				"content_type" => array("type" => "varchar", "precision" => 255, "default" => ""),
				"content_description" => array("type" => "varchar", "precision" => 255, "default" => ""),
				"content_transfer_encoding" => array("type" => "varchar", "precision" => 255, "default" => ""),
				"mime_version" => array("type" => "varchar", "precision" => 255, "default" => ""),
				"msgsize" => array("type" => "int", "precision" => 4, "default" => "0"),
				"msglines" => array("type" => "int", "precision" => 4, "default" => "0"),
				"body" => array("type" => "text") // TODO: MySQL is longtext - any discrepancies?
			),
			"pk" => array("con", "msg"),
			"ix" => array(),
			"fk" => array(),
			"uc" => array()
		),
		"lang" => array(
			"fd" => array(
				"message_id" => array("type" => "varchar", "precision" => 150, "nullable" => false, "default" => ""),
				"app_name" => array("type" => "varchar", "precision" => 100, "nullable" => false, "default" => "common"),
				"lang" => array("type" => "varchar", "precision" => 5, "nullable" => false, "default" => ""),
				"content" => array("type" => "text", "nullable" => false)
			),
			"pk" => array("message_id", "app_name", "lang"),
			"ix" => array(),
			"fk" => array(),
			"uc" => array()
		),
		"categories" => array(
			"fd" => array(
				"cat_id" => array("type" => "auto", "nullable" => false),
				"account_id" => array("type" => "int", "precision" => 4, "nullable" => false, "default" => "0"),
				"app_name" => array("type" => "varchar", "precision" => 25, "nullable" => false),
				"cat_name" => array("type" => "varchar", "precision" => 150, "nullable" => false),
				"cat_description" => array("type" => "text", "nullable" => false)
			),
			"pk" => array("cat_id"),
			"ix" => array(),
			"fk" => array(),
			"uc" => array()
		),
		"languages" => array(
			"fd" => array(
				"lang_id" => array("type" => "varchar", "precision" => 2, "nullable" => false),
				"lang_name" => array("type" => "varchar", "precision" => 50, "nullable" => false),
				"available" => array("type" => "char", "precision" => 3, "nullable" => false, "default" => "No")
			),
			"pk" => array("lang_id"),
			"ix" => array(),
			"fk" => array(),
			"uc" => array()
		),
		"notes" => array(
			"fd" => array(
				"note_id" => array("type" => "auto", "nullable" => false),
				"note_owner" => array("type" => "int", "precision" => 4),
				"note_date" => array("type" => "int", "precision" => 4),
				"note_content" => array("type" => "text")
			),
			"pk" => array("note_id"),
			"ix" => array(),
			"fk" => array(),
			"uc" => array()
		),
		"phpgw_hooks" => array(
			"fd" => array(
				"hook_id" => array("type" => "auto", "nullable" => false),
				"hook_appname" => array("type" => "varchar", "precision" => 255),
				"hook_location" => array("type" => "varchar", "precision" => 255),
				"hook_filename" => array("type" => "varchar", "precision" => 255)
			),
			"pk" => array("hook_id"),
			"ix" => array(),
			"fk" => array(),
			"uc" => array()
		)
	);
?>
