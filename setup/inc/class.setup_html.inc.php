<?php
/**
 * eGroupWare Setup
 *
 * @link http://www.egroupware.org
 * @package setup
 * @author Tony Puglisi (Angles) <angles@aminvestments.com>
 * @author Miles Lott <milos@groupwhere.org>
 * @author Ralf Becker <RalfBecker-AT-outdoor-training.de>
 * @license http://opensource.org/licenses/gpl-license.php GPL - GNU General Public License
 * @version $Id$
 */

/**
 * Some static helper functions to generate html stuff in setup
 */
class setup_html
{
	/**
	 * generate header.inc.php file output - NOT a generic html header function
	 *
	 */
	static function generate_header()
	{
		// PHP will automatically replace any dots in incoming
		// variable names with underscores.

		$GLOBALS['header_template']->set_file(array('header' => 'header.inc.php.template'));
		$GLOBALS['header_template']->set_block('header','domain','domain');
		$var = Array();

		$deletedomain = get_var('deletedomain',Array('POST'));
		$domains = get_var('domains',Array('POST'));

		foreach($domains as $k => $v)
		{
			if(is_array($deletedomain) && isset($deletedomain[$k]))
			{
				continue;
			}
			$variableName = str_replace('.','_',$k);
			$dom = get_var('setting_'.$variableName,Array('POST'));
			$GLOBALS['header_template']->set_var('DB_DOMAIN',$v);
			foreach($dom as $x => $y)
			{
				if(strtoupper($x) == 'CONFIG_PASS')
				{
					$GLOBALS['header_template']->set_var(strtoupper($x),md5($y));
				}
				else
				{
					$GLOBALS['header_template']->set_var(strtoupper($x),$y);
				}
			}
			/* Admin did not type a new password, so use the old one from the hidden field,
			 * which is already md5 encoded.
			 */
			if($dom['config_password'] && !$dom['config_pass'])
			{
				/* Real == hidden */
				$GLOBALS['header_template']->set_var('CONFIG_PASS',$dom['config_password']);
			}
			/* If the admin didn't select a db_port, set to the default */
			if(!$dom['db_port'])
			{
				$GLOBALS['header_template']->set_var('DB_PORT',$GLOBALS['default_db_ports'][$dom['db_type']]);
			}
			$GLOBALS['header_template']->parse('domains','domain',True);
		}

		$GLOBALS['header_template']->set_var('domain','');
		
		$setting = get_var('setting',Array('POST'));
		while($setting && list($k,$v) = @each($setting))
		{
			if(strtoupper($k) == 'HEADER_ADMIN_PASSWORD')
			{
				$var[strtoupper($k)] = md5($v);
			}
			else
			{
				$var[strtoupper($k)] = $v;
			}
		}
		/* Admin did not type a new header password, so use the old one from the hidden field,
		 * which is already md5 encoded.
		 */
		if($var['HEADER_ADMIN_PASS'] && empty($setting['HEADER_ADMIN_PASSWORD']))
		{
			/* Real == hidden */
			$var['HEADER_ADMIN_PASSWORD'] = $var['HEADER_ADMIN_PASS'];
		}
		$GLOBALS['header_template']->set_var($var);
		return $GLOBALS['header_template']->parse('out','header');
	}

	static function setup_tpl_dir($app_name='setup')
	{
		/* hack to get tpl dir */
		if (is_dir(EGW_SERVER_ROOT))
		{
			$srv_root = EGW_SERVER_ROOT . SEP . "$app_name" . SEP;
		}
		else
		{
			$srv_root = '';
		}

		$tpl_typical = 'templates' . SEP . 'default';
		$tpl_root = "$srv_root" ."$tpl_typical";
		return $tpl_root;
	}

	static function show_header($title='',$nologoutbutton=False, $logoutfrom='config', $configdomain='')
	{
		// add a content-type header to overwrite an existing default charset in apache (AddDefaultCharset directiv)
		header('Content-type: text/html; charset='.$GLOBALS['egw_setup']->system_charset);

		$GLOBALS['setup_tpl']->set_var('charset',$GLOBALS['egw_setup']->system_charset);
		$style = array(
			'th_bg'		=> '#486591',
			'th_text'	=> '#FFFFFF',
			'row_on'	=> '#DDDDDD',
			'row_off'	=> '#EEEEEE',
			'banner_bg'	=> '#4865F1',
			'msg'		=> '#FF0000',
		);
		$GLOBALS['setup_tpl']->set_var($style);
		if ($nologoutbutton)
		{
			$GLOBALS['setup_tpl']->set_block('T_head','loged_in');
			$GLOBALS['setup_tpl']->set_var('loged_in','');
		}
		else
		{
			$btn_logout = '<a href="index.php?FormLogout=' . $logoutfrom . '" class="link">' . lang('Logout').'</a>';
			$check_install = '<a class="textsidebox" href="check_install.php">'.lang('Check installation').'</a>';
			$register_hooks = '<a class="textsidebox" href="applications.php?hooks=1">'.lang('Find and Register all Application Hooks').'</a>';
		}

		$GLOBALS['setup_tpl']->set_var('lang_setup', lang('setup'));
		$GLOBALS['setup_tpl']->set_var('page_title',$title);
		if ($configdomain == '')
		{
			$GLOBALS['setup_tpl']->set_var('configdomain','');
		}
		else
		{
			$GLOBALS['setup_tpl']->set_var('configdomain',' - ' . lang('Domain') . ': ' . $configdomain);
		}

		if(basename($_SERVER['SCRIPT_FILENAME']) != 'index.php')
		{
			$index_btn = '<a href="index.php" class="link">' . lang('Setup Main Menu') . '</a>';
			$index_img = '<img src="../phpgwapi/templates/idots/images/orange-ball.png" alt="ball" />';
		}

		$GLOBALS['setup_tpl']->set_var('lang_version',lang('version'));
		$GLOBALS['setup_tpl']->set_var('pgw_ver',@$GLOBALS['egw_info']['server']['versions']['phpgwapi']);
		$GLOBALS['setup_tpl']->set_var(array(
			'logoutbutton'  => $btn_logout,
			'indexbutton'   => $index_btn,
			'indeximg'      => $index_img,
			'check_install' => $check_install,
			'register_hooks'=> $register_hooks,
			'main_menu'     => lang('Setup Main Menu'),
			'user_login'    => lang('Back to user login')
		));

		// manual / help link to the install manual on egroupware.org
		$manual_remote_egw_url = 'http://manual.egroupware.org/egroupware';
		$url_parts = explode('/',$_SERVER['PHP_SELF']);
		$script = array_pop($url_parts);
		$lang = setup::get_lang();
		$url = $manual_remote_egw_url.'/manual/index.php?referer='.urlencode($manual_remote_egw_url.'/setup/'.$script).
			($lang ? '&lang='.urlencode($lang) : '');
		$GLOBALS['setup_tpl']->set_var('manual','<a href="'.$url.'" target="manual" onclick="'.
			"window.open('$url','manual','width=800,height=600,scrollbars=yes,resizable=yes'); return false;".'">'.
			htmlspecialchars(lang('Manual / help')).'</a>');

		$GLOBALS['setup_tpl']->pparse('out','T_head');
		/* $setup_tpl->set_var('T_head',''); */
	}

	static function show_footer()
	{
		$GLOBALS['setup_tpl']->pparse('out','T_footer');
		unset($GLOBALS['setup_tpl']);
	}

	static function show_alert_msg($alert_word='Setup alert',$alert_msg='setup alert (generic)')
	{
		$GLOBALS['setup_tpl']->set_var('V_alert_word',$alert_word);
		$GLOBALS['setup_tpl']->set_var('V_alert_msg',$alert_msg);
		$GLOBALS['setup_tpl']->pparse('out','T_alert_msg');
	}

	static function make_frm_btn_simple($pre_frm_blurb='',$frm_method='post',$frm_action='',$input_type='submit',$input_value='',$post_frm_blurb='')
	{
		/* a simple form has simple components */
		$simple_form = $pre_frm_blurb  ."\n"
			. '<form method="' . $frm_method . '" action="' . $frm_action  . '">' . "\n"
			. '<input type="'  . $input_type . '" value="'  . $input_value . '" />' . "\n"
			. '</form>' . "\n"
			. $post_frm_blurb . "\n";
		return $simple_form;
	}

	static function make_href_link_simple($pre_link_blurb='',$href_link='',$href_text='default text',$post_link_blurb='')
	{
		/* a simple href link has simple components */
		$simple_link = $pre_link_blurb
			. '<a href="' . $href_link . '">' . $href_text . '</a> '
			. $post_link_blurb . "\n";
		return $simple_link;
	}

	static function login_form()
	{
		/* begin use TEMPLATE login_main.tpl */
		$GLOBALS['setup_tpl']->set_var('ConfigLoginMSG',@$GLOBALS['egw_info']['setup']['ConfigLoginMSG']);
		$GLOBALS['setup_tpl']->set_var('HeaderLoginMSG',@$GLOBALS['egw_info']['setup']['HeaderLoginMSG']);
		$GLOBALS['setup_tpl']->set_var('lang_header_username',lang('Header Username'));
		$GLOBALS['setup_tpl']->set_var('lang_header_password',lang('Header Password'));
		$GLOBALS['setup_tpl']->set_var('lang_header_login',lang('Header Admin Login'));
		$GLOBALS['setup_tpl']->set_var('lang_config_login',lang('Setup/Config Admin Login'));
		$GLOBALS['setup_tpl']->set_var('lang_config_username',lang('Config Username'));
		$GLOBALS['setup_tpl']->set_var('lang_config_password',lang('Config Password'));
		$GLOBALS['setup_tpl']->set_var('lang_domain',lang('Domain'));

		$GLOBALS['setup_tpl']->set_var('lang_select',self::lang_select());

		if ($GLOBALS['egw_info']['setup']['stage']['header'] == '10')
		{
			/*
			 Begin use SUB-TEMPLATE login_stage_header,
			 fills V_login_stage_header used inside of login_main.tpl
			*/
			if (count($GLOBALS['egw_domain']) > 1)
			{
				foreach($GLOBALS['egw_domain'] as $domain => $data)
				{
					$domains .= "<option value=\"$domain\" ".($domain == @$GLOBALS['egw_info']['setup']['LastDomain'] ? ' selected="selected"' : '').">$domain</option>\n";
				}
				$GLOBALS['setup_tpl']->set_var('domains',$domains);

				// use BLOCK B_multi_domain inside of login_stage_header
				$GLOBALS['setup_tpl']->parse('V_multi_domain','B_multi_domain');
				// in this case, the single domain block needs to be nothing
				$GLOBALS['setup_tpl']->set_var('V_single_domain','');
			}
			else
			{
				reset($GLOBALS['egw_domain']);
				$default_domain = each($GLOBALS['egw_domain']);
				$GLOBALS['setup_tpl']->set_var('default_domain_zero',$default_domain[0]);

				/* Use BLOCK B_single_domain inside of login_stage_header */
				$GLOBALS['setup_tpl']->parse('V_single_domain','B_single_domain');
				/* in this case, the multi domain block needs to be nothing */
				$GLOBALS['setup_tpl']->set_var('V_multi_domain','');
			}
			/*
			 End use SUB-TEMPLATE login_stage_header
			 put all this into V_login_stage_header for use inside login_main
			*/
			$GLOBALS['setup_tpl']->parse('V_login_stage_header','T_login_stage_header');
		}
		else
		{
			/* begin SKIP SUB-TEMPLATE login_stage_header */
			$GLOBALS['setup_tpl']->set_var('V_multi_domain','');
			$GLOBALS['setup_tpl']->set_var('V_single_domain','');
			$GLOBALS['setup_tpl']->set_var('V_login_stage_header','');
		}
		/*
		 end use TEMPLATE login_main.tpl
		 now out the login_main template
		*/
		$GLOBALS['setup_tpl']->pparse('out','T_login_main');
	}

	static function lang_select($onChange=False,$ConfigLang='')
	{
		if (!$ConfigLang)
		{
			$ConfigLang = setup::get_lang();
		}
		$select = '<select name="ConfigLang"'.($onChange ? ' onchange="this.form.submit();"' : '').'>' . "\n";
		$languages = setup_translation::get_supported_langs();
		foreach($languages as $data)
		{
			if($data['available'] && !empty($data['lang']))
			{
				$short = substr($data['lang'],0,2);
				if ($short == $ConfigLang || $data['lang'] == $ConfigLang || empty($ConfigLang) && $short == substr($_SERVER['HTTP_ACCEPT_LANGUAGE'],0,2))
				{
					$selected = ' selected';
				}
				else
				{
					$selected = '';
				}
				$select .= '<option value="' . $data['lang'] . '"' . $selected . '>' . $data['descr'] . '</option>' . "\n";
			}
		}
		$select .= '</select>' . "\n";
	
		return $select;
	}

	static function get_template_list()
	{
		$d = dir(EGW_SERVER_ROOT . '/phpgwapi/templates');

		while($entry = $d->read())
		{
			if ($entry != 'CVS' && $entry != '.' && $entry != '..')
			{
				$list[$entry]['name'] = $entry;
				$f = EGW_SERVER_ROOT . '/phpgwapi/templates/' . $entry . '/details.inc.php';
				if (file_exists ($f))
				{
					include($f);
					$list[$entry]['title'] = 'Use ' . $GLOBALS['egw_info']['template'][$entry]['title'] . 'interface';
				}
				else
				{
					$list[$entry]['title'] = $entry;
				}
			}
		}
		$d->close();
		reset ($list);
		return $list;
	}

	static function list_themes()
	{
		$dh = dir(EGW_SERVER_ROOT . '/phpgwapi/themes');
		while ($file = $dh->read())
		{
			if (preg_match('/'."\.theme$".'/i', $file))
			{
				$list[] = substr($file,0,strpos($file,'.'));
			}
		}
		$dh->close();
		reset ($list);
		return $list;
	}
}
