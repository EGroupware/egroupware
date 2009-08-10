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

/* $Id: class.module_calendar.inc.php,v 1.4 2008-12-29 19:01:26 hjtappe Exp $ */

class module_calendar extends Module 
{
	function module_calendar()  
	{
		$this->arguments = array(
			'redirect' => array(
				'type' => 'textfield',
				'label' => lang('Specify where URL of the day links to'),
			),
		);

		$this->title = lang('Calendar');
		$this->description = lang('This module displays the current month');
 	}

	function get_content(&$arguments,$properties)
	{
		if (!is_object($GLOBALS['egw']->jscalendar))
		{
			$GLOBALS['egw']->jscalendar =& CreateObject('phpgwapi.jscalendar');
		}
		$date = (int) (strtotime(get_var('date',array('POST','GET'))));
		$redirect = $arguments['redirect'] ? $arguments['redirect'] : '#';
		return $GLOBALS['egw']->jscalendar->flat($redirect,$date);
	}
}
