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

	$GLOBALS['egw']->redirect_link('/index.php',array(
		'menuaction' => 'infolog.uiinfolog.index',
		'filter'     => $GLOBALS['egw_info']['user']['preferences']['infolog']['defaultFilter']
	));
	$GLOBALS['egw']->common->egw_exit();
?>
