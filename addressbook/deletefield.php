<?php
  /**************************************************************************\
  * phpGroupWare - Addressbook                                               *
  * (http://www.phpgroupware.org)                                            *
  * Written by Bettina Gille [ceb@phpgroupware.org]                          *    
  * -----------------------------------------------                          *
  *  This program is free software; you can redistribute it and/or modify it *
  *  under the terms of the GNU General Public License as published by the   *
  *  Free Software Foundation; either version 2 of the License, or (at your  *
  *  option) any later version.                                              *
  \**************************************************************************/
  /* $Id$ */

	if ($confirm) {
		$phpgw_info["flags"] = array(
			'noheader' => True, 
			'nonavbar' => True
		);
	}

	$phpgw_info["flags"]["currentapp"] = 'addressbook';
	include('../header.inc.php');

	if (!$field) {
		Header('Location: ' . $phpgw->link('/addressbook/fields.php'));
	}

	if ($confirm) {
		save_custom_field($field);
		Header('Location: ' . $phpgw->link('/addressbook/fields.php',"start=$start&query=$query&sort=$sort"));
	}
	else
	{
		$hidden_vars = "<input type=\"hidden\" name=\"sort\" value=\"$sort\">\n"
			. "<input type=\"hidden\" name=\"order\" value=\"$order\">\n"
			. "<input type=\"hidden\" name=\"query\" value=\"$query\">\n"
			. "<input type=\"hidden\" name=\"start\" value=\"$start\">\n"
			. "<input type=\"hidden\" name=\"field\" value=\"$field\">\n";

		$t = new Template(PHPGW_APP_TPL);
		$t->set_file(array('field_delete' => 'delete_common.tpl'));
		$t->set_var('messages',lang('Are you sure you want to delete this field?'));

		$nolinkf = $phpgw->link('/addressbook/fields.php',"field_id=$field_id&start=$start&query=$query&sort=$sort");
		$nolink = "<a href=\"$nolinkf\">" . lang('No') ."</a>";
		$t->set_var('no',$nolink);

		$yeslinkf = $phpgw->link('/addressbook/deletefield.php',"field_id=$field_id&confirm=True");
		$yeslinkf = "<FORM method=\"POST\" name=yesbutton action=\"".$phpgw->link('/addressbook/deletefield.php') . "\">"
			. $hidden_vars
			. "<input type=hidden name=field_id value=$field_id>"
			. "<input type=hidden name=confirm value=True>"
			. "<input type=submit name=yesbutton value=Yes>"
			. "</FORM><SCRIPT>document.yesbutton.yesbutton.focus()</SCRIPT>";

		$yeslink = "<a href=\"$yeslinkf\">" . lang('Yes') ."</a>";
		$yeslink = $yeslinkf;
		$t->set_var('yes',$yeslink);

		$t->pparse('out','field_delete');
	}

	$phpgw->common->phpgw_footer();
?>
