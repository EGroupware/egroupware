<?php
  /**************************************************************************\
  * phpGroupWare - Setup                                                     *
  * http://www.phpgroupware.org                                              *
  * --------------------------------------------                             *
  * This file written by Joseph Engo<jengo@phpgroupware.org>                 *
  *  and Dan Kuykendall<seek3r@phpgroupware.org>                             *
  *  and Mark Peters<skeeter@phpgroupware.org>                               *
  *  and Miles Lott<milosch@phpgroupware.org>                                *
  * --------------------------------------------                             *
  *  This program is free software; you can redistribute it and/or modify it *
  *  under the terms of the GNU General Public License as published by the   *
  *  Free Software Foundation; either version 2 of the License, or (at your  *
  *  option) any later version.                                              *
  \**************************************************************************/

  /* $Id$ */

	/* ######## Start security check ########## */
	$d1 = strtolower(substr(@$GLOBALS['phpgw_info']['server']['api_inc'],0,3));
	$d2 = strtolower(substr(@$GLOBALS['phpgw_info']['server']['server_root'],0,3));
	$d3 = strtolower(substr(@$GLOBALS['phpgw_info']['server']['app_inc'],0,3));
	if($d1 == 'htt' || $d1 == 'ftp' || $d2 == 'htt' || $d2 == 'ftp' || $d3 == 'htt' || $d3 == 'ftp')
	{
		echo 'Failed attempt to break in via an old Security Hole!<br>';
		exit;
	}
	unset($d1);unset($d2);unset($d3);
	/* ######## End security check ########## */

	if(file_exists('../header.inc.php'))
	{
		include('../header.inc.php');
	}

	if (!function_exists('version_compare'))//version_compare() is only available in PHP4.1+
	{
		echo 'phpGroupWare now requires PHP 4.1 or greater.<br>';
		echo 'Please contact your System Administrator';
		exit;
	}
										

	
	/*  If we included the header.inc.php, but it is somehow broken, cover ourselves... */
	if(!defined('PHPGW_SERVER_ROOT') && !defined('PHPGW_INCLUDE_ROOT'))
	{
		define('PHPGW_SERVER_ROOT','..');
		define('PHPGW_INCLUDE_ROOT','..');
	}

	include(PHPGW_INCLUDE_ROOT . '/phpgwapi/inc/common_functions.inc.php');

	define('SEP',filesystem_separator());

	/*!
	 @function lang
	 @abstract function to handle multilanguage support
	*/
	function lang($key,$m1='',$m2='',$m3='',$m4='',$m5='',$m6='',$m7='',$m8='',$m9='',$m10='')
	{
		if(is_array($m1))
		{
			$vars = $m1;
		}
		else
		{
			$vars = array($m1,$m2,$m3,$m4,$m5,$m6,$m7,$m8,$m9,$m10);
		}
		$value = $GLOBALS['phpgw_setup']->translation->translate("$key", $vars );
		return $value;
	}

	/*!
	@function get_langs
	@abstract	returns array of languages we support, with enabled set
				to True if the lang file exists
	*/
	function get_langs()
	{
		$f = fopen('./lang/languages','rb');
		while($line = fgets($f,200))
		{
			list($x,$y) = split("\t",$line);
			$languages[$x]['lang']  = trim($x);
			$languages[$x]['descr'] = trim($y);
			$languages[$x]['available'] = False;
		}
		fclose($f);

		$d = dir('./lang');
		while($entry=$d->read())
		{
			if(ereg('^phpgw_',$entry))
			{
				$z = substr($entry,6,2);
				$languages[$z]['available'] = True;
			}
		}
		$d->close();

		//print_r($languages);
		return $languages;
	}

	function lang_select($onChange=False)
	{
		$ConfigLang = get_var('ConfigLang',Array('POST','COOKIE'));

		$select = '<select name="ConfigLang"'.($onChange ? ' onChange="this.form.submit();"' : '').'>' . "\n";
		$languages = get_langs();
		while(list($null,$data) = each($languages))
		{
			if($data['available'] && !empty($data['lang']))
			{
				$selected = '';
				$short = substr($data['lang'],0,2);
				if ($short == $ConfigLang || empty($ConfigLang) && $short == substr($_SERVER['HTTP_ACCEPT_LANGUAGE'],0,2))
				{
					$selected = ' selected';
				}
				$select .= '<option value="' . $data['lang'] . '"' . $selected . '>' . $data['descr'] . '</option>' . "\n";
			}
		}
		$select .= '</select>' . "\n";

		return $select;
	}

	if(file_exists(PHPGW_SERVER_ROOT.'/phpgwapi/setup/setup.inc.php'))
	{
		include(PHPGW_SERVER_ROOT.'/phpgwapi/setup/setup.inc.php'); /* To set the current core version */
		/* This will change to just use setup_info */
		$GLOBALS['phpgw_info']['server']['versions']['current_header'] = $setup_info['phpgwapi']['versions']['current_header'];
	}
	else
	{
		$GLOBALS['phpgw_info']['server']['versions']['phpgwapi'] = 'Undetected';
	}

	$GLOBALS['phpgw_info']['server']['app_images'] = 'templates/default/images';

	$GLOBALS['phpgw_setup'] = CreateObject('phpgwapi.setup',True,True);
?>
