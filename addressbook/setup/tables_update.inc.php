<?php
	/**************************************************************************\
	* phpGroupWare - Setup                                                     *
	* http://www.phpgroupware.org                                              *
	* --------------------------------------------                             *
	* This program is free software; you can redistribute it and/or modify it  *
	* under the terms of the GNU General Public License as published by the    *
	* Free Software Foundation; either version 2 of the License, or (at your   *
	* option) any later version.                                               *
	\**************************************************************************/

	/* $Id$ */

	$test[] = '0.9.13.002';
	function addressbook_upgrade0_9_13_002()
	{
		$GLOBALS['phpgw_setup']->oProc->CreateTable(
			'phpgw_addressbook_servers', array(
				'fd' => array(
					'name'    => array('type' => 'varchar', 'precision' => 64,  'nullable' => False),
					'basedn'  => array('type' => 'varchar', 'precision' => 255, 'nullable' => True),
					'search'  => array('type' => 'varchar', 'precision' => 32,  'nullable' => True),
					'attrs'   => array('type' => 'varchar', 'precision' => 255, 'nullable' => True),
					'enabled' => array('type' => 'int', 'precision' => 4)
				),
				'pk' => array('name'),
				'fk' => array(),
				'ix' => array(),
				'uc' => array()
			)
		);

		$GLOBALS['setup_info']['addressbook']['currentver'] = '0.9.13.003';
		return $GLOBALS['setup_info']['addressbook']['currentver'];
	}
?>
