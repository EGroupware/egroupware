<?php
  /**************************************************************************\
  * phpGroupWare - Setup                                                     *
  * http://www.phpgroupware.org                                              *
  * --------------------------------------------                             *
  *  This program is free software; you can redistribute it and/or modify it *
  *  under the terms of the GNU General Public License as published by the   *
  *  Free Software Foundation; either version 2 of the License, or (at your  *
  *  option) any later version.                                              *
  \**************************************************************************/

  /* $Id$ */

	$GLOBALS['phpgw_info'] = array();
	$GLOBALS['phpgw_info']['flags'] = array(
		'noheader'   => True,
		'nonavbar'   => True,
		'currentapp' => 'home',
		'noapi'      => True
	);
	include('./inc/functions.inc.php');

	// Authorize the user to use setup app and load the database
	if (!$GLOBALS['phpgw_setup']->auth('Config'))
	{
		Header('Location: index.php');
		exit;
	}
	// Does not return unless user is authorized

	if (get_var('cancel',Array('POST')))
	{
		Header('Location: index.php');
		exit;
	}

	$tpl_root = $GLOBALS['phpgw_setup']->html->setup_tpl_dir('setup');
	$setup_tpl = CreateObject('setup.Template',$tpl_root);
	$setup_tpl->set_file(array(
		'ldap'   => 'ldap.tpl',
		'T_head' => 'head.tpl',
		'T_footer' => 'footer.tpl',
		'T_alert_msg' => 'msg_alert_msg.tpl'
	));

	$GLOBALS['phpgw_setup']->html->show_header(lang('LDAP Config'),'','config',$ConfigDomain);

	if ($GLOBALS['error'])
	{
		//echo '<br><center><b>Error:</b> '.$error.'</center>';
		$GLOBALS['phpgw_setup']->html->show_alert_msg('Error',$GLOBALS['error']);
	}

	$setup_tpl->set_block('ldap','header','header');
	$setup_tpl->set_block('ldap','jump','jump');
	$setup_tpl->set_block('ldap','cancel_only','cancel_only');
	$setup_tpl->set_block('ldap','footer','footer');

	$setup_tpl->set_var('description',lang('LDAP Accounts Configuration'));
	$setup_tpl->set_var('lang_ldapmodify',lang('Modify an existing LDAP account store for use with phpGroupWare (for a new install using LDAP accounts)'));
	$setup_tpl->set_var('lang_ldapimport',lang('Import accounts from LDAP to the phpGroupware accounts table (for a new install using SQL accounts)'));
	$setup_tpl->set_var('lang_ldapexport',lang('Export phpGroupware accounts from SQL to LDAP'));
	$setup_tpl->set_var('lang_ldapdummy',lang('Setup demo accounts in LDAP'));
	$setup_tpl->set_var('ldapmodify','ldapmodify.php');
	$setup_tpl->set_var('ldapimport','ldapimport.php');
	$setup_tpl->set_var('ldapexport','ldapexport.php');
	$setup_tpl->set_var('ldapdummy','setup_demo.php');
	$setup_tpl->set_var('action_url','index.php');
	$setup_tpl->set_var('cancel',lang('Cancel'));

	$setup_tpl->pfp('out','header');
	$setup_tpl->pfp('out','jump');
	$setup_tpl->pfp('out','cancel_only');
	$setup_tpl->pfp('out','footer');

	$GLOBALS['phpgw_setup']->html->show_footer();
?>
