<?php
	/**************************************************************************\
	* phpGroupWare - eTemplates - process_exec                                 *
	* http://www.phpgroupware.org                                              *
	* Written by Ralf Becker <RalfBecker@outdoor-training.de>                  *
	* --------------------------------------------                             *
	*  This program is free software; you can redistribute it and/or modify it *
	*  under the terms of the GNU General Public License as published by the   *
	*  Free Software Foundation; either version 2 of the License, or (at your  *
	*  option) any later version.                                              *
	\**************************************************************************/

	/* $Id$ */

	$GLOBALS['phpgw_info']['flags'] = array(
		'currentapp'	=> $GLOBALS['HTTP_POST_VARS']['app'],
		'noheader'		=> True,
		'nonavbar'		=> True
	);
	include('../header.inc.php');

	ExecMethod('etemplate.etemplate.process_exec');

	$GLOBALS['phpgw']->common->phpgw_footer();
