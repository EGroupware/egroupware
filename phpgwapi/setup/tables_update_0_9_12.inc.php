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

	$test[] = '0.9.10';
	function phpgwapi_upgrade0_9_10()
	{
		global $setup_info;
		$setup_info['phpgwapi']['currentver'] = '0.9.11.001';
		return $setup_info['phpgwapi']['currentver'];
		//return True;
	}

	$test[] = '0.9.11.001';
	function phpgwapi_upgrade0_9_11_001()
	{
		global $setup_info;
		$setup_info['phpgwapi']['currentver'] = '0.9.11.002';
		return $setup_info['phpgwapi']['currentver'];
		//return True;
	}

	$test[] = '0.9.11.002';
	function phpgwapi_upgrade0_9_11_002()
	{
		global $phpgw_info, $phpgw_setup;

		$phpgw_setup->oProc->AddColumn('phpgw_categories','cat_main',array('type' => 'int', 'precision' => 4, 'default' => 0, 'nullable' => False));
		$phpgw_setup->oProc->AddColumn('phpgw_categories','cat_level',array('type' => 'int', 'precision' => 4, 'default' => 0, 'nullable' => False));

		$setup_info['phpgwapi']['currentver'] = '0.9.11.003';
		return $setup_info['phpgwapi']['currentver'];
	}

	$test[] = '0.9.11.003';
	function phpgwapi_upgrade0_9_11_003()
	{
		global $setup_info;
		$setup_info['phpgwapi']['currentver'] = '0.9.11.004';
		return $setup_info['phpgwapi']['currentver'];
		//return True;
	}

	$test[] = '0.9.11.004';
	function phpgwapi_upgrade0_9_11_004()
	{
		global $setup_info, $phpgw_setup;

		$phpgw_setup->oProc->AddColumn('phpgw_config','config_app', array('type' => 'varchar', 'precision' => 50));
		$phpgw_setup->oProc->query("UPDATE phpgw_config SET config_app='phpgwapi'",__LINE__,__FILE__);

		$setup_info['phpgwapi']['currentver'] = '0.9.11.005';
		return $setup_info['phpgwapi']['currentver'];
		//return True;
	}

	$test[] = '0.9.11.005';
	function phpgwapi_upgrade0_9_11_005()
	{
		global $setup_info, $phpgw_setup;

		$phpgw_setup->oProc->AddColumn('phpgw_accounts','account_expires', array('type' => 'int', 'precision' => 4));
		$phpgw_setup->oProc->query("UPDATE phpgw_accounts SET account_expires='-1'",__LINE__,__FILE__);

		$setup_info['phpgwapi']['currentver'] = '0.9.11.006';
		return $setup_info['phpgwapi']['currentver'];
		//return True;
	}

	$test[] = '0.9.11.006';
	function phpgwapi_upgrade0_9_11_006()
	{
		global $setup_info;
		$setup_info['phpgwapi']['currentver'] = '0.9.11.007';
		return $setup_info['phpgwapi']['currentver'];
		//return True;
	}

	$test[] = '0.9.11.007';
	function phpgwapi_upgrade0_9_11_007()
	{
		global $setup_info;
		$setup_info['phpgwapi']['currentver'] = '0.9.11.008';
		return $setup_info['phpgwapi']['currentver'];
		//return True;
	}

	$test[] = '0.9.11.008';
	function phpgwapi_upgrade0_9_11_008()
	{
		global $setup_info, $phpgw_setup;

		$phpgw_setup->oProc->DropTable('profiles');
		
		$setup_info['phpgwapi']['currentver'] = '0.9.11.009';
		return $setup_info['phpgwapi']['currentver'];
		//return True;
	}

	$test[] = '0.9.11.009';
	function phpgwapi_upgrade0_9_11_009()
	{
		global $setup_info;
		$setup_info['phpgwapi']['currentver'] = '0.9.11.010';
		return $setup_info['phpgwapi']['currentver'];
		//return True;
	}

	$test[] = '0.9.11.010';
	function phpgwapi_upgrade0_9_11_010()
	{
		global $setup_info;
		$setup_info['phpgwapi']['currentver'] = '0.9.13.001';
		return $setup_info['phpgwapi']['currentver'];
		//return True;
	}

	$test[] = '0.9.11.011';
	function phpgwapi_upgrade0_9_11_011()
	{
		global $setup_info;

		$setup_info['phpgwapi']['currentver'] = '0.9.13.001';
		return $setup_info['phpgwapi']['currentver'];
	}
?>
