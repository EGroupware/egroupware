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

	class uiaclprefs
	{
		var $acl;
		var $template;

		var $public_functions = array('index' => True);

		function uiaclprefs()
		{
			$GLOBALS['phpgw']->nextmatchs = CreateObject('phpgwapi.nextmatchs');
			
			if (!is_object($GLOBALS['phpgw']->html))
			{
				$GLOBALS['phpgw']->html = CreateObject('phpgwapi.html');
			}
		}

		function index()
		{
			$acl_app	= get_var('acl_app',array('POST','GET'));
			$start		= get_var('start',array('POST','GET'),0);
			$query		= get_var('query',array('POST','GET'));
			$owner		= get_var('owner',array('POST','GET'),$GLOBALS['phpgw_info']['user']['account_id']);

			if (!$acl_app)
			{
				$acl_app            = 'preferences';
				$acl_app_not_passed = True;
			}
			else
			{
				$GLOBALS['phpgw']->translation->add_app($acl_app);
			}

			$GLOBALS['phpgw_info']['flags']['currentapp'] = $acl_app;

			if ($acl_app_not_passed)
			{
				if(is_object($GLOBALS['phpgw']->log))
				{
					$GLOBALS['phpgw']->log->message(array(
						'text' => 'F-BadmenuactionVariable, failed to pass acl_app.',
						'line' => __LINE__,
						'file' => __FILE__
					));
					$GLOBALS['phpgw']->log->commit();
				}
			}

			if (($GLOBALS['phpgw_info']['server']['deny_user_grants_access'] || $owner != $GLOBALS['phpgw_info']['user']['account_id'])
				&& !isset($GLOBALS['phpgw_info']['user']['apps']['admin']) || $acl_app_not_passed)
			{
				$GLOBALS['phpgw']->common->phpgw_header();
				echo parse_navbar();
				echo '<center><b>' . lang('Access not permitted') . '</b></center>';
				$GLOBALS['phpgw']->common->phpgw_footer();
				return;
			}

			$owner_name = $GLOBALS['phpgw']->accounts->id2name($owner);		// get owner name for title
			if($no_privat_grant = $GLOBALS['phpgw']->accounts->get_type($owner) == 'g')
			{
				$owner_name = lang('Group').' ('.$owner_name.')';
			}
			else	// admin setting acl-rights is handled as group-rights => no private grants !!
			{
				$no_privat_grants = $owner != $GLOBALS['phpgw_info']['user']['account_id'];
			}
			$this->acl = CreateObject('phpgwapi.acl',(int)$owner);
			$this->acl->read_repository();

			if ($_POST['submit'])
			{
				$processed = $_POST['processed'];
				$to_remove = unserialize(urldecode($processed));
				foreach($to_remove as $uid)
				{
					//echo "deleting acl-records for $uid=".$GLOBALS['phpgw']->accounts->id2name($uid)." and $acl_app<br>\n";
					$this->acl->delete($acl_app,$uid);
				}

				/* Group records */
				$totalacl = array();
				$group_variable = $_POST['g_'.$GLOBALS['phpgw_info']['flags']['currentapp']];

				if (is_array($group_variable))
				{
					foreach($group_variable as $rowinfo => $perm)
					{
						list($group_id,$rights) = explode('_',$rowinfo);
						$totalacl[$group_id] += $rights;
					}
					foreach($totalacl as $group_id => $rights)
					{
						if($no_privat_grants)
						{
							/* Don't allow group-grants or admin to grant private */
							$rights &= ~PHPGW_ACL_PRIVATE;
						}
						//echo "adding acl-rights $rights for $group_id=".$GLOBALS['phpgw']->accounts->id2name($group_id)." and $acl_app<br>\n";
						$this->acl->add($GLOBALS['phpgw_info']['flags']['currentapp'],$group_id,$rights);
					}
				}

				/* User records */
				$totalacl = array();
				$user_variable = $_POST['u_'.$GLOBALS['phpgw_info']['flags']['currentapp']];

				if (is_array($user_variable))
				{
					foreach($user_variable as $rowinfo => $perm)
					{
						list($user_id,$rights) = explode('_',$rowinfo);
						$totalacl[$user_id] += $rights;
					}
					foreach($totalacl as $user_id => $rights)
					{
						if($no_privat_grants)
						{
							/* Don't allow group-grants or admin to grant private */
							$rights &= ~ PHPGW_ACL_PRIVATE;
						}
						//echo "adding acl-rights $rights for $user_id=".$GLOBALS['phpgw']->accounts->id2name($user_id)." and $acl_app<br>\n";
						$this->acl->add($GLOBALS['phpgw_info']['flags']['currentapp'],$user_id,$rights);
					}
				}
				$this->acl->save_repository();
			}
			$GLOBALS['phpgw_info']['flags']['app_header'] = lang('%1 - Preferences',$GLOBALS['phpgw_info']['apps'][$acl_app]['title']).' - '.lang('acl').': '.$owner_name;
			$GLOBALS['phpgw']->common->phpgw_header();
			echo parse_navbar();

			$this->template = CreateObject('phpgwapi.Template',$GLOBALS['phpgw']->common->get_tpl_dir($acl_app));
			$templates = Array (
				'preferences' => 'preference_acl.tpl',
				'row_colspan' => 'preference_colspan.tpl',
				'acl_row'     => 'preference_acl_row.tpl'
			);

			$this->template->set_file($templates);

			if ($submit)
			{
				$this->template->set_var('errors',lang('ACL grants have been updated'));
			}

			$common_hidden_vars = array(
				'start'		=> $start,
				'query'		=> $query,
				'owner'		=> $owner,
				'acl_app'	=> $acl_app,
			);
			$var = Array(
				'errors'      => '',
				'title'       => '<br>',
				'action_url'  => $GLOBALS['phpgw']->link('/index.php','menuaction=preferences.uiaclprefs.index&acl_app=' . $acl_app),
				'bg_color'    => $GLOBALS['phpgw_info']['theme']['th_bg'],
				'submit_lang' => lang('Save'),
				'common_hidden_vars_form' => $GLOBALS['phpgw']->html->input_hidden($common_hidden_vars)
			);
			$this->template->set_var($var);

			$vars = $this->template->get_undefined('row_colspan');
			foreach($vars as $var)
			{
				if(strstr($var,'lang_'))
				{
					$value = str_replace('lang_','',$var);
					$value = str_replace('_',' ',$value);

					$this->template->set_var($var,lang($value));
				}
			}

			$accounts = $GLOBALS['phpgw']->accounts->search(array(
				'type'	=> 'both',
				'start'	=> $start,
				'query'	=> $query,
				'order' => 'account_type,account_lid',
				'sort'	=> 'ASC',
			));
			$totalentries = $GLOBALS['phpgw']->accounts->total;
			
			$memberships = $GLOBALS['phpgw']->acl->get_location_list_for_id('phpgw_group', 1, $owner);

			$header_type = '';
			$processed = Array();
			foreach($accounts as $uid => $data)
			{
				if ($data['account_type'] == 'u' && $uid == $owner)
					continue;	/* no need to grant to self if user */

				if ($data['account_type'] != $header_type)
				{
					$this->template->set_var('string',$data['account_type'] == 'g' ? lang('Groups') : lang('Users'));
					$this->template->parse('row','row_colspan',True);
					$header_type = $data['account_type'];
				}
				$tr_color = $GLOBALS['phpgw']->nextmatchs->alternate_row_color($tr_color);

				if ($data['account_type'] == 'g')
				{
					$this->display_row($tr_color,'g_',$data['account_id'],$data['account_lid'],$no_privat_grants,$memberships);
				}
				else
				{
					$this->display_row($tr_color,'u_',$data['account_id'],$GLOBALS['phpgw']->common->display_fullname($data['account_lid'],$data['account_firstname'],$data['account_lastname']),$no_privat_grants,$memberships);
				}
				$processed[] = $uid;
			}

			$extra_parms = array(
				'menuaction'	=> 'preferences.uiaclprefs.index',
				'acl_app'		=> $acl_app,
				'owner'			=> $owner,
			);

			$var = Array(
				'nml'          => $GLOBALS['phpgw']->nextmatchs->left('/index.php',$start,$totalentries,$extra_parms),
				'nmr'          => $GLOBALS['phpgw']->nextmatchs->right('/index.php',$start,$totalentries,$extra_parms),
				'search_value' => isset($query) && $query ? $GLOBALS['phpgw']->html->htmlspecialchars($query) : '',
				'search'       => lang('search'),
				'processed'    => urlencode(serialize($processed))
			);

			$this->template->set_var($var);

			$this->template->pfp('out','preferences');
		}

		function check_acl($label,$id,$acl,$rights,$right,$disabled=False)
		{
			$this->template->set_var($acl,$label.$GLOBALS['phpgw_info']['flags']['currentapp'].'['.$id.'_'.$right.']');
			$rights_set = ($rights & $right) ? ' checked="1"' : '';
			if ($disabled)
			{
				// This is so you can't select it in the GUI
				$rights_set .= ' disabled="1"';
			}
			$this->template->set_var($acl.'_selected',$rights_set);
		}

		function display_row($bg_color,$label,$id,$name,$no_privat_grants,$memberships)
		{
			$this->template->set_var('row_color',$bg_color);
			$this->template->set_var('user',$name);
			$rights = $this->acl->get_rights($id,$GLOBALS['phpgw_info']['flags']['currentapp']);
			$is_group = $GLOBALS['phpgw']->accounts->get_type($id) == 'g';

			foreach(array(
				PHPGW_ACL_READ		=> 'read',
				PHPGW_ACL_ADD		=> 'add',
				PHPGW_ACL_EDIT		=> 'edit',
				PHPGW_ACL_DELETE	=> 'delete',
				PHPGW_ACL_PRIVATE	=> 'private',
				PHPGW_ACL_CUSTOM_1	=> 'custom_1',
				PHPGW_ACL_CUSTOM_2	=> 'custom_2',
				PHPGW_ACL_CUSTOM_3	=> 'custom_3',
			) as $right => $name)
			{
				$is_group_set = False;
				if ($is_group)
				{
					$grantors = $this->acl->get_ids_for_location($id,$right,$GLOBALS['phpgw_info']['flags']['currentapp']);
					if (is_array($grantors))
					{
						foreach($grantors as $grantor)
						{
							//echo $GLOBALS['phpgw']->accounts->id2name($id)."=$id: $name-grant from ".$GLOBALS['phpgw']->accounts->id2name($grantor)."=$grantor<br>\n";
							// check if the grant comes from a group, the owner is a member off, in that case he is NOT allowed to remove it
							if(in_array($grantor,$memberships))
							{
								//echo "==> member of ==> set by group<br>";
								$is_group_set = True;
							}
						}
					}
				}
				$this->check_acl($label,$id,$name,$rights,$right,$is_group_set || $no_privat_grants && $right == PHPGW_ACL_PRIVATE);
			}
			$this->template->parse('row','acl_row',True);
		}
	}
?>
