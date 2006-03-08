<?php
	/**************************************************************************\
	* eGroupWare - Setup                                                       *
	* http://www.egroupware.org                                                *
	* --------------------------------------------                             *
	*  This program is free software; you can redistribute it and/or modify it *
	*  under the terms of the GNU General Public License as published by the   *
	*  Free Software Foundation; either version 2 of the License, or (at your  *
	*  option) any later version.                                              *
	\**************************************************************************/

	// $Id$

	/* Include older eGroupWare update support */
	include('tables_update_0_9_9.inc.php');
	include('tables_update_0_9_10.inc.php');
	include('tables_update_0_9_12.inc.php');
	include('tables_update_0_9_14.inc.php');
	include('tables_update_1_0.inc.php');

	// updates from the stable 1.2 branch
	$test[] = '1.2.007';
	function phpgwapi_upgrade1_2_007()
	{
		return $GLOBALS['setup_info']['phpgwapi']['currentver'] = '1.3.001';
	}
?>
