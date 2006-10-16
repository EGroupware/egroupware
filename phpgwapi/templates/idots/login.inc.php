<?php
   /**************************************************************************\
   * eGroupWare                                                               *
   * http://www.egroupware.org                                                *
   * --------------------------------------------                             *
   *  This program is free software; you can redistribute it and/or modify it *
   *  under the terms of the GNU General Public License as published by the   *
   *  Free Software Foundation; either version 2 of the License, or (at your  *
   *  option) any later version.                                              *
   \**************************************************************************/

   /* $Id$ */

   //to test:
   // no login

   function login_fetch_select_domain()
   {
	  $tmpl = CreateObject('phpgwapi.Template', $GLOBALS['egw_info']['server']['template_dir']);

	  $lang_domain_select = '&nbsp;';
	  //	  $lang_domain_select = lang('Domain');

	  $domain_select = '&nbsp;';
	  $domain_select = "<select name=\"logindomain\">\n";
		 foreach($GLOBALS['egw_domain'] as $domain_name => $domain_vars)
		 {
			$domain_select .= '<option value="' . $domain_name . '"';

			if($domain_name == $_COOKIE['last_domain'])
			{
			   $domain_select .= ' selected';
			}
			$domain_select .= '>' . $domain_name . "</option>\n";
		 }
		 $domain_select .= "</select>\n";

	  return '<tr>
		 <td align="right" tablindex="1">'.lang('Domain').':&nbsp;</td>
		 <td align="left">'.$domain_select.'</td>
		 <td align="left"></td>
	  </tr>';
   }

   function parse_login_screen()
   {
	  $tmpl = CreateObject('phpgwapi.Template', $GLOBALS['egw_info']['server']['template_dir']);
	  $tmpl->set_file(array('login_form' => 'login.tpl'));

	  $tmpl->set_var('lang_message',$GLOBALS['loginscreenmessage']);
	  
	  //$tmpl->set_block('login_form','domain_selection');
	  $last_loginid = $_COOKIE['last_loginid'];

	  if($GLOBALS['egw_info']['server']['show_domain_selectbox'])
	  {
		 $domain_select = login_fetch_select_domain();
	  }
	  elseif($last_loginid !== '')
	  {
		 reset($GLOBALS['egw_domain']);
		 list($default_domain) = each($GLOBALS['egw_domain']);

		 if($_COOKIE['last_domain'] != $default_domain && !empty($_COOKIE['last_domain']))
		 {
			$last_loginid .= '@' . $_COOKIE['last_domain'];
		 }
	  }

	  //$tmpl->set_var('lang_select_domain',$lang_domain_select);
	  $tmpl->set_var('select_domain',$domain_select);

	  if(!$GLOBALS['egw_info']['server']['show_domain_selectbox'])
	  {
		 /* trick to make domain section disapear */
		 $tmpl->set_var('domain_selection',$GLOBALS['egw_info']['user']['domain'] ? 
		 '<input type="hidden" name="logindomain" value="'.htmlspecialchars($GLOBALS['egw_info']['user']['domain']).'" />' : '');
	  }
	  
	  $cnf_reg =& CreateObject('phpgwapi.config','registration');
	  $cnf_reg->read_repository();
	  $config_reg = $cnf_reg->config_data;

	  if($config_reg[enable_registration]=='True')
	  {
		 if ($config_reg[register_link]=='True')
		 {
			$reg_link='&nbsp;<a href="'. $GLOBALS['egw']->link('/registration/index.php','lang_code='.$_GET['lang']). '">'.lang('Not a user yet? Register now').'</a><br/>';
		 }
		 if ($config_reg[lostpassword_link]=='True')
		 {
			$lostpw_link='&nbsp;<a href="'. $GLOBALS['egw']->link('/registration/index.php','menuaction=registration.uireg.lostpw_step1_ask_login&lang_code='.$_GET['lang']). '">'.lang('Lost password').'</a><br/>';
		 }
		 if ($config_reg[lostid_link]=='True')
		 {
			$lostid_link='&nbsp;<a href="'. $GLOBALS['egw']->link('/registration/index.php','menuaction=registration.uireg.lostid_step1_ask_email&lang_code='.$_GET['lang']). '">'.lang('Lost Login Id').'</a><br/>';
		 }

		 /* if at least one option of "registration" is activated display the registration section */
		 if($config_reg[register_link]=='True' || $config_reg[lostpassword_link]=='True' || $config_reg[lostid_link]=='True')
		 {
			$tmpl->set_var('register_link',$reg_link);
			$tmpl->set_var('lostpassword_link',$lostpw_link);
			$tmpl->set_var('lostid_link',$lostid_link) ;

			//$tmpl->set_var('registration_url',$GLOBALS['egw_info']['server']['webserver_url'] . '/registration/');
		 }
		 else
		 {
			/* trick to make registration section disapear */
			$tmpl->set_block('login_form','registration');
			$tmpl->set_var('registration','');
		 }
	  }

	  // add a content-type header to overwrite an existing default charset in apache (AddDefaultCharset directiv)
	  header('Content-type: text/html; charset='.$GLOBALS['egw']->translation->charset());

	  $GLOBALS['egw_info']['server']['template_set'] = $GLOBALS['egw_info']['login_template_set'];

	  $tmpl->set_var('charset',$GLOBALS['egw']->translation->charset());
	  $tmpl->set_var('login_url', $GLOBALS['egw_info']['server']['webserver_url'] . '/login.php' . $extra_vars);
	  $tmpl->set_var('version',$GLOBALS['egw_info']['server']['versions']['phpgwapi']);
	  $tmpl->set_var('cd',check_logoutcode($_GET['cd']));
	  $tmpl->set_var('cookie',$last_loginid);

	  $tmpl->set_var('lang_username',lang('username'));
	  $tmpl->set_var('lang_password',lang('password'));
	  $tmpl->set_var('lang_login',lang('login'));

	  $tmpl->set_var('website_title', $GLOBALS['egw_info']['server']['site_title']);
	  $tmpl->set_var('template_set',$GLOBALS['egw_info']['login_template_set']);
	  $tmpl->set_var('bg_color',($GLOBALS['egw_info']['server']['login_bg_color']?$GLOBALS['egw_info']['server']['login_bg_color']:'FFFFFF'));
	  $tmpl->set_var('bg_color_title',($GLOBALS['egw_info']['server']['login_bg_color_title']?$GLOBALS['egw_info']['server']['login_bg_color_title']:'486591'));

	  if (substr($GLOBALS['egw_info']['server']['login_logo_file'],0,4) == 'http')
	  {
		 $var['logo_file'] = $GLOBALS['egw_info']['server']['login_logo_file'];
	  }
	  else
	  {
		 $var['logo_file'] = $GLOBALS['egw']->common->image('phpgwapi',$GLOBALS['egw_info']['server']['login_logo_file']?$GLOBALS['egw_info']['server']['login_logo_file']:'logo');
	  }
	  $var['logo_url'] = $GLOBALS['egw_info']['server']['login_logo_url']?$GLOBALS['egw_info']['server']['login_logo_url']:'http://www.eGroupWare.org';
	  if (substr($var['logo_url'],0,4) != 'http')
	  {
		 $var['logo_url'] = 'http://'.$var['logo_url'];
	  }
	  $var['logo_title'] = $GLOBALS['egw_info']['server']['login_logo_title']?$GLOBALS['egw_info']['server']['login_logo_title']:'www.eGroupWare.org';
	  $tmpl->set_var($var);

	  /* language section if activated in site config */
	  if (@$GLOBALS['egw_info']['server']['login_show_language_selection'])
	  {
		 $select_lang = '<select name="lang" onchange="'."if (this.form.login.value && this.form.passwd.value) this.form.submit(); else location.href=location.href+(location.search?'&':'?')+'lang='+this.value".'">';
			foreach ($GLOBALS['egw']->translation->get_installed_langs() as $key => $name)	// if we have a translation use it
			{
			   $select_lang .= "\n\t".'<option value="'.$key.'"'.($key == $GLOBALS['egw_info']['user']['preferences']['common']['lang'] ? ' selected="selected"' : '').'>'.$name.'</option>';
			}
			$select_lang .= "\n</select>\n";
		 $tmpl->set_var(array(
			'lang_language' => lang('Language'),
			'select_language' => $select_lang,
		 ));
	  }
	  else
	  {
		 $tmpl->set_block('login_form','language_select');
		 $tmpl->set_var('language_select','');
	  }

	  /********************************************************\
	  * Check if authentification via cookies is allowed       *
	  * and place a time selectbox, how long cookie is valid   *
	  \********************************************************/

	  if($GLOBALS['egw_info']['server']['allow_cookie_auth'])
	  {
		 $html =& CreateObject('phpgwapi.html'); /* Why the hell was nobody useing this here before??? */
		 $tmpl->set_block('login_form','remember_me_selection');
		 $tmpl->set_var('lang_remember_me',lang('Remember me'));
		 $tmpl->set_var('select_remember_me',$html->select('remember_me', 'forever', array(
			false => lang('not'),
			'1hour' => lang('1 Hour'),
			'1day' => lang('1 Day'),
			'1week'=> lang('1 Week'),
			'1month' => lang('1 Month'),
			'forever' => lang('Forever')),true
		 ));
	  }
	  else
	  {
		 /* trick to make remember_me section disapear */
		 $tmpl->set_block('login_form','remember_me_selection');
		 $tmpl->set_var('remember_me_selection','');
	  }

	  $tmpl->set_var('autocomplete', ($GLOBALS['egw_info']['server']['autocomplete_login'] ? 'autocomplete="off"' : ''));

	  $tmpl->pfp('loginout','login_form');
   }

   function login_parse_denylogin()	
   {
	  $tmpl = CreateObject('phpgwapi.Template', $GLOBALS['egw_info']['server']['template_dir']);

	  $deny_msg=lang('Oops! You caught us in the middle of system maintainance.<br/>
	  Please, check back with us shortly.');

	  $tmpl->set_file(array
	  (
		 'login_form' => 'login_denylogin.tpl'
	  ));

	  $tmpl->set_var('template_set','default');
	  $tmpl->set_var('deny_msg',$deny_msg);
	  $tmpl->pfp('loginout','login_form');
   }

