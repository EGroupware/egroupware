<?php
	/**************************************************************************\
	* eGroupWare - Filemanager                                                 *
	* http://www.egroupware.org                                                *
	* ------------------------------------------------------------------------ *
	*  This program is free software; you can redistribute it and/or modify it *
	*  under the terms of the GNU General Public License as published by the   *
	*  Free Software Foundation; either version 2 of the License, or (at your  *
	*  option) any later version.                                              *
	\**************************************************************************/
	
	/* $Id$ */

	$GLOBALS['egw_info'] = array(
		'flags' => array(
			'currentapp'    => 'filemanager',
			'noheader'      => True,
			'nonavbar'      => True,
			'noappheader'   => True,
			'noappfooter'   => True,
			'nofooter'      => True,
		),
	);
	include('../header.inc.php');

	ExecMethod('filemanager.uifilemanager.index');
?>
