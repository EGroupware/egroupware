<?php
  /**************************************************************************\
  * eGroupWare API - XML-RPC Server using builtin php functions              *
  * This file written by Miles Lott <milos@groupwhere.org>                   *
  * Copyright (C) 2003 Miles Lott                                            *
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

	// contains useful functions for xmlrpc methods
	class xmlrpc_server_shared
	{
		// convert a date-array or timestamp into a datetime.iso8601 string
		function date2iso8601($date)
		{
			if (!is_array($date))
			{
				if(strstr($_SERVER['HTTP_USER_AGENT'],"vbXMLRPC"))
				{
					return date('Ymd\TH:i:s',$date);
				}
				return date('Y-m-d\TH:i:s',$date);
			}

			$formatstring = "%04d-%02d-%02dT%02d:%02d:%02d";
			if(strstr($_SERVER['HTTP_USER_AGENT'],"vbXMLRPC"))
			{
				$formatstring = "%04d%02d%02dT%02d:%02d:%02d";
			}
			return sprintf($formatstring,
				$date['year'],$date['month'],$date['mday'],
				$date['hour'],$date['min'],$date['sec']);
		}

		// convert a datetime.iso8601 string into a datearray or timestamp
		function iso86012date($isodate,$timestamp=False)
		{
			if (($arr = split('[-:T]',$isodate)) && count($arr) == 6)
			{
				foreach(array('year','month','mday','hour','min','sec') as $n => $name)
				{
					$date[$name] = (int)$arr[$n];
				}
				return $timestamp ? mktime($date['hour'],$date['min'],$date['sec'],
					$date['month'],$date['mday'],$date['year']) : $date;
			}
			return False;
		}

		// translate cat-ids to array with id-name pairs
		function cats2xmlrpc($cats)
		{
			if (!is_object($GLOBALS['phpgw']->categories))
			{
				$GLOBALS['phpgw']->categories = CreateObject('phpgwapi.categories');
			}
			$xcats = array();
			foreach($cats as $cat)
			{
				if ($cat)
				{
					$xcats[$cat] = stripslashes($GLOBALS['phpgw']->categories->id2name($cat));
				}
			}
			return $xcats;
		}

		// translate cats back to cat-ids, creating / modifying cats on the fly
		function xmlrpc2cats($xcats)
		{
			if (!is_array($xcats))
			{
				$xcats = array();
			}
			elseif (!is_object($GLOBALS['phpgw']->categories))
			{
				$GLOBALS['phpgw']->categories = CreateObject('phpgwapi.categories');
			}
			$cats = array();
			foreach($xcats as $cat => $name)
			{
				if ($id = $GLOBALS['phpgw']->categories->name2id($name))
				{
					// existing cat-name use the id
					$cat = $id;
				}
				elseif (!($org_name = stripslashes($GLOBALS['phpgw']->categories->id2name($cat))) || $org_name == '--')
				{
					// new cat
					$cat = $GLOBALS['phpgw']->categories->add(array('name' => $name,'parent' => 0));
				}
				elseif ($org_name != $name)
				{
					// cat-name edited
					list($cat_vals) =$GLOBALS['phpgw']->categories->return_single($cat);
					$cat_vals['name'] = $name;
					$GLOBALS['phpgw']->categories->edit($cat_vals);
				}
				$cats[] = (int)$cat;
			}
			return $cats;
		}

		// get list (array with id-name pairs) of all cats of $app
		function categories($complete = False,$app = '')
		{
			if (is_array($complete)) $complete = @$complete[0];
			if (!$app) list($app) = explode('.',$this->last_method);

			if (!is_object($GLOBALS['phpgw']->categories))
			{
				$GLOBALS['phpgw']->categories = CreateObject('phpgwapi.categories');
			}
			if ($GLOBALS['phpgw']->categories->app_name != $app)
			{
				$GLOBALS['phpgw']->categories->categories('',$app);
			}
			$cats_arr = $GLOBALS['phpgw']->categories->return_sorted_array(0,False,'','','',True);
			$cats = array();
			if (is_array($cats_arr))
			{
				foreach($cats_arr as $cat)
				{
					foreach(array('name','description') as $name)
					{
						$cat[$name] = stripslashes($cat[$name]);
					}
					$cats[$cat['id']] = $complete ? $cat : $cat['name'];
				}
			}
			return $cats;
		}
	}

	if(empty($GLOBALS['phpgw_info']['server']['xmlrpc_type']))
	{
		$GLOBALS['phpgw_info']['server']['xmlrpc_type'] = 'php';
	}
	include_once(PHPGW_API_INC.SEP.'class.xmlrpc_server_' . $GLOBALS['phpgw_info']['server']['xmlrpc_type'] . '.inc.php');
