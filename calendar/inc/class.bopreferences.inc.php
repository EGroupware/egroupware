<?php
  /**************************************************************************\
  * phpGroupWare - Calendar                                                  *
  * http://www.phpgroupware.org                                              *
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

	class bopreferences
	{
		var $public_functions = Array(
			'preferences'  => True
		);

		var $prefs;
		var $debug = False;

		function bopreferences()
		{
			$GLOBALS['phpgw']->nextmatchs = CreateObject('phpgwapi.nextmatchs');
			$this->prefs['calendar']    = $GLOBALS['phpgw_info']['user']['preferences']['calendar'];
		}

		function preferences()
		{
			if (isset($GLOBALS['HTTP_POST_VARS']['submit']))
			{
				$GLOBALS['phpgw']->preferences->read_repository();
				
				$pref_list = Array(
					'weekdaystarts',
					'workdaystarts',
					'workdayends',
					'defaultcalendar',
					'defaultfilter',
					'interval'
				);

				for($i=0;$i<count($pref_list);$i++)
				{
					$GLOBALS['phpgw']->preferences->add('calendar',$pref_list[$i],$GLOBALS['HTTP_POST_VARS']['prefs'][$pref_list[$i]]);
				}

				$pref_list = Array(
					'mainscreen_showevents',
					'send_updates',
					'display_status',
					'default_private',
					'display_minicals',
					'print_black_white',
					'weekdays_only'
				);

				for($i=0;$i<count($pref_list);$i++)
				{
					if ($GLOBALS['HTTP_POST_VARS']['prefs'][$pref_list[$i]] == True)
					{
						$GLOBALS['phpgw']->preferences->add('calendar',$pref_list[$i],$GLOBALS['HTTP_POST_VARS']['prefs'][$pref_list[$i]]);
					}
					else
					{
						$GLOBALS['phpgw']->preferences->delete('calendar',$pref_list[$i]);
					}
				}

				$GLOBALS['phpgw']->preferences->save_repository(True);

				Header('Location: '.$GLOBALS['phpgw']->link('/preferences/index.php'));
				$GLOBALS['phpgw']->common->phpgw_exit();
			}
		}
	}
?>
