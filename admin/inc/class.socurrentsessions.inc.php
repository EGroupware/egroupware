<?php
	/**************************************************************************\
	* phpGroupWare - Administration                                            *
	* http://www.phpgroupware.org                                              *
	*  This file written by Joseph Engo <jengo@phpgroupware.org>               *
	* --------------------------------------------                             *
	*  This program is free software; you can redistribute it and/or modify it *
	*  under the terms of the GNU General Public License as published by the   *
	*  Free Software Foundation; either version 2 of the License, or (at your  *
	*  option) any later version.                                              *
	\**************************************************************************/

	/* $Id$ */

	class socurrentsessions
	{
		var $db;

		function socurrentsessions()
		{
			$this->db       = $GLOBALS['phpgw']->db;
		}

		function total()
		{
			$this->db->query("select count(*) from phpgw_sessions where session_flags != 'A'",__LINE__,__FILE__);
			$this->db->next_record();

			return $this->db->f(0);
		}

		function list_sessions($start,$order,$sort)
		{
			$ordermethod = 'order by session_dla asc';

			$this->db->limit_query("select * from phpgw_sessions where session_flags != 'A' order by $sort $order",$start,__LINE__,__FILE__);

			while ($this->db->next_record())
			{
				$values[] = array(
					'session_id'        => $this->db->f('session_id'),
					'session_lid'       => $this->db->f('session_lid'),
					'session_ip'        => $this->db->f('session_ip'),
					'session_logintime' => $this->db->f('session_logintime'),
					'session_action'    => $this->db->f('session_action'),
					'session_dla'       => $this->db->f('session_dla')
				);
			}
			return $values;
		}
	}
