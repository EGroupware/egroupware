<?php
/**************************************************************************\
* eGroupWare                                                               *
* http://www.egroupware.org                                                *
* This file is written by Edo van Bruggen <edovanbruggen@raketnet.nl>      *
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
	header( "Expires: Mon, 26 Jul 1997 05:00:00 GMT" ); 
	header( "Last-Modified: " . gmdate( "D, d M Y H:i:s" ) . "GMT" ); 
	header( "Cache-Control: no-cache, must-revalidate" ); 
	header( "Pragma: no-cache" );
	echo '<?xml version="1.0" encoding="UTF-8"
	standalone="yes"?>';
	echo "\r\n<response>\r\n";


	$title = $_GET["title"];
	$width = $_GET["w"];
	$height= $_GET["h"];
	echo $title." ".$width."  ".$height;
	$GLOBALS['phpgw']->preferences->read_repository();

	foreach($GLOBALS['phpgw_info']['user']['apps'] as $name => $data)
	{
		if($data['title'] == $title) {
			$size['name'] = $name;
			$size['width'] = $width;
			$size['height'] = $height;
			$GLOBALS['phpgw']->preferences->change('phpgwapi','size_'.$name,$size);
			$GLOBALS['phpgw']->preferences->save_repository(True);
			
			
		}
	}
	echo "</response>";
	
?>
