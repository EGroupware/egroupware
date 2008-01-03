<?php
	/**************************************************************************\
	* eGroupWare - Ask a Quote                                                 *
	* http://www.egroupware.org                                                *
	* -------------------------------------------------------------------------*
	* Copyright (c) 2006 Pim Snel - Lingewoud B.V. <pim@lingewoud.nl>          *
	* Copyright (c) 2006 MARIN Netherlands <info@marin.nl>                     *
	* -------------------------------------------------------------------------*
	*  This program is free software; you can redistribute it and/or modify it *
	*  under the terms of the GNU General Public License as published by the   *
	*  Free Software Foundation; either version 2 of the License, or (at your  *
	*  option) any later version.                                              *
	\**************************************************************************/

	/* $Id */

	$GLOBALS['egw_info'] = array();
	$GLOBALS['egw_info']['flags'] = array(
		'currentapp'              => 'cybro_profile',
		'noheader'                => True,
		'nonavbar'                => True,
		'enable_nextmatchs_class' => True
	);
	include('../header.inc.php');
	ExecMethod('cybro_profile.ui_cprofile.registration');

?>
