<?php
  /**************************************************************************\
  * phpGroupWare API - Translation class for phpgw lang files                *
  * This file written by Miles Lott <milosch@phpgroupware.org>               *
  * Handles multi-language support using flat files                          *
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
		var $lang;   // Currently loaded translations
		var $loaded = False;
		var $all_loaded = False;
		var $translator_helper = '*';

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
		@function translate
		@abstract Translate phrase to user selected lang
		@param $key  phrase to translate
		@param $vars vars sent to lang function, passed to us
		*/
		function translate($key,$vars=False) 
		{
			if(!$vars)
			{
				$vars = array();
			}
			$ret  = $key;
			$_key = strtolower($key);

			if(!@isset($this->lang[$_key]) && !$this->loaded)
			{
				$this->load_langs();
			}
			if(!@isset($this->lang[$_key]) &&
				($this->currentapp == 'admin' || $this->currentapp == 'preferences' || $this->currentapp == 'home') &&
				!$this->all_loaded
			)
			{
				$this->load_langs(True);
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

		function load_langs($all=False)
		{
			if(isset($GLOBALS['phpgw_info']['user']['preferences']['common']['lang']) &&
				$GLOBALS['phpgw_info']['user']['preferences']['common']['lang'])
			{
				$this->userlang = $GLOBALS['phpgw_info']['user']['preferences']['common']['lang'];
			}

			if($all)
			{
				@reset($GLOBALS['phpgw_info']['user']['apps']);
				while(list(,$appname) = @each($GLOBALS['phpgw_info']['user']['apps']))
				{
					$this->add_app($appname['name']);
				}
				$this->all_loaded = True;
			}
			else
			{
				$this->add_app('phpgwapi');
				$this->add_app($this->currentapp);
			}

			$this->loaded = True;
		}

		/*!
		@function add_app
		@abstract loads all app phrases into lang
		@param $lang	user lang variable (defaults to en)
		*/
		function add_app($app)
		{
			//echo '<br>add_app(): adding phrases from: ' . $app;
			define('SEP',filesystem_separator());

			$userlang = $this->userlang ? $this->userlang : 'en';
			//echo '<br>add_app(): userlang is: ' . $userlang;

			$fn = PHPGW_SERVER_ROOT . SEP . $app . SEP . 'setup' . SEP . 'phpgw_' . $userlang . '.lang';
			if(!@file_exists($fn))
			{
				$fn = PHPGW_SERVER_ROOT . SEP . $app . SEP . 'setup' . SEP . 'phpgw_en.lang';
			}

			if(@file_exists($fn))
			{
				$fp = fopen($fn,'r');
				while($data = fgets($fp,8000))
				{
					list($message_id,$app_name,$null,$content) = explode("\t",$data);
					//echo '<br>add_app(): adding phrase: $this->lang["'.$message_id.'"]=' . trim($content);
					$this->lang[$message_id] = trim($content);
				}
				fclose($fp);
			}
		}
	}
?>
