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
	// $Source$

	/* Include older eGroupWare update support */
	include('tables_update_0_9_9.inc.php');
	include('tables_update_0_9_10.inc.php');
	include('tables_update_0_9_12.inc.php');
	include('tables_update_0_9_14.inc.php');

	// updates from the stable 1.0.0 branch
	$test[] = '1.0.0.001';
	function phpgwapi_upgrade1_0_0_001()
	{
		$GLOBALS['setup_info']['phpgwapi']['currentver'] = '1.0.0.004';
		return $GLOBALS['setup_info']['phpgwapi']['currentver'];
	}

	$test[] = '1.0.0.002';
	function phpgwapi_upgrade1_0_0_002()
	{
		// identical to 1.0.0.001, only created to get a new version of the packages
		return phpgwapi_upgrade1_0_0_001();
	}

	$test[] = '1.0.0.003';
	function phpgwapi_upgrade1_0_0_003()
	{
		// identical to 1.0.0.001, only created to get a new version of the final 1.0 packages
		return phpgwapi_upgrade1_0_0_001();
	}
	
	$test[] = '1.0.0.004';
	function phpgwapi_upgrade1_0_0_004()
	{
		$GLOBALS['phpgw_setup']->oProc->RenameColumn('phpgw_async','id','async_id');
		$GLOBALS['phpgw_setup']->oProc->RenameColumn('phpgw_async','next','async_next');
		$GLOBALS['phpgw_setup']->oProc->RenameColumn('phpgw_async','times','async_times');
		$GLOBALS['phpgw_setup']->oProc->RenameColumn('phpgw_async','method','async_method');
		$GLOBALS['phpgw_setup']->oProc->RenameColumn('phpgw_async','data','async_data');
		$GLOBALS['phpgw_setup']->oProc->RenameColumn('phpgw_async','account_id','async_account_id');

		$GLOBALS['setup_info']['phpgwapi']['currentver'] = '1.0.1.001';
		return $GLOBALS['setup_info']['phpgwapi']['currentver'];
	}

	$test[] = '1.0.0.005';
	function phpgwapi_upgrade1_0_0_005()
	{
		// identical to 1.0.0.001, only created to get a new version of the bugfix release
		return phpgwapi_upgrade1_0_0_004();
	}
	
	$test[] = '1.0.1.001';
	function phpgwapi_upgrade1_0_1_001()
	{
		// removing the ACL entries of deleted accounts
		$GLOBALS['phpgw_setup']->setup_account_object();
		if (($all_accounts = $GLOBALS['phpgw']->accounts->search(array('type'=>'both'))))
		{
			$all_accounts = array_keys($all_accounts);
			$GLOBALS['phpgw_setup']->oProc->query("DELETE FROM phpgw_acl WHERE acl_account NOT IN (".implode(',',$all_accounts).")",__LINE__,__FILE__);
			$GLOBALS['phpgw_setup']->oProc->query("DELETE FROM phpgw_acl WHERE acl_appname='phpgw_group' AND acl_location NOT IN ('".implode("','",$all_accounts)."')",__LINE__,__FILE__);
		}
		$GLOBALS['setup_info']['phpgwapi']['currentver'] = '1.0.1.002';
		return $GLOBALS['setup_info']['phpgwapi']['currentver'];
	}

?>
