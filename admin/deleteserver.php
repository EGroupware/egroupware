<?php
  /**************************************************************************\
  * phpGroupWare - Admin                                                     *
  * (http://www.phpgroupware.org)                                            *
  * Written by Bettina Gille [ceb@phpgroupware.org]                          *    
  * -----------------------------------------------                          *
  *  This program is free software; you can redistribute it and/or modify it *
  *  under the terms of the GNU General Public License as published by the   *
  *  Free Software Foundation; either version 2 of the License, or (at your  *
  *  option) any later version.                                              *
  \**************************************************************************/
  /* $Id$ */

	if ($confirm)
	{
		$phpgw_info["flags"] = array(
			'noheader' => True, 
			'nonavbar' => True
		);
	}

	$phpgw_info['flags']['currentapp'] = 'admin';
	include('../header.inc.php');

	if (!$server_id)
	{
		Header('Location: ' . $phpgw->link('/admin/servers.php'));
	}

	if ($confirm)
	{
		$is->delete($server_id);
		Header('Location: ' . $phpgw->link('/admin/servers.php',"start=$start&query=$query&sort=$sort&order=$order&filter=$filter"));
	}
	else
	{
		$hidden_vars =
			  '<input type="hidden" name="sort"   value="' . $sort   . '"' . ">\n"
			. '<input type="hidden" name="order"  value="' . $order  . '"' . ">\n"
			. '<input type="hidden" name="query"  value="' . $query  . '"' . ">\n"
			. '<input type="hidden" name="start"  value="' . $start  . '"' . ">\n"
			. '<input type="hidden" name="filter" value="' . $filter . '"' . ">\n"
			. '<input type="hidden" name="server_id" value="' . $server_id . '">' . "\n";

		$phpgw->template->set_file(array('server_delete' => 'delete_common.tpl'));
		$phpgw->template->set_var('messages',lang('Are you sure you want to delete this server?'));

		$nolinkf = $phpgw->link('/admin/servers.php',"server_id=$server_id&start=$start&query=$query&sort=$sort&order=$order&filter=$filter");
		$nolink = "<a href=\"$nolinkf\">" . lang('No') ."</a>";
		$phpgw->template->set_var('no',$nolink);

		$yeslinkf = $phpgw->link('/admin/deleteserver.php',"server_id=$server_id&confirm=True");
		$yeslinkf = '<form method="POST" name=yesbutton action="' . $phpgw->link('/admin/deleteserver.php') . '">'
			. $hidden_vars
			. '<input type=hidden name="server_id" value="' . $server_id . '">'
			. '<input type=hidden name="confirm" value="True">'
			. '<input type=submit name="yesbutton" value=Yes>'
			. '</form><script>document.yesbutton.yesbutton.focus()</script>';

		$yeslink = '<a href="' . $yeslinkf . '">' . lang('Yes') .'</a>';
		$yeslink = $yeslinkf;
		$phpgw->template->set_var('yes',$yeslink);

		$phpgw->template->pparse('out','server_delete');
	}

	$phpgw->common->phpgw_footer();
?>
