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

	// $Id$
	// $Source$

	/* Include older phpGroupWare update support */
	include('tables_update_0_9_9.inc.php');
	include('tables_update_0_9_10.inc.php');
	include('tables_update_0_9_12.inc.php');
	include('tables_update_0_9_14.inc.php');


	$test[] = '1.0.0.001';
	function phpgwapi_upgrade1_0_0_001()
	{
		$GLOBALS['phpgw_setup']->oProc->RenameColumn('phpgw_async','id','async_id');
		$GLOBALS['phpgw_setup']->oProc->RenameColumn('phpgw_async','next','async_next');
		$GLOBALS['phpgw_setup']->oProc->RenameColumn('phpgw_async','times','async_times');
		$GLOBALS['phpgw_setup']->oProc->RenameColumn('phpgw_async','method','async_method');
		$GLOBALS['phpgw_setup']->oProc->RenameColumn('phpgw_async','data','async_data');
		$GLOBALS['phpgw_setup']->oProc->RenameColumn('phpgw_async','account_id','async_account_id');
		$GLOBALS['phpgw_setup']->oProc->RefreshTable('phpgw_async',array(
			'fd' => array(
				'async_id' => array('type' => 'varchar','precision' => '255','nullable' => False),
				'async_next' => array('type' => 'int','precision' => '4','nullable' => False),
				'async_times' => array('type' => 'varchar','precision' => '255','nullable' => False),
				'async_method' => array('type' => 'varchar','precision' => '80','nullable' => False),
				'async_data' => array('type' => 'text','nullable' => False),
				'async_account_id' => array('type' => 'int','precision' => '4','nullable' => False,'default' => '0')
			),
			'pk' => array('async_id'),
			'fk' => array(),
			'ix' => array(),
			'uc' => array()
		));

		$GLOBALS['setup_info']['phpgwapi']['currentver'] = '1.0.1.001';
		return $GLOBALS['setup_info']['phpgwapi']['currentver'];
	}
?>
