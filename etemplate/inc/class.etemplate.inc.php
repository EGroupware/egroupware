<?php
	/**************************************************************************\
	* phpGroupWare - EditableTemplates                                         *
	* http://www.phpgroupware.org                                              *
	* Written by Ralf Becker <RalfBecker@outdoor-training.de>                  *
	* --------------------------------------------                             *
	*  This program is free software; you can redistribute it and/or modify it *
	*  under the terms of the GNU General Public License as published by the   *
	*  Free Software Foundation; either version 2 of the License, or (at your  *
	*  option) any later version.                                              *
	\**************************************************************************/

	/* $Id$ */

	if (!function_exists('get_var'))
	{
		include_once('get_var.php');
	}
	$ui = ''; // html UI, which UI to use, should come from api and be in $GLOBALS['phpgw']???
	if ($_ENV['DISPLAY'] && isset($_SERVER['_']))
	{
		$ui = '_gtk';
	}
	include_once(PHPGW_INCLUDE_ROOT . "/etemplate/inc/class.uietemplate$ui.inc.php");
