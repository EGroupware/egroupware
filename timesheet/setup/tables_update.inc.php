<?php
	/**************************************************************************\
	* eGroupWare - Setup                                                       *
	* http://www.eGroupWare.org                                                *
	* Created by eTemplates DB-Tools written by ralfbecker@outdoor-training.de *
	* --------------------------------------------                             *
	* This program is free software; you can redistribute it and/or modify it  *
	* under the terms of the GNU General Public License as published by the    *
	* Free Software Foundation; either version 2 of the License, or (at your   *
	* option) any later version.                                               *
	\**************************************************************************/
	
	/* $Id$ */

	$test[] = '0.1.001';
	function timesheet_upgrade0_1_001()
	{
		$GLOBALS['egw_setup']->oProc->AddColumn('egw_timesheet','pl_id',array(
			'type' => 'int',
			'precision' => '4',
			'default' => '0'
		));

		return $GLOBALS['setup_info']['timesheet']['currentver'] = '0.2.001';
	}
?>
