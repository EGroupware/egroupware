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

	$test[] = '0.9.13.001';
	function etemplate_upgrade0_9_13_001()
	{
		$GLOBALS['phpgw_setup']->oProc->AlterColumn('phpgw_etemplate','et_data',array(
			'type' => 'text',
			'nullable' => True
		));
		$GLOBALS['phpgw_setup']->oProc->AlterColumn('phpgw_etemplate','et_size',array(
			'type' => 'char',
			'precision' => '128',
			'nullable' => True
		));
		$GLOBALS['phpgw_setup']->oProc->AlterColumn('phpgw_etemplate','et_style',array(
			'type' => 'text',
			'nullable' => True
		));
		$GLOBALS['phpgw_setup']->oProc->AddColumn('phpgw_etemplate','et_modified',array(
			'type' => 'int',
			'precision' => '4',
			'default' => '0',
			'nullable' => False
		));


		$GLOBALS['setup_info']['etemplate']['currentver'] = '0.9.15.001';
		return $GLOBALS['setup_info']['etemplate']['currentver'];
	}
?>
