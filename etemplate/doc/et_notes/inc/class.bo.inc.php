<?php
	/***************************************************************************\
	* phpGroupWare - Notes eTemplate Port                                       *
	* http://www.phpgroupware.org                                               *
	* Written by : Andy Holman (LoCdOg)                                         *
	*              Bettina Gille [ceb@phpgroupware.org]                         *
	* Ported to eTemplate by Ralf Becker [ralfbecker@outdoor-training.de]       *
	* -------------------------------------------------                         *
	* This program is free software; you can redistribute it and/or modify it   *
	* under the terms of the GNU General Public License as published by the     *
	* Free Software Foundation; either version 2 of the License, or (at your    *
	* option) any later version.                                                *
	\***************************************************************************/
	/* $Id$ */

	class bo
	{
		var $start;
		var $search;
		var $filter;
		var $cat_id;

		var $public_functions = array
		(
			'read'				=> True,
			'read_single'		=> True,
			'save'				=> True,
			'delete'			=> True,
			'check_perms'		=> True,
			'set_font'			=> True,
			'set_font_size'		=> True,
			'read_preferences'	=> True,
			'save_preferences'	=> True,
			'get_rows'			=> True
		);

		var $soap_functions = array(
			'list' => array(
				'in'  => array('int','int','struct','string','int'),
				'out' => array('array')
			),
			'read' => array(
				'in'  => array('int','struct'),
				'out' => array('array')
			),
			'save' => array(
				'in'  => array('int','struct'),
				'out' => array()
			),
			'delete' => array(
				'in'  => array('int','struct'),
				'out' => array()
			)
		);

		function bo($session=False)
		{
			$this->so = CreateObject('et_notes.so');
			$this->account		= $GLOBALS['phpgw_info']['user']['account_id'];
			$this->grants		= $GLOBALS['phpgw']->acl->get_grants('et_notes');
			$this->grants[$this->account] = PHPGW_ACL_READ + PHPGW_ACL_ADD + PHPGW_ACL_EDIT + PHPGW_ACL_DELETE;

			if ($session)
			{
				$this->read_sessiondata();
				$this->use_session = True;
			}

			global $start, $search, $filter, $cat_id;

			if(isset($start)) { $this->start = $start; }
			if(isset($search)) { $this->search = $search; }
			if(!empty($filter)) { $this->filter = $filter; }
			if(isset($cat_id)) { $this->cat_id = $cat_id; }
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
							'function'  => 'read',
							'signature' => array(array(xmlrpcInt,xmlrpcStruct)),
							'docstring' => lang('Read a single entry by passing the id and fieldlist.')
						),
						'save' => array(
							'function'  => 'save',
							'signature' => array(array(xmlrpcStruct,xmlrpcStruct)),
							'docstring' => lang('Update a single entry by passing the fields.')
						),
						'delete' => array(
							'function'  => 'delete',
							'signature' => array(array(xmlrpcBoolean,xmlrpcInt)),
							'docstring' => lang('Delete a single entry by passing the id.')
						),
						'list' => array(
							'function'  => '_list',
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
				$GLOBALS['phpgw']->session->appsession('session_data','et_notes',$data);
			}
		}

		function read_sessiondata()
		{
			$data = $GLOBALS['phpgw']->session->appsession('session_data','et_notes');

			$this->start   = $data['start'];
			$this->search  = $data['search'];
			$this->filter  = $data['filter'];
			$this->cat_id  = $data['cat_id'];
		}

		function check_perms($has, $needed)
		{
			return (!!($has & $needed) == True);
		}

		function read($start = '', $search = '', $filter = '', $cat_id = '')
		{
			if (is_array($start))
			{
				$params = $start;
			}
			else
			{
				$params['start']  = $start;
				$params['search'] = $search;
				$params['filter'] = $filter;
				$params['cat_id'] = $cat_id;
			}

			$notes = $this->so->read($params['start'],$params['search'],$params['filter'],$params['cat_id']);
			$this->total_records = $this->so->total_records;

			for ($i=0; $i<count($notes); $i++)
			{
				$notes[$i]['date']  = $GLOBALS['phpgw']->common->show_date($notes[$i]['date']);
				$notes[$i]['owner'] = $GLOBALS['phpgw']->accounts->id2name($notes[$i]['owner']);
			}

			return $notes;
		}

		function read_single($note_id)
		{
			return $this->so->read_single($note_id);
		}

		function save($note)
		{
			if ($note['access'])
			{
				$note['access'] = 'private';
			}
			else
			{
				$note['access'] = 'public';
			}

			if ($note['id'])
			{
				if ($note['id'] != 0)
				{
					$this->so->edit($note);
				}
			}
			else
			{
				$this->so->add($note);
			}
		}

		function delete($params)
		{
			if (is_array($params))
			{
				$this->so->delete($params[0]);
			}
			else
			{
				$this->so->delete($params);
			}
		}

		function read_preferences()
		{
			$GLOBALS['phpgw']->preferences->read_repository();

			$prefs = array();

			if ($GLOBALS['phpgw_info']['user']['preferences']['notes'])
			{
				$prefs['notes_font'] = $GLOBALS['phpgw_info']['user']['preferences']['notes']['notes_font'];
				$prefs['notes_font_size'] = $GLOBALS['phpgw_info']['user']['preferences']['notes']['notes_font_size'];
			}
			return $prefs;
		}

		function save_preferences($prefs)
		{
			$GLOBALS['phpgw']->preferences->read_repository();

			if ($prefs)
			{
				$GLOBALS['phpgw']->preferences->change('notes','notes_font',$prefs['notes_font']);
				$GLOBALS['phpgw']->preferences->change('notes','notes_font_size',$prefs['notes_font_size']);
				$GLOBALS['phpgw']->preferences->save_repository(True);
			}
		}

		function get_rows($query,&$rows,&$readonlys)
		{
			//echo "<p>notes.ui.get_rows(start=$query[start],search='$query[search]',filter='$query[filter]',cat_id=$query[cat_id]): notes_list =";

			$rows = $this->read($query['start'],$query['search'],$query['filter'],$query['cat_id']);
			if (!is_array($rows))
			{
				$rows = array( );
			}
			else
			{
				array_unshift($rows,0); each($rows); // first entry is not used
				//_debug_array($notes_list);
			}
			$readonlys = array( );
			while (list($n,$note) = each($rows))
			{
				if (!$this->check_perms($this->grants[$note['owner_id']],PHPGW_ACL_EDIT))
				{
					$readonlys["edit[$note[id]]"] = True;
				}
				if (!$this->check_perms($this->grants[$note['owner_id']],PHPGW_ACL_DELETE))
				{
					$readonlys["delete[$note[id]]"] = True;
				}
				$rows[$n]['access'] = $note['access'] == 'private' ? 'private' : 'public';
			}
			reset($rows);

			return $this->total_records;
		}
	}
?>
