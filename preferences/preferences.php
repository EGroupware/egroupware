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
		'currentapp'              => $_GET['appname'],
		'enable_nextmatchs_class' => True
	);
	include('../header.inc.php');
	
	if ($_POST['cancel'])
	{
		$GLOBALS['phpgw']->redirect_link('/preferences/index.php');
	}
	
	$user    = get_var('user',Array('POST'));
	$forced  = get_var('forced',Array('POST'));
	$default = get_var('default',Array('POST'));

	$t = CreateObject('phpgwapi.Template',$GLOBALS['phpgw']->common->get_tpl_dir('preferences'));
	$t->set_file(array(
		'preferences' => 'preferences.tpl'
	));
	$t->set_block('preferences','list','lists');
	$t->set_block('preferences','row','rowhandle');
	$t->set_block('preferences','help_row','help_rowhandle');
	$t->set_var(array('rowhandle' => '','help_rowhandle' => '','messages' => ''));
	
	if ($_GET['appname'] != 'preverences')
	{
		$GLOBALS['phpgw']->translation->add_app('preferences');	// we need the prefs translations too
	}

	/* Make things a little easier to follow */
	/* Some places we will need to change this if there in common */
	function check_app()
	{
		if ($_GET['appname'] == 'preferences')
		{
			return 'common';
		}
		else
		{
			return $_GET['appname'];
		}
	}

	function is_forced_value($_appname,$preference_name)
	{
		if (isset($GLOBALS['gp']->data[$_appname][$preference_name]) && $GLOBALS['type'] != 'forced')
		{
			return True;
		}
		else
		{
			return False;
		}
	}

	function create_password_box($label_name,$preference_name,$help='',$size = '',$max_size = '')
	{
		global $user,$forced,$default;
		
		$_appname = check_app();
		if (is_forced_value($_appname,$preference_name))
		{
			return True;
		}
		create_input_box($label_name,$preference_name.'][pw',$help,'',$size,$max_size,'password');
	}
	
	function create_input_box($label,$name,$help='',$default='',$size = '',$max_size = '',$type='')
	{
		global $t,$prefs;

		$_appname = check_app();
		if (is_forced_value($_appname,$name))
		{
			return True;
		}

		if ($type)	// used to specify password
		{
			$options = " TYPE='$type'";
		}
		if ($size)
		{
			$options .= " SIZE='$size'";
		}
		if ($maxsize)
		{
			$options .= " MAXSIZE='$maxsize'";
		}

		if (isset($prefs[$name]) || $GLOBALS['type'] != 'user')
		{
			$default = $prefs[$name];
		}
		
		$t->set_var('row_value',"<input name=\"${GLOBALS[type]}[$name]\" value=\"$default\"$options>");
		$t->set_var('row_name',lang($label));
		$GLOBALS['phpgw']->nextmatchs->template_alternate_row_color($t);

		$t->fp('rows',process_help($help) ? 'help_row' : 'row',True);
	}
	
	function process_help($help)
	{
		global $t,$show_help,$has_help;

		if (!empty($help))
		{
			$has_help = True;
			
			if ($show_help)
			{
				$t->set_var('help_value',lang($help));
				
				return True;
			}
		}
		return False;
	}

	function create_check_box($label,$name,$help='',$default='')
	{
		// checkboxes itself can't be use as they return nothing if uncheckt !!!
		global $prefs;
		
		if ($GLOBALS['type'] != 'user')
		{
			$default = '';	// no defaults for default or forced prefs
		}
		if (isset($prefs[$name]))
		{
			$prefs[$name] = intval(!!$prefs[$name]);	// to care for '' and 'True'
		}
		
		return create_select_box($label,$name,array(
			'0' => lang('No'),
			'1' => lang('Yes')
		),$help,$default);
	}

	function create_option_string($selected,$values)
	{
		while (is_array($values) && list($var,$value) = each($values))
		{
			$s .= '<option value="' . $var . '"';
			if ("$var" == "$selected")	// the "'s are necessary to force a string-compare
			{
				$s .= ' selected';
			}
			$s .= '>' . $value . '</option>';
		}
		return $s;
	}

	function create_select_box($label,$name,$values,$help='',$default='')
	{
		global $t,$prefs;

		$_appname = check_app();
		if (is_forced_value($_appname,$name))
		{
			return True;
		}
		
		if (isset($prefs[$name]) || $GLOBALS['type'] != 'user')
		{
			$default = $prefs[$name];
		}

		switch ($GLOBALS['type'])
		{
			case 'user':
				$s = '<option value="">' . lang('Select one') . '</option>';
				break;
			case 'default':
				$s = '<option value="">' . lang('Select one') . '</option>';
				break;
			case 'forced':
				$s = '<option value="**NULL**">' . lang('Users choice') . '</option>';
				break;
		}
		$s .= create_option_string($default,$values);
		$t->set_var('row_value',"<select name=\"${GLOBALS[type]}[$name]\">$s</select>");
		$t->set_var('row_name',lang($label));
		$GLOBALS['phpgw']->nextmatchs->template_alternate_row_color($t);

		$t->fp('rows',process_help($help) ? 'help_row' : 'row',True);
	}
	
	function create_text_area($label,$name,$rows,$cols,$help='',$default='')
	{
		global $t,$prefs;

		$_appname = check_app();
		if (is_forced_value($_appname,$name))
		{
			return True;
		}
		
		if (isset($prefs[$name]) || $GLOBALS['type'] != 'user')
		{
			$default = $prefs[$name];
		}

		$t->set_var('row_value',"<textarea rows=\"$rows\" cols=\"$cols\" name=\"${GLOBALS[type]}[$name]\">$default</textarea>");
		$t->set_var('row_name',lang($label));
		$GLOBALS['phpgw']->nextmatchs->template_alternate_row_color($t);

		$t->fp('rows',process_help($help) ? 'help_row' : 'row',True);
	}

	function process_array(&$_p, $array)
	{
		$_appname = check_app();

		while (is_array($array) && list($var,$value) = each($array))
		{
			if (isset($value) && $value != '' && $value != '**NULL**')
			{
				if (is_array($value))
				{
					$value = $value['pw'];
					if (empty($value))
					{
						continue;	// dont write empty password-fields
					}
				}
				$_p->add($_appname,$var,$value);
			}
			else
			{
				unset($_p->data[$_appname][$var]);
			}
		}
		$_p->save_repository(True);
	}

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
		if (HAS_ADMIN_RIGHTS == 1 && !isset($_GET['prefix']))	// tabs only without prefix
		{
			return True;
		}
		else
		{
			return False;
		}
	}
	
	function show_list($header = '&nbsp;')
	{
		global $t,$list_shown;

		$t->set_var('list_header',$header);
		$t->parse('lists','list',$list_shown);

		$t->set_var('rows','');
		$list_shown = True;
	}

	$session_data = $GLOBALS['phpgw']->session->appsession('session_data','preferences');

	if (is_admin())
	{
		/* This is where we will keep track of our postion. */
		/* Developers won't have to pass around a variable then */

		$GLOBALS['type'] = get_var('type',Array('GET','POST'),$session_data['type']);

		if (empty($GLOBALS['type']))
		{
			$GLOBALS['type'] = 'user';
		}
	}
	else
	{
		$GLOBALS['type'] = 'user';
	}
	$prefix = get_var('prefix',array('GET'),$session_data['prefix']);
	
	$show_help = "$session_data[show_help]" != '' ? $session_data['show_help'] : 
		intval($GLOBALS['phpgw_info']['user']['preferences']['common']['show_help']);

	if ($toggle_help = get_var('toggle_help','POST'))
	{
		$show_help = intval(!$show_help);
	}
	$has_help = 0;

	/* Only load if there working on the default preferences */
	if ($GLOBALS['type'] == 'default')
	{
		$GLOBALS['dp'] = createobject('phpgwapi.preferences',-2);
		$GLOBALS['dp']->read_repository();
	}

	if ($_POST['submit'])
	{
		/* Don't use a switch here, we need to check some permissions durring the ifs */
		if ($GLOBALS['type'] == 'user' || !($GLOBALS['type']))
		{
			process_array($GLOBALS['phpgw']->preferences, $user);
		}

		if ($GLOBALS['type'] == 'default' && is_admin())
		{
			process_array($GLOBALS['dp'], $default);
		}

		if ($GLOBALS['type'] == 'forced' && is_admin())
		{
			process_array($GLOBALS['gp'], $forced);
		}

		if (!is_admin())
		{
			$GLOBALS['phpgw']->redirect_link('/preferences/index.php');
		}

		if ($GLOBALS['type'] == 'user' && $_GET['appname'] == 'preferences')	// changes for the admin itself
		{
			if ($user['show_help'] != '')
			{
				$show_help = $user['show_help'];	// use it, if admin changes his help-prefs
			}
			if ($GLOBALS['phpgw_info']['user']['preferences']['common']['lang'] != $user['lang'])
			{
				$GLOBALS['phpgw']->redirect_link('/preferences/preferences.php','appname='.$_GET['appname']);
			}
		}
	}
	$GLOBALS['phpgw']->session->appsession('session_data','preferences',array(
		'type'      => $GLOBALS['type'],	// save our state in the app-session
		'show_help' => $show_help,
		'prefix'    => $prefix
	));

	if ($_GET['appname'] == 'preferences')
	{
		$t->set_var('lang_title',lang('Preferences'));
	}
	else
	{
		$t->set_var('lang_title',lang('%1 - Preferences',$GLOBALS['phpgw_info']['apps'][$_GET['appname']]['title']));
	}

	$t->set_var('action_url',$GLOBALS['phpgw']->link('/preferences/preferences.php','appname=' . $_GET['appname']));

	switch ($GLOBALS['type'])	// set up some globals to be used by the hooks
	{
		case 'forced':  
			$prefs = &$GLOBALS['gp']->data[check_app()]; 
			break;
		case 'default': 
			$prefs = &$GLOBALS['dp']->data[check_app()]; 
			break;
		default:
			$prefs = &$GLOBALS['phpgw']->preferences->data[check_app()];
			// use prefix if given in the url, used for email extra-accounts
			if ($_GET['prefix'] != '')
			{
				$prefix = explode('/',$_GET['prefix']);
				foreach ($prefix as $pre)
				{
					$prefs = &$prefs[$pre];
				}
			}
	}
	//echo "prefs=<pre>"; print_r($prefs); echo "</pre>\n";
	
	if ($_GET['appname'] == 'preferences')
	{
		if (! $GLOBALS['phpgw']->hooks->single('settings','preferences',True))
		{
			$error = True;
		}
	}
	else
	{
		if (! $GLOBALS['phpgw']->hooks->single('settings',$_GET['appname']))
		{
			$error = True;
		}
	}

	if ($error)
	{
		$t->set_block('preferences','form','formhandle');	// skip the form
		$t->set_var('formhandle','');
		
		$t->set_var('messages',lang('Error: There was a problem finding the preference file for %1 in %2',
			$GLOBALS['phpgw_info']['navbar'][$_GET['appname']]['title'],PHPGW_SERVER_ROOT . SEP
			. $_GET['appname'] . SEP . 'inc' . SEP . 'hook_settings.inc.php'));
	}

	if (is_admin())
	{
		$tabs[] = array(
			'label' => lang('Your preferences'),
			'link'  => $GLOBALS['phpgw']->link('/preferences/preferences.php','appname=' . $_GET['appname'] . "&type=user")
		);
		$tabs[] = array(
			'label' => lang('Default preferences'),
			'link'  => $GLOBALS['phpgw']->link('/preferences/preferences.php','appname=' . $_GET['appname'] . "&type=default")
		);
		$tabs[] = array(
			'label' => lang('Forced preferences'),
			'link'  => $GLOBALS['phpgw']->link('/preferences/preferences.php','appname=' . $_GET['appname'] . "&type=forced")
		);

		switch($GLOBALS['type'])
		{
			case 'user':    $selected = 0; break;
			case 'default': $selected = 1; break;
			case 'forced':  $selected = 2; break;
		}
		$t->set_var('tabs',$GLOBALS['phpgw']->common->create_tabs($tabs,$selected));
	}
	$t->set_var('lang_submit', lang('save'));
	$t->set_var('lang_cancel', lang('cancel'));
	$t->set_var('show_help',intval($show_help));
	$t->set_var('help_button',$has_help ? '<input type="submit" name="toggle_help" value="'.
		($show_help ? lang('help off') : lang('help')).'">' : '');

	if (!$list_shown)
	{
		show_list();
	}
	$t->parse('phpgw_body','preferences');
?>
