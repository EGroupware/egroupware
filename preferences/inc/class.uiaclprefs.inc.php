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
			$GLOBALS['phpgw']->nextmatchs = CreateObject('phpgwapi.nextmatchs');
			
			if (!is_object($GLOBALS['phpgw']->html))
			{
				$GLOBALS['phpgw']->html = CreateObject('phpgwapi.html');
			}
		}

		function index()
		{
			$acl_app	= get_var('acl_app',array('POST','GET'));
			$start		= get_var('start',array('POST','GET'));
			$query		= get_var('query',array('POST','GET'));
			$owner		= get_var('owner',array('POST','GET'));

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
				&& !isset($GLOBALS['phpgw_info']['user']['apps']['admin']))
			{
				echo '<center><b>' . lang('Access not permitted') . '</b></center>';
				$GLOBALS['phpgw']->common->phpgw_footer();
			}

			if((!isset($owner) || empty($owner)) || !$GLOBALS['phpgw_info']['user']['apps']['admin'])
			{
				$owner = $GLOBALS['phpgw_info']['user']['account_id'];
			}
			$owner_name		= $GLOBALS['phpgw']->accounts->id2name($owner);		// get owner name for title
			if($no_privat_grants	= $GLOBALS['phpgw']->accounts->get_type($owner) == 'g')
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

				for($i=0;$i<count($to_remove);$i++)
				{
					$this->acl->delete($acl_app,$to_remove[$i]);
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
						list($user_id,$rights) = split('_',$rowinfo);
						$totalacl[$user_id] += $rights;
					}
					foreach($totalacl as $user_id => $rights)
					{
						if($no_privat_grants)
						{
							/* Don't allow group-grants or admin to grant private */
							$rights &= ~ PHPGW_ACL_PRIVATE;
						}
						$this->acl->add($GLOBALS['phpgw_info']['flags']['currentapp'],$user_id,$rights);
					}
					$this->acl->save_repository();
				}
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
			
			$header_type = '';
			$processed = Array();
			foreach($accounts as $uid => $data)
			{
				if ($uid == $owner) continue;	// no need to grant to self

				if ($data['account_type'] != $header_type)
				{
					$this->template->set_var('string',$data['account_type'] == 'g' ? lang('Groups') : lang('Users'));
					$this->template->parse('row','row_colspan',True);
					$header_type = $data['account_type'];
				}
				$tr_color = $GLOBALS['phpgw']->nextmatchs->alternate_row_color($tr_color);

				if ($data['account_type'] == 'g')
				{
					$this->display_row($tr_color,'g_',$data['account_id'],$data['account_lid'],$no_privat_grants);
				}
				else
				{
					$this->display_row($tr_color,'u_',$data['account_id'],$GLOBALS['phpgw']->common->display_fullname($data['account_lid'],$data['account_firstname'],$data['account_lastname']),$no_privat_grants);
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

		function display_row($bg_color,$label,$id,$name,$no_privat_grants)
		{
			$this->template->set_var('row_color',$bg_color);
			$this->template->set_var('user',$name);
			$rights = $this->acl->get_rights($id,$GLOBALS['phpgw_info']['flags']['currentapp']);

			$grantors = $this->acl->get_ids_for_location($id,$rights,$GLOBALS['phpgw_info']['flags']['currentapp']);
			$is_group_set = False;
			if (is_array($grantors))
			{
				foreach($grantors as $grantor)
				{
					if($GLOBALS['phpgw']->accounts->get_type($grantor) == 'g')
					{
						$is_group_set = True;
					}
				}
			}

			$this->check_acl($label,$id,'read',$rights,PHPGW_ACL_READ,$is_group_set && $rights & PHPGW_ACL_READ);
			$this->check_acl($label,$id,'add',$rights,PHPGW_ACL_ADD,$is_group_set && $rights & PHPGW_ACL_ADD);
			$this->check_acl($label,$id,'edit',$rights,PHPGW_ACL_EDIT,$is_group_set && $rights & PHPGW_ACL_EDIT);
			$this->check_acl($label,$id,'delete',$rights,PHPGW_ACL_DELETE,$is_group_set && $rights & PHPGW_ACL_DELETE);
			$this->check_acl($label,$id,'private',$rights,PHPGW_ACL_PRIVATE,$no_privat_grants);

			$this->check_acl($label,$id,'custom_1',$rights,PHPGW_ACL_CUSTOM_1,$is_group_set && $rights & PHPGW_ACL_CUSTOM_1);
			$this->check_acl($label,$id,'custom_2',$rights,PHPGW_ACL_CUSTOM_2,$is_group_set && $rights & PHPGW_ACL_CUSTOM_2);
			$this->check_acl($label,$id,'custom_3',$rights,PHPGW_ACL_CUSTOM_3,$is_group_set && $rights & PHPGW_ACL_CUSTOM_3);
			$this->template->parse('row','acl_row',True);
		}
	}
?>
