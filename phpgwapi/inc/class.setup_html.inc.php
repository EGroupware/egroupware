<?php
  /**************************************************************************\
  * phpGroupWare - Setup                                                     *
  * http://www.phpgroupware.org                                              *
  * --------------------------------------------                             *
  * This file written by Tony Puglisi (Angles) <angles@phpgroupware.org>     *
  *  and Miles Lott<milosch@phpgroupware.org>                                *
  * --------------------------------------------                             *
  *  This program is free software; you can redistribute it and/or modify it *
  *  under the terms of the GNU General Public License as published by the   *
  *  Free Software Foundation; either version 2 of the License, or (at your  *
  *  option) any later version.                                              *
  \**************************************************************************/

  /* $Id$ */

	class setup_html
	{
		/*!
		@function generate_header
		@abstract generate header.inc.php file output - NOT a generic html header function
		*/
		function generate_header()
		{
			$GLOBALS['header_template']->set_file(array('header' => 'header.inc.php.template'));
			$GLOBALS['header_template']->set_block('header','domain','domain');
			$var = Array();

			$deletedomain = get_var('deletedomain',Array('POST'));
			$domains = get_var('domains',Array('POST'));
			@reset($domains);
			while($domains && list($k,$v) = @each($domains))
			{
				if(isset($deletedomain[$k]))
				{
					continue;
				}
				$dom = get_var('setting_'.$k,Array('POST'));
				$GLOBALS['header_template']->set_var('DB_DOMAIN',$v);
				while(list($x,$y) = @each($dom))
				{
					$GLOBALS['header_template']->set_var(strtoupper($x),$y);
				}
				$GLOBALS['header_template']->parse('domains','domain',True);
			}

			$GLOBALS['header_template']->set_var('domain','');

			$setting = get_var('setting',Array('POST'));
			while($setting && list($k,$v) = @each($setting))
			{
				$var[strtoupper($k)] = $v;
			}
			$GLOBALS['header_template']->set_var($var);
			return $GLOBALS['header_template']->parse('out','header');
		}

		function setup_tpl_dir($app_name='setup')
		{
			/* hack to get tpl dir */
			if (is_dir(PHPGW_SERVER_ROOT))
			{
				$srv_root = PHPGW_SERVER_ROOT . SEP . "$app_name" . SEP;
			}
			else
			{
				$srv_root = '';
			}

			$tpl_typical = 'templates' . SEP . 'default';
			$tpl_root = "$srv_root" ."$tpl_typical";
			return $tpl_root;
		}

		function show_header($title='',$nologoutbutton=False, $logoutfrom='config', $configdomain='')
		{
			$GLOBALS['setup_tpl']->set_var('lang_charset',lang('charset'));
			$style = array('th_bg'		=> '#486591',
					'th_text'	=> '#FFFFFF',
					'row_on'	=> '#DDDDDD',
					'row_off'	=> '#EEEEEE',
					'banner_bg'	=> '#4865F1',
					'msg'		=> '#FF0000',
					);
			$GLOBALS['setup_tpl']->set_var($style);
			if ($nologoutbutton)
			{
				$btn_logout = '&nbsp;';
			}
			else
			{
				$btn_logout = '<a href="index.php?FormLogout=' . $logoutfrom . '" class="link">' . lang('Logout').'</a>';
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
			$GLOBALS['setup_tpl']->set_var('pgw_ver',@$GLOBALS['phpgw_info']['server']['versions']['phpgwapi']);
			$GLOBALS['setup_tpl']->set_var('logoutbutton',$btn_logout);
			$GLOBALS['setup_tpl']->pparse('out','T_head');
			/* $setup_tpl->set_var('T_head',''); */
		}

		function show_footer()
		{
			$GLOBALS['setup_tpl']->pparse('out','T_footer');
			unset($GLOBALS['setup_tpl']);
		}

		function show_alert_msg($alert_word='Setup alert',$alert_msg='setup alert (generic)')
		{
			$GLOBALS['setup_tpl']->set_var('V_alert_word',$alert_word);
			$GLOBALS['setup_tpl']->set_var('V_alert_msg',$alert_msg);
			$GLOBALS['setup_tpl']->pparse('out','T_alert_msg');
		}

		function make_frm_btn_simple($pre_frm_blurb='',$frm_method='POST',$frm_action='',$input_type='submit',$input_value='',$post_frm_blurb='')
		{
			/* a simple form has simple components */
			$simple_form = $pre_frm_blurb  ."\n"
				. '<form method="' . $frm_method . '" action="' . $frm_action  . '">' . "\n"
				. '<input type="'  . $input_type . '" value="'  . $input_value . '">' . "\n"
				. '</form>' . "\n"
				. $post_frm_blurb . "\n";
			return $simple_form;
		}

		function make_href_link_simple($pre_link_blurb='',$href_link='',$href_text='default text',$post_link_blurb='')
		{
			/* a simple href link has simple components */
			$simple_link = $pre_link_blurb
				. '<a href="' . $href_link . '">' . $href_text . '</a> '
				. $post_link_blurb . "\n";
			return $simple_link;
		}

		function login_form()
		{
			/* begin use TEMPLATE login_main.tpl */
			$GLOBALS['setup_tpl']->set_var('ConfigLoginMSG',@$GLOBALS['phpgw_info']['setup']['ConfigLoginMSG']);
			$GLOBALS['setup_tpl']->set_var('HeaderLoginMSG',@$GLOBALS['phpgw_info']['setup']['HeaderLoginMSG']);

			$GLOBALS['setup_tpl']->set_var('lang_select',lang_select());

			if ($GLOBALS['phpgw_info']['setup']['stage']['header'] == '10')
			{
				/*
				 Begin use SUB-TEMPLATE login_stage_header,
				 fills V_login_stage_header used inside of login_main.tpl
				*/
				if (count($GLOBALS['phpgw_domain']) > 1)
				{
					foreach($GLOBALS['phpgw_domain'] as $domain => $data)
					{
						$domains .= "<option value=\"$domain\" ".($domain == @$GLOBALS['phpgw_info']['setup']['LastDomain'] ? ' SELECTED' : '').">$domain</option>\n";
					}
					$GLOBALS['setup_tpl']->set_var('domains',$domains);

					// use BLOCK B_multi_domain inside of login_stage_header
					$GLOBALS['setup_tpl']->parse('V_multi_domain','B_multi_domain');
					// in this case, the single domain block needs to be nothing
					$GLOBALS['setup_tpl']->set_var('V_single_domain','');
				}
				else
				{
					reset($GLOBALS['phpgw_domain']);
					$default_domain = each($GLOBALS['phpgw_domain']);
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

		function get_template_list()
		{
			$d = dir(PHPGW_SERVER_ROOT . '/phpgwapi/templates');

			while($entry = $d->read())
			{
				if ($entry != 'CVS' && $entry != '.' && $entry != '..')
				{
					$list[$entry]['name'] = $entry;
					$f = PHPGW_SERVER_ROOT . '/phpgwapi/templates/' . $entry . '/details.inc.php';
					if (file_exists ($f))
					{
						include($f);
						$list[$entry]['title'] = 'Use ' . $GLOBALS['phpgw_info']['template'][$entry]['title'] . 'interface';
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

		function list_themes()
		{
			$dh = dir(PHPGW_SERVER_ROOT . '/phpgwapi/themes');
			while ($file = $dh->read())
			{
				if (eregi("\.theme$", $file))
				{
					$list[] = substr($file,0,strpos($file,'.'));
				}
			}
			$dh->close();
			reset ($list);
			return $list;
		}
	}
?>
