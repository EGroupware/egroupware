<?php
	/**************************************************************************\
	* eGroupWare                                                               *
	* http://www.egroupware.org                                                *
	* This file is written by Rob van Kraanen <rvkraanen@gmail.com>            *
	* --------------------------------------------                             *
	*  This program is free software; you can redistribute it and/or modify it *
	*  under the terms of the GNU General Public License as published by the   *
	*  Free Software Foundation; either version 2 of the License, or (at your  *
	*  option) any later version.                                              *
	\**************************************************************************/
		
	$phpgw_info = array();
	$GLOBALS['phpgw_info']['flags'] = Array(
		'currentapp'	=>	'home',
	);
		
	include('../../../header.inc.php');
	
	
	$GLOBALS['idots_tpl'] = createobject('phpgwapi.Template',PHPGW_TEMPLATE_DIR);
	
	
	$GLOBALS['idots_tpl']->set_file(
		array(
			'add_shortcut' => 'add_shortcut.tpl'
		)
	);
	
	$GLOBALS['idots_tpl']->set_block('add_shortcut','formposted','formposted');
	

	/*
	**If a form is posted
	**
	*/
	if(isset($_POST['submit']) && $_POST['submit'] == lang("Add"))
	{
		$GLOBALS['phpgw']->preferences->read_repository();
		$app_data = $GLOBALS['phpgw_info']['navbar'][$_POST['select']];
	
		if(!empty($app_data['name']))
		{
			$shortcut_data = Array(
				'title'=>  $app_data['name'],
				'icon'=>   $app_data['icon'],
				'link'=>   $app_data['url'],
				'top'=>    $_POST['hitTop'],
				'left'=>   $_POST['hitLeft'],
				'type'=>   'app'
			);
			
			$name = $app_data['name'];
			$title = $app_data['title'];
			$url = $app_data['url'];
			$img = $app_data['icon'];
			$type = 'arr';
			$shortcut = $app_data['name'];
			
			$GLOBALS['phpgw']->preferences->change('phpgwapi',$shortcut,$shortcut_data);
			$GLOBALS['phpgw']->preferences->save_repository(True);
		}
		
		$var['title'] = $title;
		$var['url'] = $url;
		$var['img'] = $img;
		$var['type'] = $type;
		$var['hitTop']=   $_POST['hitTop'];
		$var['hitLeft']=  $_POST['hitLeft'];
		
		
		$GLOBALS['idots_tpl']->set_var($var);
		$GLOBALS['idots_tpl']->pfp('out','formposted');
		
	}
	else
	{
		
		$GLOBALS['idots_tpl']->set_block('add_shortcut','jscript','jscript');
		$GLOBALS['idots_tpl']->set_block('add_shortcut','css','css');
	
		$GLOBALS['idots_tpl']->set_block('add_shortcut','selstart','selstart');
	
		$GLOBALS['idots_tpl']->set_block('add_shortcut','shortcut','shortcut');
		
		$GLOBALS['idots_tpl']->set_block('add_shortcut','img','img');
		
		$GLOBALS['idots_tpl']->set_block('add_shortcut','selend','selend');
		
		$var['appNames'] = "";
		$var['appUrls'] = "";
		$first = true;
		foreach($GLOBALS['phpgw_info']['navbar'] as $app => $app_data)
		{
			
			if($first == true)
			{
				$var['appNames'] .= $app_data['name'];
				$var['appUrls'] .= $app_data['icon'];
				$starturl = $app_data['icon'];
				$first = false;
			}
			else
			{
				$var['appNames'] .= ",".$app_data['name'];
				$var['appUrls'] .= ",".$app_data['icon'];
			}
				
		}
		
		$GLOBALS['idots_tpl']->set_var($var);
		
		$GLOBALS['idots_tpl']->pfp('out','jscript');
		$GLOBALS['idots_tpl']->pfp('out','css');
	
		$var["selName"] = lang("Application");
		$GLOBALS['idots_tpl']->set_var($var);
		$GLOBALS['idots_tpl']->pfp('out','selstart');
		foreach($GLOBALS['phpgw_info']['navbar'] as $app => $app_data)
		{
			$found = false;
			foreach($GLOBALS['phpgw_info']['user']['preferences']['phpgwapi'] as $shortcut=> $shortcut_data)
			{
				if($shortcut_data['title'] == $app_data['title'])
				{
					$found = true;
				}
			}
				if($found ==false)
				{
					$var['item'] = lang($app_data['title']);
					$var['name'] = $app_data['name'];
					$GLOBALS['idots_tpl']->set_var($var);
					$GLOBALS['idots_tpl']->pfp('out','shortcut');
				}
		}
	
		$var["buttonName"]=lang("Add");
		$GLOBALS['idots_tpl']->set_var($var);
		$GLOBALS['idots_tpl']->pfp('out','selend');
		$var['starturl'] = $starturl;
		$GLOBALS['idots_tpl']->set_var($var);
		$GLOBALS['idots_tpl']->pfp('out','img');
	}
	
?>
