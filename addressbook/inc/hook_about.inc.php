<?php
  /**************************************************************************\
  * phpGroupWare                                                             *
  * http://www.phpgroupware.org                                              *
  * Written by Mark Peters <skeeter@phpgroupware.org>                        *
  * --------------------------------------------                             *
  *  This program is free software; you can redistribute it and/or modify it *
  *  under the terms of the GNU General Public License as published by the   *
  *  Free Software Foundation; either version 2 of the License, or (at your  *
  *  option) any later version.                                              *
  \**************************************************************************/

  /* $Id$ */

	function about_app()
	{
		$GLOBALS['tpl']->set_var('developers','Joseph Engo&nbsp;&nbsp;[jengo@phpgroupware.org]<br>Miles Lott&nbsp;&nbsp;[milosch@phpgroupware.org]');
		$GLOBALS['tpl']->set_var('description',lang('Addressbook is the phpgroupware default contact application. <br>It makes use of the phpgroupware contacts class to store and retrieve contact information via SQL or LDAP.'));

		return $GLOBALS['tpl']->fp('out','about');
	}
