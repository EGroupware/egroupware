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

	class bocurrentsessions
	{
		var $so;

		function bocurrentsessions()
		{
			$this->so       = CreateObject('admin.socurrentsessions');
		}

		function total()
		{
			return $this->so->total();
		}

		function list_sessions($start,$order,$sort)
		{
//			if (! $sort || ! $order)
//			{
//				$order = 'session_dla';
//				$sort  = 'asc';
//			}

			$values = $this->so->list_sessions($start,$sort,$order);

			while (list(,$value) = each($values))
			{
				if (ereg('@',$value['session_lid']))
				{
					$t = split('@',$value['session_lid']);
					$session_lid = $t[0];
				}
				else
				{
					$session_lid = $value['session_lid'];
				}

				$_values[] = array(
					'session_id'        => $value['session_id'],
					'session_lid'       => $session_lid,
					'session_ip'        => $value['session_ip'],
					'session_logintime' => $GLOBALS['phpgw']->common->show_date($value['session_logintime']),
					'session_action'    => $value['session_action'],
					'session_dla'       => $value['session_dla'],
					'session_idle'      => gmdate('G:i:s',(time() - $value['session_dla']))
				);
			}
			return $_values;
		}

	}