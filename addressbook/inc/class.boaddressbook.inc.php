<?php
  /**************************************************************************\
  * phpGroupWare - addressbook                                               *
  * http://www.phpgroupware.org                                              *
  * Written by Miles Lott <milosch@phpgroupware.org>                         *
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
			)
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
			$this->grants = &$this->so->grants;

			if($session)
			{
				$this->read_sessiondata();
				$this->use_session = True;
			}
			/* _debug_array($_POST); */
			/* Might change this to '' at the end---> */
			$_start   = get_var('start',array('POST','GET'));
			$_query   = get_var('query',array('POST','GET'));
			$_sort    = get_var('sort',array('POST','GET'));
			$_order   = get_var('order',array('POST','GET'));
			$_filter  = get_var('filter',array('POST','GET'));
			$_cat_id  = get_var('cat_id',array('POST','GET'));
			$_fcat_id = get_var('fcat_id',array('POST','GET'));

			if(!empty($_start) || ($_start == '0') || ($_start == 0))
			{
				if($this->debug) { echo '<br>overriding $start: "' . $this->start . '" now "' . $_start . '"'; }
				$this->start = $_start;
			}
			if($_limit)
			{
				$this->limit  = $_limit;
			}
			if((empty($_query) && !empty($this->query)) || !empty($_query))
			{
				$this->query  = $_query;
			}

			if(isset($_POST['fcat_id']) || isset($_POST['fcat_id']))
			{
				$this->cat_id = $_fcat_id;
			}
			else
			{
				$this->cat_id = -1;
			}
			
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

			$this->xmlrpc_methods[] = array(
				'name'        => 'read_entries',
				'description' => 'Get list of addressbook items'
			);
		}

		function _debug_sqsof()
		{
			$data = array(
				'start'  => $this->start,
				'limit'  => $this->limit,
				'query'  => $this->query,
				'sort'   => $this->sort,
				'order'  => $this->order,
				'filter' => $this->filter,
				'cat_id' => $this->cat_id
			);
			echo '<br>BO:';
			_debug_array($data);
		}

		function list_methods($_type='xmlrpc')
		{
			/*
			  This handles introspection or discovery by the logged in client,
			  in which case the input might be an array.  The server always calls
			  this function to fill the server dispatch map using a string.
			*/
			if (is_array($_type))
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
						'delete' => array(
							'function'  => 'delete_entry',
							'signature' => array(array(xmlrpcInt,xmlrpcInt)),
							'docstring' => lang('Delete a single entry by passing the id.')
						),
						'read_list' => array(
							'function'  => 'read_entries',
							'signature' => array(array(xmlrpcStruct,xmlrpcStruct)),
							'docstring' => lang('Read a list of entries.')
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

		function save_sessiondata($data)
		{
			if ($this->use_session)
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
			$this->sort   = $data['sort'];
			$this->order  = $data['order'];
			$this->filter = $data['filter'];
			$this->cat_id = $data['cat_id'];
			if($this->debug) { echo '<br>read_sessiondata();'; $this->_debug_sqsof(); }
		}

		function strip_html($dirty = '')
		{
			if ($dirty == '')
			{
				$dirty = array();
			}
			for($i=0;$i<count($dirty);$i++)
			{
				if(gettype($dirty[$i]) == 'array')
				{
					while (list($name,$value) = @each($dirty[$i]))
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

		function read_entries($data)
		{
			$entries = $this->so->read_entries($data);
			$this->total = $this->so->contacts->total_records;
			if($this->debug) { echo '<br>Total records="' . $this->total . '"'; }
			return (is_array($entries) ? $this->strip_html($entries) : array());
		}

		function read_entry($data)
		{
			if ($this->check_perms($data,PHPGW_ACL_DELETE))
			{
				$entry = $this->so->read_entry($data['id'],$data['fields']);
				return $this->strip_html($entry);
			}
			return array(0 => array('No access' => 'No access'));
		}

		function read_last_entry($fields)
		{
			if ($this->check_perms($fields,PHPGW_ACL_DELETE))
			{
				$entry = $this->so->read_last_entry($fields);
				return $this->strip_html($entry);
			}
			return array(0 => array('No access' => 'No access'));
		}

		function add_vcard()
		{
			global $uploadedfile;

			if($uploadedfile == 'none' || $uploadedfile == '')
			{
				Header('Location: ' . $GLOBALS['phpgw']->link('/index.php','menuaction=addressbook.uivcard.in&action=GetFile'));
			}
			else
			{
				$uploaddir = $GLOBALS['phpgw_info']['server']['temp_dir'] . SEP;

				srand((double)microtime()*1000000);
				$random_number = rand(100000000,999999999);
				$newfilename = md5("$uploadedfile, $uploadedfile_name, "
					. time() . getenv("REMOTE_ADDR") . $random_number );

				copy($uploadedfile, $uploaddir . $newfilename);
				$ftp = fopen($uploaddir . $newfilename . '.info','w');
				fputs($ftp,"$uploadedfile_type\n$uploadedfile_name\n");
				fclose($ftp);

				$filename = $uploaddir . $newfilename;

				$vcard = CreateObject('phpgwapi.vcard');
				$entry = $vcard->in_file($filename);
				/* _debug_array($entry);exit; */
				$entry['owner'] = $GLOBALS['phpgw_info']['user']['account_id'];
				$entry['access'] = 'private';
				$entry['tid'] = 'n';
				/* _debug_array($entry);exit; */
				$this->so->add_entry($entry);
				$ab_id = $this->get_lastid();

				/* Delete the temp file. */
				unlink($filename);
				unlink($filename . '.info');
				Header('Location: ' . $GLOBALS['phpgw']->link('/index.php','menuaction=addressbook.uiaddressbook.view&ab_id=' . $ab_id));
			}
		}

		function add_email()
		{
			global $name,$referer;

			$named = explode(' ', $name);
			for ($i=count($named);$i>=0;$i--) { $names[$i] = $named[$i]; }
			if ($names[2])
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
				$fields['owner'] = $GLOBALS['phpgw_info']['user']['account_id'];
			}
			if(empty($fields['access']))
			{
				$fields['access'] = 'public';
			}
			return $this->so->add_entry($fields);
		}

		function get_lastid()
		{
			return $this->so->get_lastid();
		}

		function update_entry($fields)
		{
			if ($this->check_perms($fields,PHPGW_ACL_EDIT))
			{
				return $this->so->update_entry($fields);
			}
			return False;
		}

		function delete_entry($addr)
		{
			$id = !is_array($addr) ? $addr : (isset($addr['id']) ? $addr['id'] : $addr['ab_id']);

			if ($this->check_perms($id,PHPGW_ACL_DELETE))
			{
				return $this->so->delete_entry($id);
			}
			return False;
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
			$id = !is_array($addr) ? $addr : (isset($addr['id']) ? $addr['id'] : $addr['ab_id']);

			if (!is_array($addr) || !isset($addr['owner']))
			{
				$a = $this->so->read_entry($id,array('owner'));
				$owner = $a[0]['owner'];
			}
			else
			{
				$owner = $addr['owner'];
			}
			//echo "<p>boaddressbook::check_perms(id='$id',rights=$rights): grant[owner='$owner']='".$this->grants[$owner]."' => ".(($this->grants[$owner] & 4) ? 'True':'False')."</p>\n";
			return !!($this->grants[$owner] & $rights);
		}

		function save_preferences($prefs,$other,$qfields,$fcat_id)
		{
			$GLOBALS['phpgw']->preferences->read_repository();
			if (is_array($prefs))
			{
				/* _debug_array($prefs);exit; */
				while (list($pref,$x) = each($qfields))
				{
					/* echo '<br>checking: ' . $pref . '=' . $prefs[$pref]; */
					if ($prefs[$pref] == 'on')
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
	 			if ($other['mainscreen_showbirthdays'])
				{
					$GLOBALS['phpgw']->preferences->add('addressbook','mainscreen_showbirthdays',True);
				}

				$GLOBALS['phpgw']->preferences->delete('addressbook','default_filter');
	 			if ($other['default_filter'])
				{
					$GLOBALS['phpgw']->preferences->add('addressbook','default_filter',$other['default_filter']);
				}

				$GLOBALS['phpgw']->preferences->delete('addressbook','autosave_category');
	 			if ($other['autosave_category'])
				{
					$GLOBALS['phpgw']->preferences->add('addressbook','autosave_category',True);
				}
			}

			$GLOBALS['phpgw']->preferences->delete('addressbook','default_category');
			$GLOBALS['phpgw']->preferences->add('addressbook','default_category',$fcat_id);

			$GLOBALS['phpgw']->preferences->save_repository(True);
		}
	}
?>
