<?php
	/**************************************************************************\
	* eGroupWare - Addressbook                                                 *
	* http://www.egroupware.org                                                *
	* --------------------------------------------                             *
	*  This program is free software; you can redistribute it and/or modify it *
	*  under the terms of the GNU General Public License as published by the   *
	*  Free Software Foundation; either version 2 of the License, or (at your  *
	*  option) any later version.                                              *
	\**************************************************************************/

	/* $Id$ */

	$GLOBALS['egw_info'] = array(
		'flags' => array(
			'currentapp' => 'addressbook',
			'noheader'   => True,
			'nonavbar'   => True
	));
	include('../header.inc.php');

	// check if we have an advanced search and reset it in case
	$old_state = $GLOBALS['egw']->session->appsession('index','addressbook');
	if ($old_state['advanced_search'])
	{
		unset($old_state['advanced_search']);
		$GLOBALS['egw']->session->appsession('index','addressbook',$old_state);
	}
	$GLOBALS['egw']->redirect_link('/index.php','menuaction=addressbook.uicontacts.index');
