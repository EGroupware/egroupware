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
	create_section('Preferences for the idots template set');

	$start_and_logout_icons = array(
		'yes'       => lang('yes'),
		'no' => lang('no')
	);

	create_select_box(
		'Show home and logout button in main application bar?',
		'start_and_logout_icons',
		$start_and_logout_icons,
		'When you say yes the home and logout buttons are presented as applications in the main top applcation bar.'
	);

	create_input_box(
		'Max number of icons in navbar',
		'max_icons',
		'How many icons should be shown in the navbar (top of the page). Additional icons go into a kind of pulldown menu, callable by the icon on the far right side of the navbar.','',3
	);

	create_check_box(
		'Autohide Sidebox menu\'s',
		'auto_hide_sidebox',
		'Automatically hide the Sidebox menu\'s?'
	);

	$click_or_onmouseover = array(
		'click'       => lang('Click'),
		'onmouseover' => lang('On Mouse Over')
	);

	create_select_box(
		'Click or Mouse Over to show menus',
		'click_or_onmouseover',
		$click_or_onmouseover,
		'Click or Mouse Over to show menus?'
	);

	create_check_box(
		'Disable slider effects',
		'disable_slider_effects',
		'Disable the animated slider effects when showing or hiding menus in the page? Opera and Konqueror users will probably must want this.'
	);

	create_check_box(
		'Disable Internet Explorer png-image-bugfix',
		'disable_pngfix',
		'Disable the execution a bugfixscript for Internet Explorer 5.5 and higher to show transparency in PNG-images?'
	);

	create_check_box(
		'Show page generation time',
		'show_generation_time',
		'Show page generation time on the bottom of the page?'
	);
