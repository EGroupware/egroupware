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

	$GLOBALS['sessionid'] = $GLOBALS['HTTP_GET_VARS']['sessionid'] ? $GLOBALS['HTTP_GET_VARS']['sessionid'] : $GLOBALS['HTTP_COOKIE_VARS']['sessionid'];
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

	$GLOBALS['phpgw_info']['flags'] = array(
		'noheader'   => True,
		'nonavbar'   => True,
		'currentapp' => $app
	);
	include('./header.inc.php');

	if ($app == 'home')
	{
		Header('Location: ' . $GLOBALS['phpgw']->link('/home.php'));
	}

	$GLOBALS['obj'] = CreateObject(sprintf('%s.%s',$app,$class));
	if ((is_array($GLOBALS['obj']->public_functions) && $GLOBALS['obj']->public_functions[$method]) && ! $invalid_data)
	{
		eval("\$GLOBALS['obj']->$method();");
	}
	else
	{
		Header('Location: ' . $GLOBALS['phpgw']->link('/home.php'));
		$GLOBALS['phpgw']->log->message(array('text'=>'W-BadmenuactionVariable, menuaction missing or corrupt: %1','p1'=>$menuaction));
		if (! is_array($obj->public_functions) || ! $obj->public_functions[$method])
		{
			$GLOBALS['phpgw']->log->message(array('text'=>'W-BadmenuactionVariable, attempted to access private method: %1','p1'=>$method));
		}
		$GLOBALS['phpgw']->log->commit();

		/*
		$_obj = CreateObject('home.home');
		$_obj->get_list();
		*/
	}

	$GLOBALS['phpgw']->common->phpgw_footer();
?>
