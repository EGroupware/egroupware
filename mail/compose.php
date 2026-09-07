<?php
/**
 * EGroupware Mail: thin compose-popup bootstrap page
 *
 * Renders nothing but a normal (nonavbar) popup page header carrying egw.js's own bootstrap
 * script tag, with a data-mail-start attribute (Api\Framework::set_extra()) added on top so
 * egw.js's own data-start handling (api/js/jsapi/egw.js) calls MailApp.bootstrapComposePopup()
 * once the framework is ready - the same "run one app method once ready" mechanism
 * egw_open.ts's clientSidePopup() already uses for a JS-injected about:blank popup, just reached
 * via a REAL navigated page instead (doc/ai/projects/mail-compose-jmap-migration.md Step 10,
 * "Option B" - ralf, 2026-09-07, choosing this over a static compose.html specifically so a
 * reload/F5 always works, not just when window.opener happens to still be alive, and because a
 * real page fixes Chrome's "third-party cookie" warning: an about:blank popup's document has no
 * real top-level HTTP response of its own, so its security context stayed opaque even though
 * every script it ran was same-origin).
 *
 * Deliberately does nothing mail/IMAP-specific at all - MailApp.bootstrapComposePopup() itself
 * still does its own ajax_getComposeToolbarData() call exactly as before, client-side, once this
 * page has loaded (ralf: "no IMAP/JMAP opening server-side"). This file only ever reads $_GET
 * params through to the script tag - Api\Mail::getInstance() (and the real IMAP connection its own
 * profile-resolution can trigger) is never touched here. The one non-mail-specific exception is
 * Etemplate::clientSideBootstrap() below, computing the {name, url, etemplate_exec_id} bootstrapComposePopup()
 * used to fetch itself via a separate mail.mail_compose.ajax_getComposeSession() round-trip (ralf,
 * 2026-09-07: "that's the workaround we used to get an etemplate_exec_id, so there's no need for it
 * now") - it's a cheap file-lookup + Api\Cache write, not a session write, so doing it here doesn't
 * conflict with committing the session early either (see below).
 *
 * Session is committed (closed) immediately, same reasoning/pattern as json.php's/mail/jmap.php's
 * own early commit_session() - nothing here writes to the session, and closing it early means
 * this request can't block a concurrent one carrying the same session cookie (ralf: "immediately
 * closing the session").
 */

use EGroupware\Api;

$GLOBALS['egw_info'] = array(
	'flags' => array(
		'currentapp' => 'mail',
		// same value (not just true) mail_compose::compose() itself uses for a real postback
		// popup - Etemplate::exec() reads this to decide "wrap in div#popupMainDiv, skip navbar"
		// vs rendering the full navbar
		'nonavbar' => 'popup',
		// Egw::load_optional_classes() (api/src/Egw.php) otherwise echoes header() ITSELF,
		// automatically, before control even returns here - found live 2026-09-07: my own
		// explicit header() call below was a silent no-op (Framework\Ajax::header()'s own
		// self::$header_done guard), so Api\Framework::set_extra()'s data-mail-start never made
		// it into the script tag at all, since that auto-triggered header() ran before this file
		// got a chance to call set_extra() first. mail_compose::compose() (reached via the
		// classic menuaction/index.php route, which sets noheader=>true for every app by default)
		// avoids this the same way - explicit control over exactly when header() runs.
		'noheader' => true,
	),
);
if (connection_aborted()) exit;
include(dirname(__DIR__).'/header.inc.php');
if (connection_aborted()) exit;

// nothing below writes anything to the session - close it immediately (see docblock above)
$GLOBALS['egw']->session->commit_session();

// mail/js/app.min.js is otherwise only included the FIRST time this session sees the mail app at
// all (Framework::_get_js()'s own data-include tracks, per session, which JS a given app has
// "already sent" - see api/js/jsapi/egw_open.ts's clientSidePopup(), which hit exactly this
// finding it missing entirely once the mail list page had already loaded it earlier) - this
// mirrors Framework::include_css_js_response()'s own "current app's own bundle" pattern to make
// sure it's here regardless of what else this session already sent.
Api\Framework::includeJS('/mail/js/app.min.js');

$bootstrap = Api\Etemplate::clientSideBootstrap('mail.compose');

// MailApp.composeWithPreset()'s own preset (to/cc/bcc, VFS-referenced or inline-content
// attachments, a body snippet, ...) - already resolved entirely client-side by whichever caller
// built it, so just decoded through here for bootstrapComposePopup() to merge into its own
// content, same as from/id/acc_id/mode/smime_type below. $_REQUEST (not $_GET-only) - a preset too
// large for a GET url (eg. calendar's own meeting-invite description/ics) arrives via
// composeWithPresetPost()'s own POST instead (same "GET when short, POST when not" split
// egw.openComposePost() already uses for the classic path).
$preset = json_decode((string)($_REQUEST['preset'] ?? ''), true) ?: [];

Api\Framework::set_extra('mail', 'start', array(
	'method' => 'app.mail.bootstrapComposePopup',
	'args'   => array(
		(string)($_GET['from'] ?? ''),
		(string)($_GET['id'] ?? ''),
		(string)($_GET['acc_id'] ?? ''),
		(string)($_GET['mode'] ?? ''),
		(string)($_GET['smime_type'] ?? ''),
		$bootstrap,
		$preset,
	),
));

echo $GLOBALS['egw']->framework->header();
echo '<div id="popupMainDiv" class="popupMainDiv"></div>'."\n";
echo $GLOBALS['egw']->framework->footer();

// run egw destructor now explicit, same reasoning mail/jmap.php's/json.php's own callers already
// have - under PHP 8 the destructor otherwise runs too late for anything on_shutdown() queued
$GLOBALS['egw']->__destruct();
