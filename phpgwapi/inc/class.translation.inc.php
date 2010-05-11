<?php
/**
 * eGroupWare API - Translations
 *
 * @link http://www.egroupware.org
 * @author Joseph Engo <jengo@phpgroupware.org>
 * @author Dan Kuykendall <seek3r@phpgroupware.org>
 * Copyright (C) 2000, 2001 Joseph Engo
 * @license http://opensource.org/licenses/lgpl-license.php LGPL - GNU Lesser General Public License
 * @package api
 * @version $Id$
 */

/**
 * eGroupWare API - Translations
 *
 * All methods of this class can now be called static.
 *
 * Translations are cached tree-wide via egw_cache class.
 */
class translation
{
	/**
	 * Language of current user, will be set by init()
	 *
	 * @var string
	 */
	static $userlang = 'en';

	/**
	 * Already loaded translations by applicaton
	 *
	 * @var array $app => $lang pairs
	 */
	static $loaded_apps = array();

	/**
	 * Loaded phrases
	 *
	 * @var array $message_id => $translation pairs
	 */
	static $lang_arr = array();

	/**
	 * Tables used by this class
	 */
	const LANG_TABLE = 'egw_lang';
	const LANGUAGES_TABLE = 'egw_languages';

	/**
	 * Prefix of language files (historically 'phpgw_')
	 */
	const LANGFILE_PREFIX = 'egw_';
	const OLD_LANGFILE_PREFIX = 'phpgw_';

	/**
	 * Maximal length of a message_id, all message_ids have to be unique in this length,
	 * our column is varchar 128
	 */
	const MAX_MESSAGE_ID_LENGTH = 128;


	/**
	 * Reference to global db-class
	 *
	 * @var egw_db
	 */
	static $db;
	/**
	 * Mark untranslated strings with an asterisk (*)
	 *
	 * @var boolean
	 */
	static $markuntranslated=false;

	/**
	 * System charset
	 *
	 * @var string
	 */
	static $system_charset;

	/**
	 * Is the mbstring extension available
	 *
	 * @var boolean
	 */
	static $mbstring;
	/**
	 * Internal encoding / charset of mbstring (if loaded)
	 *
	 * @var string
	 */
	static $mbstring_internal_encoding;

	/**
	 * Application which translations have to be cached instance- and NOT tree-specific
	 *
	 * @var array
	 */
	static $instance_specific_translations = array('loginscreen','mainscreen');

	/**
	 * returns the charset to use (!$lang) or the charset of the lang-files or $lang
	 *
	 * @param string/boolean $lang=False return charset of the active user-lang, or $lang if specified
	 * @return string charset
	 */
	static function charset($lang=False)
	{
		static $charsets = array();

		if ($lang)
		{
			if (!isset($charsets[$lang]))
			{
				if (!($charsets[$lang] = self::$db->select(self::LANG_TABLE,'content',array(
					'lang'		=> $lang,
					'message_id'=> 'charset',
					'app_name'	=> 'common',
				),__LINE__,__FILE__)->fetchColumn()))
				{
					$charsets[$lang] = 'utf-8';
				}
			}
			return $charsets[$lang];
		}
		if (self::$system_charset)	// do we have a system-charset ==> return it
		{
			$charset = self::$system_charset;
		}
		else
		{
			// if no translations are loaded (system-startup) use a default, else lang('charset')
			$charset = !self::$lang_arr ? 'utf-8' : strtolower(self::translate('charset'));
		}
		// we need to set our charset as mbstring.internal_encoding if mbstring.func_overlaod > 0
		// else we get problems for a charset is different from the default utf-8
		if (ini_get('mbstring.func_overload') && self::$mbstring_internal_encoding != $charset)
		{
			ini_set('mbstring.internal_encoding',self::$mbstring_internal_encoding = $charset);
		}
		return $charset;
	}

	/**
	 * Initialises global lang-array and loads the 'common' and app-spec. translations
	 *
	 * @param boolean $load_translations=true should we also load translations for common and currentapp
	 */
	static function init($load_translations=true)
	{
		if (!isset(self::$db))
		{
			self::$db = isset($GLOBALS['egw_setup']) && isset($GLOBALS['egw_setup']->db) ? $GLOBALS['egw_setup']->db : $GLOBALS['egw']->db;
		}
		if (!isset($GLOBALS['egw_setup']))
		{
			self::$system_charset = $GLOBALS['egw_info']['server']['system_charset'];
		}
		else
		{
			self::$system_charset =& $GLOBALS['egw_setup']->system_charset;
		}
		if ((self::$mbstring = check_load_extension('mbstring')))
		{
			if(!empty(self::$system_charset))
			{
				ini_set('mbstring.internal_encoding',self::$system_charset);
			}
		}
		self::$markuntranslated = (boolean) $GLOBALS['egw_info']['server']['markuntranslated'];

		if ($load_translations)
		{
			if ($GLOBALS['egw_info']['user']['preferences']['common']['lang'])
			{
				self::$userlang = $GLOBALS['egw_info']['user']['preferences']['common']['lang'];
			}
			self::add_app('common');
			if (!count(self::$lang_arr))
			{
				self::$userlang = 'en';
				self::add_app('common');
			}
			self::add_app($GLOBALS['egw_info']['flags']['currentapp']);
		}
	}

	/**
	 * translates a phrase and evtl. substitute some variables
	 *
	 * @param string $key phrase to translate, may contain placeholders %N (N=1,2,...) for vars
	 * @param array/boolean $vars=false vars to replace the placeholders, or false for none
	 * @param string $not_found='*' what to add to not found phrases, default '*'
	 * @return string with translation
	 */
	static function translate($key, $vars=false, $not_found='*' )
	{
		if (!self::$lang_arr)
		{
			self::init();
		}
		$ret = $key;				// save key if we dont find a translation
		if ($not_found && self::$markuntranslated) $ret .= $not_found;

		if (isset(self::$lang_arr[$key]))
		{
			$ret = self::$lang_arr[$key];
		}
		else
		{
			$new_key = strtolower(substr($key,0,self::MAX_MESSAGE_ID_LENGTH));

			if (isset(self::$lang_arr[$new_key]))
			{
				// we save the original key for performance
				$ret = self::$lang_arr[$key] =& self::$lang_arr[$new_key];
			}
		}
		if (is_array($vars) && count($vars))
		{
			if (count($vars) > 1)
			{
				static $placeholders = array('%3','%2','%1','|%2|','|%3|','%4','%5','%6','%7','%8','%9','%10');
				// to cope with $vars[0] containing '%2' (eg. an urlencoded path like a referer),
				// we first replace '%2' in $ret with '|%2|' and then use that as 2. placeholder
				// we do that for %3 as well, ...
				$vars = array_merge(array('|%3|','|%2|'),$vars);	// push '|%2|' (and such) as first replacement on $vars
				$ret = str_replace($placeholders,$vars,$ret);
			}
			else
			{
				$ret = str_replace('%1',$vars[0],$ret);
			}
		}
		return $ret;
	}

	/**
	 * Adds translations for an application
	 *
	 * By default the translations are read from the tree-wide cache
	 *
	 * @param string $app name of the application to add (or 'common' for the general translations)
	 * @param string|boolean $lang=false 2 or 5 char lang-code or false for the users language
	 */
	static function add_app($app,$lang=False)
	{
		$lang = $lang ? $lang : self::$userlang;
		if (!isset(self::$loaded_apps[$app]) || self::$loaded_apps[$app] != $lang)
		{
			//$start = microtime(true);
			// for loginscreen we have to use a instance specific cache!
			$loaded =& egw_cache::getCache(in_array($app,self::$instance_specific_translations) ? egw_cache::INSTANCE : egw_cache::TREE,
				__CLASS__,$app.':'.$lang,array(__CLASS__,'load_app'),array($app,$lang));

			// we have to use array_merge! (+= does not overwrite common translations with different ones in an app)
			// array_merge messes up translations of numbers, which make no sense and should be avoided anyway.
			self::$lang_arr = array_merge(self::$lang_arr,$loaded);
			self::$loaded_apps[$app] = $lang;
			//error_log(__METHOD__."($app,$lang) took ".(1000*(microtime(true)-$start))." ms, loaded ".count($loaded)." phrases -> total=".count(self::$lang_arr).": ".function_backtrace());
		}
	}

	/**
	 * Loads translations for an application from the database or direct from the lang-file for setup
	 *
	 * Never use directly, use add_app(), which employes caching (it has to be public, to act as callback for the cache!).
	 *
	 * @param string $app name of the application to add (or 'common' for the general translations)
	 * @param string $lang=false 2 or 5 char lang-code or false for the users language
	 * @return array the loaded strings
	 */
	static function &load_app($app,$lang)
	{
		//$start = microtime(true);
		if ($app == 'setup')
		{
			$loaded =& self::load_setup($lang);
		}
		else
		{
			if (is_null(self::$db)) self::init(false);
			$loaded = array();
			foreach(self::$db->select(self::LANG_TABLE,'message_id,content',array(
				'lang'		=> $lang,
				'app_name'	=> $app,
			),__LINE__,__FILE__) as $row)
			{
				$loaded[strtolower($row['message_id'])] = $row['content'];
			}
		}
		//error_log(__METHOD__."($app,$lang) took ".(1000*(microtime(true)-$start))." ms to load ".count($loaded)." phrases");
		return $loaded;
	}

	/**
	 * Adds setup's translations, they are not in the DB!
	 *
	 * @param string $lang 2 or 5 char lang-code
	 * @return array with loaded phrases
	 */
	static protected function &load_setup($lang)
	{
		foreach(array(
			EGW_SERVER_ROOT.'/setup/lang/' . self::LANGFILE_PREFIX . $lang . '.lang',
			EGW_SERVER_ROOT.'/setup/lang/' . self::OLD_LANGFILE_PREFIX . $lang . '.lang',
			EGW_SERVER_ROOT.'/setup/lang/' . self::LANGFILE_PREFIX . 'en.lang',
			EGW_SERVER_ROOT.'/setup/lang/' . self::OLD_LANGFILE_PREFIX . 'en.lang',
		) as $fn)
		{
			if (file_exists($fn) && ($fp = fopen($fn,'r')))
			{
				$phrases = array();
				while ($data = fgets($fp,8000))
				{
					// explode with "\t" and removing "\n" with str_replace, needed to work with mbstring.overload=7
					list($message_id,,,$content) = explode("\t",$data);
					$phrases[strtolower(trim($message_id))] = str_replace("\n",'',$content);
				}
				fclose($fp);

				return self::convert($phrases,$phrases['charset']);
			}
		}
		return array(); // nothing found (should never happen, as the en translations always exist)
	}

	/**
	 * Cached languages
	 *
	 * @var array
	 */
	static $langs;

	/**
	 * returns a list of installed langs
	 *
	 * @param boolean $force_read=false force a re-read of the languages
	 * @return array with lang-code => descriptiv lang-name pairs
	 */
	static function get_installed_langs($force_read=false)
	{
		if (!is_array(self::$langs) || $force_read)
		{
			if (is_null(self::$db)) self::init(false);

			// we only cache the translation installed for the instance, not the return of this method, which is user-language dependent
			self::$langs = egw_cache::getInstance(__CLASS__,'installed_langs',array(__CLASS__,'read_installed_langs'));

			if (!self::$langs)
			{
				return false;
			}
			foreach(self::$langs as $lang => $name)
			{
				self::$langs[$lang] = self::translate($name,False,'');
			}
			uasort(self::$langs,'strcasecmp');
		}
		return self::$langs;
	}

	/**
	 * Read the installed languages from the db
	 *
	 * Never use directly, use get_installed_langs(), which employes caching (it has to be public, to act as callback for the cache!).
	 *
	 * @return array
	 */
	static function &read_installed_langs()
	{
		$langs = array();
		foreach(self::$db->select(self::LANG_TABLE,'DISTINCT lang,lang_name','lang = lang_id',__LINE__,__FILE__,
			false,'',false,0,','.self::LANGUAGES_TABLE) as $row)
		{
			$langs[$row['lang']] = $row['lang_name'];
		}
		return $langs;
	}

	/**
	 * translates a 2 or 5 char lang-code into a (verbose) language
	 *
	 * @param string $lang
	 * @return string/false language or false if not found
	 */
	static function lang2language($lang)
	{
		if (isset(self::$langs[$lang]))	// no need to query the DB
		{
			return self::$langs[$lang];
		}
		return self::$db->select(self::LANGUAGES_TABLE,'lang_name',array('lang_id' => $lang),__LINE__,__FILE__)->fetchColumn();
	}

	/**
	 * List all languages, first the installed ones, then the available ones and last the rest
	 *
	 * @param boolean $force_read=false
	 * @return array with lang_id => lang_name pairs
	 */
	static function list_langs($force_read=false)
	{
		if (!$force_read)
		{
			return egw_cache::getInstance(__CLASS__,'list_langs',array(__CLASS__,'list_langs'),array(true));
		}
		$languages = self::get_installed_langs();	// used translated installed languages

		$availible = array();
		$f = fopen(EGW_SERVER_ROOT.'/setup/lang/languages','rb');
		while($line = fgets($f,200))
		{
			list($id,$name) = explode("\t",$line);
			$availible[] = trim($id);
		}
		fclose($f);
		$availible = "('".implode("','",$availible)."')";

		// this shows first the installed, then the available and then the rest
		foreach(self::$db->select(self::LANGUAGES_TABLE,array(
			'lang_id','lang_name',
			"CASE WHEN lang_id IN $availible THEN 1 ELSE 0 END AS availible",
		),"lang_id NOT IN ('".implode("','",array_keys($languages))."')",__LINE__,__FILE__,false,' ORDER BY availible DESC,lang_name') as $row)
		{
			$languages[$row['lang_id']] = $row['lang_name'];
		}
		return $languages;
	}

	/**
	 * provides centralization and compatibility to locate the lang files
         *
         * @param string $app application name
         * @param string $lang language code
         * @return the full path of the filename for the requested app and language
         */
        static function get_lang_file($app,$lang)
	{
		// Visit each lang file dir, look for a corresponding ${prefix}_lang file
		$langprefix=EGW_SERVER_ROOT . SEP ;
		$langsuffix=strtolower($lang) . '.lang';
		$new_appfile = $langprefix . $app . SEP . 'lang' . SEP . self::LANGFILE_PREFIX . $langsuffix;
		$cur_appfile = $langprefix . $app . SEP . 'setup' . SEP . self::LANGFILE_PREFIX . $langsuffix;
		$old_appfile = $langprefix . $app . SEP . 'setup' . SEP . self::OLD_LANGFILE_PREFIX . $langsuffix;
		// Note there's no chance for 'lang/phpgw_' files
		if (file_exists($new_appfile))
		{
			$appfile=$new_appfile;
		}
		elseif (file_exists($cur_appfile))
		{
			$appfile=$cur_appfile;
		}
		else
		{
			$appfile=$old_appfile;
		}
		return $appfile;
	}

	/**
	 * returns a list of installed charsets
	 *
	 * @return array with charset as key and comma-separated list of langs useing the charset as data
	 */
	static function get_installed_charsets()
	{
		static $charsets;

		if (!isset($charsets))
		{
			$charsets = array(
				'utf-8'      => lang('all languages').' (utf-8)',
				'iso-8859-1' => lang('Western european').' (iso-8859-1)',
				'iso-8859-2' => lang('Eastern european').' (iso-8859-2)',
				'iso-8859-7' => lang('Greek').' (iso-8859-7)',
				'euc-jp'     => lang('Japanese').' (euc-jp)',
				'euc-kr'     => lang('Korean').' (euc-kr)',
				'koi8-r'     => lang('Russian').' (koi8-r)',
				'windows-1251' => lang('Bulgarian').' (windows-1251)',
			);
		}
		return $charsets;
	}

	/**
	 * converts a string $data from charset $from to charset $to
	 *
	 * @param string/array $data string(s) to convert
	 * @param string/boolean $from charset $data is in or False if it should be detected
	 * @param string/boolean $to charset to convert to or False for the system-charset the converted string
	 * @param boolean $check_to_from=true internal to bypass all charset replacements
	 * @return string/array converted string(s) from $data
	 */
	static function convert($data,$from=False,$to=False,$check_to_from=true)
	{
		if ($check_to_from)
		{
			if ($from) $from = strtolower($from);

			if ($to) $to = strtolower($to);

			if (!$from)
			{
				$from = self::$mbstring ? strtolower(mb_detect_encoding($data)) : 'iso-8859-1';
				if($from == 'ascii')
				{
					$from = 'iso-8859-1';
				}
				//echo "<p>autodetected charset of '$data' = '$from'</p>\n";
			}
			/*
				 php does not seem to support gb2312
				 but seems to be able to decode it as EUC-CN
			*/
			switch($from)
			{
				case 'gb2312':
				case 'gb18030':
					$from = 'EUC-CN';
					break;
				case 'us-ascii':
				case 'macroman':
				case 'iso8859-1':
				case 'windows-1258':
				case 'windows-1252':
					$from = 'iso-8859-1';
					break;
				case 'windows-1250':
					$from = 'iso-8859-2';
					break;
				case 'windows-1257':
					$from = 'iso-8859-13';
					break;
				case 'windows-874':
				case 'tis-620':
					$prefer_iconv = true;
					break;
			}
			if (!$to)
			{
				$to = self::charset();
			}
			if ($from == $to || !$from || !$to || !$data)
			{
				return $data;
			}
		}
		if (is_array($data))
		{
			foreach($data as $key => $str)
			{
				$ret[$key] = self::convert($str,$from,$to,false);	// false = bypass the above checks, as they are already done
			}
			return $ret;
		}
		if ($from == 'iso-8859-1' && $to == 'utf-8')
		{
			return utf8_encode($data);
		}
		if ($to == 'iso-8859-1' && $from == 'utf-8')
		{
			return utf8_decode($data);
		}
		if (self::$mbstring && !$prefer_iconv && ($data = @mb_convert_encoding($data,$to,$from)) != '')
		{
			return $data;
		}
		if (function_exists('iconv'))
		{
			// iconv can not convert from/to utf7-imap
			if ($to == 'utf7-imap' && function_exists(imap_utf7_encode))
			{
				$convertedData = iconv($from, 'iso-8859-1', $data);
				$convertedData = imap_utf7_encode($convertedData);

				return $convertedData;
			}

			if ($from == 'utf7-imap' && function_exists(imap_utf7_decode))
			{
				$convertedData = imap_utf7_decode($data);
				$convertedData = iconv('iso-8859-1', $to, $convertedData);

				return $convertedData;
			}

			// the following is to workaround patch #962307
			// if using EUC-CN, for iconv it strickly follow GB2312 and fail
			// in an email on the first Traditional/Japanese/Korean character,
			// but in reality when people send mails in GB2312, UMA mostly use
			// extended GB13000/GB18030 which allow T/Jap/Korean characters.
			if($from == 'euc-cn')
			{
				$from = 'gb18030';
			}

			if (($convertedData = iconv($from,$to,$data)))
			{
				return $convertedData;
			}
		}
		return $data;
	}

	/**
	 * rejected lines from install_langs()
	 *
	 * @var array
	 */
	static $line_rejected = array();

	/**
	 * installs translations for the selected langs into the database
	 *
	 * @param array $langs langs to install (as data NOT keys (!))
	 * @param string $upgrademethod='dumpold' 'dumpold' (recommended & fastest), 'addonlynew' languages, 'addmissing' phrases
	 * @param string/boolean $only_app=false app-name to install only one app or default false for all
	 */
	static function install_langs($langs,$upgrademethod='dumpold',$only_app=False)
	{
		if (is_null(self::$db)) self::init(false);

		@set_time_limit(0);	// we might need some time
		//echo "<p>translation_sql::install_langs(".print_r($langs,true).",'$upgrademthod','$only_app')</p>\n";
		if (!isset($GLOBALS['egw_info']['server']) && $upgrademethod != 'dumpold')
		{
			if (($ctimes = self::$db->select(config::TABLE,'config_value',array(
				'config_app'	=> 'phpgwapi',
				'config_name'	=> 'lang_ctimes',
			),__LINE__,__FILE__)->fetchColumn()))
			{
				$GLOBALS['egw_info']['server']['lang_ctimes'] = unserialize(stripslashes($ctimes));
			}
		}
		if (!is_array($langs) || !count($langs))
		{
			return;	// nothing to do
		}
		foreach($langs as $lang)
		{
			// run the update of each lang in a transaction
			self::$db->transaction_begin();

			if ($upgrademethod == 'dumpold')
			{
				// dont delete the custom main- & loginscreen messages every time
				self::$db->delete(self::LANG_TABLE,array("app_name!='mainscreen'","app_name!='loginscreen'",'lang' => $lang),__LINE__,__FILE__);
				//echo '<br>Test: dumpold';
				$GLOBALS['egw_info']['server']['lang_ctimes'][$lang] = array();
			}
			$addlang = False;
			if ($upgrademethod == 'addonlynew')
			{
				//echo "<br>Test: addonlynew - select count(*) from egw_lang where lang='".$lang."'";
				if (!self::$db->select(self::LANG_TABLE,'COUNT(*)',array(
					'lang' => $lang,
				),__LINE__,__FILE__)->fetchColumn())
				{
					//echo '<br>Test: addonlynew - True';
					$addlang = True;
				}
			}
			if ($addlang && $upgrademethod == 'addonlynew' || $upgrademethod != 'addonlynew')
			{
				//echo '<br>Test: loop above file()';
				if (!is_object($GLOBALS['egw_setup']))
				{
					$GLOBALS['egw_setup'] =& CreateObject('setup.setup');
					$GLOBALS['egw_setup']->db = clone(self::$db);
				}
				$setup_info = $GLOBALS['egw_setup']->detection->get_versions();
				$setup_info = $GLOBALS['egw_setup']->detection->get_db_versions($setup_info);
				$raw = array();
				$apps = $only_app ? array($only_app) : array_keys($setup_info);
				foreach($apps as $app)
				{
					$appfile=self::get_lang_file($app,$lang);
					//echo '<br>Checking in: ' . $app;
					if($GLOBALS['egw_setup']->app_registered($app) && (file_exists($appfile)))
					{
						//echo '<br>Including: ' . $appfile;
						$lines = file($appfile);
						foreach($lines as $line)
						{
							// explode with "\t" and removing "\n" with str_replace, needed to work with mbstring.overload=7
							list($message_id,$app_name,,$content) = $_f_buffer = explode("\t",$line);
							$content=str_replace(array("\n","\r"),'',$content);
							if( count($_f_buffer) != 4 )
							{
								$line_display = str_replace(array("\t","\n"),
									array("<font color='red'><b>\\t</b></font>","<font color='red'><b>\\n</b></font>"), $line);
								self::$line_rejected[] = array(
									'appfile' => $appfile,
									'line'    => $line_display,
								);
							}
							$message_id = substr(strtolower(chop($message_id)),0,self::MAX_MESSAGE_ID_LENGTH);
							$app_name = chop($app_name);
							$raw[$app_name][$message_id] = $content;
						}
						if ($GLOBALS['egw_info']['server']['lang_ctimes'] && !is_array($GLOBALS['egw_info']['server']['lang_ctimes']))
						{
							$GLOBALS['egw_info']['server']['lang_ctimes'] = unserialize($GLOBALS['egw_info']['server']['lang_ctimes']);
						}
						$GLOBALS['egw_info']['server']['lang_ctimes'][$lang][$app] = filectime($appfile);
					}
				}
				$charset = strtolower(@$raw['common']['charset'] ? $raw['common']['charset'] : self::charset($lang));
				//echo "<p>lang='$lang', charset='$charset', system_charset='self::$system_charset')</p>\n";
				//echo "<p>raw($lang)=<pre>".print_r($raw,True)."</pre>\n";
				foreach($raw as $app_name => $ids)
				{
					foreach($ids as $message_id => $content)
					{
						if (self::$system_charset)
						{
							$content = self::convert($content,$charset,self::$system_charset);
						}
						$addit = False;
						//echo '<br>APPNAME:' . $app_name . ' PHRASE:' . $message_id;
						if ($upgrademethod == 'addmissing')
						{
							//echo '<br>Test: addmissing';
							$rs = self::$db->select(self::LANG_TABLE,"content,CASE WHEN app_name IN ('common') THEN 1 ELSE 0 END AS in_api",array(
								'message_id' 	=> $message_id,
								'lang'			=> $lang,
								self::$db->expression(self::LANG_TABLE,'(',array(
									'app_name' => $app_name
								)," OR app_name='common') ORDER BY in_api DESC")),__LINE__,__FILE__);

							if (!($row = $rs->fetch()))
							{
								$addit = True;
							}
							else
							{
								if ($row['in_api'])		// same phrase is in the api
								{
									$addit = $row['content'] != $content;	// only add if not identical
								}
								$row2 = $rs->fetch();
								if (!$row['in_api'] || $app_name=='common' || $row2)	// phrase is alread in the db
								{
									$addit = $content != ($row2 ? $row2['content'] : $row['content']);
									if ($addit)	// if we want to add/update it ==> delete it
									{
										self::$db->delete(self::LANG_TABLE,array(
											'message_id'	=> $message_id,
											'lang'			=> $lang,
											'app_name'		=> $app_name,
										),__LINE__,__FILE__);
									}
								}
							}
						}

						if ($addit || $upgrademethod == 'addonlynew' || $upgrademethod == 'dumpold')
						{
							if($message_id && $content)
							{
								// echo "<br>adding - insert into egw_lang values ('$message_id','$app_name','$lang','$content')";
								$result = self::$db->insert(self::LANG_TABLE,array(
									'message_id'	=> $message_id,
									'app_name'		=> $app_name,
									'lang'			=> $lang,
									'content'		=> $content,
								),False,__LINE__,__FILE__);

								if ((int)$result <= 0)
								{
									echo "<br>Error inserting record: egw_lang values ('$message_id','$app_name','$lang','$content')";
								}
							}
						}
					}
				}
			}
			// commit now the update of $lang, before we fill the cache again
			self::$db->transaction_commit();

			$apps = array_keys($raw);
			unset($raw);

			foreach($apps as $app_name)
			{
				// update the tree-level cache, as we can not effectivly unset it in a multiuser enviroment,
				// as users from other - not yet updated - instances update it again with an old version!
				egw_cache::setTree(__CLASS__,$app_name.':'.$lang,($phrases=&self::load_app($app_name,$lang)));
				//error_log(__METHOD__.'('.array2string($langs).",$upgrademethod,$only_app) updating tree-level cache for app=$app_name and lang=$lang: ".count($phrases)." phrases");
			}
		}
		// delete the cache
		egw_cache::unsetInstance(__CLASS__,'installed_langs');
		egw_cache::unsetInstance(__CLASS__,'list_langs');

		// update the ctimes of the installed langsfiles for the autoloading of the lang-files
		//error_log(__METHOD__.'('.array2string($langs).",$upgrademethod,$only_app) storing lang_ctimes=".array2string($GLOBALS['egw_info']['server']['lang_ctimes']));
		config::save_value('lang_ctimes',$GLOBALS['egw_info']['server']['lang_ctimes'],'phpgwapi');
	}

	/**
	 * re-loads all (!) langfiles if one langfile for the an app and the language of the user has changed
	 */
	static function autoload_changed_langfiles()
	{
		//echo "<h1>check_langs()</h1>\n";
		if ($GLOBALS['egw_info']['server']['lang_ctimes'] && !is_array($GLOBALS['egw_info']['server']['lang_ctimes']))
		{
			$GLOBALS['egw_info']['server']['lang_ctimes'] = unserialize($GLOBALS['egw_info']['server']['lang_ctimes']);
		}
		//error_log(__METHOD__."(): ling_ctimes=".array2string($GLOBALS['egw_info']['server']['lang_ctimes']));

		$lang = $GLOBALS['egw_info']['user']['preferences']['common']['lang'];
		$apps = $GLOBALS['egw_info']['user']['apps'];
		$apps['phpgwapi'] = True;	// check the api too
		foreach($apps as $app => $data)
		{
			$fname=self::get_lang_file($app,$lang);
			$old_fname = EGW_SERVER_ROOT . "/$app/setup/" . self::OLD_LANGFILE_PREFIX . "$lang.lang";

			if (file_exists($fname) || file_exists($fname = $old_fname))
			{
				if (!isset($GLOBALS['egw_info']['server']['lang_ctimes'][$lang]) ||
					$GLOBALS['egw_info']['server']['lang_ctimes'][$lang][$app] != filectime($fname))
				{
					// update all langs
					$installed = self::get_installed_langs();
					//error_log(__METHOD__."(): self::install_langs(".array2string($installed).')');
					self::install_langs($installed ? array_keys($installed) : array('en'));
					break;
				}
			}
		}
	}

	/* Following functions are called for app (un)install */

	/**
	 * gets array of installed languages, e.g. array('de','en')
	 *
	 * @param boolean $DEBUG=false debug messages or not, default not
	 * @return array with installed langs
	 */
	static function get_langs($DEBUG=False)
	{
		if($DEBUG)
		{
			echo '<br>get_langs(): checking db...' . "\n";
		}
		if (!self::$langs)
		{
			self::get_installed_langs();
		}
		return self::$langs ? array_keys(self::$langs) : array();
	}

	/**
	 * delete all lang entries for an application, return True if langs were found
	 *
	 * @param $appname app_name whose translations you want to delete
	 * @param boolean $DEBUG=false debug messages or not, default not
	 * @return boolean true if $appname had translations installed, false otherwise
	 */
	static function drop_langs($appname,$DEBUG=False)
	{
		if($DEBUG)
		{
			echo '<br>drop_langs(): Working on: ' . $appname;
		}
		if (is_null(self::$db)) self::init(false);

		if (self::$db->select(self::LANG_TABLE,'COUNT(*)',array(
			'app_name' => $appname
		),__LINE__,__FILE__)->fetchColumn())
		{
			self::$db->delete(self::LANG_TABLE,array(
				'app_name' => $appname
			),__LINE__,__FILE__);
			return True;
		}
		return False;
	}

	/**
	 * process an application's lang files, calling get_langs() to see what langs the admin installed already
	 *
	 * @param string $appname app_name of application to process
	 * @param boolean $DEBUG=false debug messages or not, default not
	 * @param array/boolean $force_langs=false array with langs to install anyway (beside the allready installed ones), or false for none
	 */
	static function add_langs($appname,$DEBUG=False,$force_langs=False)
	{
		$langs = self::get_langs($DEBUG);
		if(is_array($force_langs))
		{
			foreach($force_langs as $lang)
			{
				if (!in_array($lang,$langs))
				{
					$langs[] = $lang;
				}
			}
		}

		if($DEBUG)
		{
			echo '<br>add_langs(): chose these langs: ';
			_debug_array($langs);
		}

		self::install_langs($langs,'addmissing',$appname);
	}

	/**
	 * insert/update one phrase in the lang-table
	 *
	 * @param string $lang
	 * @param string $app
	 * @param string $message_id
	 * @param string $content
	 */
	static function write($lang,$app,$message_id,$content)
	{
		self::$db->insert(self::LANG_TABLE,array(
			'content' => $content,
		),array(
			'lang' => $lang,
			'app_name' => $app,
			'message_id' => $message_id,
		),__LINE__,__FILE__);

		// invalidate the cache
		egw_cache::unsetCache(in_array($app,self::$instance_specific_translations) ? egw_cache::INSTANCE : egw_cache::TREE,__CLASS__,$app.':'.$lang);
	}

	/**
	 * read one phrase from the lang-table
	 *
	 * @param string $lang
	 * @param string $app_name
	 * @param string $message_id
	 * @return string/boolean content or false if not found
	 */
	static function read($lang,$app_name,$message_id)
	{
		return self::$db->select(self::LANG_TABLE,'content',array(
			'lang' => $lang,
			'app_name' => $app_name,
			'message_id' => $message_id,
		),__LINE__,__FILE__)->fetchColumn();
	}

	/**
	 * Return the message_id of a given translation
	 *
	 * @param string $translation
	 * @param string $app='' default check all apps
	 * @param string $lang='' default check all langs
	 * @return string
	 */
	static function get_message_id($translation,$app=null,$lang=null)
	{
		$like = self::$db->Type == 'pgsql' ? 'ILIKE' : 'LIKE';
		$where = array('content '.$like.' '.self::$db->quote($translation));	// like to be case-insensitive
		if ($app) $where['app_name'] = $app;
		if ($lang) $where['lang'] = $lang;

		return self::$db->select(self::LANG_TABLE,'message_id',$where,__LINE__,__FILE__)->fetchColumn();
	}

	/**
	 * Return the decoded string meeting some additional requirements for mailheaders
	 *
	 * @param string $_string -> part of an mailheader
	 * @param string $displayCharset the charset parameter specifies the character set to represent the result by (if iconv_mime_decode is to be used)
	 * @return string
	 */
	static function decodeMailHeader($_string, $displayCharset='utf-8')
	{
		//error_log(__FILE__.','.__METHOD__.':'."called with $_string and CHARSET $displayCharset");
		if(function_exists(imap_mime_header_decode))
		{
			$newString = '';

			$string = preg_replace('/\?=\s+=\?/', '?= =?', $_string);

			$elements=imap_mime_header_decode($string);
			$convertAtEnd = false;
			foreach((array)$elements as $element)
			{
				if ($element->charset == 'default') $element->charset = 'iso-8859-1';
				if ($element->charset != 'x-unknown')
				{
					$newString .= self::convert($element->text,$element->charset);
				}
				else
				{
					$newString .= $element->text;
					$convertAtEnd = true;
				}
			}
			if ($convertAtEnd) $newString = self::decodeMailHeader($newString,$displayCharset);
			return preg_replace('/([\000-\012\015\016\020-\037\075])/','',$newString);
		}
		elseif(function_exists(mb_decode_mimeheader))
		{
			$string = $_string;
			if(preg_match_all('/=\?.*\?Q\?.*\?=/iU', $string, $matches))
			{
				foreach($matches[0] as $match)
				{
					$fixedMatch = str_replace('_', ' ', $match);
					$string = str_replace($match, $fixedMatch, $string);
				}
				$string = str_replace('=?ISO8859-','=?ISO-8859-',$string);
				$string = str_replace('=?windows-1258','=?ISO-8859-1',$string);
			}
			$string = mb_decode_mimeheader($string);
			return preg_replace('/([\000-\012\015\016\020-\037\075])/','',$string);
		}
		elseif(function_exists(iconv_mime_decode))
		{
			// continue decoding also if an error occurs
			$string = @iconv_mime_decode($_string, 2, $displayCharset);
			return preg_replace('/([\000-\012\015\016\020-\037\075])/','',$string);
		}

		// no decoding function available
		return preg_replace('/([\000-\012\015\016\020-\037\075])/','',$_string);
	}

	/**
	 * replace emailaddresses enclosed in <> (eg.: <me@you.de>) with the emailaddress only (e.g: me@you.de)
	 *    as well as those emailadresses in links, and within broken links
	 * @param string the text to process
	 * @return 1
	 */
	static function replaceEmailAdresses(&$text)
	{
		//error_log($text);
		//replace CRLF with something other to be preserved via preg_replace as CRLF seems to vanish
		$text = str_replace("\r\n",'<#cr-lf#>',$text);
		// replace emailaddresses eclosed in <> (eg.: <me@you.de>) with the emailaddress only (e.g: me@you.de)
		$text = preg_replace("/(<|&lt;a href=\")*(mailto:([\w\.,-.,_.,0-9.]+)(@)([\w\.,-.,_.,0-9.]+))(>|&gt;)*/ie","'$2 '", $text);
		$text = preg_replace('~<a[^>]+href=\"(mailto:)+([^"]+)\"[^>]*>~si','$2 ',$text);
		$text = preg_replace("/(([\w\.,-.,_.,0-9.]+)(@)([\w\.,-.,_.,0-9.]+))( |\s)*(<\/a>)*( |\s)*(>|&gt;)*/ie","'$1 '", $text);
		$text = preg_replace("/(<|&lt;)*(([\w\.,-.,_.,0-9.]+)@([\w\.,-.,_.,0-9.]+))(>|&gt;)*/ie","'$2 '", $text);
		$text = str_replace('<#cr-lf#>',"\r\n",$text);
		return 1;
	}

	/**
	 * strip tags out of the message completely with their content
	 * @param string $_body is the text to be processed
	 * @param string $tag is the tagname which is to be removed. Note, that only the name of the tag is to be passed to the function
	 *				without the enclosing brackets
	 * @param string $endtag can be different from tag  but should be used only, if begin and endtag are known to be different e.g.: <!-- -->
	 * @param bool $addbbracesforendtag if endtag is given, you may decide if the </ and > braces are to be added,
	 *				or if you want the string to be matched as is
	 * @return void the modified text is passed via reference
	 */
	static function replaceTagsCompletley(&$_body,$tag,$endtag='',$addbracesforendtag=true)
	{
		if ($tag) $tag = strtolower($tag);
		if ($endtag == '' || empty($endtag) || !isset($endtag))
		{
		        $endtag = $tag;
		} else {
		        $endtag = strtolower($endtag);
				//error_log(__METHOD__.' Using EndTag:'.$endtag);
		}
		// strip tags out of the message completely with their content
		$taglen=strlen($tag);
		$endtaglen=strlen($endtag);
		if ($_body) {
			if ($addbracesforendtag === true )
			{
				$_body = preg_replace('~<'.$tag.'[^>]*?>(.*)</'.$endtag.'[\s]*>~simU','',$_body);
				// remove left over tags, unfinished ones, and so on
				$_body = preg_replace('~<'.$tag.'[^>]*?>~si','',$_body);
			}
			if ($addbracesforendtag === false )
			{
				$_body = preg_replace('~<'.$tag.'[^>]*?>(.*)'.$endtag.'~simU','',$_body);
				// remove left over tags, unfinished ones, and so on
				$_body = preg_replace('~<'.$tag.'[^>]*?>~si','',$_body);
				$_body = preg_replace('~'.$endtag.'~','',$_body);
			}
		}
	}

	/**
	 * convertHTMLToText
	 * @param string $_html : Text to be stripped down
	 * @param string $displayCharset : charset to use; should be a valid charset
	 * @param bool $stripcrl :  flag to indicate for the removal of all crlf \r\n
	 * @param bool $stripalltags : flag to indicate wether or not to strip $_html from all remaining tags
	 * @return text $_html : the modified text.
	 */
	static function convertHTMLToText($_html,$displayCharset=false,$stripcrl=false,$stripalltags=true)
	{
		if ($displayCharset === false) $displayCharset = self::$system_charset;
		//error_log(__METHOD__.$_html);
		#print '<hr>';
		#print "<pre>"; print htmlspecialchars($_html);
		#print "</pre>";
		#print "<hr>";
		self::replaceTagsCompletley($_html,'style');
		$Rules = array ('@<script[^>]*?>.*?</script>@siU', // Strip out javascript
			'@&(quot|#34);@i',                // Replace HTML entities
			'@&(amp|#38);@i',                 //   Ampersand &
			'@&(lt|#60);@i',                  //   Less Than <
			'@&(gt|#62);@i',                  //   Greater Than >
			'@&(nbsp|#160);@i',               //   Non Breaking Space
			'@&(iexcl|#161);@i',              //   Inverted Exclamation point
			'@&(cent|#162);@i',               //   Cent
			'@&(pound|#163);@i',              //   Pound
			'@&(copy|#169);@i',               //   Copyright
			'@&(reg|#174);@i',                //   Registered
		);
		$Replace = array ('',
			'"',
			'+',
			'<',
			'>',
			' ',
			chr(161),
			chr(162),
			chr(163),
			chr(169),
			chr(174),
		);
		$_html = preg_replace($Rules, $Replace, $_html);

		//   removing carriage return linefeeds
		if ($stripcrl === true ) $_html = preg_replace('@(\r\n)@i',' ',$_html);
		$tags = array (
			0 => '~<h[123][^>]*>\r*\n*~si',
			1 => '~<h[456][^>]*>\r*\n*~si',
			2 => '~<table[^>]*>\r*\n*~si',
			3 => '~<tr[^>]*>\r*\n*~si',
			4 => '~<li[^>]*>\r*\n*~si',
			5 => '~<br[^>]*>\r*\n*~si',
			6 => '~<br[^>]*>~si',
			7 => '~<p[^>]*>\r*\n*~si',
			8 => '~<div[^>]*>\r*\n*~si',
			9 => '~<hr[^>]*>\r*\n*~si',
			10 => '/<blockquote type="cite">/',
		);
		$Replace = array (
			0 => "\r\n",
			1 => "\r\n",
			2 => "\r\n",
			3 => "\r\n",
			4 => "\r\n",
			5 => "\r\n",
			6 => "\r\n",
			7 => "\r\n",
			8 => "\r\n",
			9 => "\r\n__________________________________________________\r\n",
			10 => '#blockquote#type#cite#',
		);
		$_html = preg_replace($tags,$Replace,$_html);
		$_html = preg_replace('~</t(d|h)>\s*<t(d|h)[^>]*>~si',' - ',$_html);
		$_html = preg_replace('~<img[^>]+>~s','',$_html);
		// replace emailaddresses eclosed in <> (eg.: <me@you.de>) with the emailaddress only (e.g: me@you.de)
		self::replaceEmailAdresses($_html);
		//convert hrefs to description -> URL
		$_html = preg_replace('~<a[^>]+href=\"([^"]+)\"[^>]*>(.*)</a>~si','[$2 -> $1]',$_html);
		//this is supposed to strip out all remaining stuff in tags, this is sometimes taking out whole sections off content
		if ( $stripalltags ) {
			$_html = preg_replace('~<[^>^@]+>~s','',$_html);
			//$_html = strip_tags($_html, '<a>');
		}
		// reducing spaces
		$_html = preg_replace('~ +~s',' ',$_html);
		// we dont reduce whitespace at the start or the end of the line, since its used for structuring the document
		#$_html = preg_replace('~^\s+~m','',$_html);
		#$_html = preg_replace('~\s+$~m','',$_html);
		// restoring the preserved blockquote
		$_html = preg_replace('~#blockquote#type#cite#~s','<blockquote type="cite">',$_html);


		$_html = html_entity_decode($_html, ENT_COMPAT, $displayCharset);
		//self::replaceEmailAdresses($_html);
		#error_log($text);
		$pos = strpos($_html, 'blockquote');
		#error_log("convert HTML2Text");
		if($pos === false) {
			return $_html;
		} else {
			$indent = 0;
			$indentString = '';

			$quoteParts = preg_split('/<blockquote type="cite">/', $_html, -1, PREG_SPLIT_OFFSET_CAPTURE);

			foreach($quoteParts as $quotePart) {
				if($quotePart[1] > 0) {
					$indent++;
					$indentString .= '>';
				}
				$quoteParts2 = preg_split('/<\/blockquote>/', $quotePart[0], -1, PREG_SPLIT_OFFSET_CAPTURE);

				foreach($quoteParts2 as $quotePart2) {
					if($quotePart2[1] > 0) {
						$indent--;
						$indentString = substr($indentString, 0, $indent);
					}

					$quoteParts3 = explode("\r\n", $quotePart2[0]);

					foreach($quoteParts3 as $quotePart3) {
						$allowedLength = 76-strlen("\r\n$indentString");
						// only break lines, if not already indented
						if ($quotePart3[0] != $indentString)
						{
							if (strlen($quotePart3) > $allowedLength) {
								$s=explode(" ", $quotePart3);
								$quotePart3 = "";
								$linecnt = 0;
								foreach ($s as $k=>$v) {
									$cnt = strlen($v);
									// only break long words within the wordboundaries,
									// but it may destroy links, so we check for href and dont do it if we find it
									if($cnt > $allowedLength && stripos($v,'href=')===false) {
										$v=wordwrap($v, $allowedLength, "\r\n$indentString", true);
									}
									// the rest should be broken at the start of the new word that exceeds the limit
									if ($linecnt+$cnt > $allowedLength) {
										$v="\r\n$indentString$v";
										$linecnt = 0;
									} else {
										$linecnt += $cnt;
									}
									if (strlen($v))  $quotePart3 .= (strlen($quotePart3) ? " " : "").$v;
								}
							}
						}
						$asciiTextBuff[] = $indentString . $quotePart3 ;
					}
				}
			}
			return implode("\r\n",$asciiTextBuff);
		}
	}
}
