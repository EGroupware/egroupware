<?php
	/**************************************************************************\
	* phpGroupWare                                                             *
	* http://www.phpgroupware.org                                              *
	* Written by Dan Kuykendall <seek3r@phpgroupware.org>                      *
	* --------------------------------------------                             *
	*  This program is free software; you can redistribute it and/or modify it *
	*  under the terms of the GNU General Public License as published by the   *
	*  Free Software Foundation; either version 2 of the License, or (at your  *
	*  option) any later version.                                              *
	\**************************************************************************/

	/* $Id$ */

	// TODO:
	// Limit which users can access this program (ACL check)
	// Global disabler
	// Detect bad logins and passwords, spit out generic message

	// If your are going to use multiable accounts, remove the following lines
	$login  = 'anonymous';
	$passwd = 'anonymous';

	$GLOBALS['phpgw_info']['flags'] = array(
		'disable_Template_class' => True,
		'login' => True,
		'currentapp' => 'login',
		'noheader'  => True
	);
	include('./header.inc.php');

	$sessionid = $GLOBALS['phpgw']->session->create($login,$passwd);
	$GLOBALS['phpgw']->redirect($GLOBALS['phpgw']->link('/index.php'));
?>
