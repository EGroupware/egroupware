<?php
/**
 * FileManger - WebDAV access for ownCloud clients
 *
 * ownCloud clients require this url to return some json encoded properties
 *
 * @link http://owncloud.org/sync-clients/
 * @link http://www.egroupware.org
 * @license http://opensource.org/licenses/gpl-license.php GPL - GNU General Public License
 * @package api
 * @subpackage vfs
 * @author Ralf Becker <RalfBecker-AT-outdoor-training.de>
 * @copyright (c) 2006-12 by Ralf Becker <RalfBecker-AT-outdoor-training.de>
 * @version $Id$
 */
// forward for not existing or empty header to setup
if(!file_exists('header.inc.php') || !filesize('header.inc.php'))
{
	Header('Location: setup/index.php');
	exit;
}
$GLOBALS['egw_info'] = array(
	'flags' => array(
		'currentapp' => 'login',
		'noheader'   => True,
	)
);
include 'header.inc.php';

echo json_encode(array(
	'installed' => 'true',
	'version'   => '4.80.1',
	'versionstring' => 'EGroupware '.$GLOBALS['egw_info']['server']['versions']['phpgwapi'],
	'edition'   => '',
));
