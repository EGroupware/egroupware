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

	include_once(EGW_API_INC . '/class.accounts.inc.php');

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

			$this->account_selection = $GLOBALS['egw_info']['user']['preferences']['common']['account_selection'];

			if (!is_object($GLOBALS['egw']->html))
			{
				$GLOBALS['egw']->html =& CreateObject('phpgwapi.html');
			}
			$this->html = $GLOBALS['egw']->html;
		}

		/**
		 * Create an account-selection for a certain range of users
		 *
		 * @param $name string name of the form-element
		 * @param $element_id string id of the form-element, this need to be unique for the whole window !!!
		 * @param $selected array/int user-id or array of user-id's which are already selected
		 * @param $use string 'accounts', 'groups', 'owngroups', 'both' or app-name for all accounts with run-rights.
		 *	If an '+' is appended to the app-name, one can also select groups with run-rights for that app.
		 * @param $lines int number of lines for multiselection or 0 for a single selection
		 *	(in that case accounts should be an int or contain only 1 user-id)
		 * @param $not int/array user-id or array of user-id's not to display in selection, default False = display all
		 * @param $options	additional options (e.g. style)
		 * @param $onchange javascript to execute if the selection changes, eg. to reload the page
		 * @param $select array/bool/string array with id's as keys or values. If the id is in the key and the value is a string,
		 *	it gets appended to the user-name. Or false if the selectable values for the selectbox are determined by use.
		 *  Or a string which gets added as first Option with value=0, eg. lang('all')
		 * @param $nohtml boolean if true, returns an array with the key 'selected' as the selected participants,
		 *  and with the key 'participants' as the participants data as would fit in a select.
		 * @return the necessary html
		 */
		function selection($name,$element_id,$selected,$use='accounts',$lines=0,$not=False,$options='',$onchange='',$select=False,$nohtml=false)
		{
			//echo "<p>uiaccountsel::selection('$name',".print_r($selected,True).",'$use',$lines,$not,'$options','$onchange',".print_r($select,True).")</p>\n";
			if (!is_array($selected))
			{
				$selected = $selected ? explode(',',$selected) : array();
			}
			$account_sel = $this->account_selection;
			$app = False;
			switch($use)
			{
				default:
					if (substr($use,-1) == '+')
					{
						$app = substr($use,0,-1);
						$use = 'both';
					}
					else
					{
						$app = $use;
						$use = 'accounts';
					}
					break;
				case 'accounts':
				case 'both':
					break;
				case 'groups':
				case 'owngroups':
					$account_sel = 'selectbox';	// groups always use only the selectbox
					break;
			}
			$extra_label = is_string($select) && !empty($select) ? $select : False;
			switch($account_sel)
			{
				case 'popup':
					$select = $selected;
					break;
				case 'primary_group':
					$select = count($selected) && !isset($selected[0]) ? array_keys($selected) : $selected;
					$members = $this->member($GLOBALS['egw']->accounts->data['account_primary_group']);
					if (is_array($members))
					{
						foreach($members as $member)
						{
							if (!in_array($member['account_id'],$select))
							{
								$select[] = $member['account_id'];
							}
						}
					}
					break;
				case 'selectbox':
				default:
					if (!is_array($select))
					{
						$select = $GLOBALS['egw']->accounts->search(array(
							'type' => $use,
							'app' => $app,
						));
					}
					break;
			}
			$already_selected = $users = $groups = array();
			$use_keys = count($select) && !isset($select[0]);	// id's are the keys
			foreach($select as $key => $val)
			{
				$id = $use_keys ? $key : (is_array($val) ? $val['account_id'] : $val);

				if ($not && ($id == $not || is_array($not) && in_array($id,$not)))
				{
					continue;	// dont display that one
				}
				if (in_array($id,$selected))	// show already selected accounts first
				{
					$already_selected[$id] = $GLOBALS['egw']->common->grab_owner_name($id);
				}
				elseif ($this->get_type($id) == 'u')
				{
					$users[$id] = !is_array($val) ? $GLOBALS['egw']->common->grab_owner_name($id) :
						$GLOBALS['egw']->common->display_fullname(
							$val['account_lid'],$val['account_firstname'],$val['account_lastname']);
				}
				else
				{
					$groups[$id] = $GLOBALS['egw']->common->grab_owner_name($id);
				}
			}
			// sort users and groups alphabeticaly and put the groups behind the users
			uasort($already_selected,strcasecmp);
			uasort($users,strcasecmp);
			uasort($groups,strcasecmp);
			$select = $already_selected + $users + $groups;
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
			$link = $GLOBALS['egw']->link('/index.php',array(
				'menuaction' => 'phpgwapi.uiaccountsel.popup',
				'app' => $app,
				'use' => $use,
				'element_id'  => $element_id,
				'multiple' => $lines,	// single selection (multiple=0), closes after the first selection
			));
			$popup_options = 'width=600,height=400,toolbar=no,scrollbars=yes,resizable=yes';
			$app = $GLOBALS['egw_info']['flags']['currentapp'];
			if ($lines <= 1 && $use != 'groups' && $use != 'owngroups')
			{
				if (!$lines)
				{
					$options .= ' onchange="if (this.value==\'popup\') '."window.open('$link','uiaccountsel','$popup_options');".
						($onchange ? " else { $onchange }" : '' ).'" onclick="if (this.value==\'popup\') '."window.open('$link','uiaccountsel','$popup_options');\"";
					$select['popup'] = lang('Search').' ...';
				}
				elseif ($onchange)
				{
					$options .= ' onchange="if (this.value[0]!=\',\') { '.$onchange.' }"';
				}
				$need_js_popup = True;
			}
			elseif ($onchange)
			{
				$options .= ' onchange="'.$onchange.'"';
			}
			if ($extra_label)
			{
				//in php5 this put's the extra-label at the end: $select = array($extra_label) + $select;
				$select2 = array($extra_label);
				$select2 += $select;
				$select =& $select2; unset($select2);
			}
			
			if ($nohtml)
			{
				return array(
					'selected' => $selected,
					'participants' => $select
				);	
			}
			//echo "<p>html::select('$name',".print_r($selected,True).",".print_r($select,True).",True,'$options')</p>\n";
			$html = $this->html->select($name,$selected,$select,True,$options.' id="'.$element_id.'"',$lines > 1 ? $lines : 0);

			if ($lines > 0 && ($this->account_selection == 'popup' || $this->account_selection == 'primary_group'))
			{
				$html .= $this->html->submit_button('search','Search',"window.open('$link','uiaccountsel','$popup_options'); return false;",false,
					' title="'.$this->html->htmlspecialchars($lines > 1 ? lang('search or select accounts') : lang('search or select multiple accounts')).'"',
					'users','phpgwapi');
				$need_js_popup = True;
			}
			if ($lines == 1 && $this->account_selection == 'selectbox')
			{
				$html .= '<a href="#" onclick="'."if (selectBox = document.getElementById('$element_id')) { selectBox.size=3; selectBox.multiple=true; } return false;".'">'.
					$this->html->image('phpgwapi','users',lang('select multiple accounts')).'</a>';
			}
			if($need_js_popup && !$GLOBALS['egw_info']['flags']['uiaccountsel']['addOption_installed'])
			{
				$html .= '<script language="JavaScript">
	function addOption(id,label,value,do_onchange)
	{
		selectBox = document.getElementById(id);
		for (i=0; i < selectBox.length; i++) {
'.//		check existing entries if they're already there and only select them in that case
'			if (selectBox.options[i].value == value) {
				selectBox.options[i].selected = true;
				break;
			}
'.//		check existing entries for an entry starting with a comma, marking a not yet finished multiple selection
'			else if (value.slice(0,1) == "," && selectBox.options[i].value.slice(0,1) == ",") {
				selectBox.options[i].value = value;
				selectBox.options[i].text = "'.lang('multiple').'";
				selectBox.options[i].title = label;
				selectBox.options[i].selected = true;
				break;
			}
		}
		if (i >= selectBox.length) {
			selectBox.options[selectBox.length] =& new Option(label,value,false,true);
		}
		if (selectBox.onchange && do_onchange) selectBox.onchange();
	}
</script>';
				$GLOBALS['egw_info']['flags']['uiaccountsel']['addOption_installed'] = True;
			}
			return $html;
		}

		function popup()
		{
			global $query;	// nextmatch requires that !!!

			$app = get_var('app',array('POST','GET'));
			$use = get_var('use',array('POST','GET'));
			$group_id = get_var('group_id',array('POST','GET'),$GLOBALS['egw']->accounts->data['account_primary_group']);
			$element_id = get_var('element_id',array('POST','GET'));
			$multiple = get_var('multiple',array('POST','GET'));

			$query = get_var('query',array('POST','GET'));
			$query_type = get_var('query_type',array('POST','GET'));

			$start = (int) get_var('start',array('POST'),0);
			$order = get_var('order',array('POST','GET'),'account_lid');
			$sort = get_var('sort',array('POST','GET'),'ASC');

			//echo "<p>uiaccountsel::popup(): app='$app', use='$use', multiple='$multiple', group_id='$group_id', element_id='$element_id', start='$start', order='$order', sort='$sort'</p>\n";

			$this->nextmatchs =& CreateObject('phpgwapi.nextmatchs');

			$GLOBALS['egw']->template->set_root($GLOBALS['egw']->common->get_tpl_dir('phpgwapi'));

			$GLOBALS['egw']->template->set_file(array('accounts_list_t' => 'uiaccountsel.tpl'));
			$GLOBALS['egw']->template->set_block('accounts_list_t','letter_search','letter_search_cells');
			$GLOBALS['egw']->template->set_block('accounts_list_t','group_cal','cal');
			$GLOBALS['egw']->template->set_block('accounts_list_t','group_other','other');
			$GLOBALS['egw']->template->set_block('accounts_list_t','group_all','all');

			$GLOBALS['egw']->template->set_block('accounts_list_t','bla_intro','ibla');
			$GLOBALS['egw']->template->set_block('accounts_list_t','other_intro','iother');
			$GLOBALS['egw']->template->set_block('accounts_list_t','all_intro','iall');

			$GLOBALS['egw']->template->set_block('accounts_list_t','accounts_list','list');

			$GLOBALS['egw']->template->set_var('font',$GLOBALS['egw_info']['theme']['font']);
			$GLOBALS['egw']->template->set_var('lang_search',lang('search'));
			$GLOBALS['egw']->template->set_var('lang_groups',lang('user groups'));
			$GLOBALS['egw']->template->set_var('lang_accounts',lang('user accounts'));

			$GLOBALS['egw']->template->set_var('img',$GLOBALS['egw']->common->image('phpgwapi','select'));
			$GLOBALS['egw']->template->set_var('lang_select_user',lang('Select user'));
			$GLOBALS['egw']->template->set_var('lang_select_group',lang('Select group'));

			if ($app)	// split the groups in the ones with run-rights and without
			{
				if ($use == 'both')		// groups with run-rights too, eg. calendar
				{
					$GLOBALS['egw']->template->fp('ibla','bla_intro',True);
				}
				else
				{
					$GLOBALS['egw']->template->fp('iother','other_intro',True);
				}
				$GLOBALS['egw']->template->fp('iall','all_intro',True);
			}
			else	// use all groups and account, eg. admin
			{
				$GLOBALS['egw']->template->set_var('lang_perm',lang('group name'));
				$GLOBALS['egw']->template->fp('iother','other_intro',True);
			}

			if ($multiple >= 1)
			{
				if (!is_object($GLOBALS['egw']->js))
				{
					$GLOBALS['egw']->js =& CreateObject('phpgwapi.javascript');
				}
				$GLOBALS['egw']->js->set_onload("copyOptions('$element_id');");
			}
			$GLOBALS['egw_info']['flags']['app_header'] = lang('search or select accounts');
			$GLOBALS['egw']->common->egw_header();

			$GLOBALS['egw']->template->set_var('lang_perm',lang('Groups with permission for %1',lang($app)));
			$GLOBALS['egw']->template->set_var('lang_nonperm',lang('Groups without permission for %1',lang($app)));

			$link_data = array
			(
				'menuaction' => 'phpgwapi.uiaccountsel.popup',
				'app'        => $app,
				'use'        => $use,
				'group_id'   => $group_id,
				'element_id' => $element_id,
				'multiple'   => $multiple,
				'query_type' => $query_type,
				'query'      => $query,
			);

// -------------- list header variable template-declaration ------------------------
			$GLOBALS['egw']->template->set_var('sort_lid',$this->nextmatchs->show_sort_order($sort,'account_lid',$order,'/index.php',lang('LoginID'),$link_data));
			$GLOBALS['egw']->template->set_var('sort_firstname',$this->nextmatchs->show_sort_order($sort,'account_firstname',$order,'/index.php',lang('Firstname'),$link_data));
			$GLOBALS['egw']->template->set_var('sort_lastname',$this->nextmatchs->show_sort_order($sort,'account_lastname',$order,'/index.php',lang('Lastname'),$link_data));

// ------------------------- end header declaration --------------------------------

			$link_data['sort'] = $sort;
			$link_data['order'] = $order;

			$GLOBALS['egw']->template->set_var('lang_list_members',lang('List members'));
			$GLOBALS['egw']->template->set_var('lang_firstname',lang('firstname'));
			$GLOBALS['egw']->template->set_var('lang_lastname',lang('lastname'));

			if ($app)
			{
				$app_groups = $this->split_accounts($app,'groups');
			}
			$all_groups = $this->search(array(
				'type' => 'groups',
			));
			foreach($all_groups as $group)
			{
				$link_data['group_id'] = $group['account_id'];

				$GLOBALS['egw']->template->set_var('onclick',"addOption('$element_id','".
					$GLOBALS['egw']->common->grab_owner_name($group['account_id'])."','$group[account_id]',".(int)($multiple==1).")".
					(!$multiple ? '; window.close()' : ''));

				if (!$app || in_array($group['account_id'],$app_groups))
				{
					$GLOBALS['egw']->template->set_var('tr_color',$this->nextmatchs->alternate_row_color($tr_color,True));
					$GLOBALS['egw']->template->set_var('link_user_group',$GLOBALS['egw']->link('/index.php',$link_data));
					$GLOBALS['egw']->template->set_var('name_user_group',$GLOBALS['egw']->common->grab_owner_name($group['account_id']));

					if($use == 'both')	// allow selection of groups
					{
						$GLOBALS['egw']->template->fp('cal','group_cal',True);
					}
					else
					{
						$GLOBALS['egw']->template->fp('other','group_other',True);
					}
				}
				else
				{
					$GLOBALS['egw']->template->set_var('link_all_group',$GLOBALS['egw']->link('/index.php',$link_data));
					$GLOBALS['egw']->template->set_var('name_all_group',$GLOBALS['egw']->common->grab_owner_name($group['account_id']));
					$GLOBALS['egw']->template->set_var('accountid',$group['account_id']);
					$GLOBALS['egw']->template->fp('all','group_all',True);
				}
			}
			$link_data['group_id'] = $group_id;		// reset it

// --------------------------------- nextmatch ---------------------------
			$users = $this->search(array(
				'type' => $group_id ? $group_id : $use,
				'app' => $app,
				'start' => $start,
				'order' => $order,
				'sort' => $sort,
				'query' => $query,
				'query_type' => $query_type,
			));

			$GLOBALS['egw']->template->set_var(array(
				'left'  => $this->nextmatchs->left('/index.php',$start,$this->total,$link_data+array('query'=>$query)),
				'right' => $this->nextmatchs->right('/index.php',$start,$this->total,$link_data+array('query'=>$query)),
				'lang_showing' => ($group_id ? $GLOBALS['egw']->common->grab_owner_name($group_id).': ' : '').
					($query ? lang("Search %1 '%2'",lang($this->query_types[$query_type]),$query).': ' : '')
					.$this->nextmatchs->show_hits($this->total,$start),
			));

// -------------------------- end nextmatch ------------------------------------

			$GLOBALS['egw']->template->set_var('search_action',$GLOBALS['egw']->link('/index.php',$link_data));
			$GLOBALS['egw']->template->set_var('prev_query', $query);
			$GLOBALS['egw']->template->set_var('search_list',$this->nextmatchs->search(array('query' => $query, 'search_obj' => 1)));
			$GLOBALS['egw']->template->set_var('lang_firstname', lang("firstname"));
			$GLOBALS['egw']->template->set_var('lang_lastname', lang("lastname"));

			foreach($users as $user)
			{
				$GLOBALS['egw']->template->set_var('tr_color',$this->nextmatchs->alternate_row_color($tr_color,True));

// ---------------- template declaration for list records --------------------------

				$GLOBALS['egw']->template->set_var(array(
					'lid'		=> $user['account_lid'],
					'firstname'	=> $user['account_firstname'] ? $user['account_firstname'] : '&nbsp;',
					'lastname'	=> $user['account_lastname'] ? $user['account_lastname'] : '&nbsp;',
					'onclick'	=> "addOption('$element_id','".
						$GLOBALS['egw']->common->grab_owner_name($user['account_id'])."','$user[account_id]',".(int)($multiple==1).")".
						(!$multiple ? '; window.close()' : ''),
				));
				$GLOBALS['egw']->template->fp('list','accounts_list',True);
			}

			$GLOBALS['egw']->template->set_var('accountsel_icon',$this->html->image('phpgwapi','users-big'));
			$GLOBALS['egw']->template->set_var('query_type',is_array($this->query_types) ? $this->html->select('query_type',$query_type,$this->query_types) : '');

			$link_data['query_type'] = 'start';
			$letters = lang('alphabet');
			$letters = explode(',',substr($letters,-1) != '*' ? $letters : 'a,b,c,d,e,f,g,h,i,j,k,l,m,n,o,p,q,r,s,t,u,v,w,x,y,z');
			foreach($letters as $letter)
			{
				$link_data['query'] = $letter;
				$GLOBALS['egw']->template->set_var(array(
					'letter' => $letter,
					'link'   => $GLOBALS['egw']->link('/index.php',$link_data),
					'class'  => $query == $letter && $query_type == 'start' ? 'letter_box_active' : 'letter_box',
				));
				$GLOBALS['egw']->template->fp('letter_search_cells','letter_search',True);
			}
			unset($link_data['query']);
			unset($link_data['query_type']);
			$GLOBALS['egw']->template->set_var(array(
				'letter' => lang('all'),
				'link'   => $GLOBALS['egw']->link('/index.php',$link_data),
				'class'  => $query_type != 'start' || !in_array($query,$letters) ? 'letter_box_active' : 'letter_box',
			));
			$GLOBALS['egw']->template->fp('letter_search_cells','letter_search',True);

			$GLOBALS['egw']->template->set_var(array(
				'lang_selection' => lang('selection'),
				'lang_close' => lang('close'),
				'close_action' => 'window.close();',
			));
			
			if ($multiple >= 1)
			{
				$GLOBALS['egw']->template->set_var(array(
					'lang_close' => lang('submit'),
					'lang_multiple' => lang('multiple'),
					'close_action' => "oneLineSubmit('$element_id');",
				));
			}
				

			if ($multiple)
			{
				$GLOBALS['egw']->template->set_var(array(
					'selection' => $this->html->select('selected',False,array(),True,' id="uiaccountsel_popup_selection" style="width: 100%;"',13),
					'remove' => $this->html->submit_button('remove','remove',
						"removeSelectedOptions('$element_id'); return false;",True,' title="'.lang('Remove selected accounts').'"','delete'),
				));
			}
			$GLOBALS['egw']->template->pfp('out','accounts_list_t',True);

			$GLOBALS['egw']->common->egw_footer();
		}
	}
