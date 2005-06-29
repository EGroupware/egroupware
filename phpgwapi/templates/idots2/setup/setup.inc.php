<?php
	/**************************************************************************\
	* eGroupWare - Idots2                                                      *
	* http://www.egroupware.org                                                *
	* --------------------------------------------                             *
	*  This program is free software; you can redistribute it and/or modify it *
	*  under the terms of the GNU General Public License as published by the   *
	*  Free Software Foundation; either version 2 of the License, or (at your  *
	*  option) any later version.                                              *
	\**************************************************************************/

	/* Basic information about this template */
	$GLOBALS['egw_info']['template']['idots2']['name']      = 'idots2';
	$GLOBALS['egw_info']['template']['idots2']['title']     = 'Idots 2';
	$GLOBALS['egw_info']['template']['idots2']['version']   = '0.3.0';

	$GLOBALS['egw_info']['template']['idots2']['author'] = 'Edo van Bruggen and Rob van Kraanen';
	$GLOBALS['egw_info']['template']['idots2']['license']  = 'GPL';
	$GLOBALS['egw_info']['template']['idots2']['windowed'] = True;
	$GLOBALS['egw_info']['template']['idots2']['icon'] = "phpgwapi/templates/idots2/images/logo-idots.png";
	$GLOBALS['egw_info']['template']['idots2']['maintainer'] = array(
		array('name' => 'Edo van Bruggen', 'email' => 'edovanbruggen@raketnet.nl'),
		array('name' => 'Rob van Kraanen', 'email' => 'rvkraanen@gmail.com')
	);

	$GLOBALS['egw_info']['template']['idots2']['description'] = "
	Based on <a href='http://www.x-desktop.org' target='_new'> x-Desktop </a>. <br/>
	The theme is based on the Retro skin for Windowsblind made by <a href='http://www.essorant.com' target='_new'>Tim Dagger</a>.";

	$GLOBALS['egw_info']['template']['idots2']['note'] = "
	<br/>
	<br/>
	++ Special thanks to ++ 
	<br/>
	<br/>
	<ul>
	   <li>Pim Snel <a href='http://www.lingewoud.nl' target='_new'>Lingewoud B.V.</a> for providing this oppurtunity and sponsoring.</li>
	   <li><a href='http://www.avans.nl' target='_new'>Avans Hogeschool Den Bosch</a> (Netherlands)</li>
	   <li>Tim Dagger for letting us use the Retro skin.</li>
	   <li>Coffee ;)</li>
	</ul>
	   ";

	/* Dependencies for this app to work */
	$GLOBALS['egw_info']['template']['idots2']['depends'][] = array(
		'appname' => 'phpgwapi',
		'versions' => Array('1.0.0')
	);
?>
