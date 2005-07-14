<?php
/**************************************************************************\
* eGroupWare - Info Log                                                    *
* http://www.egroupware.org                                                *
* Written by Ralf Becker <RalfBecker@outdoor-training.de>                  *
* originaly based on todo written by Joseph Engo <jengo@phpgroupware.org>  *
* --------------------------------------------                             *
*  This program is free software; you can redistribute it and/or modify it *
*  under the terms of the GNU General Public License as published by the   *
*  Free Software Foundation; either version 2 of the License, or (at your  *
*  option) any later version.                                              *
\**************************************************************************/

/* $Id$ */

$GLOBALS['egw_info']['flags'] = array(
	'currentapp'	=> 'infolog', 
	'noheader'		=> True,
	'nonavbar'		=> True
);
include('../header.inc.php');

include_once(EGW_INCLUDE_ROOT.'/infolog/setup/setup.inc.php');
if ($setup_info['infolog']['version'] != $GLOBALS['egw_info']['apps']['infolog']['version'])
{
	$GLOBALS['egw']->common->egw_header();
	parse_navbar();
	echo '<p style="text-align: center; color:red; font-weight: bold;">'.lang('Your database is NOT up to date (%1 vs. %2), please run %3setup%4 to update your database.',
		$setup_info['infolog']['version'],$GLOBALS['egw_info']['apps']['infolog']['version'],
		'<a href="../setup/">','</a>')."</p>\n";
	$GLOBALS['egw']->common->egw_exit();
}
unset($setup_info);

ExecMethod('infolog.uiinfolog.index');

$GLOBALS['egw']->common->egw_exit();
?>
