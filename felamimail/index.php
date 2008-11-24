<?php
	/**************************************************************************\
	* eGroupWare - FeLaMiMail                                                  *
	* http://www.egroupware.org                                                *
	* http://www.phpgw.de                                                      *
	* http://www.linux-at-work.de                                              *
	* Written by Lars Kneschke [lkneschke@linux-at-work.de]                    *
	* -----------------------------------------------                          *
	*  This program is free software; you can redistribute it and/or modify it *
	*  under the terms of the GNU General Public License as published by the   *
	*  Free Software Foundation; either version 2 of the License, or (at your  *
	*  option) any later version.                                              *
	\**************************************************************************/
	/* $Id$ */

	// this is to get css inclusion working
	$_GET['menuaction']	= 'felamimail.uifelamimail.viewMainScreen';
	
	$egw_info = array();
	$egw_info['flags'] = array
	(
		'currentapp' => 'felamimail',
		'noheader'   => True,
		'nonavbar'   => True,
		'include_xajax' => True,
	);
								
	include('../header.inc.php');

	execmethod('felamimail.uifelamimail.viewMainScreen');
?>
