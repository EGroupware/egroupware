<?php
	/**************************************************************************\
	* phpGroupWare - Administration                                            *
	* http://www.phpgroupware.org                                              *
	* --------------------------------------------                             *
	*  This program is free software; you can redistribute it and/or modify it *
	*  under the terms of the GNU General Public License as published by the   *
	*  Free Software Foundation; either version 2 of the License, or (at your  *
	*  option) any later version.                                              *
	\**************************************************************************/

	/* $Id$ */

	class boaccess_history
	{
		function boaccess_history()
		{
			$this->so       = createobject('admin.soaccess_history');
		}

		function list_history($account_id,$start,$order,$sort)
		{
			$records = $this->so->list_history($account_id,$start,$order,$sort);
			while (is_array($records) && list(,$record) = each($records))
			{
				if ($record['li'] && $record['lo'])
				{
					$total = ($record['lo'] - $record['li']);
					if ($total > 86400 && $total > 172800)
					{
						$total = gmdate('z \d\a\y\s - G:i:s',$total);
					}
					else if ($total > 172800)
					{
						$total = gmdate('z \d\a\y - G:i:s',$total);
					}
					else
					{
						$total = gmdate('G:i:s',$total);
					}
				}

				if ($record['li'])
				{
					$record['li'] = $GLOBALS['phpgw']->common->show_date($record['li']);
				}

				if ($record['lo'])
				{
					$record['lo'] = $GLOBALS['phpgw']->common->show_date($record['lo']);
				}
				else
				{
					$record['lo'] = '';		// dont show a 0 for not loged out
				}

				if (ereg('@',$record['loginid']))
				{
					$t = split('@',$record['loginid']);
					$record['loginid'] = $t[0];
				}

				$_records[] = array(
					'loginid'    => $record['loginid'],
					'ip'         => $record['ip'],
					'li'         => $record['li'],
					'lo'         => $record['lo'],
					'account_id' => $record['account_id'],
					'sessionid'  => $record['sessionid'],     
					'total'      => $total
				);
			}
			return $_records;
		}

		function total($account_id)
		{
			return $this->so->total($account_id);
		}

		function return_logged_out($account_id)
		{
			return $this->so->return_logged_out($account_id);
		}
	}
