<?php
  /**************************************************************************\
  * phpGroupWare - Admin - Peer Servers                                      *
  * http://www.phpgroupware.org                                              *
  * Written by Joseph Engo <jengo@mail.com>                                  *
  * --------------------------------------------                             *
  *  This program is free software; you can redistribute it and/or modify it *
  *  under the terms of the GNU General Public License as published by the   *
  *  Free Software Foundation; either version 2 of the License, or (at your  *
  *  option) any later version.                                              *
  \**************************************************************************/

  /* $Id$ */

	class soserver
	{
		var $is = '';
		var $debug = False;

		function soserver()
		{
			$this->is = CreateObject('phpgwapi.interserver');
		}

		function list_servers($data='',&$total)
		{
			if(gettype($data) == 'array')
			{
				if($this->debug) { _debug_array($data); }
				list($start,$sort,$order,$query,$limit) = $data;
			}
			return $this->is->get_list($start,$sort,$order,$query,$limit,$total);
		}

		function read($id)
		{
			return $this->is->read_repository($id);
		}

		function add($server_info)
		{
			return $this->is->create($server_info);
		}

		function update($server_info)
		{
			$this->is->server = $server_info;
			$this->is->save_repository($server_info['server_id']);
			return $this->is->read_repository($server_info['server_id']);
		}

		function delete($id)
		{
			return $this->is->delete($id);
		}
	}
?>
