<?php
/**
 * EGroupware Admin - Test Push
 *
 * Generic push-connectivity diagnostic: shows whether push is working at all and which
 * transport is currently carrying it - a real push backend (eg. swoolepush) or the built-in
 * SSE/long-poll fallback (doc/ai/projects/push-fallback-longpoll.md) - plus, if a real backend
 * is installed, its own connectivity diagnostics. Reachable from Admin > Test Push
 * (admin/inc/class.admin_hooks.inc.php), but deliberately NOT living under /admin/ itself:
 * admin/js/app.ts installs a permanent 'load' listener on its own content iframe
 * (et2_ready()'s 'admin.index' case) that blanks ANY url matching /\/admin\// and reloads the
 * default accounts list - a deliberate safeguard against a real admin/index.php recursive-load
 * bug, but its regex is broad enough to also catch this page purely because of its own directory,
 * with no way to opt out from here - found live 2026-09-21 as "gets directly overwritten by the
 * accounts list" when reached via the sidebox with admin's own content iframe already open.
 *
 * The actual round-trip test message is sent via the generic Api\Json\Push facade
 * (Push::checkSetBackend()), NOT by constructing any specific backend class directly - that's
 * what makes this page able to demonstrate the fallback actually working: checkSetBackend()
 * catches a real backend's own construction failure per class and falls through to
 * notifications_push, exactly like any real EGroupware notification already does. An earlier
 * version of this idea (swoolepush/test.php, still available standalone/CLI-invocable) directly
 * instantiated the swoolepush backend for the actual send too, which meant it could never
 * demonstrate the fallback working while that backend was unreachable - found live 2026-09-21.
 *
 * @link http://www.egroupware.org
 * @package api
 * @license http://opensource.org/licenses/gpl-license.php GPL - GNU General Public License
 */

use EGroupware\Api;
use EGroupware\Api\Json\Push;

$GLOBALS['egw_info'] = [
	'flags' => [
		'currentapp' => PHP_SAPI !== 'cli' ? 'admin' : 'login',
		'noheader' => true,
		'nonavbar' => true,
	]
];

require_once __DIR__.'/../header.inc.php';

// Api\Egw::check_app_rights() (called automatically as part of header.inc.php's own bootstrap,
// Egw.php's wakeup2()) already enforces that the current user has the 'admin' app granted for
// currentapp='admin' above, throwing Exception\NoPermission\Admin() before this script even
// gets control if not - no separate check needed here.
if (PHP_SAPI !== 'cli')
{
	// Random per-request token, round-tripped through the actual push, so the client can tell a
	// real push for THIS test apart from a stale one arriving late from a previous run.
	// pushTestStart() (admin/js/app.ts) arms a client-side timeout, only showing the failure
	// message if no matching-token push arrives within it. 5000ms, not 2000: found live that a
	// long-poll's own ~1s tick plus network/PHP overhead can occasionally land just past 2s on a
	// cold start, showing a confusing "failed, then succeeded a moment later" pair of toasts even
	// though the fallback delivered correctly, just a little slower than a tight window allowed.
	$push_test_token = bin2hex(random_bytes(8));
	Api\Framework::set_extra('app', 'call', [
		'app' => 'admin',
		'method' => 'pushTestStart',
		'args' => [$push_test_token, 5000, lang('Push server is NOT working')],
	]);

	echo $egw->framework->header();
	echo "<pre>\n";
	$success_start = "<span style='color: green; font-weight: bold'>";
	$failure_start = "<span style='color: red; font-weight: bold'>";
	// for backend-specific detail that's expected/normal once the overall test above is already
	// green (eg. "the real backend is unreachable, so we're correctly using the fallback") -
	// informational, not an alarm, so not red like a genuine failure
	$info_start = "<span style='color: gray'>";
	$end = '</span>';
}
else
{
	echo "\n";
	$success_start = $failure_start = $info_start = $end = '';
}

// onlyFallback()=true is NOT a failure - it means push IS working, just via the built-in
// SSE/long-poll fallback instead of a real backend; only the per-backend detail below (eg. a
// real backend being unreachable) is potentially noteworthy, not this line - found live
// 2026-09-21, Ralf: "should be in green as well, as it means push service itself is working".
$only_fallback = Push::onlyFallback();
echo lang('Push').': '.$success_start.($only_fallback ?
		lang('using the fallback (SSE / regular long-poll JSON requests)') :
		lang('using a real, native push server')).$end."\n\n";

// Backend-specific diagnostics, only shown if a real push backend is actually installed - class
// names of installed backends come from the same 'push-backends' hook Push::checkSetBackend()
// itself consults, so this generalizes if a second real backend class ever exists too.
foreach(Api\Hooks::process('push-backends', [], true) as $backend_class)
{
	if (empty($backend_class) || !class_exists($backend_class)) continue;

	echo $backend_class."::\n";
	if (method_exists($backend_class, 'failedAttempts'))
	{
		echo '  '.lang('failed attempts').'='.$backend_class::failedAttempts().
			', '.lang('current backoff').'='.$backend_class::backoffTime()."s\n";

		if (defined($backend_class.'::MAX_FAILED_ATTEMPTS'))
		{
			$max_failed_attempts = constant($backend_class.'::MAX_FAILED_ATTEMPTS');
			if ($backend_class::failedAttempts() > $max_failed_attempts)
			{
				if (PHP_SAPI !== 'cli' && empty($_POST['reset']))
				{
					// POST forms with no action="..." default to the current full URL (query
					// string included), unlike GET (see the Retry form's own comment below), so
					// this hidden field is defensive rather than strictly required - kept for the
					// same reason regardless, to not depend on that default staying correct.
					echo " <form style='display:inline-block; margin:0' method='post'>".
						"<input type='hidden' name='cd' value='no' />".
						"<input type='submit' name='reset' value='".lang('reset')."' /></form>\n";
				}
				elseif (!empty($_POST['reset']) || PHP_SAPI === 'cli')
				{
					$backend_class::failedAttempts(-2 * $max_failed_attempts);
					echo '  '.lang('reset to').' failedAttempts='.$backend_class::failedAttempts().
						', backoffTime='.$backend_class::backoffTime()."s\n";
				}
			}
		}
	}
	try
	{
		echo '  '.lang('online').'='.json_encode(array_map(static function($account_id)
			{
				return Api\Accounts::id2name($account_id);
			}, (new $backend_class())->online()))."\n";
	}
	catch (\Exception $e)
	{
		// this specific backend being unreachable is expected/normal once the fallback is
		// correctly covering for it (see the "using the fallback" line above) - not red like a
		// genuine failure, since the overall push test is fine
		echo '  '.$info_start.$e->getMessage().$end."\n";
	}
	echo "\n";
}

if (PHP_SAPI !== 'cli')
{
	try
	{
		(new Push(Push::SESSION))->apply('app.admin.pushTestMessage',
			[$push_test_token, lang('Push server is working')]);
	}
	catch (\Exception $e)
	{
		echo $failure_start.$e->getMessage().$end."\n";
	}
}

echo "\n".lang('currently online, any transport').'='.json_encode(array_map(static function($account_id)
	{
		return Api\Accounts::id2name($account_id);
	}, (new Push())->online()))."\n";

if (PHP_SAPI !== 'cli')
{
	// A GET form with no action="..." resubmits to the current PATH only, dropping any existing
	// query string entirely (unlike POST, which defaults to the full current URL, query string
	// included) - without this hidden field, Retry would drop cd=no and reintroduce the same
	// check-framework redirect ("overwritten by the accounts list") this page was just fixed for.
	echo "\n<form style='display:inline-block; margin:0'><input type='hidden' name='cd' value='no' />".
		"<input type='submit' value='".lang('retry')."' /></form>\n";
}