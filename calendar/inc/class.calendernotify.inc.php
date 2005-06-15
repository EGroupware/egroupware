<?php
  /**************************************************************************\
  * eGroupWare - Calendar Notify Hook                                        *
  * http://www.egroupware.org                                                *
  * Based on Webcalendar by Craig Knudsen <cknudsen@radix.net>               *
  *          http://www.radix.net/~cknudsen                                  *
  * File created by Edo van Bruggen <edovanbruggen@raketnet.nl>              *
  * --------------------------------------------                             *
  *  This program is free software; you can redistribute it and/or modify it *
  *  under the terms of the GNU General Public License as published by the   *
  *  Free Software Foundation; either version 2 of the License, or (at your  *
  *  option) any later version.                                              *
  \**************************************************************************/


	class calendernotify
	{


		function notify()
		{
			$db;
			$table = 'phpgw_cal';
			$owner;
			$this->db    = $GLOBALS['phpgw']->db;
			$this->owner = $GLOBALS['phpgw_info']['user']['account_id'];
			$config = CreateObject('phpgwapi.config');
			$config->read_repository();
			$GLOBALS['phpgw_info']['server']['calendar'] = $config->config_data;
			unset($config);
			$messages = array();
			$count = 0;
			$time = time();
			$date_new = time()+ 604800;
			$this->db->limit_query('SELECT * FROM `'. $table .'` WHERE  cal_owner =\''
				. $this->owner.'\' ',"",__LINE__,__FILE__);
			
			while($this->db->next_record())
			{
				if($this->db->f('cal_starttime') - $time < 604800 and $this->db->f('cal_starttime') - $time > 0)
				{
					$count++;
				}
			}
			if($count > 0)
			{
				if($count == 1)
				{
					return "You have ".$count." new appointment.";
				}
				else
				{
					return "You have ".$count." new appointments.";
				}
				
			}
			return  False;

		}
	}	
?>
