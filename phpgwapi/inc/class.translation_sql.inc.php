<?php
  /**************************************************************************\
  * phpGroupWare API - Translation class for SQL                             *
  * This file written by Joseph Engo <jengo@phpgroupware.org>                *
  * and Dan Kuykendall <seek3r@phpgroupware.org>                             *
  * Handles multi-language support use SQL tables                            *
  * Copyright (C) 2000, 2001 Joseph Engo                                     *
  * -------------------------------------------------------------------------*
  * This library is part of the phpGroupWare API                             *
  * http://www.phpgroupware.org/api                                          * 
  * ------------------------------------------------------------------------ *
  * This library is free software; you can redistribute it and/or modify it  *
  * under the terms of the GNU Lesser General Public License as published by *
  * the Free Software Foundation; either version 2.1 of the License,         *
  * or any later version.                                                    *
  * This library is distributed in the hope that it will be useful, but      *
  * WITHOUT ANY WARRANTY; without even the implied warranty of               *
  * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.                     *
  * See the GNU Lesser General Public License for more details.              *
  * You should have received a copy of the GNU Lesser General Public License *
  * along with this library; if not, write to the Free Software Foundation,  *
  * Inc., 59 Temple Place, Suite 330, Boston, MA  02111-1307  USA            *
  \**************************************************************************/

  /* $Id$ */

	class translation
	{
		function translate($key, $vars=False) 
		{
			if(!$vars)
			{
				$vars = array();
			}
			$ret = $key;
			/*
			 Check also if $GLOBALS['lang'] is a array.
			 php-nuke and postnuke are using $GLOBALS['lang'], too,
			 as string.
			 This makes many problems.
			*/

			if(isset($GLOBALS['lang'][strtolower($key)]) && $GLOBALS['lang'][strtolower($key)])
			{
				$ret = $GLOBALS['lang'][strtolower($key)];
			}
			elseif(!isset($GLOBALS['lang']) || !$GLOBALS['lang'] || !is_array($GLOBALS['lang']))
			{
				$GLOBALS['lang'] = array();
				if(isset($GLOBALS['phpgw_info']['user']['preferences']['common']['lang']) &&
					$GLOBALS['phpgw_info']['user']['preferences']['common']['lang'])
				{
					$userlang = $GLOBALS['phpgw_info']['user']['preferences']['common']['lang'];
				}
				else
				{
					$userlang = 'en';
				}
				$sql = "SELECT message_id,content FROM lang WHERE lang LIKE '" . $userlang
					. "' AND (app_name LIKE '" . $GLOBALS['phpgw_info']['flags']['currentapp']
					. "' OR app_name LIKE 'common' OR app_name LIKE 'all')";

				if (strcasecmp ($GLOBALS['phpgw_info']['flags']['currentapp'], 'common')>0)
				{
					$sql .= ' ORDER BY app_name ASC';
				}
				else
				{
					$sql .= ' ORDER BY app_name DESC';
				}

				$GLOBALS['phpgw']->db->query($sql,__LINE__,__FILE__);
				$GLOBALS['phpgw']->db->next_record();
				$count = $GLOBALS['phpgw']->db->num_rows();
				for($idx = 0; $idx < $count; ++$idx)
				{
					$GLOBALS['lang'][strtolower($GLOBALS['phpgw']->db->f('message_id'))] = $GLOBALS['phpgw']->db->f('content');
					$GLOBALS['phpgw']->db->next_record();
				}
				$ret = $GLOBALS['lang'][strtolower($key)] ? $GLOBALS['lang'][strtolower($key)] : $key . '*';
			}

			$ndx = 1;
			while(list($key,$val) = each($vars))
			{
				$ret = preg_replace( "/%$ndx/", $val, $ret );
				++$ndx;
			}
			return $ret;
		}

		function add_app($app) 
		{
			/*
			 post-nuke and php-nuke are using $GLOBALS['lang'], too.
			 But not as array!
			 This produces very strange results.
			*/
			if(!is_array($GLOBALS['lang']))
			{
				$GLOBALS['lang'] = array();
			}
			
			if($GLOBALS['phpgw_info']['user']['preferences']['common']['lang'])
			{
				$userlang = $GLOBALS['phpgw_info']['user']['preferences']['common']['lang'];
			}
			else
			{
				$userlang = 'en';
			}
			$sql = "SELECT message_id,content FROM lang WHERE lang LIKE '".$userlang."' AND app_name LIKE '".$app."'";
			$GLOBALS['phpgw']->db->query($sql,__LINE__,__FILE__);
			$GLOBALS['phpgw']->db->next_record();
			$count = $GLOBALS['phpgw']->db->num_rows();
			for($idx = 0; $idx < $count; ++$idx)
			{
				$GLOBALS['lang'][strtolower ($GLOBALS['phpgw']->db->f('message_id'))] = $GLOBALS['phpgw']->db->f('content');
				$GLOBALS['phpgw']->db->next_record();
			}
		}
	}
