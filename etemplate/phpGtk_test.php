#!/usr/local/bin/php -q

<?php
	/**************************************************************************\
	* phpGroupWare - EditableTemplates - GTK User Interface                    *
	* http://www.phpgroupware.org                                              *
	* Written by Ralf Becker <RalfBecker@outdoor-training.de>                  *
	* --------------------------------------------                             *
	*  This program is free software; you can redistribute it and/or modify it *
	*  under the terms of the GNU General Public License as published by the   *
	*  Free Software Foundation; either version 2 of the License, or (at your  *
	*  option) any later version.                                              *
	\**************************************************************************/

	/* $Id$ */

//echo "Hello World!!!\n";

// To be able to test eTemplates with phpGtk you need a standalone/cgi php interpreter with compiled-in phpGtk installed
// (for instruction on how to do so look at the phpGtk website http://gtk.php.net/).

// As phpGroupWare is not programmed as eTemplate (at least not know) you need to log in via Web and the session-id need to be
// passed in the url, so you can read it and put it in the following lines (it NEED to be an active session).

$GLOBALS['HTTP_GET_VARS'] = array(
	'sessionid' => '2567b6b82e8e8f1ceb6f399342834d92',
	'kp3' => '5f9dc297f3c4739f92664359ffaf612e',
	'domain' => 'default'
);	

$GLOBALS['phpgw_info']['flags'] = array(
	'currentapp'	=> 'etemplate',
	'noheader'		=> True,
	'nonavbar'		=> True
);
include('../header.inc.php');

ExecMethod('etemplate.db_tools.edit');

$GLOBALS['phpgw']->common->phpgw_exit();
