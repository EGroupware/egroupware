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

  $db->query("DROP TABLE config");
  $db->query("DROP TABLE applications");
  $db->query("drop sequence accounts_account_id_seq");
  $db->query("DROP TABLE accounts");
  $db->query("drop sequence groups_group_id_seq");
  $db->query("DROP TABLE groups");
  $db->query("DROP TABLE preferences");
  $db->query("DROP TABLE phpgw_sessions");
  $db->query("DROP TABLE phpgw_app_sessions");
  $db->query("DROP TABLE phpgw_acl");
  $db->query("DROP TABLE phpgw_access_log");
  $db->query("drop sequence profiles_con_seq");
  $db->query("DROP TABLE profiles");
  $db->query("drop sequence addressbook_ab_id_seq");
  $db->query("DROP TABLE addressbook");
  $db->query("drop sequence webcal_entry_cal_id_seq");
  $db->query("DROP TABLE todo");
  $db->query("DROP TABLE calendar_entry");
  $db->query("DROP TABLE calendar_entry_repeats");
  $db->query("DROP TABLE calendar_entry_user");
  $db->query("drop sequence newsgroups_con_seq");
  $db->query("DROP TABLE newsgroups");
  $db->query("DROP TABLE users_newsgroups");
  $db->query("DROP TABLE lang");
  $db->query("drop sequence news_msg_con_seq");
  $db->query("DROP TABLE news_msg");
  $db->query("DROP TABLE languages");
  $db->query("drop sequence categories_cat_id_seq");
  $db->query("DROP TABLE categories");
  $db->query("DROP TABLE notes");
?>