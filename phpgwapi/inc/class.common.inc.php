<?php
  /**************************************************************************\
  * phpGroupWare API - Commononly used functions                             *
  * This file written by Dan Kuykendall <seek3r@phpgroupware.org>            *
  * and Joseph Engo <jengo@phpgroupware.org>                                 *
  * and Mark Peters <skeeter@phpgroupware.org>                               *
  * Commononly used functions by phpGroupWare developers                     *
  * Copyright (C) 2000, 2001 Dan Kuykendall                                  *
  * -------------------------------------------------------------------------*
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
		function cmp_version($str1,$str2)
		{
			ereg("([0-9]+)\.([0-9]+)\.([0-9]+)[a-zA-Z]*([0-9]*)",$str1,$regs);
			ereg("([0-9]+)\.([0-9]+)\.([0-9]+)[a-zA-Z]*([0-9]*)",$str2,$regs2);
			//echo "$regs[0] - $regs2[0]";
			for($i=1;$i<5;$i++)
			{
				//echo "<br>$regs[$i] - $regs2[$i]";
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

		/*!
		@function cmp_version_long
		@abstract Compares two Version strings and return 1 if str2 is newest (bigger version number) than str1
		@discussion This function checks all fields. cmp_version() checks release version only.
		@param $str1
		@param $str2
		*/
		function cmp_version_long($str1,$str2)
		{
			ereg("([0-9]+)\.([0-9]+)\.([0-9]+)[a-zA-Z]*([0-9]*)\.([0-9]*)",$str1,$regs);
			ereg("([0-9]+)\.([0-9]+)\.([0-9]+)[a-zA-Z]*([0-9]*)\.([0-9]*)",$str2,$regs2);
			//echo "$regs[0] - $regs2[0]";
			for($i=1;$i<6;$i++)
			{
				//echo "<br>$regs[$i] - $regs2[$i]";
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
			$GLOBALS['phpgw']->db->query('select distinct lang from lang');
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

		// This function is used if the developer wants to stop a running app in the middle of execution
		// We may need to do some clean up before hand
		/*!
		@function phpgw_exit
		@abstract function to stop running an app
		@discussion used to stop running an app in the middle of execution <br>
		There may need to be some cleanup before hand
		@param $call_footer boolean value to if true then call footer else exit
		*/
		function phpgw_exit($call_footer = False)
		{
			if ($call_footer)
			{
				$this->phpgw_footer();
			}
			$GLOBALS['phpgw']->db->disconnect();
			exit;
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

			$html_error = '<table border="0" width="100%"><tr><td align="right"><b>' . lang($text)
							. '</b>: </td><td align="left">' . $errors[0] . '</td></tr>';
			for ($i=1; $i<count($errors); $i++)
			{
				$html_error .= '<tr><td>&nbsp;</td><td align="left">' . $errors[$i] . '</td></tr>';
			}
			return $html_error . '</table>';
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
						return '&lt;' . $lid . '&gt; ' . $a[0];
						break;
					case 2:
						return '&lt;' . $lid . '&gt; ' . implode(', ',$a);
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

			if (is_dir ($appdir))
			{
				return $appdir;
			}
			elseif (is_dir ($appdir_default))
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
 
			if (is_dir ($incdir))
			{
				return $incdir;
			}
			elseif (is_dir ($incdir_default))
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
			$dh = opendir(PHPGW_SERVER_ROOT . '/phpgwapi/themes');
			while ($file = readdir($dh))
			{
				if (eregi("\.theme$", $file))
				{
					$list[] = substr($file,0,strpos($file,'.'));
				}
			}
			//$dh->close();
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
		function get_tpl_dir($appname = '')
		{
			if (! $appname)
			{
				$appname = $GLOBALS['phpgw_info']['flags']['currentapp'];
			}
			if ($appname == 'home' || $appname == 'logout' || $appname == 'login')
			{
				$appname = 'phpgwapi';
			}

			if (!isset($GLOBALS['phpgw_info']['server']['template_set']) && isset($GLOBALS['phpgw_info']['user']['preferences']['common']['template_set']))
			{
				$GLOBALS['phpgw_info']['server']['template_set'] = $GLOBALS['phpgw_info']['user']['preferences']['common']['template_set'];
			}

			// Setting this for display of template choices in user preferences
			if ($GLOBALS['phpgw_info']['server']['template_set'] == 'user_choice')
			{
				$GLOBALS['phpgw_info']['server']['usrtplchoice'] = 'user_choice';
			}

			if (($GLOBALS['phpgw_info']['server']['template_set'] == 'user_choice' ||
				!isset($GLOBALS['phpgw_info']['server']['template_set'])) &&
				isset($GLOBALS['phpgw_info']['user']['preferences']['common']['template_set']))
			{
				$GLOBALS['phpgw_info']['server']['template_set'] = $GLOBALS['phpgw_info']['user']['preferences']['common']['template_set'];
			}
			elseif ($GLOBALS['phpgw_info']['server']['template_set'] == 'user_choice' ||
				!isset($GLOBALS['phpgw_info']['server']['template_set']))
			{
				$GLOBALS['phpgw_info']['server']['template_set'] = 'default';
			}

			$tpldir         = PHPGW_SERVER_ROOT . '/' . $appname . '/templates/' . $GLOBALS['phpgw_info']['server']['template_set'];
			$tpldir_default = PHPGW_SERVER_ROOT . '/' . $appname . '/templates/default';

			if (is_dir($tpldir))
			{
				return $tpldir;
			}
			elseif (is_dir($tpldir_default))
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
		function get_image_dir($appname = '')
		{
			if ($appname == '')
			{
				$appname = $GLOBALS['phpgw_info']['flags']['currentapp'];
			}
			if (empty($phpgw_info['server']['template_set']))
			{
				$phpgw_info['server']['template_set'] = 'default';
			}

			$imagedir            = PHPGW_SERVER_ROOT . '/' . $appname . '/templates/'
				. $GLOBALS['phpgw_info']['server']['template_set'] . '/images';
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
		function get_image_path($appname = '')
		{
			if ($appname == '')
			{
				$appname = $GLOBALS['phpgw_info']['flags']['currentapp'];
			}

			if (empty($GLOBALS['phpgw_info']['server']['template_set']))
			{
				$GLOBALS['phpgw_info']['server']['template_set'] = 'default';
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

			if (!is_array($this->found_files[$appname]['images']))
			{
				$imagedir_olddefault = '/'.$appname.'/images';
				$imagedir_default    = '/'.$appname.'/templates/default/images';
				$imagedir = '/'.$appname.'/templates/'.$GLOBALS['phpgw_info']['server']['template_set'].'/images';

				if (@is_dir(PHPGW_INCLUDE_ROOT.$imagedir_olddefault))
				{
					$d = dir(PHPGW_INCLUDE_ROOT.$imagedir_olddefault);
					while (false !== ($entry = $d->read()))
					{
						if ($entry != '.' && $entry != '..')
						{
							$this->found_files[$appname]['images'][$entry] = $imagedir_olddefault;
						}
					}
					$d->close();
				}
	
				if (@is_dir(PHPGW_INCLUDE_ROOT.$imagedir_default))
				{
					$d = dir(PHPGW_INCLUDE_ROOT.$imagedir_default);
					while (false !== ($entry = $d->read()))
					{
						if ($entry != '.' && $entry != '..')
						{
							$this->found_files[$appname]['images'][$entry] = $imagedir_default;
						}
					}
					$d->close();
				}
	
				if (@is_dir(PHPGW_INCLUDE_ROOT.$imagedir))
				{
					$d = dir(PHPGW_INCLUDE_ROOT.$imagedir);
					while (false !== ($entry = $d->read()))
					{
						if ($entry != '.' && $entry != '..')
						{
							$this->found_files[$appname]['images'][$entry] = $imagedir;
						}
					}
					$d->close();
				}
			}

			if(isset($this->found_files[$appname]['images'][$image]))
			{
				$imgfile = $GLOBALS['phpgw_info']['server']['webserver_url'].$this->found_files[$appname]['images'][$image].'/'.$image;
			}
			else
			{
				$imgfile = '';
			}
			return $imgfile;
		}

		function image($appname,$image='')
		{
			if(is_array($image))
			{
				$i = 0;
				$image_found = $this->find_image($appname,$image[$i]);
				$c_image = count($image);
				while($image_found == '' && $i<$c_image)
				{
					$image_found = $this->find_image($appname,$image[$i]);
					$i++;
				}
				return $image_found;
			}
			elseif($image != '')
			{
				return $this->find_image($appname,$image);
			}
			else
			{
				return '';
			}
		}
		/*!
		@function navbar
		@abstract none yet
		@discussion *someone wanna add some detail here*
		*/
		function navbar()
		{
			$GLOBALS['phpgw_info']['navbar']['home']['title'] = 'Home';
			$GLOBALS['phpgw_info']['navbar']['home']['url']   = $GLOBALS['phpgw']->link('/index.php');
			$GLOBALS['phpgw_info']['navbar']['home']['icon']  = $this->image('phpgwapi','home.gif');

			reset($GLOBALS['phpgw_info']['user']['apps']);
			while ($permission = each($GLOBALS['phpgw_info']['user']['apps']))
			{
				if (is_long($permission[0]))
				{
					continue;
				}

				if ($GLOBALS['phpgw_info']['apps'][$permission[0]]['status'] != 2 && $GLOBALS['phpgw_info']['apps'][$permission[0]]['status'] != 3)
				{
					$GLOBALS['phpgw_info']['navbar'][$permission[0]]['title'] = $GLOBALS['phpgw_info']['apps'][$permission[0]]['title'];
					$GLOBALS['phpgw_info']['navbar'][$permission[0]]['url']   = $GLOBALS['phpgw']->link('/' . $permission[0] . '/index.php');
					$GLOBALS['phpgw_info']['navbar'][$permission[0]]['name']  = $permission[0];

					$GLOBALS['phpgw_info']['navbar'][$permission[0]]['icon']  = $this->image($permission[0],'navbar.gif');
					if($GLOBALS['phpgw_info']['navbar'][$permission[0]]['icon'] == '')
					{
						$GLOBALS['phpgw_info']['navbar'][$permission[0]]['icon']  = $this->image('phpgwapi','nonav.gif');
					}
				}
			}
			$GLOBALS['phpgw_info']['navbar']['preferences']['title'] = 'preferences';
			$GLOBALS['phpgw_info']['navbar']['preferences']['url']   = $GLOBALS['phpgw']->link('/preferences/index.php');
			$GLOBALS['phpgw_info']['navbar']['preferences']['icon']  = $this->image('preferences','navbar.gif');

			if ($GLOBALS['phpgw_info']['flags']['currentapp'] == 'home' || $GLOBALS['phpgw_info']['flags']['currentapp'] == 'preferences' || $GLOBALS['phpgw_info']['flags']['currentapp'] == 'about')
			{
				$app = 'phpGroupWare';
			}
			else
			{
				$app = $GLOBALS['phpgw_info']['flags']['currentapp'];
			}

			// We handle this here becuase its special
			$GLOBALS['phpgw_info']['navbar']['about']['title'] = lang('About x',$app);

			$GLOBALS['phpgw_info']['navbar']['about']['url']   = $GLOBALS['phpgw']->link('/about.php','app='.$app);
			$GLOBALS['phpgw_info']['navbar']['about']['icon']  = $this->image('phpgwapi','about.gif');

			$GLOBALS['phpgw_info']['navbar']['logout']['title'] = 'Logout';
			$GLOBALS['phpgw_info']['navbar']['logout']['url']   = $GLOBALS['phpgw']->link('/logout.php');
			$GLOBALS['phpgw_info']['navbar']['logout']['icon']  = $this->image('phpgwapi','logout.gif');
		}

		/*!
		@function app_header
		@abstract load header.inc.php for an application
		*/
		function app_header()
		{
			if (file_exists(PHPGW_APP_INC . '/header.inc.php'))
			{
				include(PHPGW_APP_INC . '/header.inc.php');
			}
		}
		/*!
		@function phpgw_header
		@abstract load the phpgw header
		*/
		function phpgw_header()
		{
			include(PHPGW_INCLUDE_ROOT . '/phpgwapi/templates/' . $GLOBALS['phpgw_info']['server']['template_set']
				. '/head.inc.php');
			$this->navbar(False);
			include(PHPGW_INCLUDE_ROOT . '/phpgwapi/templates/' . $GLOBALS['phpgw_info']['server']['template_set']
				. '/navbar.inc.php');
			if (!@$GLOBALS['phpgw_info']['flags']['nonavbar'] && !@$GLOBALS['phpgw_info']['flags']['navbar_target'])
			{
				echo parse_navbar();
			}
		}

		function phpgw_footer()
		{
			global $HTMLCOMPLIANT;

			if (!isset($GLOBALS['phpgw_info']['flags']['nofooter']) || !$GLOBALS['phpgw_info']['flags']['nofooter'])
			{
				include(PHPGW_API_INC . '/footer.inc.php');
			}
 
			/* Clean up mcrypt */
			if (@is_object($GLOBALS['phpgw']->crypto))
			{
				$GLOBALS['phpgw']->crypto->cleanup();
				unset($GLOBALS['phpgw']->crypto);
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
		@function encrypt_password
		@abstract encrypt password
		@abstract uses the encryption type set in setup and calls the appropriate encryption functions
		@param $password password to encrypt
		*/
		function encrypt_password($password)
		{
			if ($GLOBALS['phpgw_info']['server']['ldap_encryption_type'] == 'DES')
			{
				$salt       = $this->randomstring(2);
				$e_password = $this->des_cryptpasswd($password, $salt);
			}
			if ($GLOBALS['phpgw_info']['server']['ldap_encryption_type'] == 'MD5')
			{
				$salt       = $this->randomstring(8);
				$e_password = $this->md5_cryptpasswd($password, $salt);
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
		@abstract hooking function which allows applications to 'hook' into each other
		@discussion Someone flesh this out please
		*/
		// Note: $no_permission_check should *ONLY* be used when it *HAS* to be. (jengo)
		function hook($location, $order = '', $no_permission_check = False)
		{
			if ($order == '')
			{
				settype($order,'array');
				$order[] = $GLOBALS['phpgw_info']['flags']['currentapp'];
			}

			/* First include the ordered apps hook file */
			reset ($order);
			while (list(,$appname) = each($order))
			{
				$f = PHPGW_SERVER_ROOT . '/' . $appname . '/inc/hook_' . $location . '.inc.php';
				if (file_exists($f) &&
					( $GLOBALS['phpgw_info']['user']['apps'][$appname] || (($no_permission_check || $appname == 'preferences') && $appname)) )
				{
					include($f);
				}

				$completed_hooks[$appname] = True;
			}

			/* Then add the rest */

			if ($no_permission_check)
			{
				reset($GLOBALS['phpgw_info']['apps']);
				while (list(,$p) = each($GLOBALS['phpgw_info']['apps']))
				{
					$appname = $p['name'];
					if (! isset($completed_hooks[$appname]) || $completed_hooks[$appname] != True)
					{
						$f = PHPGW_SERVER_ROOT . '/' . $appname . '/inc/hook_' . $location . '.inc.php';
						if (file_exists($f))
						{
							include($f);
						}
					}
				}
			}
			else
			{
				reset ($GLOBALS['phpgw_info']['user']['apps']);
				while (list(,$p) = each($GLOBALS['phpgw_info']['user']['apps']))
				{
					$appname = $p['name'];
					if (! isset($completed_hooks[$appname]) || $completed_hooks[$appname] != True)
					{
						$f = PHPGW_SERVER_ROOT . '/' . $appname . '/inc/hook_' . $location . '.inc.php';
						if (file_exists($f))
						{
							include($f);
						}
					}
				}
			}
		}

		/*!
		@function hook_single
		@abstract call the hooks for a single application
		@param $location hook location - required
		@param $appname application name - optional
		*/
		// Note: $no_permission_check should *ONLY* be used when it *HAS* to be. (jengo)
		function hook_single($location, $appname = '', $no_permission_check = False)
		{
			if (! $appname)
			{
				$appname = $GLOBALS['phpgw_info']['flags']['currentapp'];
			}
			$SEP = filesystem_separator();

			/* First include the ordered apps hook file */
			$f = PHPGW_SERVER_ROOT . $SEP . $appname . $SEP . 'inc' . $SEP . 'hook_' . $location . '.inc.php';
			if (file_exists($f) &&
				( $GLOBALS['phpgw_info']['user']['apps'][$appname] || (($no_permission_check || $location == 'config' || $appname == 'phpgwapi') && $appname)) )
			{
				include($f);
				return True;
			}
			else
			{
				return False;
			}
		}

		/*!
		@function hook_count
		@abstract loop through the applications and count the hooks
		*/
		function hook_count($location)
		{
			$count = 0;
			reset($GLOBALS['phpgw_info']['user']['apps']);
			$SEP = filesystem_separator();
			while ($permission = each($GLOBALS['phpgw_info']['user']['apps']))
			{
				$f = PHPGW_SERVER_ROOT . $SEP . $permission[0] . $SEP . 'inc' . $SEP . 'hook_' . $location . '.inc.php';

				if (file_exists($f))
				{
					++$count;
				}
			}
			return $count;
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
			if (! $t)
			{
				$t = time();
			}

			$t = $t + ((60*60) * $GLOBALS['phpgw_info']['user']['preferences']['common']['tz_offset']);

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
			// This is going to be used to switch to the nntp class
			if (isset($phpgw_info['flags']['newsmode']) &&
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
