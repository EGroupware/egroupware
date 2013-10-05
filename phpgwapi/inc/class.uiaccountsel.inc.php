<?php
/**
 * API - accounts selection
 *
 * @link http://www.egroupware.org
 * @author Ralf Becker <RalfBecker-AT-outdoor-training.de>
 *
 * The original version of the acount-selection popup was written and
 * (c) 2003 by Bettina Gille [ceb@phpgroupware.org]
 *
 * @license http://opensource.org/licenses/gpl-license.php GPL - GNU General Public License
 * @package api
 * @subpackage html
 * @access public
 * @version $Id$
 */

/**
 * User Interface for account and/or group selection
 */
class uiaccountsel
{
	var $public_functions = array(
		'popup' => True,
	);
	/**
	 * value of the commen pref. 'account_selection' for non admins, 'primary_group' for admins
	 *
	 * @var string
	 */
	var $account_selection;
	/**
	 * Reference to global accounts object
	 *
	 * @var accounts
	 */
	var $accounts;

	/**
	 * Constructor
	 *
	 * @return uiaccountsel
	 */
	function __construct()
	{
		$this->accounts = $GLOBALS['egw']->accounts;

		$this->account_selection = $GLOBALS['egw_info']['user']['preferences']['common']['account_selection'];
		// admin group should NOT get limited by none or groupmembers, we use primary_group instead
		if (isset($GLOBALS['egw_info']['user']['apps']['admin']) &&
			($this->account_selection == 'none' || $this->account_selection == 'groupmembers'))
		{
			$this->account_selection = 'primary_group';
		}
	}

	/**
	 * Create an account-selection for a certain range of users
	 *
	 * The function respects the 'account_selection' general preference:
	 * 	- 'selectbox'     => Selectbox with all accounts and groups
	 *  - 'primary_group' => Selectbox with primary group and search
	 *  - 'popup'         => Popup with search
	 *  - 'groupmembers'  => Non admins can only select groupmembers
	 *  - 'none'          => Non admins can NOT select any other user
	 *
	 * @param string $name name of the form-element
	 * @param string $element_id id of the form-element, this need to be unique for the whole window !!!
	 * @param array/int $selected user-id or array of user-id's which are already selected
	 * @param string $use 'accounts', 'groups', 'owngroups', 'both' or app-name for all accounts with run-rights.
	 *	If an '+' is appended to the app-name, one can also select groups with run-rights for that app.
	 * @param int $lines > 1 number of lines for multiselection, 0 for a single selection,
	 * 	< 0 or 1(=-4) single selection which can be switched to a multiselection by js abs($lines) is size
	 *	(in that case accounts should be an int or contain only 1 user-id)
	 * @param int/array $not user-id or array of user-id's not to display in selection, default False = display all
	 * @param string $options additional options (e.g. style)
	 * @param string $onchange javascript to execute if the selection changes, eg. to reload the page
	 * @param array/bool/string $select array with id's as keys or values. If the id is in the key and the value is a string,
	 *	it gets appended to the user-name. Or false if the selectable values for the selectbox are determined by use.
	 *  Or a string which gets added as first Option with value='', eg. lang('all'), can also be specified in the array with key ''
	 * @param boolean $nohtml if true, returns an array with the key 'selected' as the selected participants,
	 *  and with the key 'participants' as the participants data as would fit in a select.
	 * @param callback $label_callback=null callback to fetch a label for non-accounts
	 * @return string/array string with html for !$nohtml, array('selected' => $selected,'participants' => $select)
	 */
	function selection($name,$element_id,$selected,$use='accounts',$lines=0,$not=False,$options='',$onchange='',$select=False,$nohtml=false,$label_callback=null)
	{
		//error_log(__METHOD__."('$name',".array2string($selected).",'$use',rows=$lines,$not,'$options','$onchange',".array2string($select).",$nohtml,$label_callback) account_selection=$this->account_selection");
		$multi_size=4;
		if ($lines < 0)
		{
			$multi_size = abs($lines);
			$lines = 1;
		}
		$options .= ' class="uiaccountselection '.$this->account_selection.'"';	// to be able to style and select it with jQuery

		if ($this->account_selection == 'none')	// dont show user-selection at all!
		{
			return html::input_hidden($name,$selected);
		}
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
				if ($this->account_selection == 'groupmembers') $use = 'owngroups';
				// fall-through

			case 'owngroups':
				$only_groups = true;
				$account_sel = 'selectbox';	// groups always use only the selectbox
				break;
		}
		$extra_label = is_string($select) && !empty($select) ? $select : False;
		if (is_array($select) && isset($select['']))
		{
			$extra_label = $select[''];
			unset($select['']);
		}
		switch($account_sel)
		{
			case 'popup':
				$select = $selected;
				break;

			case 'primary_group':
			case 'groupmembers':
				if ($account_sel == 'primary_group')
				{
					$memberships = array($GLOBALS['egw_info']['user']['account_primary_group']);
				}
				else
				{
					$memberships = (array)$this->accounts->memberships($GLOBALS['egw_info']['user']['account_id'],true);
				}
				$select = count($selected) && !isset($selected[0]) ? array_keys($selected) : $selected;
				foreach($memberships as $gid)
				{
					foreach((array)$this->accounts->members($gid,true) as $member)
					{
						if (!in_array($member,$select) && $this->accounts->is_active($member)) $select[] = $member;
					}
				}
				if ($use == 'both')	// show all memberships
				{
					if ($account_sel == 'primary_group')
					{
						$memberships = (array)$this->accounts->memberships($GLOBALS['egw_info']['user']['account_id'],true);
					}
					$select = array_merge($select,$memberships);
				}
				break;

			case 'selectbox':
			default:
				if (!is_array($select))
				{
					$select = $GLOBALS['egw']->accounts->search(array(
						'type' => $use,
						'app' => $app,
						'active' => true,	// return only active accounts
					));
					//error_log(__METHOD__."() account_selection='$this->account_selection', accounts->search(array('type'=>'$use', 'app' => '$app')) returns ".array2string($select));
				}
				// make sure everything in $selected is also in $select, as in the other account-selection methods
				if ($selected && ($missing = array_diff_key($selected,$select)))
				{
					foreach($missing as $k => $v)	// merge missing (cant use array_merge, because of nummeric keys!)
					{
						$select[$k] = $v;
					}
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
			$label = common::grab_owner_name($id);
			if ($label[0] === '#' && $label_callback)
			{
				if (!($label = call_user_func($label_callback, $id))) continue;
			}
			if (in_array($id,$selected))	// show already selected accounts first
			{
				$already_selected[$id] = $label;
			}
			elseif ($this->accounts->get_type($id) == 'u')
			{
				$users[$id] = !is_array($val) ? $label :
					common::display_fullname(
						$val['account_lid'],$val['account_firstname'],$val['account_lastname'],$id);
			}
			else
			{
				$groups[$id] = $label;
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
		$link = egw::link('/index.php',array(
			'menuaction' => 'phpgwapi.uiaccountsel.popup',
			'app' => $app,
			'use' => $use,
			'element_id'  => $element_id,
			'multiple' => $lines,	// single selection (multiple=0), closes after the first selection
		),false);
		$app = $GLOBALS['egw_info']['flags']['currentapp'];
		if (!$only_groups && ($lines <= 1 && $this->account_selection == 'popup' || !$lines && $this->account_selection == 'primary_group'))
		{
			if (!$lines)
			{
				$select['popup'] = lang('Search').' ...';
			}
		}
		if ($onchange) $options .= ' onchange="'.$onchange.'"';	// no working under CSP without 'unsafe-inline'

		if ($extra_label)
		{
			//in php5 this put's the extra-label at the end: $select = array($extra_label) + $select;
			$select2 = array('' => $extra_label);
			$select2 += $select;
			$select =& $select2; unset($select2);
		}
		//error_log(__METHOD__."(..., use='$use', ...) account_selection='$this->account_selection', select=".array2string($select));

		if ($nohtml)
		{
			return array(
				'selected' => $selected,
				'participants' => $select
			);
		}
		//echo "<p>html::select('$name',".print_r($selected,True).",".print_r($select,True).",True,'$options')</p>\n";
		$html = html::select($name,$selected,$select,True,$options.' id="'.$element_id.
			'" data-popup-link="'.htmlspecialchars($link).'"',$lines > 1 ? $lines : 0,false);

		if (!$only_groups && ($lines > 0 && $this->account_selection == 'popup' || $lines > 1 && $this->account_selection == 'primary_group'))
		{
			$html .= html::submit_button('search','Search accounts',$js,false,
				' title="'.html::htmlspecialchars(lang('Search accounts')).
				'" class="uiaccountselection_trigger" id="'.$element_id.'_popup"','search','phpgwapi','button');
			$need_js_popup = True;
		}
		elseif (!$only_groups && ($lines == 1 || $lines > 0 && $this->account_selection == 'primary_group'))
		{
			$html .= html::submit_button('search','Select multiple accounts','',false,
				' title="'.html::htmlspecialchars(lang('Select multiple accounts')).
				'" class="uiaccountselection_trigger" id="'.$element_id.'_multiple"','users','phpgwapi','button');
		}
		return $html;
	}

	function popup()
	{
		// switch CSP to script-src 'unsafe-inline', until code get fixed here to work without
		egw_framework::csp_script_src_attrs('unsafe-inline');

		global $query;	// nextmatch requires that !!!

		$app = get_var('app',array('POST','GET'));
		$use = get_var('use',array('POST','GET'));
		$group_id = get_var('group_id',array('POST','GET'), $GLOBALS['egw_info']['user']['account_primary_group']);
		$element_id = get_var('element_id',array('POST','GET'));
		$multiple = get_var('multiple',array('POST','GET'));

		$query = get_var('query',array('POST','GET'));
		$query_type = get_var('query_type',array('POST','GET'));

		$start = (int) get_var('start',array('POST'),0);
		$order = get_var('order',array('POST','GET'),'account_lid');
		$sort = get_var('sort',array('POST','GET'),'ASC');

		//echo "<p>uiaccountsel::popup(): app='$app', use='$use', multiple='$multiple', group_id='$group_id', element_id='$element_id', start='$start', order='$order', sort='$sort'</p>\n";

		$nextmatchs = new nextmatchs();

		$GLOBALS['egw']->template->set_root(common::get_tpl_dir('phpgwapi'));

		$GLOBALS['egw']->template->set_file(array('accounts_list_t' => 'uiaccountsel.tpl'));
		$GLOBALS['egw']->template->set_block('accounts_list_t','letter_search','letter_search_cells');
		$GLOBALS['egw']->template->set_block('accounts_list_t','group_cal','cal');
		$GLOBALS['egw']->template->set_block('accounts_list_t','group_selectAll','selectAllGroups');
		$GLOBALS['egw']->template->set_block('accounts_list_t','groups_multiple','multipleGroups');
		$GLOBALS['egw']->template->set_block('accounts_list_t','group_other','other');
		$GLOBALS['egw']->template->set_block('accounts_list_t','group_all','all');

		$GLOBALS['egw']->template->set_block('accounts_list_t','bla_intro','ibla');
		$GLOBALS['egw']->template->set_block('accounts_list_t','other_intro','iother');
		$GLOBALS['egw']->template->set_block('accounts_list_t','all_intro','iall');

		$GLOBALS['egw']->template->set_block('accounts_list_t','accounts_selectAll','selectAllAccounts');
		$GLOBALS['egw']->template->set_block('accounts_list_t','accounts_multiple','multipleAccounts');
		$GLOBALS['egw']->template->set_block('accounts_list_t','accounts_list','list');

		$GLOBALS['egw']->template->set_var('font',$GLOBALS['egw_info']['theme']['font']);
		$GLOBALS['egw']->template->set_var('lang_search',lang('search'));
		$GLOBALS['egw']->template->set_var('lang_groups',lang('user groups'));
		$GLOBALS['egw']->template->set_var('lang_accounts',lang('user accounts'));
		$GLOBALS['egw']->template->set_var('lang_all',lang('all'));

		$GLOBALS['egw']->template->set_var('img',common::image('phpgwapi','select'));
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
			$GLOBALS['egw']->js->set_onload("copyOptions('$element_id');");
		}
		$GLOBALS['egw_info']['flags']['app_header'] = lang('search or select accounts');
		common::egw_header();

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
		$GLOBALS['egw']->template->set_var('sort_lid',$nextmatchs->show_sort_order($sort,'account_lid',$order,'/index.php',lang('LoginID'),$link_data));
		$GLOBALS['egw']->template->set_var('sort_firstname',$nextmatchs->show_sort_order($sort,'account_firstname',$order,'/index.php',lang('Firstname'),$link_data));
		$GLOBALS['egw']->template->set_var('sort_lastname',$nextmatchs->show_sort_order($sort,'account_lastname',$order,'/index.php',lang('Lastname'),$link_data));

// ------------------------- end header declaration --------------------------------

		$link_data['sort'] = $sort;
		$link_data['order'] = $order;

		$GLOBALS['egw']->template->set_var('lang_list_members',lang('List members'));
		$GLOBALS['egw']->template->set_var('lang_firstname',lang('firstname'));
		$GLOBALS['egw']->template->set_var('lang_lastname',lang('lastname'));

		if ($multiple)
		{
			$GLOBALS['egw']->template->fp('multipleGroups','groups_multiple',True);
		}

		if ($app)
		{
			$app_groups = $this->accounts->split_accounts($app,'groups');
		}
		$all_groups = $this->accounts->search(array(
			'type'  => 'groups',
			'order' => 'account_lid',
			'sort'  => 'ASC',
		));
		$tr_color_app_groups = $tr_color_all_groups = 'row_off';
		foreach($all_groups as $group)
		{
			$link_data['group_id'] = $group['account_id'];

			$GLOBALS['egw']->template->set_var('onclick',"ownAddOption('$element_id','".
				addslashes(common::grab_owner_name($group['account_id']))."','$group[account_id]',".(int)($multiple==1).")".
				(!$multiple ? '; window.close()' : ''));

			if (!$app || in_array($group['account_id'],$app_groups))
			{
				$GLOBALS['egw']->template->set_var('tr_color',$tr_color_app_groups=$nextmatchs->alternate_row_color($tr_color_app_groups,True));
				$GLOBALS['egw']->template->set_var('link_user_group',egw::link('/index.php',$link_data));
				$GLOBALS['egw']->template->set_var('name_user_group', ($group['account_id'] == $group_id ? '<b>' : '').
					common::grab_owner_name($group['account_id']).($group['account_id'] == $group_id ? '</b>' : ''));

				if($use == 'both')	// allow selection of groups
				{
					$GLOBALS['egw']->template->fp('cal','group_cal',True);
					$GLOBALS['egw']->template->set_var('js_addAllGroups',"ownAddOption('$element_id','".
						addslashes(common::grab_owner_name($group['account_id']))."','$group[account_id]',".(int)($multiple==1).")".
						(!$multiple ? '; window.close();' : ';'));
					$GLOBALS['egw']->template->fp('selectAllGroups','group_selectAll',True);
				}
				else
				{
					$GLOBALS['egw']->template->fp('other','group_other',True);
				}
			}
			else
			{
				$GLOBALS['egw']->template->set_var('tr_color',$tr_color_all_groups=$nextmatchs->alternate_row_color($tr_color_all_groups,True));
				$GLOBALS['egw']->template->set_var('link_all_group',egw::link('/index.php',$link_data));
				$GLOBALS['egw']->template->set_var('name_all_group',common::grab_owner_name($group['account_id']));
				$GLOBALS['egw']->template->set_var('accountid',$group['account_id']);
				$GLOBALS['egw']->template->fp('all','group_all',True);
			}
		}
		$link_data['group_id'] = $group_id;		// reset it

// --------------------------------- nextmatch ---------------------------
		$users = $this->accounts->search(array(
			'type' => $group_id ? $group_id : $use,
			'app' => $app,
			'start' => $start,
			'order' => $order,
			'sort' => $sort,
			'query' => $query,
			'query_type' => $query_type,
		));

		$GLOBALS['egw']->template->set_var(array(
			'left'  => $nextmatchs->left('/index.php',$start,$this->accounts->total,$link_data+array('query'=>$query)),
			'right' => $nextmatchs->right('/index.php',$start,$this->accounts->total,$link_data+array('query'=>$query)),
			'lang_showing' => ($group_id ? common::grab_owner_name($group_id).': ' : '').
				($query ? lang("Search %1 '%2'",lang($this->accounts->query_types[$query_type]),$query).': ' : '')
				.$nextmatchs->show_hits($this->accounts->total,$start),
		));

// -------------------------- end nextmatch ------------------------------------

		$GLOBALS['egw']->template->set_var('search_action',egw::link('/index.php',$link_data));
		$GLOBALS['egw']->template->set_var('prev_query', $query);
		$GLOBALS['egw']->template->set_var('search_list',$nextmatchs->search(array('query' => $query, 'search_obj' => 1)));
		$GLOBALS['egw']->template->set_var('lang_firstname', lang("firstname"));
		$GLOBALS['egw']->template->set_var('lang_lastname', lang("lastname"));

		if ($multiple)
		{
			$GLOBALS['egw']->template->fp('multipleAccounts','accounts_multiple',True);
		}

		$tr_color = 'row_off';
		foreach($users as $user)
		{
			$GLOBALS['egw']->template->set_var('tr_color',$tr_color=$nextmatchs->alternate_row_color($tr_color,True));

// ---------------- template declaration for list records --------------------------

			$GLOBALS['egw']->template->set_var(array(
				'lid'		=> $user['account_lid'],
				'firstname'	=> $user['account_firstname'] ? $user['account_firstname'] : '&nbsp;',
				'lastname'	=> $user['account_lastname'] ? $user['account_lastname'] : '&nbsp;',
				'onclick'	=> "ownAddOption('$element_id','".
					addslashes(common::grab_owner_name($user['account_id']))."','$user[account_id]',".(int)($multiple==1).")".
					(!$multiple ? '; window.close()' : ''),
			));
			$GLOBALS['egw']->template->fp('list','accounts_list',True);
			$GLOBALS['egw']->template->set_var('js_addAllAccounts',"ownAddOption('$element_id','".
					addslashes(common::grab_owner_name($user['account_id']))."','$user[account_id]',".(int)($multiple==1).")".
					(!$multiple ? '; window.close()' : ';'));
			$GLOBALS['egw']->template->fp('selectAllAccounts','accounts_selectAll',True);
		}

		$GLOBALS['egw']->template->set_var('accountsel_icon',html::image('phpgwapi','users-big'));
		$GLOBALS['egw']->template->set_var('query_type',is_array($this->accounts->query_types) ? html::select('query_type',$query_type,$this->accounts->query_types) : '');

		$link_data['query_type'] = 'start';
		$letters = lang('alphabet');
		$letters = explode(',',substr($letters,-1) != '*' ? $letters : 'a,b,c,d,e,f,g,h,i,j,k,l,m,n,o,p,q,r,s,t,u,v,w,x,y,z');
		foreach($letters as $letter)
		{
			$link_data['query'] = $letter;
			$GLOBALS['egw']->template->set_var(array(
				'letter' => $letter,
				'link'   => egw::link('/index.php',$link_data),
				'class'  => $query == $letter && $query_type == 'start' ? 'letter_box_active' : 'letter_box',
			));
			$GLOBALS['egw']->template->fp('letter_search_cells','letter_search',True);
		}
		unset($link_data['query']);
		unset($link_data['query_type']);
		$GLOBALS['egw']->template->set_var(array(
			'letter' => lang('all'),
			'link'   => egw::link('/index.php',$link_data),
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
				'selection' => html::select('selected',False,array(),True,' id="uiaccountsel_popup_selection" style="width: 100%;"',13),
				'remove' => html::submit_button('remove','remove',
					"removeSelectedOptions('$element_id'); return false;",True,' title="'.lang('Remove selected accounts').'"','delete'),
			));
		}
		$GLOBALS['egw']->template->pfp('out','accounts_list_t',True);

		common::egw_footer();
	}
}
