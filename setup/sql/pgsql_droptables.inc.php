<?php
  /**************************************************************************\
  * phpGroupWare - Setup                                                     *
  * http://www.phpgroupware.org                                              *
  * --------------------------------------------                             *
  *  This program is free software; you can redistribute it and/or modify it *
  *  under the terms of the GNU General Public License as published by the   *
  *  Free Software Foundation; either version 2 of the License, or");at your *
  *  option) any later version.                                              *
  \**************************************************************************/

  /* $Id$ */

  $phpgw_setup->db->query("DROP TABLE config");
  $phpgw_setup->db->query("DROP TABLE applications");
  $phpgw_setup->db->query("drop sequence accounts_account_id_seq");
  $phpgw_setup->db->query("DROP TABLE accounts");
  $phpgw_setup->db->query("drop sequence groups_group_id_seq");
  $phpgw_setup->db->query("DROP TABLE groups");
  $phpgw_setup->db->query("DROP TABLE preferences");
  $phpgw_setup->db->query("DROP TABLE phpgw_sessions");
  $phpgw_setup->db->query("DROP TABLE phpgw_app_sessions");
  $phpgw_setup->db->query("DROP TABLE phpgw_acl");
  $phpgw_setup->db->query("DROP TABLE phpgw_access_log");
  $phpgw_setup->db->query("drop sequence profiles_con_seq");
  $phpgw_setup->db->query("DROP TABLE profiles");
  $phpgw_setup->db->query("drop sequence addressbook_ab_id_seq");
  $phpgw_setup->db->query("DROP TABLE addressbook");
  $phpgw_setup->db->query("drop sequence calendar_entry_cal_id_seq");
  $phpgw_setup->db->query("drop sequence todo_todo_id_seq");
  $phpgw_setup->db->query("DROP TABLE todo");
  $phpgw_setup->db->query("DROP TABLE calendar_entry");
  $phpgw_setup->db->query("DROP TABLE calendar_entry_user");
  $phpgw_setup->db->query("DROP TABLE calendar_entry_repeats");
  $phpgw_setup->db->query("drop sequence newsgroups_con_seq");
  $phpgw_setup->db->query("DROP TABLE newsgroups");
  $phpgw_setup->db->query("DROP TABLE lang");
  $phpgw_setup->db->query("drop sequence news_msg_con_seq");
  $phpgw_setup->db->query("DROP TABLE news_msg");
  $phpgw_setup->db->query("DROP TABLE languages");
  $phpgw_setup->db->query("drop sequence categories_cat_id_seq");
  $phpgw_setup->db->query("DROP TABLE categories");
  $phpgw_setup->db->query("drop sequence phpgw_categories_cat_id_seq");
  $phpgw_setup->db->query("DROP TABLE phpgw_categories");
  $phpgw_setup->db->query("DROP sequence notes_note_id_seq");
  $phpgw_setup->db->query("DROP TABLE notes");
  $phpgw_setup->db->query("drop sequence phpgw_hooks_hook_id_seq");
  $phpgw_setup->db->query("DROP TABLE phpgw_hooks");
?>