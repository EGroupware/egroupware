<?php
  /**************************************************************************\
  * eGroupWare - Calendar                                                    *
  * http://www.egroupware.org                                                *
  * Based on Webcalendar by Craig Knudsen <cknudsen@radix.net>               *
  *          http://www.radix.net/~cknudsen                                  *
  * Modified by Mark Peters <skeeter@phpgroupware.org>                       *
  * --------------------------------------------                             *
  *  This program is free software; you can redistribute it and/or modify it *
  *  under the terms of the GNU General Public License as published by the   *
  *  Free Software Foundation; either version 2 of the License, or (at your  *
  *  option) any later version.                                              *
  \**************************************************************************/

	/* $Id$ */

	class calendarmenu
	{


		function calendarmenu()
		{
			$menu = Array();
			$menu['File'] = Array(
				'New Entry'   => $GLOBALS['phpgw']->link('/index.php','menuaction=calendar.uicalendar.add'),
				'Export'=>$GLOBALS['phpgw']->link('/index.php','menuaction=calendar.uicalendar.export'),
				'Import'=>$GLOBALS['phpgw']->link('/index.php','menuaction=calendar.uiicalendar.import')
				);
				
			$menu['View'] = Array(
				'Today'=>$GLOBALS['phpgw']->link('/index.php','menuaction=calendar.uicalendar.day'),
				'This week'=>$GLOBALS['phpgw']->link('/index.php','menuaction=calendar.uicalendar.week'),
				'This month'=>$GLOBALS['phpgw']->link('/index.php','menuaction=calendar.uicalendar.month'),
				'This year'=>$GLOBALS['phpgw']->link('/index.php','menuaction=calendar.uicalendar.year'),
				'Group Planner'=>$GLOBALS['phpgw']->link('/index.php','menuaction=calendar.uicalendar.planner'),
				'Daily Matrix'=>$GLOBALS['phpgw']->link('/index.php','menuaction=calendar.uicalendar.matrixselect')
				);
				
			$menu['Preferences'] = Array(
				'Calendar preferences'=>$GLOBALS['phpgw']->link('/preferences/preferences.php','appname=calendar'),
				'Grant Access'=>$GLOBALS['phpgw']->link('/index.php','menuaction=preferences.uiaclprefs.index&acl_app=calendar'),
				'Edit Categories' => $GLOBALS['phpgw']->link('/index.php','menuaction=preferences.uicategories.index&cats_app=calendar&cats_level=True&global_cats=True')
				);
			
			if ($GLOBALS['phpgw_info']['user']['apps']['admin']) {
				$menu['Administration'] = Array(
					'Configuration'=>$GLOBALS['phpgw']->link('/index.php','menuaction=admin.uiconfig.index&appname=calendar'),
					'Custom Fields'=>$GLOBALS['phpgw']->link('/index.php','menuaction=calendar.uicustom_fields.index'),
					'Holiday Management'=>$GLOBALS['phpgw']->link('/index.php','menuaction=calendar.uiholiday.admin'),
					'Import CSV-File' => $GLOBALS['phpgw']->link('/calendar/csv_import.php'),
					'Global Categories' =>$GLOBALS['phpgw']->link('/index.php','menuaction=admin.uicategories.index&appname=calendar')
					);
			}
		
		
			return $menu;
		}
		
	}	
?>
