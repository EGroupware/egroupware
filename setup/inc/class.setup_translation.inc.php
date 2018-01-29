<?php
/**
 * EGroupware Setup translation class
 *
 * @link http://www.egroupware.org
 * @package setup
 * @author Dan Kuykendall <seek3r@phpgroupware.org>
 * @author Miles Lott <milos@groupwhere.org>
 * @author Ralf Becker <RalfBecker-AT-outdoor-training.de>
 * @license http://opensource.org/licenses/gpl-license.php GPL - GNU General Public License
 * @version $Id$
 */

use EGroupware\Api;

if (!defined('MAX_MESSAGE_ID_LENGTH'))
{
	define('MAX_MESSAGE_ID_LENGTH',128);
}

// Define prefix for langfiles (historically 'phpgw_')
define('EGW_LANGFILE_PREFIX', 'egw_');

/**
 * Setup translation class
 *
 */
class setup_translation
{
	var $langarray = array();

	/**
	 * Marker to show behind untranslated phrases, default none
	 *
	 * @var string
	 */
	var $no_translation_marker = '';//'*';

	/**
	 * constructor for the class, loads all phrases into langarray
	 *
	 * @param $lang	user lang variable (defaults to en)
	 */
	function __construct()
	{
		$ConfigLang = setup::get_lang(true);

		if(!$ConfigLang)
		{
			$lang = 'en';
		}
		else
		{
			$lang = $ConfigLang;
		}

		$fn = './lang/' . EGW_LANGFILE_PREFIX . $lang . '.lang';
		if (!file_exists($fn))
		{
			$fn = './lang/' . EGW_LANGFILE_PREFIX .'en.lang';
		}
		if (file_exists($fn) && ($fp = fopen($fn,'r')))
		{
			while (($data = fgets($fp,8000)))
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
	 * @param $_key  phrase to translate
	 * @param $vars vars sent to lang function, passed to us
	 */
	function translate($_key, $vars=False)
	{
		static $placeholders = array('%1','%2','%3','%4','%5','%6','%7','%8','%9','%10');

		$ret = $_key . $this->no_translation_marker;
		$key = strtolower(trim($_key));
		if (isset($this->langarray[$key]))
		{
			$ret = $this->langarray[$key];
		}
		if ($GLOBALS['egw_setup']->system_charset != $this->langarray['charset'])
		{
			$ret = Api\Translation::convert($ret,$this->langarray['charset']);
		}
		if (is_array($vars))
		{
			$ret = str_replace($placeholders, $vars, $ret);
		}
		return $ret;
	}

	/**
	 * Languages we support (alphabetically sorted)
	 *
	 * @param boolean $array_values =true true: values are an array, false values are just the descriptiong
	 * @return array
	 */
	static function get_supported_langs($array_values=true)
	{
		$f = fopen(EGW_SERVER_ROOT.'/setup/lang/languages','rb');
		while(($line = fgets($f)))
		{
			list($lang2,$descr) = explode("\t",$line,2);
			$lang = trim($lang2);
			if ($array_values)
			{
				$languages[$lang]['lang']  = $lang;
				$languages[$lang]['descr'] = trim($descr);
				$languages[$lang]['available'] = False;
			}
			else
			{
				$languages[$lang] = trim($descr);
			}
		}
		fclose($f);

		if ($array_values)
		{
			$d = dir(EGW_SERVER_ROOT.'/setup/lang');
			while(($file = $d->read()))
			{
				$matches = null;
				if(preg_match('/^(php|e)gw_([-a-z]+).lang$/i',$file,$matches))
				{
					$languages[$matches[2]]['available'] = True;
				}
			}
			$d->close();
			uasort($languages, function($a, $b)
			{
				return strcmp(@$a['descr'], @$b['descr']);
			});
		}
		else
		{
			asort($languages);
		}
		//_debug_array($languages);
		return $languages;
	}

	/**
	 * List availible charsets and it's supported languages
	 * @param boolean|string $name =false name for selectbox or false to return an array
	 * @param string $selected selected charset
	 * @return string|array html for a selectbox or array with charset / languages pairs
	 */
	static function get_charsets($name=false,$selected='')
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
		return Api\Html::select($name,trim(strtolower($selected)),$charsets,true);
	}
}
