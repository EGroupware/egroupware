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
	// To use, set $GLOBALS['phpgw_info']['server']['translation_system'] = 'file'; in
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
		function translation($appname='phpgwapi',$loadlang='')
		{
			global $lang;
			if($loadlang)
			{
				$lang = $loadlang;
			}
			$this->add_app($appname,$lang);

			if(!isset($GLOBALS['phpgw_setup']))
			{
				$this->system_charset = $GLOBALS['phpgw_info']['server']['system_charset'];
			}
			else
			{
				$GLOBALS['phpgw']->db->query("SELECT config_value FROM phpgw_config WHERE config_app='phpgwapi' AND config_name='system_charset'",__LINE__,__FILE__);
				if($GLOBALS['phpgw']->db->next_record())
				{
					$this->system_charset = $GLOBALS['phpgw']->db->f(0);
				}
			}
			// load multi-byte-string-extension if needed, and set its internal encodeing to your system_charset
			if($this->system_charset && substr($this->system_charset,0,9) != 'iso-8859-1')
			{
				if($this->mbstring = extension_loaded('mbstring') || @dl(PHP_SHLIB_PREFIX.'mbstring.'.PHP_SHLIB_SUFFIX))
				{
					ini_set('mbstring.internal_encoding',$this->system_charset);
					if(ini_get('mbstring.func_overload') < 7)
					{
						if($warnings) echo "<p>Warning: Please set <b>mbstring.func_overload = 7</b> in your php.ini for useing <b>$this->system_charset</b> as your charset !!!</p>\n";
					}
				}
				else
				{
					if($warnings) echo "<p>Warning: Please get and/or enable the <b>mbstring extension</b> in your php.ini for useing <b>$this->system_charset</b> as your charset, we are defaulting to <b>iconv</b> for now !!!</p>\n";
				}
			}
		}

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
			if (!count($GLOBALS['lang']))
			{
				$this->userlang = 'en';
				$this->add_app('common');
			}
			$this->add_app($GLOBALS['phpgw_info']['flags']['currentapp']);
		}
		/*
		@function charset
		@abstract returns the charset to use (!$lang) or the charset of the lang-files or $lang
		*/
		function charset($lang=False)
		{
			if($lang)
			{
				if(!isset($this->charsets[$lang]))
				{
					$GLOBALS['phpgw']->db->query("SELECT content FROM phpgw_lang WHERE lang='$lang' AND message_id='charset' AND app_name='common'",__LINE__,__FILE__);
					$this->charsets[$lang] = $GLOBALS['phpgw']->db->next_record() ? strtolower($GLOBALS['phpgw']->db->f(0)) : 'iso-8859-1';
				}
				return $this->charsets[$lang];
			}
			if($this->system_charset)	// do we have a system-charset ==> return it
			{
				return $this->system_charset;
			}
			// if no translations are loaded (system-startup) use a default, else lang('charset')
			return !is_array($GLOBALS['lang']) ? 'iso-8859-1' : strtolower($this->translate('charset'));
		}

		function isin_array($needle,$haystack)
		{
			while(list($k,$v) = each($haystack))
			{
				if($v == $needle)
				{
					return True;
				}
			}
			return False;
		}

		function translate($key, $vars=False) 
		{
			if(!$this->isin_array($GLOBALS['phpgw_info']['flags']['currentapp'],$this->loaded_apps) &&
				$GLOBALS['phpgw_info']['flags']['currentapp'] != 'home')
			{
				//echo '<br>loading app "' . $GLOBALS['phpgw_info']['flags']['currentapp'] . '" for the first time.';
				$this->add_app($GLOBALS['phpgw_info']['flags']['currentapp'],$GLOBALS['lang']);
			}
			elseif($GLOBALS['phpgw_info']['flags']['currentapp'] == 'admin' ||
				$GLOBALS['phpgw_info']['flags']['currentapp'] == 'preferences')
			{
				// This is done because for these two apps, all langs must be loaded.
				// Note we cannot load for navbar, since it would slow down EVERY page.
				// This is true until all common/admin/prefs langs are in the api file only.
				@ksort($GLOBALS['phpgw_info']['apps']);
				while(list($x,$app) = @each($GLOBALS['phpgw_info']['apps']))
				{
					if(!$this->isin_array($app['name'],$this->loaded_apps))
					{
						//echo '<br>loading app "' . $app['name'] . '" for the first time.';
						$this->add_app($app['name'],$GLOBALS['lang']);
					}
				}
			}

			if(!$vars)
			{
				$vars = array();
			}

			$ret = $key;

			if(isset($this->langarray[strtolower($key)]) && $this->langarray[strtolower($key)])
			{
				$ret = $this->langarray[strtolower($key)];
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

		function autoload_changed_langfiles()
		{
			return;
		}
		function drop_langs($appname,$DEBUG=False)
		{
			if($DEBUG)
			{
				echo '<br>drop_langs(): Working on: ' . $appname;
				echo '<br>drop_langs(): Not needed with file-based lang class.';
			}
		}

		function add_langs($appname,$DEBUG=False,$force_langs=False)
		{
			if($DEBUG)
			{
				echo '<br>add_langs(): Working on: ' . $appname;
				echo '<br>add_langs(): Not needed with file-based lang class.';
			}
		}

		/*!
		@function add_app
		@abstract loads all app phrases into langarray
		@param $lang	user lang variable (defaults to en)
		*/
		function add_app($app,$lang='')
		{
			define('SEP',filesystem_separator());

			//echo '<br>add_app(): called with app="' . $app . '", lang="' . $userlang . '"';
			if(!isset($lang) || !$lang)
			{
				if(isset($GLOBALS['phpgw_info']['user']['preferences']['common']['lang']) &&
					$GLOBALS['phpgw_info']['user']['preferences']['common']['lang'])
				{
					$userlang = $GLOBALS['phpgw_info']['user']['preferences']['common']['lang'];
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
			if(!file_exists($fn))
			{
				$fn = PHPGW_SERVER_ROOT . SEP . $app . SEP . 'setup' . SEP . 'phpgw_en.lang';
			}

			if(file_exists($fn))
			{
				$fp = fopen($fn,'r');
				while($data = fgets($fp,8000))
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
