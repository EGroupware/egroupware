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

	$GLOBALS['phpgw_info']['flags'] = array(
		'noheader'                => True,
		'noappheader'             => True,
		'nonavbar'                => True,
		'currentapp'              => $GLOBALS['HTTP_GET_VARS']['appname'],
		'enable_nextmatchs_class' => True
	);
	include('../header.inc.php');

	$user    = get_var('user',Array('POST'));
	$forced  = get_var('forced',Array('POST'));
	$default = get_var('default',Array('POST'));

	$GLOBALS['appname'] = get_var('appname',Array('GET'));

	if(get_var('cancel',Array('POST')))
	{
		Header('Location: ' . $GLOBALS['phpgw']->link('/preferences/index.php'));
	}

	$t = CreateObject('phpgwapi.Template',$GLOBALS['phpgw']->common->get_tpl_dir('preferences'));
	$t->set_file(array(
		'_preferences' => 'preferences.tpl'
	));
	$t->set_block('_preferences','list');
	$t->set_block('_preferences','row');

	$t->set_block('_preferences','header');
	$t->set_block('_preferences','footer');
	$t->set_block('_preferences','row_error');

	/* Make things a little easier to follow */
	/* Some places we will need to change this if there in common */
	function check_app()
	{
		if ($GLOBALS['appname'] == 'preferences')
		{
			return 'common';
		}
		else
		{
			return $GLOBALS['appname'];
		}
	}

	function is_forced_value($_appname,$preference_name)
	{
		if ($GLOBALS['gp']->data[$_appname][$preference_name] && $GLOBALS['type'] != 'forced')
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
		global $t;

		$_appname = check_app();
		$GLOBALS['phpgw']->nextmatchs->template_alternate_row_color($t);
		$t->set_var('row_name',lang($label_name));

		if (is_forced_value($_appname,$preference_name))
		{
			return True;
		}

		switch ($GLOBALS['type'])
		{
			case 'user':
				$s = '<input name="user[' . $preference_name . ']" value="' . $GLOBALS['phpgw_info']['user']['preferences'][$_appname][$preference_name] . '">';
				break;
			case 'default':
				$s = '<input name="default[' . $preference_name . ']" value="' . $GLOBALS['dp']->data[$_appname][$preference_name] . '">';
				break;
			case 'forced':
				$s = '<input name="forced[' . $preference_name . ']" value="' . $GLOBALS['gp']->data[$_appname][$preference_name] . '">';
				break;
		}
		$t->set_var('row_value',$s);

		$t->fp('rows','row',True);
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
		global $t;

		$_appname = check_app();
		if (is_forced_value($_appname,$preference_name))
		{
			return True;
		}

		$GLOBALS['phpgw']->nextmatchs->template_alternate_row_color($t);

		$t->set_var('row_name',lang($label_name));

		switch ($GLOBALS['type'])
		{
			case 'user':
				$s = '<option value="">' . lang('Select one') . '</option>'
					. create_option_string($GLOBALS['phpgw_info']['user']['preferences'][$_appname][$preference_name],$values);
				$t->set_var('row_value','<select name="user[' . $preference_name . ']">' . $s . '</select>');
				break;
			case 'default':
				$s = '<option value="">' . lang('Select one') . '</option>'
					. create_option_string($GLOBALS['dp']->data[$_appname][$preference_name],$values);
				$t->set_var('row_value','<select name="default[' . $preference_name . ']">' . $s . '</select>');
				break;
			case 'forced':
				$s = '<option value="**NULL**">' . lang('Users choice') . '</option>'
					. create_option_string($GLOBALS['gp']->data[$_appname][$preference_name],$values);
				$t->set_var('row_value','<select name="forced[' . $preference_name . ']">' . $s . '</select>');
				break;
		}

		$t->fp('rows','row',True);
	}

	function process_array(&$_p, $array)
	{
		$_appname = check_app();

		while (is_array($array) && list($var,$value) = each($array))
		{
			if (isset($value) && $value != '' && $value != '**NULL**')
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

	/* So we can check if the admin is allowing users to make there own choices */
	/* in life. */
	$GLOBALS['gp'] = createobject('phpgwapi.preferences',-1);
	$GLOBALS['gp']->read_repository();

	/* Only check this once */
	if ($GLOBALS['phpgw']->acl->check('run',1,'admin'))
	{
		/* Don't use a global variable for this ... */
		define('HAS_ADMIN_RIGHTS',1);
	}

	/* Makes the ifs a little nicer, plus ... this will change once the ACL manager is in place */
	/* and is able to create less powerfull admins.  This will handle the ACL checks for that (jengo) */
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

	if (is_admin())
	{
		/* This is where we will keep track of our postion. */
		/* Developers won't have to pass around a variable then */
		$session_data = $GLOBALS['phpgw']->session->appsession('session_data','preferences');

		if (! is_array($session_data))
		{
			$session_data = array(
				'type' => 'user'
			);
			$GLOBALS['phpgw']->session->appsession('session_data','preferences',$session_data);
		}

		$type = get_var('type',Array('GET','POST'));
		if (!isset($type))
		{
			$GLOBALS['type'] = $session_data['type'];
		}
		else
		{
			$GLOBALS['type'] = $type;
			$session_data = array(
				'type' => $GLOBALS['type']
			);
			$GLOBALS['phpgw']->session->appsession('session_data','preferences',$session_data);
		}

		$tabs[] = array(
			'label' => lang('Your preferences'),
			'link'  => $GLOBALS['phpgw']->link('/preferences/preferences.php','appname=' . $GLOBALS['appname'] . '&type=user')
		);
		$tabs[] = array(
			'label' => lang('Default preferences'),
			'link'  => $GLOBALS['phpgw']->link('/preferences/preferences.php','appname=' . $GLOBALS['appname'] . '&type=default')
		);
		$tabs[] = array(
			'label' => lang('Forced preferences'),
			'link'  => $GLOBALS['phpgw']->link('/preferences/preferences.php','appname=' . $GLOBALS['appname'] . '&type=forced')
		);

		switch($GLOBALS['type'])
		{
			case 'user':    $selected = 0; break;
			case 'default': $selected = 1; break;
			case 'forced':  $selected = 2; break;
		}
		$t->set_var('tabs',$GLOBALS['phpgw']->common->create_tabs($tabs,$selected));
	}
	else
	{
		$GLOBALS['type'] = 'user';
	}

	/* Only load if there working on the default preferences */
	if ($GLOBALS['type'] == 'default')
	{
		$GLOBALS['dp'] = createobject('phpgwapi.preferences',-2);
		$GLOBALS['dp']->read_repository();
	}

	if (get_var('submit',Array('POST')))
	{
		/* Don't use a switch here, we need to check some permissions durring the ifs */
		if ($GLOBALS['type'] == 'user' || !($GLOBALS['type']))
		{
			process_array($p, $user);
		}

		if ($GLOBALS['type'] == 'default' && is_admin())
		{
			process_array($GLOBALS['dp'], $default);
		}

		if ($GLOBALS['type'] == 'forced' && is_admin())
		{
			process_array($GLOBALS['gp'], $forced);
		}

		Header('Location: ' . $GLOBALS['phpgw']->link('/preferences/index.php'));
		$GLOBALS['phpgw_info']['flags']['nodisplay'] = True;
		exit;
	}

	$GLOBALS['phpgw']->common->phpgw_header();

	if ($GLOBALS['appname'] == 'preferences')
	{
		$t->set_var('lang_title',lang('Preferences'));
	}
	else
	{
		$t->set_var('lang_title',lang('%1 - Preferences',$GLOBALS['phpgw_info']['navbar'][$GLOBALS['appname']]['title']));
	}

	$t->set_var('action_url',$GLOBALS['phpgw']->link('/preferences/preferences.php','appname=' . $GLOBALS['appname']));
	$t->set_var('th_bg',  $GLOBALS['phpgw_info']['theme']['th_bg']);
	$t->set_var('th_text',$GLOBALS['phpgw_info']['theme']['th_text']);
	$t->set_var('row_on', $GLOBALS['phpgw_info']['theme']['row_on']);
	$t->set_var('row_off',$GLOBALS['phpgw_info']['theme']['row_off']);

	if ($GLOBALS['appname'] == 'preferences')
	{
		if (! $GLOBALS['phpgw']->hooks->single('settings','preferences',True))
		{
			$error = True;
		}
	}
	else
	{
		if (! $GLOBALS['phpgw']->hooks->single('settings',$GLOBALS['appname']))
		{
			$error = True;
		}
	}

	if ($error)
	{
		$t->set_var('messages',lang('Error: There was a problem finding the preference file for %1 in %2',
			$GLOBALS['phpgw_info']['navbar'][$GLOBALS['appname']]['title'],PHPGW_SERVER_ROOT . SEP
			. $GLOBALS['appname'] . SEP . 'inc' . SEP . 'hook_settings.inc.php'));
	}
	$t->pfp('out','header');

	$t->set_var('lang_submit', lang('submit'));
	$t->set_var('lang_cancel', lang('cancel'));

	if (! $error)
	{
		$t->pfp('out','list');
		$t->pfp('out','footer');
	}
	$GLOBALS['phpgw']->common->phpgw_footer();
?>
