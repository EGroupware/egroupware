<?php
/**
 * EGroupware API: Commononly used (static) functions
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

use EGroupware\Api;

/**
 * Commononly used (static) functions
 *
 * @deprecated use Api\* alternatives mentioned in individual methods
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
	 * @param $category =LC_ALL category to set, see setlocal function
	 * @param $charset =null default system charset
	 * @return string the local (or best estimate) set
	 * @deprecated use Api\Preferences::setlocal($category,$charset)
	 */
	static function setlocale($category=LC_ALL, $charset=null)
	{
		return Api\Preferences::setlocale($category, $charset);
	}

	/**
	 * Compares two Version strings and return 1 if str2 is newest (bigger version number) than str1
	 *
	 * This function checks for major version only.
	 * @param $str1
	 * @param $str2
	 * @deprecated not used anymore
	 */
	static function cmp_version($str1,$str2,$debug=False)
	{
		$regs = $regs2 = null;
		preg_match("/([0-9]+)\.([0-9]+)\.([0-9]+)[a-zA-Z]*([0-9]*)/",$str1,$regs);
		preg_match("/([0-9]+)\.([0-9]+)\.([0-9]+)[a-zA-Z]*([0-9]*)/",$str2,$regs2);
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
	 * @deprecated not used anymore
	 */
	static function cmp_version_long($str1,$str2,$debug=False)
	{
		$regs = $regs2 = null;
		preg_match("/([0-9]+)\.([0-9]+)\.([0-9]+)[a-zA-Z]*([0-9]*)\.([0-9]*)/",$str1,$regs);
		preg_match("/([0-9]+)\.([0-9]+)\.([0-9]+)[a-zA-Z]*([0-9]*)\.([0-9]*)/",$str2,$regs2);
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
	 * @deprecated use Api\CalDAV::generate_uid($_appName, $_eventID)
	 * @return string the unique id
	 */
	static function generate_uid($_appName, $_eventID)
	{
		return Api\CalDAV::generate_uid($_appName, $_eventID);
	}

	/**
	 * get the local content id from a global UID
	 *
	 * @param sting $_globalUid the global UID
	 * @deprecated dont use, as only EGroupware interal uids are reversable
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
	 * @deprecated not used anymore
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
	 * @deprecated not used anymore
	 */
	static function getPreferredLanguage()
	{
		// create a array of languages the user is accepting
		$userLanguages = explode(',',$_SERVER['HTTP_ACCEPT_LANGUAGE']);
		$supportedLanguages = self::getInstalledLanguages();

		// find usersupported language
		foreach($userLanguages as $lang)
		{
			// remove everything behind '-' example: de-de
			$pieces = explode('-', trim($lang));
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
	 * @deprecated use Api\Ldap::quote()
	 * @param $string either a string to be escaped, or an array of values to be escaped
	 * @return string
	 */
	static function ldap_addslashes($string='')
	{
		return Api\Ldap::quote($string);
	}

	/**
	 * connect to the ldap server and return a handle
	 *
	 * @deprecated use Api\Ldap::factory(true, $host, $dn, $passwd)
	 * @param string $host ='' ldap host
	 * @param string $dn ='' ldap_root_dn
	 * @param string $passwd ='' ldap_root_pw
	 * @return resource
	 */
	static function ldapConnect($host='', $dn='', $passwd='')
	{
		// use Lars new ldap class
		return Api\Ldap::factory(true, $host, $dn, $passwd);
	}

	/**
	 * function to stop running an app
	 *
	 * @param $call_footer boolean value to if true then call footer else exit
	 * @deprecated use $GLOBALS['egw']->framework->footer(), if necessary, and exit
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
	 * @deprecated use Api\Auth::randomstring($size)
	 */
	static function randomstring($size)
	{
		return Api\Auth::randomstring($size);
	}

	/**
	 * @deprecated just use forward slashes supported by PHP on all OS
	 */
	static function filesystem_separator()
	{
		return filesystem_separator();
	}

	/**
	 * This is used for reporting errors in a nice format.
	 *
	 * @param $error - array of errors
	 * @deprecated not used anymore
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
	 * return the fullname of a user
	 *
	 * @param $lid ='' account loginid
	 * @param $firstname ='' firstname
	 * @param $lastname ='' lastname
	 * @param $accountid =0 id, to check if it's a user or group, otherwise the lid will be used
	 * @deprecated use Api\Accounts::format_username()
	 */
	static function display_fullname($lid = '', $firstname = '', $lastname = '',$accountid=0)
	{
		return Api\Accounts::format_username($lid, $firstname, $lastname, $accountid);
	}

	/**
	 * Return formatted username for a given account_id
	 *
	 * @param string $accountid =null account id
	 * @return string full name of user or "#$accountid" if user not found
	 * @deprecated use Api\Accounts::username($accountid)
	 */
	static function grab_owner_name($accountid=null)
	{
		return Api\Accounts::username($accountid);
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
	 * @deprecated not used anymore
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

				$output_text .= '<td align="left" style="background: url(' . $ir . '/tabs-bg1.gif) repeat-x;">&nbsp;<b><a href="'
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
				$output_text .= '<td align="left" style="background: url(' . $ir . '/tabs-bg0.gif) repeat-x;">&nbsp;<b><a href="'
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
	 * get template dir of an application
	 *
	 * @param $appname appication name optional can be derived from $GLOBALS['egw_info']['flags']['currentapp'];
	 * @return string|boolean dir or false if no dir is found
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
		if (!file_exists(EGW_SERVER_ROOT.'/phpgwapi/templates/'.basename($GLOBALS['egw_info']['server']['template_set']).'/class.'.
			$GLOBALS['egw_info']['server']['template_set'].'_framework.inc.php') &&
			!file_exists(EGW_SERVER_ROOT.'/'.basename($GLOBALS['egw_info']['server']['template_set']).'/inc/class.'.
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
		return False;
	}

	/**
	 * checks if image_dir exists and has more than just a navbar-icon
	 *
	 * this is just a workaround for idots, better to use find_image, which has a fallback \
	 * 	on a per image basis to the default dir
	 *
	 * @deprecated
	 */
	static function is_image_dir($dir)
	{
		if (!@is_dir($dir))
		{
			return False;
		}
		if (($d = opendir($dir)))
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
	 * @deprecated
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
	 * @deprecated
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
	 * @deprecated use Api\Image::find($app,$image) they are identical now
	 */
	static function find_image($app,$image)
	{
		return Api\Image::find($app,$image);
	}

	/**
	 * Searches an image of a given type, if not found also without extension
	 *
	 * @param string $app
	 * @param string|array $image one or more image-name in order of precedence
	 * @param string $extension ='' extension to $image, makes sense only with an array
	 * @param boolean $svg =false should svg images be returned or not:
	 *	true: always return svg, false: never return svg (current default), null: browser dependent, see svg_usable()
	 * @return string url of image or null if not found
	 * @deprecated not used in newer template
	 */
	static function image_on($app,$image,$extension='_on',$svg=false)
	{
		return ($img = Api\Image::find($app,$image,$extension,$svg)) ? $img : Api\Image::find($app,$image,'',$svg);
	}

	/**
	 * Searches a appname, template and maybe language and type-specific image
	 *
	 * @param string $app
	 * @param string|array $image one or more image-name in order of precedence
	 * @param string $extension ='' extension to $image, makes sense only with an array
	 * @param boolean $_svg =false should svg images be returned or not:
	 *	true: always return svg, false: never return svg (current default), null: browser dependent, see svg_usable()
	 * @return string url of image or null if not found
	 * @deprecated use Api\Image::find($app,$image,$extension='',$_svg=false)
	 */
	static function image($app,$image,$extension='',$_svg=false)
	{
		return Api\Image::find($app, $image, $extension, $_svg);
	}

	/**
	 * Does browser support svg
	 *
	 * All non IE and IE 9+
	 *
	 * @return boolean
	 * @deprecated use Api\Image::svg_usable()
	 */
	static function svg_usable()
	{
		return Api\Image::svg_usable();
	}

	/**
	 * Scan filesystem for images of all apps
	 *
	 * For each application and image-name (without extension) one full path is returned.
	 * The path takes template-set and image-type-priority (now fixed to: png, jpg, gif, ico) into account.
	 *
	 * VFS image directory is treated like an application named 'vfs'.
	 *
	 * @param string $template_set =null 'default', 'idots', 'jerryr', default is template-set from user prefs
	 * @param boolean $svg =null prefer svg images, default for all browsers but IE<9
	 * @return array of application => image-name => full path
	 * @deprecated use Api\Image::map($template_set=null, $svg=null)
	 */
	static function image_map($template_set=null, $svg=null)
	{
		return Api\Image::map($template_set, $svg);
	}

	/**
	 * Delete image map cache for ALL template sets
	 *
	 * @deprecated use Api\Image::invalidate()
	 */
	public static function delete_image_map()
	{
		return Api\Image::invalidate();
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
	 * @deprecated use echo $GLOBALS['egw']->framework->footer() or egw_framework::render($content)
	 */
	static function egw_footer()
	{
		if(is_object($GLOBALS['egw']->framework))
		{
			echo $GLOBALS['egw']->framework->footer();
		}
	}

	/**
	* Used by template headers for including CSS in the header
	*
	* @deprecated use $GLOBALS['egw']->framework->_get_css()
	* @return string
	*/
	static function get_css()
	{
		return $GLOBALS['egw']->framework->_get_css();
	}

	/**
	* Used by the template headers for including javascript in the header
	*
	* @deprecated use egw_framework::_get_js()
	* @return string the javascript to be included
	*/
	static function get_java_script()
	{
		return egw_framework::_get_js();
	}

	/**
	* Returns on(Un)Load attributes from js class
	*
	* @deprecated use egw_framework::_get_js()
	* @returns string body attributes
	*/
	static function get_body_attribs()
	{
		return egw_framework::_get_body_attribs();
	}

	/**
	 * @deprecated not used anymore
	 */
	static function hex2bin($data)
	{
		$len = strlen($data);
		return @pack('H' . $len, $data);
	}

	/**
	 * encrypt data passed to the function
	 *
	 * @deprecated not used anymore
	 * @param $data data (string?) to be encrypted
	 */
	static function encrypt($data)
	{
		return $GLOBALS['egw']->crypto->encrypt($data);
	}

	/**
	 * decrypt $data
	 *
	 * @deprecated not used anymore
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
	 * @param int $t =0 timestamp, default current time
	 * @param string $format ='' timeformat, default '' = read from the user prefernces
	 * @param boolean $adjust_to_usertime =true should datetime::tz_offset be added to $t or not, default true
	 * @deprecated use Api\DateTime::to($time, $format) Api\DateTime::server2user($time, $format)
	 * @return string the formated date/time
	 */
	static function show_date($t = 0, $format = '', $adjust_to_usertime=true)
	{
		if (!$t) $t = 'now';

		$ret = $adjust_to_usertime ? egw_time::server2user($t, $format) : egw_time::to($t, $format);
		//error_log(__METHOD__.'('.array2string($t).", '$format', ".array2string($adjust_to_usertime).') returning '.array2string($ret));
		return $ret;
	}

	/**
	 * Format a date according to the user preferences
	 *
	 * @param string $yearstr year
	 * @param string $monthstr month
	 * @param string $daystr day
	 * @param boolean $add_seperator =false add the separator specifed in the prefs or not, default no
	 * @deprecated use Api\DateTime->format()
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
	 * @param int|string $sec ='' defaults to ''
	 * @deprecated use Api\DateTime->format()
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
	 * convert all european special chars to ascii
	 *
	 * @param string $str
	 * @return string
	 * @deprecated use Api\Translation::to_ascii
	 */
	public static function transliterate($str)
	{
		return Api\Translation::to_ascii($str);
	}

	/**
	 * Format an email address according to the system standard
	 *
	 * Convert all european special chars to ascii and fallback to the accountname, if nothing left eg. chiniese
	 *
	 * @param string $first firstname
	 * @param string $last lastname
	 * @param string $account account-name (lid)
	 * @param string $domain =null domain-name or null to use eGW's default domain $GLOBALS['egw_info']['server']['mail_suffix]
	 * @return string with email address
	 * @deprecated use Api\Accounts::email($first, $last, $account, $domain)
	 */
	static function email_address($first,$last,$account,$domain=null)
	{
		return Api\Accounts::email($first, $last, $account, $domain);
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
	 * @deprecated not used anymore
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
	 * @deprecated not used anymore
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
	 * @deprecated not used anymore
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
	 * @deprecated not used anymore
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
	 * @param int $min =0 if != 0 minimum id
	 * @param int $max =0 if != 0 maximum id allowed, if it would be exceeded we return false
	 * @deprecated use Api\Accounts\Ldap::next_id($appname, $min, $max)
	 * @return int|boolean the next id or false if $max given and exceeded
	 */
	static function next_id($appname,$min=0,$max=0)
	{
		return Api\Accounts\Ldap::next_id($appname, $min, $max);
	}

	/**
	 * Return a value for the last id entered, which an app may need to check values for LDAP
	 *
	 * @param string $appname app-name
	 * @param int $min =0 if != 0 minimum id
	 * @param int $max =0 if != 0 maximum id allowed, if it would be exceeded we return false
	 * @deprecated use Api\Accounts\Ldap::last_id($appname, $min, $max)
	 * @return int|boolean current id in the next_id table for a particular app/class or -1 for no app and false if $max is exceeded.
	 */
	static function last_id($appname,$min=0,$max=0)
	{
		return Api\Accounts\Ldap::last_id($appname, $min, $max);
	}

	/**
	 * gets an eGW conformat referer from $_SERVER['HTTP_REFERER'], suitable for direct use in the link function
	 *
	 * @param string $default ='' default to use if referer is not set by webserver or not determinable
	 * @param string $referer ='' referer string to use, default ('') use $_SERVER['HTTP_REFERER']
	 * @return string
	 * @todo get "real" referer for jDots template
	 */
	static function get_referer($default='',$referer='')
	{
		// HTTP_REFERER seems NOT to get urldecoded
		if (!$referer) $referer = urldecode($_SERVER['HTTP_REFERER']);

		$webserver_url = $GLOBALS['egw_info']['server']['webserver_url'];
		if (empty($webserver_url) || $webserver_url{0} == '/')	// url is just a path
		{
			$referer = preg_replace('/^https?:\/\/[^\/]+/','',$referer);	// removing the domain part
		}
		if (strlen($webserver_url) > 1)
		{
			list(,$referer) = explode($webserver_url,$referer,2);
		}
		$ret = str_replace('/etemplate/process_exec.php', '/index.php', $referer);

		if (empty($ret) || strpos($ret, 'cd=yes') !== false) $ret = $default;

		return $ret;
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
