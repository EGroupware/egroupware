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
?>
