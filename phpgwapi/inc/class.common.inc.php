<?php
	/**************************************************************************\
	* phpGroupWare API - Commononly used functions                             *
	* Written by Dan Kuykendall <seek3r@phpgroupware.org>                      *
	* and Joseph Engo <jengo@phpgroupware.org>                                 *
	* and Mark Peters <skeeter@phpgroupware.org>                               *
	* and Bettina Gille [ceb@phpgroupware.org]                                 * 
	* Commononly used functions by phpGroupWare developers                     *
	* Copyright (C) 2000 - 2002 Dan Kuykendall                                 *
	* ------------------------------------------------------------------------ *
	* This library is part of the phpGroupWare API                             *
	* http://www.phpgroupware.org/api                                          * 
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

	$d1 = strtolower(@substr(PHPGW_API_INC,0,3));
	$d2 = strtolower(@substr(PHPGW_SERVER_ROOT,0,3));
	$d3 = strtolower(@substr(PHPGW_APP_INC,0,3));
	if($d1 == 'htt' || $d1 == 'ftp' || $d2 == 'htt' || $d2 == 'ftp' || $d3 == 'htt' || $d3 == 'ftp')
	{
		echo 'Failed attempt to break in via an old Security Hole!<br>'."\n";
		exit;
	}
	unset($d1);unset($d2);unset($d3);

	/*!
	@class common
	@abstract common class that contains commonly used functions
	*/
	class common
	{
		var $debug_info; // An array with debugging info from the API
		var $found_files;

		/*!
		@function cmp_version
		@abstract Compares two Version strings and return 1 if str2 is newest (bigger version number) than str1
		@discussion This function checks for major version only.
		@param $str1
		@param $str2
		*/
		function cmp_version($str1,$str2,$debug=False)
		{
			ereg("([0-9]+)\.([0-9]+)\.([0-9]+)[a-zA-Z]*([0-9]*)",$str1,$regs);
			ereg("([0-9]+)\.([0-9]+)\.([0-9]+)[a-zA-Z]*([0-9]*)",$str2,$regs2);
			if($debug)
			{
				echo '<br>cmp_version(' . $str1 . ',' . $str2 . ')';
				echo "<br>$regs[0] - $regs2[0]";
			}

			for($i=1;$i<5;$i++)
			{
				if($debug) { echo "<br>$i: $regs[$i] - $regs2[$i]"; }
				if($regs2[$i] == $regs[$i])
				{
					if($debug) { echo ' are equal...'; }
					continue;
				}
				if($regs2[$i] > $regs[$i])
				{
					if($debug) { echo ', and a < b.  Returning 1.'; }
					return 1;
				}
				elseif($regs2[$i] < $regs[$i])
				{
					if($debug) { echo ', and a > b.  Returning 0.'; }
					return 0;
				}
			}
			if($debug)
			{
				echo ' - all equal.  Returning NULL.';
				return '';
			}
		}

		/*!
		@function cmp_version_long
		@abstract Compares two Version strings and return 1 if str2 is newest (bigger version number) than str1
		@discussion This function checks all fields. cmp_version() checks release version only.
		@param $str1
		@param $str2
		*/
		function cmp_version_long($str1,$str2,$debug=False)
		{
			ereg("([0-9]+)\.([0-9]+)\.([0-9]+)[a-zA-Z]*([0-9]*)\.([0-9]*)",$str1,$regs);
			ereg("([0-9]+)\.([0-9]+)\.([0-9]+)[a-zA-Z]*([0-9]*)\.([0-9]*)",$str2,$regs2);
			if($debug)
			{
				echo '<br>cmp_version_long(' . $str1 . ',' . $str2 . ')';
				echo "<br>$regs[0] - $regs2[0]";
			}
			if(!$regs[0])
			{
				if($debug) { echo '<br>calling cmp_version(' . $str1 . ',' . $str2 . ')'; }
				return $this->cmp_version($str1,$str2,$debug);
			}

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
					if($debug) { echo ', and a < b.  Returning 1.'; }
					return 1;
				}
				elseif($regs2[$i] < $regs[$i])
				{
					if($debug) { echo ', and a > b.  Returning 0.'; }
					return 0;
				}
			}
			if($debug)
			{
				echo ' - all equal.  Returning NULL.';
				return '';
			}
		}

		// Convert an array into the format needed for the access column.
		/*!
		@function array_to_string
		@abstract Convert an array into the format needed for the access column
		@param $access
		@param $array
		*/
		function array_to_string($access,$array)
		{
			$this->debug_info[] = 'array_to_string() is a depreciated function - use ACL instead';
			$s = '';
			if ($access == 'group' || $access == 'public' || $access == 'none')
			{
				if (count($array))
				{
					while ($t = each($array)) {
						$s .= ',' . $t[1];
					}
					$s .= ',';
				}
				if (! count($array) && $access == 'none')
				{
					$s = '';
				}
			}
			return $s;
		}

		// This is used for searching the access fields
		/*!
		@function sql_search
		@abstract this function is used for searching the access fields
		@param $table
		@param $owner 
		*/
		function sql_search($table,$owner=0)
		{
			$this->debug_info[] = 'sql_search() is a deprecated function - use ACL instead';
			$s = '';
			if (!$owner)
			{
				$owner = $GLOBALS['phpgw_info']['user']['account_id'];
			}
			$groups = $GLOBALS['phpgw']->accounts->membership(intval($owner));
			if (gettype($groups) == 'array')
			{
				while ($group = each($groups))
				{
					$s .= " or $table like '%," . $group[2] . ",%'";
				}
			}
			return $s;
		}

		// return a array of installed languages
		/*!
		@function getInstalledLanguages
		@abstract return an array of installed languages
		@result $installedLanguages; an array containing the installed languages
		*/
		function getInstalledLanguages()
		{
			$GLOBALS['phpgw']->db->query('select distinct lang from phpgw_lang');
			while (@$GLOBALS['phpgw']->db->next_record()) 
			{
				$installedLanguages[$GLOBALS['phpgw']->db->f('lang')] = $GLOBALS['phpgw']->db->f('lang');
			}

			return $installedLanguages;
		}

		// return the preferred language of the users
		// it's using HTTP_ACCEPT_LANGUAGE (send from the users browser)
		// and ...(to find out which languages are installed)
		/*!
		@function getPreferredLanguage
		@abstract return the preferred langugae of the users
		@discussion it uses HTTP_ACCEPT_LANGUAGE (from the users browser) <br>
		and .... to find out which languages are installed
		*/
		function getPreferredLanguage()
		{
			// create a array of languages the user is accepting
			$userLanguages = explode(',',$GLOBALS['HTTP_ACCEPT_LANGUAGE']);
			$supportedLanguages = $this->getInstalledLanguages();

			// find usersupported language
			while (list($key,$value) = each($userLanguages))
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

		// connect to the ldap server and return a handle
		/*!
		@function ldapConnect
		@abstract connect to the ldap server and return a handle
		@param $host ldap host
		@param $dn ldap_root_dn
		@param $passwd ldap_root_pw
		*/
		function ldapConnect($host = '', $dn = '', $passwd = '')
		{
			if (! $host)
			{
				$host = $GLOBALS['phpgw_info']['server']['ldap_host'];
			}

			if (! $dn)
			{
				$dn = $GLOBALS['phpgw_info']['server']['ldap_root_dn'];
			}

			if (! $passwd)
			{
				$passwd = $GLOBALS['phpgw_info']['server']['ldap_root_pw'];
			}

			// connect to ldap server
			if (! $ds = ldap_connect($host))
			{
				/* log does not exist in setup(, yet) */
				if(is_object($GLOBALS['phpgw']->log))
				{
					$GLOBALS['phpgw']->log->message('F-Abort, Failed connecting to LDAP server');
					$GLOBALS['phpgw']->log->commit();
				}

				printf("<b>Error: Can't connect to LDAP server %s!</b><br>",$host);
				return False;
			}

			// bind as admin, we not to able to do everything
			if (! ldap_bind($ds,$dn,$passwd))
			{
				if(is_object($GLOBALS['phpgw']->log))
				{
					$GLOBALS['phpgw']->log->message('F-Abort, Failed binding to LDAP server');
					$GLOBALS['phpgw']->log->commit();
				}

				printf("<b>Error: Can't bind to LDAP server: %s!</b><br>",$dn);
				return False;
			}

			return $ds;
		}

		/*!
		@function randomstring
		@abstract return a random string of size $size
		@param $size int-size of random string to return
		*/
		function randomstring($size)
		{
			$s = '';
			srand((double)microtime()*1000000);
			$random_char = array('0','1','2','3','4','5','6','7','8','9','a','b','c','d','e','f',
				'g','h','i','j','k','l','m','n','o','p','q','r','s','t','u','v',
				'w','x','y','z','A','B','C','D','E','F','G','H','I','J','K','L',
				'M','N','O','P','Q','R','S','T','U','V','W','X','Y','Z');

			for ($i=0; $i<$size; $i++)
			{
				$s .= $random_char[rand(1,61)];
			}
			return $s;
		}

		// Look at the note towards the top of this file (jengo)
		function filesystem_separator()
		{
			return filesystem_separator();
		}

		/*!
		@function error_list
		@abstract This is used for reporting errors in a nice format.
		@param $error - array of errors
		*/
		function error_list($errors,$text='Error')
		{
			if (! is_array($errors))
			{
				return False;
			}

			if ($text == 'Error')
			{
				$text = '';
			}
			else
			{
				$text .= ': ';
			}
			reset($errors);
			while(list(,$value) = each($errors))
			{
				$msgbox_input[$text.$value] = False;
				
			}
			return $this->msgbox($msgbox_input);
		}

		/*!
		@function check_owner
		@abstract none yet
		@param $record ?
		@param $link ?
		@param $label ?
		@param $extravars
		*/
		// This is a depreciated function - use ACL instead (jengo)
		function check_owner($record,$link,$label,$extravars = '')
		{
			$this->debug_info[] = 'check_owner() is a depreciated function - use ACL instead';
			$s = '<a href="' . $GLOBALS['phpgw']->link($link,$extravars) . '"> ' . lang($label) . ' </a>';
			if (ereg('^[0-9]+$',$record))
			{
				if ($record != $GLOBALS['phpgw_info']['user']['account_id'])
				{
					$s = '&nbsp;';
				}
			}
			else
			{
				if ($record != $GLOBALS['phpgw_info']['user']['userid'])
				{
					$s = '&nbsp';
				}
			}

			return $s;
		}

		/*!
		@function display_fullname
		@abstract return the fullname of a user
		@param $lid account loginid
		@param $firstname firstname
		@param $lastname lastname
		*/
		function display_fullname($lid = '', $firstname = '', $lastname = '')
		{
			if (! $lid && ! $firstname && ! $lastname)
			{
				$lid       = $GLOBALS['phpgw_info']['user']['account_lid'];
				$firstname = $GLOBALS['phpgw_info']['user']['firstname'];
				$lastname  = $GLOBALS['phpgw_info']['user']['lastname'];
			}

			if ($lastname)
			{
				$a[] = $lastname;
			}

			if ($firstname)
			{
				$a[] = $firstname;
			}

			if(isset($a))
			{
				switch(count($a))
				{
					case 0:
						return $lid;
						break;
					case 1:
						return '<' . $lid . '> ' . $a[0];
						break;
					case 2:
						return '<' . $lid . '> ' . implode(', ',$a);
						break;
				}
			}
			else
			{
				return $lid;
			}
		}

		/*!
		@function grab_owner_name
		@abstract grab the owner name
		@param $id account id
		*/
		function grab_owner_name($accountid = '')
		{
			$GLOBALS['phpgw']->accounts->get_account_name($accountid,$lid,$fname,$lname);
			return $this->display_fullname($lid,$fname,$lname);
		}

		/*!
		@function create_tabs
		@abstract create tabs
		@param $tabs ?
		@param $selected ?
		@param $fontsize optional
		*/
		function create_tabs($tabs, $selected, $fontsize = '')
		{
			$output_text = '<table border="0" cellspacing="0" cellpadding="0"><tr>';

			/* This is a php3 workaround */
			if(PHPGW_IMAGES_DIR == 'PHPGW_IMAGES_DIR')
			{
				$ir = ExecMethod('phpgwapi.phpgw.common.get_image_path', 'phpgwapi');
			}
			else
			{
				$ir = PHPGW_IMAGES_DIR;
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
						. $tab[1]['link'] . '" class="tablink">' . $fs . $tab[1]['label']
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
						. $tab[1]['link'] . '" class="tablink">' . $fs . $tab[1]['label'] . $fse
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

		/*!
		@function get_app_dir
		@abstract get directory of application
		@discussion $appname can either be passed or derived from $phpgw_info['flags']['currentapp'];
		@param $appname name of application 
		*/
		function get_app_dir($appname = '')
		{
			if ($appname == '')
			{
				$appname = $GLOBALS['phpgw_info']['flags']['currentapp'];
			}
			if ($appname == 'home' || $appname == 'logout' || $appname == 'login')
			{
				$appname = 'phpgwapi';
			}

			$appdir         = PHPGW_INCLUDE_ROOT . '/'.$appname;
			$appdir_default = PHPGW_SERVER_ROOT . '/'.$appname;

			if (@is_dir ($appdir))
			{
				return $appdir;
			}
			elseif (@is_dir ($appdir_default))
			{
				return $appdir_default;
			}
			else
			{
				return False;
			}
		}

		/*!
		@function get_inc_dir
		@abstract get inc (include dir) of application
		@discussion $appname can either be passed or derived from $phpgw_info['flags']['currentapp'];
		@param $appname name of application 
		*/
		function get_inc_dir($appname = '')
		{
			if (! $appname)
			{
				$appname = $GLOBALS['phpgw_info']['flags']['currentapp'];
			}
			if ($appname == 'home' || $appname == 'logout' || $appname == 'login')
			{
				$appname = 'phpgwapi';
			}
 
			$incdir         = PHPGW_INCLUDE_ROOT . '/' . $appname . '/inc';
			$incdir_default = PHPGW_SERVER_ROOT . '/' . $appname . '/inc';
 
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

		/*!
		@function list_themes
		@abstract list themes available
		*/
		function list_themes()
		{
			$tpl_dir = $this->get_tpl_dir('phpgwapi');

			$dh = opendir($tpl_dir . SEP . 'css');
			while ($file = readdir($dh))
			{
				if (eregi("\.css$", $file) && $file != 'phpgw.css')
				{
					$list[] = substr($file,0,strpos($file,'.'));
				}
			}
			closedir($dh);
			reset ($list);
			return $list;
		}

		/*!
		@function list_templates
		@abstract list available templates
		*/
		function list_templates()
		{
			$d = dir(PHPGW_SERVER_ROOT . '/phpgwapi/templates');
			while ($entry=$d->read())
			{
				if ($entry != 'CVS' && $entry != '.' && $entry != '..' && is_dir(PHPGW_SERVER_ROOT . '/phpgwapi/templates/' . $entry))
				{
					$list[$entry]['name'] = $entry;
					$f = PHPGW_SERVER_ROOT . '/phpgwapi/templates/' . $entry . '/details.inc.php';
					if (file_exists ($f))
					{
						include($f);
						$list[$entry]['title'] = 'Use '.$GLOBALS['phpgw_info']['template'][$entry]['title'].'interface';
					}
					else
					{
						$list[$entry]['title'] = $entry;
					}
				}
			}
			$d->close();
			reset ($list);
			return $list;
		}

		/*!
		@function get_tpl_dir
		@abstract get template dir of an application
		@param $appname appication name optional can be derived from $phpgw_info['flags']['currentapp'];
		*/
		function get_tpl_dir($appname = '',$layout = '')
		{
			if (! $appname)
			{
				$appname = $GLOBALS['phpgw_info']['flags']['currentapp'];
			}
			if ($appname == 'home' || $appname == 'logout' || $appname == 'login' || $appname == 'about')
			{
				$appname = 'phpgwapi';
			}

			if (!isset($GLOBALS['phpgw_info']['server']['template_set']) && isset($GLOBALS['phpgw_info']['user']['preferences']['common']['template_set']))
			{
				$GLOBALS['phpgw_info']['server']['template_set'] = $GLOBALS['phpgw_info']['user']['preferences']['common']['template_set'];
			}

			// Setting this for display of template choices in user preferences
			if (@$GLOBALS['phpgw_info']['server']['template_set'] == 'user_choice')
			{
				$GLOBALS['phpgw_info']['server']['usrtplchoice'] = 'user_choice';
			}

			if ((@$GLOBALS['phpgw_info']['server']['template_set'] == 'user_choice' ||
				!isset($GLOBALS['phpgw_info']['server']['template_set'])) &&
				isset($GLOBALS['phpgw_info']['user']['preferences']['common']['template_set']))
			{
				$GLOBALS['phpgw_info']['server']['template_set'] = $GLOBALS['phpgw_info']['user']['preferences']['common']['template_set'];
			}
			elseif (@$GLOBALS['phpgw_info']['server']['template_set'] == 'user_choice' ||
				!isset($GLOBALS['phpgw_info']['server']['template_set']))
			{
				$GLOBALS['phpgw_info']['server']['template_set'] = 'default';
			}

/******** start temporarily code **************************************/
/* this just makes sure the template set is updated to the new format */
if (!@is_file(PHPGW_SERVER_ROOT . '/phpgwapi/templates/' . $GLOBALS['phpgw_info']['server']['template_set'].'/phpgw.xsl'))
{
	$GLOBALS['phpgw_info']['server']['template_set'] = 'idsociety';
}
/******** end temporarily code **************************************/

			$tpldir         = PHPGW_SERVER_ROOT . '/' . $appname . '/templates/' . $GLOBALS['phpgw_info']['server']['template_set'];
			$tpldir_default = PHPGW_SERVER_ROOT . '/' . $appname . '/templates/default';

			if ($layout == 'default')
			{
				$tpldir = $tpldir_default;
			}

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

		/*!
		@function get_image_dir
		@abstract get image dir of an application
		@param $appname application name optional can be derived from $phpgw_info['flags']['currentapp'];
		*/
		function get_image_dir($appname = '',$layout = '')
		{
			if ($appname == '')
			{
				$appname = $GLOBALS['phpgw_info']['flags']['currentapp'];
			}
			if (empty($GLOBALS['phpgw_info']['server']['template_set']))
			{
				$GLOBALS['phpgw_info']['server']['template_set'] = 'default';
			}

			if ($layout)
			{
				$imagedir_layout = PHPGW_SERVER_ROOT . SEP . $appname . SEP . 'templates' . SEP . $layout . SEP . 'images';
				if (@is_dir ($imagedir_layout))
				{
					return $imagedir_layout;
				}
			}

			$imagedir            = PHPGW_SERVER_ROOT . '/' . $appname . '/templates/' . $GLOBALS['phpgw_info']['server']['template_set'] . '/images';
			$imagedir_default    = PHPGW_SERVER_ROOT . '/' . $appname . '/templates/default/images';
			$imagedir_olddefault = PHPGW_SERVER_ROOT . '/' . $appname . '/images';

			if (@is_dir ($imagedir))
			{
				return $imagedir;
			}
			elseif (@is_dir ($imagedir_default))
			{
				return $imagedir_default;
			}
			elseif (@is_dir ($imagedir_olddefault))
			{
				return $imagedir_olddefault;
			}
			else
			{
				return False;
			}
		}

		/*!
		@function get_image_path
		@abstract get image path of an application
		@param $appname appication name optional can be derived from $phpgw_info['flags']['currentapp'];
		*/
		function get_image_path($appname = '',$layout = '')
		{
			if ($appname == '')
			{
				$appname = $GLOBALS['phpgw_info']['flags']['currentapp'];
			}

			if (empty($GLOBALS['phpgw_info']['server']['template_set']))
			{
				$GLOBALS['phpgw_info']['server']['template_set'] = 'default';
			}

			if ($layout)
			{
				$imagedir_layout = PHPGW_SERVER_ROOT . SEP . $appname . SEP . 'templates' . SEP . $layout . SEP . 'images';
				if (@is_dir ($imagedir_layout))
				{
					return $GLOBALS['phpgw_info']['server']['webserver_url'] . SEP . $appname . SEP . 'templates' . SEP . $layout . SEP . 'images';
				}
			}

			$imagedir            = PHPGW_SERVER_ROOT . '/'.$appname.'/templates/'.$GLOBALS['phpgw_info']['server']['template_set'].'/images';
			$imagedir_default    = PHPGW_SERVER_ROOT . '/'.$appname.'/templates/default/images';
			$imagedir_olddefault = PHPGW_SERVER_ROOT . '/'.$appname.'/images';

			if (@is_dir ($imagedir))
			{
				return $GLOBALS['phpgw_info']['server']['webserver_url'].'/'.$appname.'/templates/'.$GLOBALS['phpgw_info']['server']['template_set'].'/images';
			}
			elseif (@is_dir ($imagedir_default))
			{
				return $GLOBALS['phpgw_info']['server']['webserver_url'].'/'.$appname.'/templates/default/images';
			}
			elseif (@is_dir ($imagedir_olddefault))
			{
				return $GLOBALS['phpgw_info']['server']['webserver_url'].'/'.$appname.'/images';
			}
			else
			{
				return False;
			}
		}

		function find_image($appname,$image)
		{
			static $imgpref;
			if(! @$imgpref)
			{
				switch(@$GLOBALS['phpgw_info']['server']['image_type'])
				{
					case 1:
						$imgpref = Array('png','jpg','gif');
						break;
					case 2:
						$imgpref = Array('png','jpg','nogifs');
						break;
					default:
						$imgpref = Array('gif','jpg','png');
						break;
				}
			}
			if (!@is_array($this->found_files[$appname]))
			{
				$imagedir_olddefault = '/'.$appname.'/images';
				$imagedir_default    = '/'.$appname.'/templates/default/images';
				$imagedir = '/'.$appname.'/templates/'.$GLOBALS['phpgw_info']['server']['template_set'].'/images';

				if (@is_dir(PHPGW_INCLUDE_ROOT.$imagedir_olddefault))
				{
					$d = dir(PHPGW_INCLUDE_ROOT.$imagedir_olddefault);
					while (false != ($entry = $d->read()))
					{
						if ($entry != '.' && $entry != '..')
						{
							$this->found_files[$appname][$entry] = $imagedir_olddefault;
						}
					}
					$d->close();
				}

				if (@is_dir(PHPGW_INCLUDE_ROOT.$imagedir_default))
				{
					$d = dir(PHPGW_INCLUDE_ROOT.$imagedir_default);
					while (false != ($entry = $d->read()))
					{
						if ($entry != '.' && $entry != '..')
						{
							$this->found_files[$appname][$entry] = $imagedir_default;
						}
					}
					$d->close();
				}

				if (@is_dir(PHPGW_INCLUDE_ROOT.$imagedir))
				{
					$d = dir(PHPGW_INCLUDE_ROOT.$imagedir);
					while (false != ($entry = $d->read()))
					{
						if ($entry != '.' && $entry != '..')
						{
							$this->found_files[$appname][$entry] = $imagedir;
						}
					}
					$d->close();
				}
			}

			if(isset($this->found_files[$appname][$image.'.'.$imgpref[0]]))
			{
				$imgfile = $GLOBALS['phpgw_info']['server']['webserver_url'].$this->found_files[$appname][$image.'.'.$imgpref[0]].'/'.$image.'.'.$imgpref[0];
			}
			elseif(isset($this->found_files[$appname][$image.'.'.$imgpref[1]]))
			{
				$imgfile = $GLOBALS['phpgw_info']['server']['webserver_url'].$this->found_files[$appname][$image.'.'.$imgpref[1]].'/'.$image.'.'.$imgpref[1];
			}
			elseif(isset($this->found_files[$appname][$image.'.'.$imgpref[2]]))
			{
				$imgfile = $GLOBALS['phpgw_info']['server']['webserver_url'].$this->found_files[$appname][$image.'.'.$imgpref[2]].'/'.$image.'.'.$imgpref[2];
			}
			elseif(isset($this->found_files[$appname][$image]))
			{
				$imgfile = $GLOBALS['phpgw_info']['server']['webserver_url'].$this->found_files[$appname][$image].'/'.$image;
			}
			elseif(isset($this->found_files['phpgwapi'][$image.'.'.$imgpref[0]]))
			{
				$imgfile = $GLOBALS['phpgw_info']['server']['webserver_url'].$this->found_files['phpgwapi'][$image.'.'.$imgpref[0]].'/'.$image.'.'.$imgpref[0];
			}
			elseif(isset($this->found_files['phpgwapi'][$image.'.'.$imgpref[1]]))
			{
				$imgfile = $GLOBALS['phpgw_info']['server']['webserver_url'].$this->found_files['phpgwapi'][$image.'.'.$imgpref[1]].'/'.$image.'.'.$imgpref[1];
			}
			elseif(isset($this->found_files['phpgwapi'][$image.'.'.$imgpref[2]]))
			{
				$imgfile = $GLOBALS['phpgw_info']['server']['webserver_url'].$this->found_files['phpgwapi'][$image.'.'.$imgpref[2]].'/'.$image.'.'.$imgpref[2];
			}
			elseif(isset($this->found_files['phpgwapi'][$image]))
			{
				$imgfile = $GLOBALS['phpgw_info']['server']['webserver_url'].$this->found_files['phpgwapi'][$image].'/'.$image;
			}
			else
			{
				$imgfile = '';
			}
			return $imgfile;
		}

		function image($appname,$image='',$ext='')
		{
			if(is_array($image))
			{
				$i = 0;
				if(isset($this->found_files[$appname][$image[$i].$ext]))
				{
					$image_found = $GLOBALS['phpgw_info']['server']['webserver_url'].$this->found_files[$appname][$image[$i].$ext].'/'.$image[$i].$ext;
				}
				else
				{
					$image_found = $this->find_image($appname,$image[$i].$ext);
				}
				$c_image = count($image);
				$i++;
				while($image_found == '' && $i<$c_image)
				{
					if(isset($this->found_files[$appname][$image[$i].$ext]))
					{
						$image_found = $GLOBALS['phpgw_info']['server']['webserver_url'].$this->found_files[$appname][$image[$i].$ext].'/'.$image[$i].$ext;
					}
					else
					{
						$image_found = $this->find_image($appname,$image[$i].$ext);
						$i++;
					}
				}
				return $image_found;
			}
			elseif($image != '')
			{
				if(isset($this->found_files[$appname][$image.$ext]))
				{
					return $GLOBALS['phpgw_info']['server']['webserver_url'].$this->found_files[$appname][$image.$ext].'/'.$image.$ext;
				}
				else
				{
					return $this->find_image($appname,$image.$ext);
				}
			}
			else
			{
				return '';
			}
		}

		function image_on($appname,$image,$extension='_on')
		{
			$with_extension = $this->image($appname,$image,$extension);
			$without_extension = $this->image($appname,$image);
			if($with_extension != '')
			{
				return $with_extension;
			}
			elseif($without_extension != '')
			{
				return $without_extension;
			}
			else
			{
				return '';
			}
		}

		/*!
		@function msgbox
		@abstract Generate a consistant msgbox for app apps to use
		@discussion makes it easier and more consistant to generate message boxes
		*/

		function msgbox($text = '', $type = True, $base = '')
		{
			if ($text=='' && @isset($GLOBALS['phpgw_info']['flags']['msgbox_data']))
			{
				$text = $GLOBALS['phpgw_info']['flags']['msgbox_data'];
				unset($GLOBALS['phpgw_info']['flags']['msgbox_data']);
			}
			elseif($text=='')
			{
				return;
			}

			$GLOBALS['phpgw']->xslttpl->add_file($this->get_tpl_dir('phpgwapi','default') . SEP . 'msgbox');

			$prev_helper = $GLOBALS['phpgw']->translation->translator_helper;
			$GLOBALS['phpgw']->translation->translator_helper = '';

			if (is_array($text))
			{
				reset($text);

				while (list($key,$value) = each($text))
				{
					if ($value == True)
					{
						$img	= $this->image('phpgwapi','msgbox_good');
						$alt	= lang('OK');
					}
					else
					{
						$img	= $this->image('phpgwapi','msgbox_bad');
						$alt	= lang('ERROR');
					}

					$data[] = array
					(
						'msgbox_text'				=> lang($key),
						'msgbox_img'				=> $img,
						'msgbox_img_alt'			=> $alt,
						'lang_msgbox_statustext'	=> $alt
					);
				}
			}
			else
			{
				if ($type == True)
				{
					$img	= $this->image('phpgwapi','msgbox_good');
					$alt	= lang('OK');
				}
				else
				{
					$img	= $this->image('phpgwapi','msgbox_bad');
					$alt	= lang('ERROR');
				}

				$data = array
				(
					'msgbox_text'				=> lang($text),
					'msgbox_img'				=> $img,
					'msgbox_img_alt'			=> $alt,
					'lang_msgbox_statustext'	=> $alt
				);
			}

			$GLOBALS['phpgw']->translation->translator_helper = $prev_helper;

			if ($base)
			{
				$GLOBALS['phpgw']->xslttpl->set_var($base,array('msgbox_data' => $data),True);
			}
			else
			{
				return $data;
			}
		}

		function framework()
		{
			$this->navbar();

			$css = $this->get_css_url();
			$var = array
			(
				'charset'			=> lang('charset'),
				'website_title'		=> $GLOBALS['phpgw_info']['server']['site_title'],
				'phpgw_css_file'	=> $css[0],
				'theme_css_file'	=> $css[1],
				'phpgw_body'		=> $phpgw_body
			);

			$GLOBALS['phpgw']->xslttpl->add_file($this->get_tpl_dir('phpgwapi') . SEP . 'phpgw');

			$GLOBALS['phpgw_info']['user']['preferences']['common']['template_set'] = 'idsociety';

			switch ($GLOBALS['phpgw_info']['user']['preferences']['common']['template_set'])
			{
				case 'idsociety':
					$find_single = strrpos($GLOBALS['phpgw_info']['server']['webserver_url'],'/');
					$find_double = strpos(strrev($GLOBALS['phpgw_info']['server']['webserver_url'].' '),'//');
					if($find_double)
					{
						$find_double = strlen($GLOBALS['phpgw_info']['server']['webserver_url']) - $find_double - 1;
					}
					if($find_double)
					{
						if($find_single == $find_double + 1)
						{
							$GLOBALS['strip_portion'] = $GLOBALS['phpgw_info']['server']['webserver_url'];
						}
						else
						{
							$GLOBALS['strip_portion'] = substr($GLOBALS['phpgw_info']['server']['webserver_url'],0,$find_double + 1);
						}
					}
					else
					{
						$GLOBALS['strip_portion'] = $GLOBALS['phpgw_info']['server']['webserver_url'].'/';
					}

					$var['home_link']		= $GLOBALS['phpgw_info']['navbar']['home']['url'];
					$var['prefs_link']		= $GLOBALS['phpgw_info']['navbar']['preferences']['url'];
					$var['logout_link']		= $GLOBALS['phpgw_info']['navbar']['logout']['url'];
					$var['about_link']		= $GLOBALS['phpgw_info']['navbar']['about']['url'];
	
					$var['home_title']		= $GLOBALS['phpgw_info']['navbar']['home']['title'];
					$var['prefs_title']		= $GLOBALS['phpgw_info']['navbar']['preferences']['title'];
					$var['logout_title']	= $GLOBALS['phpgw_info']['navbar']['logout']['title'];
					$var['about_title']		= $GLOBALS['phpgw_info']['navbar']['about']['title'];

					if ($GLOBALS['phpgw_info']['flags']['currentapp'] != 'home')
					{
						$var['home_img'] = $this->image('phpgwapi','welcome2');
						$GLOBALS['phpgw_info']['flags']['preload_images'][] = $this->image_on('phpgwapi','welcome2','_over');
					}
					else
					{
						$var['home_img'] = $this->image_on('phpgwapi','welcome2','_over');
						$GLOBALS['phpgw_info']['flags']['preload_images'][] = $this->image('phpgwapi','welcome2');
					}
					$var['home_img_hover'] = $this->image_on('phpgwapi','welcome2','_over');

					if ($GLOBALS['phpgw_info']['flags']['currentapp'] != 'preferences')
					{
						$var['prefs_img'] = $this->image('phpgwapi','preferences2');
						$GLOBALS['phpgw_info']['flags']['preload_images'][] = $this->image_on('phpgwapi','preferences2','_over');
					}
					else
					{
						$var['prefs_img'] = $this->image_on('phpgwapi','preferences2','_over');
						$GLOBALS['phpgw_info']['flags']['preload_images'][] = $this->image('phpgwapi','preferences2');
					}
					$var['prefs_img_hover'] = $this->image_on('phpgwapi','preferences2','_over');

					$var['logout_img'] = $this->image('phpgwapi','log_out2');
					$GLOBALS['phpgw_info']['flags']['preload_images'][] = $this->image_on('phpgwapi','log_out2','_over');
					$var['logout_img_hover'] = $this->image_on('phpgwapi','log_out2','_over');

					if ($GLOBALS['phpgw_info']['flags']['currentapp'] != 'about')
					{
						$var['about_img']		= $this->image('phpgwapi','question_mark2');
						$var['about_img_hover']	= $this->image_on('phpgwapi','question_mark2','_over');
					}
					else
					{
						$var['about_img'] = $this->image_on('phpgwapi','question_mark2','_over');
						$var['about_img_hover'] = $this->image('phpgwapi','question_mark2');
					}

					$var['logo_img'] = $this->image('phpgwapi','logo2');
					$var['nav_bar_left_top_bg_img'] = $this->image('phpgwapi','nav_bar_left_top_bg');
					break;
			}

			if (isset($GLOBALS['phpgw_info']['navbar']['admin']) && isset($GLOBALS['phpgw_info']['user']['preferences']['common']['show_currentusers']))
			{
				$GLOBALS['phpgw']->db->query('select count(session_id) from phpgw_sessions');
				$GLOBALS['phpgw']->db->next_record();
				$var['current_users']		= lang('Current users') . ': ' . $GLOBALS['phpgw']->db->f(0);
				$var['url_current_users']	= $GLOBALS['phpgw']->link('/index.php','menuaction=admin.uicurrentsessions.list_sessions');
			}

			$var['user_info_name'] = $this->display_fullname();
			$now = time();
			$var['user_info_date'] = lang($this->show_date($now,'l')) . ' '
									. $this->show_date($now,$GLOBALS['phpgw_info']['user']['preferences']['common']['dateformat']);
			$var['user_info'] = $var['user_info_name'] .' - ' .$var['user_info_date'];

			while ($app = each($GLOBALS['phpgw_info']['navbar']))
			{
				if ($app[0] != 'home' && $app[0] != 'preferences' && $app[0] != 'about' && $app[0] != 'logout')
				{
					$var['applications'][] = array
					(
						'icon'			=> $app[1]['icon'],
						'title'			=> $app[1]['title'],
						'img_src_over'	=> $app[1]['icon_hover'],
						'url'			=> $app[1]['url'],
						'name'			=> str_replace('-','_',$app[0])
					);

					if($app[1]['icon_hover'] != '')
					{
						$GLOBALS['phpgw_info']['flags']['preload_images'][] = $app[1]['icon_hover'];
					}
				}
			}

			$var['onload'] = $this->load_preload_images_data();

			if($GLOBALS['phpgw_info']['flags']['msgbox_data'])
			{
				$this->msgbox('',False,'phpgw');
			}

			switch($GLOBALS['phpgw_info']['flags']['currentapp'])
			{
				case 'home':
					$var['home'] = True;
					break;
			}

			$var['lang_powered_by']			= lang('powered by');
			$var['lang_version']			= lang('version');
			$var['phpgw_version']			= $GLOBALS['phpgw_info']['server']['versions']['phpgwapi'];
			$var['lang_phpgw_statustext']	= lang('phpGroupWare --> homepage');
			$var['top_spacer_middle_img']	= $GLOBALS['phpgw']->common->image('phpgwapi','top_spacer_middle');

			$GLOBALS['phpgw']->xslttpl->set_var('phpgw',$var,True);
		}

		/*!
		@function navbar
		@abstract Build the application navigation bar based on user's accessible applications
		@discussion *someone wanna add some detail here*
		*/
		function navbar()
		{
			$GLOBALS['phpgw_info']['navbar']['home']['title']		= lang('home');
			$GLOBALS['phpgw_info']['navbar']['home']['url']			= $GLOBALS['phpgw']->link('/home.php');
			$GLOBALS['phpgw_info']['navbar']['home']['icon']		= $this->image('phpgwapi',Array('home','nonav'));
			$GLOBALS['phpgw_info']['navbar']['home']['icon_hover']	= $this->image_on('phpgwapi',Array('home','nonav'),'-over');

			reset($GLOBALS['phpgw_info']['user']['apps']);
			/* ksort($GLOBALS['phpgw_info']['user']['apps']); */

			if(is_array($GLOBALS['phpgw_info']['user']['apps']['admin']))
			{
				$newarray['admin'] = $GLOBALS['phpgw_info']['user']['apps']['admin'];
				while(list($index,$value) = each($GLOBALS['phpgw_info']['user']['apps']))
				{
					if($index != 'admin')
					{
						$newarray[$index] = $value;
					}
				}
				$GLOBALS['phpgw_info']['user']['apps'] = $newarray;
				reset($GLOBALS['phpgw_info']['user']['apps']);
			}
			unset($index);
			unset($value);
			unset($newarray);

			while ($permission = each($GLOBALS['phpgw_info']['user']['apps']))
			{
				if (is_long($permission[0]))
				{
					continue;
				}

				if ($GLOBALS['phpgw_info']['apps'][$permission[0]]['status'] != 2 && $GLOBALS['phpgw_info']['apps'][$permission[0]]['status'] != 3)
				{
					$GLOBALS['phpgw_info']['navbar'][$permission[0]]['title']	= lang($permission[0]);
					$GLOBALS['phpgw_info']['navbar'][$permission[0]]['url']		= $GLOBALS['phpgw']->link('/' . $permission[0] . '/index.php');
					$GLOBALS['phpgw_info']['navbar'][$permission[0]]['name']	= $permission[0];

					if ($permission[0] != $GLOBALS['phpgw_info']['flags']['currentapp'])
					{
						$GLOBALS['phpgw_info']['navbar'][$permission[0]]['icon']		= $this->image($permission[0],Array('navbar','nonav'));
						$GLOBALS['phpgw_info']['navbar'][$permission[0]]['icon_hover']	= $this->image_on($permission[0],Array('navbar','nonav'),'-over');
					}
					else
					{
						$GLOBALS['phpgw_info']['navbar'][$permission[0]]['icon']		= $this->image_on($permission[0],Array('navbar','nonav'),'-over');
						$GLOBALS['phpgw_info']['navbar'][$permission[0]]['icon_hover']	= $this->image($permission[0],Array('navbar','nonav'));
					}
				}
			}

			$GLOBALS['phpgw_info']['navbar']['preferences']['title']		= lang('preferences');
			$GLOBALS['phpgw_info']['navbar']['preferences']['url']			= $GLOBALS['phpgw']->link('/preferences/index.php');
			$GLOBALS['phpgw_info']['navbar']['preferences']['icon']			= $this->image('preferences',Array('navbar','nonav'));
			$GLOBALS['phpgw_info']['navbar']['preferences']['icon_hover']	= $this->image_on('preferences',Array('navbar','nonav'),'-over');

			if ($GLOBALS['phpgw_info']['flags']['currentapp'] == 'home' || $GLOBALS['phpgw_info']['flags']['currentapp'] == 'preferences')
			{
				$app = 'phpGroupWare';
			}
			else
			{
				$app = $GLOBALS['phpgw_info']['flags']['currentapp'];
			}

			/* We handle this here becuase its special */

			$GLOBALS['phpgw_info']['navbar']['about']['title']		= lang('about x',lang($app));
			$GLOBALS['phpgw_info']['navbar']['about']['url']		= $GLOBALS['phpgw']->link('/about.php','app='.$app);
			$GLOBALS['phpgw_info']['navbar']['about']['icon']		= $this->image('phpgwapi',Array('about','nonav'));
			$GLOBALS['phpgw_info']['navbar']['about']['icon_hover']	= $this->image_on('phpgwapi',Array('about','nonav'),'-over');

			$GLOBALS['phpgw_info']['navbar']['logout']['title'] = lang('logout');
			$GLOBALS['phpgw_info']['navbar']['logout']['url']   = $GLOBALS['phpgw']->link('/logout.php');
			$GLOBALS['phpgw_info']['navbar']['logout']['icon']  = $this->image('phpgwapi',Array('logout','nonav'));
			$GLOBALS['phpgw_info']['navbar']['logout']['icon_hover']  = $this->image_on('phpgwapi',Array('logout','nonav'),'-over');
		}

		/*!
		@function get_css_url
		@abstract returns the path of the css file for the choosen layout/theme
		@discussion *someone wanna add some detail here*
		*/
		function get_css_url()
		{
			if (! $GLOBALS['phpgw_info']['user']['preferences']['common']['theme'])
			{
				if ($GLOBALS['phpgw_info']['server']['template_set'] == 'user_choice')
				{
					$GLOBALS['phpgw_info']['user']['preferences']['common']['theme'] = 'default';
				}
				else
				{
					$GLOBALS['phpgw_info']['user']['preferences']['common']['theme'] = $GLOBALS['phpgw_info']['server']['template_set'];
				}
			}
			if ($GLOBALS['phpgw_info']['server']['force_theme'] == 'user_choice')
			{
				if (!isset($GLOBALS['phpgw_info']['user']['preferences']['common']['theme']))
				{
					$GLOBALS['phpgw_info']['user']['preferences']['common']['theme'] = 'default';
				}
			}
			else
			{
				if (isset($GLOBALS['phpgw_info']['server']['force_theme']))
				{
					$GLOBALS['phpgw_info']['user']['preferences']['common']['theme'] = $GLOBALS['phpgw_info']['server']['force_theme'];
				}
			}

			if(@file_exists(PHPGW_SERVER_ROOT . SEP . 'phpgwapi' . SEP . 'templates' . SEP . $GLOBALS['phpgw_info']['user']['preferences']['common']['template_set']
							. SEP . 'css' . SEP . $GLOBALS['phpgw_info']['user']['preferences']['common']['theme'] . '.css'))
			{
				$css_file = $GLOBALS['phpgw_info']['server']['webserver_url'] . SEP . 'phpgwapi' . SEP . 'templates' . SEP . $GLOBALS['phpgw_info']['user']['preferences']['common']['template_set']
						. SEP . 'css' . SEP . $GLOBALS['phpgw_info']['user']['preferences']['common']['theme'] . '.css';
			}
			elseif(@file_exists(PHPGW_SERVER_ROOT . SEP . 'phpgwapi' . SEP . 'templates' . SEP . $GLOBALS['phpgw_info']['user']['preferences']['common']['template_set'] . SEP . 'css' . SEP . 'submarine.css'))
			{
				$css_file =  $GLOBALS['phpgw_info']['server']['webserver_url'] . SEP . 'phpgwapi' . SEP . 'templates' . SEP . $GLOBALS['phpgw_info']['user']['preferences']['common']['template_set'] . SEP . 'css' . SEP . 'submarine.css';
			}
			else
			{
				/* Hope we don't get to this point.  Better then the user seeing a */
				/* complety back screen and not know whats going on                */
				$GLOBALS['phpgw_info']['theme']['bg_color'] = 'FFFFFF';
				$GLOBALS['phpgw']->log->write(array('text'=>'F-Abort, No themes found'));
			}
			$phpgw_css_file = $GLOBALS['phpgw_info']['server']['webserver_url'] . SEP . 'phpgwapi' . SEP . 'templates' . SEP . $GLOBALS['phpgw_info']['user']['preferences']['common']['template_set']
							. SEP . 'css' . SEP . 'phpgw.css';
			return array($phpgw_css_file,$css_file);
		}

		function load_preload_images_data()
		{
			if(@is_array($GLOBALS['phpgw_info']['flags']['preload_images']))
			{
				$preload_image_string = '';
				reset($GLOBALS['phpgw_info']['flags']['preload_images']);
				while(list($key,$value) = each($GLOBALS['phpgw_info']['flags']['preload_images']))
				{
					if($preload_image_string != '')
					{
						$preload_image_string .= ",'$value'";
					}
					else
					{
						$preload_image_string .= "'$value'";
					}
				}
				return " MM_preloadImages($preload_image_string);";
			}
			return '';
		}

		function load_phpgw_body_tags()
		{
			$GLOBALS['phpgw_info']['flags']['body_tags']['onLoad'] = $this->load_preload_images_data(); 

			if(@is_array($GLOBALS['phpgw_info']['flags']['body_tags']))
			{
				$body_tags_string = '';
				reset($GLOBALS['phpgw_info']['flags']['body_tags']);
				while(list($key,$value) = each($GLOBALS['phpgw_info']['flags']['body_tags']))
				{
					if($value != '')
					{
						$body_tags_string .= " $key=\"$value\"";
					}
				}
				$GLOBALS['phpgw']->template->set_var('phpgw_body_tags',$body_tags_string);
			}
		}

		/*!
		@function phpgw_header
		@abstract load the phpgw header
		*/
		function phpgw_header($forceheader = True, $forcenavbar = True)
		{
			/* So far I dont have use for $forceheader and $forcenavbar */
			/* I only allow this to be run once by using the constant */
			if(!defined('PHPGW_HEADER_RAN'))
			{
				define('PHPGW_HEADER_RAN',True);
				$this->get_css_url();
				$this->load_phpgw_body_tags();
				$GLOBALS['phpgw']->template->set_var('phpgw_msgbox',$this->msgbox());
				$GLOBALS['phpgw']->template->set_block('phpgw','phpgw_head_javascript');
				$GLOBALS['phpgw']->template->pfp('out','phpgw_main_start');
			}
		}

		/*!
		@function phpgw_appheader
		@abstract load header.inc.php for an application
		*/
		function phpgw_appheader()
		{
			if (!is_array(MENUACTION))
			{
				list($app,$class,$method) = explode('.',MENUACTION);
				if (is_array($GLOBALS[$class]->public_functions) && $GLOBALS[$class]->public_functions['header'])
				{
					$GLOBALS[$class]->header();
				}
			}
			elseif (file_exists(PHPGW_APP_INC . '/header.inc.php'))
			{
				include(PHPGW_APP_INC . '/header.inc.php');
			}
		}
		/*!
		@function phpgw_appfooter
		@abstract load footer.inc.php for an application
		*/
		function phpgw_appfooter()
		{
			if (!is_array(MENUACTION))
			{
				list($app,$class,$method) = explode('.',MENUACTION);
				if (is_array($GLOBALS[$class]->public_functions) && $GLOBALS[$class]->public_functions['footer'])
				{
					$GLOBALS[$class]->footer();
				}
			}
			elseif (file_exists(PHPGW_APP_INC . '/footer.inc.php'))
			{
				include(PHPGW_APP_INC . '/footer.inc.php');
			}
		}

		function phpgw_footer()
		{
			if(!defined('PHPGW_FOOTER_RAN'))
			{
				define('PHPGW_FOOTER_RAN',True);
				if (!isset($GLOBALS['phpgw_info']['flags']['nodisplay']) || !$GLOBALS['phpgw_info']['flags']['nodisplay'])
				{
					if($GLOBALS['phpgw_info']['flags']['currentapp'] != 'home' &&
						$GLOBALS['phpgw_info']['flags']['currentapp'] != 'login' &&
						$GLOBALS['phpgw_info']['flags']['currentapp'] != 'logout' &&
						!@$GLOBALS['phpgw_info']['flags']['noappfooter'])
					{
						$this->phpgw_appfooter();
					}
					$this->phpgw_header();
					$GLOBALS['phpgw']->template->pfp('out','phpgw_main_end');
				}
				
				$GLOBALS['phpgw']->db->disconnect();

				/* Clean up mcrypt */
				if (@is_object($GLOBALS['phpgw']->crypto))
				{
					$GLOBALS['phpgw']->crypto->cleanup();
					unset($GLOBALS['phpgw']->crypto);
				}
				
				if (DEBUG_TIMER)
				{
					$GLOBALS['debug_timer_stop'] = perfgetmicrotime();
					echo 'Page loaded in ' . ($GLOBALS['debug_timer_stop'] - $GLOBALS['debug_timer_start']) . ' seconds.';
				}
			}
		}

		function hex2bin($data)
		{
			$len = strlen($data);
			return pack('H' . $len, $data);
		}

		/*!
		@function encrypt
		@abstract encrypt data passed to the function
		@param $data data (string?) to be encrypted
		*/
		function encrypt($data)
		{
			return $GLOBALS['phpgw']->crypto->encrypt($data);
		}
		/*!
		@function decrypt
		@abstract decrypt $data
		@param $data data to be decrypted
		*/
		function decrypt($data)
		{
			return $GLOBALS['phpgw']->crypto->decrypt($data);
		}

		/*!
		@function des_cryptpasswd
		@abstract des encrypt a password
		@param $userpass userpassword
		@param $random random seed
		*/
		function des_cryptpasswd($userpass, $random)
		{
			$lcrypt = '{crypt}';
			$password = crypt($userpass);
			$ldappassword = sprintf('%s%s', $lcrypt, $password);

			return $ldappassword;
		}
		/*!
		@function md5_cryptpasswd
		@abstract md5 encrypt password
		@param $userpass user password
		@param $random random seed
		*/
		function md5_cryptpasswd($userpass, $random)
		{
			$bsalt = '$1$';
			$esalt = '$';
			$lcrypt = '{crypt}';
			$modsalt = sprintf('%s%s%s', $bsalt, $random, $esalt);
			$password = crypt($userpass, $modsalt);
			$ldappassword = sprintf('%s%s', $lcrypt, $password);

			return $ldappassword;
		}
		/*!
		@function sha_cryptpasswd
		@abstract sha encrypt password
		@param $userpass user password
		*/
		function sha_cryptpasswd($userpass)
		{
			$hash = base64_encode(mhash(MHASH_SHA1, $userpass));
			$ldappassword = sprintf('%s%s', '{SHA}', $hash);

			return $ldappassword;
		}

		/*!
		@function encrypt_password
		@abstract encrypt password
		@abstract uses the encryption type set in setup and calls the appropriate encryption functions
		@param $password password to encrypt
		*/
		function encrypt_password($password)
		{
			if($GLOBALS['phpgw_info']['server']['ldap_encryption_type'] == 'DES')
			{
				$salt       = $this->randomstring(2);
				$e_password = $this->des_cryptpasswd($password, $salt);
			}
			if($GLOBALS['phpgw_info']['server']['ldap_encryption_type'] == 'MD5')
			{
				$salt       = $this->randomstring(8);
				$e_password = $this->md5_cryptpasswd($password, $salt);
			}
			if($GLOBALS['phpgw_info']['server']['ldap_encryption_type'] == 'SHA')
			{
				if(@function_exists('mhash'))
				{
					$e_password = $this->sha_cryptpasswd($password);
				}
				else
				{
					/* this should error instead... */
					$salt       = $this->randomstring(8);
					$e_password = $this->md5_cryptpasswd($password, $salt);
				}
			}
			return $e_password;
		}

		/*!
		@function find_portal_order
		@abstract find the current position of the app is the users portal_order preference
		@param $app application id to find current position - required
		@discussion No discussion
		*/
		function find_portal_order($app)
		{
			if(!is_array($GLOBALS['phpgw_info']['user']['preferences']['portal_order']))
			{
				return -1;
			}
			@reset($GLOBALS['phpgw_info']['user']['preferences']['portal_order']);
			while(list($seq,$appid) = each($GLOBALS['phpgw_info']['user']['preferences']['portal_order']))
			{
				if($appid == $app)
				{
					@reset($GLOBALS['phpgw_info']['user']['preferences']['portal_order']);
					return $seq;
				}
			}
			@reset($GLOBALS['phpgw_info']['user']['preferences']['portal_order']);
			return -1;
		}

		/*!
		@function hook
		@abstract temp wrapper to new hooks class
		*/
		function hook($location, $appname = '', $no_permission_check = False)
		{
			echo '$'."GLOBALS['phpgw']common->hook()".' has been replaced. Please change to the new $'."GLOBALS['phpgw']hooks->process()".'. For now this will act as a wrapper<br>';
			return $GLOBALS['phpgw']->hooks->process($location, $order, $no_permission_check);
		}

		/*!
		@function hook_single
		@abstract temp wrapper to new hooks class
		*/
		// Note: $no_permission_check should *ONLY* be used when it *HAS* to be. (jengo)
		function hook_single($location, $appname = '', $no_permission_check = False)
		{
			echo '$'."GLOBALS['phpgw']common->hook_single()".' has been replaced. Please change to the new $'."GLOBALS['phpgw']hooks->single()".'. For now this will act as a wrapper<br>';
			return $GLOBALS['phpgw']->hooks->single($location, $order, $no_permission_check);
		}

		/*!
		@function hook_count
		@abstract temp wrapper to new hooks class
		*/
		function hook_count($location)
		{
			echo '$'."GLOBALS['phpgw']common->hook_count()".' has been replaced. Please change to the new $'."GLOBALS['phpgw']hooks->count()".'. For now this will act as a wrapper<br>';
			return $GLOBALS['phpgw']->hooks->count($location);
		}

		/* Wrapper to the session->appsession() */
		function appsession($data = '##NOTHING##')
		{
			$this->debug_info[] = '$phpgw->common->appsession() is a depreciated function'
				. ' - use $phpgw->session->appsession() instead';

			return $GLOBALS['phpgw']->session->appsession('default','',$data);
		}

		/*!
		@function show_date
		@abstract show current date
		@param $t time - optional can be pulled from user preferences
		@param $format - optional can be pulled from user prefernces
		*/
		function show_date($t = '', $format = '')
		{
			if(!is_object($GLOBALS['phpgw']->datetime))
			{
				$GLOBALS['phpgw']->datetime = createobject('phpgwapi.datetime');
			}
			
			if (!$t || intval($t) <= 0)
			{
				$t = $GLOBALS['phpgw']->datetime->gmtnow;
			}

			//  + (date('I') == 1?3600:0)
			$t += $GLOBALS['phpgw']->datetime->tz_offset;
			
			if (! $format)
			{
				$format = $GLOBALS['phpgw_info']['user']['preferences']['common']['dateformat'] . ' - ';
				if ($GLOBALS['phpgw_info']['user']['preferences']['common']['timeformat'] == '12')
				{
					$format .= 'h:i:s a';
				}
				else
				{
					$format .= 'H:i:s';
				}
			}
			return date($format,$t);
		}

		/*!
		@function dateformatorder
		@abstract 
		@param $yearstr year - string
		@param $monthstr month - string
		@param $day day - string
		@param $add_seperator boolean defaults to false
		*/
		function dateformatorder($yearstr,$monthstr,$daystr,$add_seperator = False)
		{
			$dateformat = strtolower($GLOBALS['phpgw_info']['user']['preferences']['common']['dateformat']);
			$sep = substr($GLOBALS['phpgw_info']['user']['preferences']['common']['dateformat'],1,1);

			$dlarr[strpos($dateformat,'y')] = $yearstr;
			$dlarr[strpos($dateformat,'m')] = $monthstr;
			$dlarr[strpos($dateformat,'d')] = $daystr;
			ksort($dlarr);

			if ($add_seperator)
			{
				return (implode($sep,$dlarr));
			}
			else
			{
				return (implode(' ',$dlarr));
			}
		} 

		/*!
		@function formattime
		@abstract format the time takes settings from user preferences
		@param $hour hour
		@param $min minutes
		@param $sec defaults to ''
		*/
		function formattime($hour,$min,$sec='')
		{
			$h12 = $hour;
			if ($GLOBALS['phpgw_info']['user']['preferences']['common']['timeformat'] == '12')
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

			if ($sec)
			{
				$sec = ":$sec";
			}

			return "$h12:$min$sec$ampm";
		}

		// This is not the best place for it, but it needs to be shared bewteen Aeromail and SM
		/*!
		@function get_email_passwd_ex
		@abstract uses code in /email class msg to obtain the appropriate password for email
		@param  (none - it will abtain the info it needs on its own)
		*/
		/*
		function get_email_passwd_ex()
		{
			// ----  Create the email Message Class  if needed  -----
			if (is_object($GLOBALS['phpgw']->msg))
			{
				$do_free_me = False;
			}
			else
			{
				$GLOBALS['phpgw']->msg = CreateObject('email.mail_msg');
				$do_free_me = True;
			}
			// use the Msg class to obtain the appropriate password
			$tmp_prefs = $GLOBALS['phpgw']->preferences->read();
			if (!isset($tmp_prefs['email']['passwd']))
			{
				$email_passwd = $GLOBALS['phpgw_info']['user']['passwd'];
			}
			else
			{
				$email_passwd = $GLOBALS['phpgw']->msg->decrypt_email_passwd($tmp_prefs['email']['passwd']);
			}
			// cleanup and return
			if ($do_free_me)
			{
				unset ($GLOBALS['phpgw']->msg);
			}
			return $email_passwd;
		}
		*/

		// This is not the best place for it, but it needs to be shared bewteen Aeromail and SM
		/*!
		@function create_emailpreferences
		@abstract create email preferences
		@discussion This is not the best place for it, but it needs to be shared between Aeromail and SM
		@param $prefs
		@param $account_id -optional defaults to : phpgw_info['user']['account_id']
		*/
		function create_emailpreferences($prefs='',$accountid='')
		{
			return $GLOBALS['phpgw']->preferences->create_email_preferences($accountid);
			// ----  Create the email Message Class  if needed  -----
			if (is_object($GLOBALS['phpgw']->msg))
			{
				$do_free_me = False;
			}
			else
			{
				$GLOBALS['phpgw']->msg = CreateObject('email.mail_msg');
				$do_free_me = True;
			}

			// this sets the preferences into the phpgw_info structure
			$GLOBALS['phpgw']->msg->create_email_preferences();

			// cleanup and return
			if ($do_free_me)
			{
				unset ($GLOBALS['phpgw']->msg);
			}
		}

		/*
		function create_emailpreferences($prefs,$accountid='')
		{
			$account_id = get_account_id($accountid);
			
			// NEW EMAIL PASSWD METHOD (shared between SM and aeromail)
			$prefs['email']['passwd'] = $this->get_email_passwd_ex();
			
			// Add default preferences info
			if (!isset($prefs['email']['userid']))
			{
				if ($GLOBALS['phpgw_info']['server']['mail_login_type'] == 'vmailmgr')
				{
					$prefs['email']['userid'] = $GLOBALS['phpgw']->accounts->id2name($account_id)
						. '@' . $GLOBALS['phpgw_info']['server']['mail_suffix'];
				}
				else
				{
					$prefs['email']['userid'] = $GLOBALS['phpgw']->accounts->id2name($account_id);
				}
			}
			// Set Server Mail Type if not defined
			if (empty($GLOBALS['phpgw_info']['server']['mail_server_type']))
			{
				$GLOBALS['phpgw_info']['server']['mail_server_type'] = 'imap';
			}
			
			// OLD EMAIL PASSWD METHOD
			if (!isset($prefs['email']['passwd']))
			{
				$prefs['email']['passwd'] = $GLOBALS['phpgw_info']['user']['passwd'];
			}
			else
			{
				$prefs['email']['passwd'] = $this->decrypt($prefs['email']['passwd']);
			}
			// NEW EMAIL PASSWD METHOD Located at the begining of this function
			
			if (!isset($prefs['email']['address']))
			{
				$prefs['email']['address'] = $GLOBALS['phpgw']->accounts->id2name($account_id)
					. '@' . $GLOBALS['phpgw_info']['server']['mail_suffix'];
			}
			if (!isset($prefs['email']['mail_server']))
			{
				$prefs['email']['mail_server'] = $GLOBALS['phpgw_info']['server']['mail_server'];
			}
			if (!isset($prefs['email']['mail_server_type']))
			{
				$prefs['email']['mail_server_type'] = $GLOBALS['phpgw_info']['server']['mail_server_type'];
			}
			if (!isset($prefs['email']['imap_server_type']))
			{
				$prefs['email']['imap_server_type'] = $GLOBALS['phpgw_info']['server']['imap_server_type'];
			}
			// These sets the mail_port server variable
			if ($prefs['email']['mail_server_type']=='imap')
			{
				$prefs['email']['mail_port'] = '143';
			}
			elseif ($prefs['email']['mail_server_type']=='pop3')
			{
				$prefs['email']['mail_port'] = '110';
			}
 			elseif ($prefs['email']['mail_server_type']=='imaps')
 			{
 				$prefs['email']['mail_port'] = '993';
 			}
 			elseif ($prefs['email']['mail_server_type']=='pop3s')
 			{
 				$prefs['email']['mail_port'] = '995';
 			}
			// This is going to be used to switch to the nntp class
			if (isset($GLOBALS['phpgw_info']['flags']['newsmode']) &&
				$GLOBALS['phpgw_info']['flags']['newsmode'])
			{
				$prefs['email']['mail_server_type'] = 'nntp';
			}
			// DEBUG
			//echo "<br>prefs['email']['passwd']: " .$prefs['email']['passwd'] .'<br>';
			return $prefs;
		}
		*/

		// This will be moved into the applications area.
		/*!
		@function check_code
		@abstract ?
		@discussion This will be moved into the applications area
		*/
		function check_code($code)
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
						. lang('Error deleting x x directory',lang('users'),' '.lang('private').' ') 
						. ',<br>' . lang('Please x by hand',lang('delete')) . '<br><br>'
						. lang('To correct this error for the future you will need to properly set the')
						. '<br>' . lang('permissions to the files/users directory')
						. '<br>' . lang('On *nix systems please type: x','chmod 770 '
						. $GLOBALS['phpgw_info']['server']['files_dir'] . '/users/'); 
					break;
				case 35:	$s .= lang('Account has been updated') . '<p>'
						. lang('Error renaming x x directory',lang('users'),
						' '.lang('private').' ') 
						. ',<br>' . lang('Please x by hand',
						lang('rename')) . '<br><br>'
						. lang('To correct this error for the future you will need to properly set the')
						. '<br>' . lang('permissions to the files/users directory')
						. '<br>' . lang('On *nix systems please type: x','chmod 770 '
						. $GLOBALS['phpgw_info']['server']['files_dir'] . '/users/'); 
					break;
				case 36:	$s .= lang('Account has been created') . '<p>'
						. lang('Error creating x x directory',lang('users'),
						' '.lang('private').' ') 
						. ',<br>' . lang('Please x by hand',
						lang('create')) . '<br><br>'
						. lang('To correct this error for the future you will need to properly set the')
						. '<br>' . lang('permissions to the files/users directory')
						. '<br>' . lang('On *nix systems please type: x','chmod 770 '
						. $GLOBALS['phpgw_info']['server']['files_dir'] . '/users/'); 
					break;
				case 37:	$s .= lang('Group has been added') . '<p>'
						. lang('Error creating x x directory',lang('groups'),' ')
						. ',<br>' . lang('Please x by hand',
						lang('create')) . '<br><br>'
						. lang('To correct this error for the future you will need to properly set the')
						. '<br>' . lang('permissions to the files/users directory')
						. '<br>' . lang('On *nix systems please type: x','chmod 770 '
						. $GLOBALS['phpgw_info']['server']['files_dir'] . '/groups/'); 
					break;
				case 38:	$s .= lang('Group has been deleted') . '<p>'
						. lang('Error deleting x x directory',lang('groups'),' ')
						. ',<br>' . lang('Please x by hand',
						lang('delete')) . '<br><br>'
						. lang('To correct this error for the future you will need to properly set the')
						. '<br>' . lang('permissions to the files/users directory')
						. '<br>' . lang('On *nix systems please type: x','chmod 770 '
						. $GLOBALS['phpgw_info']['server']['files_dir'] . '/groups/'); 
					break;
				case 39:	$s .= lang('Group has been updated') . '<p>'
						. lang('Error renaming x x directory',lang('groups'),' ')
						. ',<br>' . lang('Please x by hand',
						lang('rename')) . '<br><br>'
						. lang('To correct this error for the future you will need to properly set the')
						. '<br>' . lang('permissions to the files/users directory')
						. '<br>' . lang('On *nix systems please type: x','chmod 770 '
						. $GLOBALS['phpgw_info']['server']['files_dir'] . '/groups/'); 
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
		/*!
		@function phpgw_error
		@abstract process error message
		@param $error error 
		@param $line line
		@param $file file
		*/
		function phpgw_error($error,$line = '', $file = '') 
		{
			echo '<p><b>phpGroupWare internal error:</b><p>'.$error;
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

		/*!
		@function create_phpcode_from_array
		@abstract create phpcode from array
		@param $array - array
		*/
		function create_phpcode_from_array($array)
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
										$s .= '$phpgw_info["' . $key . '"]["' . $key2 . '"]["' . $key3 . '"]["' .$key4 . '"]="' . $val4 . '";';
										$s .= "\n";
									}
								}
								else
								{
									$s .= '$phpgw_info["' . $key . '"]["' . $key2 . '"]["' . $key3 . '"]="' . $val3 . '";';
									$s .= "\n";
								}
							}
						}
						else
						{
							$s .= '$phpgw_info["' . $key .'"]["' . $key2 . '"]="' . $val2 . '";';
							$s .= "\n";
						}
					}
				}
				else
				{
					$s .= '$phpgw_info["' . $key . '"]="' . $val . '";';
					$s .= "\n";
				}
			}
			return $s;
		}

		// This will return the full phpgw_info array, used for debugging
		/*!
		@function debug_list_array_contents
		@abstract return the full phpgw_info array for debugging
		@param array - array
		*/
		function debug_list_array_contents($array)
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
		/*!
		@function debug_list_core_functions
		@abstract return a list of functionsin the API
		*/
		function debug_list_core_functions()
		{
			echo '<br><b>core functions</b><br>';
			echo '<pre>';
			chdir(PHPGW_INCLUDE_ROOT . '/phpgwapi');
			system("grep -r '^[ \t]*function' *");
			echo '</pre>';
		}

		// This will return a value for the next id an app/class may need to insert values into ldap.
		/*!
		@function next_id
		@abstract return the next higher value for an integer, and increment it in the db.
		*/
		function next_id($appname,$min=0,$max=0)
		{
			if (!$appname)
			{
				return -1;
			}

			$GLOBALS['phpgw']->db->query("SELECT id FROM phpgw_nextid WHERE appname='".$appname."'",__LINE__,__FILE__);
			while( $GLOBALS['phpgw']->db->next_record() )
			{
				$id = $GLOBALS['phpgw']->db->f('id');
			}

			if (empty($id) || !$id)
			{
				$id = 1;
				$GLOBALS['phpgw']->db->query("INSERT INTO phpgw_nextid (appname,id) VALUES ('".$appname."',".$id.")",__LINE__,__FILE__);
			}
			elseif($id<$min)
			{
				$id = $min;
				$GLOBALS['phpgw']->db->query("UPDATE phpgw_nextid SET id=".$id." WHERE appname='".$appname."'",__LINE__,__FILE__);
			}
			elseif ($max && ($id > $max))
			{
				return False;
			}
			else
			{
				$id = $id + 1;
				$GLOBALS['phpgw']->db->query("UPDATE phpgw_nextid SET id=".$id." WHERE appname='".$appname."'",__LINE__,__FILE__);
			}

			return intval($id);
		}

		// This will return a value for the last id entered, which an app may need to check
		// values for ldap.
		/*!
		@function last_id
		@abstract return the current id in the next_id table for a particular app/class.
		*/
		function last_id($appname,$min=0,$max=0)
		{
			if (!$appname)
			{
				return -1;
			}

			$GLOBALS['phpgw']->db->query("SELECT id FROM phpgw_nextid WHERE appname='".$appname."'",__LINE__,__FILE__);
			while( $GLOBALS['phpgw']->db->next_record() )
			{
				$id = $GLOBALS['phpgw']->db->f('id');
			}

			if (empty($id) || !$id)
			{
				if($min)
				{
					$id = $min;
				}
				else
				{
					$id = 1;
				}
				$GLOBALS['phpgw']->db->query("INSERT INTO phpgw_nextid (appname,id) VALUES ('".$appname."',".$id.")",__LINE__,__FILE__);
			}
			elseif($id<$min)
			{
				$id = $min;
				$GLOBALS['phpgw']->db->query("UPDATE phpgw_nextid SET id=".$id." WHERE appname='".$appname."'",__LINE__,__FILE__);
			}
			elseif ($max && ($id > $max))
			{
				return False;
			}
			else
			{
				return intval($id);
			}
		}
	}//end common class

