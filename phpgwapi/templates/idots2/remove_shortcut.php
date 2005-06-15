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
$GLOBALS['phpgw_info']['flags'] = array(
	'noheader' => True,
	'nonavbar' => True,
	'disable_Template_class' => True,
	'currentapp' => 'notifywindow'
);
include('../../../header.inc.php');
header("Content-type: text/xml");
echo '<?xml version="1.0" encoding="UTF-8"
 standalone="yes"?>';
// echo "\r\n<response>\r\n";
// echo "\r\n<title>\r\n";
	$id = $_GET["id"];

	print_r($GLOBALS['phpgw_info']['user']['apps'][$id]['title']);
//	echo("test");
	$GLOBALS['phpgw']->preferences->read_repository();
	
	if($GLOBALS['phpgw_info']['user']['preferences']['phpgwapi'])
	{
		foreach($GLOBALS['phpgw_info']['user']['preferences']['phpgwapi'] as $shortcut => $shortcut_data)
		{
			if($shortcut_data['title']== $id)
			{
				$GLOBALS['phpgw']->preferences->delete('phpgwapi',$shortcut);
				$GLOBALS['phpgw']->preferences->save_repository(True);
			}
		}
	}
    echo "\r\n</title>\r\n";

	echo "</response>"; 
	
?>
