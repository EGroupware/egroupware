<?php
	/**************************************************************************\
	* phpGroupWare - Preferences                                               *
	* http://www.phpgroupware.org                                              *
	* --------------------------------------------                             *
	*  This program is free software; you can redistribute it and/or modify it *
	*  under the terms of the GNU General Public License as published by the   *
	*  Free Software Foundation; either version 2 of the License, or (at your  *
	*  option) any later version.                                              *
	\**************************************************************************/

	/* $Id$ */

	$templates = $GLOBALS['phpgw']->common->list_templates();
	while (list($var,$value) = each($templates))
	{
		$_templates[$var] = $templates[$var]['title'];
	}

	$themes = $GLOBALS['phpgw']->common->list_themes();
	while (list(,$value) = each($themes))
	{
		$_themes[$value] = $value;
	}

	create_input_box('Max matches per page','maxmatchs');
	create_select_box('Interface/Template Selection','template_set',$_templates);
	create_select_box('Theme (colors/fonts) Selection','theme',$_themes);

	$navbar_format = array(
		'icons'          => lang('Icons only'),
		'icons_and_text' => lang('Icons and text'),
		'text'           => lang('Text only')
	);
	create_select_box('Show navigation bar as','navbar_format',$navbar_format);

	for ($i = -23; $i<24; $i++)
	{
		$tz_offset[$i] = $i;
	}
	create_select_box('Time zone offset','tz_offset',$tz_offset);

	$date_formats = array(
		'm/d/Y' => 'm/d/Y',
		'm-d-Y' => 'm-d-Y',
		'm.d.Y' => 'm.d.Y',
		'Y/d/m' => 'Y/d/m',
		'Y-d-m' => 'Y-d-m',
		'Y.d.m' => 'Y.d.m',
		'Y/m/d' => 'Y/m/d',
		'Y-m-d' => 'Y-m-d',
		'Y.m.d' => 'Y.m.d',
		'd/m/Y' => 'd/m/Y',
		'd-m-Y' => 'd-m-Y',
		'd.m.Y' => 'd.m.Y'
	);
	create_select_box('Date format','dateformat',$date_formats);

	$time_formats = array(
		'12' => '12 hour',
		'24' => '24 hour'
	);
	create_select_box('Time format','timeformat',$time_formats);

	$sbox = createobject('phpgwapi.sbox');
	create_select_box('Country','country',$sbox->country_array);

	$db2 = $GLOBALS['phpgw']->db;
	$GLOBALS['phpgw']->db->query("select distinct lang from lang",__LINE__,__FILE__);
	while ($GLOBALS['phpgw']->db->next_record())
	{
//		$phpgw_info['installed_langs'][$phpgw->db->f('lang')] = $phpgw->db->f('lang');

		$db2->query("select lang_name from languages where lang_id = '"
			. $GLOBALS['phpgw']->db->f('lang') . "'",__LINE__,__FILE__);
		$db2->next_record();

		// When its not in the phpgw_languages table, it will show ??? in the field
		// otherwise
		if ($db2->f('lang_name'))
		{
			$langs[$GLOBALS['phpgw']->db->f('lang')] = $db2->f('lang_name');
		}
	}
	create_select_box('Language','lang',$langs);

	// preference.php handles this function
	if (is_admin())
	{
		// The 'True' is *NOT* being used as a constant, don't change it
		$yes_and_no = array(
			'True' => 'Yes',
			''     => 'No'
		);
		create_select_box('Show current users on navigation bar','show_currentusers',$yes_and_no);
	}

	reset($GLOBALS['phpgw_info']['user']['apps']);
	while (list($permission) = each($GLOBALS['phpgw_info']['user']['apps']))
	{
		if ($GLOBALS['phpgw_info']['apps'][$permission]['status'] != 2)
		{
			$user_apps[$permission] = $permission;
		}
	}
	create_select_box('Default application','default_app',$user_apps);

	create_input_box('Currency','currency');
