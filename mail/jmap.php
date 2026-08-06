<?php
/**
 * EGroupware Mail: local JMAP server for plain IMAP accounts - HTTP entrypoint
 *
 * Thin front-controller: boots EGroupware (same technique as json.php), then hands the
 * request straight to EGroupware\Mail\JmapShim. See that class's docblock for the actual
 * design/scope of this local JMAP shim.
 *
 * @link https://www.egroupware.org
 * @author Ralf Becker <rb-AT-egroupware.org>
 * @copyright (c) 2026 by EGroupware GmbH <info-AT-egroupware.org>
 * @package mail
 * @license https://opensource.org/licenses/gpl-license.php GPL - GNU General Public License
 */

/**
 * Called by Api\Egw::verify_session(), if there's no valid EGroupware session
 *
 * We're an API endpoint consumed by fetch(), not a page navigation, so we
 * answer with a JMAP-shaped 401 instead of the usual HTML redirect to login.
 *
 * @param mixed &$account unused, required by the autocreate_session_callback signature
 */
function mail_jmap_unauthorized(&$account)
{
	unset($account);
	http_response_code(401);
	header('Content-Type: application/json; charset=utf-8');
	echo json_encode(['type' => 'urn:ietf:params:jmap:error:notAuthorized'], JSON_UNESCAPED_SLASHES);
	exit;
}

$GLOBALS['egw_info'] = array(
	'flags' => array(
		'disable_Template_class' => true,
		'noheader' => true,
		'currentapp' => 'mail',
		'autocreate_session_callback' => 'mail_jmap_unauthorized',
		'no_exception_handler' => true,
	),
);
include(dirname(__DIR__).'/header.inc.php');

// release session, as this endpoint is stateless (stores nothing in $_SESSION) and it
// blocks parallel requests otherwise - same pattern as api/avatar.php, api/images.php, ...
$GLOBALS['egw']->session->commit_session();

use EGroupware\Mail\JmapShim;

header('Content-Type: application/json; charset=utf-8');

try
{
	if ($_SERVER['REQUEST_METHOD'] === 'POST')
	{
		$request = json_decode(file_get_contents('php://input'), true, 512, JSON_THROW_ON_ERROR);
		echo json_encode([
			'methodResponses' => JmapShim::dispatch((array)($request['methodCalls'] ?? [])),
			'sessionState' => '0',
		], JSON_UNESCAPED_SLASHES);
	}
	else
	{
		echo json_encode(JmapShim::session(), JSON_UNESCAPED_SLASHES);
	}
}
catch (\Throwable $e)
{
	http_response_code(500);
	echo json_encode(['type' => 'serverFail', 'description' => $e->getMessage()], JSON_UNESCAPED_SLASHES);
}
exit;
