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

	class uialarm
	{
		var $template;
		var $template_dir;

		var $bo;

		var $debug = False;
//		var $debug = True;

		var $tz_offset;
		var $theme;

		var $public_functions = array(
			'manager' => True
		);

		function uialarm()
		{
			$GLOBALS['phpgw']->nextmatchs = CreateObject('phpgwapi.nextmatchs');
			$GLOBALS['phpgw']->browser    = CreateObject('phpgwapi.browser');
			
			$this->theme = $GLOBALS['phpgw_info']['theme'];

			$this->bo = CreateObject('calendar.boalarm');
			$this->tz_offset = $this->bo->tz_offset;

			if($this->debug)
			{
				echo "BO Owner : ".$this->bo->owner."<br>\n";
			}

			$this->template = $GLOBALS['phpgw']->template;
			$this->template_dir = $GLOBALS['phpgw']->common->get_tpl_dir('calendar');
		}

		function prep_page()
		{
			$cal_id = $GLOBALS['HTTP_POST_VARS']['cal_id'];
			$event = $this->bo->cal->read_entry(intval($GLOBALS['HTTP_POST_VARS']['cal_id']));

			$can_edit = $this->bo->cal->can_user_edit($event);
				
			if(!$can_edit)
			{
				Header('Location : '.$GLOBALS['phpgw']->link('/index.php',
						Array(
							'menuaction'	=> 'calendar.uicalendar.view',
							'cal_id'		=> $GLOBALS['HTTP_POST_VARS']['cal_id']
						)
					)
				);
			}

  			unset($GLOBALS['phpgw_info']['flags']['noheader']);
   		unset($GLOBALS['phpgw_info']['flags']['nonavbar']);
	   	$GLOBALS['phpgw']->common->phpgw_header();
		}

		/* Public functions */

		function manager()
		{
			$this->prep_page();
		}

		function add_alarm()
		{
			$this->prep_page();
		}
	}
