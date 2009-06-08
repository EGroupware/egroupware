<?php
  /**************************************************************************\
  * eGroupWare API - XML-RPC Server                                          *
  * ------------------------------------------------------------------------ *
  * This library is part of the eGroupWare API                               *
  * http://www.egroupware.org/api                                            *
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
		var $simpledate = False;

		// convert a date-array or timestamp into a datetime.iso8601 string
		function date2iso8601($date)
		{
			if (!is_array($date))
			{
				if($this->simpledate)
				{
					return date('Ymd\TH:i:s',$date);
				}
				return date('Y-m-d\TH:i:s',$date);
			}

			$formatstring = "%04d-%02d-%02dT%02d:%02d:%02d";
			if($this->simpledate)
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
			$arr = array();

			if (preg_match('/^([0-9]{4})([0-9]{2})([0-9]{2})T([0-9]{2}):([0-9]{2}):([0-9]{2})$/',$isodate,$arr))
			{
				// $isodate is simple ISO8601, remove the difference between split and ereg
				array_shift($arr);
			}
			elseif (($arr = preg_split('/[-:T]/',$isodate)) && count($arr) == 6)
			{
				// $isodate is extended ISO8601, do nothing
			}
			else
			{
				return False;
			}

				foreach(array('year','month','mday','hour','min','sec') as $n => $name)
				{
					$date[$name] = (int)$arr[$n];
				}
				return $timestamp ? mktime($date['hour'],$date['min'],$date['sec'],
					$date['month'],$date['mday'],$date['year']) : $date;
			}

		// translate cat-ids to array with id-name pairs
		function cats2xmlrpc($cats)
		{
			if (!is_object($GLOBALS['egw']->categories))
			{
				$GLOBALS['egw']->categories = CreateObject('phpgwapi.categories');
			}
			$xcats = array();
			foreach($cats as $cat)
			{
				if ($cat)
				{
					$xcats[$cat] = stripslashes($GLOBALS['egw']->categories->id2name($cat));
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
			elseif (!is_object($GLOBALS['egw']->categories))
			{
				$GLOBALS['egw']->categories = CreateObject('phpgwapi.categories');
			}
			$cats = array();
			foreach($xcats as $cat => $name)
			{
				if ($id = $GLOBALS['egw']->categories->name2id($name))
				{
					// existing cat-name use the id
					$cat = $id;
				}
				elseif (!($org_name = stripslashes($GLOBALS['egw']->categories->id2name($cat))) || $org_name == '--')
				{
					// new cat
					$cat = $GLOBALS['egw']->categories->add(array('name' => $name,'parent' => 0));
				}
				elseif ($org_name != $name)
				{
					// cat-name edited
					list($cat_vals) =$GLOBALS['egw']->categories->return_single($cat);
					$cat_vals['name'] = $name;
					$GLOBALS['egw']->categories->edit($cat_vals);
				}
				$cats[] = (int)$cat;
			}
			return $cats;
		}

		// get list (array with id-name pairs) of all cats of $app
		function categories($complete = False,$app = '')
		{
			if (is_array($complete))
			{
				$complete = @$complete[0];
			}
			if (!$app)
			{
				list($app) = explode('.',$this->last_method);
			}

			if (!is_object($GLOBALS['egw']->categories))
			{
				$GLOBALS['egw']->categories = CreateObject('phpgwapi.categories');
			}
			if ($GLOBALS['egw']->categories->app_name != $app)
			{
				$GLOBALS['egw']->categories->categories('',$app);
			}
			$cats_arr = $GLOBALS['egw']->categories->return_sorted_array(0,False,'','','',True);
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

		function setSimpleDate($enable=True)
		{
			$this->simpledate = $enable;
		}
	}

	if(empty($GLOBALS['egw_info']['server']['xmlrpc_type']))
	{
		$GLOBALS['egw_info']['server']['xmlrpc_type'] = 'php';
	}
	include_once(EGW_API_INC.SEP.'class.xmlrpc_server_' . $GLOBALS['egw_info']['server']['xmlrpc_type'] . '.inc.php');
