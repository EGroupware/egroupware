<?php
  /**************************************************************************\
  * eGroupWare                                                               *
  * http://www.egroupware.org                                                *
  * --------------------------------------------                             *
  *  This program is free software; you can redistribute it and/or modify it *
  *  under the terms of the GNU General Public License as published by the   *
  *  Free Software Foundation; either version 2 of the License, or (at your  *
  *  option) any later version.                                              *
  \**************************************************************************/

	/* $Id$ */

	$GLOBALS['phpgw_info']['flags'] = array(
		'currentapp' => 'filemanager',
		'enable_nextmatchs_class' => True,
		'noheader' => True,
		'nonavbar' => True
	);

	//var_dump($file_attributes);
	include('../header.inc.php');
	/*
	   To add an on/off preference, just add it here.  Key is internal name, value is displayed name
	*/
	$other_checkboxes = array ("viewinnewwin" => lang("View documents in new window"), "viewonserver" => lang("View documents on server (if available)"), "viewtextplain" => lang("Unknown MIME-type defaults to text/plain when viewing"), "dotdot" => lang("Show .."), "dotfiles" => lang("Show .files"), "show_help" => lang("Show help"), "show_command_line" => lang("Show command line (EXPERIMENTAL. DANGEROUS.)"));

	/*
	   To add a dropdown preferences, add it here.  Key is internal name, value key is
	   displayed name, value values are choices in the dropdown
	*/
	$other_dropdown = array ("show_upload_boxes" => array (lang("Default number of upload fields to show"), "5", "10", "20", "30"));

	if ($submit)
	{
		$GLOBALS['phpgw']->preferences->read_repository ();

		reset ($other_checkboxes);
		while (list ($internal, $displayed) = each ($other_checkboxes))
		{
			$GLOBALS['phpgw']->preferences->add ($GLOBALS['phpgw_info']["flags"]["currentapp"], $internal, $$internal);
		}

		reset ($other_dropdown);
		while (list ($internal, $displayed) = each ($other_dropdown))
		{
			$GLOBALS['phpgw']->preferences->add ($GLOBALS['phpgw_info']["flags"]["currentapp"], $internal, $$internal);
		}

		reset ($file_attributes);
		while (list ($internal, $displayed) = each ($file_attributes))
		{
			$GLOBALS['phpgw']->preferences->add ($GLOBALS['phpgw_info']["flags"]["currentapp"], $internal, $$internal);
		}


		$GLOBALS['phpgw']->preferences->save_repository (True);
     
		Header('Location: '.$GLOBALS['phpgw']->link('/preferences/index.php'));
		$GLOBALS['phpgw']->common->phpgw_exit();
	}

	function display_item ($field,$data)
	{
		global $p, $tr_color;

		$tr_color = $GLOBALS['phpgw']->nextmatchs->alternate_row_color ($tr_color);
		$var = array (
			'bg_color'	=>	$tr_color,
			'field'		=>	$field,
			'data'		=>	$data
		);
		$p->set_var ($var);
		$p->parse ('row', 'pref_list', True);
	}

	$GLOBALS['phpgw']->common->phpgw_header ();
	echo parse_navbar ();

	$p = CreateObject ('phpgwapi.Template', $GLOBALS['phpgw']->common->get_tpl_dir ('filemanager'));
	$templates = array (
		'pref'			=> 'pref.tpl',
		'pref_colspan'	=> 'pref_colspan.tpl',
		'pref_list'		=>	'pref_list.tpl'
	);
	$p->set_file ($templates);

	$var = array (
		'title'			=>	lang ('FileManager preferences'),
		'action_url'	=>	$GLOBALS['phpgw']->link ('/' . $GLOBALS['phpgw_info']['flags']['currentapp'] . '/preferences.php'),
		'bg_color'		=>	$GLOBALS['phpgw_info']['theme']['th_bg'],
		'submit_lang'	=>	lang ('submit')
	);
	
	$p->set_var ($var);
	$p->set_var ('text', '&nbsp;');
	$p->parse ('row', 'pref_colspan', True);

	if ($totalerrors)
	{
		echo '<p><center>' . $GLOBALS['phpgw']->common->error_list($errors) . '</center>';
	}


	while (list ($internal, $displayed) = each ($file_attributes))
	{
		unset ($checked);
		if ($GLOBALS['phpgw_info']["user"]["preferences"]["filemanager"][$internal])
		{
			$checked = 1;
		}

		$str .= html_form_input ("checkbox", $internal, NULL, NULL, NULL, $checked, NULL, 1) . " $displayed" . html_break (1, NULL, 1);
	}

	display_item (lang ('Display attributes'), $str);

	reset ($other_checkboxes);
	while (list ($internal, $displayed) = each ($other_checkboxes))
	{
		unset ($checked);
		if ($GLOBALS['phpgw_info']["user"]["preferences"]["filemanager"][$internal])
		{
			$checked = 1;
		}

		$str = html_form_input ("checkbox", $internal, NULL, NULL, NULL, $checked, NULL, 1);
		display_item ($displayed, $str);
	}

	reset ($other_dropdown);
	while (list ($internal, $value_array) = each ($other_dropdown))
	{
		reset ($value_array);
		unset ($options);
		while (list ($num, $value) = each ($value_array))
		{
			if ($num == 0)
			{
				$displayed = $value;
				continue;
			}

			$options .= html_form_option ($value, $value, $GLOBALS['phpgw_info']["user"]["preferences"]["filemanager"][$internal] == $value, True);
		}

		$output = html_form_select_begin ($internal, True);
		$output .= $options;
		$output .= html_form_select_end (True);

		display_item ($displayed, $output);
	}

	$p->pparse ('out', 'pref');
	$GLOBALS['phpgw']->common->phpgw_footer ();
?>
