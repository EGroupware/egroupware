<?php
	/**************************************************************************\
	* phpGroupWare                                                             *
	* http://www.phpgroupware.org                                              *
	* --------------------------------------------                             *
	*  This program is free software; you can redistribute it and/or modify it *
	*  under the terms of the GNU General Public License as published by the   *
	*  Free Software Foundation; either version 2 of the License, or (at your  *
	*  option) any later version.                                              *
	\**************************************************************************/

	/* $Id$ */

	if (! $sessionid)
	{
		Header('Location: login.php');
		exit;
	}

	/*
		This is the preliminary menuaction driver for the new multi-layered design
	*/
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
	//$phpgw->log->message('W-BadmenuactionVariable, menuaction missing or corrupt: %1',$menuaction);
	//$phpgw->log->commit();

		$app = 'home';
		$invalid_data = True;
	}

	$phpgw_info['flags'] = array(
		'noheader'   => True,
		'nonavbar'   => True,
		'currentapp' => $app
	);
	include('./header.inc.php');

	if ($app == 'home')
	{
		Header('Location: ' . $phpgw->link('/home.php'));
	}

	$obj = CreateObject(sprintf('%s.%s',$app,$class));
	if ((is_array($obj->public_functions) && $obj->public_functions[$method]) && ! $invalid_data)
	{
		eval("\$obj->$method();");
	}
	else
	{
		Header('Location: ' . $phpgw->link('/home.php'));
		$phpgw->log->message('W-BadmenuactionVariable, menuaction missing or corrupt: %1',$menuaction);
		if (! is_array($obj->public_functions) || ! $obj->public_functions[$method])
		{
			$phpgw->log->message('W-BadmenuactionVariable, attempted to access private method: %1',$method);
		}
		$phpgw->log->commit();

		/*
		$_obj = CreateObject('home.home');
		$_obj->get_list();
		*/
	}

	$phpgw->common->phpgw_footer();
