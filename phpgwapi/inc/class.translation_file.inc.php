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

	// This should be considered experimental.  It works, at the app level.
	// But, for admin and prefs it really slows things down.  See the note
	// in the translate() function.
	// To use, set $phpgw_info["server"]["translation_system"] = "file"; in
	// class.translation.inc.php
	class translation
	{
		var $langarray;   // Currently loaded translations
		var $loaded_apps = array(); // Loaded app langs

		/*!
		@function translate
		@abstract Translate phrase to user selected lang
		@param $key  phrase to translate
		@param $vars vars sent to lang function, passed to us
		*/
		function translation()
		{
			global $phpgw_info, $lang;
			$this->add_app('phpgwapi',$lang);
		}

		function isin_array($needle,$haystack)
		{
			while (list ($k,$v) = each($haystack))
			{
				if ($v == $needle)
				{
					return True;
				}
			}
			return False;
		}

		function translate($key, $vars=False) 
		{
			global $phpgw_info, $lang;

			if (!$this->isin_array($phpgw_info['flags']['currentapp'],$this->loaded_apps) &&
				$phpgw_info['flags']['currentapp'] != 'home')
			{
				//echo '<br>loading app "' . $phpgw_info['flags']['currentapp'] . '" for the first time.';
				$this->add_app($phpgw_info['flags']['currentapp'],$lang);
			}
			elseif ($phpgw_info['flags']['currentapp'] == 'admin' ||
				$phpgw_info['flags']['currentapp'] == 'preferences')
			{
				// This is done because for these two apps, all langs must be loaded.
				// Note we cannot load for navbar, since it would slow down EVERY page.
				// This is true until all common/admin/prefs langs are in the api file only.
				@ksort($phpgw_info['apps']);
				while(list($x,$app) = each($phpgw_info['apps']))
				{
					if (!$this->isin_array($app['name'],$this->loaded_apps))
					{
						//echo '<br>loading app "' . $app['name'] . '" for the first time.';
						$this->add_app($app['name'],$lang);
					}
				}
			}

			if (!$vars)
			{
				$vars = array();
			}

			$ret = $key;

			if (isset($this->langarray[strtolower ($key)]) && $this->langarray[strtolower ($key)])
			{
				$ret = $this->langarray[strtolower ($key)];
			}
			else
			{
				$ret = $key."*";
			}
			$ndx = 1;
			while( list($key,$val) = each( $vars ) )
			{
				$ret = preg_replace( "/%$ndx/", $val, $ret );
				++$ndx;
			}
			return $ret;
		}

		/*!
		@function add_app
		@abstract loads all app phrases into langarray
		@param $lang	user lang variable (defaults to en)
		*/
		function add_app($app,$lang='')
		{
			global $phpgw_info;

			define('SEP',filesystem_separator());

			//echo '<br>add_app(): called with app="' . $app . '", lang="' . $userlang . '"';
			if (!isset($lang) || !$lang)
			{
				if (isset($phpgw_info['user']['preferences']['common']['lang']) &&
					$phpgw_info['user']['preferences']['common']['lang'])
				{
					$userlang = $phpgw_info['user']['preferences']['common']['lang'];
				}
				else
				{
					$userlang = 'en';
				}
			}
			else
			{
				$userlang = $lang;
			}

			$fn = PHPGW_SERVER_ROOT . SEP . $app . SEP . 'setup' . SEP . 'phpgw_' . $userlang . '.lang';
			if (!file_exists($fn))
			{
				$fn = PHPGW_SERVER_ROOT . SEP . $app . SEP . 'setup' . SEP . 'phpgw_en.lang';
			}

			if (file_exists($fn))
			{
				$fp = fopen($fn,'r');
				while ($data = fgets($fp,8000))
				{
					list($message_id,$app_name,$null,$content) = explode("\t",$data);
					//echo '<br>add_app(): adding phrase: $this->langarray["'.$message_id.'"]=' . trim($content);
					$this->langarray[$message_id] = trim($content);
				}
				fclose($fp);
			}
			// stuff class array listing apps that are included already
			$this->loaded_apps[] = $app;
		}
	}
?>
