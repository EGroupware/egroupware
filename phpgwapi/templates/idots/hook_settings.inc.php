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
	
	create_input_box('Max number of icons in navbar','max_icons',
'How many icons should be shown in the navbar (top of the page). Additional icons go into a kind of pulldown menu, callable by the icon on the far right side of the navbar.','10',3);

	create_check_box('Show page generation time','show_generation_time',
	'Show page generation time on the bottom of the page?');
