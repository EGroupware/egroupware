<?php
/**
 * API call when password has been changed outside EGroupware to eg. re-encrypting (mail) credentials
 *
 * Can be used eg. via CURL *after* the password has been changed:
 *
 * echo '<new-password>' | curl --user <username> --data-raw '<old-password>' -X POST https://egw.domain.com/egroupware/api/changepwd.php
 *
 * (You can also use --data @<filename-with-old-password> instead of --date-raw '<old-password>')
 *
 * It will connect with EGroupware (verifying the certificate), authenticate with
 * the new credentials and send in a POST request the old credentials.
 *
 * EGroupware will then re-encrypt everything encrypted with the session password:
 * - mail credentials
 * - private S/Mime keys
 * - let all EGroupware apps know about the password change
 *
 * Hook will give the following http status:
 * - "204 No Content" on success / credentials are changed
 * - "401 Unauthorized", if new password is wrong or not supplied via basic auth
 * - "500 Internal server error" on error
 *
 * For Apache FCGI you need the following rewrite rule:
 *
 * 	RewriteEngine on
 * 	RewriteRule .* - [E=HTTP_AUTHORIZATION:%{HTTP:Authorization},L]
 *
 * Otherwise authentication request will be send over and over again, as password is NOT available to PHP!
 *
 * @link http://www.egroupware.org
 * @license http://opensource.org/licenses/gpl-license.php GPL - GNU General Public License
 * @package api
 * @author Ralf Becker <rb-AT-egroupware.org>
 * @copyright (c) 2018 by Ralf Becker <rb-AT-egroupware.org>
 */

use EGroupware\Api;

$GLOBALS['egw_info'] = array(
	'flags' => array(
		'disable_Template_class' => True,
		'noheader'  => True,
		'currentapp' => 'api',
		'autocreate_session_callback' => 'EGroupware\Api\Header\Authenticate::autocreate_session_callback',
	)
);

// if you move this file somewhere else, you need to adapt the path to the header!
require(dirname(__DIR__).'/header.inc.php');


try {
	$old_password = file_get_contents('php://input');
	if (empty($old_password)) throw new Exception('Old password must not be empty!');

	Api\Auth::changepwd($old_password);
	http_response_code(204);	// No Content
}
catch (\Exception $e) {
	http_response_code(500);
	header('Content-Type: text/plain; charset=utf-8');
	echo $e->getMessage()."\n";
}