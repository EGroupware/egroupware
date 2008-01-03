<?php
	/**************************************************************************\
	* eGroupWare - PSP_Admin                                                   *
	* http://www.egroupware.org                                                *
	* -------------------------------------------------------------------------*
	* Copyright (c) 2006 Richard van Diessen Jataggo BV richard@jataggo.com    *
	* -------------------------------------------------------------------------*
	*  This program is free software; you can redistribute it and/or modify it *
	*  under the terms of the GNU General Public License as published by the   *
	*  Free Software Foundation; either version 2 of the License, or (at your  *
	*  option) any later version.                                              *
	\**************************************************************************/

	/* $Id */

	$GLOBALS['egw_info'] = array();
	$GLOBALS['egw_info']['flags'] = array(
		'currentapp'              => 'psp_admin',
		'noheader'                => True,
		'nonavbar'                => True,
		'enable_nextmatchs_class' => True
	);
	include('../header.inc.php');

	ExecMethod('psp_admin.ui_pspadmin.settings');

?>
