<?php
	/**************************************************************************\
	* eGroupWare - Setup                                                       *
	* http://www.egroupware.org                                                *
	* --------------------------------------------                             *
	*  This program is free software; you can redistribute it and/or modify it *
	*  under the terms of the GNU General Public License as published by the   *
	*  Free Software Foundation; either version 2 of the License, or (at your  *
	*  option) any later version.                                              *
	\**************************************************************************/

	// $Id$

	/* Include older eGroupWare update support */
	include('tables_update_0_9_9.inc.php');
	include('tables_update_0_9_10.inc.php');
	include('tables_update_0_9_12.inc.php');
	include('tables_update_0_9_14.inc.php');
	include('tables_update_1_0.inc.php');

	// updates from the stable 1.2 branch
	$test[] = '1.2.007';
	function phpgwapi_upgrade1_2_007()
	{
		return $GLOBALS['setup_info']['phpgwapi']['currentver'] = '1.3.001';
	}
	
	$test[] = '1.2.008';
	function phpgwapi_upgrade1_2_008()
	{
		// fixing the lang change from zt -> zh-tw for existing installations
		return $GLOBALS['setup_info']['phpgwapi']['currentver'] = '1.3.002';
	}

	// updates in HEAD / 1.3
	$test[] = '1.3.001';
	function phpgwapi_upgrade1_3_001()
	{
		// fixing the lang change from zt -> zh-tw for existing installations
		$GLOBALS['egw_setup']->db->update('egw_languages',array('lang_id' => 'zh-tw'),array('lang_id' => 'zt'),__LINE__,__FILE__);

		return $GLOBALS['setup_info']['phpgwapi']['currentver'] = '1.3.002';
	}
	
	$test[] = '1.3.002';
	function phpgwapi_upgrade1_3_002()
	{
		/*************************************************************************\
		 *      add addressbook-type contact into type definition table           *
		\*************************************************************************/
		if ($GLOBALS['DEBUG'])
		{
			echo "<br>\n<b>initiating to create the default type 'contact' for addressbook";
		}
		
		$newconf = array('n' => array(
			'name' => 'contact',
			'options' => array(
				'template' => 'addressbook.edit',
				'icon' => 'navbar.png'
		)));
		$GLOBALS['egw_setup']->oProc->query("INSERT INTO egw_config (config_app,config_name,config_value) VALUES ('addressbook','types','". serialize($newconf). "')",__LINE__,__FILE__);

		if ($GLOBALS['DEBUG'])
		{
			echo " DONE!</b>";
		}
		/*************************************************************************/
		
		return $GLOBALS['setup_info']['phpgwapi']['currentver'] = '1.3.003';
	}
?>
