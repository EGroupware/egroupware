<?php
	/**************************************************************************\
	* phpGroupWare - help                                                      *
	* start file for the phpGroupWare help system                              *
	* http://www.phpgroupware.org                                              *
	* Written by Bettina Gille [ceb@phpgroupware.org]                          *
	* -----------------------------------------------                          *
	*  This program is free software; you can redistribute it and/or modify it *
	*  under the terms of the GNU General Public License as published by the   *
	*  Free Software Foundation; either version 2 of the License, or (at your  *
	*  option) any later version.                                              *
	\**************************************************************************/
	/* $Id$ */

	$GLOBALS['phpgw_info'] = array();

	$GLOBALS['phpgw_info']['flags'] = array
	(
		'headonly'		=> True,
		'currentapp'	=> 'help'
	);
	include('header.inc.php');

	$GLOBALS['phpgw']->help = CreateObject('phpgwapi.help_helper');

	$GLOBALS['phpgw']->hooks->process('help',array('manual'));

	$GLOBALS['phpgw']->xslttpl->set_var('phpgw',$GLOBALS['phpgw']->help->output);
?>
