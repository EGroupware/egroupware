<?php
  /**************************************************************************\
  * eGroupWare API - Translation class for phpgw lang files                  *
  * This file written by Miles Lott <milosch@groupwhere.org>                 *
  * and Dan Kuykendall <seek3r@phpgroupware.org>                             *
  * Handles multi-language support using flat files                          *
  * -------------------------------------------------------------------------*
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

	if (!defined('MAX_MESSAGE_ID_LENGTH'))
	{
		define('MAX_MESSAGE_ID_LENGTH',230);
	}

	class setup_translation
	{
		var $langarray = array();

		/*!
		@function setup_lang
		@abstract constructor for the class, loads all phrases into langarray
		@param $lang	user lang variable (defaults to en)
		*/
		function setup_translation()
		{
			$ConfigLang = get_var('ConfigLang',Array('POST','COOKIE'));

			if(!$ConfigLang)
			{
				$lang = 'en';
			}
			else
			{
				$lang = $ConfigLang;
			}

			$fn = '.' . SEP . 'lang' . SEP . 'phpgw_' . $lang . '.lang';
			if (!file_exists($fn))
			{
				$fn = '.' . SEP . 'lang' . SEP . 'phpgw_en.lang';
			}
			if (file_exists($fn))
			{
				$fp = fopen($fn,'r');
				while ($data = fgets($fp,8000))
				{
					// explode with "\t" and removing "\n" with str_replace, needed to work with mbstring.overload=7
					list($message_id,,,$content) = explode("\t",$data);
					$this->langarray[strtolower(trim($message_id))] = str_replace("\n",'',$content);
				}
				fclose($fp);
			}
		}

		/*!
		@function translate
		@abstract Translate phrase to user selected lang
		@param $key  phrase to translate
		@param $vars vars sent to lang function, passed to us
		*/
		function translate($key, $vars=False) 
		{
			$ret = $key.'*';
			$key = strtolower(trim($key));
			if (isset($this->langarray[$key]))
			{
				$ret = $this->langarray[$key];
			}
			if (is_array($vars))
			{
				foreach($vars as $n => $var)
				{
					$ret = preg_replace( '/%'.($n+1).'/', $var, $ret );
				}
			}
			return $ret;
		}

		// the following functions have been moved to phpgwapi/tanslation_sql

		function setup_translation_sql()
		{
			if (!is_object($this->sql))
			{
				include_once(EGW_API_INC.'/class.translation_sql.inc.php');
				$this->sql = new translation;
			}
		}

		function get_langs($DEBUG=False)
		{
			$this->setup_translation_sql();
			return $this->sql->get_langs($DEBUG);
		}

		function drop_langs($appname,$DEBUG=False)
		{
			$this->setup_translation_sql();
			return $this->sql->drop_langs($appname,$DEBUG);
		}

		function add_langs($appname,$DEBUG=False,$force_langs=False)
		{
			$this->setup_translation_sql();
			return $this->sql->add_langs($appname,$DEBUG,$force_langs);
		}
		
		function drop_add_all_langs($langs=False)
		{
			$this->setup_translation_sql();

			if (!$langs && !count($langs = $this->sql->get_langs()))
			{
				$langs[] = 'en';
			}
			return $this->sql->install_langs($langs,'dumpold');
		}
		
		/**
		 * List availible charsets and it's supported languages
		 * @param boolean/string $name=false name for selectbox or false to return an array
		 * @param string $selected selected charset
		 * @return string/array html for a selectbox or array with charset / languages pairs
		 */
		function get_charsets($name=false,$selected='')
		{
			$charsets = array(
				'utf-8' => 'utf-8: '.lang('all languages (incl. not listed ones)'),
			);
			if (($f = fopen('lang/languages','r')))
			{
				while(($line = fgets($f)) !== false)
				{
					list($lang,$language) = explode("\t",trim($line));
					if ($lang && ($lf = fopen("../phpgwapi/setup/phpgw_$lang.lang",'r')))
					{
						while(($line = fgets($lf)) !== false)
						{
							list($phrase,,,$charset) = explode("\t",$line);
							if ($phrase == 'charset')
							{
								$charset = trim(strtolower($charset));
								
								if ($charset != 'utf-8')
								{
									$charsets[$charset] .= (isset($charsets[$charset]) ? ', ' : $charset.': ') . $language;
								}
								break;
							}
						}
						fclose($lf);
					}
				}
				fclose($f);
			}
			if (!$name)
			{
				return $charsets;
			}
			$html = CreateObject('phpgwapi.html');
			
			return $html->select($name,trim(strtolower($selected)),$charsets,true);
		}								
	}
?>
