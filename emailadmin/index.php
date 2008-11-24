<?php
	/**************************************************************************\
	* EGroupWare - EMailAdmin                                                  *
	* http://www.egroupware.org                                                *
	* Written by Lars Kneschke [lkneschke@egroupware.org]                      *
	* -----------------------------------------------                          *
	*  This program is free software; you can redistribute it and/or modify it *
	*  under the terms of the GNU General Public License as published by the   *
	*  Free Software Foundation; either version 2 of the License, or (at your  *
	*  option) any later version.                                              *
	\**************************************************************************/
	/* $Id$ */

	$_GET['menuaction']     = 'emailadmin.emailadmin_ui.listProfiles';

	$GLOBALS['egw_info'] = array(
		'flags' => array(
			'currentapp' => 'emailadmin',
			'noheader'   => True,
			'nonavbar'   => True,
		),
	);
	include('../header.inc.php');

	execmethod('emailadmin.emailadmin_ui.listProfiles');
?>
