<?php
  /**************************************************************************\\
  * phpGroupWare - Setup                                                     *
  * http://www.eGroupWare.org                                                *
  * Created by eTemplates DB-Tools written by ralfbecker@outdoor-training.de *
  * --------------------------------------------                             *
  * This program is free software; you can redistribute it and/or modify it  *
  * under the terms of the GNU General Public License as published by the    *
  * Free Software Foundation; either version 2 of the License, or (at your   *
  * option) any later version.                                               *
  \\**************************************************************************/

  /* $Id$ */

	$menu_title = $GLOBALS['phpgw_info']['apps'][$appname]['title'] . ' '. lang('Menu');
	$file = Array(
		'eTemplate Editor' => $GLOBALS['phpgw']->link('/index.php','menuaction=etemplate.editor.edit'),
		'DB-Tools' => $GLOBALS['phpgw']->link('/index.php','menuaction=etemplate.db_tools.edit'),
	);
	if (@$GLOBALS['phpgw_info']['user']['apps']['developer_tools'])
	{
		$file += array(
			'_NewLine_', // give a newline
			'developer_tools' => $GLOBALS['phpgw']->link('/index.php','menuaction=developer_tools.uilangfile.index'),
		);
	}
	display_sidebox($appname,$menu_title,$file);
