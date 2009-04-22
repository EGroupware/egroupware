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
		static $placeholders = array('%1','%2','%3','%4','%5','%6','%7','%8','%9','%10');

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
			$new_key = strtolower(trim(substr($key,0,self::MAX_MESSAGE_ID_LENGTH)));

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
			$loaded =& egw_cache::getTree(__CLASS__,$app.':'.$lang,array(__CLASS__,'load_app'),array($app,$lang));

			self::$lang_arr = self::$lang_arr ? array_merge(self::$lang_arr,$loaded) : $loaded;
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
			$loaded = array();
			foreach(self::$db->select(self::LANG_TABLE,'message_id,content',array(
				'lang'		=> $lang,
				'app_name'	=> $app,
			),__LINE__,__FILE__) as $row)
			{
				$loaded[strtolower($row['message_id'])] = $row['content'];
			}
		}
		//error_log(__METHOD__."($app,$lang) took ".(1000*(microtime(true)-$start))." ms");
		return $loaded;
	}

	/**
	 * Adds setup's translations, they are not in the DB!
	 *
	 * @param string $lang 2 or 5 char lang-code
	 * @return array with loaded phrases
	 */
	protected static function &load_setup($lang)
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

			self::$langs = array();
			foreach(self::$db->select(self::LANG_TABLE,'DISTINCT lang,lang_name','lang = lang_id',__LINE__,__FILE__,
				false,'',false,0,','.self::LANGUAGES_TABLE) as $row)
			{
				self::$langs[$row['lang']] = $row['lang_name'];
			}
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
	 * List all languages, first the installed ones, then the availible ones and last the rest
	 *
	 * @return array with lang_id => lang_name pairs
	 */
	static function list_langs()
	{
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
				'utf-8'      => lang('all languages'),
				'iso-8859-1' => 'Western european',
				'iso-8859-2' => 'Eastern european',
				'iso-8859-7' => 'Greek',
				'euc-jp'     => 'Japanese',
				'euc-kr'     => 'Korean',
				'koi8-r'     => 'Russian',
				'windows-1251' => 'Bulgarian',
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
		if (self::$mbstring && ($data = @mb_convert_encoding($data,$to,$from)) != '')
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
			if($from == 'EUC-CN')
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
			return;
		}
		self::$db->transaction_begin();

		if ($upgrademethod == 'dumpold')
		{
			// dont delete the custom main- & loginscreen messages every time
			self::$db->delete(self::LANG_TABLE,array("app_name!='mainscreen'","app_name!='loginscreen'"),__LINE__,__FILE__);
			//echo '<br>Test: dumpold';
			$GLOBALS['egw_info']['server']['lang_ctimes'] = array();
		}
		foreach($langs as $lang)
		{
			//echo '<br>Working on: ' . $lang;
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
				// Visit each app/setup dir, look for a egw_lang file
				foreach($apps as $app)
				{
					$appfile = EGW_SERVER_ROOT . SEP . $app . SEP . 'setup' . SEP . self::LANGFILE_PREFIX . strtolower($lang) . '.lang';
					$old_appfile = EGW_SERVER_ROOT . SEP . $app . SEP . 'setup' . SEP . self::OLD_LANGFILE_PREFIX . strtolower($lang) . '.lang';
					//echo '<br>Checking in: ' . $app;
					if($GLOBALS['egw_setup']->app_registered($app) && (file_exists($appfile) || file_exists($appfile=$old_appfile)))
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
						$GLOBALS['egw_info']['server']['lang_ctimes'][$lang][$app] = filemtime($appfile);
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
					// delete the cache
					egw_cache::unsetTree(__CLASS__,$app_name.':'.$lang);
					//error_log(__METHOD__.'('.array2string($langs).",$upgrademethod,$only_app) deleted cache for app=$app_name and lang=$lang.");
				}
			}
		}
		self::$db->transaction_commit();

		// update the ctimes of the installed langsfiles for the autoloading of the lang-files
		//
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
		//_debug_array($GLOBALS['egw_info']['server']['lang_ctimes']);

		$lang = $GLOBALS['egw_info']['user']['preferences']['common']['lang'];
		$apps = $GLOBALS['egw_info']['user']['apps'];
		$apps['phpgwapi'] = True;	// check the api too
		foreach($apps as $app => $data)
		{
			$fname = EGW_SERVER_ROOT . "/$app/setup/" . self::LANGFILE_PREFIX . "$lang.lang";
			$old_fname = EGW_SERVER_ROOT . "/$app/setup/" . self::OLD_LANGFILE_PREFIX . "$lang.lang";

			if (file_exists($fname) || file_exists($fname = $old_fname))
			{
				$ctime = filectime($fname);
				/* This is done to avoid string offset error at least in php5 */
				$tmp = $GLOBALS['egw_info']['server']['lang_ctimes'][$lang];
				$ltime = (int)$tmp[$app];
				unset($tmp);
				//echo "checking lang='$lang', app='$app', ctime='$ctime', ltime='$ltime'<br>\n";

				if ($ctime != $ltime)
				{
					// update all langs
					$installed = self::get_installed_langs();
					//echo "<p>install_langs(".print_r($installed,True).")</p>\n";
					self::install_langs($installed ? array_keys($installed) : array());
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
	 * @param string $app_name
	 * @param string $message_id
	 * @param string $content
	 */
	static function write($lang,$app_name,$message_id,$content)
	{
		self::$db->insert(self::LANG_TABLE,array(
			'content' => $content,
		),array(
			'lang' => $lang,
			'app_name' => $app_name,
			'message_id' => $message_id,
		),__LINE__,__FILE__);
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

			foreach((array)$elements as $element)
			{
				if ($element->charset == 'default') $element->charset = 'iso-8859-1';

				$newString .= self::convert($element->text,$element->charset);
			}
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
}
