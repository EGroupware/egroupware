<?php
  /**************************************************************************\
  * phpGroupWare - PHPWebHosting                                             *
  * http://www.phpgroupware.org                                              *
  * --------------------------------------------                             *
  *  This program is free software; you can redistribute it and/or modify it *
  *  under the terms of the GNU General Public License as published by the   *
  *  Free Software Foundation; either version 2 of the License, or (at your  *
  *  option) any later version.                                              *
  \**************************************************************************/

	/* $Id$ */

	$phpgw_info["flags"] = array("currentapp" => "phpwebhosting", "enable_nextmatchs_class" => True, "noheader" => True, "nonavbar" => True);
	include("../header.inc.php");

	/*
	   To add a preference, just add it here.  Key is internal name, value is displayed name
	*/
	$other_checkboxes = array ("viewinnewwin" => "View documents in new window", "viewonserver" => "View documents on server (if available)", "viewtextplain" => "Unknown MIME-type defaults to text/plain when viewing", "dotdot" => "Show ..", "dotfiles" => "Show .files", "show_help" => "Show help");

	if ($submit)
	{
		$phpgw->preferences->read_repository ();

		reset ($file_attributes);
		while (list ($internal, $displayed) = each ($file_attributes))
		{
			$phpgw->preferences->add ($phpgw_info["flags"]["currentapp"], $internal, $$internal);
		}

		reset ($other_checkboxes);
		while (list ($internal, $displayed) = each ($other_checkboxes))
		{
			$phpgw->preferences->add ($phpgw_info["flags"]["currentapp"], $internal, $$internal);
		}

		$phpgw->preferences->save_repository (True);
     
		Header('Location: '.$phpgw->link('/preferences/index.php'));
		$phpgw->common->phpgw_exit();
	}

	function display_item ($field,$data)
	{
		global $phpgw, $p, $tr_color;

		$tr_color = $phpgw->nextmatchs->alternate_row_color ($tr_color);
		$var = array (
			'bg_color'	=>	$tr_color,
			'field'		=>	$field,
			'data'		=>	$data
		);
		$p->set_var ($var);
		$p->parse ('row', 'pref_list', True);
	}

	$phpgw->common->phpgw_header ();
	echo parse_navbar ();

	$p = CreateObject ('phpgwapi.Template', $phpgw->common->get_tpl_dir ('phpwebhosting'));
	$templates = array (
		'pref'			=> 'pref.tpl',
		'pref_colspan'	=> 'pref_colspan.tpl',
		'pref_list'		=>	'pref_list.tpl',
	);
	$p->set_file ($templates);

	$var = array (
		'title'			=>	lang ('PHPWebHosting preferences'),
		'action_url'	=>	$phpgw->link ('/' . $phpgw_info['flags']['currentapp'] . '/preferences.php'),
		'bg_color'		=>	$phpgw_info['theme']['th_bg'],
		'submit_lang'	=>	lang ('submit')
	);
	
	$p->set_var ($var);
	$p->set_var ('text', '&nbsp;');
	$p->parse ('row', 'pref_colspan', True);

	if ($totalerrors)
	{
		echo '<p><center>' . $phpgw->common->error_list($errors) . '</center>';
	}

	while (list ($internal, $displayed) = each ($file_attributes))
	{
		unset ($checked);
		if ($phpgw_info["user"]["preferences"]["phpwebhosting"][$internal])
			$checked = 1;

		$str .= html_form_input ("checkbox", $internal, NULL, NULL, NULL, $checked, NULL, 1) . " $displayed" . html_break (1, NULL, 1);
	}

	display_item (lang ('Display attributes'), $str);

	while (list ($internal, $displayed) = each ($other_checkboxes))
	{
		unset ($checked);
		if ($phpgw_info["user"]["preferences"]["phpwebhosting"][$internal])
			$checked = 1;

		$str = html_form_input ("checkbox", $internal, NULL, NULL, NULL, $checked, NULL, 1);
		display_item (lang ($displayed), $str);
	}

	$p->pparse ('out', 'pref');
	$phpgw->common->phpgw_footer ();
?>
