<?php
  /**************************************************************************\
  * phpGroupWare API - Translation class for SQL                             *
  * This file written by Joseph Engo <jengo@phpgroupware.org>                *
  * and Dan Kuykendall <seek3r@phpgroupware.org>                             *
  * and Miles Lott <milosch@phpgroupware.org>                                *
  * Handles multi-language support using SQL                                 *
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
	if (!defined('MAX_MESSAGE_ID_LENGTH'))
	{
		define('MAX_MESSAGE_ID_LENGTH',230);	
	}

	class translation
	{
		var $lang = array();
		var $userlang   = '';
		var $currentapp = '';
		var $loaded = False;
		var $translator_helper = '*';

		/*!
		 @function translation
		 @abstract class constructor - sets class vars for currentapp and user lang preference
		 @discussion User lang defaults to 'en'.
		*/
		function translation()
		{
			if(isset($GLOBALS['phpgw_info']['user']['preferences']['common']['lang']) &&
				$GLOBALS['phpgw_info']['user']['preferences']['common']['lang'])
			{
				$this->userlang = $GLOBALS['phpgw_info']['user']['preferences']['common']['lang'];
			}

			$this->currentapp = $GLOBALS['phpgw_info']['flags']['currentapp'];
		}

		/*!
		 @function load_langs
		 @abstract loads up translations into $this->lang based on currentapp and userlang preference.
		 @discussion This also loads translations with appname='common' or appname='all'.
		*/
		function load_langs()
		{
			if($this->userlang == '')
			{
				$this->userlang = $GLOBALS['phpgw_info']['user']['preferences']['common']['lang'] ?
					$GLOBALS['phpgw_info']['user']['preferences']['common']['lang'] : 'en';
			}
			$sql = "SELECT message_id,content FROM phpgw_lang WHERE lang LIKE '" . $this->userlang
				. "' AND (app_name LIKE '" . $this->currentapp
				. "' OR app_name LIKE 'common' OR app_name LIKE 'all') ORDER BY app_name ";

			if(strcasecmp($this->currentapp, 'common') > 0)
			{
				$sql .= 'ASC';
			}
			else
			{
				$sql .= 'DESC';
			}

			$GLOBALS['phpgw']->db->query($sql,__LINE__,__FILE__);
			$GLOBALS['phpgw']->db->next_record();

			$count = $GLOBALS['phpgw']->db->num_rows();
			for($idx = 0; $idx < $count; ++$idx)
			{
				$this->lang[strtolower($GLOBALS['phpgw']->db->f('message_id'))] = $GLOBALS['phpgw']->db->f('content');
				$GLOBALS['phpgw']->db->next_record();
			}
			/* Done stuffing the array.  If someone prefers to have $GLOBALS['lang'] set to this as before,
			   it could be done here. - $GLOBALS['lang'] = $this->lang;
			*/
			$this->loaded = True;
		}

		/*!
		 @function translate
		 @abstract Return the translated string from $this->lang, if it exists.  If no translation exists, return the same string with an asterisk.
		 @discussion This should be called from the global function lang(), not directly.
		 @syntax translate('translate %1',array('replacement'));  OR  translate('translate this');
		 @example translate('translate this %1', array('lang entry'));  returns 'Translate this Lang Entry' or 'translate this lang %1*'
		*/
		function translate($key,$vars=False) 
		{
			if(!$vars)
			{
				$vars = array();
			}
			$ret  = $key;
			$_key = trim(substr(strtolower($key),0,MAX_MESSAGE_ID_LENGTH));

			if(!@isset($this->lang[$_key]) && !$this->loaded)
			{
				$this->load_langs();
			}

			$ret = @isset($this->lang[$_key]) ? $this->lang[$_key] : $key . $this->translator_helper;	

			$ndx = 1;
			while(list($key,$val) = each($vars))
			{
				$ret = preg_replace( "/%$ndx/", $val, $ret );
				++$ndx;
			}
			return $ret;
		}

		/*!
		 @function add_app
		 @abstract Add an additional app's translations to $this->lang
		*/
		function add_app($app) 
		{
			$sql = "SELECT message_id,content FROM phpgw_lang WHERE lang LIKE '" . $this->userlang
				. "' AND app_name LIKE '" . $app . "'";

			$GLOBALS['phpgw']->db->query($sql,__LINE__,__FILE__);
			$GLOBALS['phpgw']->db->next_record();

			$count = $GLOBALS['phpgw']->db->num_rows();
			for($idx = 0; $idx < $count; ++$idx)
			{
				$this->lang[strtolower($GLOBALS['phpgw']->db->f('message_id'))] = $GLOBALS['phpgw']->db->f('content');
				$GLOBALS['phpgw']->db->next_record();
			}
		}
		
		/*!
		 @function get_installed_langs
		 @returns array of installed langs, in the form eg. 'de' => 'German'
		*/
		function get_installed_langs()
		{
			$GLOBALS['phpgw']->db->query("SELECT DISTINCT l.lang,ln.lang_name FROM phpgw_lang l,phpgw_languages ln WHERE l.lang = ln.lang_id",__LINE__,__FILE__);
			if (!$GLOBALS['phpgw']->db->num_rows())
			{
				return False;
			}
			while ($GLOBALS['phpgw']->db->next_record())
			{
				$langs[$GLOBALS['phpgw']->db->f('lang')] = $GLOBALS['phpgw']->db->f('lang_name');
			}
			return $langs;
		}
	}
