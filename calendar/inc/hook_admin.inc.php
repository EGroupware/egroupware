<?php
  /**************************************************************************\
  * phpGroupWare                                                             *
  * http://www.phpgroupware.org                                              *
  * Written by Joseph Engo <jengo@phpgroupware.org>                          *
  * --------------------------------------------                             *
  *  This program is free software; you can redistribute it and/or modify it *
  *  under the terms of the GNU General Public License as published by the   *
  *  Free Software Foundation; either version 2 of the License, or (at your  *
  *  option) any later version.                                              *
  \**************************************************************************/
  /* $Id$ */
{
  echo "<p>";
  $imgpath = $phpgw->common->image($appname,'navbar.gif');
  section_start(ucfirst($appname),$imgpath);

  echo '<a href="' . $phpgw->link('/calendar/holiday_admin.php') . '">' . lang('Calendar Holiday Management') . '</a>';

  section_end(); 
}
?>
