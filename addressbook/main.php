<?php
	/**************************************************************************\
	* phpGroupWare - Messenger                                                 *
	* http://www.phpgroupware.org                                              *
	* This application written by Joseph Engo <jengo@phpgroupware.org>         *
	* --------------------------------------------                             *
	* Funding for this program was provided by http://www.checkwithmom.com     *
	* --------------------------------------------                             *
	*  This program is free software; you can redistribute it and/or modify it *
	*  under the terms of the GNU General Public License as published by the   *
	*  Free Software Foundation; either version 2 of the License, or (at your  *
	*  option) any later version.                                              *
	\**************************************************************************/

	/* $Id$ */

	if ($menuaction)
	{
		list($app,$class,$method) = explode('.',$menuaction);
		if (! $app || ! $class || ! $method)
		{
			$invalid_data = True;
		}
	}
	else
	{
		$app = 'home';
		$invalid_data = True;
	}

	$phpgw_info['flags'] = array(
		'noheader'   => True,
		'nonavbar'   => True,
		'currentapp' => $app
	);
	include('../header.inc.php');

	$obj = CreateObject(sprintf('%s.%s',$app,$class));
	if ((is_array($obj->public_functions) && $obj->public_functions[$method]) && ! $invalid_data)
	{
		eval("\$obj->$method();");
	}
	else
	{
		$_obj = CreateObject('addressbook.uiaddressbook');
		$_obj->get_list();
	}
?>
