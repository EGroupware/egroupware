<?php
  /**************************************************************************\
  * phpGroupWare - Setup                                                     *
  * http://www.phpgroupware.org                                              *
  * --------------------------------------------                             *
  *  This program is free software; you can redistribute it and/or modify it *
  *  under the terms of the GNU General Public License as published by the   *
  *  Free Software Foundation; either version 2 of the License, or (at your  *
  *  option) any later version.                                              *
  \**************************************************************************/

  /* $Id$ */

	/* Include older phpGroupWare update support */
	include($appdir . 'tables_update_0_9_9.inc.php');
	include($appdir . 'tables_update_0_9_10.inc.php');
	include($appdir . 'tables_update_0_9_12.inc.php');
	include($appdir . 'tables_update_0_9_14.inc.php');

	/* This is since the last release */
	$test[] = '0.9.13.018';
	function phpgwapi_upgrade0_9_13_018()
	{
		$GLOBALS['setup_info']['phpgwapi']['currentver'] = '0.9.15.001';
		return $GLOBALS['setup_info']['phpgwapi']['currentver'];
	}

	$test[] = '0.9.14';
	function phpgwapi_upgrade0_9_14()
	{
		$GLOBALS['setup_info']['phpgwapi']['currentver'] = '0.9.15.001';
		return $GLOBALS['setup_info']['phpgwapi']['currentver'];
	}

	$test[] = '0.9.15.001';
	function phpgwapi_upgrade0_9_15_001()
	{
		$GLOBALS['phpgw_setup']->oProc->RenameTable('lang','phpgw_lang');
		$GLOBALS['phpgw_setup']->oProc->RenameTable('languages','phpgw_languages');

		$GLOBALS['setup_info']['phpgwapi']['currentver'] = '0.9.15.002';
		return $GLOBALS['setup_info']['phpgwapi']['currentver'];
	}
?>
