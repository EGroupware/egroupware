#!/usr/bin/php -q
<?php
	/**************************************************************************\
	* phpGroupWare API - Timed Asynchron Services for phpGroupWare             *
	* Written by Ralf Becker <RalfBecker@outdoor-training.de>                  *
	* Class for creating cron-job like timed calls of phpGroupWare methods     *
	* -------------------------------------------------------------------------*
	* This library is part of the phpGroupWare API                             *
	* http://www.phpgroupware.org/                                             *
	* ------------------------------------------------------------------------ *
	* This library is free software; you can redistribute it and/or modify it  *
	* under the terms of the GNU Lesser General Public License as published by *
	* the Free Software Foundation; either version 2.1 of the License,         *
	* or any later version.                                                    *
	* This library is distributed in the hope that it will be useful, but      *
	* WITHOUT ANY WARRANTY; without even the implied warranty of               *
	* MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.                     *
	* See the GNU Lesser General Public License for more details.              *
	* You should have received a copy of the GNU Lesser General Public License *
	* along with this library; if not, write to the Free Software Foundation,  *
	* Inc., 59 Temple Place, Suite 330, Boston, MA  02111-1307  USA            *
	\**************************************************************************/

	/* $Id$ */

	$path_to_phpgroupware = '../..';	// need to be adapted if this script is moved somewhere else
	$GLOBALS['domain'] = 'default';

	$GLOBALS['phpgw_info']['flags'] = array(
		'currentapp' => 'login'
	);
	include($path_to_phpgroupware.'/header.inc.php');

	$num = ExecMethod('phpgwapi.asyncservice.check_run');
	
	// if the following comment got removed, you will get an email from cron for every check performed
	//echo date('Y/m/d H:i:s ').($num ? "$num job(s) executed" : 'Nothing to execute')."\n";

	$GLOBALS['phpgw']->common->phpgw_exit();



