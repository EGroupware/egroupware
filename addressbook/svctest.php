<?php
  /**************************************************************************\
  * eGroupWare - Addressbook - API contacts service test                     *
  * http://www.egroupware.org                                                *
  * --------------------------------------------                             *
  *  This program is free software; you can redistribute it and/or modify it *
  *  under the terms of the GNU General Public License as published by the   *
  *  Free Software Foundation; either version 2 of the License, or (at your  *
  *  option) any later version.                                              *
  \**************************************************************************/

  /* $Id$ */

	$GLOBALS['phpgw_info'] = array();

	$GLOBALS['phpgw_info']['flags'] = array(
		'currentapp' => 'addressbook',
		'noheader'   => True,
		'nonavbar'   => True
	);
	include('../header.inc.php');

	$obj = CreateObject('phpgwapi.service');

	/* Entire addressbook */
	$tmp = $obj->exec(array('contacts','read_list'));
	echo '<br/>Entire list:';
	_debug_array($tmp);

	/* Single entry with id of 182 */
	$tmp = $obj->exec(array('contacts','read',array('id' => 182)));
	echo '<br/>Single entry:';
	_debug_array($tmp);

	$GLOBALS['phpgw']->common->phpgw_footer();
?>
