<?php
	/**************************************************************************\
	* eGroupWare API - Accounts manager - User Interface functions             *
	* Written or modified by RalfBecker@outdoor-training.de                    *
	* The original version of the acount-selection popup was written and       *
	* (c) 2003 by Bettina Gille [ceb@phpgroupware.org]                         *
	* -------------------------------------------------------------------------*
	* This library is part of the eGroupWare API                               *
	* http://www.egroupware.org                                                *
	* ------------------------------------------------------------------------ *
	* This library is free software; you can redistribute it and/or modify it  *
	* under the terms of the GNU Lesser General Public License as published by *
	* the Free Software Foundation; either version 2.1 of the License,         *
	* or any later version.                                                    *
	* This library is distributed in the hope that it will be useful, but      *
	* WITHOUT ANY WARRANTY; without even the implied warranty of               *
	* MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.                     *
	* See the GNU Lesser General Public License for more details.              *
	* You should have received a copy of the GNU Lesser General Public License *
	* along with this library; if not, write to the Free Software Foundation,  *
	* Inc., 59 Temple Place, Suite 330, Boston, MA  02111-1307  USA            *
	\**************************************************************************/
	/* $Id$ */

	include_once(PHPGW_API_INC . '/class.accounts.inc.php');

	/**
	 * User Interface for account and/or group selection
	 */

	class uiaccountsel extends accounts
	{
		var $public_functions = array(
			'popup' => True,
		);

		function uiaccountsel($account_id = '', $account_type='')
		{
			$this->accounts($account_id,$account_type);			// call constructor of extended class

			$this->account_selection = $GLOBALS['phpgw_info']['user']['preferences']['common']['account_selection'];
		}

		/**
		 * Create an account-selection for a certain range of users
		 *
		 * @param $name string name of the form-element
		 * @param $element_id string id of the form-element, this need to be unique for the whole window !!!
		 * @param $selected array/int user-id or array of user-id's which are already selected
		 * @param $use string/array 'accounts', 'groups', 'both' or app-name for all accounts with run-rights or an
		 *	array with id's as keys or values. If the id is in the key and the value is a string, it gets appended to the user-name
		 * @param $lines int number of lines for multiselection or 0 for a single selection
		 *	(in that case accounts should be an int or contain only 1 user-id)
		 * @param $not int/array user-id or array of user-id's not to display in selection, default False = display all
		 * @param $options	additional options (e.g. style)
		 * @param $onchange javascript to execute if the selection changes, eg. to reload the page
		 * @return the necessary html
		 */
		function selection($name,$element_id,$selected,$use='accounts',$lines=1,$not=False,$options='',$onchange='')
		{
			//echo "<p>uiaccountsel::selection('$name',".print_r($selected,True).",'$use',$lines,$not,'$options')</p>\n";
			if (!is_object($GLOBALS['phpgw']->html))
			{
				$GLOBALS['phpgw']->html = CreateObject('phpgwapi.html');
			}
			if (!is_array($selected))
			{
				$selected = $selected ? array($selected) : array();
			}
			$enumerate_groups = False;

			switch($this->account_selection)
			{
				case 'popup':
					$use = $selected;
					break;
				case 'primary_group':
					$use = count($selected) && !isset($selected[0]) ? array_keys($selected) : $selected;
					$members = $this->member($GLOBALS['phpgw']->accounts->data['account_primary_group']);
					if (is_array($members))
					{
						foreach($members as $member)
						{
							if (!in_array($member['account_id'],$use))
							{
								$use[] = $member['account_id'];
							}
						}
					}
					break;
				case 'selectbox':
				default:
					if ($use == 'accounts' || $use == 'groups' || $use == 'both')
					{
						$use = $GLOBALS['phpgw']->accounts->get_list($use);
					}
					elseif (!is_array($use))	// app-name
					{
						$use = $GLOBALS['phpgw']->acl->get_ids_for_location('run',1,$use);
						$enumerate_groups = True;
					}
			}
			$users = $groups = array();
			$use_keys = count($use) && !isset($use[0]);	// id's are the keys
			foreach($use as $key => $val)
			{
				$id = $use_keys ? $key : (is_array($val) ? $val['account_id'] : $val);

				if ($not && ($id == $not || is_array($not) && in_array($id,$not)))
				{
					continue;	// dont display that one
				}
				if ($this->get_type($id) == 'u')
				{
					$users[$id] = !is_array($val) ? $GLOBALS['phpgw']->common->grab_owner_name($id) :
						$GLOBALS['phpgw']->common->display_fullname(
								$val['account_lid'],$val['account_firstname'],$val['account_lastname']);
				}
				else
				{
					if ($enumerate_groups && ($members = $this->member($id)))
					{
						foreach($members as $member)
						{
							if ($not && $member['account_id'] == $not) continue;	// dont display that one

							$users[$member['account_id']] = $GLOBALS['phpgw']->common->grab_owner_name($member['account_id']);
						}
					}
					$groups[$id] = lang('group').' '.$this->id2name($id);
				}
			}
			// sort users and groups alphabeticaly and put the groups behind the users
			uasort($users,strcasecmp);
			uasort($groups,strcasecmp);
			$select = $users + $groups;

			if (count($selected) && !isset($selected[0]))	// id's are the keys
			{
				foreach($selected as $id => $val)
				{
					if (is_string($val) && isset($users[$id]))	// add string to option-label
					{
						$users[$id] .= " ($val)";
					}
				}
				$selected = array_keys($selected);
			}
			// add necessary popup trigers
			$popup = "window.open('".$GLOBALS['phpgw']->link('/index.php',array(
				'menuaction' => 'phpgwapi.uiaccountsel.popup',
				'app' => $GLOBALS['phpgw_info']['flags']['currentapp'],
				'element_id'  => $element_id,
			))."','Search','width=800,height=600,toolbar=no,scrollbars=yes,resizable=yes')";
			if (!$lines)
			{
				$options .= ' onchange="if (this.value=\'popup\') '.$popup.';'.($onchange?" else {$onchange}":'').'"';
				$select['popup'] = lang('Search').' ...';
			}
			elseif ($onchange)
			{
				$options .= ' onchange="'.$onchange.'"';
			}
			//echo "<p>html::select('$name',".print_r($selected,True).",".print_r($select,True).",True,'$options')</p>\n";
			$html = $GLOBALS['phpgw']->html->select($name,$selected,$select,True,$options.' id="'.$element_id.'"',$lines);

			if ($lines > 1 && ($this->account_selection == 'popup' || $this->account_selection == 'primary_group'))
			{
				$html .= $GLOBALS['phpgw']->html->image('calendar','multi_3',lang('click to select or search accounts'),' onclick="'.$popup.';" style="cursor: pointer; cursor: hand;"');
			}

			if(!$GLOBALS['phpgw_info']['flags']['uiaccountsel']['addOption_installed'])
			{
				$html .= '<script LANGUAGE="JavaScript">
	function addOption(id,label,value)
	{
'./*		alert(\'opener.addOption(\'+id+\',\'+label+\',\'+value+\')\');
*/'		selectBox = document.getElementById(id);
'./*		if (selectBox == null) alert(\'selectBox \'+id+\' not found !!!\');
*/'		selectBox.options[selectBox.length] = new Option(label,value,false,true);
	}
</script>';
				$GLOBALS['phpgw_info']['flags']['uiaccountsel']['addOption_installed'] = True;
			}
			return $html;
		}

		function popup($app='')
		{
			if (!$app) $app = $_GET['app'];

			$group_id = get_var('group_id',array('GET','POST'));
			$element_id = get_var('element_id',array('GET','POST'));

			if(isset($_POST['query']))
			{
				$GLOBALS['query'] = $_POST['query'];
			}

			$start = (int) get_var('start',array('POST'),0);
			$order = get_var('order',array('GET'),'account_lid');
			$sort = get_var('sort',array('GET'),'ASC');

			$this->nextmatchs = CreateObject('phpgwapi.nextmatchs');

			$GLOBALS['phpgw']->template->set_root($GLOBALS['phpgw']->common->get_tpl_dir('phpgwapi'));

			$GLOBALS['phpgw']->template->set_file(array('accounts_list_t' => 'uiaccountsel.tpl'));
			$GLOBALS['phpgw']->template->set_block('accounts_list_t','group_cal','cal');
			$GLOBALS['phpgw']->template->set_block('accounts_list_t','group_other','other');
			$GLOBALS['phpgw']->template->set_block('accounts_list_t','group_all','all');

			$GLOBALS['phpgw']->template->set_block('accounts_list_t','bla_intro','ibla');
			$GLOBALS['phpgw']->template->set_block('accounts_list_t','other_intro','iother');
			$GLOBALS['phpgw']->template->set_block('accounts_list_t','all_intro','iall');


			$GLOBALS['phpgw']->template->set_block('accounts_list_t','accounts_list','list');

			$GLOBALS['phpgw']->template->set_var('font',$GLOBALS['phpgw_info']['theme']['font']);
			$GLOBALS['phpgw']->template->set_var('lang_search',lang('search'));
			$GLOBALS['phpgw']->template->set_var('lang_groups',lang('user groups'));
			$GLOBALS['phpgw']->template->set_var('lang_accounts',lang('user accounts'));

			$GLOBALS['phpgw']->template->set_var('img',$GLOBALS['phpgw']->common->image('phpgwapi','select'));
			$GLOBALS['phpgw']->template->set_var('lang_select_user',lang('Select user'));
			$GLOBALS['phpgw']->template->set_var('lang_select_group',lang('Select group'));
			$GLOBALS['phpgw']->template->set_var('css_file',$GLOBALS['phpgw_info']['server']['webserver_url'] .
				'/phpgwapi/templates/idots/css/idots.css');

			switch($app)
			{
				case 'calendar':
					$GLOBALS['phpgw']->template->fp('ibla','bla_intro',True);
					$GLOBALS['phpgw']->template->fp('iall','all_intro',True);
					break;
				case 'admin':
					$GLOBALS['phpgw']->template->set_var('lang_perm',lang('group name'));
					$GLOBALS['phpgw']->template->fp('iother','other_intro',True);
					break;
				default:
					$GLOBALS['phpgw']->template->fp('iother','other_intro',True);
					$GLOBALS['phpgw']->template->fp('iall','all_intro',True);
					break;
			}

			$GLOBALS['phpgw_info']['flags']['currentapp'] = $app;
			$GLOBALS['phpgw']->common->phpgw_header();

			$GLOBALS['phpgw']->template->set_var('lang_perm',lang('Groups with permission for %1',lang($app)));
			$GLOBALS['phpgw']->template->set_var('lang_nonperm',lang('Groups without permission for %1',lang($app)));

			$link_data = array
			(
				'menuaction' => 'phpgwapi.uiaccountsel.popup',
				'app'        => $app,
				'group_id'   => $group_id,
				'element_id' => $element_id,
			);

			$app_groups = array();

			if ($app != 'admin')
			{
				$user_groups = $this->membership($this->account);

				$app_user = $GLOBALS['phpgw']->acl->get_ids_for_location('run',1,$app);
				for ($i = 0;$i<count($app_user);$i++)
				{
					$type = $this->get_type($app_user[$i]);
					if($type == 'g')
					{
						$app_groups[] = $app_user[$i];
						$members[] = $GLOBALS['phpgw']->acl->get_ids_for_location($app_user[$i],1,'phpgw_group');
					}
				}

				$i = count($app_user);
				while(is_array($members) && list(,$mem) = each($members))
				{
					for($j=0;$j<count($mem);$j++)
					{
						$app_user[$i] = $mem[$j];
						$i++;
					}
				}
				//_debug_array($app_user);
			}
			else
			{
				$all_groups	= $this->get_list('groups');
				$all_user	= $this->get_list('accounts');

				while(is_array($all_groups) && list(,$agroup) = each($all_groups))
				{
					$user_groups[] = array
					(
						'account_id'	=> $agroup['account_id'],
						'account_name'	=> $agroup['account_firstname']
					);
				}

				for($j=0;$j<count($user_groups);$j++)
				{
					$app_groups[$i] = $user_groups[$j]['account_id'];
					$i++;
				}

				for($j=0;$j<count($all_user);$j++)
				{
					$app_user[$i] = $all_user[$j]['account_id'];
					$i++;
				}
			}

			$GLOBALS['phpgw']->template->set_var('lang_list_members',lang('List members'));

			if (is_array($user_groups))
			{
				foreach($user_groups as $group)
				{
					$link_data['group_id'] = $group['account_id'];

					$GLOBALS['phpgw']->template->set_var('onclick',"opener.addOption('$element_id','".
						$GLOBALS['phpgw']->common->grab_owner_name($group['account_id'])."','$group[account_id]')");

					if (in_array($group['account_id'],$app_groups))
					{
						$GLOBALS['phpgw']->template->set_var('tr_color',$this->nextmatchs->alternate_row_color($tr_color));
						$GLOBALS['phpgw']->template->set_var('link_user_group',$GLOBALS['phpgw']->link('/index.php',$link_data));
						$GLOBALS['phpgw']->template->set_var('name_user_group',$group['account_name']);
						$GLOBALS['phpgw']->template->set_var('account_display',$GLOBALS['phpgw']->common->grab_owner_name($group['account_id']));
						$GLOBALS['phpgw']->template->set_var('accountid',$group['account_id']);

						switch($app)
						{
							case 'calendar':	$GLOBALS['phpgw']->template->fp('cal','group_cal',True); break;
							default:			$GLOBALS['phpgw']->template->fp('other','group_other',True); break;
						}
					}
					else
					{
						if ($app != 'admin')
						{
							$GLOBALS['phpgw']->template->set_var('link_all_group',$GLOBALS['phpgw']->link('/index.php',$link_data));
							$GLOBALS['phpgw']->template->set_var('name_all_group',$group['account_name']);
							$GLOBALS['phpgw']->template->set_var('accountid',$group['account_id']);
							$GLOBALS['phpgw']->template->fp('all','group_all',True);
						}
					}
				}
			}

			if (!$GLOBALS['query'])
			{
				if (isset($group_id) && !empty($group_id))
				{
					//echo 'GROUP_ID: ' . $group_id;
					$users = $GLOBALS['phpgw']->acl->get_ids_for_location($group_id,1,'phpgw_group');

					for ($i=0;$i<count($users);$i++)
					{
						if (in_array($users[$i],$app_user))
						{
							$GLOBALS['phpgw']->accounts->account_id = $users[$i];
							$GLOBALS['phpgw']->accounts->read_repository();

							switch ($order)
							{
								case 'account_firstname':
									$id = $GLOBALS['phpgw']->accounts->data['firstname'];
									break;
								case 'account_lastname':
									$id = $GLOBALS['phpgw']->accounts->data['lastname'];
									break;
								case 'account_lid':
								default:
									$id = $GLOBALS['phpgw']->accounts->data['account_lid'];
									break;
							}
							$id .= $GLOBALS['phpgw']->accounts->data['lastname'];	// default sort-order
							$id .= $GLOBALS['phpgw']->accounts->data['firstname'];
							$id .= $GLOBALS['phpgw']->accounts->data['account_id'];	// make our index unique

							$val_users[$id] = array
							(
								'account_id'		=> $GLOBALS['phpgw']->accounts->data['account_id'],
								'account_lid'		=> $GLOBALS['phpgw']->accounts->data['account_lid'],
								'account_firstname'	=> $GLOBALS['phpgw']->accounts->data['firstname'],
								'account_lastname'	=> $GLOBALS['phpgw']->accounts->data['lastname']
							);
						}
					}

					if (is_array($val_users))
					{
						if ($sort != 'DESC')
						{
							ksort($val_users);
						}
						else
						{
							krsort($val_users);
						}
					}
					$val_users = array_values($val_users);	// get a numeric index
				}
				$total = count($val_users);
			}
			else
			{
				switch($app)
				{
					case 'calendar':	$users = 'both'; break;
					default:			$users = 'accounts'; break;
				}
				$entries	= $this->get_list($users,$start,$sort,$order,$GLOBALS['query']);
				$total		= $this->total;
				for ($i=0;$i<count($entries);$i++)
				{
					if (in_array($entries[$i]['account_id'],$app_user))
					{
						$val_users[] = $entries[$i];
					}
				}
			}

// --------------------------------- nextmatch ---------------------------

			$left = $this->nextmatchs->left('/index.php',$start,$total,$link_data);
			$right = $this->nextmatchs->right('/index.php',$start,$total,$link_data);
			$GLOBALS['phpgw']->template->set_var('left',$left);
			$GLOBALS['phpgw']->template->set_var('right',$right);

			$GLOBALS['phpgw']->template->set_var('lang_showing',$this->nextmatchs->show_hits($total,$start));

// -------------------------- end nextmatch ------------------------------------

			$GLOBALS['phpgw']->template->set_var('search_action',$GLOBALS['phpgw']->link('/index.php',$link_data));
			$GLOBALS['phpgw']->template->set_var('search_list',$this->nextmatchs->search(array('query' => $GLOBALS['query'], 'search_obj' => 1)));

// ---------------- list header variable template-declarations --------------------------

// -------------- list header variable template-declaration ------------------------
			$GLOBALS['phpgw']->template->set_var('sort_lid',$this->nextmatchs->show_sort_order($sort,'account_lid',$order,'/index.php',lang('LoginID'),$link_data));
			$GLOBALS['phpgw']->template->set_var('sort_firstname',$this->nextmatchs->show_sort_order($sort,'account_firstname',$order,'/index.php',lang('Firstname'),$link_data));
			$GLOBALS['phpgw']->template->set_var('sort_lastname',$this->nextmatchs->show_sort_order($sort,'account_lastname',$order,'/index.php',lang('Lastname'),$link_data));

// ------------------------- end header declaration --------------------------------
			$stop = min($start + $this->nextmatchs->maxmatches,count($val_users));
			for($i = $start; $i < $stop; ++$i)
			{
				$GLOBALS['phpgw']->template->set_var('tr_color',$this->nextmatchs->alternate_row_color($tr_color));

// ---------------- template declaration for list records --------------------------

				$user = $val_users[$i];
				$GLOBALS['phpgw']->template->set_var(array(
					'lid'		=> $user['account_lid'],
					'firstname'	=> $user['account_firstname'] ? $user['account_firstname'] : '&nbsp;',
					'lastname'	=> $user['account_lastname'] ? $user['account_lastname'] : '&nbsp;',
					'onclick'	=> "opener.addOption('$element_id','".
						$GLOBALS['phpgw']->common->grab_owner_name($user['account_id'])."','$user[account_id]')",
				));

				$GLOBALS['phpgw']->template->fp('list','accounts_list',True);
			}

			$GLOBALS['phpgw']->template->set_var('start',$start);
			$GLOBALS['phpgw']->template->set_var('sort',$sort);
			$GLOBALS['phpgw']->template->set_var('order',$order);
			$GLOBALS['phpgw']->template->set_var('query',$GLOBALS['query']);
			$GLOBALS['phpgw']->template->set_var('group_id',$group_id);

			$GLOBALS['phpgw']->template->set_var('lang_done',lang('done'));
			$GLOBALS['phpgw']->template->pfp('out','accounts_list_t',True);

			$GLOBALS['phpgw']->common->phpgw_footer();
		}
	}
