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

  // This is so we can enable or disable apps for the phpGroupWare and phpGroupWare Plus packges
  $db->query("insert into applications (app_name, app_title, app_enabled, app_order, app_tables, app_version) values ('admin', 'Administration', 1, 1, NULL, '".$currentver."')");
  $db->query("insert into applications (app_name, app_title, app_enabled, app_order, app_tables, app_version) values ('tts', 'Trouble Ticket System', 0, 2, NULL, '0.0.0')");
  $db->query("insert into applications (app_name, app_title, app_enabled, app_order, app_tables, app_version) values ('inv', 'Inventory', 0, 3, NULL, '0.0.0')");
  $db->query("insert into applications (app_name, app_title, app_enabled, app_order, app_tables, app_version) values ('chat', 'Chat', 0, 4, NULL, '0.0.0')");
  $db->query("insert into applications (app_name, app_title, app_enabled, app_order, app_tables, app_version) values ('headlines', 'Headlines', 0, 5, 'news_sites,news_headlines', '0.0.0')");
  $db->query("insert into applications (app_name, app_title, app_enabled, app_order, app_tables, app_version) values ('filemanager', 'File manager', 1, 6, NULL, '".$currentver."')");
  $db->query("insert into applications (app_name, app_title, app_enabled, app_order, app_tables, app_version) values ('addressbook', 'Address Book', 1, 7, 'addressbook', '".$currentver."')");
  $db->query("insert into applications (app_name, app_title, app_enabled, app_order, app_tables, app_version) values ('todo', 'ToDo List', 1, 8, 'todo', '".$currentver."')");
  $db->query("insert into applications (app_name, app_title, app_enabled, app_order, app_tables, app_version) values ('calendar', 'Calendar', 1, 9, 'webcal_entry,webcal_entry_users,webcal_entry_groups,webcal_repeats', '".$currentver."')");
  $db->query("insert into applications (app_name, app_title, app_enabled, app_order, app_tables, app_version) values ('email', 'Email', 1, 10,NULL, '".$currentver."')");
  $db->query("insert into applications (app_name, app_title, app_enabled, app_order, app_tables, app_version) values ('nntp', 'NNTP', 1, 11, 'newsgroups', '".$currentver."')");
  $db->query("insert into applications (app_name, app_title, app_enabled, app_order, app_tables, app_version) values ('cron_apps', 'cron_apps', 0, 0, NULL, '".$currentver."')");
  $db->query("insert into applications (app_name, app_title, app_enabled, app_order, app_tables, app_version) values ('weather', 'Weather', 1, 12, NULL, '".$currentver."')");
  $db->query("insert into applications (app_name, app_title, app_enabled, app_order, app_tables, app_version) values ('transy', 'Translation Management', 1, 13, NULL, '".$currentver."')");
  $db->query("insert into applications (app_name, app_title, app_enabled, app_order, app_tables, app_version) values ('notes', 'Notes', 1, 14, NULL, '$currentver')");
?>