<?php
	/**************************************************************************\
	* phpGroupWare - Info Log administration                                   *
	* http://www.phpgroupware.org                                              *
	* --------------------------------------------                             *
	*  This program is free software; you can redistribute it and/or modify it *
	*  under the terms of the GNU General Public License as published by the   *
	*  Free Software Foundation; either version 2 of the License, or (at your  *
	*  option) any later version.                                              *
	\**************************************************************************/

	/* $Id$ */

	$imgpath = $phpgw->common->image($appname,'navbar.gif');
	section_start($appname,$imgpath);

	section_item($phpgw->link('/infolog/csv_import.php'),lang('CSV-Import'));

	section_end();
?>