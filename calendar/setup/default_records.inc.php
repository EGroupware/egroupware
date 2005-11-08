<?php
	/**************************************************************************\
	* eGroupWare - Setup / Calendar                                            *
	* http://www.egroupware.org                                                *
	* --------------------------------------------                             *
	*  This program is free software; you can redistribute it and/or modify it *
	*  under the terms of the GNU General Public License as published by the   *
	*  Free Software Foundation; either version 2 of the License, or (at your  *
	*  option) any later version.                                              *
	\**************************************************************************/

	/* $Id$ */

	// enable auto-loading of holidays from localhost by default
	foreach(array(
		'auto_load_holidays' => 'True',
		'holidays_url_path'  => 'localhost',
	) as $name => $value)
	{
		$oProc->insert($GLOBALS['egw_setup']->config_table,array(
			'config_value' => $value,
		),array(
			'config_app' => 'phpgwapi',
			'config_name' => $name,
		),__FILE__,__LINE__);
	}
