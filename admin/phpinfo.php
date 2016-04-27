<?php
/**
 * EGgroupware administration
 *
 * @link http://www.egroupware.org
 * @package admin
 * @license http://opensource.org/licenses/gpl-license.php GPL - GNU General Public License
 * @version $Id$
 */

$GLOBALS['egw_info']['flags'] = array(
	'noheader'   => True,
	'nonavbar'   => True,
	'currentapp' => 'admin'
);
include('../header.inc.php');

if ($GLOBALS['egw']->acl->check('info_access',1,'admin'))
{
	$GLOBALS['egw']->redirect_link('/index.php');
}

phpinfo();
