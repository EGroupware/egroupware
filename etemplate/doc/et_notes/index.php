<?php
	/**************************************************************************\
	* phpGroupWare - Notes eTemplate Port                                      *
	* http://www.phpgroupware.org                                              *
	* Written by Bettina Gille [ceb@phpgroupware.org]                          *
	* Ported to eTemplate by Ralf Becker [ralfbecker@outdoor-training.de]      *
	* -----------------------------------------------                          *
	*  This program is free software; you can redistribute it and/or modify it *
	*  under the terms of the GNU General Public License as published by the   *
	*  Free Software Foundation; either version 2 of the License, or (at your  *
	*  option) any later version.                                              *
	\**************************************************************************/
	/* $Id$ */

	$GLOBALS['phpgw_info']['flags'] = array
	(
		'currentapp' => 'et_notes',
		'noheader'   => True,
		'nonavbar'   => True
	);
	include('../header.inc.php');

	header('Location: '.$GLOBALS['phpgw']->link('/index.php','menuaction=et_notes.ui.index'));
	$GLOBALS['phpgw']->common->phpgw_exit();
?>
