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
?>
