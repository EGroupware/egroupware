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

	$phpgw_info = array();
	$phpgw_info["flags"] = array(
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

	class phpgw
	{
		var $common;
		var $accounts;
		var $applications;
		var $db;
	}
	$phpgw = new phpgw;
	$phpgw->common = CreateObject('phpgwapi.common');

	$common = $phpgw->common;
	$GLOBALS['phpgw_setup']->loaddb();
	$phpgw->db = $GLOBALS['phpgw_setup']->db;

	$tpl_root = $GLOBALS['phpgw_setup']->html->setup_tpl_dir('setup');
	$setup_tpl = CreateObject('phpgwapi.Template',$tpl_root);
	$setup_tpl->set_file(array(
		'ldap'   => 'ldap.tpl',
		'T_head' => 'head.tpl',
		'T_footer' => 'footer.tpl',
		'T_alert_msg' => 'msg_alert_msg.tpl'
	));

	$GLOBALS['phpgw_setup']->db->query("SELECT config_name,config_value FROM phpgw_config WHERE config_name LIKE 'ldap%'",__LINE__,__FILE__);
	while ($GLOBALS['phpgw_setup']->db->next_record())
	{
		$config[$GLOBALS['phpgw_setup']->db->f('config_name')] = $GLOBALS['phpgw_setup']->db->f('config_value');
	}
	$phpgw_info['server']['ldap_host']          = $config['ldap_host'];
	$phpgw_info['server']['ldap_context']       = $config['ldap_context'];
	$phpgw_info['server']['ldap_group_context'] = $config['ldap_group_context'];
	$phpgw_info['server']['ldap_root_dn']       = $config['ldap_root_dn'];
	$phpgw_info['server']['ldap_root_pw']       = $config['ldap_root_pw'];
	$phpgw_info['server']['ldap_account_home']  = $config['ldap_account_home'];
	$phpgw_info['server']['ldap_account_shell'] = $config['ldap_account_shell'];
	$phpgw_info['server']['ldap_extra_attributes'] = $config['ldap_extra_attributes'];

	$phpgw_info['server']['account_repository'] = 'ldap';

	$phpgw->accounts     = CreateObject('phpgwapi.accounts');
	$acct                = $phpgw->accounts;

	// First, see if we can connect to the LDAP server, if not send `em back to config.php with an
	// error message.

	// connect to ldap server
	if(!$ldap = $common->ldapConnect())
	{
		$noldapconnection = True;
	}

	if($noldapconnection)
	{
		Header('Location: config.php?error=badldapconnection');
		exit;
	}

	$sql = "SELECT * FROM phpgw_accounts WHERE account_type='u'";
	$GLOBALS['phpgw_setup']->db->query($sql,__LINE__,__FILE__);
	while($GLOBALS['phpgw_setup']->db->next_record())
	{
		$i = $GLOBALS['phpgw_setup']->db->f('account_id');
		$account_info[$i]['account_id']        = $GLOBALS['phpgw_setup']->db->f('account_id');
		$account_info[$i]['account_lid']       = $GLOBALS['phpgw_setup']->db->f('account_lid');
		$account_info[$i]['account_firstname'] = $GLOBALS['phpgw_setup']->db->f('account_firstname');
		$account_info[$i]['account_lastname']  = $GLOBALS['phpgw_setup']->db->f('account_lastname');
		$account_info[$i]['account_status']    = $GLOBALS['phpgw_setup']->db->f('account_status');
		$account_info[$i]['account_expires']   = $GLOBALS['phpgw_setup']->db->f('account_expires');
	}

	while(list($key,$data) = @each($account_info))
	{
		$tmp = $data['account_id'];
		$newaccount[$tmp] = $data;
	}
	$account_info = $newaccount;

	$sql = "SELECT * FROM phpgw_accounts WHERE account_type='g'";
	$GLOBALS['phpgw_setup']->db->query($sql,__LINE__,__FILE__);
	while($GLOBALS['phpgw_setup']->db->next_record())
	{
		$i = $GLOBALS['phpgw_setup']->db->f('account_id');
		$group_info[$i]['account_id']        = $GLOBALS['phpgw_setup']->db->f('account_id');
		$group_info[$i]['account_lid']       = $GLOBALS['phpgw_setup']->db->f('account_lid');
		$group_info[$i]['account_firstname'] = $GLOBALS['phpgw_setup']->db->f('account_firstname');
		$group_info[$i]['account_lastname']  = $GLOBALS['phpgw_setup']->db->f('account_lastname');
		$group_info[$i]['account_status']    = $GLOBALS['phpgw_setup']->db->f('account_status');
		$group_info[$i]['account_expires']   = $GLOBALS['phpgw_setup']->db->f('account_expires');
	}

	while(list($key,$data) = @each($group_info))
	{
		$tmp = $data['account_id'][0];
		$newgroup[$tmp] = $data;
	}
	$group_info = $newgroup;

	if($cancel)
	{
		Header('Location: ldap.php');
		exit;
	}

	if($submit)
	{
		if($ldapgroups)
		{
			while(list($key,$groupid) = each($ldapgroups))
			{
				$id_exist = 0;
				$thisacctid    = $group_info[$groupid]['account_id'];
				$thisacctlid   = $group_info[$groupid]['account_lid'];
				$thisfirstname = $group_info[$groupid]['account_firstname'];
				$thislastname  = $group_info[$groupid]['account_lastname'];
				$thismembers   = $group_info[$groupid]['members'];

				// Do some checks before we try to import the data to LDAP.
				if(!empty($thisacctid) && !empty($thisacctlid))
				{
					$groups = CreateObject('phpgwapi.accounts',intval($thisacctid));
					$groups->db = $GLOBALS['phpgw_setup']->db;

					// Check if the account is already there.
					// If so, we won't try to create it again.
					$acct_exist = $acct->name2id($thisacctlid);
					if($acct_exist)
					{
						$thisacctid = $acct_exist;
					}
					$id_exist = $groups->exists(intval($thisacctid));
					
					echo '<br>accountid: ' . $thisacctid;
					echo '<br>accountlid: ' . $thisacctlid;
					echo '<br>exists: ' . $id_exist;
					
					/* If not, create it now. */
					if(!$id_exist)
					{
						$thisaccount_info = array(
							'account_type'      => 'g',
							'account_id'        => $thisacctid,
							'account_lid'       => $thisacctlid,
							'account_passwd'    => 'x',
							'account_firstname' => $thisfirstname,
							'account_lastname'  => $thislastname,
							'account_status'    => 'A',
							'account_expires'   => -1
						);
						$groups->create($thisaccount_info);
					}
				}
			}
		}

		if($users)
		{
			while(list($key,$accountid) = each($users))
			{
				$id_exist = 0; $acct_exist = 0;
				$thisacctid    = $account_info[$accountid]['account_id'];
				$thisacctlid   = $account_info[$accountid]['account_lid'];
				$thisfirstname = $account_info[$accountid]['account_firstname'];
				$thislastname  = $account_info[$accountid]['account_lastname'];

				// Do some checks before we try to import the data.
				if(!empty($thisacctid) && !empty($thisacctlid))
				{
					$accounts = CreateObject('phpgwapi.accounts',intval($thisacctid));
					$accounts->db = $GLOBALS['phpgw_setup']->db;

					// Check if the account is already there.
					// If so, we won't try to create it again.
					$acct_exist = $acct->name2id($thisacctlid);
					if($acct_exist)
					{
						$thisacctid = $acct_exist;
					}
					$id_exist = $accounts->exists(intval($thisacctid));
					// If not, create it now.
					if(!$id_exist)
					{
						echo '<br>Adding' . $thisacctid;
						$thisaccount_info = array(
							'account_type'      => 'u',
							'account_id'        => $thisacctid,
							'account_lid'       => $thisacctlid,
							'account_passwd'    => 'x',
							'account_firstname' => $thisfirstname,
							'account_lastname'  => $thislastname,
							'account_status'    => 'A',
							'account_expires'   => -1,
							'homedirectory'     => $config['ldap_account_home'] . '/' . $thisacctlid,
							'loginshell'        => $config['ldap_account_shell']
						);
						$accounts->create($thisaccount_info);
					}
				}
			}
		}
		$setup_complete = True;
	}

	$GLOBALS['phpgw_setup']->html->show_header('LDAP Export','','ldapexport',$ConfigDomain);

	if($error)
	{
		//echo '<br><center><b>Error:</b> '.$error.'</center>';
		$GLOBALS['phpgw_setup']->html->show_alert_msg('Error',$error);
	}

	if($setup_complete)
	{
		echo lang('<br><center>Export has been completed!  You will need to set the user passwords manually.</center>');
		echo lang('<br><center>Click <a href="index.php">here</a> to return to setup </center>');
		$GLOBALS['phpgw_setup']->html->show_footer();
		exit;
	}

	$setup_tpl->set_block('ldap','header','header');
	$setup_tpl->set_block('ldap','user_list','user_list');
	$setup_tpl->set_block('ldap','admin_list','admin_list');
	$setup_tpl->set_block('ldap','group_list','group_list');
	$setup_tpl->set_block('ldap','app_list','app_list');
	$setup_tpl->set_block('ldap','submit','submit');
	$setup_tpl->set_block('ldap','footer','footer');

	while(list($key,$account) = @each($account_info))
	{
		$user_list .= '<option value="' . $account['account_id'] . '">'
			. $common->display_fullname($account['account_lid'],$account['account_firstname'],$account['account_lastname'])
			. '</option>';
	}

	@reset($account_info);
	while(list($key,$account) = @each($account_info))
	{
		$admin_list .= '<option value="' . $account['account_id'] . '">'
			. $common->display_fullname($account['account_lid'],$account['account_firstname'],$account['account_lastname'])
			. '</option>';
	}

	while(list($key,$group) = @each($group_info))
	{
		$group_list .= '<option value="' . $group['account_id'] . '">'
			. $group['account_lid']
			. '</option>';
	}

	$setup_tpl->set_var('action_url','ldapexport.php');
	$setup_tpl->set_var('users',$user_list);
	$setup_tpl->set_var('admins',$admin_list);
	$setup_tpl->set_var('ldapgroups',$group_list);
	$setup_tpl->set_var('s_apps',$app_list);

	$setup_tpl->set_var('ldap_import',lang('LDAP export users'));
	$setup_tpl->set_var('description',lang("This section will help you export users and groups from phpGroupWare's account tables into your LDAP tree").'.');
	$setup_tpl->set_var('select_users',lang('Select which user(s) will be exported'));
	$setup_tpl->set_var('select_groups',lang('Select which group(s) will be exported (group membership will be maintained)'));
	$setup_tpl->set_var('form_submit','export');
	$setup_tpl->set_var('cancel',lang('Cancel'));

	$setup_tpl->pfp('out','header');
	if($account_info)
	{
		$setup_tpl->pfp('out','user_list');
	}
	if($group_info)
	{
		$setup_tpl->pfp('out','group_list');
	}
	$setup_tpl->pfp('out','submit');
	$setup_tpl->pfp('out','footer');

	$GLOBALS['phpgw_setup']->html->show_footer();
?>
