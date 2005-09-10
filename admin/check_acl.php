<?php
/**************************************************************************\
* eGroupWare - Admin - delete ACL records of deleted accounts              *
* http://www.egroupware.org                                                *
* Written and (c) 2004 by Ralf Becker <RalfBecker@outdoor-training.de>     *
* --------------------------------------------                             *
*  This program is free software; you can redistribute it and/or modify it *
*  under the terms of the GNU General Public License as published by the   *
*  Free Software Foundation; either version 2 of the License, or (at your  *
*  option) any later version.                                              *
\**************************************************************************/

/* $Id$ */

/**
 * delete ACL records of deleted accounts (can be called only via the URL)
 *
 * ACL records of deleted accounts have very irritating effects on the ACL (specialy calendar)
 *
 * @package admin
 * @author RalfBecker@outdoor-training.de
 * @license GPL
 */

$GLOBALS['egw_info'] = array(
	'flags' => array(
		'currentapp' => 'admin',
));
include('../header.inc.php');

if (!$GLOBALS['egw_info']['user']['apps']['admin'])
{
	echo '<p align="center">'.lang('Permission denied')."</p>\n";
}
else
{
	$deleted = 0;
	if (($all_accounts = $GLOBALS['egw']->accounts->search(array('type'=>'both'))))
	{
		$all_accounts = array_keys($all_accounts);
		$GLOBALS['egw']->db->query("DELETE FROM phpgw_acl WHERE acl_account NOT IN (".implode(',',$all_accounts).") OR acl_appname='phpgw_group' AND acl_location NOT IN ('".implode("','",$all_accounts)."')",__LINE__,__FILE__);
		$deleted = $GLOBALS['egw']->db->affected_rows();
	}
	echo '<p align="center">'.lang('%1 ACL records of not (longer) existing accounts deleted.',$deleted)."</p>\n";
}
$GLOBALS['egw']->common->egw_footer();
$GLOBALS['egw']->common->egw_exit();
