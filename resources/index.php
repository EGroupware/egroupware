<?php
	/**************************************************************************\
	* eGroupWare - resources - Resource Management System                      *
	* http://www.egroupware.org                                                *
	* Written by Lukas Weiss [ichLukas@gmx.net] and                            *
	*            Cornelius Weiss <egw@von-und-zu-weiss.de>                     *
	* -----------------------------------------------                          *
	*  This program is free software; you can redistribute it and/or modify it *
	*  under the terms of the GNU General Public License as published by the   *
	*  Free Software Foundation; either version 2 of the License, or (at your  *
	*  option) any later version.                                              *
	\**************************************************************************/
	
	
	$GLOBALS['egw_info']['flags'] = array(
		'currentapp'	=> 'resources',
		'noheader'	=> True,
		'nonavbar'	=> True
	);
	include('../header.inc.php');

	$GLOBALS['egw']->redirect_link('/index.php','menuaction=resources.ui_resources.index');
	