<?php
	/**************************************************************************\
	* eGroupWare - Preferences                                                 *
	* http://www.eGroupWare.org                                                *
	* --------------------------------------------                             *
	*  This file written by Edo van Bruggen <edovanbruggen@raketnet.nl>        * 
	*  This program is free software; you can redistribute it and/or modify it *
	*  under the terms of the GNU General Public License as published by the   *
	*  Free Software Foundation; either version 2 of the License, or (at your  *
	*  option) any later version.                                              *
	\**************************************************************************/
	
	
	create_section('Preferences for the idots2 template set');
	
	$clock_show = array(
		'yes' => lang('yes'),
		'no' => lang('no')
	);
	
	create_select_box(
		'Show clock?',
		'clock_show',
		$clock_show,
		'Would you like to display a clock on the right corner in the taskbar?'
	);
	
	$clock_min = array(
		'minute' => lang('minute'),
		'second' => lang('second')
	);
	
	create_select_box(
		'Update the clock per minute or per second',
		'clock_min',
		$clock_min,
		'If the clock is enabled would you like it to update it every second or every minute?'
	);
	
	$files = Array();
	$dir = '../phpgwapi/templates/idots2/images/backgrounds';
		
	$dh  = opendir($dir);
	$files['none'] = "none";
	while (false !== ($filename = readdir($dh))) 
	{
		if(strlen($filename) > 3)
		{
			$files[$filename] = $filename;
		}
	}
	closedir($dh);
	
	create_select_box(
		'Choose a background image.',
		'files',
		$files,
		'If there are some images in the background folder you can choose the one you would like to see.'
	);
	
	$bckStyle = array(
		'centered'	=> lang('centered'),
		'tiled'		=> lang('tiled'),
		'stretched'	=> lang('stretched')
	);
	
	create_select_box(
		'Choose a background style.',
		'bckStyle',
		$bckStyle,
		'What style would you like the image to have?'
	);
	
	create_input_box(
		'Choose a background color',
		'bgcolor',
		'What color should all the blank space on the desktop have',
		'#FFFFFF',
		7,
		7,
		'',
		false
	);
	
	$showLogo = array(
		'yes'       => lang('yes'),
		'no'	    => lang('no')
	);
	
	create_select_box(
		'Show logo\'s on the desktop.',
		'showLogo',
		$showLogo,
		'Show the logo\'s of eGroupware and x-desktop on the desktop.'
	);
	create_input_box(
		'Choose a background color for the icons',
		'bgcolor_icons',
		'',
		'#FFFFFF',
		7,
		7,
		'',
		false
	);
	
	$back_icons = array(
		'yes'       => lang('yes'),
		'no'	    => lang('no')
	);
	
	create_select_box(
		'Transparant bg for the icons?',
		'back_icons',
		$back_icons,
		''
	);
	create_input_box(
		'Choose a text color for the icons',
		'textcolor_icons',
		'',
		'#FFFFFF',
		7,
		7,
		'',
		false
	);

	create_check_box(
		'Show page generation time?',
		'show_generation_time',
		'Would you like to display the page generation time at the bottom of every window?'
	);
	
	create_input_box(
		'Default width for the windows',
		'scrWidth',
		'Select the default width for the application windows',
		'',
		'',
		'',
		'',
		false
	);
	
	create_input_box(
		'Default height for the windows',
		'scrHeight',
		'Select the default height for the application windows',
		'',
		'',
		'',
		'',
		false
	);
  

