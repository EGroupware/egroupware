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
	
	if ($_GET['appname'] != 'preferences')
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
		if (isset($GLOBALS['phpgw']->preferences->forced[$_appname][$preference_name]) && $GLOBALS['type'] != 'forced')
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
	
	function create_input_box($label,$name,$help='',$default='',$size = '',$max_size = '',$type='',
		$run_lang=True)
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
		
		if ($GLOBALS['type'] == 'user')
		{
			$def_text = !$GLOBALS['phpgw']->preferences->user[$_appname][$name] ? $GLOBALS['phpgw']->preferences->data[$_appname][$name] : $GLOBALS['phpgw']->preferences->default[$_appname][$name];

			if (isset($notifys[$name]))	// translate the substitution names
			{
				$def_text = $GLOBALS['phpgw']->preferences->lang_notify($def_text,$notifys[$name]);
			}
			$def_text = $def_text != '' ? ' <i><font size="-1">'.lang('default').':&nbsp;'.$def_text.'</font></i>' : '';
		}
		$t->set_var('row_value',"<input name=\"${GLOBALS[type]}[$name]\" value=\"".htmlentities($default)."\"$options>$def_text");
		$t->set_var('row_name',lang($label));
		$GLOBALS['phpgw']->nextmatchs->template_alternate_row_color($t);

		$t->fp('rows',process_help($help,$run_lang) ? 'help_row' : 'row',True);
	}
	
	function process_help($help,$run_lang=True)
	{
		global $t,$show_help,$has_help;

		if (!empty($help))
		{
			$has_help = True;
			
			if ($show_help)
			{
				$t->set_var('help_value',$run_lang ? lang($help) : $help);
				
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
				$s = '<option value="">' . lang('Use default') . '</option>';
				break;
			case 'default':
				$s = '<option value="">' . lang('No default') . '</option>';
				break;
			case 'forced':
				$s = '<option value="**NULL**">' . lang('Users choice') . '</option>';
				break;
		}
		$s .= create_option_string($default,$values);
		if ($GLOBALS['type'] == 'user')
		{
			$def_text = $GLOBALS['phpgw']->preferences->default[$_appname][$name];
			$def_text = $def_text != '' ? ' <i><font size="-1">'.lang('default').':&nbsp;'.$values[$def_text].'</font></i>' : '';
		}
		$t->set_var('row_value',"<select name=\"${GLOBALS[type]}[$name]\">$s</select>$def_text");
		$t->set_var('row_name',lang($label));
		$GLOBALS['phpgw']->nextmatchs->template_alternate_row_color($t);

		$t->fp('rows',process_help($help) ? 'help_row' : 'row',True);
	}
	
	/*!
	@function create_notify
	@abstract creates text-area or inputfield with subtitution-variables
	@syntax create_notify($label,$name,$rows,$cols,$help='',$default='',$vars2='')
	@param $label untranslated label
	@param $name name of the pref 
	@param $rows, $cols of the textarea or input-box ($rows==1)
	@param $help untranslated help-text
	@param $default default-value
	@param $vars2 array with extra substitution-variables of the form key => help-text
	*/
	function create_notify($label,$name,$rows,$cols,$help='',$default='',$vars2='',$subst_help=True)
	{
		global $t,$prefs,$notifys;

		$vars = $GLOBALS['phpgw']->preferences->vars;
		if (is_array($vars2))
		{
			$vars += $vars2;
		}
		$prefs[$name] = $GLOBALS['phpgw']->preferences->lang_notify($prefs[$name],$vars);

		$notifys[$name] = $vars;	// this gets saved in the app_session for re-translation

		$help = $help ? lang($help) : '';
		if ($subst_help)
		{
			$help .= '<p><b>'.lang('Substitutions and their meanings:').'</b>';
			foreach($vars as $var => $var_help)
			{
				$lname = ($lname = lang($var)) == $var.'*' ? $var : $lname;
				$help .= "<br>\n".'<b>$$'.$lname.'$$</b>: '.$var_help;
			}
			$help .= "</p>\n";
		}
		if ($row == 1)
		{
			create_input_box($label,$name,$help,$default,$cols,'','',False);
		}
		else
		{
			create_text_area($label,$name,$rows,$cols,$help,$default,False);
		}
	}

	function create_text_area($label,$name,$rows,$cols,$help='',$default='',$run_lang=True)
	{
		global $t,$prefs,$notifys;

		$_appname = check_app();
		if (is_forced_value($_appname,$name))
		{
			return True;
		}
		
		if (isset($prefs[$name]) || $GLOBALS['type'] != 'user')
		{
			$default = $prefs[$name];
		}

		if ($GLOBALS['type'] == 'user')
		{
			$def_text = !$GLOBALS['phpgw']->preferences->user[$_appname][$name] ? $GLOBALS['phpgw']->preferences->data[$_appname][$name] : $GLOBALS['phpgw']->preferences->default[$_appname][$name];

			if (isset($notifys[$name]))	// translate the substitution names
			{
				$def_text = $GLOBALS['phpgw']->preferences->lang_notify($def_text,$notifys[$name]);
			}
			$def_text = $def_text != '' ? '<br><i><font size="-1"><b>'.lang('default').'</b>:<br>'.nl2br($def_text).'</font></i>' : '';
		}
		$t->set_var('row_value',"<textarea rows=\"$rows\" cols=\"$cols\" name=\"${GLOBALS[type]}[$name]\">".htmlentities($default)."</textarea>$def_text");
		$t->set_var('row_name',lang($label));
		$GLOBALS['phpgw']->nextmatchs->template_alternate_row_color($t);

		$t->fp('rows',process_help($help,$run_lang) ? 'help_row' : 'row',True);
	}

	function process_array(&$repository,$array,$notifys,$prefix='')
	{
		$_appname = check_app();

		$prefs = &$repository[$_appname];

		if ($prefix != '')
		{
			$prefix_arr = explode('/',$prefix);
			foreach ($prefix_arr as $pre)
			{
				$prefs = &$prefs[$pre];
			}
		}
		unset($prefs['']);
		//echo "array:<pre>"; print_r($array); echo "</pre>\n";
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
				$prefs[$var] = stripslashes($value);

				if ($notifys[$var])	// need to translate the key-words back
				{
					$prefs[$var] = $GLOBALS['phpgw']->preferences->lang_notify($prefs[$var],$notifys[$var],True);
				}
			}
			else
			{
				unset($prefs[$var]);
			}
		}
		//echo "prefix='$prefix', prefs=<pre>"; print_r($repository[$_appname]); echo "</pre>\n";

		// the following hook can be used to verify the prefs 
		// if you return something else than False, it is treated as an error-msg and 
		// displayed to the user (the prefs get not saved !!!)
		//
		if ($error = $GLOBALS['phpgw']->hooks->single(array(
			'location' => 'verify_settings',
			'prefs'    => $repository[$_appname],
			'prefix'   => $prefix,
			'type'     => $GLOBALS['type']
		),$_GET['appname']))
		{
			return $error;
		}
		
		$GLOBALS['phpgw']->preferences->save_repository(True,$GLOBALS['type']);
		
		return False;
	}

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
		global $prefix;

		if (HAS_ADMIN_RIGHTS == 1 && empty($prefix))	// tabs only without prefix
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

	$prefix = get_var('prefix',array('GET'),$session_data['appname'] == $_GET['appname'] ? $session_data['prefix'] : '');
	
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
	$show_help = "$session_data[show_help]" != '' && $session_data['appname'] == $_GET['appname'] ? 
		$session_data['show_help'] : intval($GLOBALS['phpgw_info']['user']['preferences']['common']['show_help']);

	if ($toggle_help = get_var('toggle_help','POST'))
	{
		$show_help = intval(!$show_help);
	}
	$has_help = 0;

	if ($_POST['submit'])
	{
		/* Don't use a switch here, we need to check some permissions durring the ifs */
		if ($GLOBALS['type'] == 'user' || !($GLOBALS['type']))
		{
			$error = process_array($GLOBALS['phpgw']->preferences->user,$user,$session_data['notifys'],$prefix);
		}

		if ($GLOBALS['type'] == 'default' && is_admin())
		{
			$error = process_array($GLOBALS['phpgw']->preferences->default, $default,$session_data['notifys']);
		}

		if ($GLOBALS['type'] == 'forced' && is_admin())
		{
			$error = process_array($GLOBALS['phpgw']->preferences->forced, $forced,$session_data['notifys']);
		}

		if (!is_admin() || $error)
		{
			$GLOBALS['phpgw']->redirect_link('/preferences/index.php');
		}
		
		if ($GLOBALS['type'] == 'user' && $_GET['appname'] == 'preferences' && $user['show_help'] != '')
		{
			$show_help = $user['show_help'];	// use it, if admin changes his help-prefs
		}
	}
	$GLOBALS['phpgw']->session->appsession('session_data','preferences',array(
		'type'      => $GLOBALS['type'],	// save our state in the app-session
		'show_help' => $show_help,
		'prefix'    => $prefix,
		'appname'   => $_GET['appname']		// we use this to reset prefix on appname-change
	));
	// changes for the admin itself, should have immediate feedback ==> redirect
	if (!$error && $_POST['submit'] && $GLOBALS['type'] == 'user' && $_GET['appname'] == 'preferences') {
		$GLOBALS['phpgw']->redirect_link('/preferences/preferences.php','appname='.$_GET['appname']);
	}

	$GLOBALS['phpgw_info']['flags']['app_header'] = $_GET['appname'] == 'preferences' ?
		lang('Preferences') : lang('%1 - Preferences',$GLOBALS['phpgw_info']['apps'][$_GET['appname']]['title']);
	$GLOBALS['phpgw']->common->phpgw_header();

	$t->set_var('messages',$error);
	$t->set_var('action_url',$GLOBALS['phpgw']->link('/preferences/preferences.php','appname=' . $_GET['appname']));

	switch ($GLOBALS['type'])	// set up some globals to be used by the hooks
	{
		case 'forced':  
			$prefs = &$GLOBALS['phpgw']->preferences->forced[check_app()]; 
			break;
		case 'default': 
			$prefs = &$GLOBALS['phpgw']->preferences->default[check_app()]; 
			break;
		default:
			$prefs = &$GLOBALS['phpgw']->preferences->user[check_app()];
			// use prefix if given in the url, used for email extra-accounts
			if ($prefix != '')
			{
				$prefix_arr = explode('/',$prefix);
				foreach ($prefix_arr as $pre)
				{
					$prefs = &$prefs[$pre];
				}
			}
	}
	//echo "prefs=<pre>"; print_r($prefs); echo "</pre>\n";
	
	$notifys = array();
	if (!$GLOBALS['phpgw']->hooks->single('settings',$_GET['appname']))
	{
		$t->set_block('preferences','form','formhandle');	// skip the form
		$t->set_var('formhandle','');
		
		$t->set_var('messages',lang('Error: There was a problem finding the preference file for %1 in %2',
			$GLOBALS['phpgw_info']['navbar'][$_GET['appname']]['title'],PHPGW_SERVER_ROOT . SEP
			. $_GET['appname'] . SEP . 'inc' . SEP . 'hook_settings.inc.php'));
	}

	if (count($notifys))	// there have been notifys in the hook, we need to save in the session
	{
		$GLOBALS['phpgw']->session->appsession('session_data','preferences',array(
			'type'      => $GLOBALS['type'],	// save our state in the app-session
			'show_help' => $show_help,
			'prefix'    => $prefix,
			'appname'   => $_GET['appname'],	// we use this to reset prefix on appname-change
			'notifys'   => $notifys
		));
		//echo "notifys:<pre>"; print_r($notifys); echo "</pre>\n";
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
	
	//echo '<pre style="text-align: left;">'; print_r($GLOBALS['phpgw']->preferences->data); echo "</pre>\n";
?>
