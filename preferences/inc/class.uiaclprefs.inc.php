<?php
	/**************************************************************************\
	* eGroupWare - Preferences                                                 *
	* http://www.egroupware.org                                                *
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
			$GLOBALS['egw']->nextmatchs = CreateObject('phpgwapi.nextmatchs');

			if (!is_object($GLOBALS['egw']->html))
			{
				$GLOBALS['egw']->html = CreateObject('phpgwapi.html');
			}
		}

		function index()
		{
			$acl_app	= get_var('acl_app',array('POST','GET'));
			$start		= get_var('start',array('POST','GET'),0);
			$query		= get_var('query',array('POST','GET'));
			$owner		= get_var('owner',array('POST','GET'),$GLOBALS['egw_info']['user']['account_id']);

			if (!$acl_app)
			{
				$acl_app            = 'preferences';
				$acl_app_not_passed = True;
			}
			else
			{
				$GLOBALS['egw']->translation->add_app($acl_app);
			}

			$GLOBALS['egw_info']['flags']['currentapp'] = $acl_app;

			if ($acl_app_not_passed)
			{
				if(is_object($GLOBALS['egw']->log))
				{
					$GLOBALS['egw']->log->message(array(
						'text' => 'F-BadmenuactionVariable, failed to pass acl_app.',
						'line' => __LINE__,
						'file' => __FILE__
					));
					$GLOBALS['egw']->log->commit();
				}
			}

			if (($GLOBALS['egw_info']['server']['deny_user_grants_access'] || $owner != $GLOBALS['egw_info']['user']['account_id'])
				&& !isset($GLOBALS['egw_info']['user']['apps']['admin']) || $acl_app_not_passed)
			{
				$GLOBALS['egw']->common->phpgw_header();
				echo parse_navbar();
				echo '<center><b>' . lang('Access not permitted') . '</b></center>';
				$GLOBALS['egw']->common->phpgw_footer();
				return;
			}

			$owner_name = $GLOBALS['egw']->accounts->id2name($owner);		// get owner name for title
			if($no_privat_grants = $GLOBALS['egw']->accounts->get_type($owner) == 'g')
			{
				$owner_name = lang('Group').' ('.$owner_name.')';
			}
			else	// admin setting acl-rights is handled as group-rights => no private grants !!
			{
				$no_privat_grants = $owner != $GLOBALS['egw_info']['user']['account_id'];
			}
			$this->acl = CreateObject('phpgwapi.acl',(int)$owner);
			$this->acl->read_repository();

			if ($_POST['submit'])
			{
				$processed = $_POST['processed'];
				$to_remove = unserialize(urldecode($processed));
				foreach($to_remove as $uid)
				{
					//echo "deleting acl-records for $uid=".$GLOBALS['egw']->accounts->id2name($uid)." and $acl_app<br>\n";
					$this->acl->delete($acl_app,$uid);
				}

				/* Group records */
				$totalacl = array();
				$group_variable = $_POST['g_'.$GLOBALS['egw_info']['flags']['currentapp']];

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
							$rights &= ~EGW_ACL_PRIVATE;
						}
						//echo "adding acl-rights $rights for $group_id=".$GLOBALS['egw']->accounts->id2name($group_id)." and $acl_app<br>\n";
						$this->acl->add($GLOBALS['egw_info']['flags']['currentapp'],$group_id,$rights);
					}
				}

				/* User records */
				$totalacl = array();
				$user_variable = $_POST['u_'.$GLOBALS['egw_info']['flags']['currentapp']];

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
							$rights &= ~ EGW_ACL_PRIVATE;
						}
						//echo "adding acl-rights $rights for $user_id=".$GLOBALS['egw']->accounts->id2name($user_id)." and $acl_app<br>\n";
						$this->acl->add($GLOBALS['egw_info']['flags']['currentapp'],$user_id,$rights);
					}
				}
				$this->acl->save_repository();
			}
			$GLOBALS['egw_info']['flags']['app_header'] = lang('%1 - Preferences',$GLOBALS['egw_info']['apps'][$acl_app]['title']).' - '.lang('acl').': '.$owner_name;
			$GLOBALS['egw']->common->phpgw_header();
			echo parse_navbar();

			$this->template = CreateObject('phpgwapi.Template',$GLOBALS['egw']->common->get_tpl_dir($acl_app));
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
				'action_url'  => $GLOBALS['egw']->link('/index.php','menuaction=preferences.uiaclprefs.index&acl_app=' . $acl_app),
				'bg_color'    => $GLOBALS['egw_info']['theme']['th_bg'],
				'submit_lang' => lang('Save'),
				'common_hidden_vars_form' => $GLOBALS['egw']->html->input_hidden($common_hidden_vars)
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

			$accounts = $GLOBALS['egw']->accounts->search(array(
				'type'	=> 'both',
				'start'	=> $start,
				'query'	=> $query,
				'order' => 'account_type,account_lid',
				'sort'	=> 'ASC',
			));
			$totalentries = $GLOBALS['egw']->accounts->total;

			$memberships = (array) $GLOBALS['egw']->acl->get_location_list_for_id('phpgw_group', 1, $owner);

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
				$tr_class = $GLOBALS['egw']->nextmatchs->alternate_row_color($tr_color,true);

				if ($data['account_type'] == 'g')
				{
					$this->display_row($tr_class,'g_',$data['account_id'],$data['account_lid'],$no_privat_grants,$memberships);
				}
				else
				{
					$this->display_row($tr_class,'u_',$data['account_id'],$GLOBALS['egw']->common->display_fullname($data['account_lid'],$data['account_firstname'],$data['account_lastname']),$no_privat_grants,$memberships);
				}
				$processed[] = $uid;
			}

			$extra_parms = array(
				'menuaction'	=> 'preferences.uiaclprefs.index',
				'acl_app'		=> $acl_app,
				'owner'			=> $owner,
			);

			$var = Array(
				'nml'          => $GLOBALS['egw']->nextmatchs->left('/index.php',$start,$totalentries,$extra_parms),
				'nmr'          => $GLOBALS['egw']->nextmatchs->right('/index.php',$start,$totalentries,$extra_parms),
				'search_value' => isset($query) && $query ? $GLOBALS['egw']->html->htmlspecialchars($query) : '',
				'search'       => lang('search'),
				'processed'    => urlencode(serialize($processed))
			);

			$this->template->set_var($var);

			$this->template->pfp('out','preferences');
		}

		function check_acl($label,$id,$acl,$rights,$right,$disabled=False)
		{
			$this->template->set_var($acl,$label.$GLOBALS['egw_info']['flags']['currentapp'].'['.$id.'_'.$right.']');
			$rights_set = ($rights & $right) ? ' checked="1"' : '';
			if ($disabled)
			{
				// This is so you can't select it in the GUI
				$rights_set .= ' disabled="1"';
			}
			$this->template->set_var($acl.'_selected',$rights_set);
		}

		function display_row($tr_class,$label,$id,$name,$no_privat_grants,$memberships)
		{
			$this->template->set_var('row_class',$tr_class);
			$this->template->set_var('row_color',$GLOBALS['egw_info']['theme'][$tr_class]);
			$this->template->set_var('user',$name);
			$rights = $this->acl->get_rights($id,$GLOBALS['egw_info']['flags']['currentapp']);
			$is_group = $GLOBALS['egw']->accounts->get_type($id) == 'g';

			foreach(array(
				EGW_ACL_READ		=> 'read',
				EGW_ACL_ADD		=> 'add',
				EGW_ACL_EDIT		=> 'edit',
				EGW_ACL_DELETE	=> 'delete',
				EGW_ACL_PRIVATE	=> 'private',
				EGW_ACL_CUSTOM_1	=> 'custom_1',
				EGW_ACL_CUSTOM_2	=> 'custom_2',
				EGW_ACL_CUSTOM_3	=> 'custom_3',
			) as $right => $name)
			{
				$is_group_set = False;
				if ($is_group)
				{
					$grantors = $this->acl->get_ids_for_location($id,$right,$GLOBALS['egw_info']['flags']['currentapp']);
					if (is_array($grantors))
					{
						foreach($grantors as $grantor)
						{
							//echo $GLOBALS['egw']->accounts->id2name($id)."=$id: $name-grant from ".$GLOBALS['egw']->accounts->id2name($grantor)."=$grantor<br>\n";
							// check if the grant comes from a group, the owner is a member off, in that case he is NOT allowed to remove it
							if(in_array($grantor,$memberships))
							{
								//echo "==> member of ==> set by group<br>";
								$is_group_set = True;
							}
						}
					}
				}
				$this->check_acl($label,$id,$name,$rights,$right,$is_group_set || $no_privat_grants && $right == EGW_ACL_PRIVATE);
			}
			$this->template->parse('row','acl_row',True);
		}
	}
?>
