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
		var $ui;
		var $so;
		var $public_functions = array(
			'kill' => True
		);

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
			$values = $this->so->list_sessions($start,$sort,$order);

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

				$_values[] = array(
					'session_id'        => $value['session_id'],
					'session_lid'       => $session_lid,
					'session_ip'        => $value['session_ip'],
					'session_logintime' => $GLOBALS['phpgw']->common->show_date($value['session_logintime']),
					'session_action'    => $value['session_action'],
					'session_dla'       => $value['session_dla'],
					'session_idle'      => gmdate('G:i:s',($GLOBALS['phpgw']->datetime->gmtnow - $value['session_dla']))
				);
			}
			return $_values;
		}

		function kill()
		{
			if ($GLOBALS['HTTP_GET_VARS']['ksession'] &&
				($GLOBALS['sessionid'] != $GLOBALS['HTTP_GET_VARS']['ksession']) &&
				! $GLOBALS['phpgw']->acl->check('current_sessions_access',8,'admin'))
			{
				$GLOBALS['phpgw']->session->destroy($GLOBALS['HTTP_GET_VARS']['ksession'],0);
			}
			$this->ui = createobject('admin.uicurrentsessions');
			$this->ui->list_sessions();
		}
	}
