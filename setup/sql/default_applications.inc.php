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
  // This is for the base apps, which should always be included
  $phpgw_setup->db->query("insert into applications (app_name, app_title, app_enabled, app_order, app_tables, app_version) values ('admin', 'Administration', 1, 1, NULL, '".$phpgw_info["setup"]["currentver"]["phpgwapi"]."')");
  $phpgw_setup->db->query("insert into applications (app_name, app_title, app_enabled, app_order, app_tables, app_version) values ('addressbook', 'Address Book', 1, 7, 'addressbook', '".$phpgw_info["setup"]["currentver"]["phpgwapi"]."')");
  $phpgw_setup->db->query("insert into applications (app_name, app_title, app_enabled, app_order, app_tables, app_version) values ('filemanager', 'File manager', 1, 6, NULL, '".$phpgw_info["setup"]["currentver"]["phpgwapi"]."')");
  $phpgw_setup->db->query("insert into applications (app_name, app_title, app_enabled, app_order, app_tables, app_version) values ('calendar', 'Calendar', 1, 9, 'calendar_entry,calendar_entry_users,calendar_entry_repeats', '".$phpgw_info["setup"]["currentver"]["phpgwapi"]."')");
  $phpgw_setup->db->query("insert into applications (app_name, app_title, app_enabled, app_order, app_tables, app_version) values ('cron_apps', 'cron_apps', 0, 0, NULL, '".$phpgw_info["setup"]["currentver"]["phpgwapi"]."')");
  $phpgw_setup->db->query("insert into applications (app_name, app_title, app_enabled, app_order, app_tables, app_version) values ('email', 'Email', 1, 10,NULL, '".$phpgw_info["setup"]["currentver"]["phpgwapi"]."')");
  $phpgw_setup->db->query("insert into applications (app_name, app_title, app_enabled, app_order, app_tables, app_version) values ('nntp', 'NNTP', 1, 11, 'newsgroups', '".$phpgw_info["setup"]["currentver"]["phpgwapi"]."')");
  $phpgw_setup->db->query("insert into applications (app_name, app_title, app_enabled, app_order, app_tables, app_version) values ('notes', 'Notes', 1, 14, NULL, '".$phpgw_info["setup"]["currentver"]["phpgwapi"]."')");
  // This is for the add-on application, which are enabled/disabled based being in the regular or plus version
  $phpgw_setup->db->query("insert into applications (app_name, app_title, app_enabled, app_order, app_tables, app_version) values ('todo', 'ToDo List', 1, 8, 'todo', '".$phpgw_info["setup"]["currentver"]["phpgwapi"]."')");
  $phpgw_setup->db->query("insert into applications (app_name, app_title, app_enabled, app_order, app_tables, app_version) values ('transy', 'Translation Management', 1, 13, NULL, '".$phpgw_info["setup"]["currentver"]["phpgwapi"]."')");
  $phpgw_setup->db->query("insert into applications (app_name, app_title, app_enabled, app_order, app_tables, app_version) values ('tts', 'Trouble Ticket System', 1, 2, NULL, '0.0.0')");
  $phpgw_setup->db->query("insert into applications (app_name, app_title, app_enabled, app_order, app_tables, app_version) values ('inv', 'Inventory', 1, 3, NULL, '0.0.0')");
  $phpgw_setup->db->query("insert into applications (app_name, app_title, app_enabled, app_order, app_tables, app_version) values ('chat', 'Chat', 1, 4, NULL, '0.0.0')");
  $phpgw_setup->db->query("insert into applications (app_name, app_title, app_enabled, app_order, app_tables, app_version) values ('headlines', 'Headlines', 1, 5, 'news_sites,news_headlines', '0.0.0')");
  $phpgw_setup->db->query("insert into applications (app_name, app_title, app_enabled, app_order, app_tables, app_version) values ('weather', 'Weather', 1, 12, NULL, '".$phpgw_info["setup"]["currentver"]["phpgwapi"]."')");
  $phpgw_setup->db->query("insert into applications (app_name, app_title, app_enabled, app_order, app_tables, app_version) values ('bookmarks', 'Book Marks', 1, 15, NULL, '".$phpgw_info["setup"]["currentver"]["phpgwapi"]."')");
  $phpgw_setup->db->query("insert into applications (app_name, app_title, app_enabled, app_order, app_tables, app_version) values ('manual', 'Manual', 1, 16, NULL, '".$phpgw_info["setup"]["currentver"]["phpgwapi"]."')");
?>