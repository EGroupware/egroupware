<?php
  /**************************************************************************\
  * phpGroupWare - Setup                                                     *
  * http://www.phpgroupware.org                                              *
  * --------------------------------------------                             *
  *  This program is free software; you can redistribute it and/or modify it *
  *  under the terms of the GNU General Public License as published by the   *
  *  Free Software Foundation; either version 2 of the License, or");at your  *
  *  option) any later version.                                              *
  \**************************************************************************/

  /* $Id$ */

  $phpgw_setup->db->query("DROP TABLE phpgw_config");
  $phpgw_setup->db->query("DROP TABLE phpgw_applications");
  $phpgw_setup->db->query("DROP TABLE phpgw_accounts");
  $phpgw_setup->db->query("DROP TABLE phpgw_preferences");
  $phpgw_setup->db->query("DROP TABLE phpgw_sessions");
  $phpgw_setup->db->query("DROP TABLE phpgw_app_sessions");
  $phpgw_setup->db->query("DROP TABLE phpgw_acl");
  $phpgw_setup->db->query("DROP TABLE phpgw_hooks");
  $phpgw_setup->db->query("DROP TABLE phpgw_access_log");
  $phpgw_setup->db->query("DROP TABLE phpgw_categories");
  $phpgw_setup->db->query("DROP TABLE profiles");
  $phpgw_setup->db->query("DROP TABLE phpgw_addressbook");
  $phpgw_setup->db->query("DROP TABLE phpgw_addressbook_extra");
  $phpgw_setup->db->query("DROP TABLE todo");
  $phpgw_setup->db->query("DROP TABLE calendar_entry");
  $phpgw_setup->db->query("DROP TABLE calendar_entry_repeats");
  $phpgw_setup->db->query("DROP TABLE calendar_entry_user");
  $phpgw_setup->db->query("DROP TABLE newsgroups");
  $phpgw_setup->db->query("DROP TABLE news_msg");
  $phpgw_setup->db->query("DROP TABLE lang");
  $phpgw_setup->db->query("DROP TABLE languages");
  $phpgw_setup->db->query("DROP TABLE customers");
  $phpgw_setup->db->query("DROP TABLE notes");

	/* Legacy tables */

  $phpgw_setup->db->query("DROP TABLE config");
  $phpgw_setup->db->query("DROP TABLE applications");
  $phpgw_setup->db->query("DROP TABLE groups");
  $phpgw_setup->db->query("DROP TABLE accounts");
  $phpgw_setup->db->query("DROP TABLE preferences");
?>