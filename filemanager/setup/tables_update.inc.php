<?php
  /**************************************************************************\
  * phpGroupWare - PHPWebHosting                                             *
  * http://www.phpgroupware.org                                              *
  * --------------------------------------------                             *
  *  This program is free software; you can redistribute it and/or modify it *
  *  under the terms of the GNU General Public License as published by the   *
  *  Free Software Foundation; either version 2 of the License, or (at your  *
  *  option) any later version.                                              *
  \**************************************************************************/

  /* $Id$ */

	$test[] = '0.9.13.001';
	function phpwebhosting_upgrade0_9_13_001()
	{
		global $setup_info, $phpgw_setup;

		$phpgw_setup->oProc->AddColumn('phpgw_vfs', 'link_directory', array ('type' => 'text'));
		$phpgw_setup->oProc->AddColumn('phpgw_vfs', 'link_name', array ('type' => 'text'));

		$setup_info['phpwebhosting']['currentver'] = '0.9.13.002';

		return $setup_info['phpwebhosting']['currentver'];
	}

	$test[] = '0.9.13.002';
	function phpwebhosting_upgrade0_9_13_002()
	{
		global $setup_info;

		$setup_info['phpwebhosting']['currentver'] = '0.9.13.003';

		return $setup_info['phpwebhosting']['currentver'];
	}

	$test[] = '0.9.13.003';
	function phpwebhosting_upgrade0_9_13_003()
	{
		global $setup_info;

		$setup_info['phpwebhosting']['currentver'] = '0.9.13.004';

		return $setup_info['phpwebhosting']['currentver'];
	}

	$test[] = '0.9.13.004';
	function phpwebhosting_upgrade0_9_13_004()
	{
		global $setup_info, $phpgw_setup;

		$phpgw_setup->oProc->AddColumn('phpgw_vfs', 'version', array ('type' => 'varchar', 'precision' => 30,'nullable' => False, 'default' => '0.0.0.0'));

		$setup_info['phpwebhosting']['currentver'] = '0.9.13.005';

		return $setup_info['phpwebhosting']['currentver'];
	}

?>
