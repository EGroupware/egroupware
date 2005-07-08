<?php
  /**************************************************************************\
  * phpGroupWare                                                             *
  * http://www.phpgroupware.org                                              *
  * --------------------------------------------                             *
  *  This program is free software; you can redistribute it and/or modify it *
  *  under the terms of the GNU General Public License as published by the   *
  *  Free Software Foundation; either version 2 of the License, or (at your  *
  *  option) any later version.                                              *
  \**************************************************************************/

  /* $Id$ */

	$egw_info = array();
	$GLOBALS['egw_info']['flags'] = array(
		'noheader' => True,
		'nonavbar' => True,
		'disable_Template_class' => True,
		'currentapp' => 'notifywindow'
	);
	include('./header.inc.php');

	echo '<html><body>';
	$GLOBALS['egw']->hooks->process('notifywindow_simple',array('email'));
	echo '</body></html>';
?>
