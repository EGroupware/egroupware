<?php
  /**************************************************************************\
  * eGroupWare - Setup                                                       *
  * http://www.egroupware.org                                                *
  * --------------------------------------------                             *
  * This file written by Joseph Engo<jengo@phpgroupware.org>                 *
  *  and Dan Kuykendall<seek3r@phpgroupware.org>                             *
  *  and Mark Peters<skeeter@phpgroupware.org>                               *
  *  and Miles Lott<milosch@groupwhere.org>                                  *
  * --------------------------------------------                             *
  *  This program is free software; you can redistribute it and/or modify it *
  *  under the terms of the GNU General Public License as published by the   *
  *  Free Software Foundation; either version 2 of the License, or (at your  *
  *  option) any later version.                                              *
  \**************************************************************************/

  /* $Id$ */

	error_reporting(error_reporting() & ~E_NOTICE);

	if(file_exists('../header.inc.php'))
	{
		include('../header.inc.php');
	}

	if (!function_exists('version_compare'))//version_compare() is only available in PHP4.1+
	{
		echo 'eGroupWare now requires PHP 4.1 or greater.<br>';
		echo 'Please contact your System Administrator';
		exit;
	}

	/*  If we included the header.inc.php, but it is somehow broken, cover ourselves... */
	if(!defined('EGW_SERVER_ROOT') && !defined('EGW_INCLUDE_ROOT'))
	{
		define('EGW_SERVER_ROOT','..');
		define('EGW_INCLUDE_ROOT','..');
		define('PHPGW_SERVER_ROOT','..');
		define('PHPGW_INCLUDE_ROOT','..');
	}

	include(EGW_INCLUDE_ROOT . '/phpgwapi/inc/common_functions.inc.php');

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
		while($file=$d->read())
		{
			if(preg_match('/^phpgw_([-a-z]+).lang$/i',$file,$matches))
			{
				$languages[$matches[1]]['available'] = True;
			}
		}
		$d->close();

		//print_r($languages);
		return $languages;
	}

	function lang_select($onChange=False,$ConfigLang='')
	{
		if (!$ConfigLang)
		{
			$ConfigLang = get_var('ConfigLang',Array('POST','COOKIE'));
		}
		$select = '<select name="ConfigLang"'.($onChange ? ' onChange="this.form.submit();"' : '').'>' . "\n";
		$languages = get_langs();
		usort($languages,create_function('$a,$b','return strcmp(@$a[\'descr\'],@$b[\'descr\']);'));
		foreach($languages as $data)
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

	if(file_exists(EGW_SERVER_ROOT.'/phpgwapi/setup/setup.inc.php'))
	{
		include(EGW_SERVER_ROOT.'/phpgwapi/setup/setup.inc.php'); /* To set the current core version */
		/* This will change to just use setup_info */
		$GLOBALS['egw_info']['server']['versions']['current_header'] = $setup_info['phpgwapi']['versions']['current_header'];
	}
	else
	{
		$GLOBALS['egw_info']['server']['versions']['phpgwapi'] = 'Undetected';
	}

	$GLOBALS['egw_info']['server']['app_images'] = 'templates/default/images';

	$GLOBALS['egw_setup'] = CreateObject('setup.setup',True,True);
	$GLOBALS['phpgw_setup'] =& $GLOBALS['egw_setup'];
