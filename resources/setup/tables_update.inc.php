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

	$test[] = '0.0.1.008';
	function resources_upgrade0_0_1_008()
	{
		$GLOBALS['phpgw_setup']->oProc->AddColumn('egw_resources','picture_src',array(
			'type' => 'varchar',
			'precision' => '20'
		));

		$GLOBALS['setup_info']['resources']['currentver'] = '0.0.1.012';
		return $GLOBALS['setup_info']['resources']['currentver'];
	}


	$test[] = '0.0.1.012';
	function resources_upgrade0_0_1_012()
	{
		$GLOBALS['phpgw_setup']->oProc->AddColumn('egw_resources','picture_thumb',array(
			'type' => 'blob'
		));

		$GLOBALS['setup_info']['resources']['currentver'] = '0.0.1.013';
		return $GLOBALS['setup_info']['resources']['currentver'];
	}


	$test[] = '0.0.1.013';
	function resources_upgrade0_0_1_013()
	{
		$GLOBALS['phpgw_setup']->oProc->DropColumn('egw_resources',array(
			'fd' => array(
				'id' => array('type' => 'auto'),
				'name' => array('type' => 'varchar','precision' => '100'),
				'short_description' => array('type' => 'varchar','precision' => '100'),
				'cat_id' => array('type' => 'int','precision' => '11','nullable' => False),
				'quantity' => array('type' => 'int','precision' => '11'),
				'useable' => array('type' => 'int','precision' => '11'),
				'location' => array('type' => 'varchar','precision' => '100'),
				'bookable' => array('type' => 'varchar','precision' => '1'),
				'buyable' => array('type' => 'varchar','precision' => '1'),
				'prize' => array('type' => 'varchar','precision' => '200'),
				'long_description' => array('type' => 'longtext'),
				'accessories' => array('type' => 'varchar','precision' => '50'),
				'picture_src' => array('type' => 'varchar','precision' => '20'),
				'picture_thumb' => array('type' => 'blob')
			),
			'pk' => array('id'),
			'fk' => array(),
			'ix' => array(),
			'uc' => array()
		),'picture');
		$GLOBALS['phpgw_setup']->oProc->DropColumn('egw_resources',array(
			'fd' => array(
				'id' => array('type' => 'auto'),
				'name' => array('type' => 'varchar','precision' => '100'),
				'short_description' => array('type' => 'varchar','precision' => '100'),
				'cat_id' => array('type' => 'int','precision' => '11','nullable' => False),
				'quantity' => array('type' => 'int','precision' => '11'),
				'useable' => array('type' => 'int','precision' => '11'),
				'location' => array('type' => 'varchar','precision' => '100'),
				'bookable' => array('type' => 'varchar','precision' => '1'),
				'buyable' => array('type' => 'varchar','precision' => '1'),
				'prize' => array('type' => 'varchar','precision' => '200'),
				'long_description' => array('type' => 'longtext'),
				'accessories' => array('type' => 'varchar','precision' => '50'),
				'picture_src' => array('type' => 'varchar','precision' => '20')
			),
			'pk' => array('id'),
			'fk' => array(),
			'ix' => array(),
			'uc' => array()
		),'picture_thumb');

		$GLOBALS['setup_info']['resources']['currentver'] = '0.0.1.014';
		return $GLOBALS['setup_info']['resources']['currentver'];
	}


	$test[] = '0.0.1.014';
	function resources_upgrade0_0_1_014()
	{
		$GLOBALS['phpgw_setup']->oProc->AlterColumn('egw_resources','quantity',array(
			'type' => 'int',
			'precision' => '11',
			'default' => '1'
		));
		$GLOBALS['phpgw_setup']->oProc->AlterColumn('egw_resources','useable',array(
			'type' => 'int',
			'precision' => '11',
			'default' => '1'
		));

		$GLOBALS['setup_info']['resources']['currentver'] = '0.0.1.015';
		return $GLOBALS['setup_info']['resources']['currentver'];
	}


	$test[] = '0.0.1.015';
	function resources_upgrade0_0_1_015()
	{
		$GLOBALS['phpgw_setup']->oProc->AlterColumn('egw_resources','accessories',array(
			'type' => 'varchar',
			'precision' => '100'
		));
		$GLOBALS['phpgw_setup']->oProc->AddColumn('egw_resources','accessory_only',array(
			'type' => 'varchar',
			'precision' => '1',
			'default' => '0'
		));
		$GLOBALS['phpgw_setup']->oProc->AddColumn('egw_resources','relatives',array(
			'type' => 'varchar',
			'precision' => '100'
		));

		$GLOBALS['setup_info']['resources']['currentver'] = '0.0.1.016';
		return $GLOBALS['setup_info']['resources']['currentver'];
	}


	$test[] = '0.0.1.016';
	function resources_upgrade0_0_1_016()
	{
		$GLOBALS['phpgw_setup']->oProc->RenameColumn('egw_resources','accessories','picture_src');
		$GLOBALS['phpgw_setup']->oProc->DropColumn('egw_resources',array(
			'fd' => array(
				'id' => array('type' => 'auto'),
				'name' => array('type' => 'varchar','precision' => '100'),
				'short_description' => array('type' => 'varchar','precision' => '100'),
				'cat_id' => array('type' => 'int','precision' => '11','nullable' => False),
				'quantity' => array('type' => 'int','precision' => '11','default' => '1'),
				'useable' => array('type' => 'int','precision' => '11','default' => '1'),
				'location' => array('type' => 'varchar','precision' => '100'),
				'bookable' => array('type' => 'varchar','precision' => '1'),
				'buyable' => array('type' => 'varchar','precision' => '1'),
				'prize' => array('type' => 'varchar','precision' => '200'),
				'long_description' => array('type' => 'longtext'),
				'accessories' => array('type' => 'varchar','precision' => '100'),
				'picture_src' => array('type' => 'varchar','precision' => '20'),
				'relatives' => array('type' => 'varchar','precision' => '100')
			),
			'pk' => array('id'),
			'fk' => array(),
			'ix' => array(),
			'uc' => array()
		),'accessory_only');
		$GLOBALS['phpgw_setup']->oProc->DropColumn('egw_resources',array(
			'fd' => array(
				'id' => array('type' => 'auto'),
				'name' => array('type' => 'varchar','precision' => '100'),
				'short_description' => array('type' => 'varchar','precision' => '100'),
				'cat_id' => array('type' => 'int','precision' => '11','nullable' => False),
				'quantity' => array('type' => 'int','precision' => '11','default' => '1'),
				'useable' => array('type' => 'int','precision' => '11','default' => '1'),
				'location' => array('type' => 'varchar','precision' => '100'),
				'bookable' => array('type' => 'varchar','precision' => '1'),
				'buyable' => array('type' => 'varchar','precision' => '1'),
				'prize' => array('type' => 'varchar','precision' => '200'),
				'long_description' => array('type' => 'longtext'),
				'accessories' => array('type' => 'varchar','precision' => '100'),
				'picture_src' => array('type' => 'varchar','precision' => '20')
			),
			'pk' => array('id'),
			'fk' => array(),
			'ix' => array(),
			'uc' => array()
		),'relatives');
		$GLOBALS['phpgw_setup']->oProc->AddColumn('egw_resources','picture_src',array(
			'type' => 'varchar',
			'precision' => '20'
		));
		$GLOBALS['phpgw_setup']->oProc->AlterColumn('egw_resources','accessoriy_of',array(
			'type' => 'int',
			'precision' => '11',
			'default' => '-1'
		));

		$GLOBALS['setup_info']['resources']['currentver'] = '0.0.1.017';
		return $GLOBALS['setup_info']['resources']['currentver'];
	}
?>
