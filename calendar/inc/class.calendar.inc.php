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

  $phpgw_info['server']['calendar_type'] = 'sql';
  include(PHPGW_INCLUDE_ROOT.'/calendar/inc/class.calendar_'.$phpgw_info['server']['calendar_type'].'.inc.php');

  class calendar extends calendar_
  {
	function calendar($params=False)
	{
	  global $phpgw_info;
	  
	  if(gettype($params)=="array")
	  {
	    while(list($key,$value) = each($params))
	    {
		  $this->$key = $value;
	    }
	  }
	  else
	  {
        $this->printer_friendly = $params;
      }

      if(!$this->owner)
      {
        $this->owner = $phpgw_info['user']['account_id'];
      }
      
      if(!isset($this->rights))
      {
        $this->rights = PHPGW_ACL_READ + PHPGW_ACL_ADD + PHPGW_ACL_EDIT + PHPGW_ACL_DELETE + 16;
      }

      $this->today = $this->localdates(time());
    }
  }
?>
