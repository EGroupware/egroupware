<?php
	/**************************************************************************\
	* eGroupWare - eTemplates - Example App et_media                           *
	* http://www.egroupware.org                                                *
	* Written by Ralf Becker <RalfBecker@outdoor-training.de>                  *
	* --------------------------------------------                             *
	*  This program is free software; you can redistribute it and/or modify it *
	*  under the terms of the GNU General Public License as published by the   *
	*  Free Software Foundation; either version 2 of the License, or (at your  *
	*  option) any later version.                                              *
	\**************************************************************************/

	/* $Id$ */

	$GLOBALS['egw_info'] = array(
		'flags' => array(
			'currentapp'	=> 'et_media',
			'noheader'	=> True,
			'nonavbar'	=> True,
		),
	);
	include('../header.inc.php');

	$GLOBALS['egw']->redirect_link('/index.php','menuaction=et_media.et_media.edit');
