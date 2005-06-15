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
	if(!file_exists('header.inc.php'))
	{
		Header('Location: setup/index.php');
		exit;
	}

	$GLOBALS['sessionid'] = isset($_GET['sessionid']) ? $_GET['sessionid'] : @$_COOKIE['sessionid'];
	if(!$GLOBALS['sessionid'])
	{
		Header('Location: login.php'.
			(isset($_SERVER['QUERY_STRING']) && !empty($_SERVER['QUERY_STRING']) ?
			'?phpgw_forward='.urlencode('/index.php?'.$_SERVER['QUERY_STRING']):''));
		exit;
	}
	
	
	if(isset($_GET['hasupdates']) && $_GET['hasupdates'] == "yes") {
		$hasupdates = True;
	}

	/*
		This is the menuaction driver for the multi-layered design
	*/
	if(isset($_GET['menuaction']))
	{
		list($app,$class,$method) = explode('.',@$_GET['menuaction']);
		if(! $app || ! $class || ! $method)
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

	
	if($app == 'phpgwapi')
	{
		$app = 'home';
		$api_requested = True;
	}
	

	$GLOBALS['phpgw_info']['flags'] = array(
		'noheader'   => True,
		'nonavbar'   => True,
		'enable_network_class'    => True,
		'enable_contacts_class'   => True,
		'enable_nextmatchs_class' => True,
		'currentapp' => $app
	);
	include('./header.inc.php');
	
	
// 	Check if we are using windows or normal webpage
	$windowed = false;
	$settings = PHPGW_SERVER_ROOT . '/phpgwapi/templates/' . $GLOBALS['phpgw_info']['user']['preferences']['common']['template_set'] . '/settings/settings.inc.php';
 	
	if(@file_exists($settings))
	{
		
		include($settings);
		if(isset($template_info)) {
			if($template_info['idots2']['windowed'])
			{
				$windowed = true;
				
			}	
		}
	}
				
	
	if($app == 'home' && !$api_requested && !$windowed)
	{
		if ($GLOBALS['phpgw_info']['server']['force_default_app'] && $GLOBALS['phpgw_info']['server']['force_default_app'] != 'user_choice')
		{
			$GLOBALS['phpgw_info']['user']['preferences']['common']['default_app'] = $GLOBALS['phpgw_info']['server']['force_default_app'];
			
		}
		if($GLOBALS['phpgw_info']['user']['preferences']['common']['default_app'] && !$hasupdates) {
			
			Header('Location: ' . $GLOBALS['phpgw']->link('/'.$GLOBALS['phpgw_info']['user']['preferences']['common']['default_app'].'/index.php'));
			exit();
		}
		else {
			Header('Location: ' . $GLOBALS['phpgw']->link('home/index.php'));
		}
			
	}

	if($windowed && $_GET['cd'] == "yes") 
	{
		
		$GLOBALS['phpgw_info']['flags'] = array(
			'noheader'   => False,
			'nonavbar'   => False,
			'enable_network_class'    => True,
			'enable_contacts_class'   => True,
			'enable_nextmatchs_class' => True,
			'currentapp' => 'eGroupWare'
		);
		$GLOBALS['phpgw']->common->phpgw_header();
		$GLOBALS['phpgw']->common->phpgw_footer();

	}
	else {
		if($api_requested)
		{
			
			$app = 'phpgwapi';
		}
		
		$GLOBALS[$class] = CreateObject(sprintf('%s.%s',$app,$class));
		if((is_array($GLOBALS[$class]->public_functions) && $GLOBALS[$class]->public_functions[$method]) && ! $invalid_data)
		{
			execmethod($_GET['menuaction']);
			unset($app);
			unset($class);
			unset($method);
			unset($invalid_data);
			unset($api_requested);
		}
		else
		{
			if(!$app || !$class || !$method)
			{
				if(@is_object($GLOBALS['phpgw']->log))
				{
					$GLOBALS['phpgw']->log->message(array(
						'text' => 'W-BadmenuactionVariable, menuaction missing or corrupt: %1',
						'p1'   => $menuaction,
						'line' => __LINE__,
						'file' => __FILE__
					));
				}
			}
	
			if(!is_array($GLOBALS[$class]->public_functions) || ! $$GLOBALS[$class]->public_functions[$method] && $method)
			{
				if(@is_object($GLOBALS['phpgw']->log))
				{
					$GLOBALS['phpgw']->log->message(array(
						'text' => 'W-BadmenuactionVariable, attempted to access private method: %1',
						'p1'   => $method,
						'line' => __LINE__,
						'file' => __FILE__
					));
				}
			}
			if(@is_object($GLOBALS['phpgw']->log))
			{
				$GLOBALS['phpgw']->log->commit();
			}
			
			$GLOBALS['phpgw']->redirect_link('home/index.php');
		}
	
		if(!isset($GLOBALS['phpgw_info']['nofooter']))
		{
			$GLOBALS['phpgw']->common->phpgw_footer();
		}
	}
?>
