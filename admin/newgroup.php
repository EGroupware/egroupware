<?php
  /**************************************************************************\
  * phpGroupWare - administration                                            *
  * http://www.phpgroupware.org                                              *
  * --------------------------------------------                             *
  *  This program is free software; you can redistribute it and/or modify it *
  *  under the terms of the GNU General Public License as published by the   *
  *  Free Software Foundation; either version 2 of the License, or (at your  *
  *  option) any later version.                                              *
  \**************************************************************************/

  /* $Id$ */

	$phpgw_info = array();
	if ($submit)
	{
		$phpgw_info['flags'] = array('noheader' => True, 'nonavbar' => True);
	}

	$phpgw_info['flags']['currentapp'] = 'admin';
	include('../header.inc.php');

	function is_odd($n)
	{
		$ln = substr($n,-1);
		if ($ln == 1 || $ln == 3 || $ln == 5 || $ln == 7 || $ln == 9)
		{
			return True;
		}
		else
		{
			return False;
		}
	}

	if ($submit)
	{
		if (!$n_group)
		{
			$error = '<br>' . lang('You must enter a group name.');
		}
		else
		{
			if ($phpgw->accounts->exists($n_group))
			{
				$error = '<br>' . lang('Sorry, that group name has already been taken.');
			}
		}

		if ($account_expires_month || $account_expires_day || $account_expires_year)
		{
			if (! checkdate($account_expires_month,$account_expires_day,$account_expires_year))
			{
				$error[] = lang('You have entered an invalid expiration date');
			}
			else
			{
				$account_expires = mktime(2,0,0,$account_expires_month,$account_expires_day,$account_expires_year);
			}
		}
		else
		{
			$account_expires = -1;
		}


		if (!$error)
		{
			$phpgw->db->lock(array(
				'phpgw_accounts',
				'phpgw_nextid',
				'phpgw_preferences',
				'phpgw_sessions',
				'phpgw_acl',
				'phpgw_applications'
			));

			$group = CreateObject('phpgwapi.accounts',$group_id);
			$account_info = array(
				'account_type'      => 'g',
				'account_lid'       => $n_group,
				'account_passwd'    => '',
				'account_firstname' => $n_group,
				'account_lastname'  => 'Group',
				'account_status'    => 'A',
				'account_expires'   => $account_expires
			);
			$group->create($account_info);
			$group_id = $phpgw->accounts->name2id($n_group);

			$apps = CreateObject('phpgwapi.applications',intval($group_id));
			$apps->update_data(Array());
			@reset($n_group_permissions);

			if (count($n_group_permissions))
			{
				while($app = each($n_group_permissions))
				{
					if ($app[1])
					{
						$apps->add($app[0]);
						$new_apps[] = $app[0];
					}
				}
				$apps->save_repository();
			}

			$acl = CreateObject('phpgwapi.acl',$group_id);
			$acl->read_repository();
			for ($i=0; $i<count($n_users);$i++)
			{
				$acl->add_repository('phpgw_group',$group_id,$n_users[$i],1);

				// If the user is logged in, it will force a refresh of the session_info
				#     $phpgw->db->query("update phpgw_sessions set session_info='' "
				#          ."where session_lid='" . $phpgw->accounts->id2name(intval($n_users[$i])) . "@" . $phpgw_info["user"]["domain"] . "'",__LINE__,__FILE__);

				$pref = CreateObject('phpgwapi.preferences',intval($n_users[$i]));
				$t = $pref->read_repository();

				$docommit = False;
				for ($j=0;$j<count($new_apps);$j++)
				{
					if($new_apps[$j]=="admin")
					{
						$check = "common";
					}
					else
					{
						$check = $new_apps[$j];
					}
					if (!$t["$check"])
					{
						$phpgw->common->hook_single("add_def_pref", $new_apps[$j]);
						$docommit = True;
					}
				}
				if ($docommit)
				{
					$pref->save_repository();
				}
			}

			$basedir = $phpgw_info["server"]["files_dir"] . SEP . "groups" . SEP;
			$cd = 31;
			umask(000);
			if (! @mkdir ($basedir . $n_group, 0707))
			{
				$cd = 37;
			}

			$phpgw->db->unlock();

			Header("Location: " . $phpgw->link("/admin/groups.php","cd=$cd"));
			$phpgw->common->phpgw_exit();
		}
	}

	$p = CreateObject('phpgwapi.Template',PHPGW_APP_TPL);
	$p->set_file(array("form" => "groups_form.tpl"));

	if ($error)
	{
		$phpgw->common->phpgw_header();
		echo parse_navbar();
		$p->set_var("error","<p><center>$error</center>");
	}
	else
	{
		$p->set_var("error","");
	}

	$p->set_var("form_action",$phpgw->link("/admin/newgroup.php"));
	$p->set_var("hidden_vars","");
	$p->set_var("lang_group_name",lang("New group name"));
	$p->set_var("group_name_value","");

	$accounts = CreateObject('phpgwapi.accounts',$group_id);
	$account_list = $accounts->get_list('accounts');
	$account_num = count($account_list);

	if ($account_num < 5)
	{
		$p->set_var('select_size',$account_num);
	}
	else
	{
		$p->set_var('select_size','5');
	}

	$p->set_var("lang_include_user",lang("Select users for inclusion"));

	for ($i=0; $i<count($n_users); $i++) {
		$selected_users[$n_users[$i]] = " selected";
	}

	while (list($key,$entry) = each($account_list))
	{
		$user_list .= '<option value="' . $entry['account_id'] . '"'
			. $selected_users[intval($entry['account_id'])] . '>'
			. $phpgw->common->display_fullname(
				$entry['account_lid'],
				$entry['account_firstname'],
				$entry['account_lastname'])
			. '</option>'."\n";
	}

	$p->set_var("user_list",$user_list);
	$p->set_var("lang_permissions",lang("Permissions this group has"));

	$i = 0;

	$phpgw->applications->read_installed_apps();

	$sorted_apps = $phpgw_info["apps"];
	@asort($sorted_apps);
	@reset($sorted_apps);
	while ($permission = each($sorted_apps))
	{
		if ($permission[1]["enabled"])
		{
			$perm_display[$i][0] = $permission[0];
			$perm_display[$i][1] = $permission[1]["title"];
			$i++;
		}
	}

	$perm_html = "";
	for ($i=0;$i<200;) {     // The $i<200 is only used for a brake
		if (! $perm_display[$i][1]) break;
		$perm_html .= '<tr bgcolor="'.$phpgw_info["theme"]["row_on"].'"><td>' . lang($perm_display[$i][1]) . '</td>'
			. '<td><input type="checkbox" name="n_group_permissions['
			. $perm_display[$i][0] . ']" value="True"';
		if ($n_group_permissions[$perm_display[$i][0]])
		{
			$perm_html .= " checked";
		}
		$perm_html .= "></td>";
		$i++;

		if ($i == count($perm_display) && is_odd(count($perm_display)))
		{
			$perm_html .= '<td colspan="2">&nbsp;</td></tr>';
		}

		if (! $perm_display[$i][1]) break;
			$perm_html .= '<td>' . lang($perm_display[$i][1]) . '</td>'
				. '<td><input type="checkbox" name="n_group_permissions['
				. $perm_display[$i][0] . ']" value="True"';
		if ($n_group_permissions[$perm_display[$i][0]])
		{
			$perm_html .= " checked";
		}
		$perm_html .= "></td></tr>\n";
		$i++;
	}

	$p->set_var("permissions_list",$perm_html);	
	$p->set_var("lang_submit_button",lang("Create Group"));
	$p->pparse("out","form");

	$phpgw->common->phpgw_footer();
?>
