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

	/* Authorize the user to use setup app and load the database */
	if (!$GLOBALS['phpgw_setup']->auth('Config'))
	{
		Header('Location: index.php');
		exit;
	}
	/* Does not return unless user is authorized */

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
	$setup_tpl = CreateObject('setup.Template',$tpl_root);
	$setup_tpl->set_file(array(
		'ldap'   => 'ldap.tpl',
		'T_head' => 'head.tpl',
		'T_footer' => 'footer.tpl',
		'T_alert_msg' => 'msg_alert_msg.tpl'
	));

	$GLOBALS['phpgw_setup']->db->query("SELECT config_name,config_value FROM phpgw_config WHERE config_name LIKE 'ldap%' OR config_name='account_repository'",__LINE__,__FILE__);
	while ($GLOBALS['phpgw_setup']->db->next_record())
	{
		$config[$GLOBALS['phpgw_setup']->db->f('config_name')] = $GLOBALS['phpgw_setup']->db->f('config_value');
	}
	$phpgw_info['server']['ldap_host']          = $config['ldap_host'];
	$phpgw_info['server']['ldap_context']       = $config['ldap_context'];
	$phpgw_info['server']['ldap_group_context'] = $config['ldap_group_context'];
	$phpgw_info['server']['ldap_root_dn']       = $config['ldap_root_dn'];
	$phpgw_info['server']['ldap_root_pw']       = $config['ldap_root_pw'];
	$phpgw_info['server']['account_repository'] = $config['account_repository'];

	$phpgw->accounts     = CreateObject('phpgwapi.accounts');
	$acct                = $phpgw->accounts;

	/* connect to ldap server */
	if (! $ldap = $common->ldapConnect())
	{
		$noldapconnection = True;
	}

	if ($noldapconnection)
	{
		Header('Location: config.php?error=badldapconnection');
		exit;
	}

	$sr = ldap_search($ldap,$config['ldap_context'],'(|(uid=*))',array('cn','givenname','uid','uidnumber'));
	$info = ldap_get_entries($ldap, $sr);
	$tmp = '';

	for ($i=0; $i<$info['count']; $i++)
	{
		if (! $phpgw_info['server']['global_denied_users'][$info[$i]['uid'][0]])
		{
			$account_info[$info[$i]['uidnumber'][0]] = $info[$i];
		}
	}

	if ($phpgw_info['server']['ldap_group_context'])
	{
		$srg = ldap_search($ldap,$config['ldap_group_context'],'(|(cn=*))',array('gidnumber','cn','memberuid'));
		$info = ldap_get_entries($ldap, $srg);
		$tmp = '';

		for ($i=0; $i<$info['count']; $i++)
		{
			if (! $phpgw_info['server']['global_denied_groups'][$info[$i]['cn'][0]] &&
				! $account_info[$i][$info[$i]['cn'][0]])
			{
				$group_info[$info[$i]['gidnumber'][0]] = $info[$i];
			}
		}
	}
	else
	{
		$group_info = array();
	}

	$GLOBALS['phpgw_setup']->db->query("SELECT app_name FROM phpgw_applications WHERE app_enabled!='0' AND app_enabled!='3' ORDER BY app_title",__LINE__,__FILE__);
	while ($GLOBALS['phpgw_setup']->db->next_record())
	{
		$apps[$GLOBALS['phpgw_setup']->db->f('app_name')] = lang($GLOBALS['phpgw_setup']->db->f('app_name'));
	}

	if ($cancel)
	{
		Header("Location: ldap.php");
		exit;
	}

	$GLOBALS['phpgw_setup']->html->show_header('LDAP Modify','','config',$ConfigDomain);

	if ($submit)
	{
		$acl = CreateObject('phpgwapi.acl');
		$acl->db = $GLOBALS['phpgw_setup']->db;
		if ($ldapgroups)
		{
			$groups = CreateObject('phpgwapi.accounts');
			$groups->db = $GLOBALS['phpgw_setup']->db;
			while (list($key,$groupid) = each($ldapgroups))
			{
				$id_exist = 0;
				$entry = array();
				$thisacctid    = $group_info[$groupid]['gidnumber'][0];
				$thisacctlid   = $group_info[$groupid]['cn'][0];
				/* echo "Updating GROUPID : ".$thisacctlid."<br>\n"; */
				$thisfirstname = $group_info[$groupid]['cn'][0];
				$thismembers   = $group_info[$groupid]['memberuid'];
				$thisdn        = $group_info[$groupid]['dn'];

				/* Do some checks before we try to import the data. */
				if (!empty($thisacctid) && !empty($thisacctlid))
				{
					$groups->account_id = intval($thisacctid);

					$sr = ldap_search($ldap,$config['ldap_group_context'],'cn='.$thisacctlid);
					$entry = ldap_get_entries($ldap, $sr);

					reset($entry[0]['objectclass']);
					$addclass = True;
					while(list($key,$value) = each($entry[0]['objectclass']))
					{
						if(strtolower($value) == 'phpgwaccount')
						{
							$addclass = False;
						}
					}
					if($addclass)
					{
						reset($entry[0]['objectclass']);
						$replace['objectclass'] = $entry[0]['objectclass'];
						$replace['objectclass'][]       = 'phpgwAccount';
						ldap_mod_replace($ldap,$thisdn,$replace);
						unset($replace);
						unset($addclass);
					}
					unset($add);
					if(!@isset($entry[0]['phpgwaccountstatus']))
					{
						$add['phpgwaccountstatus'][]	= 'A';
					}
					if(!@isset($entry[0]['phpgwaccounttype']))
					{
						$add['phpgwaccounttype'][]	= 'g';
					}
					if(!@isset($entry[0]['phpgwaccountexpires']))
					{
						$add['phpgwaccountexpires'][]	= -1;
					}
					if(@isset($add))
					{
						ldap_mod_add($ldap,$thisdn,$add);
					}

					/* Now make the members a member of this group in phpgw. */
					while (list($key,$members) = each($thismembers))
					{
						if ($key == 'count')
						{
							continue;
						}
						/* echo '<br>members: ' . $members; */
						$tmpid = 0;
						@reset($account_info);
						while(list($x,$y) = each($account_info))
						{
							/* echo '<br>checking: '.$y['account_lid']; */
							if ($members == $y['account_lid'])
							{
								$tmpid = $y['account_id'];
							}
						}
						// Insert acls for this group based on memberuid field.
						// Since the group has app rights, we don't need to give users
						//  these rights.  Instead, we maintain group membership here.
						if($tmpid)
						{
							$acl->account_id = intval($tmpid);
							$acl->read_repository();

							$acl->delete('phpgw_group',$thisacctid,1);
							$acl->add('phpgw_group',$thisacctid,1);

							// Now add the acl to let them change their password
							$acl->delete('preferences','changepassword',1);
							$acl->add('preferences','changepassword',1);

							$acl->save_repository();
						}
					}
					/* Now give this group some rights */
					$phpgw_info['user']['account_id'] = $thisacctid;
					$acl->account_id = intval($thisacctid);
					$acl->read_repository();
					@reset($s_apps);
					while (list($key,$app) = @each($s_apps))
					{
						$acl->delete($app,'run',1);
						$acl->add($app,'run',1);
					}
					$acl->save_repository();
					$defaultgroupid = $thisacctid;
				}
			}
		}

		if($users)
		{
			$accounts = CreateObject('phpgwapi.accounts');
			$accounts->db = $GLOBALS['phpgw_setup']->db;
			while (list($key,$id) = each($users))
			{
				$id_exist = 0;
				$thisacctid  = $account_info[$id]['uidnumber'][0];
				$thisacctlid = $account_info[$id]['uid'][0];
				/* echo "Updating USERID : ".$thisacctlid."<br>\n"; */
				$thisdn      = $account_info[$id]['dn'];

				/* Do some checks before we try to import the data. */
				if (!empty($thisacctid) && !empty($thisacctlid))
				{
					$accounts->account_id = intval($thisacctid);
					$sr = ldap_search($ldap,$config['ldap_context'],'uid='.$thisacctlid);
					$entry = ldap_get_entries($ldap, $sr);
					reset($entry[0]['objectclass']);
					$addclass = True;
					while(list($key,$value) = each($entry[0]['objectclass']))
					{
						if(strtolower($value) == 'phpgwaccount')
						{
							$addclass = False;
						}
					}
					if($addclass)
					{
						reset($entry[0]['objectclass']);
						$replace['objectclass'] = $entry[0]['objectclass'];
						$replace['objectclass'][]       = 'phpgwAccount';
						ldap_mod_replace($ldap,$thisdn,$replace);
						unset($replace);
						unset($addclass);
					}
					unset($add);
					if(!@isset($entry[0]['phpgwaccountstatus']))
					{
						$add['phpgwaccountstatus'][]	= 'A';
					}
					if(!@isset($entry[0]['phpgwaccounttype']))
					{
						$add['phpgwaccounttype'][]	= 'u';
					}
					if(!@isset($entry[0]['phpgwaccountexpires']))
					{
						$add['phpgwaccountexpires'][]	= -1;
					}
					if(@isset($add))
					{
						ldap_mod_add($ldap,$thisdn,$add);
					}

					/*
					Insert default acls for this user.
					Since the group has app rights, we don't need to give users
					these rights.
					*/
					$acl->account_id = intval($thisacctid);
					$acl->read_repository();

					/*
					However, if no groups were imported, we do need to give each user
					apps access
					*/
					if(!$ldapgroups)
					{
						@reset($s_apps);
						while (list($key,$app) = @each($s_apps))
						{
							$acl->delete($app,'run',1);
							$acl->add($app,'run',1);
						}
					}
					// Now add the acl to let them change their password
					$acl->delete('preferences','changepassword',1);
					$acl->add('preferences','changepassword',1);

					/*
					Only give them admin if we asked for them to have it.
					This is typically an exception to apps for run rights
					as a group member.
					*/
					for ($a=0;$a<count($admins);$a++)
					{
						if ($admins[$a] == $thisacctid)
						{
							$acl->delete('admin','run',1);
							$acl->add('admin','run',1);
						}
					}
					/* Save these new acls. */
					$acl->save_repository();
				}
			}
		}
		$setup_complete = True;
	}

	if ($error)
	{
		/* echo '<br><center><b>Error:</b> '.$error.'</center>'; */
		$GLOBALS['phpgw_setup']->html->show_alert_msg('Error',$error);
	}

	if ($setup_complete)
	{
		echo '<br><center>'.lang('Modifications have been completed!').' '.lang('Click <a href="index.php">here</a> to return to setup.').'<br><center>';
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

	while (list($key,$account) = @each($account_info))
	{
		$user_list .= '<option value="' . $account['uidnumber'][0] . '">' . $account['cn'][0] . '(' . $account['uid'][0] . ')</option>';
	}

	@reset($account_info);
	while (list($key,$account) = @each($account_info))
	{
		$admin_list .= '<option value="' . $account['uidnumber'][0] . '">' . $account['cn'][0] . '(' . $account['uid'][0] . ')</option>';
	}

	while (list($key,$group) = @each($group_info))
	{
		$group_list .= '<option value="' . $group['gidnumber'][0] . '">' . $group['cn'][0]  . '</option>';
	}

	while(list($appname,$apptitle) = each($apps))
	{
		if($appname == 'admin' ||
			$appname == 'skel' ||
			$appname == 'backup' ||
			$appname == 'netsaint' ||
			$appname == 'developer_tools' ||
			$appname == 'phpsysinfo' ||
			$appname == 'eldaptir' ||
			$appname == 'qmailldap')
		{
			$app_list .= '<option value="' . $appname . '">' . $apptitle . '</option>';
		}
		else
		{
			$app_list .= '<option value="' . $appname . '" selected>' . $apptitle . '</option>';
		}
	}

	$setup_tpl->set_var('action_url','ldapmodify.php');
	$setup_tpl->set_var('users',$user_list);
	$setup_tpl->set_var('admins',$admin_list);
	$setup_tpl->set_var('ldapgroups',$group_list);
	$setup_tpl->set_var('s_apps',$app_list);

	$setup_tpl->set_var('ldap_import',lang('LDAP Modify'));
	$setup_tpl->set_var('description',lang("This section will help you setup your LDAP accounts for use with phpGroupWare").'.');
	$setup_tpl->set_var('select_users',lang('Select which user(s) will be modified'));
	$setup_tpl->set_var('select_admins',lang('Select which user(s) will also have admin privileges'));
	$setup_tpl->set_var('select_groups',lang('Select which group(s) will be modified (group membership will be maintained)'));
	$setup_tpl->set_var('select_apps',lang('Select the default applications to which your users will have access').'.');
	$setup_tpl->set_var('form_submit',lang('Modify'));
	$setup_tpl->set_var('cancel',lang('Cancel'));

	$setup_tpl->pfp('out','header');
	$setup_tpl->pfp('out','user_list');
	$setup_tpl->pfp('out','admin_list');
	$setup_tpl->pfp('out','group_list');
	$setup_tpl->pfp('out','app_list');
	$setup_tpl->pfp('out','submit');
	$setup_tpl->pfp('out','footer');

	$GLOBALS['phpgw_setup']->html->show_footer();
?>
