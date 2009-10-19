<?php
   /**************************************************************************\
   * eGroupWare - Preferences                                                 *
   * http://www.eGroupWare.org                                                *
   * --------------------------------------------                             *
   *  This program is free software; you can redistribute it and/or modify it *
   *  under the terms of the GNU General Public License as published by the   *
   *  Free Software Foundation; either version 2 of the License, or (at your  *
   *  option) any later version.                                              *
   \**************************************************************************/

   /* $Id$ */

   $top_menu = array(
	  'topmenu' => lang('Show as Topmenu'),
	  'sidebox' => lang('Show in sidebox')
   );
   $yes_no = array(
	  'yes' => lang('yes'),
	  'no'  => lang('no')
   );
   $click_or_onmouseover = array(
	  'click'       => lang('Click'),
	  'onmouseover' => lang('On Mouse Over')
   );

   $GLOBALS['settings'] = array(
	  'prefssection' => array(
		 'type'  => 'section',
		 'title' => 'Preferences for the idots template set',
		 'xmlrpc' => False,
		 'admin'  => False,
	  ),
	  'show_general_menu' => array(
		 'type'   => 'select',
		 'label'  => 'How to show the general eGroupWare menu ?',
		 'name'   => 'show_general_menu',
		 'values' => $top_menu,
		 'help'   => 'Where and how will the egroupware links like preferences, about and logout be displayed.',
		 'xmlrpc' => False,
		 'admin'  => False,
		 'forced' => 'topmenu',
	  ),
	  'start_and_logout_icons' => array(
		 'type'   => 'select',
		 'label'  => 'Show home and logout button in main application bar?',
		 'name'   => 'start_and_logout_icons',
		 'values' => $yes_no,
		 'help'   => 'When you say yes the home and logout buttons are presented as applications in the main top applcation bar.',
		 'xmlrpc' => False,
		 'admin'  => False,
		 'forced' => 'yes',
	  ),
	  'max_icons' => array(
		 'type'  => 'input',
		 'label' => 'Max number of icons in navbar',
		 'name'  => 'max_icons',
		 'help'  => 'How many icons should be shown in the navbar (top of the page). Additional icons go into a kind of pulldown menu, callable by the icon on the far right side of the navbar.',
		 'size'   => 3,
		 'xmlrpc' => False,
		 'admin'  => False,
		 'forced' => 12,
	  ),
	  'auto_hide_sidebox' => array(
		 'type'  => 'check',
		 'label' => 'Autohide Sidebox menu\'s',
		 'name'  => 'auto_hide_sidebox',
		 'help'  => 'Automatically hide the Sidebox menu\'s?',
		 'xmlrpc' => False,
		 'admin'  => False,
		 'default' => true,
	  ),
	  'click_or_onmouseover' => array(
		 'type'   => 'select',
		 'label'  => 'Click or Mouse Over to show menus',
		 'name'   => 'click_or_onmouseover',
		 'values' => $click_or_onmouseover,
		 'help'   => 'Click or Mouse Over to show menus?',
		 'xmlrpc' => False,
		 'admin'  => False,
		 'forced' => 'click',
	  ),
	  'disable_slider_effects' => array(
		 'type'  => 'check',
		 'label' => 'Disable slider effects',
		 'name'  => 'disable_slider_effects',
		 'help'  => 'Disable the animated slider effects when showing or hiding menus in the page? Opera and Konqueror users will probably must want this.',
		 'xmlrpc' => False,
		 'admin'  => False,
		 'forced' => false,
	  ),
	  'disable_pngfix' => array(
		 'type'  => 'check',
		 'label' => 'Disable Internet Explorer png-image-bugfix',
		 'name'  => 'disable_pngfix',
		 'help'  => 'Disable the execution a bugfixscript for Internet Explorer 5.5 and higher to show transparency in PNG-images?',
		 'xmlrpc' => False,
		 'admin'  => False,
		 'forced' => false,
	  ),
	  'show_generation_time' => array(
		 'type'  => 'check',
		 'label' => 'Show page generation time',
		 'name'  => 'show_generation_time',
		 'help'  => 'Show page generation time on the bottom of the page?',
		 'xmlrpc' => False,
		 'admin'  => False,
		 'forced' => false,
	  )
   );
