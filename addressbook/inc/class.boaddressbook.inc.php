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
			'update_entry'    => True
		);

		var $xml_functions = array();

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

		var $use_session = False;

		function boaddressbook($session=False)
		{
			$this->so = CreateObject('addressbook.soaddressbook');
			$this->rights = $this->so->rights;
			$this->grants = $this->so->grants;

			if($session)
			{
				$this->read_sessiondata();
				$this->use_session = True;
			}
			/* _debug_array($GLOBALS['HTTP_POST_VARS']); */
			/* Might change this to '' at the end---> */
			$start   = $GLOBALS['start'];
			$query   = $GLOBALS['query'];
			$sort    = $GLOBALS['sort'];
			$order   = $GLOBALS['order'];
			$filter  = $GLOBALS['filter'];
			$cat_id  = $GLOBALS['cat_id'];
			$fcat_id = $GLOBALS['fcat_id'];

			if(!empty($start) || ($start == '0') || ($start == 0))
			{
				if($this->debug) { echo '<br>overriding start: "' . $this->start . '" now "' . $start . '"'; }
				$this->start = $start;
			}
			if($limit)  { $this->limit  = $limit;  }
			if((empty($query) && !empty($this->query)) ||
				!empty($query))
			{
				$this->query  = $query;
			}
			if(isset($fcat_id) || $fcat_id == '0' || $fcat_id == 0) { $this->cat_id = $fcat_id; }
			if(isset($sort))    { $this->sort   = $sort;   }
			if(isset($order))   { $this->order  = $order;  }
			if(isset($filter))  { $this->filter = $filter; }
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
						'read_entry' => array(
							'function'  => 'read_entry',
							'signature' => array(array(xmlrpcStruct,xmlrpcStruct)),
							'docstring' => lang('Read a single entry by passing the id and fieldlist.')
						),
						'add_entry' => array(
							'function'  => 'add_entry',
							'signature' => array(array(xmlrpcStruct,xmlrpcStruct)),
							'docstring' => lang('Add a single entry by passing the fields.')
						),
						'update_entry' => array(
							'function'  => 'update_entry',
							'signature' => array(array(xmlrpcStruct,xmlrpcStruct)),
							'docstring' => lang('Update a single entry by passing the fields.')
						),
						'delete_entry' => array(
							'function'  => 'delete_entry',
							'signature' => array(array(xmlrpcInt,xmlrpcInt)),
							'docstring' => lang('Delete a single entry by passing the id.')
						),
						'read_entries' => array(
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
			return $this->strip_html($entries);
		}

		function read_entry($data)
		{
			$entry = $this->so->read_entry($data['id'],$data['fields']);
			return $this->strip_html($entry);
		}

		function read_last_entry($fields)
		{
			$entry = $this->so->read_last_entry($fields);
			return $this->strip_html($entry);
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
			return $this->so->add_entry($fields);
		}

		function get_lastid()
		{
			return $this->so->get_lastid();
		}

		function update_entry($fields)
		{
			return $this->so->update_entry($fields);
		}

		function delete_entry($ab_id)
		{
			return $this->so->delete_entry($ab_id);
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
					$GLOBALS['phpgw']->preferences->add('addressbook','mainscreen_showbirthdays');
				}

				$GLOBALS['phpgw']->preferences->delete('addressbook','default_filter');
	 			if ($other['default_filter'])
				{
					$GLOBALS['phpgw']->preferences->add('addressbook','default_filter');
				}

				$GLOBALS['phpgw']->preferences->delete('addressbook','autosave_category');
	 			if ($other['autosave_category'])
				{
					$GLOBALS['phpgw']->preferences->add('addressbook','autosave_category',True);
				}
			}

			if($fcat_id)
			{
				$GLOBALS['phpgw']->preferences->delete('addressbook','default_category');
				$GLOBALS['phpgw']->preferences->add('addressbook','default_category',$fcat_id);
			}

			$GLOBALS['phpgw']->preferences->save_repository(True);
			/* _debug_array($prefs);exit; */
			Header('Location: ' . $GLOBALS['phpgw']->link('/preferences/index.php'));
		}
	}
?>
