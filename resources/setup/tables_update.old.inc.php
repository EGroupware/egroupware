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

	$test[] = '';
	function resources_upgrade()
	{
		$GLOBALS['phpgw_setup']->oProc->AddColumn('egw_resources','cat_id',array(
			'type' => 'int',
			'precision' => '11'
		));

		$GLOBALS['setup_info']['resources']['currentver'] = '001';
		return $GLOBALS['setup_info']['resources']['currentver'];
	}


	$test[] = '';
	function resources_upgrade()
	{
		$GLOBALS['phpgw_setup']->oProc->AddColumn('egw_resources','quantity',array(
			'type' => 'int',
			'precision' => '11'
		));

		$GLOBALS['setup_info']['resources']['currentver'] = '001';
		return $GLOBALS['setup_info']['resources']['currentver'];
	}


	$test[] = '';
	function resources_upgrade()
	{
		$GLOBALS['phpgw_setup']->oProc->AddColumn('egw_resources','useable',array(
			'type' => 'int',
			'precision' => '11'
		));
		$GLOBALS['phpgw_setup']->oProc->AddColumn('egw_resources','location',array(
			'type' => 'varchar',
			'precision' => '100'
		));

		$GLOBALS['setup_info']['resources']['currentver'] = '001';
		return $GLOBALS['setup_info']['resources']['currentver'];
	}
?>
