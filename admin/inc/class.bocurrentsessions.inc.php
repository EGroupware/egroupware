<?php
	/**************************************************************************\
	* eGroupWare - Administration                                              *
	* http://www.egroupware.org                                                *
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
		var $ui;
		var $so;
		var $public_functions = array(
			'kill' => True
		);

		function total()
		{
			return $GLOBALS['phpgw']->session->total();
		}

		function list_sessions($start,$order,$sort)
		{
			$values = $GLOBALS['phpgw']->session->list_sessions($start,$sort,$order);

			while (list(,$value) = @each($values))
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
				$tmp = time() - $value['session_dla'];
				$secs = $tmp % 60;
				$mins = (($tmp - $secs) % 3600) / 60;
				$hours = ($tmp - ($mins * 60) - $secs) / 3600;
				$_values[] = array(
					'session_id'        => $value['session_id'],
					'session_lid'       => $session_lid,
					'session_ip'        => $value['session_ip'],
					'session_logintime' => $GLOBALS['phpgw']->common->show_date($value['session_logintime']),
					'session_action'    => $value['session_action'],
					'session_dla'       => $value['session_dla'],
					'session_idle'      => str_pad($hours, 2, '0', STR_PAD_LEFT) . ':' . str_pad($mins, 2, '0', STR_PAD_LEFT) . ':' . str_pad($secs, 2, '0', STR_PAD_LEFT)
				);
			}
			return $_values;
		}

		function kill()
		{
			if ($_GET['ksession'] &&
				($GLOBALS['sessionid'] != $_GET['ksession']) &&
				! $GLOBALS['phpgw']->acl->check('current_sessions_access',8,'admin'))
			{
				$GLOBALS['phpgw']->session->destroy($_GET['ksession'],0);
			}
			$this->ui = createobject('admin.uicurrentsessions');
			$this->ui->list_sessions();
		}
	}
