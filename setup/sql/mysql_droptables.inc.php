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

  $db->query("DROP TABLE config");
  $db->query("DROP TABLE applications");
  $db->query("DROP TABLE accounts");
  $db->query("DROP TABLE groups");
  $db->query("DROP TABLE preferences");
  $db->query("DROP TABLE phpgw_sessions");
  $db->query("DROP TABLE phpgw_app_sessions");
  $db->query("DROP TABLE phpgw_acl");
  $db->query("DROP TABLE phpgw_access_log");
  $db->query("DROP TABLE profiles");
  $db->query("DROP TABLE addressbook");
  $db->query("DROP TABLE todo");
  $db->query("DROP TABLE calendar_entry");
  $db->query("DROP TABLE calendar_entry_repeats");
  $db->query("DROP TABLE calendar_entry_user");
  $db->query("DROP TABLE newsgroups");
  $db->query("DROP TABLE news_msg");
  $db->query("DROP TABLE lang");
  $db->query("DROP TABLE languages");
  $db->query("DROP TABLE customers");
  $db->query("DROP TABLE categories");
  $db->query("DROP TABLE notes");
?>