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

	$test[] = '0.9.11';
	function infolog_upgrade0_9_11()
	{
		$GLOBALS['phpgw_setup']->oProc->RenameColumn('phpgw_infolog','info_datecreated','info_datemodified');
		$GLOBALS['phpgw_setup']->oProc->AddColumn('phpgw_infolog','info_event_id',array(
			'type' => 'int',
			'precision' => '4',
			'default' => '0',
			'nullable' => False
		));


		$GLOBALS['setup_info']['infolog']['currentver'] = '0.9.15.001';
		return $GLOBALS['setup_info']['phpgwapi']['currentver'];
	}


	$test[] = '0.9.15.001';
	function infolog_upgrade0_9_15_001()
	{
		$GLOBALS['phpgw_setup']->oProc->CreateTable('phpgw_links',array(
			'fd' => array(
				'link_id' => array('type' => 'auto','nullable' => False),
				'link_app1' => array('type' => 'varchar','precision' => '25','nullable' => False),
				'link_id1' => array('type' => 'varchar','precision' => '50','nullable' => False),
				'link_app2' => array('type' => 'varchar','precision' => '25','nullable' => False),
				'link_id2' => array('type' => 'varchar','precision' => '50','nullable' => False),
				'link_remark' => array('type' => 'varchar','precision' => '50','nullable' => True),
				'link_lastmod' => array('type' => 'int','precision' => '4','nullable' => False),
				'link_owner' => array('type' => 'int','precision' => '4','nullable' => False)
			),
			'pk' => array('link_id'),
			'fk' => array(),
			'ix' => array(),
			'uc' => array()
		));


		$GLOBALS['setup_info']['infolog']['currentver'] = '0.9.15.002';
		return $GLOBALS['setup_info']['phpgwapi']['currentver'];
	}
?>
