<?php
	/**************************************************************************\
	* phpGroupWare - administration                                            *
	* http://www.phpgroupware.org                                              *
	* Written by Joseph Engo <jengo@phpgroupware.org>                          *
	* Modified by Stephen Brown <steve@dataclarity.net>                        *
	*  to distribute admin across the application directories                  *
	* --------------------------------------------                             *
	*  This program is free software; you can redistribute it and/or modify it *
	*  under the terms of the GNU General Public License as published by the   *
	*  Free Software Foundation; either version 2 of the License, or (at your  *
	*  option) any later version.                                              *
	\**************************************************************************/

	/* $Id$ */

	$phpgw_info['flags']['currentapp'] = 'admin';
	include('../header.inc.php');

	check_code($cd);

	// This func called by the includes to dump a row header
	function section_start($name='',$icon='')
	{
		global $phpgw,$phpgw_info;
		echo '<table width="75%" border="0" cellspacing="0" cellpadding="0"><tr>';
		if ($icon)
		{
			echo '<td width="5%"><img src="' . $icon . '" alt="[Icon]" align="middle"></td>';
			echo '<td><fontsize="+2">' . lang($name) . '</font></td>';
		}
		else
		{
			echo '<td colspan="2"><font size="+2">' . $name . '</font></td>';
		}
		echo '</tr>';
		echo '<tr><td colspan="2">';
	}

	function section_end()
	{
		echo '</td></tr></table>';
	}

	// We only want to list applications that are enabled, even if hidden from navbar, plus the common stuff
	// (if they can get to the admin page, the admin app is enabled, hence it is shown)

	$phpgw->db->query("SELECT app_name FROM phpgw_applications WHERE app_enabled=1 OR app_enabled=2 ORDER BY app_title",__LINE__,__FILE__);

	// Stuff it in an array in the off chance the admin includes need the db
	while ($phpgw->db->next_record())
	{
		$apps[] = $phpgw->db->f('app_name');
	}

	for ($i =0; $i < sizeof($apps); $i++)
	{
		$appname = $apps[$i];
		$f = PHPGW_SERVER_ROOT . '/' . $appname . '/inc/hook_admin.inc.php';

		if (file_exists($f))
		{
			include($f);
			echo "<p>\n";
		}
	}

	if ($SHOW_INFO > 0)
	{
		echo '<p><a href="' . $phpgw->link('/admin/index.php', 'SHOW_INFO=0'). '">' . lang('Hide PHP Information') . '</a>';
		echo "<hr>\n";
		phpinfo();
		echo "<hr>\n";
	}
	else
	{
		echo '<p><a href="' . $phpgw->link('/admin/index.php', 'SHOW_INFO=1'). '">' . lang('PHP Information') . '</a>';
	}
	$phpgw->common->phpgw_footer();
?>
