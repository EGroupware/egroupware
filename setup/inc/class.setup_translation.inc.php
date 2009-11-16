<?php
/**
 * Setup
 *
 * @link http://www.egroupware.org
 * @package setup
 * @author Dan Kuykendall <seek3r@phpgroupware.org>
 * @author Miles Lott <milos@groupwhere.org>
 * @author Ralf Becker <RalfBecker-AT-outdoor-training.de>
 * @license http://opensource.org/licenses/gpl-license.php GPL - GNU General Public License
 * @version $Id$
 */

	if (!defined('MAX_MESSAGE_ID_LENGTH'))
	{
		define('MAX_MESSAGE_ID_LENGTH',128);
	}

	// Define prefix for langfiles (historically 'phpgw_')
	define('EGW_LANGFILE_PREFIX', 'egw_');

	class setup_translation
	{
		var $langarray = array();
		
		var $no_translation_marker = '*';

		/**
		 * constructor for the class, loads all phrases into langarray
		 *
		 * @param $lang	user lang variable (defaults to en)
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
			
			$fn = '.' . SEP . 'lang' . SEP . EGW_LANGFILE_PREFIX . $lang . '.lang';
			if (!file_exists($fn))
			{
				$fn = '.' . SEP . 'lang' . SEP . EGW_LANGFILE_PREFIX .'en.lang';
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
				
				if (!$GLOBALS['egw_setup']->system_charset)
				{
					$GLOBALS['egw_setup']->system_charset = $this->langarray['charset'];
				}
			}
		}

		/**
		 * Translate phrase to user selected lang
		 *
		 * @param $key  phrase to translate
		 * @param $vars vars sent to lang function, passed to us
		 */
		function translate($key, $vars=False) 
		{
			$ret = $key . $this->no_translation_marker;
			$key = strtolower(trim($key));
			if (isset($this->langarray[$key]))
			{
				$ret = $this->langarray[$key];
			}
			if ($GLOBALS['egw_setup']->system_charset != $this->langarray['charset'])
			{
				if (!is_object($this->sql))
				{
					$this->setup_translation_sql();
				}
				$ret = $this->sql->convert($ret,$this->langarray['charset']);
			}
			if (is_array($vars))
			{
				foreach($vars as $n => $var)
				{
					$ret = str_replace( '%'.($n+1), $var, $ret );
				}
			}
			return $ret;
		}

		// the following functions have been moved to phpgwapi/tanslation_sql

		function setup_translation_sql()
		{
			if (!is_object($this->sql) || is_object($GLOBALS['egw_setup']->db) && !is_object($this->sql->db))
			{
				include_once(EGW_API_INC.'/class.translation.inc.php');
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
					if ($lang && ($lf = @fopen("../phpgwapi/setup/" . EGW_LANGFILE_PREFIX . "$lang.lang",'r')))
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
			$html =& CreateObject('phpgwapi.html');
			
			return $html->select($name,trim(strtolower($selected)),$charsets,true);
		}								
	}
?>
