<?php
/**
 * EGroupware - Anonymous letter-avatar
 *
 * @link http://www.egroupware.org
 * @author Ralf Becker <rb-at-egroupware.org>
 * @license http://opensource.org/licenses/gpl-license.php LGPL - GNU General Public License Version 2 or later
 * @package api
 * @subpackage contacts
 */

use EGroupware\Api;

$GLOBALS['egw_info'] = array('flags' => array(
	'disable_Template_class'  => True,
	'noheader'                => True,
	// misuse session creation callback to send the image, in case we have no session
	'autocreate_session_callback' => 'send_image',
	'currentapp'              => 'api',
));

require('../header.inc.php');

send_image();

function send_image()
{
	$params = [
		'firstname' => $_GET['firstname'],
		'lastname'  => $_GET['lastname'],
		'id'        => $_GET['id'],
	];
	Api\Session::cache_control(864000);    // 10 days
	header('Content-type: image/jpeg');
	header('Etag: '.md5(json_encode($params)));

	if (($image = Api\Contacts\Lavatar::generate($params)) !== false)
	{
		echo $image;
	}
	else
	{
		http_response_code(404);
	}
	exit;
}