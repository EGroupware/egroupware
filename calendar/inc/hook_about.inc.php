<?php
    /**************************************************************************\
    * phpGroupWare - about                                                     *
    * http://www.phpgroupware.org                                              *
    * --------------------------------------------                             *
    * This program is free software; you can redistribute it and/or modify it  *
    * under the terms of the GNU General Public License as published by the    *
    * Free Software Foundation; either version 2 of the License, or (at your   *
    * option) any later version.                                               *
    \**************************************************************************/
    /* $Id$ */

	function about_app($tpl,$handle)
	{
		$t = CreateObject('phpgwapi.Template',$GLOBALS['phpgw']->common->get_tpl_dir('phpgwapi'));
		$s = $t->set_file(array('about' => 'about_app.tpl'));
		$s .= $t->set_var('app_title',lang('calendar'));
		$s .= $t->set_var('based_on',lang('based on'));
		$s .= $t->set_var('written_by',lang('written by'));
		$s .= $t->set_var('source','Webcalendar by Craig Knudsen &lt;cknudsen@radix.net&gt;<br>http://www.radix.net/~cknudsen');
		$s .= $t->set_var('developers','Mark Peters &lt;skeeter@phpgroupware.org&gt;');
		$s .= $t->fp('out','about');
		return $s;
	}
