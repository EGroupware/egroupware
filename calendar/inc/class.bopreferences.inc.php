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
			global $GLOBALS;
			
			$GLOBALS['phpgw']->nextmatchs = CreateObject('phpgwapi.nextmatchs');

			$this->prefs['calendar']    = $GLOBALS['phpgw_info']['user']['preferences']['calendar'];
		}

		function preferences()
		{
			global $GLOBALS, $HTTP_POST_VARS;
      	
			if (isset($HTTP_POST_VARS['submit']))
			{
				$GLOBALS['phpgw']->preferences->read_repository();
				$GLOBALS['phpgw']->preferences->add('calendar','weekdaystarts',$HTTP_POST_VARS['prefs']['weekdaystarts']);
				$GLOBALS['phpgw']->preferences->add('calendar','workdaystarts',intval($HTTP_POST_VARS['prefs']['workdaystarts']));
				$GLOBALS['phpgw']->preferences->add('calendar','workdayends',intval($HTTP_POST_VARS['prefs']['workdayends']));
				$GLOBALS['phpgw']->preferences->add('calendar','defaultcalendar',$HTTP_POST_VARS['prefs']['defaultcalendar']);
				$GLOBALS['phpgw']->preferences->add('calendar','defaultfilter',$HTTP_POST_VARS['prefs']['defaultfilter']);
				$GLOBALS['phpgw']->preferences->add('calendar','interval',intval($HTTP_POST_VARS['prefs']['interval']));
				if ($HTTP_POST_VARS['prefs']['mainscreen_showevents'] == True)
				{
					$GLOBALS['phpgw']->preferences->add('calendar','mainscreen_showevents',$HTTP_POST_VARS['prefs']['mainscreen_showevents']);
				}
				else
				{
					$GLOBALS['phpgw']->preferences->delete('calendar','mainscreen_showevents');
				}
				if ($HTTP_POST_VARS['prefs']['send_updates'] == True)
				{
					$GLOBALS['phpgw']->preferences->add('calendar','send_updates',$HTTP_POST_VARS['prefs']['send_updates']);
				}
				else
				{
					$GLOBALS['phpgw']->preferences->delete('calendar','send_updates');
				}
		
				if ($HTTP_POST_VARS['prefs']['display_status'] == True)
				{
					$GLOBALS['phpgw']->preferences->add('calendar','display_status',$HTTP_POST_VARS['prefs']['display_status']);
				}
				else
				{
					$GLOBALS['phpgw']->preferences->delete('calendar','display_status');
				}

				if ($HTTP_POST_VARS['prefs']['default_private'] == True)
				{
					$GLOBALS['phpgw']->preferences->add('calendar','default_private',$HTTP_POST_VARS['prefs']['default_private']);
				}
				else
				{
					$GLOBALS['phpgw']->preferences->delete('calendar','default_private');
				}

				if ($HTTP_POST_VARS['prefs']['display_minicals'] == True)
				{
					$GLOBALS['phpgw']->preferences->add('calendar','display_minicals',$HTTP_POST_VARS['prefs']['display_minicals']);
				}
				else
				{
					$GLOBALS['phpgw']->preferences->delete('calendar','display_minicals');
				}

				if ($HTTP_POST_VARS['prefs']['print_black_white'] == True)
				{
					$GLOBALS['phpgw']->preferences->add('calendar','print_black_white',$HTTP_POST_VARS['prefs']['print_black_white']);
				}
				else
				{
					$GLOBALS['phpgw']->preferences->delete('calendar','print_black_white');
				}

				$GLOBALS['phpgw']->preferences->save_repository(True);

				Header('Location: '.$GLOBALS['phpgw']->link('/preferences/index.php'));
				$GLOBALS['phpgw']->common->phpgw_exit();
			}
		}
	}

