<?php
  /**************************************************************************\
  * eGroupWare - Addressbook                                                 *
  * http://www.egroupware.org                                                *
  * Written by Joseph Engo <jengo@phpgroupware.org                           *
  *  and Miles Lott <milos@groupwhere.org>                                   *
  * --------------------------------------------                             *
  *  This program is free software; you can redistribute it and/or modify it *
  *  under the terms of the GNU General Public License as published by the   *
  *  Free Software Foundation; either version 2 of the License, or (at your  *
  *  option) any later version.                                              *
  \**************************************************************************/

  /* $Id$ */

	class boaddressbook
	{
		var $public_functions = array(
			'read_entries'    => True,
			'read_entry'      => True,
			'read_last_entry' => True,
			'add_entry'       => True,
			'add_vcard'       => True,
			'add_email'       => True,
			'update_entry'    => True,
			'delete_entry'    => True,
		);

		var $xml_functions  = array();
		var $xmlrpc_methods = array();
		var $soap_functions = array(
			'read_entries' => array(
				'in'  => array('int','int','struct','string','int'),
				'out' => array('array')
			),
			'read_entry' => array(
				'in'  => array('int','struct'),
				'out' => array('array')
			),
			'read_last_entry' => array(
				'in'  => array('struct'),
				'out' => array('array')
			),
			'add_entry' => array(
				'in'  => array('int','struct'),
				'out' => array()
			),
			'update_entry' => array(
				'in'  => array('int','struct'),
				'out' => array()
			),
			'categories' => array(
				'in'  => array('bool'),
				'out' => array('struct')
			),
			'customfields' => array(
				'in' => array('array'),
				'out'=> array('struct')
			),
		);

		var $debug = False;

		var $so;
		var $start;
		var $limit;
		var $query;
		var $sort;
		var $order;
		var $filter;
		var $cat_id;
		var $total;
		var $contact_cache = array();

		var $use_session = False;

		function boaddressbook($session=False)
		{
			$this->so = CreateObject('addressbook.soaddressbook');
			// make some fields of the contacts-object availible
			$this->grants = &$this->so->grants;
			$this->stock_contact_fields = &$this->so->contacts->stock_contact_fields;
			$this->tel_types = &$this->so->contacts->tel_types;
			$this->email_types = &$this->so->contacts->email_types;
			$this->adr_types = &$this->so->contacts->adr_types;

			if($session)
			{
				$this->read_sessiondata();
				$this->use_session = True;
			}
			// are we called via xmlrpc?
			$this->xmlrpc = is_object($GLOBALS['server']) && $GLOBALS['server']->last_method;

			/* _debug_array($_POST); */
			$_start   = get_var('start',array('POST','GET'));
			$_query   = get_var('query',array('POST','GET'),'_UNSET_');
			$_cquery  = get_var('cquery', array('GET','POST'),'_UNSET_');
			$_sort    = get_var('sort',array('POST','GET'));
			$_order   = get_var('order',array('POST','GET'));
			$_filter  = get_var('filter',array('POST','GET'));
//			$_cat_id  = get_var('cat_id',array('POST','GET'));
			$_fcat_id = get_var('fcat_id',array('POST','GET'));
			$_typeid  = get_var('typeid',array('POST','GET'),'_UNSET_');

			if(!empty($_start) || ($_start == '0') || ($_start == 0))
			{
				if($this->debug) { echo '<br>overriding $start: "' . $this->start . '" now "' . $_start . '"'; }
				$this->start = $_start;
			}
			if($_limit)
			{
				$this->limit  = $_limit;
			}

			if($_query != '_UNSET_')
			{
				$this->query = $_query;
			}
			if($_cquery != '_UNSET_')
			{
				$this->cquery = $_cquery;
			}

			if($_typeid != '_UNSET_')
			{
				$this->typeid = $_typeid;
			}
			if(!@in_array($this->typeid,array('n','c')))
			{
				$this->typeid = 'n';
			}

			if(isset($_POST['fcat_id']) || isset($_POST['fcat_id']))
			{
				$this->cat_id = $_fcat_id;
			}
			else
			{
				$this->cat_id = -1;
			}

			/*
			if(isset($_POST['typeid']) || isset($_POST['typeid']))
			{
				$this->typeid = $typeid;
			}
			else
			{
				$this->typeid = 'n';
			}
			*/

			if(isset($_sort)   && !empty($_sort))
			{
				if($this->debug) { echo '<br>overriding $sort: "' . $this->sort . '" now "' . $_sort . '"'; }
				$this->sort   = $_sort;
			}

			if(isset($_order)  && !empty($_order))
			{
				if($this->debug) { echo '<br>overriding $order: "' . $this->order . '" now "' . $_order . '"'; }
				$this->order  = $_order;
			}

			if(isset($_filter) && !empty($_filter))
			{
				if($this->debug) { echo '<br>overriding $filter: "' . $this->filter . '" now "' . $_filter . '"'; }
				$this->filter = $_filter;
			}

			if($this->debug) { $this->_debug_sqsof(); }
		}

		function _debug_sqsof()
		{
			$data = array(
				'start'  => $this->start,
				'limit'  => $this->limit,
				'query'  => $this->query,
				'cquery' => $this->cquery,
				'sort'   => $this->sort,
				'order'  => $this->order,
				'filter' => $this->filter,
				'cat_id' => $this->cat_id,
				'typeid' => $this->typeid
			);
			echo '<br>BO:';
			_debug_array($data);
		}

		function save_sessiondata($data)
		{
			if($this->use_session)
			{
				if($this->debug) { echo '<br>Save:'; _debug_array($data); }
				$GLOBALS['phpgw']->session->appsession('session_data','addressbook',$data);
			}
		}

		function read_sessiondata()
		{
			$data = $GLOBALS['phpgw']->session->appsession('session_data','addressbook');
			if($this->debug) { echo '<br>Read:'; _debug_array($data); }

			$this->start  = $data['start'];
			$this->limit  = $data['limit'];
			$this->query  = $data['query'];
			$this->cquery = $data['cquery'];
			$this->sort   = $data['sort'];
			$this->order  = $data['order'];
			$this->filter = $data['filter'];
			$this->cat_id = $data['cat_id'];
			$this->typeid = $data['typeid'];
			if($this->debug) { echo '<br>read_sessiondata();'; $this->_debug_sqsof(); }
		}

		function strip_html($dirty='')
		{
			if($dirty == '')
			{
				$dirty = array();
			}
			for($i=0;$i<count($dirty);$i++)
			{
				if(@is_array($dirty[$i]))
				{
					foreach($dirty[$i] as $name => $value)
					{
						$cleaned[$i][$name] = $GLOBALS['phpgw']->strip_html($dirty[$i][$name]);
					}
				}
				else
				{
					$cleaned[$i] == $GLOBALS['phpgw']->strip_html($dirty[$i]);
				}
			}
			return $cleaned;
		}

		// return array with all addressbook categories (for xmlrpc)
		function categories($complete = False)
		{
			return $this->xmlrpc ? $GLOBALS['server']->categories($complete) : False;
		}

		// return array with all addressbook customfields (for xmlrpc)
		function customfields($new_fields=False)
		{
			$fields = CreateObject('addressbook.uifields',True);	// no extra bo-class

			if (is_array($new_fields) && count($new_fields))
			{
				if (!$GLOBALS['phpgw_info']['user']['apps']['admin'])
				{
					$GLOBALS['server']->xmlrpc_error($GLOBALS['xmlrpcerr']['no_access'],$GLOBALS['xmlrpcstr']['no_access']);
				}
				foreach($new_fields as $new)
				{
					$fields->save_custom_field('',$new);
				}
			}
			$customfields = array();
			foreach($fields->read_custom_fields() as $data)
			{
				$customfields[$data['name']] = $data['title'];
			}
			if ($this->xmlrpc && !isset($customfields['freebusy_url']))
			{
				$fields->save_custom_field('','freebusy URL');
				$customfields['freebusy_url'] = 'freebusy URL';
			}
			return $customfields;
		}

		// translate array of internal datas to xmlrpc, eg. format bday as iso8601
		function data2xmlrpc($datas)
		{
			if (is_array($datas))
			{
				foreach($datas as $n => $data)
				{
					// translate birthday to a iso8601 date
					if (isset($data['bday']))
					{
						if (strlen($data['bday']) > 2)
						{
							list($m,$d,$y) = explode('/',$data['bday']);
						}
						else
						{
							$y = $m = $d = 0;
						}
						$datas[$n]['bday'] = $GLOBALS['server']->date2iso8601(array('year'=>$y,'month'=>$m,'mday'=>$d));
					}
					// translate modification time
					if (isset($data['last_mod']))
					{
						$datas[$n]['last_mod'] = $GLOBALS['server']->date2iso8601($data['last_mod']);
					}
					// translate categories-id-list to array with id-name pairs
					if (isset($data['cat_id']))
					{
						$datas[$n]['cat_id'] = $GLOBALS['server']->cats2xmlrpc(explode(',',$data['cat_id']));
					}
				}
			}
			return $datas;
		}

		// retranslate from xmlrpc / iso8601 to internal format
		function xmlrpc2data($data)
		{
			if (isset($data['bday']))
			{
				$arr = $GLOBALS['server']->iso86012date($data['bday']);
				$data['bday'] = $arr['year'] && $arr['month'] && $arr['mday'] ? sprintf('%d/%02d/%04d',$arr['month'],$arr['mday'],$arr['year']) : '';
			}
			if (isset($data['last_mod']))
			{
				$data['last_mod']  = $GLOBALS['server']->iso86012date($data['last_mod'],True);
			}
			if (isset($data['cat_id']))
			{
				$cats = $GLOBALS['server']->xmlrpc2cats($data['cat_id']);
				$data['cat_id'] = count($cats) > 1 ? ','.implode(',',$cats).',' : (int)$cats[0];
			}
			return $data;
		}

		// return a pseudo addressbook-entry for a user
		function user_pseudo_entry($account)
		{
			static $prefs=False;
			if (!is_object($prefs))
			{
				$prefs = CreateObject('phpgwapi.preferences');	// wie need a new copy, as wie change the user
			}
			if (!is_array($account))
			{
				$GLOBALS['phpgw']->accounts->account_id = $account;
				$account = $GLOBALS['phpgw']->accounts->read_repository();
			}
			$prefs->account_id = $account['account_id'];
			$prefs->read_repository();
			$freebusy_url = $GLOBALS['phpgw_info']['server']['webserver_url'].'/calendar/freebusy.php?user='.$account['account_lid'];
			if ($freebusy_url[0] == '/')
			{
				$freebusy_url = ($_SERVER['HTTPS'] ? 'https://' : 'http://').$_SERVER['HTTP_HOST'].$freebusy_url;
			}
			$firstname = $account['account_firstname'] ? $account['account_firstname'] : $account['firstname'];
			$lastname  = $account['account_lastname'] ? $account['account_lastname'] : $account['lastname'];
			$ret = array(
				'n_family' => $lastname,
				'n_given'  => $firstname,
				'fn'       => $GLOBALS['phpgw']->common->display_fullname($account['account_lid'],$firstname,$lastname),
				'email'    => $prefs->email_address($account['account_id']),
				'freebusy_url' => $freebusy_url,
				'rights'   => PHPGW_ACL_READ,	// readonly access
				'id'       => -$account['account_id'],
				'tid'      => 'p',	// Profil
			);
			//echo "<p>user_pseudo_entry(".print_r($account,True).")=".print_r($ret,True)."</p>";
			return $ret;
		}

		function get_users($type='all')
		{
			$users = array();
			switch ($type)
			{
				case 'all':			// all
					$accounts = $GLOBALS['phpgw']->accounts->get_list('accounts');
					break;
				case 'calendar':	// Calendar users
					$accounts = $GLOBALS['phpgw']->acl->get_ids_for_location('run',1,'calendar');
					break;
				case 'groupmates':	// Groupmates
					$accounts = array();
					foreach($GLOBALS['phpgw']->accounts->membership() as $group)
					{
						$accounts[] = $group['account_id'];
					}
					break;
			}
			foreach($accounts as $key => $account)
			{
				if ($type == 'calendar' && $GLOBALS['phpgw']->accounts->get_type($account) == 'g' || $type == 'groupmates')
				{
					// $account is a group
					unset($accounts[$key]);
					foreach($GLOBALS['phpgw']->accounts->member($account) as $member)
					{
						$accounts[] = $member['account_id'];
					}
				}
			}
			if ($type != 'all')
			{
				$accounts = array_unique($accounts);	// remove doubles
			}
			$prefs = CreateObject('phpgwapi.preferences');	// wie need a new copy, as wie change the user
			foreach($accounts as $account)
			{
				$users[] = $this->user_pseudo_entry($account);
			}
			return $users;
		}

		function read_entries($data)
		{
			if ($this->xmlrpc && !isset($data['fields']))
			{
				$data['fields'] = array_keys(array_merge($this->so->contacts->non_contact_fields,$this->so->contacts->stock_contact_fields,$this->customfields()));
			}
			$entries = $this->so->read_entries($data);
			$this->total = $this->so->contacts->total_records;
			if (!is_array($entries))
			{
				$entries = array();
			}
			$entries = $this->strip_html($entries);

			// evtl. get uses as read-only addressbook entries, just with Name, Firstname, Email
			if (@$data['include_users'])
			{
				$entries = array_merge($entries,$this->get_users($data['include_users']));
			}
			if ($this->xmlrpc)
			{
				$entries = $this->data2xmlrpc($entries);
			}
			if($this->debug) { echo '<br>Total records="' . $this->total . '"'; }
			return $entries;
		}

		function read_entry($data)
		{
			if ($this->xmlrpc && !isset($data['fields']))
			{
				$data['fields'] = array_keys(array_merge($this->so->contacts->non_contact_fields,$this->so->contacts->stock_contact_fields,$this->customfields()));
			}
			if ($data['id'] < 0)
			{
				$entry = array($this->user_pseudo_entry(-$data['id']));
				if ($this->xmlrpc)
				{
					$entry = $this->data2xmlrpc($entry);
				}
				return $entry;
			}
			if($this->check_perms($data,PHPGW_ACL_READ))
			{
				$entry = $this->so->read_entry($data['id'],$data['fields']);
				$entry = $this->strip_html($entry);
				if ($this->xmlrpc)
				{
					$entry = $this->data2xmlrpc($entry);
				}
				return $entry;
			}
			if ($this->xmlrpc)
			{
				$GLOBALS['server']->xmlrpc_error($GLOBALS['xmlrpcerr']['no_access'],$GLOBALS['xmlrpcstr']['no_access']);
			}
			return False;
		}

		function read_last_entry($fields)
		{
			if($this->check_perms($fields,PHPGW_ACL_READ))
			{
				$entry = $this->so->read_last_entry($fields);
				$entry = $this->strip_html($entry);
				if ($this->xmlrpc)
				{
					$entry = $this->data2xmlrpc($entry);
				}
				return $entry;
			}
			if ($this->xmlrpc)
			{
				$GLOBALS['server']->xmlrpc_error($GLOBALS['xmlrpcerr']['no_access'],$GLOBALS['xmlrpcstr']['no_access']);
			}
			return False;
		}

		function add_vcard($uploadedfile='')
		{
			if($uploadedfile == 'none' || $uploadedfile == '' || substr($uploadedfile['name'],-4) != '.vcf')
			{
				return False;
			}
			else
			{
				$filename = $uploadedfile['tmp_name'];

				$vcard = CreateObject('phpgwapi.vcard');
				$entry = $vcard->in_file($filename);
				/* _debug_array($entry);exit; */
				$entry['owner'] = (int)$GLOBALS['phpgw_info']['user']['account_id'];
				$entry['access'] = 'private';
				$entry['tid'] = 'n';
				/* _debug_array($entry);exit; */

				$this->so->add_entry($entry);
				$ab_id = $this->get_lastid();

				return(int)$ab_id;
			}
		}

		function add_email()
		{
			global $name,$referer;

			$named = explode(' ', $name);
			for($i=count($named);$i>=0;$i--) { $names[$i] = $named[$i]; }
			if($names[2])
			{
				$fields['n_given']  = $names[0];
				$fields['n_middle'] = $names[1];
				$fields['n_family'] = $names[2];
			}
			else
			{
				$fields['n_given']  = $names[0];
				$fields['n_family'] = $names[1];
			}
			$fields['email']    = $add_email;
			$referer = urlencode($referer);

			$this->so->add_entry($GLOBALS['phpgw_info']['user']['account_id'],$fields,'private','','n');
			$ab_id = $this->get_lastid();

			Header('Location: '
				. $GLOBALS['phpgw']->link('/index.php',"menuaction=addressbook.uiaddressbook.view&ab_id=$ab_id&referer=$referer"));
		}

		function add_entry($fields)
		{
			// setting some defaults, if not set eg. via xmlrpc
			$fields['tid'] = trim($fields['tid']);
			if(empty($fields['tid']))
			{
				$fields['tid'] = 'n';
			}
			if(!@$fields['owner'])
			{
				$fields['owner'] = (int)$GLOBALS['phpgw_info']['user']['account_id'];
			}
			if(empty($fields['access']))
			{
				$fields['access'] = 'public';
			}
			if ($this->xmlrpc)
			{
				$fields = $this->xmlrpc2data($fields);
			}
			$id = $this->so->add_entry($fields);

			if ($this->xmlrpc && !$id)
			{
				$GLOBALS['server']->xmlrpc_error($GLOBALS['xmlrpcerr']['no_access'],$GLOBALS['xmlrpcstr']['no_access']);
			}
			return $id;
		}

		function get_lastid()
		{
			return $this->so->get_lastid();
		}

		function update_entry($fields)
		{
			if (!$fields['id'] && !$fields['ab_id'])
			{
				return $this->add_entry($fields);
			}
			$ok = False;
			if($this->check_perms($fields,PHPGW_ACL_EDIT))
			{
				if ($this->xmlrpc)
				{
					$fields = $this->xmlrpc2data($fields);
				}
				$ok = $this->so->update_entry($fields);
			}
			if ($this->xmlrpc && !$ok)
			{
				$GLOBALS['server']->xmlrpc_error($GLOBALS['xmlrpcerr']['no_access'],$GLOBALS['xmlrpcstr']['no_access']);
			}
			return $ok;
		}

		function delete_entry($addr)
		{
			if(!is_array($addr))
			{
				$id = (int)$addr;
			}
			else
			{
				if(is_numeric($addr[0]))	// xmlrpc liefert array($id)
				{
					$id = (int)$addr[0];
				}
				else
				{
					$id = isset($addr['id']) ? $addr['id'] : $addr['ab_id'];
				}
			}
			if($this->check_perms($id,PHPGW_ACL_DELETE))
			{
				$this->so->delete_entry($id);
			}
			elseif ($this->xmlrpc)
			{
				$GLOBALS['server']->xmlrpc_error($GLOBALS['xmlrpcerr']['no_access'],$GLOBALS['xmlrpcstr']['no_access']);
			}
		}

		/*!
		@function check_perms
		@abstract checks if user has the necessary rights on the given address or address-id
		@syntax check_perms($addr,$rights)
		@param $addr mixed address-record with id and owner or addr-id
		@param $rights integer PHPGW_ACL_{READ|EDIT|ADD|DELETE}
		@return True if the user has the requested rights, else False
		*/
		function check_perms($addr,$rights)
		{
			$id = (int) (!is_array($addr) ? $addr : (isset($addr['id']) ? $addr['id'] : $addr['ab_id']));

			if ($id < 0)
			{
				return $rights == PHPGW_ACL_READ;
			}
			if (!is_array($addr) || !isset($addr['rights']) && !isset($addr['owner']))
			{
				$addr = $this->so->read_entry($id,array('owner'));
				if (!$addr && $this->xmlrpc)
				{
					$GLOBALS['server']->xmlrpc_error($GLOBALS['xmlrpcerr']['not_exist'],$GLOBALS['xmlrpcstr']['not_exist']);
				}
				$addr = $addr[0];
			}
			$ret = $this->so->contacts->check_perms(False,$rights,$addr);
			//echo "<p>boaddressbook::check_perms(".print_r($addr,True).",$rights) = ".($ret?'True':'False')."</p>\n";
			return $ret;
		}

		function save_preferences($prefs,$other,$qfields,$fcat_id)
		{
			$GLOBALS['phpgw']->preferences->read_repository();
			if(is_array($prefs))
			{
				/* _debug_array($prefs);exit; */
				while(list($pref,$x) = each($qfields))
				{
					/* echo '<br>checking: ' . $pref . '=' . $prefs[$pref]; */
					if($prefs[$pref] == 'on')
					{
						$GLOBALS['phpgw']->preferences->add('addressbook',$pref,'addressbook_on');
					}
					else
					{
						$GLOBALS['phpgw']->preferences->delete('addressbook',$pref);
					}
				}
			}
			if(is_array($other))
			{
				$GLOBALS['phpgw']->preferences->delete('addressbook','mainscreen_showbirthdays');
				if($other['mainscreen_showbirthdays'])
				{
					$GLOBALS['phpgw']->preferences->add('addressbook','mainscreen_showbirthdays',True);
				}

				$GLOBALS['phpgw']->preferences->delete('addressbook','default_filter');
				if($other['default_filter'])
				{
					$GLOBALS['phpgw']->preferences->add('addressbook','default_filter',$other['default_filter']);
				}

				$GLOBALS['phpgw']->preferences->delete('addressbook','autosave_category');
				if($other['autosave_category'])
				{
					$GLOBALS['phpgw']->preferences->add('addressbook','autosave_category',True);
				}
			}

			$GLOBALS['phpgw']->preferences->delete('addressbook','default_category');
			$GLOBALS['phpgw']->preferences->add('addressbook','default_category',$fcat_id);

			$GLOBALS['phpgw']->preferences->save_repository(True);
		}

		function list_methods($_type='xmlrpc')
		{
			/*
			  This handles introspection or discovery by the logged in client,
			  in which case the input might be an array.  The server always calls
			  this function to fill the server dispatch map using a string.
			*/
			if(is_array($_type))
			{
				$_type = $_type['type'] ? $_type['type'] : $_type[0];
			}
			switch($_type)
			{
				case 'xmlrpc':
					$xml_functions = array(
						'read' => array(
							'function'  => 'read_entry',
							'signature' => array(array(xmlrpcStruct,xmlrpcStruct)),
							'docstring' => lang('Read a single entry by passing the id and fieldlist.')
						),
						'add' => array(
							'function'  => 'add_entry',
							'signature' => array(array(xmlrpcStruct,xmlrpcStruct)),
							'docstring' => lang('Add a single entry by passing the fields.')
						),
						'save' => array(
							'function'  => 'update_entry',
							'signature' => array(array(xmlrpcStruct,xmlrpcStruct)),
							'docstring' => lang('Update a single entry by passing the fields.')
						),
						'write' => array(	// alias for consistent nameing
							'function'  => 'update_entry',
							'signature' => array(array(xmlrpcStruct,xmlrpcStruct)),
							'docstring' => lang('Write (update or add) a single entry by passing the fields.')
						),
						'delete' => array(
							'function'  => 'delete_entry',
							'signature' => array(array(xmlrpcInt,xmlrpcInt)),
							'docstring' => lang('Delete a single entry by passing the id.')
						),
						'read_list' => array(
							'function'  => 'read_entries',
							'signature' => array(array(xmlrpcStruct,xmlrpcStruct)),
							'docstring' => lang('Read a list / search for entries.')
						),
						'search' => array(	// alias for consitent nameing
							'function'  => 'read_entries',
							'signature' => array(array(xmlrpcStruct,xmlrpcStruct)),
							'docstring' => lang('Read a list / search for entries.')
						),
						'categories' => array(
							'function'  => 'categories',
							'signature' => array(array(xmlrpcBoolean,xmlrpcBoolean)),
							'docstring' => lang('List all categories')
						),
						'customfields' => array(
							'function'  => 'customfields',
							'signature' => array(array(xmlrpcArray,xmlrpcArray)),
							'docstring' => lang('List all customfields')
						),
						'list_methods' => array(
							'function'  => 'list_methods',
							'signature' => array(array(xmlrpcStruct,xmlrpcString)),
							'docstring' => lang('Read this list of methods.')
						)
					);
					return $xml_functions;
					break;
				case 'soap':
					return $this->soap_functions;
					break;
				default:
					return array();
					break;
			}
		}

	}
?>
