<?php
  /**************************************************************************\
  * phpGroupWare                                                             *
  * http://www.phpgroupware.org                                              *
  * Written by Mark Peters <skeeter@phpgroupware.org>                        *
  * --------------------------------------------                             *
  *  This program is free software; you can redistribute it and/or modify it *
  *  under the terms of the GNU General Public License as published by the   *
  *  Free Software Foundation; either version 2 of the License, or (at your  *
  *  option) any later version.                                              *
  \**************************************************************************/

  /* $Id$ */


	function country_set($config)
	{
		global $phpgw;

		$country  = array(
			'user_choice'  => 'Users Choice',
			'force_select' => 'Force Selectbox'
		);

		while (list ($key, $value) = each ($country))
		{
			if ($config['countrylist'] == $key)
			{
				$selected = ' selected';
			}
			else
			{
				$selected = '';
			}
			$descr = lang($value);

			$out .= '<option value="' . $key . '"' . $selected . '>' . $descr . '</option>' . "\n";
		}
		return $out;
	}

	function force_theme($config)
	{
		global $phpgw;

		$themes  = array('user_choice');
		$themes += $phpgw->common->list_themes();

		while (list ($key, $value) = each ($themes))
		{
			if ($config['force_theme'] == $value)
			{
				$selected = ' selected';
			}
			else
			{
				$selected = '';
			}
			if ($value == 'user_choice')
			{
				$descr = lang('Users Choice');
			}
			else
			{
				$descr = $value;
			}
			$out .= '<option value="' . $value . '"' . $selected .'>'.$descr.'</option>' . "\n";
		}
		return $out;
	}

	function template_set($config)
	{
		global $phpgw;

		$templ['user_choice']  = array(
			'name' => 'user_choice',
			'title' => lang('Users Choice')
		);
		$templ += $phpgw->common->list_templates();

		while (list ($key, $value) = each ($templ))
		{
			if ($config['template_set'] == $key)
			{
				$selected = ' selected';
			}
			else
			{
				$selected = '';
			}

			$descr = $templ[$key]['title'];
			$out .= '<option value="' . $key . '"' . $selected . '>'.$descr.'</option>' . "\n";
		}
		return $out;
	}

	function force_default_app($config)
	{
		global $phpgw, $phpgw_info;

		$out = '<option value="user_choice"';
		if ($config['force_default_app'] == 'user_choice')
		{
			$out .= ' selected';
		}
		$out .= '>' . lang('Users Choice') . '</option>' . "\n";

		reset($phpgw_info['user']['apps']);
		while ($permission = each($phpgw_info['user']['apps']))
		{
			if ($phpgw_info['apps'][$permission[0]]['status'] != 2)
			{
				$out .= '<option value="' . $permission[0] . '"';
				if ($config['force_default_app'] == $permission[0])
				{
					$out .= ' selected';
				}
				$out .= '>' . lang($phpgw_info['apps'][$permission[0]]['title']) . '</option>' . "\n";
			}
		}

		return $out;
	}

?>
