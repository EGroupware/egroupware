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

	// define the maximal length of a message_id, all message_ids have to be unique 
	// in this length, our column is varchar 255, but addslashes might add some length
	define('MAX_MESSAGE_ID_LENGTH',230);	
	
	class translation
	{
		var $userlang = 'en';
		var $loaded_apps = array();

		function init()
		{
			// post-nuke and php-nuke are using $GLOBALS['lang'] too
			// but not as array!
			// this produces very strange results
			if (!is_array($GLOBALS['lang']))
			{
				$GLOBALS['lang'] = array();
			}

			if ($GLOBALS['phpgw_info']['user']['preferences']['common']['lang'])
			{
				$this->userlang = $GLOBALS['phpgw_info']['user']['preferences']['common']['lang'];
			}
			$this->add_app('common');
			$this->add_app($GLOBALS['phpgw_info']['flags']['currentapp']);
		}

		function translate($key, $vars=false )
		{
			if (!$vars)
			{
				$vars = array();
			}
			if (!is_array($GLOBALS['lang']) || !count($GLOBALS['lang']))
			{
				$this->init();
			}
			$ret = $key.'*';	// save key if we dont find a translation

			$key = strtolower(trim(substr($key,0,MAX_MESSAGE_ID_LENGTH)));

			if (isset($GLOBALS['lang'][$key]))
			{
				$ret = $GLOBALS['lang'][$key];
			}
			$ndx = 1;
			foreach($vars as $val)
			{
				$ret = preg_replace( "/%$ndx/", $val, $ret );
				++$ndx;
			}
			return $ret;
		}

		function add_app($app,$lang=False)
		{
			$lang = $lang ? $lang : $this->userlang;

			if (!isset($this->loaded_apps[$app]) || $this->loaded_apps[$app] != $lang)
			{
				$sql = "select message_id,content from phpgw_lang where lang='".$lang."' and app_name='".$app."'";
				$GLOBALS['phpgw']->db->query($sql,__LINE__,__FILE__);
				while ($GLOBALS['phpgw']->db->next_record())
				{
					$GLOBALS['lang'][strtolower ($GLOBALS['phpgw']->db->f('message_id'))] = $GLOBALS['phpgw']->db->f('content');
				}
				$this->loaded_apps[$app] = $lang;
			}
		}

		function get_installed_langs()
		{
			if (!is_array($this->langs))
			{
				$GLOBALS['phpgw']->db->query("SELECT DISTINCT l.lang,ln.lang_name FROM phpgw_lang l,phpgw_languages ln WHERE l.lang = ln.lang_id",__LINE__,__FILE__);
				if (!$GLOBALS['phpgw']->db->num_rows())
				{
					return False;
				}
				while ($GLOBALS['phpgw']->db->next_record())
				{
					$this->langs[$GLOBALS['phpgw']->db->f('lang')] = $GLOBALS['phpgw']->db->f('lang_name');
				}
			}
			return $this->langs;
		}
	}
