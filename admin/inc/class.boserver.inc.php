<?php
  /**************************************************************************\
  * phpGroupWare - Admin                                                     *
  * http://www.phpgroupware.org                                              *
  * Written by Miles Lott <milosch@phpgroupware.org>                         *
  * --------------------------------------------                             *
  *  This program is free software; you can redistribute it and/or modify it *
  *  under the terms of the GNU General Public License as published by the   *
  *  Free Software Foundation; either version 2 of the License, or (at your  *
  *  option) any later version.                                              *
  \**************************************************************************/

  /* $Id$ */

	class boserver
	{
		var $public_functions = array(
			'list_servers' => True,
			'read'         => True,
			'edit'         => True,
			'delete'       => True
		);

		var $xml_functions  = array();
		var $soap_functions = array();

		var $debug = False;

		var $so    = '';
		var $start = 0;
		var $limit = 0;
		var $query = '';
		var $sort  = '';
		var $order = '';
		var $total = 0;

		var $use_session = False;

		function boserver($session=False)
		{
			$this->so = CreateObject('admin.soserver');

			if($session)
			{
				$this->read_sessiondata();
				$this->use_session = True;
			}

			$start = $GLOBALS['start'];
			$query = $GLOBALS['query'];
			$sort  = $GLOBALS['sort'];
			$order = $GLOBALS['order'];

			if(!empty($start) || ($start == '0') || ($start == 0))
			{
				if($this->debug) { echo '<br>overriding start: "' . $this->start . '" now "' . $start . '"'; }
				$this->start = $start;
			}

			if((empty($query) && !empty($this->query)) || !empty($query))
			{
				$this->query = $query;
			}

			if($limit)        { $this->limit = $limit; }
			if(isset($sort))  { $this->sort  = $sort;  }
			if(isset($order)) { $this->order = $order; }
		}

		function save_sessiondata($data)
		{
			if ($this->use_session)
			{
				if($this->debug) { echo '<br>Save:'; _debug_array($data); }
				$GLOBALS['phpgw']->session->appsession('session_data','admin_servers',$data);
			}
		}

		function read_sessiondata()
		{
			$data = $GLOBALS['phpgw']->session->appsession('session_data','admin_servers');
			if($this->debug) { echo '<br>Read:'; _debug_array($data); }

			$this->start  = $data['start'];
			$this->limit  = $data['limit'];
			$this->query  = $data['query'];
			$this->sort   = $data['sort'];
			$this->order  = $data['order'];
		}

		function list_servers()
		{
			return $this->so->list_servers(array($this->start,$this->sort,$this->order,$this->query,$this->limit),$this->total);
		}

		function read($id)
		{
			if(is_array($id))
			{
				$id = $id['server_id'];
			}
			return $this->so->read($id);
		}

		function edit($server_info)
		{
			if(!is_array($server_info))
			{
				return False;
			}

			if($server_info['server_id'])
			{
				return $this->so->update($server_info);
			}
			else
			{
				return $this->so->add($server_info);
			}
		}

		function delete($id)
		{
			if(is_array($id))
			{
				$id = $id['server_id'];
			}
			return $this->so->delete($id);
		}
	}
