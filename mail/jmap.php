<?php
/**
 * EGroupware Mail: local JMAP server for plain IMAP accounts - HTTP entrypoint
 *
 * Thin front-controller: boots EGroupware (same technique as json.php), then hands the
 * request straight to EGroupware\Api\Mail\Jmap\Imap. See that class's docblock for the actual
 * design/scope of this local JMAP shim.
 *
 * @link https://www.egroupware.org
 * @author Ralf Becker <rb-AT-egroupware.org>
 * @copyright (c) 2026 by EGroupware GmbH <info-AT-egroupware.org>
 * @package mail
 * @license https://opensource.org/licenses/gpl-license.php GPL - GNU General Public License
 */

// transparent output-compression turns the (pre)view GET response chunked, ie. no Content-Length -
// browsers won't cache a response without one (same reasoning/fix as api/images.php)
ini_set('zlib.output_compression', 0);

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
		// without this, Api\Session::cache_control() (called right before session_start(), see
		// Session.php) defaults to session_cache_limiter('nocache') - which sends Pragma: no-cache
		// and an already-expired Expires header at session-start time, before our own GET branch's
		// Cache-Control/ETag headers below even run. header() only replaces a header of the same
		// name, so that stale Pragma/Expires would otherwise linger and force revalidation on every
		// request - same reasoning/fix as api/images.php's 'nocachecontrol' => true.
		'nocachecontrol' => true,
	),
);
// the client may already have given up before we even boot EGroupware (slow session/DB startup) -
// cheapest possible place to notice and skip the whole bootstrap
if (connection_aborted()) exit;
include(dirname(__DIR__).'/header.inc.php');
if (connection_aborted()) exit;

// release session, as this endpoint is stateless (stores nothing in $_SESSION) and it
// blocks parallel requests otherwise - same pattern as api/avatar.php, api/images.php, ...
$GLOBALS['egw']->session->commit_session();

use EGroupware\Api\Session;
use EGroupware\Api\Mail\Jmap\Imap as JmapImap;

// Blob download (RFC 8620 §6.2): plain GET matching the "downloadUrl" template from session()
// below, not part of the regular JSON method-call dispatch (jmap-jam calls this separately, see
// JmapImap::download()'s docblock).
if ($_SERVER['REQUEST_METHOD'] !== 'POST' && isset($_GET['download']))
{
	JmapImap::download((string)($_GET['accountId'] ?? ''), (string)($_GET['blobId'] ?? ''),
		(string)($_GET['name'] ?? 'download'), (string)($_GET['type'] ?? 'application/octet-stream'));
	exit;
}

// Blob upload (RFC 8620 §6.3): POST of raw bytes matching the "uploadUrl" template from session()
// below - jmap-jam substitutes {accountId} into the query string before POSTing, so this is a POST
// with a non-JSON (raw binary) body, handled separately from the JSON method-call dispatch below.
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_GET['upload']))
{
	header('Content-Type: application/json; charset=utf-8');
	try
	{
		JmapImap::upload((string)($_GET['accountId'] ?? ''));
	}
	catch (\Throwable $e)
	{
		http_response_code(500);
		echo json_encode(['type' => 'serverFail', 'description' => $e->getMessage()], JSON_UNESCAPED_SLASHES);
	}
	exit;
}

header('Content-Type: application/json; charset=utf-8');

// Cacheable GET dispatch: same method-call shape as the POST branch below, but methodCalls/using
// arrive PHP http_build_query()-style (plain $_GET bracket-array params, see mail/js/jmap.ts's
// phpBuildQuery()) instead of a JSON-encoded POST body - no json_decode() needed here, PHP already
// parsed the nested array for us. "using" is accepted for parity but, same as the POST branch,
// never read - this shim doesn't validate against it. This lets the browser's own HTTP cache
// handle repeat requests instead of us keeping anything in server-side memory (a permanent leak
// across requests). Only ever sent by mail/js/jmap.ts for the local shim's (pre)view body fetch -
// real JMAP servers require POST (RFC 8620 §3.3), so a real Stalwart account never reaches this.
//
// The ETag is a weak validator over the raw query string, not the response: for this shim, the
// response is a pure function of the request args (an IMAP UID/part's content is immutable once
// fetched), so a matching If-None-Match answers 304 before doing any IMAP work at all, not just
// before re-sending bytes already in the browser's cache.
if ($_SERVER['REQUEST_METHOD'] !== 'POST' && isset($_GET['methodCalls']))
{
	$etag = '"'.sha1($_SERVER['QUERY_STRING']).'"';
	// same helper api/categories.php uses - a plain header('Cache-Control: ...') only sets that one
	// header, but browsers also expect a matching Expires header (this session was already started
	// with the 'private_no_expire' limiter, from the 'nocachecontrol' flag above, so this call's
	// $expire/$private mismatch against that is what makes it actually send the headers - see
	// Session::cache_control()'s "session already started" branch)
	Session::cache_control(864000, true);
	header('ETag: '.$etag);
	if (trim($_SERVER['HTTP_IF_NONE_MATCH'] ?? '') === $etag)
	{
		http_response_code(304);
		exit;
	}
	ob_start();
	try
	{
		echo json_encode([
			'methodResponses' => JmapImap::dispatch((array)$_GET['methodCalls']),
			'sessionState' => '0',
		], JSON_UNESCAPED_SLASHES);
	}
	catch (\Throwable $e)
	{
		http_response_code(500);
		echo json_encode(['type' => 'serverFail', 'description' => $e->getMessage()], JSON_UNESCAPED_SLASHES);
	}
	// browsers won't cache a response with no Content-Length (chunked) - buffer it ourselves so we
	// can send an exact length, instead of relying on zlib.output_compression staying off everywhere
	$content = ob_get_clean();

	// we run our own gzip compression, to set a correct Content-Length of the encoded content
	if (in_array('gzip', explode(',', $_SERVER['HTTP_ACCEPT_ENCODING'] ?? '')) && function_exists('gzencode'))
	{
		$content = gzencode($content);
		header('Content-Encoding: gzip');
	}

	header('Content-Length: '.bytes($content));
	echo $content;
	exit;
}

try
{
	if ($_SERVER['REQUEST_METHOD'] === 'POST')
	{
		$request = json_decode(file_get_contents('php://input'), true, 512, JSON_THROW_ON_ERROR);
		echo json_encode([
			'methodResponses' => JmapImap::dispatch((array)($request['methodCalls'] ?? [])),
			'sessionState' => '0',
		], JSON_UNESCAPED_SLASHES);
	}
	else
	{
		echo json_encode(JmapImap::session(), JSON_UNESCAPED_SLASHES);
	}
}
catch (\Throwable $e)
{
	http_response_code(500);
	echo json_encode(['type' => 'serverFail', 'description' => $e->getMessage()], JSON_UNESCAPED_SLASHES);
}
exit;
