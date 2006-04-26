<?php
/**************************************************************************\
* eGroupWare - Adressbook - General user interface object                  *
* http://www.egroupware.org                                                *
* Written and (c) 2005/6 by Cornelius Weiss <egw@von-und-zu-weiss.de>      *
* and Ralf Becker <RalfBecker-AT-outdoor-training.de>                      *
* ------------------------------------------------------------------------ *
*  This program is free software; you can redistribute it and/or modify it *
*  under the terms of the GNU General Public License as published by the   *
*  Free Software Foundation; either version 2 of the License, or (at your  *
*  option) any later version.                                              *
\**************************************************************************/

/* $Id$ */

require_once(EGW_INCLUDE_ROOT.'/addressbook/inc/class.bocontacts.inc.php');

/**
 * General user interface object of the adressbook
 *
 * @package addressbook
 * @author Cornelius Weiss <egw@von-und-zu-weiss.de>
 * @author Ralf Becker <RalfBecker-AT-outdoor-training.de>
 * @copyright (c) 2005/6 by Cornelius Weiss <egw@von-und-zu-weiss.de> and Ralf Becker <RalfBecker-AT-outdoor-training.de>
 * @license http://opensource.org/licenses/gpl-license.php GPL - GNU General Public License
 */
class uicontacts extends bocontacts
{
	var $public_functions = array(
		'search'	=> True,
		'edit'		=> True,
		'view'		=> True,
		'index'     => True,
		'photo'		=> True,
	);
	var $prefs;
	/**
	 * var boolean $private_addressbook use a separate private addressbook (former private flag), for contacts not shareable via regular read acl
	 */
	var $private_addressbook = false;

	function uicontacts($contact_app='addressbook')
	{
		$this->bocontacts($contact_app);

		foreach(array(
			'tmpl'    => 'etemplate.etemplate',
		) as $my => $app_class)
		{
			list(,$class) = explode('.',$app_class);

			if (!is_object($GLOBALS['egw']->$class))
			{
				$GLOBALS['egw']->$class =& CreateObject($app_class);
			}
			$this->$my = &$GLOBALS['egw']->$class;
		}
		$this->prefs =& $GLOBALS['egw_info']['user']['preferences']['addressbook'];
		$this->private_addressbook = $this->contacts_repository == 'sql' && $this->prefs['private_addressbook'];

		// our javascript
		// to be moved in a seperate file if rewrite is over
		$GLOBALS['egw_info']['flags']['java_script'] .= $this->js();
	}
	
	/**
	 * List contacts of an addressbook
	 *
	 * @param array $content=null submitted content
	 * @param string $msg=null	message to show	
	 */
	function index($content=null,$msg=null)
	{
		if (is_array($content))
		{
			$msg = $content['msg'];

			if (isset($content['nm']['rows']['delete']))	// handle a single delete like delete with the checkboxes
			{
				list($id) = @each($content['nm']['rows']['delete']);
				$content['action'] = 'delete';
				$content['nm']['rows']['checked'] = array($id);
			}
			if ($content['action'] !== '')
			{
				if (!count($content['nm']['rows']['checked']) && !$content['use_all'])
				{
					$msg = lang('You need to select some contacts first');
				}
				else
				{
					if ($this->action($content['action'],$content['nm']['rows']['checked'],$content['use_all'],$success,$failed,$action_msg))
					{
						$msg .= lang('%1 contact(s) %2',$success,$action_msg);
					}
					else
					{
						$msg .= lang('%1 contact(s) %2, %3 failed because of insufficent rights !!!',$success,$action_msg,$failed);
					}
				}
			}
		}
		$content = array(
			'msg' => $msg ? $msg : $_GET['msg'],
		);
		$content['nm'] = $GLOBALS['egw']->session->appsession('index','addressbook');
		if (!is_array($content['nm']))
		{
			$content['nm'] = array(
				'get_rows'       =>	'addressbook.uicontacts.get_rows',	// I  method/callback to request the data for the rows eg. 'notes.bo.get_rows'
				'header_right'   =>	'addressbook.index.right',	// I  template to show right of the range-value, right-aligned (optional)
				'bottom_too'     => false,		// I  show the nextmatch-line (arrows, filters, search, ...) again after the rows
				'never_hide'     => True,		// I  never hide the nextmatch-line if less then maxmatch entrie
				'start'          =>	0,			// IO position in list
				'cat_id'         =>	0,			// IO category, if not 'no_cat' => True
				'search'         =>	'',			// IO search pattern
				'order'          =>	'n_family',	// IO name of the column to sort after (optional for the sortheaders)
				'sort'           =>	'ASC',		// IO direction of the sort: 'ASC' or 'DESC'
				'col_filter'     =>	array(),	// IO array of column-name value pairs (optional for the filterheaders)
				'filter_label'   =>	'Addressbook',	// I  label for filter    (optional)
				// filter needs to be type string as as int it matches the private addressbook too!
				'filter'         =>	(string) $GLOBALS['egw_info']['user']['account_id'],	// IO filter, if not 'no_filter' => True
				'filter_no_lang' => True,		// I  set no_lang for filter (=dont translate the options)
				'no_filter2'     => True,		// I  disable the 2. filter (params are the same as for filter)
				'filter2'        =>	'',			// IO filter2, if not 'no_filter2' => True
				'filter2_no_lang'=> True,		// I  set no_lang for filter2 (=dont translate the options)
				'lettersearch'   => true,
			);
			// use the state of the last session stored in the user prefs
			if (($state = @unserialize($this->prefs['index_state'])))
			{
				$content['nm'] = array_merge($content['nm'],$state);
			}
		}
		$sel_options = array(
			'filter' => $this->get_addressbooks(EGW_ACL_READ,lang('All')),
		);
		$sel_options['action'] = array(
			'delete' => lang('Delete'),
			'vcard'  => lang('Export as VCard'),
		)+$this->get_addressbooks(EGW_ACL_ADD);
		foreach($this->content_types as $tid => $data)
		{
			$sel_options['col_filter[tid]'][$tid] = $data['name'];
		}
		$this->tmpl->read('addressbook.index');
		return $this->tmpl->exec('addressbook.uicontacts.index',$content,$sel_options,$readonlys,$preserv);
	}
	
	/**
	 * apply an action to multiple contacts
	 *
	 * @param string/int $action 'delete', 'vcard', 'csv' or nummerical account_id to move contacts to that addessbook
	 * @param array $checked contact id's to use if !$use_all
	 * @param boolean $use_all if true use all contacts of the current selection (in the session)
	 * @param int &$success number of succeded actions
	 * @param int &$failed number of failed actions (not enought permissions)
	 * @param string &$action_msg translated verb for the actions, to be used in a message like %1 contacts 'deleted'
	 * @return boolean true if all actions succeded, false otherwise
	 */
	function action($action,$checked,$use_all,&$success,&$failed,&$action_msg)
	{
		//echo "<p>uicontacts::action('$action',".print_r($checked,true).','.(int)$use_all.",...)</p>\n";
		$success = $failed = 0;
		
		if ($use_all)
		{
			// get the whole selection
			$query = $GLOBALS['egw']->session->appsession('index','addressbook');
			$query['num_rows'] = -1;	// all
			$this->get_rows($query,$checked,$readonlys,true);	// true = only return the id's
		}
		foreach($checked as $id)
		{
			switch($action)
			{
				case 'delete':
					$action_msg = lang('deleted');
					if (($Ok = ($contact = $this->read($id)) && $this->check_perms(EGW_ACL_DELETE,$contact)))
					{
						if ($contact['owner'])	// regular contact
						{
							$Ok = $this->delete($id);
						}
						elseif (count($checked) == 1)	// delete single account --> redirect to admin
						{
							$GLOBALS['egw']->redirect_link('/index.php',array(
								'menuaction' => 'admin.uiaccounts.delete_user',
								'account_id' => $contact['account_id'],
							));
							// this does NOT return!
						}
						else	// no mass delete of accounts
						{
							$Ok = false;
						}
					}
					break;

				case 'vcard':
					$action_msg = lang('exported');
					$Ok = false;	// todo
					break;

				case 'csv':
					$action_msg = lang('exported');
					$Ok = false;	// todo
					break;

				default:	// move to an other addressbook
					if (!is_numeric($action) || !($this->grants[(int) $action] & EGW_ACL_EDIT))	// might be ADD in the future
					{
						return false;
					}
					$action_msg = lang('moved');
					if (($OK = ($contact = $this->read($id)) && $this->check_perms(EGW_ACL_DELETE,$contact)))
					{
						if (!$contact['owner'])		// no mass-change of accounts
						{
							$Ok = false;
						}
						elseif ($contact['owner'] != $action)	// no need to change
						{
							$contact['owner'] = (int) $action;
							$contact['private'] = 0;
							$Ok = $this->save($contact);
						}
					}
					break;
			}
			if ($Ok)
			{
				++$success;
			}
			else
			{
				++$failed;
			}
		}
		return !$failed;
	}

	/**
	 * rows callback for index nextmatch
	 *
	 * @internal 
	 * @param array &$query
	 * @param array &$rows returned rows/cups
	 * @param array &$readonlys eg. to disable buttons based on acl
	 * @param boolena $id_only=false if true only return (via $rows) an array of contact-ids, dont save state to session
	 * @return int total number of contacts matching the selection
	 */
	function get_rows(&$query,&$rows,&$readonlys,$id_only=false)
	{
		//echo "<p>uicontacts::get_rows(".print_r($query,true).")</p>\n";
		if (!$id_only)
		{
			$GLOBALS['egw']->session->appsession('index','addressbook',$query);
			// save the state of the index in the user prefs
			$state = serialize(array(
				'filter' => $query['filter'],
				'cat_id' => $query['cat_id'],
				'order'  => $query['order'],
				'sort'   => $query['sort'],
				'col_filter' => array('tid' => $query['col_filter']['tid']),
			));
			if ($state != $this->prefs['index_state'])
			{
				$GLOBALS['egw']->preferences->add('addressbook','index_state',$state);
				$GLOBALS['egw']->preferences->save_repository();
			}
		}
		if (isset($query['col_filter']['cat_id'])) unset($query['col_filter']['cat_id']);
		if ($query['cat_id'])
		{
			if (!is_object($GLOBALS['egw']->categories))
			{
				$GLOBALS['egw']->categories =& CreateObject('phpgwapi.categories');
			}
			$cats = $GLOBALS['egw']->categories->return_all_children((int)$query['cat_id']);
			$query['col_filter']['cat_id'] = count($cats) > 1 ? $cats : $query['cat_id'];
		}
		if ($query['filter'] !== '')	// not all addressbooks
		{
			$query['col_filter']['owner'] = (string) (int) $query['filter'];

			if ($this->private_addressbook)
			{
				$query['col_filter']['private'] = substr($query['filter'],-1) == 'p' ? 1 : 0;
			}
		}
		// translate the select order to the realy used over all 3 columns
		$sort = $query['sort'];
		switch($query['order'])
		{
			case 'org_name':
				$order = "org_name $sort,n_family $sort,n_given $sort";
				break;
			default:
				$query['order'] = 'n_family';
			case 'n_family':
				$order = "n_family $sort,n_given $sort,org_name $sort";
				break;
			case 'n_given':
				$order = "n_given $sort,n_family $sort,org_name $sort";
				break;
			case 'n_fileas':
				$order = 'n_fileas '.$sort;
				break;
		}
		if ($query['searchletter'])	// only show contacts which ordercriteria starts with the given letter
		{
			$query['col_filter'][] = $query['order'].' LIKE '.$GLOBALS['egw']->db->quote($query['searchletter'].'%');
		}
		else	// dont show contacts with empty order criteria
		{
			$query['col_filter'][] = $query['order']."!=''";
		}
		$rows = (array) parent::search($query['search'],$id_only,$order,'','%',false,'OR',array((int)$query['start'],(int) $query['num_rows']),$query['col_filter']);
		//echo "<p style='margin-top: 100px;'>".$this->somain->db->Query_ID->sql."</p>\n";

		if ($id_only) return $this->total;	// no need to set other fields or $readonlys

		if ($this->prefs['custom_colum'] != 'never' && $rows)	// do we need the custom fields
		{
			foreach($rows as $n => $val)
			{
				$ids[] = $val['id'];
			}
			$customfields = $this->read_customfields($ids);
		}
		$order = $query['order'];
		
		if (!$rows) $rows = array();
		
		$readonlys = array();
		$photos = $homeaddress = false;
		foreach($rows as $n => $val)
		{
			$row =& $rows[$n];
			
			$given = $row['n_given'] ? $row['n_given'] : ($row['n_prefix'] ? $row['n_prefix'] : '');

			switch($order)
			{
				case 'org_name':
					$row['line1'] = $row['org_name'];
					$row['line2'] = $row['n_family'].($given ? ', '.$given : '');
					break;
				case 'n_family':
					$row['line1'] = $row['n_family'].($given ? ', '.$given : '');
					$row['line2'] = $row['org_name'];
					break;
				case 'n_given':
					$row['line1'] = $given.' '.$row['n_family'];
					$row['line2'] = $row['org_name'];
					break;
				case 'n_fileas':
					list($row['line1'],$row['line2']) = explode(': ',$row['n_fileas']);
					break;
			}
			$this->type_icon($row['owner'],$row['private'],$row['tid'],$row['type'],$row['type_label']);
			
			static $tel2show = array('tel_work','tel_cell','tel_home');
			foreach($tel2show as $name)
			{
				
				$row[$name] .= ' '.($row['tel_prefer'] == $name ? '&#9829;' : '');		// .' ' to NOT remove the field
			}
			// allways show the prefered phone, if not already shown
			if (!in_array($row['tel_prefer'],$tel2show) && $row[$row['tel_prefer']])
			{
				$row['tel_prefered'] = $row[$row['tel_prefer']].' &#9829;';
			}
			foreach(array('email','email_home') as $name)
			{
				if ($row[$name])
				{
					$row[$name.'_link'] = $this->email2link($row[$name]);
					if ($GLOBALS['egw_info']['user']['apps']['felamimail'])
					{
						$row[$name.'_popup'] = '700x750';
					}
				}
				else
				{
					$row[$name] = ' ';	// to NOT remove the field
				}
			}
			$readonlys["delete[$row[id]]"] = !$this->check_perms(EGW_ACL_DELETE,$row);
			$readonlys["edit[$row[id]]"] = !$this->check_perms(EGW_ACL_EDIT,$row);
			
			if ($row['photo']) $photos = true;
			
			if (isset($customfields[$row['id']]))
			{
				foreach($this->customfields as $name => $data)
				{
					$row['customfields'][] = $customfields[$row['id']][$name];
				}
			}
			if ($this->prefs['home_column'] != 'never' && !$homeaddress)
			{
				foreach(array('adr_two_countryname','adr_two_locality','adr_two_postalcode','adr_two_street','adr_two_street2') as $name)
				{
					if ($row[$name]) $homeaddress = true;
				}
			}
		}
		// disable photo column, if view contains no photo(s)
		if (!$photos || $this->prefs['photo_column'] == 'never') $rows['no_photo'] = '1';
		// disable customfields column, if we have no customefield(s)
		if (!$customfields || $this->prefs['custom_column'] == 'never') $rows['no_customfields'] = '1';
		// disable homeaddress column, if we have no homeaddress(es)
		if ($homeaddress && !$this->prefs['home_column'] || $this->prefs['home_column'] == 'always') $rows['show_home'] = '1';

		$rows['order'] = $order;
		
		$rows['customfields'] = array_values($this->customfields);
		
		// full app-header with all search criteria for specially for the print
		$GLOBALS['egw_info']['flags']['app_header'] = lang('addressbook');
		if ($query['filter'] !== '')
		{ 
			$GLOBALS['egw_info']['flags']['app_header'] .= ': '.($query['filter'] == '0' ? lang('accounts') :
				($GLOBALS['egw']->accounts->get_type($query['filter']) == 'g' ? 
					lang('Group %1',$GLOBALS['egw']->accounts->id2name($query['filter'])) :
					$GLOBALS['egw']->common->grab_owner_name((int)$query['filter']).
						(substr($query['filter'],-1) == 'p' ? ' ('.lang('private').')' : '')));
		}
		if ($query['cat_id'])
		{
			$GLOBALS['egw_info']['flags']['app_header'] .= ': '.lang('Category').' '.$GLOBALS['egw']->categories->id2name($query['cat_id']);
		}
		if ($query['searchletter']) 
		{
			$order = $order == 'org_name' ? lang('company name') : ($order == 'n_given' ? lang('first name') : lang('last name'));
			$GLOBALS['egw_info']['flags']['app_header'] .= ' - '.lang("%1 starts with '%2'",$order,$query['searchletter']);
		}
		if ($query['search']) 
		{
			$GLOBALS['egw_info']['flags']['app_header'] .= ' - '.lang("Search for '%1'",$query['search']);
		}
		return $this->total;		
	}

	/**
	 * Get addressbook type icon from owner, private and tid
	 *
	 * @param int $owner user- or group-id or 0 for accounts
	 * @param boolean $private 
	 * @param string $tid 'n' for regular addressbook
	 * @param string &$icon icon-name
	 * @param string &$label translated label
	 */
	function type_icon($owner,$private,$tid,&$icon,&$label)
	{
		if (!$owner)
		{
			$icon = 'accounts';
			$label = lang('accounts');
		}
		elseif ($row['private'])
		{
			$icon = 'private';
			$label = lang('private');
		}
		elseif ($tid != 'n')
		{
			// ToDo Conny: tid-icons
			$icon = '';
			$label = $tid;
		}
		elseif ($GLOBALS['egw']->accounts->get_type($owner) == 'g')
		{
			$icon = 'group';
			$label = lang('group %1',$GLOBALS['egw']->accounts->id2name($owner));
		}
		else
		{
			$icon = 'personal';
			$label = $owner == $this->user ? lang('personal') : $GLOBALS['egw']->common->grab_owner_name($owner);
		}					
	}

	/**
	 * Get the availible addressbooks of the user
	 *
	 * @param int $required=EGW_ACL_READ required rights on the addressbook
	 * @param string $extra_label first label if given (already translated)
	 * @return array with owner => label pairs
	 */ 
	function get_addressbooks($required=EGW_ACL_READ,$extra_label=null)
	{
		//echo "uicontacts::get_addressbooks($required,$include_all) grants="; _debug_array($this->grants);

		$addressbooks = array();
		if ($extra_label) $addressbooks[''] = $extra_label;
		$addressbooks[$this->user] = lang('Personal');
		// add all group addressbooks the user has the necessary rights too
		foreach($this->grants as $uid => $rights)
		{
			if (($rights & $required) && $GLOBALS['egw']->accounts->get_type($uid) == 'g')
			{
				$addressbooks[$uid] = lang('Group %1',$GLOBALS['egw']->accounts->id2name($uid));
			}
		}
		if ($this->grants[0] & $required)
		{
			$addressbooks[0] = lang('Accounts');
		}
		// add all other user addressbooks the user has the necessary rights too
		foreach($this->grants as $uid => $rights)
		{
			if ($uid != $this->user && ($rights & $required) && $GLOBALS['egw']->accounts->get_type($uid) == 'u')
			{
				$addressbooks[$uid] = $GLOBALS['egw']->common->grab_owner_name($uid);
			}
		}
		if ($this->private_addressbook)
		{
			$addressbooks[$this->user.'p'] = lang('Private');
		}
		//_debug_array($addressbooks);
		return $addressbooks;
	}

	/**
	* Edit a contact 
	*
	* @param array $content=null submitted content
	* @param int $_GET['contact_id'] contact_id manly for popup use
	* @param bool $_GET['makecp'] ture if you want do copy the contact given by $_GET['contact_id']
	*/
	function edit($content=null)
	{
		if (!is_object($this->link))
		{
			if (!is_object($GLOBALS['egw']->link))
			{
				$GLOBALS['egw']->link =& CreateObject('phpgwapi.bolink');
			}
			$this->link =& $GLOBALS['egw']->link;
		}
		if (is_array($content))
		{
			list($button) = @each($content['button']);
			unset($content['button']);
			$content['private'] = (int) ($content['owner'] && substr($content['owner'],-1) == 'p');
			$content['owner'] = (string) (int) $content['owner'];

			switch($button)
			{
				case 'save':
				case 'apply':
					if ($content['delete_photo']) unset($content['jpegphoto']);
					if (is_array($content['upload_photo']) && !empty($content['upload_photo']['tmp_name']) && 
						$content['upload_photo']['tmp_name'] != 'none')
					{
						$content['jpegphoto'] = $this->resize_photo($content['upload_photo']);
					}
					$links = false;
					if (!$content['id'] && is_array($content['link_to']['to_id']))
					{
						$links = $content['link_to']['to_id'];
					}
					$this->save($content);
					// writing links for new entry, existing ones are handled by the widget itself
					if ($links && $content['id'])	
					{
						$this->link->link('addressbook',$content['id'],$links);
					}
					if ($button == 'save')
					{
						echo "<html><body><script>var referer = opener.location;opener.location.href = referer+(referer.search?'&':'?')+'msg=".
							addslashes(urlencode(lang('Contact saved')))."'; window.close();</script></body></html>\n";
						$GLOBALS['egw']->common->egw_exit();
					}
					$content['link_to']['to_id'] = $content['id'];
					$GLOBALS['egw_info']['flags']['java_script'] .= "<script LANGUAGE=\"JavaScript\">
						var referer = opener.location;
						opener.location.href = referer+(referer.search?'&':'?')+'msg=".addslashes(urlencode(lang('Contact saved')))."';</script>";
					break;
					
				case 'delete':
					if($this->delete($content));
					{
						echo "<html><body><script>var referer = opener.location; opener.location.href = referer+(referer.search?'&':'?')+'msg=".
							addslashes(urlencode(lang('Contact deleted !!!')))."';window.close();</script></body></html>\n";
						$GLOBALS['egw']->common->egw_exit();
					}
					break;
			}
			// type change
		}
		else
		{
			$content = array();
			$contact_id = $_GET['contact_id'] ? $_GET['contact_id'] : ((int)$_GET['account_id'] ? 'account:'.(int)$_GET['account_id'] : 0);
			$view = $_GET['view'];
			// new contact --> set some defaults
			if ($contact_id && is_array($content = $this->read($contact_id)))
			{
				$contact_id = $content['id'];	// it could have been: "account:$account_id"
			}
			else // not found
			{
				if (isset($_GET['owner']) && $_GET['owner'] !== '')
				{
					$content['owner'] = $_GET['owner'];
				}
				else
				{
					$state = $GLOBALS['egw']->session->appsession('index','addressbook');
					$content['owner'] = $state['filter'];
					unset($state);
				}
				$content['private'] = (int) ($content['owner'] && substr($content['owner'],-1) == 'p');
				if (!($this->grants[$content['owner'] = (string) (int) $content['owner']] & EGW_ACL_ADD))
				{
					$content['owner'] = (string) $this->user;
				}
				$new_type = array_keys($this->content_types);
				$content['tid'] = $_GET['typeid'] ? $_GET['typeid'] : $new_type[0];
				foreach($this->get_contact_columns() as $field => $data)
				{
					if ($_GET['presets'][$field]) $content[$field] = $_GET['presets'][$field];
				}
				$content['creator'] = $this->user;
				$content['created'] = $this->now_su;
			}
			
			if($content && $_GET['makecp'])	// copy the contact
			{
				$content['link_to']['to_id'] = 0;
				$this->link->link('addressbook',$content['link_to']['to_id'],'addressbook',$content['id'],
					lang('Copied by %1, from record #%2.',$GLOBALS['egw']->common->display_fullname('',
					$GLOBALS['egw_info']['user']['account_firstname'],$GLOBALS['egw_info']['user']['account_lastname']),
					$content['id']));
				unset($content['id']);
				$content['creator'] = $this->user;
				$content['created'] = $this->now_su;
			}
			else
			{
				$content['link_to']['to_id'] = $contact_id;
			}
		}
		//_debug_array($content);
		$readonlys['button[delete]'] = !$this->check_perms(EGW_ACL_DELETE,$content);
		$readonlys['button[copy]'] = $readonlys['button[edit]'] = $readonlys['button[vcard]'] = true;

		$sel_options['fileas_type'] = $this->fileas_options($content);
		$sel_options['owner'] = $this->get_addressbooks(EGW_ACL_ADD);
		if ((string) $content['owner'] !== '')
		{
			if (!isset($sel_options['owner'][(int)$content['owner']]))
			{
				$sel_options['owner'][(int)$content['owner']] = !$content['owner'] ? lang('Accounts') :
					$GLOBALS['egw']->common->grab_owner_name($content['owner']);
			}
			$readonlys['owner'] = !$content['owner'] || 		// dont allow to move accounts, as this mean deleting the user incl. all content he owns
				!$this->check_perms(EGW_ACL_DELETE,$content);	// you need delete rights to move a contact into an other addressbook
		}
		for($i = -23; $i<=23; $i++) $tz[$i] = ($i > 0 ? '+' : '').$i;
		$sel_options['tz'] = $tz;
		$content['tz'] = $content['tz'] ? $content['tz'] : 0;
		foreach($this->content_types as $type => $data) $sel_options['tid'][$type] = $data['name'];

		foreach($GLOBALS['egw']->acl->get_all_location_rights($GLOBALS['egw']->acl->account_id,'addressbook',true) as $id => $right)
		{
			if($id < 0) $sel_options['published_groups'][$id] = $GLOBALS['egw']->accounts->id2name($id);
		}
		$content['typegfx'] = $GLOBALS['egw']->html->image('addressbook',$this->content_types[$content['tid']]['options']['icon'],'',' width="16px" height="16px"');
		$content['link_to'] = array(
			'to_app' => 'addressbook',
			'to_id'  => $content['link_to']['to_id'],
		);
		$content['photo'] = $this->photo_src($content['id'],$content['jpegphoto'],'template');

		if ($content['private']) $content['owner'] .= 'p';

		$GLOBALS['egw_info']['flags']['include_xajax'] = true;

		$this->tmpl->read($this->content_types[$content['tid']]['options']['template']);
		return $this->tmpl->exec('addressbook.uicontacts.edit',$content,$sel_options,$readonlys,$content, 2);
	}
	
	function ajax_setFileasOptions($n_prefix,$n_given,$n_middle,$n_family,$n_suffix,$org_name)
	{
		$names = array(
			'n_prefix' => $n_prefix,
			'n_given'  => $n_given,
			'n_middle' => $n_middle,
			'n_family' => $n_family,
			'n_suffix' => $n_suffix,
			'org_name' => $org_name,
		);
		$response =& new xajaxResponse();
		$response->addScript("setOptions('".addslashes(implode("\b",$this->fileas_options($names)))."');");

		return $response->getXML();
	}
	
	/**
	 * resizes the uploaded photo to 60*80 pixel and returns it
	 *
	 * @param array $file info uploaded file
	 * @return string with resized jpeg photo
	 */
	function resize_photo($file)
	{
		switch($file['type'])
		{
			case 'image/gif':
				$upload = imagecreatefromgif($file['tmp_name']);
				break;
			case 'image/jpeg':
			case 'image/pjpeg':
				$upload = imagecreatefromjpeg($file['tmp_name']);
				break;
			case 'image/png':
			case 'image/x-png':
				$upload = imagecreatefrompng($file['tmp_name']);
				break;
			default:
				return null;
		}
		if (!$upload) return null;

		list($src_w,$src_h) = getimagesize($file['tmp_name']);
		
		// scale the image to a width of 60 and a height according to the proportion of the source image
		$photo = imagecreatetruecolor($dst_w = 60,$dst_h = round($src_h * 60 / $src_w));
		imagecopyresized($photo,$upload,0,0,0,0,$dst_w,$dst_h,$src_w,$src_h);
		//echo "<p>imagecopyresized(\$photo,\$upload,0,0,0,0,$dst_w,$dst_h,$src_w,$src_h);</p>\n";

		ob_start();
		imagejpeg($photo,'',90);
		$jpeg = ob_get_contents();
		ob_end_clean();

		imagedestroy($photo);
		imagedestroy($upload);
		
		return $jpeg;
	}		
	
	function view($content=null)
	{
		if(is_array($content))
		{
			list($button) = each($content['button']);
			switch ($button)
			{
				case 'vcard':
					$GLOBALS['egw']->redirect_link('/index.php','menuaction=addressbook.uivcard.out&ab_id=' .$content['id']);

				case 'cancel':
					$GLOBALS['egw']->redirect_link('/index.php','menuaction=addressbook.uicontacts.index');

				case 'delete':
					$GLOBALS['egw']->redirect_link('/index.php',array(
						'menuaction' => 'addressbook.uicontacts.index',
						'msg' => $this->delete($content) ? lang('Something went wrong by deleting this contact') : lang('Contact deleted !!!'),
					));
			}
		}
		else
		{
			if(!$_GET['contact_id'] || !is_array($content = $this->read($_GET['contact_id'])))
			{
				$GLOBALS['egw']->redirect_link('/index.php',array(
					'menuaction' => 'addressbook.uicontacts.index',
					'msg' => $content,
				));
			}
		}
		foreach((array)$content as $key => $val)
		{
			$readonlys[$key] = true;
			if (in_array($key,array('tel_home','tel_work','tel_cell')))
			{
				$readonlys[$key.'2'] = true;
				$content[$key.'2'] = $content[$key];
			}				
		}
		$content['view'] = true;
		$content['link_to'] = array(
			'to_app' => 'addressbook',
			'to_id'  => $content['id'],
		);
		$readonlys['link_to'] = $readonlys['customfields'] = true;
		$readonlys['button[save]'] = $readonlys['button[apply]'] = $readonlys['change_photo'] = true;
		$readonlys['button[delete]'] = !$this->check_perms(EGW_ACL_DELETE,$content);
		$readonlys['button[edit]'] = !$this->check_perms(EGW_ACL_EDIT,$content);
		
		$sel_options['fileas_type'][$content['fileas_type']] = $this->fileas($content);
		$sel_options['owner'] = $this->get_addressbooks();
		for($i = -23; $i<=23; $i++) $tz[$i] = ($i > 0 ? '+' : '').$i;
		$sel_options['tz'] = $tz;
		$content['tz'] = $content['tz'] ? $content['tz'] : 0;
		foreach($this->content_types as $type => $data) $sel_options['tid'][$type] = $data['name'];
		foreach(explode(',',$content['published_groups']) as $id)
		{
			$sel_options['published_groups'][$id] = $GLOBALS['egw']->accounts->id2name($id);
		}
		$content['typegfx'] = $GLOBALS['egw']->html->image('addressbook',$this->content_types[$content['tid']]['options']['icon'],'',' width="16px" height="16px"');
		$this->tmpl->read($this->content_types[$content['tid']]['options']['template']);
		foreach(array('email','email_home','url') as $name)
		{
			if ($content[$name] )
			{
				$url = $name == 'url' ? $content[$name] : $this->email2link($content[$name]);
				if (!is_array($url))
				{
					$this->tmpl->set_cell_attribute($name,'size','b,,1');
				}
				elseif ($url)
				{
					$content[$name.'_link'] = $url;
					$this->tmpl->set_cell_attribute($name,'size','b,@'.$name.'_link,,,_blank'.
						($GLOBALS['egw_info']['user']['apps']['felamimail']?',700x750':''));
				}
				$this->tmpl->set_cell_attribute($name,'type','label');
				$this->tmpl->set_cell_attribute($name,'no_lang',true);
			}
		}
		$this->tmpl->exec('addressbook.uicontacts.view',$content,$sel_options,$readonlys,array('id' => $content['id']));
		
		$GLOBALS['egw']->hooks->process(array(
			'location' => 'addressbook_view',
			'ab_id'    => $content['id']
		));
	}
	
	/**
	 * convert email-address in compose link
	 *
	 * @param string $email email-addresse
	 * @return array/string array with get-params or mailto:$email, or '' or no mail addresse
	 */
	function email2link($email)
	{
		if (!strstr($email,'@')) return '';

		if($GLOBALS['egw_info']['user']['apps']['felamimail'])
		{
			return array(
				'menuaction' => 'felamimail.uicompose.compose',
				'send_to'    => base64_encode($email)
			);
		}
		if($GLOBALS['egw_info']['user']['apps']['email'])
		{
			return array(
				'menuaction' => 'email.uicompose.compose',
				'to' => $email,
			);
		}
		return 'mailto:' . $email;
	}

	function search($content='')
	{
		$GLOBALS['egw_info']['flags']['app_header'] = lang('Addressbook'). ' - '. lang('Advanced search');
		if(!($GLOBALS['egw_info']['server']['contact_repository'] == 'sql' || !isset($GLOBALS['egw_info']['server']['contact_repository'])))
		{
			$GLOBALS['egw']->common->egw_header();
			echo parse_navbar();
			echo '<p> Advanced Search is not supported for ldap storage yet. Sorry! </p>';
			$GLOBALS['egw']->common->egw_exit();
		}
		
		$content['advs']['msg'] = lang('Please select only one category');
		// This is no fun yet, as we dont have a sortorder in prefs now, AND as we are not able to sort within cf.
// 		$prefs = $GLOBALS['egw']->preferences->read_repository();
// 		foreach($prefs['addressbook'] as $key => $value)
// 		{
// 			if($value == 'addressbook_on') $content['advs']['colums_to_present'][$key] = lang($key);
// 		}
	
// 		echo 'addressbook.uicontacts.search->content:'; _debug_array($content);
		$content['advs']['hidebuttons'] = true;
		$content['advs']['input_template'] = 'addressbook.edit';
		$content['advs']['search_method'] = 'addressbook.bocontacts.search';
		$content['advs']['search_class_constructor'] = $contact_app;
		$content['advs']['colums_to_present'] = array(
			'id' => 'id',
			'n_given' => lang('first name'),
			'n_family' => lang('last name'),
			'email_home' => lang('home email'),
			'email' => lang('work email'),
			'tel_home' => lang('tel home'),
		);
		
		$content['advs']['row_actions'] = array(
			'view' => array(
				'type' => 'button',
				'options' => array(
					'size' => 'view',
					'onclick' => "location.href='".$GLOBALS['egw']->link('/index.php',
					array('menuaction' => 'addressbook.uicontacts.view',)).'&contact_id=$row_cont[id]\';return false;',
			)),
			'edit' => array(
				'type' => 'button',
				'options' => array(
					'size' => 'edit',
					'onclick' => 'window.open(\''.
					$GLOBALS['egw']->link('/index.php?menuaction=addressbook.uicontacts.edit').
					'&contact_id=$row_cont[id] \',\'\',\'dependent=yes,width=850,height=440,location=no,menubar=no,toolbar=no,scrollbars=yes,status=yes\');
					return false;',
				)),
			'delete' => array(
				'type' => 'button',
				'method' => 'addressbook.bocontacts.delete',
				'options' => array(
					'size' => 'delete',
					'onclick' => 'if(!confirm(\''. lang('Do your really want to delete this contact?'). '\')) return false;',
				)),
		);
/*		$content['advs']['actions']['email'] = array(
				'type' => 'button',
				'options' => array(
					'label' => lang('email'),
					'no_lang' => true,
		));
		$content['advs']['actions']['export'] = array(
				'type' => 'button',
				'options' => array(
					'label' => lang('export'),
					'no_lang' => true,
		));*/
		$content['advs']['actions']['delete'] = array(
				'type' => 'button',
				'method' => 'addressbook.bocontacts.delete',
				'options' => array(
					'label'  => lang('delete'),
					'no_lang' => true,
					'onclick' => 'if(!confirm(\''. lang('WARNING: All contacts found will be deleted!'). '\')) return false;',
		));
		
		for($i = -23; $i<=23; $i++) $tz[$i] = ($i > 0 ? '+' : '').$i;
		$sel_options['tz'] = $tz + array('' => lang('doesn\'t matter'));
		$sel_options['tid'][] = lang('all');
		//foreach($this->content_types as $type => $data) $sel_options['tid'][$type] = $data['name'];
		
		// some changes for the new addressbook
		$sel_options['owner'] = $this->get_addressbooks(EGW_ACL_READ,lang('all'));
		$readonlys['change_photo'] = true;
		
		$this->tmpl->read('addressbook.search');
		return $this->tmpl->exec('addressbook.uicontacts.search',$content,$sel_options,$readonlys,$preserv);
	}
	
	function photo()
	{
		ob_start();
		$contact_id = isset($_GET['contact_id']) ? $_GET['contact_id'] : 
			(isset($_GET['account_id']) ? 'account:'.$_GET['account_id'] : 0);
		
		if (substr($contact_id,0,8) == 'account:')
		{
			$contact_id = $GLOBALS['egw']->accounts->id2name(substr($contact_id,8),'person_id');
		}
		if (!($contact = $this->read($contact_id)) || !$contact['jpegphoto'])
		{
			$GLOBALS['egw']->redirect($GLOBALS['egw']->common->image('addressbook','photo'));
		}
		if (!ob_get_contents())
		{
			header('Content-type: image/jpeg');
			header('Content-length: '.(extension_loaded(mbstring) ? mb_strlen($contact['jpeg_photo'],'ascii') : strlen($contact['jpeg_photo'])));
			echo $contact['jpegphoto'];
			exit;
		}
	}
	
	function js()
	{
		return '<script LANGUAGE="JavaScript">
		
		function showphones(form) 
		{
			if (form) {
				copyvalues(form,"tel_home","tel_home2");
				copyvalues(form,"tel_work","tel_work2");
				copyvalues(form,"tel_cell","tel_cell2");
			}
		}
		
		function hidephones(form) 
		{
			if (form) {
				copyvalues(form,"tel_home2","tel_home");
				copyvalues(form,"tel_work2","tel_work");
				copyvalues(form,"tel_cell2","tel_cell");
			}
		}
		
		function copyvalues(form,src,dst){
			var srcelement = getElement(form,src);  //ById("exec["+src+"]");
			var dstelement = getElement(form,dst);  //ById("exec["+dst+"]");
			if (srcelement && dstelement) {
				dstelement.value = srcelement.value;
			}
		}
		
		function getElement(form,pattern){
			for (i = 0; i < form.length; i++){
				if(form.elements[i].name){
					var found = form.elements[i].name.search("\\\\["+pattern+"\\\\]");
					if (found != -1){
						return form.elements[i];
					}
				}
			}
		}
		
		function setFileasOptions(input)
		{
			var prefix = document.getElementById("exec[n_prefix]").value;
			var given  = document.getElementById("exec[n_given]").value;
			var middle = document.getElementById("exec[n_middle]").value;
			var family = document.getElementById("exec[n_family]").value;
			var suffix = document.getElementById("exec[n_suffix]").value;
			var org    = document.getElementById("exec[org_name]").value;
			
			xajax_doXMLHTTP("addressbook.uicontacts.ajax_setFileasOptions",prefix,given,middle,family,suffix,org);
		}
		
		function setOptions(options_str)
		{
			var options = options_str.split("\\\\b");
			var selbox = document.getElementById("exec[fileas_type]");
			var i;
			for (i=0; i < options.length; i++)
			{
				selbox.options[i].text = options[i];
			}
		}
	
		</script>';
	}
}
