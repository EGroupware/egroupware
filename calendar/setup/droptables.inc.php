<?php
  /**************************************************************************\
  * phpGroupWare - Setup                                                     *
  * http://www.phpgroupware.org                                              *
  * --------------------------------------------                             *
  *  This program is free software; you can redistribute it and/or modify it *
  *  under the terms of the GNU General Public License as published by the   *
  *  Free Software Foundation; either version 2 of the License, or");at your  *
  *  option) any later version.                                              *
  \**************************************************************************/

  /* $Id$ */

  $db->query("DROP TABLE calendar_entry");
  $db->query("DROP TABLE calendar_entry_repeats");
  $db->query("DROP TABLE calendar_entry_user");
?>