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

	$appname = $GLOBALS['HTTP_GET_VARS']['appname'];
	$user    = $GLOBALS['HTTP_POST_VARS']['user'];
	$global  = $GLOBALS['HTTP_POST_VARS']['global'];
	$default = $GLOBALS['HTTP_POST_VARS']['default'];

	$GLOBALS['phpgw_info']['flags'] = array(
		'noheader'                => True,
		'nonavbar'                => True,
		'currentapp'              => $appname,
		'enable_nextmatchs_class' => True
	);
	include('../header.inc.php');

	if ($GLOBALS['HTTP_POST_VARS']['cancel'])
	{
		Header('Location: ' . $GLOBALS['phpgw']->link('/preferences/index.php'));
	}

	// Make things a little easier to follow
	function is_admin()
	{
		if (HAS_ADMIN_RIGHTS == 1)
		{
			return True;
		}
		else
		{
			return False;
		}
	}

	// Some places we will need to change this if there in common
	function check_app($appname)
	{
		if ($appname == 'preferences')
		{
			return 'common';
		}
		else
		{
			return $appname;
		}
	}

	function parse_row(&$tpl)
	{
		if (is_admin())
		{
			$tpl->fp('rows','row_a',True);
		}
		else
		{
			$tpl->fp('rows','row_u',True);
		}
	}

	function is_forced_value($appname,$preference_name)
	{
		global $gp;

		if ($gp->data[$appname][$preference_name])
		{
			return True;
		}
		else
		{
			return False;		
		}
	}

	function create_input_box($label_name,$preference_name,$size = '',$max_size = '')
	{
		global $appname, $t, $dp, $gp;

		$_appname = check_app($appname);

		$GLOBALS['phpgw']->nextmatchs->template_alternate_row_color(&$t);
		$t->set_var('row_name',lang($label_name));
		if (! is_forced_value($_appname,$preference_name))
		{
			$t->set_var('row_user','<input name="user[' . $preference_name . ']" value="' . $GLOBALS['phpgw_info']['user']['preferences'][$_appname][$preference_name] . '">');
		}
		else
		{
			$t->set_var('row_user','&nbsp;');
		}
		if (is_admin())
		{
			$t->set_var('row_global','<input name="global[' . $preference_name . ']" value="' . $gp->data[$_appname][$preference_name] . '">');
			$t->set_var('row_default','<input name="default[' . $preference_name . ']" value="' . $dp->data[$_appname][$preference_name] . '">');
		}
		parse_row(&$t);
	}

	function create_option_string($selected,$values)
	{
		while (is_array($values) && list($var,$value) = each($values))
		{
			$s .= '<option value="' . $var . '"';
			if ($var == $selected)
			{
				$s .= ' selected';
			}
			$s .= '>' . $value . '</option>';
		}
		return $s;
	}

	function create_select_box($label_name,$preference_name,$values)
	{
		global $appname, $t, $dp, $gp;

		$_appname = check_app($appname);

		$GLOBALS['phpgw']->nextmatchs->template_alternate_row_color(&$t);

		$t->set_var('row_name',lang($label_name));
		if (! is_forced_value($_appname,$preference_name))
		{
			$s = '<option value="">' . lang('Select one') . '</option>'
				. create_option_string($GLOBALS['phpgw_info']['user']['preferences'][$_appname][$preference_name],$values);
			$t->set_var('row_user','<select name="user[' . $preference_name . ']">' . $s . '</select>');
		}
		else
		{
			$t->set_var('row_user','&nbsp;');
		}

		if (is_admin())
		{
			$s = '<option value="**NULL**">' . lang('Users choice') . '</option>'
				. create_option_string($gp->data[$_appname][$preference_name],$values);
			$t->set_var('row_global','<select name="global[' . $preference_name . ']">' . $s . '</select>');

			$s = '<option value="">' . lang('Select one') . '</option>'
				. create_option_string($dp->data[$_appname][$preference_name],$values);
			$t->set_var('row_default','<select name="default[' . $preference_name . ']">' . $s . '</select>');
		}
		parse_row(&$t);
	}

	function process_array(&$_p, $array)
	{
		global $appname;

		$_appname = check_app($appname);

		while (is_array($array) && list($var,$value) = each($array))
		{
			if ($value && $value != '**NULL**')
			{
				$_p->add($_appname,$var,$value);
			}
			else
			{
				unset($_p->data[$_appname][$var]);
			}
		}
		$_p->save_repository(True);
	}

	$p = createobject('phpgwapi.preferences');
	$p->read_repository();

	// So we can check if the admin is allowing users to make there own choices
	// in life.
	$gp = createobject('phpgwapi.preferences',-1);
	$gp->read_repository();

	// Only check this once
	if ($GLOBALS['phpgw']->acl->check('run',1,'admin'))
	{
		// Don't use a global variable for this ...
		define('HAS_ADMIN_RIGHTS',1);

		$dp = createobject('phpgwapi.preferences',-2);
		$dp->read_repository();
	}

	$t = CreateObject('phpgwapi.Template',$GLOBALS['phpgw']->common->get_tpl_dir('preferences'));
	$t->set_file(array(
		'_preferences' => 'preferences.tpl'
	));
	$t->set_block('_preferences','list_a');
	$t->set_block('_preferences','row_a');

	$t->set_block('_preferences','list_u');
	$t->set_block('_preferences','row_u');

	$t->set_block('_preferences','header');
	$t->set_block('_preferences','footer');
	$t->set_block('_preferences','row_error');

	if ($GLOBALS['HTTP_POST_VARS']['submit'])
	{
		process_array(&$p, $user);

		if (is_admin())
		{
			process_array(&$gp, $global);
			process_array(&$dp, $default);
		}

		Header('Location: ' . $GLOBALS['phpgw']->link('/preferences/index.php#' . $appname));
		$GLOBALS['phpgw']->common->phpgw_exit();
	}

	$GLOBALS['phpgw']->common->phpgw_header();
	echo parse_navbar();

	if ($appname == 'preferences')
	{
		$t->set_var('lang_title',lang('Preferences'));
	}
	else
	{
		$t->set_var('lang_title',lang('%1 - Preferences',$GLOBALS['phpgw_info']['navbar'][$appname]['title']));
	}
	$t->set_var('action_url',$GLOBALS['phpgw']->link('/preferences/preferences.php','appname=' . $appname));
	$t->set_var('th_bg',  $GLOBALS['phpgw_info']['theme']['th_bg']);
	$t->set_var('th_text',$GLOBALS['phpgw_info']['theme']['th_text']);
	$t->set_var('row_on', $GLOBALS['phpgw_info']['theme']['row_on']);
	$t->set_var('row_off',$GLOBALS['phpgw_info']['theme']['row_off']);

	if (is_admin())
	{
		$t->set_var('lang_user',lang('Yours'));
		$t->set_var('lang_global',lang('Forced'));
		$t->set_var('lang_default',lang('Default'));
	}
	else
	{
		$t->set_var('lang_user','&nbsp;');
	}

	if (! $GLOBALS['phpgw']->common->hook_single('settings',$appname,True))
	{
		$t->set_var('messages',lang('Error: There was a problem finding the preference file for %1 in %2',
				$GLOBALS['phpgw_info']['navbar'][$appname]['title'],PHPGW_SERVER_ROOT . SEP
				. $appname . SEP . 'inc' . SEP . 'hook_settings.inc.php'));
		$error = True;
	}
	$t->pfp('out','header');

	$t->set_var('lang_submit', lang('submit'));
	$t->set_var('lang_cancel', lang('cancel'));

	if (! $error)
	{
		if (is_admin())
		{
			$t->pfp('out','list_a');
		}
		else
		{
			$t->pfp('out','list_u');
		}
	}

	if (! $error)
	{
		$t->pfp('out','footer');
	}

	$GLOBALS['phpgw']->common->phpgw_footer();
?>
