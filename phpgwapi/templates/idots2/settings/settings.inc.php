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
	$template_info['idots2']['name']      = 'idots2';
	$template_info['idots2']['title']     = 'Idots 2';
	$template_info['idots2']['version']   = '0.3.0';


	$template_info['idots2']['author'] = 'Edo van Bruggen and Rob van Kraanen';
	$template_info['idots2']['license']  = 'GPL';
	$template_info['idots2']['windowed'] = True;
	$template_info['idots2']['icon'] = "phpgwapi/templates/idots2/images/logo-idots.png";
	$template_info['idots2']['maintainer'] = array(
		array('name' => 'Edo van Bruggen', 'email' => 'edovanbruggen@raketnet.nl'),
		array('name' => 'Rob van Kraanen', 'email' => 'rvkraanen@gmail.com')
	);
	$template_info['idots2']['description'] = "Based on <a href='http://www.x-desktop.org' target='_new'> x- Desktop </a>. <br>The theme is based on the Retro skin for Windowsblind made by <a href='http://www.essorant.com' target='_new'>Tim Dagger</a>.";
	$template_info['idots2']['note'] = "<br><br>++ Special thanks to ++ <br><br>- Pim Snel / <a href='http://www.lingewoud.nl' target='_new'>Lingewoud B.V.</a> for providing this oppurtunity and sponsoring.<br>	- <a href='http://www.avans.nl' target='_new'>Avans Hogeschool Den Bosch</a> (Netherlands)<br>- Tim Dagger for letting us use the Retro skin.<br>- Coffee ;)";

	/* Dependencies for this app to work */
	$template_info['idots2']['depends'][] = array(
		'appname' => 'phpgwapi',
		'versions' => Array('1.0.0')
	);
?>
