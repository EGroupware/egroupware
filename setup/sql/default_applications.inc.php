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

	$reserved_directorys = array(
		'phpgwapi' => True,
		'doc'      => True,
		'setup'    => True,
		'.'        => True,
		'..'       => True,
		'CVS'      => True,
		'CVSROOT'  => True,
		'files'    => True
	);

	$i = 30;
	$dh = opendir(PHPGW_SERVER_ROOT);
	while ($dir = readdir($dh))
	{
		if (! $reserved_directorys[$dir] && is_dir(PHPGW_SERVER_ROOT . SEP . $dir))
		{
			if (is_file(PHPGW_SERVER_ROOT . SEP . $dir . SEP . 'setup' . SEP . 'tables_new.inc.php'))
			{
				$new_schema[] = $dir;
			}

			if (is_file(PHPGW_SERVER_ROOT . SEP . $dir . SEP . 'setup' . SEP . 'setup.inc.php'))
			{
				include(PHPGW_SERVER_ROOT . SEP . $dir . SEP . 'setup' . SEP . 'setup.inc.php');
			}
			else
			{
				$setup_info[$dir] = array('name' => $dir, 'app_order' => '99', 'version' => '0.0.0');
			}
		}
	}

	while ($app = each($setup_info))
	{
		$phpgw_setup->db->query("insert into phpgw_applications (app_name, app_title, app_enabled, app_order,"
			. " app_tables, app_version) values ('" . $app[0] . "', '" . $app[1]["name"]
			. "',1," . $app[1]['app_order'] . ", '" . $app[1]['app_tables'] . "', '"
			. $app[1]['version'] . "')");
	}
?>
