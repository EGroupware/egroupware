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


	$test[] = '';
	function resources_upgrade()
	{
		$GLOBALS['phpgw_setup']->oProc->RenameColumn('egw_resources','short_descreption','short_description');
		$GLOBALS['phpgw_setup']->oProc->AddColumn('egw_resources','bookable',array(
			'type' => 'int',
			'precision' => '1'
		));
		$GLOBALS['phpgw_setup']->oProc->AddColumn('egw_resources','buyable',array(
			'type' => 'int',
			'precision' => '1'
		));
		$GLOBALS['phpgw_setup']->oProc->AddColumn('egw_resources','prize',array(
			'type' => 'varchar',
			'precision' => '200'
		));
		$GLOBALS['phpgw_setup']->oProc->AddColumn('egw_resources','long_description',array(
			'type' => 'longtext'
		));
		$GLOBALS['phpgw_setup']->oProc->AddColumn('egw_resources','picture',array(
			'type' => 'blob'
		));
		$GLOBALS['phpgw_setup']->oProc->AddColumn('egw_resources','accessories',array(
			'type' => 'varchar',
			'precision' => '50'
		));

		$GLOBALS['setup_info']['resources']['currentver'] = '008';
		return $GLOBALS['setup_info']['resources']['currentver'];
	}


	$test[] = '0.0.1.008';
	function resources_upgrade0_0_1_008()
	{
		$GLOBALS['phpgw_setup']->oProc->AlterColumn('egw_resources','cat_id',array(
			'type' => 'int',
			'precision' => '2'
		));

		$GLOBALS['setup_info']['resources']['currentver'] = '0.0.1.008';
		return $GLOBALS['setup_info']['resources']['currentver'];
	}


	$test[] = '0.0.1.008';
	function resources_upgrade0_0_1_008()
	{
		$GLOBALS['phpgw_setup']->oProc->AlterColumn('egw_resources','cat_id',array(
			'type' => 'varchar',
			'precision' => '11'
		));
		$GLOBALS['phpgw_setup']->oProc->AlterColumn('egw_resources','quantity',array(
			'type' => 'varchar',
			'precision' => '11'
		));
		$GLOBALS['phpgw_setup']->oProc->AlterColumn('egw_resources','useable',array(
			'type' => 'varchar',
			'precision' => '11'
		));
		$GLOBALS['phpgw_setup']->oProc->AlterColumn('egw_resources','bookable',array(
			'type' => 'varchar',
			'precision' => '1'
		));
		$GLOBALS['phpgw_setup']->oProc->AlterColumn('egw_resources','buyable',array(
			'type' => 'varchar',
			'precision' => '1'
		));

		$GLOBALS['setup_info']['resources']['currentver'] = '0.0.1.008';
		return $GLOBALS['setup_info']['resources']['currentver'];
	}


	$test[] = '0.0.1.008';
	function resources_upgrade0_0_1_008()
	{
		$GLOBALS['phpgw_setup']->oProc->AlterColumn('egw_resources','cat_id',array(
			'type' => 'varchar',
			'precision' => '11',
			'nullable' => False
		));

		$GLOBALS['setup_info']['resources']['currentver'] = '0.0.1.009';
		return $GLOBALS['setup_info']['resources']['currentver'];
	}


	$test[] = '0.0.1.009';
	function resources_upgrade0_0_1_009()
	{
		$GLOBALS['phpgw_setup']->oProc->AlterColumn('egw_resources','cat_id',array(
			'type' => 'int',
			'precision' => '11',
			'nullable' => False
		));

		$GLOBALS['setup_info']['resources']['currentver'] = '0.0.1.010';
		return $GLOBALS['setup_info']['resources']['currentver'];
	}
?>
