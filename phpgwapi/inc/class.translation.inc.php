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

// define the maximal length of a message_id, all message_ids have to be unique
// in this length, our column is varchar 128
if (!defined('MAX_MESSAGE_ID_LENGTH'))
{
	define('MAX_MESSAGE_ID_LENGTH',128);
}
// some constanst for pre php4.3
if (!defined('PHP_SHLIB_SUFFIX'))
{
	define('PHP_SHLIB_SUFFIX',strtoupper(substr(PHP_OS, 0,3)) == 'WIN' ? 'dll' : 'so');
}
if (!defined('PHP_SHLIB_PREFIX'))
{
	define('PHP_SHLIB_PREFIX',PHP_SHLIB_SUFFIX == 'dll' ? 'php_' : '');
}

// Define prefix for langfiles (historically 'phpgw_')
define('EGW_LANGFILE_PREFIX', 'egw_');
define('PHPGW_LANGFILE_PREFIX', 'phpgw_');

/**
 * eGroupWare API - Translations
 */
class translation
{
	var $userlang = 'en';
	var $loaded_apps = array();
	var $line_rejected = array();
	var $lang_array = array();
	var $lang_table = 'egw_lang';
	var $languages_table = 'egw_languages';
	var $config_table = 'egw_config';
	/**
	 * Instance of the db-class
	 *
	 * @var egw_db
	 */
	var $db;
	/**
	 * Mark untranslated strings with an asterisk (*), values '' or 'yes'
	 *
	 * @var string
	 */
	var $markunstranslated;

	/**
	 * Constructor, sets up a copy of the db-object, gets the system-charset and tries to load the mbstring extension
	 */
	function translation($warnings = False)
	{
		for ($i = 1; $i <= 9; $i++) {
			$this->placeholders[] = '%'.$i;
		}
		$this->db = is_object($GLOBALS['egw']->db) ? $GLOBALS['egw']->db : $GLOBALS['egw_setup']->db;

		if (!isset($GLOBALS['egw_setup'])) {
			$this->system_charset = @$GLOBALS['egw_info']['server']['system_charset'];
		} else {
			$this->system_charset =& $GLOBALS['egw_setup']->system_charset;
		}

		if (extension_loaded('mbstring') || @dl(PHP_SHLIB_PREFIX.'mbstring.'.PHP_SHLIB_SUFFIX)) {
			$this->mbstring = true;
			if(!empty($this->system_charset)) {
				ini_set('mbstring.internal_encoding',$this->system_charset);
			}
			if (ini_get('mbstring.func_overload') < 7) {
				if ($warnings) {
					echo "<p>Warning: Please set <b>mbstring.func_overload = 7</b> in your php.ini for useing <b>$this->system_charset</b> as your charset !!!</p>\n";
				}
			}
		} else {
			if ($warnings) {
				echo "<p>Warning: Please get and/or enable the <b>mbstring extension</b> in your php.ini for useing <b>$this->system_charset</b> as your charset, we are defaulting to <b>iconv</b> for now !!!</p>\n";
			}
		}
	}

	/**
	 * returns the charset to use (!$lang) or the charset of the lang-files or $lang
	 *
	 * @param string/boolean $lang=False return charset of the active user-lang, or $lang if specified
	 * @return string charset
	 */
	function charset($lang=False)
	{
		if ($lang)
		{
			if (!isset($this->charsets[$lang]))
			{
				if (!($this->charsets[$lang] = $this->db->select($this->lang_table,'content',array(
					'lang'		=> $lang,
					'message_id'=> 'charset',
					'app_name'	=> 'common',
				),__LINE__,__FILE__)->fetchSingle()))
				{
					$this->charsets[$lang] = 'iso-8859-1';
				}
			}
			return $this->charsets[$lang];
		}
		if ($this->system_charset)	// do we have a system-charset ==> return it
		{
			$charset = $this->system_charset;
		}
		else
		{
			// if no translations are loaded (system-startup) use a default, else lang('charset')
			$charset = !is_array(@$this->lang_arr) ? 'iso-8859-1' : strtolower($this->translate('charset'));
		}
		// we need to set our charset as mbstring.internal_encoding if mbstring.func_overlaod > 0
		// else we get problems for a charset is different from the default utf-8
		if (ini_get('mbstring.func_overload') && $this->mbstring_internal_encoding != $charset)
		{
			ini_set('mbstring.internal_encoding',$this->mbstring_internal_encoding = $charset);
		}			
		return $charset;
	}

	/**
	 * Initialises global lang-array and loads the 'common' and app-spec. translations
	 */
	function init()
	{
		if (!is_array(@$this->lang_arr))
		{
			$this->lang_arr = array();
		}

		if ($GLOBALS['egw_info']['user']['preferences']['common']['lang'])
		{
			$this->userlang = $GLOBALS['egw_info']['user']['preferences']['common']['lang'];
		}
		$this->add_app('common');
		if (!count($this->lang_arr))
		{
			$this->userlang = 'en';
			$this->add_app('common');
		}
		$this->add_app($GLOBALS['egw_info']['flags']['currentapp']);
		
		$this->markunstranslated = $GLOBALS['egw_info']['server']['markuntranslated'];
	}

	/**
	 * translates a phrase and evtl. substitute some variables
	 *
	 * @param string $key phrase to translate, may contain placeholders %N (N=1,2,...) for vars
	 * @param array/boolean $vars=false vars to replace the placeholders, or false for none
	 * @param string $not_found='*' what to add to not found phrases, default '*'
	 * @return string with translation
	 */
	function translate($key, $vars=false, $not_found='*' )
	{
		if (!is_array(@$this->lang_arr) || !count($this->lang_arr))
		{
			$this->init();
		}
		$ret = $key;				// save key if we dont find a translation
		if ($not_found && $this->markunstranslated) $ret .= $not_found;

		if (isset($this->lang_arr[$key]))
		{
			$ret = $this->lang_arr[$key];
		}
		else
		{
			$new_key = strtolower(trim(substr($key,0,MAX_MESSAGE_ID_LENGTH)));

			if (isset($this->lang_arr[$new_key]))
			{
				// we save the original key for performance
				$ret = $this->lang_arr[$key] =& $this->lang_arr[$new_key];
			}
		}
		if (is_array($vars) && count($vars))
		{
			if (count($vars) > 1)
			{
				$ret = str_replace($this->placeholders,$vars,$ret);
			}
			else
			{
				$ret = str_replace('%1',$vars[0],$ret);
			}
		}
		return $ret;
	}

	/**
	 * adds translations for an application from the database to the lang-array
	 *
	 * @param string $app name of the application to add (or 'common' for the general translations)
	 * @param string/boolean $lang=false 2 or 5 char lang-code or false for the users language
	 */
	function add_app($app,$lang=False)
	{
		$lang = $lang ? $lang : $this->userlang;

		if (!isset($this->loaded_apps[$app]) || $this->loaded_apps[$app] != $lang)
		{
			if ($app == 'setup') return $this->add_setup($lang);

			foreach($this->db->select($this->lang_table,'message_id,content',array(
				'lang'		=> $lang,
				'app_name'	=> $app,
			),__LINE__,__FILE__) as $row)
			{
				$this->lang_arr[strtolower ($row['message_id'])] = $row['content'];
			}
			$this->loaded_apps[$app] = $lang;
		}
	}

	/**
	 * Adds setup's translations, they are not in the DB!
	 *
	 * @param string $lang 2 or 5 char lang-code
	 */
	function add_setup($lang)
	{
		foreach(array(
			EGW_SERVER_ROOT.'/setup/lang/' . EGW_LANGFILE_PREFIX . $lang . '.lang',
			EGW_SERVER_ROOT.'/setup/lang/' . PHPGW_LANGFILE_PREFIX . $lang . '.lang',
			EGW_SERVER_ROOT.'/setup/lang/' . EGW_LANGFILE_PREFIX . 'en.lang',
			EGW_SERVER_ROOT.'/setup/lang/' . PHPGW_LANGFILE_PREFIX . 'en.lang',
		) as $fn)
		{
			if (file_exists($fn))
			{
				$fp = fopen($fn,'r');
				while ($data = fgets($fp,8000))
				{
					// explode with "\t" and removing "\n" with str_replace, needed to work with mbstring.overload=7
					list($message_id,,,$content) = explode("\t",$data);
					$phrases[strtolower(trim($message_id))] = str_replace("\n",'',$content);
				}
				fclose($fp);
				
				foreach($phrases as $message_id => $content)
				{
					$this->lang_arr[$message_id] = $this->convert($content,$phrases['charset']);
				}
			}
			break;
		}
		$this->loaded_apps['setup'] = $lang;
	}

	/**
	 * Cached languages
	 *
	 * @var array
	 */
	var $langs;

	/**
	 * returns a list of installed langs
	 *
	 * @param boolean $force_read=false force a re-read of the languages
	 * @return array with lang-code => descriptiv lang-name pairs
	 */
	function get_installed_langs($force_read=false)
	{
		if (!is_array($this->langs) || $force_read)
		{
			$this->langs = array();
			foreach($this->db->select($this->lang_table,'DISTINCT lang,lang_name','lang = lang_id',__LINE__,__FILE__,
				false,'',false,0,','.$this->languages_table) as $row)
			{
				$this->langs[$row['lang']] = $row['lang_name'];
			}
			if (!$this->langs)
			{
				return false;
			}
			foreach($this->langs as $lang => $name)
			{
				$this->langs[$lang] = $this->translate($name,False,'');
			}
			uasort($this->langs,'strcasecmp');
		}
		return $this->langs;
	}

	/**
	 * translates a 2 or 5 char lang-code into a (verbose) language
	 *
	 * @param string $lang
	 * @return string/false language or false if not found
	 */
	function lang2language($lang)
	{
		if (isset($this->langs[$lang]))	// no need to query the DB
		{
			return $this->langs[$lang];
		}
		return $this->db->select($this->languages_table,'lang_name',array('lang_id' => $lang),__LINE__,__FILE__)->fetchSingle();
	}

	/**
	 * List all languages, first the installed ones, then the availible ones and last the rest
	 *
	 * @return array with lang_id => lang_name pairs
	 */
	function list_langs()
	{
		$languages = $this->get_installed_langs();	// used translated installed languages

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
		foreach($this->db->select($this->languages_table,array(
			'lang_id','lang_name',
			"CASE WHEN lang_id IN $availible THEN 1 ELSE 0 END AS availible",
		),"lang_id NOT IN ('".implode("','",array_keys($languages))."')",__LINE__,__FILE__,false,' ORDER BY availible DESC,lang_name') as $row)
		{
			$languages[$row['lang_id']] = $row['lang_name'];
		}
		return $languages;
	}

	/**
	 * Cached charsets
	 *
	 * @var array
	 */
	var $charsets;

	/**
	 * returns a list of installed charsets
	 *
	 * @return array with charset as key and comma-separated list of langs useing the charset as data
	 */
	function get_installed_charsets()
	{
		if (!is_array($this->charsets))
		{
			$this->get_installed_langs();

			$distinct = $this->db->capabilities['distinct_on_text'] ? 'DISTINCT' : '';
			$this->charsets = array();
			foreach($this->db->select($this->lang_table,$distinct.' lang,lang_name,content AS charset',array(
				'message_id' => 'charset',
			),__LINE__,__FILE__,false,'',false,0,",$this->languages_table WHERE lang = lang_id") as $row)
			{
				$data = &$this->charsets[$charset = strtolower($row['charset'])];
				$lang = $this->langs[$row['lang']].' ('.$row['lang'].')';
				if ($distinct || strpos($data,$lang) === false)
				{
					$data .= ($data ? ', ' : $charset.': ').$lang;
				}						
			}
			if (!$this->charsets)
			{
				return False;
			}
			// add the old charsets, to provide some alternatives to utf-8 while importing
			foreach(array(
				'iso-8859-1' => 'Western european',
				'iso-8859-2' => 'Eastern european',
				'iso-8859-7' => 'Greek',
				'euc-jp'     => 'Japanese',
				'euc-kr'     => 'Korean',
				'koi8-r'     => 'Russian',				
				'windows-1251' => 'Bulgarian') as $charset => $lang)
			{
				$this->charsets[$charset] .= (!isset($this->charsets[$charset]) ? $charset.': ' : ', ') . $lang;
			}
		}
		return $this->charsets;
	}

	/**
	 * converts a string $data from charset $from to charset $to
	 *
	 * @param string/array $data string(s) to convert
	 * @param string/boolean $from charset $data is in or False if it should be detected
	 * @param string/boolean $to charset to convert to or False for the system-charset the converted string
	 * @return string/array converted string(s) from $data
	 */
	function convert($data,$from=False,$to=False)
	{
		if (is_array($data))
		{
			foreach($data as $key => $str)
			{
				$ret[$key] = $this->convert($str,$from,$to);
			}
			return $ret;
		}

		if ($from)
		{
			$from = strtolower($from);
		}
		if ($to)
		{
			$to = strtolower($to);
		}

		if (!$from)
		{
			$from = $this->mbstring ? strtolower(mb_detect_encoding($data)) : 'iso-8859-1';
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
			$to = $this->charset();
		}
		if ($from == $to || !$from || !$to || !$data)
		{
			return $data;
		}
		if ($from == 'iso-8859-1' && $to == 'utf-8')
		{
			return utf8_encode($data);
		}
		if ($to == 'iso-8859-1' && $from == 'utf-8')
		{
			return utf8_decode($data);
		}
		if ($this->mbstring && @mb_convert_encoding($data,$to,$from)!="")
		{
			return @mb_convert_encoding($data,$to,$from);
		}
		if(function_exists('iconv'))
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
			if($from=='EUC-CN') 
			{
				$from='gb18030';
			}

			if (($convertedData = iconv($from,$to,$data))) 
			{
				return $convertedData;
			}
		}
		#die("<p>Can't convert from charset '$from' to '$to' without the <b>mbstring extension</b> !!!</p>");

		// this is not good, not convert did succed
		return $data;
	}

	/**
	 * installs translations for the selected langs into the database
	 *
	 * @param array $langs langs to install (as data NOT keys (!))
	 * @param string $upgrademethod='dumpold' 'dumpold' (recommended & fastest), 'addonlynew' languages, 'addmissing' phrases
	 * @param string/boolean $only_app=false app-name to install only one app or default false for all
	 */
	function install_langs($langs,$upgrademethod='dumpold',$only_app=False)
	{
		@set_time_limit(0);	// we might need some time
		//echo "<p>translation_sql::install_langs(".print_r($langs,true).",'$upgrademthod','$only_app')</p>\n";
		if (!isset($GLOBALS['egw_info']['server']) && $upgrademethod != 'dumpold')
		{
			if (($ctimes = $this->db->select($this->config_table,'config_value',array(
				'config_app'	=> 'phpgwapi',
				'config_name'	=> 'lang_ctimes',
			),__LINE__,__FILE__)->fetchSingle()))
			{
				$GLOBALS['egw_info']['server']['lang_ctimes'] = unserialize(stripslashes($ctimes));
			}
		}

		if (!is_array($langs) || !count($langs))
		{
			return;
		}
		$this->db->transaction_begin();

		if ($upgrademethod == 'dumpold')
		{
			// dont delete the custom main- & loginscreen messages every time
			$this->db->delete($this->lang_table,array("app_name!='mainscreen'","app_name!='loginscreen'"),__LINE__,__FILE__);
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
				if (!$this->db->select($this->lang_table,'COUNT(*)',array(
					'lang' => $lang,
				),__LINE__,__FILE__)->fetchSingle())
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
					$GLOBALS['egw_setup']->db = clone($this->db);
				}
				$setup_info = $GLOBALS['egw_setup']->detection->get_versions();
				$setup_info = $GLOBALS['egw_setup']->detection->get_db_versions($setup_info);
				$raw = array();
				$apps = $only_app ? array($only_app) : array_keys($setup_info);
				// Visit each app/setup dir, look for a egw_lang file
				foreach($apps as $app)
				{
					$appfile = EGW_SERVER_ROOT . SEP . $app . SEP . 'setup' . SEP . EGW_LANGFILE_PREFIX . strtolower($lang) . '.lang';
					$old_appfile = EGW_SERVER_ROOT . SEP . $app . SEP . 'setup' . SEP . PHPGW_LANGFILE_PREFIX . strtolower($lang) . '.lang';
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
								$this->line_rejected[] = array(
									'appfile' => $appfile,
									'line'    => $line_display,
								);
							}
							$message_id = substr(strtolower(chop($message_id)),0,MAX_MESSAGE_ID_LENGTH);
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
				$charset = strtolower(@$raw['common']['charset'] ? $raw['common']['charset'] : $this->charset($lang));
				//echo "<p>lang='$lang', charset='$charset', system_charset='$this->system_charset')</p>\n";
				//echo "<p>raw($lang)=<pre>".print_r($raw,True)."</pre>\n";
				foreach($raw as $app_name => $ids)
				{
					foreach($ids as $message_id => $content)
					{
						if ($this->system_charset)
						{
							$content = $this->convert($content,$charset,$this->system_charset);
						}
						$addit = False;
						//echo '<br>APPNAME:' . $app_name . ' PHRASE:' . $message_id;
						if ($upgrademethod == 'addmissing')
						{
							//echo '<br>Test: addmissing';
							$rs = $this->db->select($this->lang_table,"content,CASE WHEN app_name IN ('common') THEN 1 ELSE 0 END AS in_api",array(
								'message_id' 	=> $message_id,
								'lang'			=> $lang,
								$this->db->expression($this->lang_table,'(',array(
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
										$this->db->delete($this->lang_table,array(
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
								$result = $this->db->insert($this->lang_table,array(
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
		}
		$this->db->transaction_commit();

		// update the ctimes of the installed langsfiles for the autoloading of the lang-files
		//
		config::save_value('lang_ctimes',$GLOBALS['egw_info']['server']['lang_ctimes'],'phpgwapi');
	}

	/**
	 * re-loads all (!) langfiles if one langfile for the an app and the language of the user has changed
	 */
	function autoload_changed_langfiles()
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
			$fname = EGW_SERVER_ROOT . "/$app/setup/" . EGW_LANGFILE_PREFIX . "$lang.lang";
			$old_fname = EGW_SERVER_ROOT . "/$app/setup/" . PHPGW_LANGFILE_PREFIX . "$lang.lang";

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
					$installed = $this->get_installed_langs();
					//echo "<p>install_langs(".print_r($installed,True).")</p>\n";
					$this->install_langs($installed ? array_keys($installed) : array());
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
	function get_langs($DEBUG=False)
	{
		if($DEBUG)
		{
			echo '<br>get_langs(): checking db...' . "\n";
		}
		if (!$this->langs)
		{
			$this->get_installed_langs();
		}
		return $this->langs ? array_keys($this->langs) : array();
	}

	/**
	 * delete all lang entries for an application, return True if langs were found
	 *
	 * @param $appname app_name whose translations you want to delete
	 * @param boolean $DEBUG=false debug messages or not, default not
	 * @return boolean true if $appname had translations installed, false otherwise
	 */
	function drop_langs($appname,$DEBUG=False)
	{
		if($DEBUG)
		{
			echo '<br>drop_langs(): Working on: ' . $appname;
		}
		if ($this->db->select($this->lang_table,'COUNT(*)',array(
			'app_name' => $appname
		),__LINE__,__FILE__)->fetchSingle())
		{
			$this->db->delete($this->lang_table,array(
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
	function add_langs($appname,$DEBUG=False,$force_langs=False)
	{
		$langs = $this->get_langs($DEBUG);
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

		$this->install_langs($langs,'addmissing',$appname);
	}
	
	/**
	 * insert/update one phrase in the lang-table
	 *
	 * @param string $lang
	 * @param string $app_name
	 * @param string $message_id
	 * @param string $content
	 */
	function write($lang,$app_name,$message_id,$content)
	{
		$this->db->insert($this->lang_table,array(
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
	function read($lang,$app_name,$message_id)
	{
		return $this->db->select($this->lang_table,'content',array(
			'lang' => $lang,
			'app_name' => $app_name,
			'message_id' => $message_id,
		),__LINE__,__FILE__)->fetchSingle();
	}
	
	/**
	 * Return the message_id of a given translation
	 *
	 * @param string $translation
	 * @param string $app='' default check all apps
	 * @param string $lang='' default check all langs
	 * @return string
	 */
	function get_message_id($translation,$app=null,$lang=null)
	{
		$like = $this->db->Type == 'pgsql' ? 'ILIKE' : 'LIKE';
		$where = array('content '.$like.' '.$this->db->quote($translation));	// like to be case-insensitive
		if ($app) $where['app_name'] = $app;
		if ($lang) $where['lang'] = $lang;
		
		return $this->db->select($this->lang_table,'message_id',$where,__LINE__,__FILE__)->fetchSingle();
	}

	/**
	 * Return the decoded string meeting some additional requirements for mailheaders
	 *
	 * @param string $_string -> part of an mailheader
	 * @param string $displayCharset the charset parameter specifies the character set to represent the result by (if iconv_mime_decode is to be used)
	 * @return string
	 */
	function decodeMailHeader($_string, $displayCharset='utf-8')
	{
		//error_log(__FILE__.','.__METHOD__.':'."called with $_string and CHARSET $displayCharset");
		if(function_exists(imap_mime_header_decode)) {
			$newString = '';

			$string = preg_replace('/\?=\s+=\?/', '?= =?', $_string);

			$elements=imap_mime_header_decode($string);

			foreach((array)$elements as $element) {
				if ($element->charset == 'default')
					$element->charset = 'iso-8859-1';
				$newString .= self::convert($element->text,$element->charset);
			}
			return preg_replace('/([\000-\012\015\016\020-\037\075])/','',$newString);
		} elseif(function_exists(mb_decode_mimeheader)) {
			$string = $_string;
			if(preg_match_all('/=\?.*\?Q\?.*\?=/iU', $string, $matches)) {
				foreach($matches[0] as $match) {
					$fixedMatch = str_replace('_', ' ', $match);
					$string = str_replace($match, $fixedMatch, $string);
				}
				$string = str_replace('=?ISO8859-','=?ISO-8859-',$string);
				$string = str_replace('=?windows-1258','=?ISO-8859-1',$string);
			}
			$string = mb_decode_mimeheader($string);
			return preg_replace('/([\000-\012\015\016\020-\037\075])/','',$string);
		} elseif(function_exists(iconv_mime_decode)) {
			// continue decoding also if an error occurs
			$string = @iconv_mime_decode($_string, 2, $displayCharset);
			return preg_replace('/([\000-\012\015\016\020-\037\075])/','',$string);
		}

		// no decoding function available
		return preg_replace('/([\000-\012\015\016\020-\037\075])/','',$_string);
	}
}
