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

	/* Include older phpGroupWare update support */
	include($appdir . 'tables_update_0_9_9.inc.php');
	include($appdir . 'tables_update_0_9_10.inc.php');
	include($appdir . 'tables_update_0_9_12.inc.php');
	include($appdir . 'tables_update_0_9_14.inc.php');

	/* This is since the last release */
	$test[] = '0.9.13.018';
	function phpgwapi_upgrade0_9_13_018()
	{
		$GLOBALS['setup_info']['phpgwapi']['currentver'] = '0.9.15.001';
		return $GLOBALS['setup_info']['phpgwapi']['currentver'];
	}

	$test[] = '0.9.14';
	function phpgwapi_upgrade0_9_14()
	{
		$GLOBALS['setup_info']['phpgwapi']['currentver'] = '0.9.15.001';
		return $GLOBALS['setup_info']['phpgwapi']['currentver'];
	}

	$test[] = '0.9.14.000';
	function phpgwapi_upgrade0_9_14_000()
	{
		$GLOBALS['setup_info']['phpgwapi']['currentver'] = '0.9.15.001';
		return $GLOBALS['setup_info']['phpgwapi']['currentver'];
	}

	$test[] = '0.9.14.001';
	function phpgwapi_upgrade0_9_14_001()
	{
		$GLOBALS['setup_info']['phpgwapi']['currentver'] = '0.9.15.001';
		return $GLOBALS['setup_info']['phpgwapi']['currentver'];
	}

	$test[] = '0.9.15.001';
	function phpgwapi_upgrade0_9_15_001()
	{
		$GLOBALS['phpgw_setup']->oProc->RenameTable('lang','phpgw_lang');
		$GLOBALS['phpgw_setup']->oProc->RenameTable('languages','phpgw_languages');

		$GLOBALS['setup_info']['phpgwapi']['currentver'] = '0.9.15.002';
		return $GLOBALS['setup_info']['phpgwapi']['currentver'];
	}

	$test[] = '0.9.15.002';
	function phpgwapi_upgrade0_9_15_002()
	{
		$GLOBALS['phpgw_setup']->oProc->CreateTable(
			'phpgw_newprefs', array(
				'fd' => array(
					'preference_owner' => array('type' => 'int', 'precision' => 4, 'nullable' => False),
					'preference_value' => array('type' => 'text')
				),
				'pk' => array('preference_owner'),
				'fk' => array(),
				'ix' => array(),
				'uc' => array()
			)
		);

		$GLOBALS['phpgw_setup']->oProc->query('SELECT * FROM phpgw_preferences',__LINE__,__FILE__);
		$db2 = $GLOBALS['phpgw_setup']->db;

		while($GLOBALS['phpgw_setup']->oProc->next_record())
		{
			$accountid = $GLOBALS['phpgw_setup']->oProc->f('preference_owner');
			settype($accountid,'integer');

			$db2->query('INSERT INTO phpgw_newprefs (preference_owner,preference_value) VALUES('
				. $accountid . ",'"
				. $GLOBALS['phpgw_setup']->oProc->f('preference_value') . "')",
				__LINE__,__FILE__);
		}

		$GLOBALS['phpgw_setup']->oProc->DropTable('phpgw_preferences');
		$GLOBALS['phpgw_setup']->oProc->RenameTable('phpgw_newprefs','phpgw_preferences');

		$setup_info['phpgwapi']['currentver'] = '0.9.15.003';
		return $setup_info['phpgwapi']['currentver'];
	}

	$test[] = '0.9.15.003';
	function phpgwapi_upgrade0_9_15_003()
	{
		$GLOBALS['phpgw_setup']->oProc->AddColumn('phpgw_vfs','content', array ('type' => 'text', 'nullable' => False));

		$GLOBALS['setup_info']['phpgwapi']['currentver'] = '0.9.15.004';
		return $GLOBALS['setup_info']['phpgwapi']['currentver'];
	}

	$test[] = '0.9.15.004';
	function phpgwapi_upgrade0_9_15_004()
	{
		$GLOBALS['phpgw_setup']->db->query("UPDATE phpgw_languages set available='Yes' WHERE lang_id='pl'");

		$GLOBALS['setup_info']['phpgwapi']['currentver'] = '0.9.15.005';
		return $GLOBALS['setup_info']['phpgwapi']['currentver'];
	}

	$test[] = '0.9.15.005';
	function phpgwapi_upgrade0_9_15_005()
	{
		$GLOBALS['phpgw_setup']->oProc->query("INSERT INTO phpgw_config (config_app, config_name, config_value) VALUES ('phpgwapi','sessions_timeout',7200)");
		$GLOBALS['phpgw_setup']->oProc->query("INSERT INTO phpgw_config (config_app, config_name, config_value) VALUES ('phpgwapi','sessions_app_timeout',86400)");

		$GLOBALS['setup_info']['phpgwapi']['currentver'] = '0.9.15.006';
		return $GLOBALS['setup_info']['phpgwapi']['currentver'];
	}



	$test[] = '0.9.15.006';
	function phpgwapi_upgrade0_9_15_006()
	{
		$GLOBALS['phpgw_setup']->oProc->AlterColumn('phpgw_config','config_value',array(
			'type' => 'text',
			'nullable' => False
		));


		$GLOBALS['setup_info']['phpgwapi']['currentver'] = '0.9.15.007';
		return $GLOBALS['setup_info']['phpgwapi']['currentver'];
	}


	$test[] = '0.9.15.007';
	function phpgwapi_upgrade0_9_15_007()
	{
		$GLOBALS['phpgw_setup']->oProc->DropColumn('phpgw_applications',array(
			'fd' => array(
				'app_id' => array('type' => 'auto','precision' => '4','nullable' => False),
				'app_name' => array('type' => 'varchar','precision' => '25','nullable' => False),
				'app_enabled' => array('type' => 'int','precision' => '4'),
				'app_order' => array('type' => 'int','precision' => '4'),
				'app_tables' => array('type' => 'text'),
				'app_version' => array('type' => 'varchar','precision' => '20','nullable' => False,'default' => '0.0')
			),
			'pk' => array('app_id'),
			'fk' => array(),
			'ix' => array(),
			'uc' => array('app_name')
		),'app_title');


		$GLOBALS['setup_info']['phpgwapi']['currentver'] = '0.9.15.008';
		return $GLOBALS['setup_info']['phpgwapi']['currentver'];
	}
?>
