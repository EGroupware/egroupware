<?php
	/**************************************************************************\
	* phpGroupWare - Info Log                                                  *
	* http://www.phpgroupware.org                                              *
	* Written by Ralf Becker <RalfBecker@outdoor-training.de>                  *
	* originaly based on todo written by Joseph Engo <jengo@phpgroupware.org>  *
	* --------------------------------------------                             *
	*  This program is free software; you can redistribute it and/or modify it *
	*  under the terms of the GNU General Public License as published by the   *
	*  Free Software Foundation; either version 2 of the License, or (at your  *
	*  option) any later version.                                              *
	\**************************************************************************/

	/* $Id$ */

	$GLOBALS['phpgw_info']['flags'] = array(
		'currentapp'	=> 'infolog', 
		'noheader'		=> True,
		'nonavbar'		=> True
	);
	include('../header.inc.php');

	header('Location: '.$GLOBALS['phpgw']->link('/index.php','menuaction=infolog.uiinfolog.get_list'));
	$GLOBALS['phpgw']->common->phpgw_exit();
?>
