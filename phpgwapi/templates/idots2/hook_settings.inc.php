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

   /* $Id$ */

   $clock_show = array(
	  'yes' => lang('yes'),
	  'no'  => lang('no')
   );

   $clock_min = array(
	  'minute' => lang('minute'),
	  'second' => lang('second')
   );

   $files = Array();
   $dir = 'phpgwapi/templates/idots2/images/backgrounds';

   $dh  = opendir($dir);
   $files['none'] = "none";
   while(false !== ($filename = readdir($dh))) 
   {
	  if(strlen($filename) > 3)
	  {
		 $files[$filename] = $filename;
	  }
   }
   closedir($dh);

   $bckStyle = array(
	  'centered'  => lang('centered'),
	  'tiled'     => lang('tiled'),
	  'stretched' => lang('stretched')
   );

   $showLogo = array(
	  'yes' => lang('yes'),
	  'no'  => lang('no')
   );

   $back_icons = array(
	  'yes' => lang('yes'),
	  'no'  => lang('no')
   );


   $GLOBALS['settings'] = array(
	  'prefssection' => array(
		 'type'  => 'section',
		 'title' => 'Preferences for the idots2 template set template set',
		 'xmlrpc' => False,
		 'admin'  => False
	  ),
	  'clock_show' => array(
		 'type'   => 'select',
		 'label'  => 'Show clock?',
		 'name'   => 'clock_show',
		 'values' => $clock_show,
		 'help'   => '',
		 'xmlrpc' => False,
		 'admin'  => False
	  ),
	  'clock_min' => array(
		 'type'  => 'select',
		 'label' => 'Update the clock per minute or per second',
		 'name'  => 'clock_min',
		 'help'  => 'If the clock is enabled would you like it to update it every second or every minute?',
		 'values' => $clock_min,
		 'xmlrpc' => False,
		 'admin'  => False
	  ),
	  'files' => array(
		 'type'  => 'select',
		 'label' => 'Choose a background image.',
		 'name'  => 'files',
		 'help'  => 'If there are some images in the background folder you can choose the one you would like to see.',
		 'values' => $files,
		 'xmlrpc' => False,
		 'admin'  => False
	  ),
	  'blkStyle' => array(
		 'type'   => 'select',
		 'label'  => 'Choose a background style.',
		 'name'   => 'blkStyle',
		 'values' => $bckStyle,
		 'help'   => 'What style would you like the image to have?',
		 'xmlrpc' => False,
		 'admin'  => False
	  ),
	  'bgcolor' => array(
		 'type'  => 'input',
		 'label' => 'Choose a background color',
		 'name'  => 'bgcolor',
		 'help'  => 'What color should all the blank space on the desktop have',
		 'default' => '#ffffff',
		 'size'=>'7',
		 'xmlrpc' => False,
		 'admin'  => False
	  ),
	  'showLogo' => array(
		 'type'  => 'select',
		 'label' => 'Show logo\'s on the desktop.',
		 'name'  => 'showLogo',
		 'help'  => 'Show the logo\'s of eGroupware and x-desktop on the desktop.',
		 'values' => $showLogo,
		 'xmlrpc' => False,
		 'admin'  => False
	  ),
	  'show_generation_time' => array(
		 'type'  => 'check',
		 'label' => 'Show page generation time',
		 'name'  => 'show_generation_time',
		 'help'  => 'Show page generation time on the bottom of the page?',
		 'xmlrpc' => False,
		 'admin'  => False
	  ),
	  'bgcolor_icons' => array(
		 'type'   => 'input',
		 'label' => 'Choose a background color for the icons',
		 'name' => 'bgcolor_icons',
		 'default'=> '#ffffff',
		 'size'=>'15',
	  ),
	  'back_icons' => array(
		 'type'   => 'select',
		 'label'=> 'Transparant bg for the icons?',
		 'name'=> 'back_icons',
		 'values'=> $back_icons,
	  ),
	  'textcolor_icons' => array(
		 'type'   => 'input',
		 'label'=>'Choose a text color for the icons',
		 'name'=>'textcolor_icons',
		 'default'=>'#FFFFFF',
		 'size'=>'15'
	  ),
	  'show_generation_time' => array(
		 'type'   => 'check',
		 'label'=>'Show page generation time?',
		 'name'=>'show_generation_time',
		 'help'=>'Would you like to display the page generation time at the bottom of every window?'
	  ),
	  'scrWidth' => array(
		 'type'   => 'input',
		 'label'=>'Default width for the windows',
		 'name'=>'scrWidth',
		 'help'=>'Select the default width for the application windows',
	  ),
	  'scrHeight' => array(
		 'type'   => 'input',
		 'label'=>'Default height for the windows',
		 'name'=>'scrHeight',
		 'help'=>'Select the default height for the application windows',
	  )

   );


