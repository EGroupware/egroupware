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

	$phpgw_info = array();
	$GLOBALS['sessionid'] = @$GLOBALS['HTTP_GET_VARS']['sessionid'] ? @$GLOBALS['HTTP_GET_VARS']['sessionid'] : @$GLOBALS['HTTP_COOKIE_VARS']['sessionid'];
	if (! $GLOBALS['sessionid'])
	{
		Header('Location: login.php');
		exit;
	}

	/*
		This is the preliminary menuaction driver for the new multi-layered design
	*/
	if (@isset($GLOBALS['HTTP_GET_VARS']['menuaction']))
	{
		list($app,$class,$method) = explode('.',$GLOBALS['HTTP_GET_VARS']['menuaction']);
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

	// FIX ME! Don't leave this, we need to create a common place where applications can access
	// things like the spell check class that the API has. (jengo)
	if ($app == 'phpgwapi')
	{
		$app = 'home';
		$api_requested = True;
	}

	$GLOBALS['phpgw_info']['flags'] = array(
		'noheader'   => True,
		'nonavbar'   => True,
		'currentapp' => $app
	);
	include('./header.inc.php');

	if ($app == 'home' && ! $api_requested)
	{
		Header('Location: ' . $GLOBALS['phpgw']->link('/home.php'));
	}

	if ($api_requested)
	{
		$app = 'phpgwapi';
	}

	$GLOBALS['obj'] = CreateObject(sprintf('%s.%s',$app,$class));
	$GLOBALS[$class] = $GLOBALS['obj'];
	if ((is_array($GLOBALS[$class]->public_functions) && $GLOBALS[$class]->public_functions[$method]) && ! $invalid_data)
	{
//		eval("\$GLOBALS['obj']->$method();");
		execmethod($GLOBALS['HTTP_GET_VARS']['menuaction']);

		$GLOBALS['phpgw']->common->stop_xslt_capture();	// send captured output to the xslttpl

		unset($app);
		unset($obj);
		unset($class);
		unset($method);
		unset($invalid_data);
		unset($api_requested);
	}
	else
	{
		if (! $app || ! $class || ! $method)
		{
			$GLOBALS['phpgw']->log->message(array(
				'text' => 'W-BadmenuactionVariable, menuaction missing or corrupt: %1',
				'p1'   => $menuaction,
				'line' => __LINE__,
				'file' => __FILE__
			));
		}

		if (! is_array($obj->public_functions) || ! $obj->public_functions[$method] && $method)
		{
			$GLOBALS['phpgw']->log->message(array(
				'text' => 'W-BadmenuactionVariable, attempted to access private method: %1',
				'p1'   => $method,
				'line' => __LINE__,
				'file' => __FILE__
			));
		}
		$GLOBALS['phpgw']->log->commit();

		$phpgw->redirect($GLOBALS['phpgw']->link('/home.php'));
		/*
		$_obj = CreateObject('home.home');
		$_obj->get_list();
		*/
	}
?>
