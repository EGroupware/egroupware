<?php
/**
 * EGroupware preferences
 *
 * @package preferences
 * @link http://www.egroupware.org
 * @license http://opensource.org/licenses/gpl-license.php GPL - GNU General Public License
 * @version $Id$
 */

/**
 * Preferences UI
 */
class uisettings
{
	var $public_functions = array(
		'index' => True
	);
	/**
	 * Instance of Template class
	 * @var Template
	 */
	var $t;
	var $list_shown = False;
	var $show_help;
	var $has_help;
	var $prefix = '';
	/**
	 * Instance of business object
	 *
	 * @var bosettings
	 */
	var $bo;

	function __construct()
	{
		$this->bo =& CreateObject('preferences.bosettings',$_GET['appname']);

		if($GLOBALS['egw']->acl->check('run',1,'admin'))
		{
			/* Don't use a global variable for this ... */
			define('HAS_ADMIN_RIGHTS',1);

			if ((int) $_GET['account_id'])
			{
				$GLOBALS['egw']->preferences->account_id = (int) $_GET['account_id'];
				$GLOBALS['egw']->preferences->read_repository();
			}
		}
	}


	/**
	 * add nation ACL tab to Admin >> Edit user
	 */
	function edit_user()
	{
		global $menuData;

		$menuData[] = array(
			'description'   => 'Preferences',
			'url'           => '/index.php',
			'extradata'     => 'menuaction=preferences.uisettings.index&appname=preferences'
		);
	}

	function index()
	{
		// make preferences called via sidebox menu of an app, to behave like a part of that app
		$referer = common::get_referer('/preferences/index.php');
		// for framed templates, use the index page to return, instead of the referer
		if (strpos($referer,'cd=yes') !== false)
		{
			$referer = substr(egw_framework::index($_GET['appname']),
				strlen($GLOBALS['egw_info']['server']['webserver_url']));
		}
		if (!preg_match('/(preferences.php|menuaction=preferences.uisettings.index)+/i',$referer))
		{
			$this->bo->session_data['referer'] = $referer;
		}
		//echo '<p align="right">'."referer='{$this->bo->session_data['referer']}'</p>\n";
		if (substr($this->bo->session_data['referer'],0,strlen('/preferences')) != '/preferences')
		{
			$GLOBALS['egw_info']['flags']['currentapp'] = $_GET['appname'];
		}
		if($_POST['cancel'])
		{
			egw::redirect_link($this->bo->session_data['referer']);
		}
		if (substr($_SERVER['PHP_SELF'],-15) == 'preferences.php')
		{
			$pref_link = '/preferences/preferences.php';
			$link_params = array(
				'appname'    => $_GET['appname'],
			);
		}
		else
		{
			$pref_link = '/index.php';
			$link_params = array(
				'menuaction' => 'preferences.uisettings.index',
				'appname'    => $_GET['appname'],
			);
			if ($this->is_admin() && (int) $_GET['account_id'])
			{
				$link_params['account_id'] = (int) $_GET['account_id'];
			}
		}
		$user    = get_var('user',Array('POST'));
		$forced  = get_var('forced',Array('POST'));
		$default = get_var('default',Array('POST'));

		$this->t = new Template(common::get_tpl_dir('preferences'),'keep');
		$this->t->set_file(array(
			'preferences' => 'preferences.tpl'
		));
		$this->t->set_block('preferences','list','lists');
		$this->t->set_block('preferences','row','rowhandle');
		$this->t->set_block('preferences','help_row','help_rowhandle');
		$this->t->set_block('preferences','section_row','section_rowhandle');
		$this->t->set_var(array('rowhandle' => '','help_rowhandle' => '','messages' => '', 'section_rowhandle' => ''));

		$this->prefix = get_var('prefix',array('GET'),$this->bo->session_data['appname'] == $_GET['appname'] ? $this->bo->session_data['prefix'] : '');

		if($this->is_admin())
		{
			/* This is where we will keep track of our postion. */
			/* Developers won't have to pass around a variable then */

			$GLOBALS['type'] = get_var('type',Array('GET','POST'),$this->bo->session_data['type']);

			if(empty($GLOBALS['type']))
			{
				$GLOBALS['type'] = 'user';
			}
		}
		else
		{
			$GLOBALS['type'] = 'user';
		}
		$this->show_help = isset($this->bo->session_data['show_help']) && $this->bo->session_data['appname'] == $_GET['appname']
			? $this->bo->session_data['show_help']
			: (int)$GLOBALS['egw_info']['user']['preferences']['common']['show_help'];

		if($toggle_help = get_var('toggle_help','POST'))
		{
			$this->show_help = (int)(!$this->show_help);
		}
		$this->has_help = 0;

		if($_POST['save'] || $_POST['apply'])
		{
			if ($this->bo->session_data['notifies'])	// notifies NEED the translation for the application loaded
			{
				translation::add_app($_GET['appname']);
			}
			/* Don't use a switch here, we need to check some permissions during the ifs */
			if($GLOBALS['type'] == 'user' || !($GLOBALS['type']))
			{
				$error = $this->bo->process_array($GLOBALS['egw']->preferences->user,$user,$this->bo->session_data['notifies'],$GLOBALS['type'],$this->prefix);
			}

			if($GLOBALS['type'] == 'default' && $this->is_admin())
			{
				$error = $this->bo->process_array($GLOBALS['egw']->preferences->default, $default,$this->bo->session_data['notifies'],$GLOBALS['type']);
			}

			if($GLOBALS['type'] == 'forced' && $this->is_admin())
			{
				$error = $this->bo->process_array($GLOBALS['egw']->preferences->forced, $forced,$this->bo->session_data['notifies'],$GLOBALS['type']);
			}

			if (is_array($error)) $error = false;	// process_array returns the prefs-array on success

			if($GLOBALS['type'] == 'user' && $_GET['appname'] == 'preferences' && $user['show_help'] != '')
			{
				$this->show_help = $user['show_help'];	// use it, if admin changes his help-prefs
			}
			if($_POST['save'] && !$error)
			{
				$GLOBALS['egw']->redirect_link($this->bo->session_data['referer']);
			}
		}

		// save our state in the app-session
		$this->bo->save_session($_GET['appname'],$GLOBALS['type'],$this->show_help,$this->prefix);

		// changes for the admin itself, should have immediate feedback ==> redirect
		if(!$error && ($_POST['save'] || $_POST['apply']) && $GLOBALS['type'] == 'user' && $_GET['appname'] == 'preferences')
		{
			$GLOBALS['egw']->redirect_link($pref_link,$link_params);
		}

		$this->t->set_var('messages',$error);
		$this->t->set_var('action_url',$GLOBALS['egw']->link($pref_link,$link_params));
		$this->t->set_var('th_bg',  $GLOBALS['egw_info']['theme']['th_bg']);
		$this->t->set_var('th_text',$GLOBALS['egw_info']['theme']['th_text']);
		$this->t->set_var('row_on', $GLOBALS['egw_info']['theme']['row_on']);
		$this->t->set_var('row_off',$GLOBALS['egw_info']['theme']['row_off']);

		$this->bo->read($this->check_app(),$this->prefix,$GLOBALS['type']);
		//echo "prefs=<pre>"; print_r($this->bo->prefs); echo "</pre>\n";

		$this->notifies = array();
		if(!$this->bo->call_hook($_GET['appname']))
		{
			throw new egw_exception_wrong_parameter("Could not find settings for application: ".$_GET['appname']);
		}

		foreach($this->bo->settings as $key => $valarray)
		{
			if(!$this->is_admin())
			{
				if($valarray['admin'])
				{
					continue;
				}
			}
			unset($valarray['default']);	// not longer used as default, since we have default prefs
			switch($valarray['type'])
			{
				case 'section':
					$this->create_section($valarray['title']);
					break;
				case 'subsection':
					$this->create_section($valarray['title'],'prefSubSection');
					break;
				case 'input':
					$this->create_input_box(
						$valarray['label'],
						$valarray['name'],
						$valarray['help'],
						$valarray['default'],
						$valarray['size'],
						$valarray['maxsize'],
						$valarray['type'],
						$valarray['run_lang']	// if run_lang is set and false $valarray['help'] is run through lang()
					);
					break;
				case 'password':
					$this->create_password_box(
						$valarray['label'],
						$valarray['name'],
						$valarray['help'],
						$valarray['size'],
						$valarray['maxsize'],
						$valarray['run_lang']
					);
					break;
				case 'text':
					$this->create_text_area(
						$valarray['label'],
						$valarray['name'],
						$valarray['rows'],
						$valarray['cols'],
						$valarray['help'],
						$valarray['default'],
						$valarray['run_lang']
					);
					break;
				case 'select':
				case 'multiselect':
					$this->create_select_box(
						$valarray['label'],
						$valarray['name'],
						$valarray['values'],
						$valarray['help'],
						$valarray['default'],
						$valarray['run_lang'],
						$valarray['type'] == 'multiselect'
					);
					break;
				case 'check':
					$this->create_check_box(
						$valarray['label'],
						$valarray['name'],
						$valarray['help'],
						$valarray['default'],
						$valarray['run_lang']
					);
					break;
				case 'notify':
					$this->create_notify(
						$valarray['label'],
						$valarray['name'],
						$valarray['rows'],
						$valarray['cols'],
						$valarray['help'],
						$valarray['default'],
						$valarray['values'],
						$valarray['subst_help'],
						$valarray['run_lang']
					);
					break;
				case 'color':
					$this->create_color_box(
						$valarray['label'],
						$valarray['name'],
						$valarray['default'],
						$valarray['help'],
						$valarray['run_lang']	// if run_lang is set and false $valarray['help'] is run through lang()
					);
					break;
			}
		}

		$GLOBALS['egw_info']['flags']['app_header'] = ($this->is_admin() && (int) $_GET['account_id'] ?
			common::grab_owner_name((int) $_GET['account_id']).': ' : '').($_GET['appname'] == 'preferences' ?
			lang('Common preferences') : lang('%1 - Preferences',$GLOBALS['egw_info']['apps'][$_GET['appname']]['title']));
		common::egw_header();
		echo parse_navbar();

		if(count($this->notifies))	// there have been notifies in the hook, we need to save in the session
		{
			$this->bo->save_session($_GET['appname'],$GLOBALS['type'],$this->show_help,$this->prefix,$this->notifies);
			//echo "notifies="; _debug_array($this->notifies);
		}
		if($this->is_admin())
		{
			if ((int) $_GET['account_id'])
			{
				echo '<table><tr valign="top"><td>'."\n".ExecMethod('admin.uimenuclass.createHTMLCode','edit_user')."\n</td>\n<td>".
					'<p class="th" style="width: 100%; text-align: left; font-weight: bold; margin-top: 2px; padding: 1px;">'.
					lang('Common preferences')."</p>\n";
			}
			$tabs[] = array(
				'label' => (int) $_GET['account_id'] ? common::grab_owner_name($_GET['account_id']) : lang('Your preferences'),
				'link'  => $GLOBALS['egw']->link($pref_link,$link_params+array('type'=>'user')),
			);
			$tabs[] = array(
				'label' => lang('Default preferences'),
				'link'  => $GLOBALS['egw']->link($pref_link,$link_params+array('type'=>'default')),
			);
			$tabs[] = array(
				'label' => lang('Forced preferences'),
				'link'  => $GLOBALS['egw']->link($pref_link,$link_params+array('type'=>'forced')),
			);

			switch($GLOBALS['type'])
			{
				case 'user':    $selected = 0; break;
				case 'default': $selected = 1; break;
				case 'forced':  $selected = 2; break;
			}
			$this->t->set_var('tabs',common::create_tabs($tabs,$selected));
		}
		else
		{
			$this->t->set_var('tabs','');
		}
		$this->t->set_var('lang_save', lang('save'));
		$this->t->set_var('lang_apply', lang('apply'));
		$this->t->set_var('lang_cancel', lang('cancel'));
		$this->t->set_var('show_help',(int)$this->show_help);
		$this->t->set_var('help_button',$this->has_help ? '<input type="submit" name="toggle_help" value="'.
		($this->show_help ? lang('help off') : lang('help')).'">' : '');

		if(!$this->list_shown)
		{
			$this->show_list();
		}
		$this->t->pfp('phpgw_body','preferences');

		if($this->is_admin() && (int) $_GET['account_id'])
		{
			echo "\n</td></tr></table>\n";
		}
		//echo '<pre style="text-align: left;">'; print_r($GLOBALS['egw']->preferences->data); echo "</pre>\n";

		common::egw_footer();
	}

	/* Make things a little easier to follow */
	/* Some places we will need to change this if they're in common */
	function check_app()
	{
		if($_GET['appname'] == 'preferences')
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
		if(isset($GLOBALS['egw']->preferences->forced[$_appname][$preference_name]) && $GLOBALS['type'] != 'forced')
		{
			return True;
		}
		else
		{
			return False;
		}
	}

	function create_password_box($label_name,$preference_name,$help='',$size='',$max_size='',$run_lang=True)
	{
		$_appname = $this->check_app();
		if($this->is_forced_value($_appname,$preference_name))
		{
			return True;
		}
		$this->create_input_box($label_name,$preference_name.
			($GLOBALS['type'] != 'user' ? '' : '][pw'),	// we need to show the default or forced pw, otherwise we are never able to reset it
			$help,'',$size,$max_size,'password',$run_lang);
	}

	function create_color_box($label,$name,$default='',$help='',$run_lang=True)
	{
		if($GLOBALS['type'] == 'user')
		{
			$_appname = $this->check_app();
			$def_text = !$GLOBALS['egw']->preferences->user[$_appname][$name] ? $GLOBALS['egw']->preferences->data[$_appname][$name] : $GLOBALS['egw']->preferences->default[$_appname][$name];
			$def_text = preg_match('/^#[0-9A-F]{6}$/i',$def_text) ? ' &nbsp; <i><font size="-1">'.lang('default').':&nbsp;<span style="background-color: '.$def_text.'">'.$def_text.'</span></font></i>' : '';
		}
		$this->create_input_box($label,$name,$help,$default,'','','color',$run_lang,$def_text);
	}

	function create_input_box($label,$name,$help='',$default='',$size='',$max_size='',$type='',$run_lang=True,$def_text='')
	{
		$_appname = $this->check_app();
		if($this->is_forced_value($_appname,$name))
		{
			return True;
		}

		if($size)
		{
			$options .= " size='$size'";
		}
		if($maxsize)
		{
			$options .= " max='$maxsize'";
		}

		if(isset($this->bo->prefs[$name]) || $GLOBALS['type'] != 'user')
		{
			$default = $this->bo->prefs[$name];
		}

		if($GLOBALS['type'] == 'user' && empty($def_text))
		{
			$def_text = !$GLOBALS['egw']->preferences->user[$_appname][$name] ? $GLOBALS['egw']->preferences->data[$_appname][$name] : $GLOBALS['egw']->preferences->default[$_appname][$name];

			if(isset($this->notifies[$name]))	// translate the substitution names
			{
				$def_text = $GLOBALS['egw']->preferences->lang_notify($def_text,$this->notifies[$name]);
			}
			$def_text = $def_text != '' ? ' <i><font size="-1">'.lang('default').':&nbsp;'.$def_text.'</font></i>' : '';
		}
		$this->t->set_var('row_value',html::input($GLOBALS['type']."[$name]",$default,$type,$options).$def_text);

		$this->t->set_var('row_name',$run_lang !== -1 ? lang($label) : $label);
		$GLOBALS['egw']->nextmatchs->template_alternate_row_color($this->t);

		$this->t->fp('rows',$this->process_help($help,$run_lang) ? 'help_row' : 'row',True);
	}

	function process_help($help,$run_lang=True)
	{
		if(!empty($help))
		{
			$this->has_help = True;

			if($this->show_help)
			{
				$this->t->set_var('help_value',is_null($run_lang) || $run_lang ? lang($help) : $help);

				return True;
			}
		}
		return False;
	}

	function create_check_box($label,$name,$help='',$default='',$run_lang=True)
	{
		// checkboxes itself can't be use as they return nothing if uncheckt !!!

		if($GLOBALS['type'] != 'user')
		{
			$default = '';	// no defaults for default or forced prefs
		}
		if(isset($this->bo->prefs[$name]))
		{
			$this->bo->prefs[$name] = (string)(int)(!!$this->bo->prefs[$name]);	// to care for '' and 'True'
		}

		return $this->create_select_box($label,$name,array(
			'0' => lang('No'),
			'1' => lang('Yes')
		),$help,$default,$run_lang);
	}

	function create_option_string($selected,$values)
	{
		while(is_array($values) && list($var,$value) = each($values))
		{
			$s .= '<option value="' . $var . '"';
			if("$var" == "$selected")	// the "'s are necessary to force a string-compare
			{
				$s .= ' selected="1"';
			}
			$s .= '>' . $value . '</option>';
		}
		return $s;
	}

	/**
	 * Create different sections with a title
	 */
	function create_section($title='',$span_class='prefSection')
	{
		$this->t->set_var('title','<span class="'.$span_class.'">'.lang($title).'</span>');

		$this->t->fp('rows','section_row',True);
	}

	function create_select_box($label,$name,$values,$help='',$default='',$run_lang=True,$multiple=false)
	{
		$_appname = $this->check_app();
		if($this->is_forced_value($_appname,$name))
		{
			return True;
		}

		if(isset($this->bo->prefs[$name]) || $GLOBALS['type'] != 'user')
		{
			$default = $this->bo->prefs[$name];
		}
		//echo "<p>uisettings::create_select_box('$label','$name',".print_r($values,true).",,'$default',$run_lang,$multiple)</p>\n";

		if (!$multiple)
		{
			switch($GLOBALS['type'])
			{
				case 'user':
					$extra = array('' => lang('Use default'));
					break;
				case 'default':
					$extra = array('' => lang('No default'));
					break;
				case 'forced':
					$extra = array('**NULL**' => lang('Users choice'));
					break;
			}
			if (is_array($extra)) $values = $extra + (is_array($values)?$values:array($values));

			$select = html::select($GLOBALS['type'].'['.$name.']',$default,$values,true);
		}
		else
		{
			if (!is_array($default)) $default = explode(',',$default);
			$select = html::input_hidden($GLOBALS['type'].'['.$name.']','',false);	// causes bosettings not to ignore unsetting all
			$select .= html::checkbox_multiselect($GLOBALS['type'].'['.$name.']',$default,$values,true,'',5);
		}
		if($GLOBALS['type'] == 'user' && (string)$GLOBALS['egw']->preferences->default[$_appname][$name] !== '')
		{
			// flatten values first (some selectbox values are given multi-dimensional)
			foreach($values as $id => $val)
			{
				if (is_array($val))
				{
					unset($values[$id]);
					$values += $val;
				}
			}
			$default_value = $GLOBALS['egw']->preferences->default[$_appname][$name];
			if ($multiple) $default_value = explode(',',$default_value);
			$defs = array();
			foreach((array)$default_value as $def)
			{
				if ($values[$def]) $defs[] = $values[$def];
			}
			$def_text = ' <i><font size="-1">'.lang('default').':&nbsp;'.implode(', ',$defs).'</font></i>';
		}
		$this->t->set_var('row_value',$select.$def_text);
		$this->t->set_var('row_name',$run_lang !== -1 ? lang($label) : $label);
		$GLOBALS['egw']->nextmatchs->template_alternate_row_color($this->t);

		$this->t->fp('rows',$this->process_help($help,$run_lang) ? 'help_row' : 'row',True);
	}

	/**
	* creates text-area or inputfield with subtitution-variables
	*
	* @param string $label untranslated label
	* @param string $name name of the pref
	* @param int $rows of the textarea or input-box ($rows==1)
	* @param int $cols of the textarea or input-box ($rows==1)
	* @param string $help='' untranslated help-text, run through lang if $run_lang != false
	* @param string $default='' default-value
	* @param array $vars2='' array with extra substitution-variables of the form key => help-text
	* @param boolean $subst_help=true show help about substitues
	* @param boolean $run_lang=true should $help help be run through lang()
	*/
	function create_notify($label,$name,$rows,$cols,$help='',$default='',$vars2='',$subst_help=True,$run_lang=True)
	{
		$vars = $GLOBALS['egw']->preferences->vars;
		if(is_array($vars2))
		{
			$vars += $vars2;
		}
		$this->bo->prefs[$name] = $GLOBALS['egw']->preferences->lang_notify($this->bo->prefs[$name],$vars);

		$this->notifies[$name] = $vars;	// this gets saved in the app_session for re-translation

		$help = $help && ($run_lang || is_null($run_lang)) ? lang($help) : $help;
		if($subst_help || is_null($subst_help))
		{
			$help .= '<p><b>'.lang('Substitutions and their meanings:').'</b>';
			foreach($vars as $var => $var_help)
			{
				$lname = ($lname = lang($var)) == $var.'*' ? $var : $lname;
				$help .= "<br>\n".'<b>$$'.$lname.'$$</b>: '.$var_help;
			}
			$help .= "</p>\n";
		}
		if($row == 1)
		{
			$this->create_input_box($label,$name,$help,$default,$cols,'','',False);
		}
		else
		{
			$this->create_text_area($label,$name,$rows,$cols,$help,$default,False);
		}
	}

	function create_text_area($label,$name,$rows,$cols,$help='',$default='',$run_lang=True)
	{
		$charSet = translation::charset();

		$_appname = $this->check_app();
		if($this->is_forced_value($_appname,$name))
		{
			return True;
		}

		if(isset($this->bo->prefs[$name]) || $GLOBALS['type'] != 'user')
		{
			$default = $this->bo->prefs[$name];
		}

		if($GLOBALS['type'] == 'user')
		{
			$def_text = !$GLOBALS['egw']->preferences->user[$_appname][$name] ? $GLOBALS['egw']->preferences->data[$_appname][$name] : $GLOBALS['egw']->preferences->default[$_appname][$name];

			if(isset($this->notifies[$name]))	// translate the substitution names
			{
				$def_text = $GLOBALS['egw']->preferences->lang_notify($def_text,$this->notifies[$name]);
			}
			$def_text = $def_text != '' ? '<br><i><font size="-1"><b>'.lang('default').'</b>:<br>'.nl2br($def_text).'</font></i>' : '';
		}
		$this->t->set_var('row_value',"<textarea rows=\"$rows\" cols=\"$cols\" name=\"${GLOBALS[type]}[$name]\">".
		htmlentities($default,ENT_COMPAT,$charSet)."</textarea>$def_text");
		$this->t->set_var('row_name',lang($label));
		$GLOBALS['egw']->nextmatchs->template_alternate_row_color($this->t);

		$this->t->fp('rows',$this->process_help($help,$run_lang) ? 'help_row' : 'row',True);
	}

	/* Makes the ifs a little nicer, plus ... this will change once the ACL manager is in place */
	/* and is able to create less powerfull admins.  This will handle the ACL checks for that (jengo) */
	function is_admin()
	{
		if(HAS_ADMIN_RIGHTS == 1 && empty($this->prefix))	// tabs only without prefix
		{
			return True;
		}
		else
		{
			return False;
		}
	}

	function show_list($header='&nbsp;')
	{
		$this->t->set_var('list_header',$header);
		$this->t->parse('lists','list',$this->list_shown);

		$this->t->set_var('rows','');
		$this->list_shown = True;
	}
}
