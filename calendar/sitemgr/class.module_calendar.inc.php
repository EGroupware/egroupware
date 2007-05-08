<?php
/**************************************************************************\
* eGroupWare SiteMgr - Web Content Management                              *
* http://www.egroupware.org                                                *
* --------------------------------------------                             *
*  This program is free software; you can redistribute it and/or modify it *
*  under the terms of the GNU General Public License as published by the   *
*  Free Software Foundation; either version 2 of the License, or (at your  *
*  option) any later version.                                              *
\**************************************************************************/

/* $Id: class.module_calendar.inc.php 19554 2005-11-02 14:37:42Z ralfbecker $ */

class module_calendar extends Module 
{
	function module_calendar()  
	{
		$this->arguments = array();

		$this->title = lang('Calendar');
		$this->description = lang('This module displays the current month');
 	}

	function get_content(&$arguments,$properties)
	{
		if (!is_object($GLOBALS['egw']->jscalendar))
		{
			$GLOBALS['egw']->jscalendar =& CreateObject('phpgwapi.jscalendar');
		}
		return $GLOBALS['egw']->jscalendar->flat('#');
	}
}
