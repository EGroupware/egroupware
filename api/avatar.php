<?php
/**
 * API: loading user preferences and data
 *
 * Usage: /egroupware/api/user.php?user=123
 *
 * @link www.egroupware.org
 * @author Ralf Becker <RalfBecker-AT-outdoor-training.de>
 * @package api
 * @license http://opensource.org/licenses/gpl-license.php GPL - GNU General Public License
 */

use EGroupware\Api;

// switch evtl. set output-compression off, as we cant calculate a Content-Length header with transparent compression
ini_set('zlib.output_compression', 0);

$GLOBALS['egw_info'] = array(
	'flags' => array(
		'currentapp' => 'api',
		'noheader' => true,
		'nocachecontrol' => true,
	)
);

include '../header.inc.php';

// release session, as we dont need it and it blocks parallel requests
$GLOBALS['egw']->session->commit_session();

$contacts = new Api\Contacts();

$contacts->photo();