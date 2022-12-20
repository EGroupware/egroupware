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
 */

namespace EGroupware\Api;

/**
 * EGroupware API - Translations
 *
 * All methods of this class can now be called static.
 *
 * Translations are cached tree-wide via Cache class.
 *
 * Translations are no longer stored in database, but load directly from *.lang files into cache.
 * Only exception as instance specific translations: mainscreen, loginscreen and custom (see $instance_specific_translations)
 */
class Translation
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
	 * @var Db
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
	 * Internal encoding / charset of PHP / mbstring (if loaded)
	 *
	 * @var string
	 */
	static $default_charset;

	/**
	 * Application which translations have to be cached instance- and NOT tree-specific
	 *
	 * @var array
	 */
	static $instance_specific_translations = array('loginscreen','mainscreen','custom');

	/**
	 * returns the charset to use (!$lang) or the charset of the lang-files or $lang
	 *
	 * @param string|boolean $lang =False return charset of the active user-lang, or $lang if specified
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
		$ini_default_charset = version_compare(PHP_VERSION, '5.6', '<') ? 'mbstring.internal_encoding' : 'default_charset';
		if (ini_get($ini_default_charset) && self::$default_charset != $charset)
		{
			ini_set($ini_default_charset, self::$default_charset = $charset);
		}
		return $charset;
	}

	/**
	 * Initialises global lang-array and loads the 'common' and app-spec. translations
	 *
	 * @param boolean $load_translations =true should we also load translations for common and currentapp
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
				$ini_default_charset = version_compare(PHP_VERSION, '5.6', '<') ? 'mbstring.internal_encoding' : 'default_charset';
				ini_set($ini_default_charset, self::$system_charset);
			}
		}

		// try loading load_via from tree-wide cache and check if it contains more rules
		if (($load_via = Cache::getTree(__CLASS__, 'load_via')) &&
			$load_via >= self::$load_via && 	// > for array --> contains more elements
			// little sanity check: cached array contains all stock keys, otherwise ignore it
			!array_diff_key(self::$load_via, $load_via))
		{
			self::$load_via = $load_via;
			//error_log(__METHOD__."() load_via set from tree-wide cache to ".array2string(self::$load_via));
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
			if (class_exists('EGroupware\\Api\\Etemplate', false) || class_exists('etemplate', false)) $apps[] = 'etemplate';
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
	 * @param array $vars =null vars to replace the placeholders, or null for none
	 * @param string $not_found ='*' what to add to not found phrases, default '*'
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
	 * Translates a phrase according to the given user's language preference,
	 * which may be different from the current user.
	 *
	 * @param int $account_id
	 * @param string $message
	 * @param array $vars =null vars to replace the placeholders, or null for none
	 */
	static function translate_as($account_id, $message, $vars=null)
	{
		if(!is_numeric($account_id))
		{
			return static::translate($message, $vars);
		}

		$preferences = new Preferences($account_id);
		$prefs = $preferences->read();
		if($prefs['common']['lang'] != $GLOBALS['egw_info']['user']['preferences']['common']['lang'])
		{
			$old_lang = self::$userlang;
			$GLOBALS['egw_info']['user']['preferences']['common']['lang'] = $prefs['common']['lang'];
			$apps = array_keys(self::$loaded_apps);
			self::init(true);
			self::add_app($apps);
		}
		$phrase = static::translate($message, $vars);
		if (!empty($old_lang))
		{
			$GLOBALS['egw_info']['user']['preferences']['common']['lang'] = $old_lang;
			self::init(true);
			self::add_app($apps);
		}
		return $phrase;
	}

	/**
	 * Adds translations for (multiple) application(s)
	 *
	 * By default the translations are read from the tree-wide cache
	 *
	 * @param string|array $apps name(s) of application(s) to add (or 'common' for the general translations)
	 * 	if multiple names given, they are requested in one request from cache and loaded in given order
	 * @param string $lang =false 2 or 5 char lang-code or false for the users language
	 */
	static function add_app($apps, $lang=null)
	{
		//error_log(__METHOD__."(".array2string($apps).", $lang) count(self::\$lang_arr)=".count(self::$lang_arr));
		//$start = microtime(true);
		if (!$lang) $lang = self::$userlang;
		$tree_level = $instance_level = array();
		if (!is_array($apps)) $apps = (array)$apps;
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
		if ($tree_level) $tree_level = Cache::getTree(__CLASS__, $tree_level);
		if ($instance_level) $instance_level = Cache::getInstance(__CLASS__, $instance_level);

		// merging loaded translations together
		$updated_load_via = false;
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
					$loaded =& self::load_app_files($app, $l, null, $updated_load_via);
				}
				//error_log(__METHOD__."('$app', '$lang') instance_specific=$instance_specific, load_app(_files)() returned ".(is_array($loaded)?'Array('.count($loaded).')':array2string($loaded)));
				if ($loaded)
				{
					Cache::setCache($instance_specific ? Cache::INSTANCE : Cache::TREE,
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
			$custom = Cache::getInstance(__CLASS__, 'custom:en');
			if($custom)
			{
				self::$lang_arr = array_merge(self::$lang_arr, $custom);
			}
		}
		if ($updated_load_via)
		{
			self::update_load_via();
		}
		//error_log(__METHOD__.'('.array2string($apps).", '$lang') took ".(1000*(microtime(true)-$start))." ms, loaded_apps=".array2string(self::$loaded_apps).", loaded ".count($loaded)." phrases -> total=".count(self::$lang_arr));//.": ".function_backtrace());
	}

	/**
	 * Loads translations for an application from the database or direct from the lang-file for setup
	 *
	 * Never use directly, use add_app(), which employes caching (it has to be public, to act as callback for the cache!).
	 *
	 * @param string $app name of the application to add (or 'common' for the general translations)
	 * @param string $lang =false 2 or 5 char lang-code or false for the users language
	 * @return array the loaded strings
	 */
	static function &load_app($app,$lang)
	{
		//$start = microtime(true);
		if (!isset(self::$db))
		{
			self::init(false);
			if (!isset(self::$db)) return;
		}
		$loaded = array();
		try {
			foreach (self::$db->select(self::LANG_TABLE, 'message_id,content', array(
				'lang' => $lang,
				'app_name' => $app,
			), __LINE__, __FILE__) as $row)
			{
				$loaded[strtolower($row['message_id'])] = $row['content'];
			}
		}
		catch (Db\Exception $e) {
			// ignore error
		}
		//error_log(__METHOD__."($app,$lang) took ".(1000*(microtime(true)-$start))." ms to load ".count($loaded)." phrases");
		return $loaded;
	}

	/**
	 * How to load translations for a given app
	 *
	 * Translations for common, preferences or admin are in spread over all applications.
	 * Api, old phpgwapi and etemplate have translations for some pseudo-apps.
	 *
	 * @var array app => app(s) or string 'all-apps'
	 */
	static $load_via = array(
		'common' => 'all-apps',
		'preferences' => 'all-apps',
		'admin' => 'all-apps',
		'jscalendar' => array('phpgwapi'),
		'sitemgr-link' => array('sitemgr'),
		'groupdav' => array('api'),
		'developer_tools' => array('etemplate'),
		'login' => array('api','registration'),
	);

	/**
	 * Check if cached translations are up to date or invalidate cache if not
	 *
	 * Called via login.php for each interactive login.
	 */
	static function check_invalidate_cache()
	{
		$lang = $GLOBALS['egw_info']['user']['preferences']['common']['lang'];
		$apps = array_keys($GLOBALS['egw_info']['apps']);
		foreach($apps as $app)
		{
			$file = self::get_lang_file($app, $lang);
			// check if file has changed compared to what's cached
			if (file_exists($file))
			{
				$cached_time = Cache::getTree(__CLASS__, $file);
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
		Cache::unsetTree(__CLASS__, $app.':'.$lang);
		Cache::unsetTree(__CLASS__, self::get_lang_file($app, $lang));

		foreach(self::$load_via as $load => $via)
		{
			//error_log("load_via[load='$load'] = via = ".array2string($via));
			if ($via === 'all-apps' || in_array($app, (array)$via))
			{
				//error_log(__METHOD__."('$app', '$lang') additional invalidate translations $load:$lang");
				Cache::unsetTree(__CLASS__, $load.':'.$lang);
				Cache::unsetTree(__CLASS__, self::get_lang_file($load, $lang));
			}
		}
		// unset statistics
		Cache::unsetTree(__CLASS__, 'statistics');
	}

	const STATISTIC_CACHE_TIMEOUT = 86400;

	/**
	 * Statistical values about how much a language and app is translated, number or valid phrases per $lang or $lang/$app
	 *
	 * @param string $_lang =null
	 * @return array $lang or $app => number pairs
	 */
	static function statistics($_lang=null)
	{
		$cache = Cache::getTree(__CLASS__, 'statistics');

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
			Cache::setTree(__CLASS__, 'statistics', $cache, self::STATISTIC_CACHE_TIMEOUT);
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
		if (!in_array($_app, self::$instance_specific_translations))
		{
			// check if cache is NOT invalided by checking if we have a modification time for concerned lang-file
			$time = Cache::getTree(__CLASS__, $file=self::get_lang_file($_app, $_lang));
			// if we dont have one, cache has been invalidated and we need to load translations
			if (!isset($time)) self::add_app($_app, $_lang);

			$etag = self::max_lang_time();
		}
		else
		{
			$etag = md5(json_encode(Cache::getCache(Cache::INSTANCE, __CLASS__, $_app.':'.$_lang)));
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
			$max_lang_time = Cache::getTree(__CLASS__, 'max_lang_time');
		}
		if (isset($time) && $time > $max_lang_time)
		{
			//error_log(__METHOD__."($time) updating previous max_lang_time=$max_lang_time to $time");
			Cache::setTree(__CLASS__, 'max_lang_time', $max_lang_time=$time);
		}
		return $max_lang_time;
	}

	/**
	 * Loads translations for an application direct from the lang-file(s)
	 *
	 * Never use directly, use add_app(), which employes caching (it has to be public, to act as callback for the cache!).
	 *
	 * @param string $app name of the application to add (or 'common' for the general translations)
	 * @param string $lang =false 2 or 5 char lang-code or false for the users language
	 * @param string $just_app_file =null if given only that app is loaded ignoring self::$load_via
	 * @param boolean $updated_load_via =false on return true if self::$load_via was updated
	 * @return array the loaded strings
	 */
	static function &load_app_files($app, $lang, $just_app_file=null, &$updated_load_via=false)
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
			Cache::setTree(__CLASS__, $file, $time=filemtime($file));
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
						if (!isset(self::$load_via[$l_app]) && !file_exists(EGW_SERVER_ROOT.'/'.$l_app))
						{
							error_log(__METHOD__."() lang file $file contains invalid app '$l_app' on line $line_nr --> ignored");
							continue;
						}
						// if not update load_via accordingly and store it as config
						//error_log(__METHOD__."() load_via does not contain $l_app => $app_dir");
						if (!isset(self::$load_via[$l_app])) self::$load_via[$l_app] = array($l_app);
						if (!is_array(self::$load_via[$l_app])) self::$load_via[$l_app] = array(self::$load_via[$l_app]);
						self::$load_via[$l_app][] = $app_dir;
						$updated_load_via = true;
					}
					else if ($l_app != $app_dir &&
						array_intersect((array)self::$load_via[$l_app], array('all-apps', $app_dir)))
					{
						$loaded[$l_id] = $l_translation;
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
	 * Update tree-wide stored load_via with our changes
	 *
	 * Merging in meantime stored changes from other instances to minimize race-conditions
	 */
	protected static function update_load_via()
	{
		if (($load_via = Cache::getTree(__CLASS__, 'load_via')) &&
			// little sanity check: cached array contains all stock keys, otherwise ignore it
			!array_diff_key(self::$load_via, $load_via))
		{
			foreach($load_via as $app => $via)
			{
				if (self::$load_via[$app] != $via)
				{
					//error_log(__METHOD__."() setting load_via[$app]=".array2string($via));
					self::$load_via[$app] = array_unique(array_merge((array)self::$load_via[$app], (array)$via));
				}
			}
		}
		Cache::setTree(__CLASS__, 'load_via', self::$load_via);
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
	 * @param boolean $translate =true translate language-names
	 * @param boolean $force_read =false force a re-read of the languages
	 * @return array with lang-code => descriptiv lang-name pairs
	 */
	static function get_available_langs($translate=true, $force_read=false)
	{
		if (!is_array(self::$langs) || $force_read)
		{
			if (!($f = fopen($file=EGW_SERVER_ROOT.'/setup/lang/languages','rb')))
			{
				throw new Exception("List of available languages (%1) missing!", $file);
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
	 * @param boolean $force_read =false force a re-read of the languages
	 * @return array with lang-code => descriptiv lang-name pairs
	 */
	static function get_installed_langs($force_read=false)
	{
		return self::get_available_langs($force_read);
	}

	/**
	 * translates a 2 or 5 char lang-code into a (verbose) language
	 *
	 * @param string $lang
	 * @return string|false language or false if not found
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
	 * @param boolean $force_read =false
	 * @return array with lang_id => lang_name pairs
	 */
	static function list_langs($force_read=false)
	{
		if (!$force_read)
		{
			return Cache::getInstance(__CLASS__,'list_langs',array(__CLASS__,'list_langs'),array(true));
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
	static function get_lang_file($app,$lang,$root=EGW_SERVER_ROOT)
	{
		if ($app == 'common') $app = 'api';

		return $root.'/'.$app.'/'.self::LANG_DIR.'/'.self::LANGFILE_PREFIX.$lang.self::LANGFILE_EXTENSION;
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
	 * @param string $_str
	 * @return string
	 */
	static function to_ascii($_str)
	{
		static $extra = array(
			'&szlig;' => 'ss',
			'&#776;'  => 'e',	// mb_convert_encoding return &#776; for all German umlauts
		);
		if (function_exists('mb_convert_encoding'))
		{
			$entities = mb_convert_encoding($_str, 'html-entities', self::charset());
		}
		else
		{
			$entities = htmlentities($_str, ENT_QUOTES, self::charset());
		}

		$estr = str_replace(array_keys($extra),array_values($extra), $entities);
		$ustr = preg_replace('/&([aAuUoO])uml;/','\\1e', $estr);	// replace german umlauts with the letter plus one 'e'
		$astr = preg_replace('/&([a-zA-Z])(grave|acute|circ|ring|cedil|tilde|slash|uml);/','\\1', $ustr);	// remove all types of accents

		return preg_replace('/[^\x20-\x7f]/', '',					// remove all non-ascii
			preg_replace('/&([a-zA-Z]+|#[0-9]+|);/','', $astr));	// remove all other entities
	}

	/**
	 * converts a string $data from charset $from to charset $to
	 *
	 * @param string|array $data string(s) to convert
	 * @param string|boolean $from charset $data is in or False if it should be detected
	 * @param string|boolean $to charset to convert to or False for the system-charset the converted string
	 * @param boolean $check_to_from =true internal to bypass all charset replacements
	 * @return NULL|string|array converted string(s) from $data
	 */
	static function convert($data,$from=False,$to=False,$check_to_from=true)
	{
		if (empty($data))
		{
			return $data;	// no need for any charset conversation (NULL, '', 0, '0', array())
		}
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
				case 'ks_c_5601-1987':
					$from = 'CP949';
					break;
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
				case 'windows-1253':
					$from = 'iso-8859-7';
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
				$ret[$key] = empty($str) ? $str :	// do NOT convert null to '' (other empty values need no conversation too)
					self::convert($str,$from,$to,false);	// false = bypass the above checks, as they are already done
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
		try {
			if (self::$mbstring && !$prefer_iconv && ($data = @mb_convert_encoding($data, $to, $from)) != '')
			{
				return $data;
			}
		}
		catch (\ValueError $e) {
			// ignore encodings unknown to mb_convert_encoding
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
	 * converts a string $data from charset $from to something that is json_encode tested
	 *
	 * @param string|array $_data string(s) to convert
	 * @param string|boolean $from charset $data is in or False if it should be detected
	 * @return string|array converted string(s) from $data
	 */
	static function convert_jsonsafe($_data,$from=False)
	{
		if ($from===false) $from = self::detect_encoding($_data);

		$data = self::convert($_data, strtolower($from));

		// in a way, this tests if we are having real utf-8 (the displayCharset) by now; we should if charsets reported (or detected) are correct
		if (strtoupper(self::charset()) == 'UTF-8')
		{
			$test = @json_encode($data);
			//error_log(__METHOD__.__LINE__.' ->'.strlen($data).' Error:'.json_last_error().'<- data:#'.$test.'#');
			if (($test=="null" || $test === false || is_null($test)) && strlen($data)>0)
			{
				// try to fix broken utf8
				$x = (function_exists('mb_convert_encoding')?mb_convert_encoding($data,'UTF-8','UTF-8'):(function_exists('iconv')?@iconv("UTF-8","UTF-8//IGNORE",$data):$data));
				$test = @json_encode($x);
				if (($test=="null" || $test === false || is_null($test)) && strlen($data)>0)
				{
					// this should not be needed, unless something fails with charset detection/ wrong charset passed
					error_log(__METHOD__.__LINE__.' Charset Reported:'.$from.' Charset Detected:'.self::detect_encoding($data));
					$data = utf8_encode($data);
				}
				else
				{
					$data = $x;
				}
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
			Cache::unsetCache(Cache::TREE,__CLASS__,$app.':'.$lang);
		}
		else
		{
			foreach(array_keys((array)self::get_installed_langs()) as $key)
			{
				Cache::unsetCache(Cache::INSTANCE,__CLASS__,$app.':'.$key);
			}
		}
 	}

	/**
	 * read one phrase from the lang-table
	 *
	 * @param string $lang
	 * @param string $app_name
	 * @param string $message_id
	 * @return string|boolean content or false if not found
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
	 * @param string $app ='' default check all apps
	 * @param string $lang ='' default check all langs
	 * @return string
	 */
	static function get_message_id($translation,$app=null,$lang=null)
	{
		$where = array('content '.self::$db->capabilities[Db::CAPABILITY_CASE_INSENSITIV_LIKE].' '.self::$db->quote($translation));
		if ($app) $where['app_name'] = $app;
		if ($lang) $where['lang'] = $lang;

		$id = self::$db->select(self::LANG_TABLE,'message_id',$where,__LINE__,__FILE__)->fetchColumn();

		// Check cache, since most things aren't in the DB anymore
		if(!$id)
		{
			$ids = array_filter(array_keys(self::$lang_arr), function($haystack) use($translation) {
				return stripos(self::$lang_arr[$haystack],$translation) !== false;
			});
			$id = array_shift($ids);
			if(!$id && ($lang && $lang !== 'en' || self::$userlang != 'en'))
			{
				// Try english
				if (in_array($app, self::$instance_specific_translations))
				{
					$instance_level[] = $app.':en';
				}
				else
				{
					$tree_level[] = $app.':en';
				}

				// load all translations from cache at once
				if ($tree_level) $lang_arr = Cache::getTree(__CLASS__, $tree_level);
				if ($instance_level) $lang_arr = Cache::getInstance(__CLASS__, $instance_level);
				$lang_arr = $lang_arr[$app.':en'] ?? [];
				$ids = array_filter(array_keys($lang_arr), function($haystack) use($translation, $lang_arr) {
					return stripos($lang_arr[$haystack],$translation) !== false;
				});
				$id = array_shift($ids);
			}
		}

		return $id;
	}

 	/**
	 * detect_encoding - try to detect the encoding
	 *    only to be used if the string in question has no structure that determines his encoding
	 *
	 * @param string - to be evaluated
	 * @param string $verify =null encoding to verify, get checked first and have a match for only ascii or no detection available
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
}