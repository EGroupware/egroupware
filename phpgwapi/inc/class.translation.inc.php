<?php
/**
 * EGroupware API - Translations
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
 * EGroupware API - Translations
 *
 * All methods of this class can now be called static.
 *
 * Translations are cached tree-wide via egw_cache class.
 *
 * Translations are no longer stored in database, but load directly from *.lang files into cache.
 * Only exception as instance specific translations: mainscreen, loginscreen and custom (see $instance_specific_translations)
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
	 * Directory for language files
	 */
	const LANG_DIR = 'lang';

	/**
	 * Prefix of language files
	 */
	const LANGFILE_PREFIX = 'egw_';

	/**
	 * Prefix of language files
	 */
	const LANGFILE_EXTENSION = '.lang';

	/**
	 * Reference to global db-class
	 *
	 * @var egw_db
	 */
	static $db;

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
	static $instance_specific_translations = array('loginscreen','mainscreen','custom');

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
		// in case no charset is set, default to utf-8
		if (empty($charset) || $charset == 'charset') $charset = 'utf-8';

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

		// try loading load_via from tree-wide cache and check if it contains more rules
		if (($load_via = egw_cache::getTree(__CLASS__, 'load_via')) &&
			$load_via > $GLOBALS['egw_info']['server']['translation_load_via'])	// > for array --> contains more elements
		{
			self::$load_via = $load_via;
			config::save_value('translation_load_via', self::$load_via, 'phpgwapi');
			//error_log(__METHOD__."() load_via set from tree-wide cache to ".array2string(self::$load_via));
		}
		// if not check our config for load-via information
		elseif (isset($GLOBALS['egw_info']['server']['translation_load_via']))
		{
			self::$load_via = $GLOBALS['egw_info']['server']['translation_load_via'];
			// if different from tree-wide value, update that
			if ($GLOBALS['egw_info']['server']['translation_load_via'] != $load_via)
			{
				egw_cache::setTree(__CLASS__, 'load_via', self::$load_via);
			}
			//error_log(__METHOD__."() load_via set from config to ".array2string(self::$load_via));
		}

		self::$lang_arr = self::$loaded_apps = array();

		if ($load_translations)
		{
			if ($GLOBALS['egw_info']['user']['preferences']['common']['lang'])
			{
				self::$userlang = $GLOBALS['egw_info']['user']['preferences']['common']['lang'];
			}
			$apps = array('common');
			// for eTemplate apps, load etemplate before app itself (allowing app to overwrite etemplate translations)
			if (class_exists('etemplate', false)) $apps[] = 'etemplate';
			if ($GLOBALS['egw_info']['flags']['currentapp']) $apps[] = $GLOBALS['egw_info']['flags']['currentapp'];
			// load instance specific translations last, so they can overwrite everything
			$apps[] = 'custom';
			self::add_app($apps);

			if (!count(self::$lang_arr))
			{
				self::$userlang = 'en';
				self::add_app($apps);
			}
		}
	}

	/**
	 * translates a phrase and evtl. substitute some variables
	 *
	 * @param string $key phrase to translate, may contain placeholders %N (N=1,2,...) for vars
	 * @param array $vars=null vars to replace the placeholders, or null for none
	 * @param string $not_found='*' what to add to not found phrases, default '*'
	 * @return string with translation
	 */
	static function translate($key, $vars=null, $not_found='' )
	{
		if (!self::$lang_arr)
		{
			self::init();
		}
		$ret = $key;				// save key if we dont find a translation
		if ($not_found) $ret .= $not_found;

		if (isset(self::$lang_arr[$key]))
		{
			$ret = self::$lang_arr[$key];
		}
		else
		{
			$new_key = strtolower($key);

			if (isset(self::$lang_arr[$new_key]))
			{
				$ret = self::$lang_arr[$new_key];
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
	 * Adds translations for (multiple) application(s)
	 *
	 * By default the translations are read from the tree-wide cache
	 *
	 * @param string|array $apps name(s) of application(s) to add (or 'common' for the general translations)
	 * 	if multiple names given, they are requested in one request from cache and loaded in given order
	 * @param string $lang=false 2 or 5 char lang-code or false for the users language
	 */
	static function add_app($apps, $lang=null)
	{
		//error_log(__METHOD__."(".array2string($apps).", $lang) count(self::\$lang_arr)=".count(self::$lang_arr));
		//$start = microtime(true);
		if (!$lang) $lang = self::$userlang;
		$tree_level = $instance_level = array();
		$apps = (array)$apps;
		foreach($apps as $key => $app)
		{
			if (!isset(self::$loaded_apps[$app]) || self::$loaded_apps[$app] != $lang && $app != 'common')
			{
				if (in_array($app, self::$instance_specific_translations))
				{
					$instance_level[] = $app.':'.($app == 'custom' ? 'en' : $lang);
				}
				else
				{
					$tree_level[] = $app.':'.$lang;
				}
			}
			else
			{
				unset($apps[$key]);
			}
		}
		// load all translations from cache at once
		if ($tree_level) $tree_level = egw_cache::getTree(__CLASS__, $tree_level);
		if ($instance_level) $instance_level = egw_cache::getInstance(__CLASS__, $instance_level);

		// merging loaded translations together
		foreach((array)$apps as $app)
		{
			$l = $app == 'custom' ? 'en' : $lang;
			if (isset($tree_level[$app.':'.$l]))
			{
				$loaded =& $tree_level[$app.':'.$l];
			}
			elseif (isset($instance_level[$app.':'.$l]))
			{
				$loaded =& $instance_level[$app.':'.$l];
			}
			else
			{
				if (($instance_specific = in_array($app, self::$instance_specific_translations)))
				{
					$loaded =& self::load_app($app, $l);
				}
				else
				{
					$loaded =& self::load_app_files($app, $l);
				}
				//error_log(__METHOD__."('$app', '$lang') instance_specific=$instance_specific, load_app(_files)() returned ".(is_array($loaded)?'Array('.count($loaded).')':array2string($loaded)));
				if ($loaded || $instance_specific)
				{
					egw_cache::setCache($instance_specific ? egw_cache::INSTANCE : egw_cache::TREE,
						__CLASS__, $app.':'.$l, $loaded);
					//error_log(__METHOD__."('$app', '$lang') caching now ".(is_array($loaded)?'Array('.count($loaded).')':array2string($loaded)));
				}
			}
			if ($loaded)
			{
				self::$lang_arr = array_merge(self::$lang_arr, $loaded);
				self::$loaded_apps[$app] = $l;	// dont set something not existing to $loaded_apps, no need to load client-side
			}
		}
		// Re-merge custom over instance level, they have higher precidence
		if($tree_level && !$instance_level && self::$instance_specific_translations)
		{
			$custom = egw_cache::getInstance(__CLASS__, 'custom:en');
			if($custom)
			{
				self::$lang_arr = array_merge(self::$lang_arr, $custom);
			}
		}
		//error_log(__METHOD__.'('.array2string($apps).", '$lang') took ".(1000*(microtime(true)-$start))." ms, loaded_apps=".array2string(self::$loaded_apps).", loaded ".count($loaded)." phrases -> total=".count(self::$lang_arr));//.": ".function_backtrace());
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
		if (is_null(self::$db)) self::init(false);
		$loaded = array();
		foreach(self::$db->select(self::LANG_TABLE,'message_id,content',array(
			'lang'		=> $lang,
			'app_name'	=> $app,
		),__LINE__,__FILE__) as $row)
		{
			$loaded[strtolower($row['message_id'])] = $row['content'];
		}
		//error_log(__METHOD__."($app,$lang) took ".(1000*(microtime(true)-$start))." ms to load ".count($loaded)." phrases");
		return $loaded;
	}

	/**
	 * How to load translations for a given app
	 *
	 * Translations for common, preferences or admin are in spread over all applications.
	 * API has translations for some pseudo-apps.
	 *
	 * @var array app => app(s) or string 'all-apps'
	 */
	static $load_via = array(
		'common' => 'all-apps',
		'preferences' => 'all-apps',
		'admin' => 'all-apps',
		'jscalendar' => 'phpgwapi',
		'sitemgr-link' => 'sitemgr',
		'groupdav' => 'phpgwapi',
		'login' => 'phpgwapi',
	);

	/**
	 * Check if cached translations are up to date or invalidate cache if not
	 *
	 * Called via login.php for each interactive login.
	 */
	static function check_invalidate_cache()
	{
		$lang = $GLOBALS['egw_info']['user']['preferences']['common']['lang'];
		$apps = array_keys($GLOBALS['egw_info']['user']['apps']);
		$apps[] = 'phpgwapi';	// check the api too
		foreach($apps as $app)
		{
			$file = self::get_lang_file($app, $lang);
			// check if file has changed compared to what's cached
			if (file_exists($file))
			{
				$cached_time = egw_cache::getTree(__CLASS__, $file);
				$file_time = filemtime($file);
				if ($cached_time != $file_time)
				{
					//error_log(__METHOD__."() $file MODIFIED ($cached_time != $file_time)");
					self::invalidate_lang_file($app, $lang);
				}
				//else error_log(__METHOD__."() $file unchanged ($cached_time == $file_time)");
			}
		}
	}

	/**
	 * Invalidate cache for lang-file of $app and $lang
	 *
	 * @param string $app
	 * @param string $lang
	 */
	static function invalidate_lang_file($app, $lang)
	{
		//error_log(__METHOD__."('$app', '$lang') invalidate translations $app:$lang");
		egw_cache::unsetTree(__CLASS__, $app.':'.$lang);
		egw_cache::unsetTree(__CLASS__, self::get_lang_file($app, $lang));

		foreach(self::$load_via as $load => $via)
		{
			//error_log("load_via[load='$load'] = via = ".array2string($via));
			if ($via === 'all-apps' || in_array($app, (array)$via))
			{
				//error_log(__METHOD__."('$app', '$lang') additional invalidate translations $load:$lang");
				egw_cache::unsetTree(__CLASS__, $load.':'.$lang);
				egw_cache::unsetTree(__CLASS__, self::get_lang_file($load, $lang));
			}
		}
		// unset statistics
		egw_cache::unsetTree(__CLASS__, 'statistics');
	}

	const STATISTIC_CACHE_TIMEOUT = 86400;

	/**
	 * Statistical values about how much a language and app is translated, number or valid phrases per $lang or $lang/$app
	 *
	 * @param string $_lang=null
	 * @return array $lang or $app => number pairs
	 */
	static function statistics($_lang=null)
	{
		$cache = egw_cache::getTree(__CLASS__, 'statistics');

		if (!isset($cache[(string)$_lang]))
		{
			$cache[(string)$_lang] = array();
			if (empty($_lang))
			{
				$en_phrases = array_keys(self::load_app_files(null, 'en', 'all-apps'));
				$cache['']['en'] = count($en_phrases);
				foreach(array_keys(self::get_available_langs()) as $lang)
				{
					if ($lang == 'en') continue;
					$lang_phrases = array_keys(self::load_app_files(null, $lang, 'all-apps'));
					$valid_phrases = array_intersect($lang_phrases, $en_phrases);
					$cache[''][$lang] = count($valid_phrases);
				}
			}
			else
			{
				$cache['en'] = array();
				foreach(scandir(EGW_SERVER_ROOT) as $app)
				{
					if ($app[0] == '.' || !is_dir(EGW_SERVER_ROOT.'/'.$app) ||
						!file_exists(self::get_lang_file($app, 'en')))
					{
						continue;
					}
					$en_phrases = array_keys(self::load_app_files(null, 'en', $app));
					if (count($en_phrases) <= 2) continue;
					$cache['en'][$app] = count($en_phrases);
					$lang_phrases = array_keys(self::load_app_files(null, $_lang, $app));
					$valid_phrases = array_intersect($lang_phrases, $en_phrases);
					$cache[$_lang][$app] = count($valid_phrases);
				}
				asort($cache['en'], SORT_NUMERIC);
				$cache['en'] = array_reverse($cache['en'], true);
			}
			asort($cache[(string)$_lang], SORT_NUMERIC);
			$cache[(string)$_lang] = array_reverse($cache[(string)$_lang], true);
			egw_cache::setTree(__CLASS__, 'statistics', $cache, self::STATISTIC_CACHE_TIMEOUT);
		}
		return $cache[(string)$_lang];
	}

	/**
	 * Get a state / etag for a given app's translations
	 *
	 * We currently only use a single state for all none-instance-specific apps depending on self::max_lang_time().
	 *
	 * @param string $_app
	 * @param string $_lang
	 * @return string
	 */
	static function etag($_app, $_lang)
	{
		if (!in_array($_app, translation::$instance_specific_translations))
		{
			// check if cache is NOT invalided by checking if we have a modification time for concerned lang-file
			$time = egw_cache::getTree(__CLASS__, $file=self::get_lang_file($_app, $_lang));
			// if we dont have one, cache has been invalidated and we need to load translations
			if (!isset($time)) self::add_app($_app, $_lang);

			$etag = self::max_lang_time();
		}
		else
		{
			$etag = md5(json_encode(egw_cache::getCache(egw_cache::INSTANCE, __CLASS__, $_app.':'.$_lang)));
		}
		//error_log(__METHOD__."('$_app', '$_lang') returning '$etag'");
		return $etag;
	}

	/**
	 * Get or set maximum / latest modification-time for files of not instance-specific translations
	 *
	 * @param type $time
	 * @return type
	 */
	static function max_lang_time($time=null)
	{
		static $max_lang_time = null;

		if (!isset($max_lang_time) || isset($time))
		{
			$max_lang_time = egw_cache::getTree(__CLASS__, 'max_lang_time');
		}
		if (isset($time) && $time > $max_lang_time)
		{
			//error_log(__METHOD__."($time) updating previous max_lang_time=$max_lang_time to $time");
			egw_cache::setTree(__CLASS__, 'max_lang_time', $max_lang_time=$time);
		}
		return $max_lang_time;
	}

	/**
	 * Loads translations for an application direct from the lang-file(s)
	 *
	 * Never use directly, use add_app(), which employes caching (it has to be public, to act as callback for the cache!).
	 *
	 * @param string $app name of the application to add (or 'common' for the general translations)
	 * @param string $lang=false 2 or 5 char lang-code or false for the users language
	 * @param string $just_app_file=null	if given only that app is loaded ignoring self::$load_via
	 * @return array the loaded strings
	 */
	static function &load_app_files($app, $lang, $just_app_file=null)
	{
		//$start = microtime(true);
		$load_app = isset($just_app_file) ? $just_app_file : (isset(self::$load_via[$app]) ? self::$load_via[$app] : $app);
		$loaded = array();
		foreach($load_app == 'all-apps' ? scandir(EGW_SERVER_ROOT) : (array)$load_app as $app_dir)
		{
			if ($load_app == 'all-apps' && $app_dir=='..') continue; // do not try to break out of egw server root
			if ($app_dir[0] == '.' || !is_dir(EGW_SERVER_ROOT.'/'.$app_dir) ||
				!@file_exists($file=self::get_lang_file($app_dir, $lang)) ||
				!($f = fopen($file, 'r')))
			{
				continue;
			}
			// store ctime of file we parse
			egw_cache::setTree(__CLASS__, $file, $time=filemtime($file));
			self::max_lang_time($time);

			$line_nr = 0;
			//use fgets and split the line, as php5.3.3 with squeeze does not support splitting lines with fgetcsv while reading properly
			//if the first letter after the delimiter is a german umlaut (UTF8 representation thereoff)
			//while(($line = fgetcsv($f, 1024, "\t")))
			while(($read = fgets($f)))
			{
				$line = explode("\t", trim($read));
				++$line_nr;
				if (count($line) != 4) continue;
				list($l_id,$l_app,$l_lang,$l_translation) = $line;
				if ($l_lang != $lang) continue;
				if (!isset($just_app_file) && $l_app != $app)
				{
					// check if $l_app contained in file in $app_dir is mentioned in $load_via
					if ($l_app != $app_dir && (!isset(self::$load_via[$l_app]) ||
						!array_intersect((array)self::$load_via[$l_app], array('all-apps', $app_dir))))
					{
						if (!in_array($l_app,array('common','login')) && !file_exists(EGW_SERVER_ROOT.'/'.$l_app))
						{
							error_log(__METHOD__."() lang file $file contains invalid app '$l_app' on line $line_nr --> ignored");
							continue;
						}
						// if not update load_via accordingly and store it as config
						//error_log(__METHOD__."() load_via does not contain $l_app => $app_dir");
						if (!isset(self::$load_via[$l_app])) self::$load_via[$l_app] = array($l_app);
						if (!is_array(self::$load_via[$l_app])) self::$load_via[$l_app] = array(self::$load_via[$l_app]);
						self::$load_via[$l_app][] = $app_dir;
						config::save_value('translation_load_via', self::$load_via, 'phpgwapi');
						egw_cache::setTree(__CLASS__, 'load_via', self::$load_via);
					}
					continue;
				}
				$loaded[$l_id] = $l_translation;
			}
			fclose($f);
		}
		//error_log(__METHOD__."('$app', '$lang') returning ".(is_array($loaded)?'Array('.count($loaded).')':array2string($loaded))." in ".number_format(microtime(true)-$start,3)." secs".' '.function_backtrace());
		return $loaded;
	}

	/**
	 * Cached languages
	 *
	 * @var array
	 */
	static $langs;

	/**
	 * Returns a list of available languages / translations
	 *
	 * @param boolean $translate=true translate language-names
	 * @param boolean $force_read=false force a re-read of the languages
	 * @return array with lang-code => descriptiv lang-name pairs
	 */
	static function get_available_langs($translate=true, $force_read=false)
	{
		if (!is_array(self::$langs) || $force_read)
		{
			if (!($f = fopen($file=EGW_SERVER_ROOT.'/setup/lang/languages','rb')))
			{
				throw new egw_exception("List of available languages (%1) missing!", $file);
			}
			while(($line = fgetcsv($f, null, "\t")))
			{
				self::$langs[$line[0]] = $line[1];
			}
			fclose($f);

			if ($translate)
			{
				if (is_null(self::$db)) self::init(false);

				foreach(self::$langs as $lang => $name)
				{
					self::$langs[$lang] = self::translate($name,False,'');
				}
			}
			uasort(self::$langs,'strcasecmp');
		}
		return self::$langs;
	}

	/**
	 * Returns a list of installed languages / translations
	 *
	 * Translations no longer need to be installed, therefore all available translations are returned here.
	 *
	 * @param boolean $force_read=false force a re-read of the languages
	 * @return array with lang-code => descriptiv lang-name pairs
	 */
	static function get_installed_langs($force_read=false)
	{
		return self::get_available_langs($force_read=false);
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
	 * List all languages, first available ones, then the rest
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
		$languages = self::get_installed_langs();	// available languages
		$availible = "('".implode("','",array_keys($languages))."')";

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
		if ($app == 'common') $app = 'phpgwapi';
		return EGW_SERVER_ROOT.'/'.$app.'/'.self::LANG_DIR.'/'.self::LANGFILE_PREFIX.$lang.self::LANGFILE_EXTENSION;
	}

	/**
	 * returns a list of installed charsets
	 *
	 * @return array with charset as key and comma-separated list of langs useing the charset as data
	 */
	static function get_installed_charsets()
	{
		static $charsets=null;

		if (!isset($charsets))
		{
			$charsets = array(
				'utf-8'      => lang('Unicode').' (utf-8)',
				'iso-8859-1' => lang('Western european').' (iso-8859-1)',
				'iso-8859-2' => lang('Eastern european').' (iso-8859-2)',
				'iso-8859-7' => lang('Greek').' (iso-8859-7)',
				'euc-jp'     => lang('Japanese').' (euc-jp)',
				'euc-kr'     => lang('Korean').' (euc-kr)',
				'koi8-r'     => lang('Russian').' (koi8-r)',
				'windows-1251' => lang('Bulgarian').' (windows-1251)',
				'cp850'      => lang('DOS International').' (CP850)',
			);
		}
		return $charsets;
	}

	/**
	 * Transliterate utf-8 filename to ascii, eg. 'Äpfel' --> 'Aepfel'
	 *
	 * @param string $str
	 * @return string
	 */
	static function to_ascii($str)
	{
		static $extra = array(
			'&szlig;' => 'ss',
		);
		$str = htmlentities($str,ENT_QUOTES,self::charset());
		$str = str_replace(array_keys($extra),array_values($extra),$str);
		$str = preg_replace('/&([aAuUoO])uml;/','\\1e',$str);	// replace german umlauts with the letter plus one 'e'
		$str = preg_replace('/&([a-zA-Z])(grave|acute|circ|ring|cedil|tilde|slash|uml);/','\\1',$str);	// remove all types of accents
		$str = preg_replace('/&([a-zA-Z]+|#[0-9]+|);/','',$str);	// remove all other entities

		return $str;
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
				case 'windows-1252':
				case 'mswin1252':
					if (function_exists('iconv'))
					{
						$prefer_iconv = true;
						break;
					}
					// fall throught to remap to iso-8859-1
				case 'us-ascii':
				case 'macroman':
				case 'iso8859-1':
				case 'windows-1258':
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
				case 'windows-1256':
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
				$data_iso = iconv($from, 'iso-8859-1', $data);
				$convertedData = imap_utf7_encode($data_iso);

				return $convertedData;
			}

			if ($from == 'utf7-imap' && function_exists(imap_utf7_decode))
			{
				$data_iso = imap_utf7_decode($data);
				$convertedData = iconv('iso-8859-1', $to, $data_iso);

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
	 * insert/update/delete one phrase in the lang-table
	 *
	 * @param string $lang
	 * @param string $app
	 * @param string $message_id
	 * @param string $content translation or null to delete translation
	 */
	static function write($lang,$app,$message_id,$content)
	{
		if ($content)
		{
			self::$db->insert(self::LANG_TABLE,array(
				'content' => $content,
			),array(
				'lang' => $lang,
				'app_name' => $app,
				'message_id' => $message_id,
			),__LINE__,__FILE__);
		}
		else
		{
			self::$db->delete(self::LANG_TABLE,array(
				'lang' => $lang,
				'app_name' => $app,
				'message_id' => $message_id,
			),__LINE__,__FILE__);
		}
		// invalidate the cache
		if(!in_array($app,self::$instance_specific_translations))
		{
			egw_cache::unsetCache(egw_cache::TREE,__CLASS__,$app.':'.$lang);
		}
		else
		{
			foreach(array_keys((array)self::get_installed_langs()) as $key)
			{
				egw_cache::unsetCache(egw_cache::INSTANCE,__CLASS__,$app.':'.$key);
			}
		}
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
		$where = array('content '.self::$db->capabilities[egw_db::CAPABILITY_CASE_INSENSITIV_LIKE].' '.self::$db->quote($translation));
		if ($app) $where['app_name'] = $app;
		if ($lang) $where['lang'] = $lang;

		return self::$db->select(self::LANG_TABLE,'message_id',$where,__LINE__,__FILE__)->fetchColumn();
	}

 	/**
	 * detect_encoding - try to detect the encoding
	 *    only to be used if the string in question has no structure that determines his encoding
	 *
	 * @param string - to be evaluated
	 * @param string $verify=null encoding to verify, get checked first and have a match for only ascii or no detection available
	 * @return string - encoding
	 */
	static function detect_encoding($string, $verify=null)
	{
		if (function_exists('iconv'))
		{
			$list = array('utf-8', 'iso-8859-1', 'windows-1251'); // list may be extended

			if ($verify) array_unshift($list, $verify);

			foreach ($list as $item)
			{
				$sample = iconv($item, $item, $string);
				if ($sample == $string)
				{
					return $item;
				}
			}
		}
		if (self::$mbstring)
		{
			$detected = strtolower(mb_detect_encoding($string));
		}
		if ($verify && (!isset($detected) || $detected === 'ascii'))
		{
			return $verify;	// ascii matches all charsets
		}
		return isset($detected) ? $detected : 'iso-8859-1'; // we choose to return iso-8859-1 as default
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
			// some characterreplacements, as they fail to translate
			$sar = array(
				'@(\x84|\x93|\x94)@',
				'@(\x96|\x97|\x1a)@',
				'@(\x91|\x92)@',
				'@(\x85)@',
				'@(\x86)@',
			);
			$rar = array(
				'"',
				'-',
				'\'',
				'...',
				'+',
			);

			$newString = '';

			$string = preg_replace('/\?=\s+=\?/', '?= =?', $_string);

			$elements=imap_mime_header_decode($string);

			$convertAtEnd = false;
			foreach((array)$elements as $element)
			{
				if ($element->charset == 'default') $element->charset = self::detect_encoding($element->text);
				if ($element->charset != 'x-unknown')
				{
					if( strtoupper($element->charset) != 'UTF-8') $element->text = preg_replace($sar,$rar,$element->text);
					// check if there is a possible nested encoding; make sure that the inputstring and the decoded result are different to avoid loops
					if(preg_match('/\?=.+=\?/', $element->text) && $element->text != $_string)
					{
						$element->text = self::decodeMailHeader($element->text, $element->charset);
						$element->charset = $displayCharset;
					}
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
		$text = preg_replace("/(<|&lt;a href=\")*(mailto:([\w\.,-.,_.,0-9.]+)(@)([\w\.,-.,_.,0-9.]+))(>|&gt;)*/i","'$2 '", $text);
		//$text = preg_replace_callback("/(<|&lt;a href=\")*(mailto:([\w\.,-.,_.,0-9.]+)(@)([\w\.,-.,_.,0-9.]+))(>|&gt;)*/i",'self::transform_mailto2text',$text);
		//$text = preg_replace('~<a[^>]+href=\"(mailto:)+([^"]+)\"[^>]*>~si','$2 ',$text);
		$text = preg_replace_callback('~<a[^>]+href=\"(mailto:)+([^"]+)\"[^>]*>([ @\w\.,-.,_.,0-9.]+)<\/a>~si','self::transform_mailto2text',$text);
		$text = preg_replace("/(([\w\.,-.,_.,0-9.]+)(@)([\w\.,-.,_.,0-9.]+))( |\s)*(<\/a>)*( |\s)*(>|&gt;)*/i","'$1 '", $text);
		$text = preg_replace("/(<|&lt;)*(([\w\.,-.,_.,0-9.]+)@([\w\.,-.,_.,0-9.]+))(>|&gt;)*/i","'$2 '", $text);
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
		$singleton = false;
		if ($endtag=='/>') $singleton =true;
		if ($endtag == '' || empty($endtag) || !isset($endtag))
		{
			$endtag = $tag;
		} else {
			$endtag = strtolower($endtag);
			//error_log(__METHOD__.' Using EndTag:'.$endtag);
		}
		// strip tags out of the message completely with their content
		if ($_body) {
			if ($singleton)
			{
				//$_body = preg_replace('~<'.$tag.'[^>].*? '.$endtag.'~simU','',$_body);
				$_body = preg_replace('~<?'.$tag.'[^>].* '.$endtag.'~simU','',$_body); // we are in Ungreedy mode, so we expect * to be ungreedy without specifying ?
			}
			else
			{
				$found=null;
				if ($addbracesforendtag === true )
				{
					if (stripos($_body,'<'.$tag)!==false)  $ct = preg_match_all('#<'.$tag.'(?:\s.*)?>(.+)</'.$endtag.'>#isU', $_body, $found);
					if ($ct>0)
					{
						//error_log(__METHOD__.__LINE__.array2string($found[0]));
						// only replace what we have found
						$_body = str_ireplace($found[0],'',$_body);
					}
					// remove left over tags, unfinished ones, and so on
					$_body = preg_replace('~<'.$tag.'[^>]*?>~si','',$_body);
				}
				if ($addbracesforendtag === false )
				{
					if (stripos($_body,'<'.$tag)!==false)  $ct = preg_match_all('#<'.$tag.'(?:\s.*)?>(.+)'.$endtag.'#isU', $_body, $found);
					if ($ct>0)
					{
						//error_log(__METHOD__.__LINE__.array2string($found[0]));
						// only replace what we have found
						$_body = str_ireplace($found[0],'',$_body);
					}
/*
					$_body = preg_replace('~<'.$tag.'[^>]*?>(.*?)'.$endtag.'~simU','',$_body);
*/
					// remove left over tags, unfinished ones, and so on
					$_body = preg_replace('~<'.$tag.'[^>]*?>~si','',$_body);
					$_body = preg_replace('~'.$endtag.'~','',$_body);
				}
			}
		}
	}

	static function transform_mailto2text($matches)
	{
		//error_log(__METHOD__.__LINE__.array2string($matches));
		// this is the actual url
		$matches[2] = trim(strip_tags($matches[2]));
		$matches[3] = trim(strip_tags($matches[3]));
		$matches[2] = str_replace(array('%40','%20'),array('@',' '),$matches[2]);
		$matches[3] = str_replace(array('%40','%20'),array('@',' '),$matches[3]);
		return $matches[1].$matches[2].($matches[2]==$matches[3]?' ':' -> '.$matches[3].' ');
	}

	static function transform_url2text($matches)
	{
		//error_log(__METHOD__.__LINE__.array2string($matches));
		$linkTextislink = false;
		// this is the actual url
		$matches[2] = trim(strip_tags($matches[2]));
		if ($matches[2]==$matches[1]) $linkTextislink = true;
		$matches[1] = str_replace(' ','%20',$matches[1]);
		return ($linkTextislink?' ':'[ ').$matches[1].($linkTextislink?'':' -> '.$matches[2]).($linkTextislink?' ':' ]');
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
		// assume input isHTML, but test the input anyway, because,
		// if it is not, we may not want to strip whitespace
		$isHTML = true;
		if (strlen(strip_tags($_html)) == strlen($_html))
		{
			$isHTML = false;
			// return $_html; // maybe we should not proceed at all
		}
		if ($displayCharset === false) $displayCharset = self::$system_charset;
		//error_log(__METHOD__.$_html);
		#print '<hr>';
		#print "<pre>"; print htmlspecialchars($_html);
		#print "</pre>";
		#print "<hr>";
		if (stripos($_html,'style')!==false) self::replaceTagsCompletley($_html,'style'); // clean out empty or pagewide style definitions / left over tags
		if (stripos($_html,'head')!==false) self::replaceTagsCompletley($_html,'head'); // Strip out stuff in head
		if (stripos($_html,'![if')!==false && stripos($_html,'<![endif]>')!==false) self::replaceTagsCompletley($_html,'!\[if','<!\[endif\]>',false); // Strip out stuff in ifs
		if (stripos($_html,'!--[if')!==false && stripos($_html,'<![endif]-->')!==false) self::replaceTagsCompletley($_html,'!--\[if','<!\[endif\]-->',false); // Strip out stuff in ifs
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
			'@&(trade|#8482);@i',             //   trade
			'@&#39;@i',                       //   singleQuote
			'@(\xc2\xa0)@',                   //   nbsp or tab (encoded windows-style)
			'@(\xe2\x80\x8b)@',               //   ZERO WIDTH SPACE
		);
		$Replace = array ('',
			'"',
			'#amper#sand#',
			'<',
			'>',
			' ',
			chr(161),
			chr(162),
			chr(163),
			'(C)',//chr(169),// copyrighgt
			'(R)',//chr(174),// registered
			'(TM)',// trade
			"'",
			' ',
			'',
		);
		$_html = preg_replace($Rules, $Replace, $_html);

		//   removing carriage return linefeeds, preserve those enclosed in <pre> </pre> tags
		if ($stripcrl === true )
		{
			if (stripos($_html,'<pre ')!==false || stripos($_html,'<pre>')!==false)
			{
				$contentArr = html::splithtmlByPRE($_html);
				foreach ($contentArr as $k =>&$elem)
				{
					if (stripos($elem,'<pre ')===false && stripos($elem,'<pre>')===false)
					{
						//$elem = str_replace('@(\r\n)@i',' ',$elem);
						$elem = str_replace(array("\r\n","\n"),($isHTML?'':' '),$elem);
					}
				}
				$_html = implode('',$contentArr);
			}
			else
			{
				$_html = str_replace(array("\r\n","\n"),($isHTML?'':' '),$_html);
			}
		}
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
			11 => '/<blockquote>/',
			12 => '~</blockquote>~si',
			13 => '~<blockquote[^>]*>~si',
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
			11 => '#blockquote#type#cite#',
			12 => '#blockquote#end#cite#',
			13 => '#blockquote#type#cite#',
		);
		$_html = preg_replace($tags,$Replace,$_html);
		$_html = preg_replace('~</t(d|h)>\s*<t(d|h)[^>]*>~si',' - ',$_html);
		$_html = preg_replace('~<img[^>]+>~s','',$_html);
		// replace emailaddresses eclosed in <> (eg.: <me@you.de>) with the emailaddress only (e.g: me@you.de)
		self::replaceEmailAdresses($_html);
		//convert hrefs to description -> URL
		//$_html = preg_replace('~<a[^>]+href=\"([^"]+)\"[^>]*>(.*)</a>~si','[$2 -> $1]',$_html);
		$_html = preg_replace_callback('~<a[^>]+href=\"([^"]+)\"[^>]*>(.*?)</a>~si','self::transform_url2text',$_html);

		// reducing double \r\n to single ones, dont mess with pre sections
		if ($stripcrl === true && $isHTML)
		{
			if (stripos($_html,'<pre ')!==false || stripos($_html,'<pre>')!==false)
			{
				$contentArr = html::splithtmlByPRE($_html);
				foreach ($contentArr as $k =>&$elem)
				{
					if (stripos($elem,'<pre ')===false && stripos($elem,'<pre>')===false)
					{
						//this is supposed to strip out all remaining stuff in tags, this is sometimes taking out whole sections off content
						if ( $stripalltags ) {
							$_html = preg_replace('~<[^>^@]+>~s','',$_html);
						}
						// strip out whitespace inbetween CR/LF
						$elem = preg_replace('~\r\n\s+\r\n~si', "\r\n\r\n", $elem);
						// strip out / reduce exess CR/LF
						$elem = preg_replace('~\r\n{3,}~si',"\r\n\r\n",$elem);
					}
				}
				$_html = implode('',$contentArr);
			}
			else
			{
				//this is supposed to strip out all remaining stuff in tags, this is sometimes taking out whole sections off content
				if ( $stripalltags ) {
					$_html = preg_replace('~<[^>^@]+>~s','',$_html);
				}
				// strip out whitespace inbetween CR/LF
				$_html = preg_replace('~\r\n\s+\r\n~si', "\r\n\r\n", $_html);
				// strip out / reduce exess CR/LF
				$_html = preg_replace('~(\r\n){3,}~si',"\r\n\r\n",$_html);
			}
		}
		//this is supposed to strip out all remaining stuff in tags, this is sometimes taking out whole sections off content
		if ( $stripalltags ) {
			$_html = preg_replace('~<[^>^@]+>~s','',$_html);
			//$_html = strip_tags($_html, '<a>');
		}
		// reducing spaces (not for input that was plain text from the beginning)
		if ($isHTML) $_html = preg_replace('~ +~s',' ',$_html);
		// restoring ampersands
		$_html = str_replace('#amper#sand#','&',$_html);
		//error_log(__METHOD__.__LINE__.' Charset:'.$displayCharset.' -> '.$_html);
		$_html = html_entity_decode($_html, ENT_COMPAT, $displayCharset);
		//error_log(__METHOD__.__LINE__.' Charset:'.$displayCharset.' After html_entity_decode: -> '.$_html);
		//self::replaceEmailAdresses($_html);
		$pos = strpos($_html, 'blockquote');
		//error_log("convert HTML2Text: $_html");
		if($pos === false) {
			return $_html;
		} else {
			$indent = 0;
			$indentString = '';

			$quoteParts = preg_split('/#blockquote#type#cite#/', $_html, -1, PREG_SPLIT_OFFSET_CAPTURE);
			foreach($quoteParts as $quotePart) {
				if($quotePart[1] > 0) {
					$indent++;
					$indentString .= '>';
				}
				$quoteParts2 = preg_split('/#blockquote#end#cite#/', $quotePart[0], -1, PREG_SPLIT_OFFSET_CAPTURE);

				foreach($quoteParts2 as $quotePart2) {
					if($quotePart2[1] > 0) {
						$indent--;
						$indentString = substr($indentString, 0, $indent);
					}

					$quoteParts3 = explode("\r\n", $quotePart2[0]);

					foreach($quoteParts3 as $quotePart3) {
						//error_log(__METHOD__.__LINE__.'Line:'.$quotePart3);
						$allowedLength = 76-strlen("\r\n$indentString");
						// only break lines, if not already indented
						if (substr($quotePart3,0,strlen($indentString)) != $indentString)
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
										//error_log(__METHOD__.__LINE__.'LongWordFound:'.$v);
										$v=wordwrap($v, $allowedLength, "\r\n$indentString", true);
									}
									// the rest should be broken at the start of the new word that exceeds the limit
									if ($linecnt+$cnt > $allowedLength) {
										$v="\r\n$indentString$v";
										//error_log(__METHOD__.__LINE__.'breaking here:'.$v);
										$linecnt = 0;
									} else {
										$linecnt += $cnt;
									}
									if (strlen($v))  $quotePart3 .= (strlen($quotePart3) ? " " : "").$v;
								}
							}
						}
						//error_log(__METHOD__.__LINE__.'partString to return:'.$indentString . $quotePart3);
						$asciiTextBuff[] = $indentString . $quotePart3 ;
					}
				}
			}
			return implode("\r\n",$asciiTextBuff);
		}
	}
}
