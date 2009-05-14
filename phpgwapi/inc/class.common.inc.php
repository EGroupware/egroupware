<?php
/**
 * eGroupWare API: Commononly used (static) functions
 *
 * This file written by Dan Kuykendall <seek3r@phpgroupware.org>
 * and Joseph Engo <jengo@phpgroupware.org>
 * and Mark Peters <skeeter@phpgroupware.org>
 * and Lars Kneschke <lkneschke@linux-at-work.de>
 * Functions commonly used by eGroupWare developers
 * Copyright (C) 2000, 2001 Dan Kuykendall
 * Copyright (C) 2003 Lars Kneschke
 *
 * @link http://www.egroupware.org
 * @license http://opensource.org/licenses/gpl-license.php GPL - GNU General Public License
 * @package api
 * @version $Id$
 */

/**
 * common class containing commonly used static functions
 */
class common
{
	static $debug_info; // An array with debugging info from the API
	static $found_files;

	/**
	 * Try to guess and set a locale supported by the server, with fallback to 'en_EN' and 'C'
	 *
	 * This method uses the language and nationalty set in the users common prefs.
	 *
	 * @param $category=LC_ALL category to set, see setlocal function
	 * @param $charset=null default system charset
	 * @return string the local (or best estimate) set
	 */
	static function setlocale($category=LC_ALL,$charset=null)
	{
		$lang = $GLOBALS['egw_info']['user']['preferences']['common']['lang'];
		$country = $GLOBALS['egw_info']['user']['preferences']['common']['country'];

		if (strlen($lang) == 2)
		{
			$country_from_lang = strtoupper($lang);
		}
		else
		{
			list($lang,$country_from_lang) = explode('-',$lang);
			$country_from_lang = strtoupper($country_from_lang);
		}
		if (is_null($charset)) $charset = $GLOBALS['egw']->translation->charset();

		foreach(array(
			$lang.'_'.$country,
			$lang.'_'.$country_from_lang,
			$lang,
			'en_EN',
			'de_DE',	// this works with utf-8, en_EN@utf-8 does NOT!
			'C',
		) as $local)
		{
			if (($ret = setlocale($category,$local.'@'.$charset))) return $ret;
			if (($ret = setlocale($category,$local))) return $ret;
		}
		error_log(__METHOD__."($category,$charset) lang=$lang, country=$country, country_from_lang=$country_from_lang: Could not set local!");
		return false;	// should not happen, as the 'C' local should at least be available everywhere
	}

	/**
	 * Compares two Version strings and return 1 if str2 is newest (bigger version number) than str1
	 *
	 * This function checks for major version only.
	 * @param $str1
	 * @param $str2
	 */
	static function cmp_version($str1,$str2,$debug=False)
	{
		ereg("([0-9]+)\.([0-9]+)\.([0-9]+)[a-zA-Z]*([0-9]*)",$str1,$regs);
		ereg("([0-9]+)\.([0-9]+)\.([0-9]+)[a-zA-Z]*([0-9]*)",$str2,$regs2);
		if($debug) { echo "<br>$regs[0] - $regs2[0]"; }

		for($i=1;$i<5;$i++)
		{
			if($debug) { echo "<br>$i: $regs[$i] - $regs2[$i]"; }
			if($regs2[$i] == $regs[$i])
			{
				continue;
			}
			if($regs2[$i] > $regs[$i])
			{
				return 1;
			}
			elseif($regs2[$i] < $regs[$i])
			{
				return 0;
			}
		}
	}

	/**
	 * Compares two Version strings and return 1 if str2 is newest (bigger version number) than str1
	 *
	 * This function checks all fields. cmp_version() checks release version only.
	 * @param $str1
	 * @param $str2
	 */
	static function cmp_version_long($str1,$str2,$debug=False)
	{
		ereg("([0-9]+)\.([0-9]+)\.([0-9]+)[a-zA-Z]*([0-9]*)\.([0-9]*)",$str1,$regs);
		ereg("([0-9]+)\.([0-9]+)\.([0-9]+)[a-zA-Z]*([0-9]*)\.([0-9]*)",$str2,$regs2);
		if($debug) { echo "<br>$regs[0] - $regs2[0]"; }

		for($i=1;$i<6;$i++)
		{
			if($debug) { echo "<br>$i: $regs[$i] - $regs2[$i]"; }

			if($regs2[$i] == $regs[$i])
			{
				if($debug) { echo ' are equal...'; }
				continue;
			}
			if($regs2[$i] > $regs[$i])
			{
				if($debug) { echo ', and a > b'; }
				return 1;
			}
			elseif($regs2[$i] < $regs[$i])
			{
				if($debug) { echo ', and a < b'; }
				return 0;
			}
		}
		if($debug) { echo ' - all equal.'; }
	}

	/**
	* generate a unique id, which can be used for syncronisation
	*
	* @param string $_appName the appname
	* @param string $_eventID the id of the content
	* @return string the unique id
	*/
	static function generate_uid($_appName, $_eventID)
	{
		if(empty($_appName) || empty($_eventID)) return false;

		$suffix = $GLOBALS['egw_info']['server']['hostname'] ? $GLOBALS['egw_info']['server']['hostname'] : 'local';
		$prefix = $_appName.'-'.$_eventID.'-'.$GLOBALS['egw_info']['server']['install_id'];

		return $prefix;
	}

	/**
	* get the local content id from a global UID
	*
	* @param sting $_globalUid the global UID
	* @return int local egw content id
	*/
	static function get_egwId($_globalUid)
	{
		if(empty($_globalUid)) return false;

		$globalUidParts = explode('-',$_globalUid);
		array_shift($globalUidParts);	// remove the app name
		array_pop($globalUidParts);		// remove the install_id

		return implode('-',$globalUidParts);	// return the rest, allowing to have dashs in the id, can happen with LDAP!
	}

	/**
	 * return an array of installed languages
	 *
	 * @return $installedLanguages; an array containing the installed languages
	 */
	static function getInstalledLanguages()
	{
		$GLOBALS['egw']->db->query('SELECT DISTINCT lang FROM egw_lang');
		while (@$GLOBALS['egw']->db->next_record())
		{
			$installedLanguages[$GLOBALS['egw']->db->f('lang')] = $GLOBALS['egw']->db->f('lang');
		}

		return $installedLanguages;
	}

	/**
	 * get preferred language of the users
	 *
	 * Uses HTTP_ACCEPT_LANGUAGE (from the browser) and getInstalledLanguages to find out which languages are installed
	 *
	 * @return string
	 */
	static function getPreferredLanguage()
	{
		// create a array of languages the user is accepting
		$userLanguages = explode(',',$_SERVER['HTTP_ACCEPT_LANGUAGE']);
		$supportedLanguages = self::getInstalledLanguages();

		// find usersupported language
		foreach($userLanguages as $key => $value)
		{
			// remove everything behind '-' example: de-de
			$value = trim($value);
			$pieces = explode('-', $value);
			$value = $pieces[0];
			# print 'current lang $value<br>';
			if ($supportedLanguages[$value])
			{
				$retValue=$value;
				break;
			}
		}

		// no usersupported language found -> return english
		if (empty($retValue))
		{
			$retValue='en';
		}

		return $retValue;
	}

	/**
	 * escapes a string for use in searchfilters meant for ldap_search.
	 *
	 * Escaped Characters are: '*', '(', ')', ' ', '\', NUL
	 * It's actually a PHP-Bug, that we have to escape space.
	 * For all other Characters, refer to RFC2254.
	 *
	 * @deprecated use ldap::quote()
	 * @param $string either a string to be escaped, or an array of values to be escaped
	 * @return string
	 */
	static function ldap_addslashes($string='')
	{
		return ldap::quote($string);
	}

	/**
	 * connect to the ldap server and return a handle
	 *
	 * @deprecated use ldap::ldapConnect()
	 * @param $host ldap host
	 * @param $dn ldap_root_dn
	 * @param $passwd ldap_root_pw
	 * @return resource
	 */
	static function ldapConnect($host='', $dn='', $passwd='')
	{
		// use Lars new ldap class
		return $GLOBALS['egw']->ldap->ldapConnect($host,$dn,$passwd);
	}

	/**
	 * function to stop running an app
	 *
	 * used to stop running an app in the middle of execution <br>
	 * There may need to be some cleanup before hand
	 * @param $call_footer boolean value to if true then call footer else exit
	 */
	static function egw_exit($call_footer = False)
	{
		if (!defined('EGW_EXIT'))
		{
			define('EGW_EXIT',True);

			if ($call_footer)
			{
				self::egw_footer();
			}
		}
		exit;
	}

	/**
	 * return a random string of size $size
	 *
	 * @param $size int-size of random string to return
	 */
	static function randomstring($size)
	{
		$s = '';
		srand((double)microtime()*1000000);
		$random_char = array(
			'0','1','2','3','4','5','6','7','8','9','a','b','c','d','e','f',
			'g','h','i','j','k','l','m','n','o','p','q','r','s','t','u','v',
			'w','x','y','z','A','B','C','D','E','F','G','H','I','J','K','L',
			'M','N','O','P','Q','R','S','T','U','V','W','X','Y','Z'
		);

		for ($i=0; $i<$size; $i++)
		{
			$s .= $random_char[rand(1,61)];
		}
		return $s;
	}

	// Look at the note towards the top of this file (jengo)
	static function filesystem_separator()
	{
		return filesystem_separator();
	}

	/**
	 * This is used for reporting errors in a nice format.
	 *
	 * @param $error - array of errors
	 */
	static function error_list($errors,$text='Error')
	{
		if (!is_array($errors))
		{
			return False;
		}

		$html_error = '<table border="0" width="100%"><tr><td align="right"><b>' . lang($text)
			. '</b>: </td><td align="left">' . $errors[0] . '</td></tr>';
		for ($i=1; $i<count($errors); $i++)
		{
			$html_error .= '<tr><td>&nbsp;</td><td align="left">' . $errors[$i] . '</td></tr>';
		}
		return $html_error . '</table>';
	}

	/**
	 * @deprecated use ACL instead
	 */
	function check_owner($record,$link,$label,$extravars = '')
	{
		self::$debug_info[] = 'check_owner() is a depreciated function - use ACL instead';
	}

	/**
	 * return the fullname of a user
	 *
	 * @param $lid='' account loginid
	 * @param $firstname='' firstname
	 * @param $lastname='' lastname
	 * @param $accountid=0 id, to check if it's a user or group, otherwise the lid will be used
	 */
	static function display_fullname($lid = '', $firstname = '', $lastname = '',$accountid=0)
	{
		if (! $lid && ! $firstname && ! $lastname)
		{
			$lid       = $GLOBALS['egw_info']['user']['account_lid'];
			$firstname = $GLOBALS['egw_info']['user']['firstname'];
			$lastname  = $GLOBALS['egw_info']['user']['lastname'];
		}
		$is_group = $GLOBALS['egw']->accounts->get_type($accountid ? $accountid : $lid) == 'g';

		if (empty($firstname)) $firstname = $lid;
		if (empty($lastname) || $is_group)
		{
			$lastname  = $is_group ? lang('Group') : lang('User');
		}
		$display = $GLOBALS['egw_info']['user']['preferences']['common']['account_display'];

		if ($firstname && $lastname)
		{
			$delimiter = ', ';
		}
		else
		{
			$delimiter = '';
		}

		$name = '';
		switch($display)
		{
			case 'firstname':
				$name = $firstname . ' ' . $lastname;
				break;
			case 'lastname':
				$name = $lastname . $delimiter . $firstname;
				break;
			case 'username':
				$name = $lid;
				break;
			case 'firstall':
				$name = $firstname . ' ' . $lastname . ' ['.$lid.']';
				break;
			case 'lastall':
				$name = $lastname . $delimiter . $firstname . ' ['.$lid.']';
				break;
			case 'all':
				/* fall through */
			default:
				$name = '['.$lid.'] ' . $firstname . ' ' . $lastname;
		}
		return $name;
	}

	/**
	 * grab the owner name
	 *
	 * @param $id account id
	 */
	static function grab_owner_name($accountid = '')
	{
		$GLOBALS['egw']->accounts->get_account_name($accountid,$lid,$fname,$lname);

		return self::display_fullname($lid,$fname,$lname,$accountid);
	}

	/**
	 * create tabs
	 *
	 * @param array $tabs an array repersenting the tabs you wish to display, each element
	 * 		 * 		 * 	 in the array is an array of 3 elements, 'label' which is the
	 * 		 * 		 * 	 text displaed on the tab (you should pass translated string,
	 * 		 * 		 * 	 create_tabs will not do <code>lang()</code> for you), 'link'
	 * 		 * 		 * 	 which is the uri, 'target', the frame name or '_blank' to show
	 * 		 * 		 * 	 page in a new browser window.
	 * @param mixed $selected the tab whos key is $selected will be displayed as current tab
	 * @param $fontsize optional
	 * @return string return html that displays the tabs
	 */
	static function create_tabs($tabs, $selected, $fontsize = '')
	{
		$output_text = '<table border="0" cellspacing="0" cellpadding="0"><tr>';

		/* This is a php3 workaround */
		if(EGW_IMAGES_DIR == 'EGW_IMAGES_DIR')
		{
			$ir = ExecMethod('phpgwapi.phpgw.common.get_image_path', 'phpgwapi');
		}
		else
		{
			$ir = EGW_IMAGES_DIR;
		}

		if ($fontsize)
		{
			$fs  = '<font size="' . $fontsize . '">';
			$fse = '</font>';
		}

		$i = 1;
		while ($tab = each($tabs))
		{
			if ($tab[0] == $selected)
			{
				if ($i == 1)
				{
					$output_text .= '<td align="right"><img src="' . $ir . '/tabs-start1.gif"></td>';
				}

				$output_text .= '<td align="left" background="' . $ir . '/tabs-bg1.gif">&nbsp;<b><a href="'
					. $tab[1]['link'] . '" class="tablink" '.$tab[1]['target'].'>' . $fs . $tab[1]['label']
					. $fse . '</a></b>&nbsp;</td>';
				if ($i == count($tabs))
				{
					$output_text .= '<td align="left"><img src="' . $ir . '/tabs-end1.gif"></td>';
				}
				else
				{
					$output_text .= '<td align="left"><img src="' . $ir . '/tabs-sepr.gif"></td>';
				}
			}
			else
			{
				if ($i == 1)
				{
					$output_text .= '<td align="right"><img src="' . $ir . '/tabs-start0.gif"></td>';
				}
				$output_text .= '<td align="left" background="' . $ir . '/tabs-bg0.gif">&nbsp;<b><a href="'
					. $tab[1]['link'] . '" class="tablink" '.$tab[1]['target'].'>' . $fs . $tab[1]['label'] . $fse
					. '</a></b>&nbsp;</td>';
				if (($i + 1) == $selected)
				{
					$output_text .= '<td align="left"><img src="' . $ir . '/tabs-sepl.gif"></td>';
				}
				elseif ($i == $selected || $i != count($tabs))
				{
					$output_text .= '<td align="left"><img src="' . $ir . '/tabs-sepm.gif"></td>';
				}
				elseif ($i == count($tabs))
				{
					if ($i == $selected)
					{
						$output_text .= '<td align="left"><img src="' . $ir . '/tabs-end1.gif"></td>';
					}
					else
					{
						$output_text .= '<td align="left"><img src="' . $ir . '/tabs-end0.gif"></td>';
					}
				}
				else
				{
					if ($i != count($tabs))
					{
						$output_text .= '<td align="left"><img src="' . $ir . '/tabs-sepr.gif"></td>';
					}
				}
			}
			$i++;
			$output_text .= "\n";
		}
		$output_text .= "</table>\n";
		return $output_text;
	}

	/**
	 * get directory of application
	 *
	 * $appname can either be passed or derived from $GLOBALS['egw_info']['flags']['currentapp'];
	 * @param $appname name of application
	 */
	static function get_app_dir($appname = '')
	{
		if ($appname == '')
		{
			$appname = $GLOBALS['egw_info']['flags']['currentapp'];
		}
		if ($appname == 'logout' || $appname == 'login')
		{
			$appname = 'phpgwapi';
		}

		$appdir         = EGW_INCLUDE_ROOT . '/'.$appname;
		$appdir_default = EGW_SERVER_ROOT . '/'.$appname;

		if (@is_dir($appdir))
		{
			return $appdir;
		}
		elseif (@is_dir($appdir_default))
		{
			return $appdir_default;
		}
		else
		{
			return False;
		}
	}

	/**
	 * get inc (include dir) of application
	 *
	 * $appname can either be passed or derived from $GLOBALS['egw_info']['flags']['currentapp'];
	 * @param $appname name of application
	 */
	static function get_inc_dir($appname = '')
	{
		if (!$appname)
		{
			$appname = $GLOBALS['egw_info']['flags']['currentapp'];
		}
		if ($appname == 'logout' || $appname == 'login' || $appname == 'about')
		{
			$appname = 'phpgwapi';
		}

		$incdir         = EGW_INCLUDE_ROOT . '/' . $appname . '/inc';
		$incdir_default = EGW_SERVER_ROOT . '/' . $appname . '/inc';

		if (@is_dir ($incdir))
		{
			return $incdir;
		}
		elseif (@is_dir ($incdir_default))
		{
			return $incdir_default;
		}
		else
		{
			return False;
		}
	}

	/**
	 * list themes available
	 *
	 * themes can either be css file like in HEAD (if the template has a css-dir and has css-files in is) \
	 * 	or ordinary .14 themes-files
	 */
	function list_themes()
	{
		$tpl_dir = self::get_tpl_dir('phpgwapi');

		if ($dh = @opendir($tpl_dir . SEP . 'css'))
		{
			while ($file = readdir($dh))
			{
				if (eregi("\.css$", $file) && $file != 'phpgw.css')
				{
					$list[] = substr($file,0,strpos($file,'.'));
				}
			}
                            closedir($dh);
		}
		if(!is_array($list))
		{
			$dh = opendir(EGW_SERVER_ROOT . '/phpgwapi/themes');
			while ($file = readdir($dh))
			{
				if (eregi("\.theme$", $file))
				{
					$list[] = substr($file,0,strpos($file,'.'));
				}
			}
			closedir($dh);
		}
		reset ($list);
		return $list;
	}

	/**
	* List available templates
	*
	* @returns array alphabetically sorted list of templates
	*/
	function list_templates()
	{
		$list = array();
		$d = dir(EGW_SERVER_ROOT . '/phpgwapi/templates');
		while (($entry=$d->read()))
		{
			if ($entry != '..' && file_exists(EGW_SERVER_ROOT . '/phpgwapi/templates/' . $entry .'/class.'.$entry.'_framework.inc.php'))
			{
				$list[$entry]['name'] = $entry;
				if (file_exists ($f = EGW_SERVER_ROOT . '/phpgwapi/templates/' . $entry . '/setup/setup.inc.php'))
				{
					include($f);
					$list[$entry]['title'] = $GLOBALS['egw_info']['template'][$entry]['title'];
				}
				else
				{
					$list[$entry]['title'] = $entry;
				}
			}
		}
		$d->close();
		ksort($list);

		return $list;
	}

	/**
	 * get template dir of an application
	 *
	 * @param $appname appication name optional can be derived from $GLOBALS['egw_info']['flags']['currentapp'];
	 * @static
	 * @return string/boolean dir or false if no dir is found
	 */
	static function get_tpl_dir($appname = '')
	{
		if (!$appname)
		{
			$appname = $GLOBALS['egw_info']['flags']['currentapp'];
		}
		if ($appname == 'logout' || $appname == 'login')
		{
			$appname = 'phpgwapi';
		}

		if (!isset($GLOBALS['egw_info']['server']['template_set']) && isset($GLOBALS['egw_info']['user']['preferences']['common']['template_set']))
		{
			$GLOBALS['egw_info']['server']['template_set'] = $GLOBALS['egw_info']['user']['preferences']['common']['template_set'];
		}

		// Setting this for display of template choices in user preferences
		if ($GLOBALS['egw_info']['server']['template_set'] == 'user_choice')
		{
			$GLOBALS['egw_info']['server']['usrtplchoice'] = 'user_choice';
		}

		if (($GLOBALS['egw_info']['server']['template_set'] == 'user_choice' ||
			!isset($GLOBALS['egw_info']['server']['template_set'])) &&
			isset($GLOBALS['egw_info']['user']['preferences']['common']['template_set']))
		{
			$GLOBALS['egw_info']['server']['template_set'] = $GLOBALS['egw_info']['user']['preferences']['common']['template_set'];
		}
		if (!file_exists(EGW_SERVER_ROOT.'/phpgwapi/templates/'.$GLOBALS['egw_info']['server']['template_set'].'/class.'.
			$GLOBALS['egw_info']['server']['template_set'].'_framework.inc.php'))
		{
			$GLOBALS['egw_info']['server']['template_set'] = 'idots';
		}
		$tpldir         = EGW_SERVER_ROOT . '/' . $appname . '/templates/' . $GLOBALS['egw_info']['server']['template_set'];
		$tpldir_default = EGW_SERVER_ROOT . '/' . $appname . '/templates/default';

		if (@is_dir($tpldir))
		{
			return $tpldir;
		}
		elseif (@is_dir($tpldir_default))
		{
			return $tpldir_default;
		}
		else
		{
			return False;
		}
	}

	/**
	 * checks if image_dir exists and has more than just a navbar-icon
	 *
	 * this is just a workaround for idots, better to use find_image, which has a fallback \
	 * 	on a per image basis to the default dir
	 */
	static function is_image_dir($dir)
	{
		if (!@is_dir($dir))
		{
			return False;
		}
		if ($d = opendir($dir))
		{
			while ($f = readdir($d))
			{
				$ext = strtolower(strrchr($f,'.'));
				if (($ext == '.gif' || $ext == '.png') && strpos($f,'navbar') === False)
				{
					closedir($d);
					return True;
				}
			}
			closedir($d);
		}
		return False;
	}

	/**
	 * get image dir of an application
	 *
	 * @param $appname application name optional can be derived from $GLOBALS['egw_info']['flags']['currentapp'];
	 */
	static function get_image_dir($appname = '')
	{
		if ($appname == '')
		{
			$appname = $GLOBALS['egw_info']['flags']['currentapp'];
		}
		if (empty($GLOBALS['egw_info']['server']['template_set']))
		{
			$GLOBALS['egw_info']['server']['template_set'] = 'idots';
		}

		$imagedir            = EGW_SERVER_ROOT . '/' . $appname . '/templates/'
			. $GLOBALS['egw_info']['server']['template_set'] . '/images';
		$imagedir_default    = EGW_SERVER_ROOT . '/' . $appname . '/templates/idots/images';
		$imagedir_olddefault = EGW_SERVER_ROOT . '/' . $appname . '/images';

		if (self::is_image_dir ($imagedir))
		{
			return $imagedir;
		}
		elseif (self::is_image_dir ($imagedir_default))
		{
			return $imagedir_default;
		}
		elseif (self::is_image_dir ($imagedir_olddefault))
		{
			return $imagedir_olddefault;
		}
		else
		{
			return False;
		}
	}

	/**
	 * get image path of an application
	 *
	 * @param $appname appication name optional can be derived from $GLOBALS['egw_info']['flags']['currentapp'];
	 */
	static function get_image_path($appname = '')
	{
		if ($appname == '')
		{
			$appname = $GLOBALS['egw_info']['flags']['currentapp'];
		}

		if (empty($GLOBALS['egw_info']['server']['template_set']))
		{
			$GLOBALS['egw_info']['server']['template_set'] = 'idots';
		}

		$imagedir            = EGW_SERVER_ROOT . '/'.$appname.'/templates/'.$GLOBALS['egw_info']['server']['template_set'].'/images';
		$imagedir_default    = EGW_SERVER_ROOT . '/'.$appname.'/templates/idots/images';
		$imagedir_olddefault = EGW_SERVER_ROOT . '/'.$appname.'/templates/default/images';

		if (self::is_image_dir($imagedir))
		{
			return $GLOBALS['egw_info']['server']['webserver_url'].'/'.$appname.'/templates/'.$GLOBALS['egw_info']['server']['template_set'].'/images';
		}
		elseif (self::is_image_dir($imagedir_default))
		{
			return $GLOBALS['egw_info']['server']['webserver_url'].'/'.$appname.'/templates/idots/images';
		}
		elseif (self::is_image_dir($imagedir_olddefault))
		{
			return $GLOBALS['egw_info']['server']['webserver_url'].'/'.$appname.'/templates/default/images';
		}
		return False;
	}

	/**
	 * Searches and image by a given search order (it maintains a cache of the existing images):
	 * - image dir of the application for the given template
	 * - image dir of the application for the default template
	 * - image dir of the API for the given template
	 * - image dir of the API for the default template
	 *
	 * @param string $appname
	 * @param string $image
	 * @return string url of the image
	 */
	static function find_image($appname,$image)
	{
		$imagedir = '/'.$appname.'/templates/'.$GLOBALS['egw_info']['user']['preferences']['common']['template_set'].'/images';
		$vfs_imagedir = $GLOBALS['egw_info']['server']['vfs_image_dir'];

		if (!@is_array(self::$found_files[$appname]))
		{
			$imagedir_olddefault = '/'.$appname.'/templates/default/images';
			$imagedir_default    = '/'.$appname.'/templates/idots/images';

			if (@is_dir(EGW_INCLUDE_ROOT.$imagedir_olddefault))
			{
				$d = dir(EGW_INCLUDE_ROOT.$imagedir_olddefault);
				while (false != ($entry = $d->read()))
				{
					if ($entry != '.' && $entry != '..')
					{
						self::$found_files[$appname][$entry] = $imagedir_olddefault;
					}
				}
				$d->close();
			}

			if (@is_dir(EGW_INCLUDE_ROOT.$imagedir_default))
			{
				$d = dir(EGW_INCLUDE_ROOT.$imagedir_default);
				while (false != ($entry = $d->read()))
				{
					if ($entry != '.' && $entry != '..')
					{
						self::$found_files[$appname][$entry] = $imagedir_default;
					}
				}
				$d->close();
			}

			if (@is_dir(EGW_INCLUDE_ROOT.$imagedir))
			{
				$d = dir(EGW_INCLUDE_ROOT.$imagedir);
				while (false != ($entry = $d->read()))
				{
					if ($entry != '.' && $entry != '..')
					{
						self::$found_files[$appname][$entry] = $imagedir;
					}
				}
				$d->close();
			}

			if (!isset(self::$found_files['vfs']) && egw_vfs::file_exists($vfs_imagedir) &&
				egw_vfs::is_dir($vfs_imagedir) && ($d = egw_vfs::opendir($vfs_imagedir)))
			{
				while (($entry = readdir($d)) !== false)
				{
					if (!egw_vfs::is_dir($vfs_imagedir.'/'.$entry))
					{
						if (list($type,$subtype) = explode('/',egw_vfs::mime_content_type($vfs_imagedir.'/'.$entry)))// && $type == 'image')
						{
							if ($type == 'image' || $type == 'application') self::$found_files['vfs'][$entry] = $vfs_imagedir;
						}
					}
				}
				closedir($d);
			}
		}
		if (!$GLOBALS['egw_info']['server']['image_type'])
		{
			// priority: GIF->JPG->PNG
			$img_type = array('.gif','.jpg','.png','');
		}
		else
		{
			// priority: : PNG->JPG->GIF
			$img_type = array('.png','.jpg','.gif','');
		}
		$imgfile = '';
		// first look in the instance specific image dir in vfs
		foreach($img_type as $type)
		{
			// first look in the instance specific image dir in vfs
			if(isset(self::$found_files['vfs'][$image.$type]))
			{
				$imgfile = egw::link(egw_vfs::download_url(self::$found_files['vfs'][$image.$type].'/'.$image.$type));
				break;
			}
			// then look in the selected template dir
			if(self::$found_files[$appname][$image.$type]==$imagedir)
			{
				$imgfile = $GLOBALS['egw_info']['server']['webserver_url'].self::$found_files[$appname][$image.$type].'/'.$image.$type;
				break;
			}
			//then look everywhere else
			if (isset(self::$found_files[$appname][$image.$type]))
			{
				$imgfile = $GLOBALS['egw_info']['server']['webserver_url'].self::$found_files[$appname][$image.$type].'/'.$image.$type;
				break;
			}
		}

		if (empty($imgfile))
		{
			// searching the image in the api-dirs
			if (!isset(self::$found_files['phpgwapi']))
			{
				self::find_image('phpgwapi','');
			}
			foreach(array_merge($img_type,array('')) as $type)
			{
				if(isset(self::$found_files['phpgwapi'][$image.$type]))
				{
					$imgfile = $GLOBALS['egw_info']['server']['webserver_url'].self::$found_files['phpgwapi'][$image.$type].'/'.$image.$type;
					break;
				}
			}
		}
		//echo "<p>".__METHOD__."($appname,$image) = $imgfile</p>\n";
		return $imgfile;
	}

	/**
	 * Searches a appname, template and maybe language and type-specific image
	 *
	 * @param string $appname
	 * @param string $image
	 * @param string $ext
	 * @param boolean $use_lang
	 * @return string url of the image
	 */
	static function image($appname,$image='',$ext='',$use_lang=True)
	{
		if (!is_array($image))
		{
			if (empty($image))
			{
				return '';
			}
		}
		if ($use_lang)
		{
			foreach((array)$image as $img)
			{
				$lang_images[] = $img . '_' . $GLOBALS['egw_info']['user']['preferences']['common']['lang'];
				$lang_images[] = $img;
			}
			$image = $lang_images;
		}
		foreach((array)$image as $img)
		{
			if(isset(self::$found_files[$appname][$img.$ext]))
			{
				$image_found = self::$found_files[$appname][$img.$ext].'/'.$img.$ext;
			}
			else
			{
				$image_found = self::find_image($appname,$img.$ext);
			}
			if ($image_found) break;
		}
		//echo "<p>".__METHOD__."($appname,".array2string($image).",$ext,$use_lang) = $image_found</p>\n";
		return $image_found;
	}

	/**
	 * Searches an image of a given type, if not found also without type/extension
	 *
	 * @param string $appname
	 * @param string $image
	 * @param string $extension
	 * @return string url of the image
	 */
	static function image_on($appname,$image,$extension='_on')
	{
		if (($with_extension = self::image($appname,$image,$extension)))
		{
			return $with_extension;
		}
		if(($without_extension = self::image($appname,$image)))
		{
			return $without_extension;
		}
		return '';
	}

	/**
 	 * prepare an array with variables used to render the navbar
	 *
	 * @deprecated inherit from egw_framework class in your template and use egw_framework::_navbar_vars()
	 */
	static function navbar()
	{
		$GLOBALS['egw_info']['navbar'] = $GLOBALS['egw']->framework->_get_navbar_vars();
	}

	/**
	 * load header.inc.php for an application
	 *
	 * @deprecated
	 */
	static function app_header()
	{
		if (file_exists(EGW_APP_INC . '/header.inc.php'))
		{
			include(EGW_APP_INC . '/header.inc.php');
		}
	}

	/**
	 * load the eGW header
	 *
	 * @deprecated use egw_framework::header(), $GLOBALS['egw']->framework->navbar() or better egw_framework::render($content)
	 */
	static function egw_header()
	{
		echo $GLOBALS['egw']->framework->header();

		if (!$GLOBALS['egw_info']['flags']['nonavbar'])
		{
		   echo $GLOBALS['egw']->framework->navbar();
		}
	 }

	/**
	 * load the eGW footer
	 *
	 * @deprecated use egw_framework::footer() or egw_framework::render($content)
	 */
	static function egw_footer()
	{
		if(is_object($GLOBALS['egw']->framework)) {
			echo $GLOBALS['egw']->framework->footer();
		}
	}

	/**
	* Used by template headers for including CSS in the header
	*
	* @deprecated use framework::_get_css()
	* @return string
	*/
	static function get_css()
	{
		return $GLOBALS['egw']->framework->_get_css();
	}

	/**
	* Used by the template headers for including javascript in the header
	*
	* @deprecated use framework::_get_js()
	* @return string the javascript to be included
	*/
	static function get_java_script()
	{
		return $GLOBALS['egw']->framework->_get_js();
	}

	/**
	* Returns on(Un)Load attributes from js class
	*
	* @deprecated use framework::_get_js()
	* @returns string body attributes
	*/
	static function get_body_attribs()
	{
		return $GLOBALS['egw']->framework->_get_body_attribs();
	}

	static function hex2bin($data)
	{
		$len = strlen($data);
		return @pack('H' . $len, $data);
	}

	/**
	 * encrypt data passed to the function
	 *
	 * @param $data data (string?) to be encrypted
	 */
	static function encrypt($data)
	{
		return $GLOBALS['egw']->crypto->encrypt($data);
	}

	/**
	 * decrypt $data
	 *
	 * @param $data data to be decrypted
	 */
	static function decrypt($data)
	{
		return $GLOBALS['egw']->crypto->decrypt($data);
	}

	/**
	 * legacy wrapper for newer auth class function, encrypt_password
	 *
	 * uses the encryption type set in setup and calls the appropriate encryption functions
	 *
	 * @deprecated use auth::encrypt_password()
	 * @param $password password to encrypt
	 */
	static function encrypt_password($password,$sql=False)
	{
		return auth::encrypt_password($password,$sql);
	}

	/**
	 * find the current position of the app is the users portal_order preference
	 *
	 * @param $app application id to find current position - required
	 * No discussion
	 */
	function find_portal_order($app)
	{
		if(!is_array($GLOBALS['egw_info']['user']['preferences']['portal_order']))
		{
			return -1;
		}
		@reset($GLOBALS['egw_info']['user']['preferences']['portal_order']);
		while(list($seq,$appid) = each($GLOBALS['egw_info']['user']['preferences']['portal_order']))
		{
			if($appid == $app)
			{
				@reset($GLOBALS['egw_info']['user']['preferences']['portal_order']);
				return $seq;
			}
		}
		@reset($GLOBALS['egw_info']['user']['preferences']['portal_order']);
		return -1;
	}

	/**
	 * return a formatted timestamp or current time
	 *
	 * @param int $t=0 timestamp, default current time
	 * @param string $format='' timeformat, default '' = read from the user prefernces
	 * @param boolean $adjust_to_usertime=true should datetime::tz_offset be added to $t or not, default true
	 * @return string the formated date/time
	 */
	static function show_date($t = 0, $format = '', $adjust_to_usertime=true)
	{
		if (!$t)
		{
			$t = $GLOBALS['egw']->datetime->gmtnow;
		}

		if ($adjust_to_usertime)
		{
			$t += $GLOBALS['egw']->datetime->tz_offset;
		}

		if (!$format)
		{
			$format = $GLOBALS['egw_info']['user']['preferences']['common']['dateformat'] . ' - ';
			if ($GLOBALS['egw_info']['user']['preferences']['common']['timeformat'] == '12')
			{
				$format .= 'h:i a';
			}
			else
			{
				$format .= 'H:i';
			}
		}
		return adodb_date($format,$t);
	}

	/**
	 * Format a date according to the user preferences
	 *
	 * @param string $yearstr year
	 * @param string $monthstr month
	 * @param string $day day
	 * @param boolean $add_seperator=false add the separator specifed in the prefs or not, default no
	 * @return string
	 */
	static function dateformatorder($yearstr,$monthstr,$daystr,$add_seperator = False)
	{
		$dateformat = strtolower($GLOBALS['egw_info']['user']['preferences']['common']['dateformat']);
		$sep = substr($GLOBALS['egw_info']['user']['preferences']['common']['dateformat'],1,1);

		$dlarr[strpos($dateformat,'y')] = $yearstr;
		$dlarr[strpos($dateformat,'m')] = $monthstr;
		$dlarr[strpos($dateformat,'d')] = $daystr;
		ksort($dlarr);

		if ($add_seperator)
		{
			return implode($sep,$dlarr);
		}
		return implode(' ',$dlarr);
	}

	/**
	 * format the time takes settings from user preferences
	 *
	 * @param int $hour hour
	 * @param int $min minutes
	 * @param int/string $sec='' defaults to ''
	 * @return string formated time
	 */
	static function formattime($hour,$min,$sec='')
	{
		$h12 = $hour;
		if ($GLOBALS['egw_info']['user']['preferences']['common']['timeformat'] == '12')
		{
			if ($hour >= 12)
			{
				$ampm = ' pm';
			}
			else
			{
				$ampm = ' am';
			}

			$h12 %= 12;

			if ($h12 == 0 && $hour)
			{
				$h12 = 12;
			}
			if ($h12 == 0 && !$hour)
			{
				$h12 = 0;
			}
		}
		else
		{
			$h12 = $hour;
		}

		if ($sec !== '')
		{
			$sec = ':'.$sec;
		}

		return $h12.':'.$min.$sec.$ampm;
	}

	/**
	 * Format an email address according to the system standard
	 *
	 * Convert all european special chars to ascii and fallback to the accountname, if nothing left eg. chiniese
	 *
	 * @param string $first firstname
	 * @param string $last lastname
	 * @param string $account account-name (lid)
	 * @param string $domain=null domain-name or null to use eGW's default domain $GLOBALS['egw_info']['server']['mail_suffix]
	 * @return string with email address
	 */
	static function email_address($first,$last,$account,$domain=null)
	{
		//echo "<p align=right>common::email_address('$first','$last','$account')";
		// convert all european special chars to ascii, (c) RalfBecker-AT-egroupware.org ;-)
		static $extra = array(
			'&szlig;' => 'ss',
			' ' => '',
		);
		foreach (array('first','last','account') as $name)
		{
			$$name = htmlentities($$name,ENT_QUOTES,$GLOBALS['egw']->translation->charset());
			$$name = str_replace(array_keys($extra),array_values($extra),$$name);
			$$name = preg_replace('/&([aAuUoO])uml;/','\\1e',$$name);	// replace german umlauts with the letter plus one 'e'
			$$name = preg_replace('/&([a-zA-Z])(grave|acute|circ|ring|cedil|tilde|slash|uml);/','\\1',$$name);	// remove all types of acents
			$$name = preg_replace('/&([a-zA-Z]+|#[0-9]+|);/','',$$name);	// remove all other entities
		}
		//echo " --> ('$first', '$last', '$account')";
		if (!$first && !$last)	// fallback to the account-name, if real names contain only special chars
		{
			$first = '';
			$last = $account;
		}
		if (!$first || !$last)
		{
			$dot = $underscore = '';
		}
		else
		{
			$dot = '.';
			$underscore = '_';
		}
		if (!$domain) $domain = $GLOBALS['egw_info']['server']['mail_suffix'];

		$email = str_replace(array('first','last','initial','account','dot','underscore','-'),
			array($first,$last,substr($first,0,1),$account,$dot,$underscore,''),
			$GLOBALS['egw_info']['server']['email_address_format'] ? $GLOBALS['egw_info']['server']['email_address_format'] : 'first-dot-last').
			($domain ? '@'.$domain : '');
		//echo " = '$email'</p>\n";
		return $email;
	}

	/**
	 * create email preferences
	 *
	 * This is not the best place for it, but it needs to be shared between Aeromail and SM
	 * @param $prefs
	 * @param $account_id -optional defaults to : phpgw_info['user']['account_id']
	 */
	function create_emailpreferences($prefs='',$accountid='')
	{
		return $GLOBALS['egw']->preferences->create_email_preferences($accountid);
		// ----  Create the email Message Class  if needed  -----
		if (is_object($GLOBALS['egw']->msg))
		{
			$do_free_me = False;
		}
		else
		{
			$GLOBALS['egw']->msg =& CreateObject('email.mail_msg');
			$do_free_me = True;
		}

		// this sets the preferences into the phpgw_info structure
		$GLOBALS['egw']->msg->create_email_preferences();

		// cleanup and return
		if ($do_free_me)
		{
			unset ($GLOBALS['egw']->msg);
		}
	}

	/**
	 * ?
	 *
	 * This will be moved into the applications area
	 */
	static function check_code($code)
	{
		$s = '<br>';
		switch ($code)
		{
			case 13:	$s .= lang('Your message has been sent');break;
			case 14:	$s .= lang('New entry added sucessfully');break;
			case 15:	$s .= lang('Entry updated sucessfully');	break;
			case 16:	$s .= lang('Entry has been deleted sucessfully'); break;
			case 18:	$s .= lang('Password has been updated');	break;
			case 38:	$s .= lang('Password could not be changed');	break;
			case 19:	$s .= lang('Session has been killed');	break;
			case 27:	$s .= lang('Account has been updated');	break;
			case 28:	$s .= lang('Account has been created');	break;
			case 29:	$s .= lang('Account has been deleted');	break;
			case 30:	$s .= lang('Your settings have been updated'); break;
			case 31:	$s .= lang('Group has been added');	break;
			case 32:	$s .= lang('Group has been deleted');	break;
			case 33:	$s .= lang('Group has been updated');	break;
			case 34:	$s .= lang('Account has been deleted') . '<p>'
					. lang('Error deleting %1 %2 directory',lang('users'),' '.lang('private').' ')
					. ',<br>' . lang('Please %1 by hand',lang('delete')) . '<br><br>'
					. lang('To correct this error for the future you will need to properly set the')
					. '<br>' . lang('permissions to the files/users directory')
					. '<br>' . lang('On *nix systems please type: %1','chmod 770 '
					. $GLOBALS['egw_info']['server']['files_dir'] . '/users/');
				break;
			case 35:	$s .= lang('Account has been updated') . '<p>'
					. lang('Error renaming %1 %2 directory',lang('users'),
					' '.lang('private').' ')
					. ',<br>' . lang('Please %1 by hand',
					lang('rename')) . '<br><br>'
					. lang('To correct this error for the future you will need to properly set the')
					. '<br>' . lang('permissions to the files/users directory')
					. '<br>' . lang('On *nix systems please type: %1','chmod 770 '
					. $GLOBALS['egw_info']['server']['files_dir'] . '/users/');
				break;
			case 36:	$s .= lang('Account has been created') . '<p>'
					. lang('Error creating %1 %2 directory',lang('users'),
					' '.lang('private').' ')
					. ',<br>' . lang('Please %1 by hand',
					lang('create')) . '<br><br>'
					. lang('To correct this error for the future you will need to properly set the')
					. '<br>' . lang('permissions to the files/users directory')
					. '<br>' . lang('On *nix systems please type: %1','chmod 770 '
					. $GLOBALS['egw_info']['server']['files_dir'] . '/users/');
				break;
			case 37:	$s .= lang('Group has been added') . '<p>'
					. lang('Error creating %1 %2 directory',lang('groups'),' ')
					. ',<br>' . lang('Please %1 by hand',
					lang('create')) . '<br><br>'
					. lang('To correct this error for the future you will need to properly set the')
					. '<br>' . lang('permissions to the files/users directory')
					. '<br>' . lang('On *nix systems please type: %1','chmod 770 '
					. $GLOBALS['egw_info']['server']['files_dir'] . '/groups/');
				break;
			case 38:	$s .= lang('Group has been deleted') . '<p>'
					. lang('Error deleting %1 %2 directory',lang('groups'),' ')
					. ',<br>' . lang('Please %1 by hand',
					lang('delete')) . '<br><br>'
					. lang('To correct this error for the future you will need to properly set the')
					. '<br>' . lang('permissions to the files/users directory')
					. '<br>' . lang('On *nix systems please type: %1','chmod 770 '
					. $GLOBALS['egw_info']['server']['files_dir'] . '/groups/');
				break;
			case 39:	$s .= lang('Group has been updated') . '<p>'
					. lang('Error renaming %1 %2 directory',lang('groups'),' ')
					. ',<br>' . lang('Please %1 by hand',
					lang('rename')) . '<br><br>'
					. lang('To correct this error for the future you will need to properly set the')
					. '<br>' . lang('permissions to the files/users directory')
					. '<br>' . lang('On *nix systems please type: %1','chmod 770 '
					. $GLOBALS['egw_info']['server']['files_dir'] . '/groups/');
				break;
			case 40: $s .= lang('You have not entered a title').'.';
				break;
			case 41: $s .= lang('You have not entered a valid time of day').'.';
				break;
			case 42: $s .= lang('You have not entered a valid date').'.';
				break;
			case 43: $s .= lang('You have not entered participants').'.';
				break;
			default:	return '';
		}
		return $s;
	}

	/**
	 * process error message
	 *
	 * @param $error error
	 * @param $line line
	 * @param $file file
	 */
	static function phpgw_error($error,$line = '', $file = '')
	{
		echo '<p><b>eGroupWare internal error:</b><p>'.$error;
		if ($line)
		{
			echo 'Line: '.$line;
		}
		if ($file)
		{
			echo 'File: '.$file;
		}
		echo '<p>Your session has been halted.';
		exit;
	}

	/**
	 * create phpcode from array
	 *
	 * @param $array - array
	 */
	static function create_phpcode_from_array($array)
	{
		while (list($key, $val) = each($array))
		{
			if (is_array($val))
			{
				while (list($key2, $val2) = each($val))
				{
					if (is_array($val2))
					{
						while (list($key3, $val3) = each ($val2))
						{
							if (is_array($val3))
							{
								while (list($key4, $val4) = each ($val3))
								{
									$s .= "\$GLOBALS['egw_info']['" . $key . "']['" . $key2 . "']['" . $key3 . "']['" .$key4 . "']='" . $val4 . "';";
									$s .= "\n";
								}
							}
							else
							{
								$s .= "\$GLOBALS['egw_info']['" . $key . "']['" . $key2 . "']['" . $key3 . "']='" . $val3 . "';";
								$s .= "\n";
							}
						}
					}
					else
					{
						$s .= "\$GLOBALS['egw_info']['" . $key ."']['" . $key2 . "']='" . $val2 . "';";
						$s .= "\n";
					}
				}
			}
			else
			{
				$s .= "\$GLOBALS['egw_info']['" . $key . "']='" . $val . "';";
				$s .= "\n";
			}
		}
		return $s;
	}

	// This will return the full phpgw_info array, used for debugging
	/**
	 * return the full phpgw_info array for debugging
	 *
	 * @param array - array
	 */
	static function debug_list_array_contents($array)
	{
		while (list($key, $val) = each($array))
		{
			if (is_array($val))
			{
				while (list($key2, $val2) = each($val))
				{
					if (is_array($val2))
					{
						while (list($key3, $val3) = each ($val2))
						{
							if (is_array($val3))
							{
								while (list($key4, $val4) = each ($val3))
								{
									echo $$array . "[$key][$key2][$key3][$key4]=$val4<br>";
								}
							}
							else
							{
								echo $$array . "[$key][$key2][$key3]=$val3<br>";
							}
						}
					}
					else
					{
						echo $$array . "[$key][$key2]=$val2<br>";
					}
				}
			}
			else
			{
				echo $$array . "[$key]=$val<br>";
			}
		}
	}

	// This will return a list of functions in the API
	/**
	 * return a list of functionsin the API
	 *
	 */
	static function debug_list_core_functions()
	{
		echo '<br><b>core functions</b><br>';
		echo '<pre>';
		chdir(EGW_INCLUDE_ROOT . '/phpgwapi');
		system("grep -r '^[ \t]*function' *");
		echo '</pre>';
	}

	const NEXTID_TABLE = 'egw_nextid';

	/**
	 * Return a value for the next id an app/class may need to insert values into LDAP
	 *
	 * @param string $appname app-name
	 * @param int $min=0 if != 0 minimum id
	 * @param int $max=0 if != 0 maximum id allowed, if it would be exceeded we return false
	 * @return int/boolean the next id or false if $max given and exceeded
	 */
	static function next_id($appname,$min=0,$max=0)
	{
		if (!$appname)
		{
			return -1;
		}

		$id = (int) $GLOBALS['egw']->db->select(self::NEXTID_TABLE,'id',array('appname' => $appname),__LINE__,__FILE__)->fetchSingle();

		if ($max && $id >= $max)
		{
			return False;
		}
		++$id;

		if($id < $min) $id = $min;

		$GLOBALS['egw']->db->insert(self::NEXTID_TABLE,array('id' => $id),array('appname' => $appname),__LINE__,__FILE__);

		return (int)$id;
	}

	/**
	 * Return a value for the last id entered, which an app may need to check values for LDAP
	 *
	 * @param string $appname app-name
	 * @param int $min=0 if != 0 minimum id
	 * @param int $max=0 if != 0 maximum id allowed, if it would be exceeded we return false
	 * @return int current id in the next_id table for a particular app/class or -1 for no app and false if $max is exceeded.
	 */
	static function last_id($appname,$min=0,$max=0)
	{
		if (!$appname)
		{
			return -1;
		}

		$id = (int)$GLOBALS['egw']->db->select(self::NEXTID_TABLE,'id',array('appname' => $appname),__LINE__,__FILE__)->fetchSingle();

		if (!$id || $id < $min)
		{
			return self::next_id($appname,$min,$max);
		}
		if ($max && $id > $max)
		{
			return False;
		}
		return $id;
	}

	/**
	 * gets an eGW conformat referer from $_SERVER['HTTP_REFERER'], suitable for direct use in the link function
	 *
	 * @param string $default='' default to use if referer is not set by webserver or not determinable
	 * @param string $referer='' referer string to use, default ('') use $_SERVER['HTTP_REFERER']
	 * @return string
	 */
	static function get_referer($default='',$referer='')
	{
		if (!$referer) $referer = $_SERVER['HTTP_REFERER'];

		$webserver_url = $GLOBALS['egw_info']['server']['webserver_url'];
		if (empty($webserver_url) || $webserver_url{0} == '/')	// url is just a path
		{
			$referer = preg_replace('/^https?:\/\/[^\/]+/','',$referer);	// removing the domain part
		}
		if (strlen($webserver_url) > 1)
		{
			list(,$referer) = explode($webserver_url,$referer,2);
		}
		$referer = str_replace('/etemplate/process_exec.php','/index.php',$referer);

		if (empty($referer)) $referer = $default;

		return $referer;
	}

	// some depricated functions for the migration
	static function phpgw_exit($call_footer = False)
	{
		self::egw_exit($call_footer);
	}

	static function phpgw_final()
	{
		self::egw_final();
	}

	static function phpgw_header()
	{
		self::egw_header();
	}

	static function phpgw_footer()
	{
		self::egw_footer();
	}
}
