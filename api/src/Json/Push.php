<?php
/**
 * EGroupware API: push JSON commands to client
 *
 * @link http://www.egroupware.org
 * @license http://opensource.org/licenses/gpl-license.php GPL - GNU General Public License
 * @package api
 * @subpackage json
 * @author Ralf Becker <rb@stylite.de>
 */

namespace EGroupware\Api\Json;

use EGroupware\Api;
use notifications_push;

/**
 * Class to push JSON commands to client
 */
class Push extends Msg
{
	/**
	 * Available backends to try
	 *
	 * @var array
	 */
	protected static $backends = array(
		'notifications_push',
	);
	/**
	 * Backend to use
	 *
	 * @var PushBackend
	 */
	protected static $backend;

	/**
	 * account_id we are pushing too
	 *
	 * @var int|int[]
	 */
	protected $account_id;

	/**
	 * Push to all clients / broadcast
	 */
	const ALL = 0;
	/**
	 * Push to current session
	 */
	const SESSION = null;

	/**
	 * How long to cache online status / maximum frequency for querying
	 */
	const INSTANCE_ONLINE_CACHE_EXPIRATION = 10;

	/**
	 * account_id's of users currently online
	 *
	 * @var array|null
	 */
	protected static $online;

	/**
	 *
	 * @param ?int|int[] $account_id =null account_id(s) to push message too or
	 *	self::SESSION(=null): for current session only or self::ALL(=0) for whole instance / broadcast
	 */
	public function __construct($account_id=self::SESSION)
	{
		$this->account_id = $account_id;
	}

	/**
	 * Adds any type of data to the message
	 *
	 * @param string $key
	 * @param mixed $data
	 * @throws Exception\NotOnline if $account_id is not online
	 */
	protected function addGeneric($key, $data)
	{
		self::checkSetBackend();

		self::$backend->addGeneric($this->account_id, $key, $data);
	}

	/**
	 * Get users online / connected to push-server
	 *
	 * @return array of integer account_id currently available for push
	 */
	public static function online()
	{
		if (!isset(self::$online))
		{
			self::$online = Api\Cache::getInstance(__CLASS__, 'online', function()
			{
				self::checkSetBackend();

				return self::$backend->online();
			}, [], self::INSTANCE_ONLINE_CACHE_EXPIRATION);
		}
		return self::$online;
	}

	/**
	 * Get given user is online / connected to push-server
	 *
	 * @return boolean
	 */
	public static function isOnline($account_id)
	{
		return in_array($account_id, self::online());
	}

	/**
	 * Check and if neccessary set push backend
	 *
	 * @param boolean $ignore_cache =false
	 * @throws Exception\NotOnline
	 */
	protected static function checkSetBackend($ignore_cache=false)
	{
		if ($ignore_cache || !isset(self::$backend))
		{
			// we prepend so the default backend stays last
			foreach(Api\Hooks::process('push-backends', [], true) as $class)
			{
				if (!empty($class))
				{
					array_unshift(self::$backends, $class);
				}
			}
			foreach(self::$backends as $class)
			{
				if (class_exists($class))
				{
					try {
						self::$backend = new $class;
						break;
					}
					catch (\Exception $e) {
						// ignore all exceptions
						unset($e);
						self::$backend = null;
					}
				}
			}
			if (!isset(self::$backend))
			{
				throw new Exception\NotOnline('No valid push-backend found!');
			}
		}
	}

	/**
	 * Check if only fallback / no real push available
	 *
	 * @param boolean $ignore_cache =false
	 * @return bool true: fallback, false: real push
	 */
	public static function onlyFallback($ignore_cache=false)
	{
		try {
			self::checkSetBackend($ignore_cache);
		}
		catch (\Exception $e) {
			return true;
		}
		return self::$backend instanceof \notifications_push;
	}

	/**
	 * Maximum time to hold an ajax_poll() request open, waiting for something to deliver
	 *
	 * Must stay comfortably under any relevant timeout the client-request path may hit
	 * (php.ini max_execution_time, a reverse proxy's read timeout, ...) - 20s is chosen to fit
	 * every commonly-encountered default (proxy defaults are typically 60s+, PHP's own is 30s+),
	 * not because of any protocol requirement.
	 */
	const AJAX_POLL_MAX_WAIT_SECONDS = 20;

	/**
	 * How often ajax_poll() checks for something new while waiting
	 */
	const AJAX_POLL_TICK_USECONDS = 1000000;	// 1s

	/**
	 * Maximum time to hold an SSE (text/event-stream) ajax_poll() connection open, well beyond
	 * AJAX_POLL_MAX_WAIT_SECONDS - see doc/ai/projects/push-fallback-longpoll.md Phase 6. Still
	 * finite: PHP's max_execution_time applies to a streaming response too (best-effort-raised
	 * below, but some hosts disable set_time_limit() entirely - if so, this cap likely never
	 * actually gets reached and the connection ends earlier instead, which just means an earlier,
	 * harmless client-side reconnect, not a fatal error). A shorter, more conservative value than
	 * "as long as possible" on purpose: whatever this instance's proxy/CDN chain, if any, does to
	 * a genuinely long-lived connection is untested territory the initial ack-vs-timeout race
	 * doesn't cover (that only proves the FIRST byte gets through promptly, not that a connection
	 * held for e.g. 30+ minutes wouldn't eventually get cut by some unrelated idle-timeout) -
	 * ending the stream here and letting the client reconnect keeps each individual hold inside
	 * more safely-assumed territory.
	 */
	const AJAX_POLL_SSE_MAX_WAIT_SECONDS = 120;

	/**
	 * Default cap on how many ajax_poll() calls, instance-wide, may hold their request open at
	 * once - each held call ties up one PHP-FPM worker for up to AJAX_POLL_MAX_WAIT_SECONDS,
	 * fundamentally unlike a real push server's coroutines, so this must never be allowed to grow
	 * unbounded (see doc/ai/projects/push-fallback-longpoll.md Phase 4). Admin-overridable via
	 * notifications' config screen (notifications/templates/default/config.xet,
	 * 'ajax_poll_max_concurrent').
	 *
	 * Sizing rule of thumb: keep pm.max_children (php-fpm's worker pool size) at at least 2x this
	 * value, so held polls can never claim more than ~50% of the pool even at the configured
	 * ceiling - Ralf's call: that's still a safe default, and up to ~2/3 of the pool is
	 * acceptable too if an install wants a higher ceiling. 20 (this default) needs
	 * pm.max_children >= 40 under the safe (50%) rule - comfortably under this repo's own default
	 * container's pm.max_children=80.
	 */
	const AJAX_POLL_DEFAULT_MAX_CONCURRENT = 20;

	/**
	 * Bounded long-poll: waits up to AJAX_POLL_MAX_WAIT_SECONDS for a push message queued for the
	 * current user (via the SQL fallback backend, notifications_push), returning as soon as one
	 * is delivered or the wait expires - see doc/ai/projects/push-fallback-longpoll.md Phase 3.
	 * Client-side driver: api/js/jsapi/egw_push_fallback.ts, which only calls this while
	 * egw.pushAvailable() says a real push connection is NOT working.
	 *
	 * Ajax-callable without needing any specific app permission - menuaction
	 * 'EGroupware\Api\Json\Push::ajax_poll' resolves $appName to 'api' (see
	 * Api\Json\Request::handleRequest()), which is always allowed. Deliberately not gated behind
	 * $GLOBALS['egw_info']['user']['apps']['notifications'] the way the older, per-response
	 * piggyback in Api\Json\Request::handle() is - the whole point of this method is to work for
	 * every logged-in user, not just ones with that app enabled.
	 *
	 * NOT a substitute for a real push server in every case: this only ever finds anything when
	 * notifications_push (the SQL fallback PushBackend) is the ACTIVE backend for this instance -
	 * see the doc's "Known limitations" section for the one case that doesn't cover (a real push
	 * backend configured instance-wide, but unreachable from this one browser).
	 *
	 * Concurrency safety valve (Phase 4): once AJAX_POLL_DEFAULT_MAX_CONCURRENT (or the
	 * admin-configured override) calls are already holding their request open, any further call
	 * degrades to a single, immediate, non-holding check instead of joining that queue - this
	 * method can then never itself be the reason pm.max_children gets exhausted. A degraded
	 * caller is told so (Response::data(['degraded' => true])) so the client backs off to a
	 * slower cadence instead of re-issuing in a tight loop against an endpoint that's now
	 * responding instantly every time.
	 *
	 * SSE (Phase 6): $sse=true asks to switch this same held connection into a
	 * text/event-stream, emitting each delivery as its own event instead of returning after one -
	 * ONE shared budget/counter for both transports (see doc/ai/projects/
	 * push-fallback-longpoll.md Phase 6 for why that's correct, not just simpler: the resource
	 * question is "how many users have a held connection open right now", which is the same
	 * number either way). No slot -> the exact same degrade path as the plain long-poll, whether
	 * or not $sse was requested - a degraded response is always plain JSON, never a stream, so
	 * the client can tell the two apart from the response Content-Type alone, no separate probe
	 * step needed. Client-side driver: api/js/jsapi/egw_push_fallback.ts races a single request
	 * against a short timer to detect whether SSE actually survives this instance's proxy/CDN
	 * chain end-to-end, falling back to the plain long-poll above if not.
	 */
	public static function ajax_poll($sse=false)
	{
		// release the session lock immediately, same as notifications_ajax::get_notifications() -
		// this holds the request open for up to AJAX_POLL_MAX_WAIT_SECONDS and must never block a
		// sibling tab/request from the same session for that long
		$GLOBALS['egw']->session->commit_session();

		if (!class_exists(notifications_push::class))
		{
			return;
		}

		$max_concurrent = (int)(Api\Config::read('notifications')['ajax_poll_max_concurrent'] ?? 0)
			?: self::AJAX_POLL_DEFAULT_MAX_CONCURRENT;

		// Atomic (APCu/memcached) claim-a-slot-then-check pattern: incrementCache() is the
		// operation that decides "did I get a slot", not a separate read-then-act check, which
		// would race under real concurrency. A false return (no cache provider configured at
		// all) is treated the same as "budget exceeded" - fail toward the safe (never-holds) side.
		$active = Api\Cache::incrementCache(Api\Cache::INSTANCE, self::class, 'ajax_poll_active',
			1, 1, self::AJAX_POLL_SSE_MAX_WAIT_SECONDS + 10);

		if ($active === false || $active > $max_concurrent)
		{
			if ($active !== false)
			{
				Api\Cache::decrementCache(Api\Cache::INSTANCE, self::class, 'ajax_poll_active');
			}
			notifications_push::get();
			Response::get()->data(['degraded' => true]);
			return;
		}

		try
		{
			if ($sse)
			{
				self::ajaxPollSse();
			}
			else
			{
				self::ajaxPollWait();
			}
		}
		finally
		{
			Api\Cache::decrementCache(Api\Cache::INSTANCE, self::class, 'ajax_poll_active');
		}
	}

	/**
	 * The plain (non-SSE) bounded wait: single response, delivered as soon as something is
	 * queued, the client disconnects (eg. the SSE ack-timeout race in egw_push_fallback.ts
	 * aborting this exact request), or AJAX_POLL_MAX_WAIT_SECONDS elapses - see ajax_poll()'s
	 * own docs.
	 */
	protected static function ajaxPollWait()
	{
		$deadline = microtime(true) + self::AJAX_POLL_MAX_WAIT_SECONDS;
		do
		{
			if (connection_aborted())
			{
				break;
			}

			$already_send = notifications_push::alreadySend();
			$max_id = notifications_push::maxId();

			// not yet initialized this session (first call ever), or genuinely something new
			// since our last delivery - either way notifications_push::get() itself decides
			// what to do (seed vs. deliver) and pays the one session_start()/write_close()
			// round-trip that takes; every other tick is 2 cheap, session-free instance-cache
			// reads
			if ((!isset($already_send) || (isset($max_id) && $max_id > $already_send)) &&
				notifications_push::get())
			{
				break;
			}
			if (microtime(true) >= $deadline)
			{
				break;
			}
			usleep(self::AJAX_POLL_TICK_USECONDS);
		}
		while(true);
	}

	/**
	 * The SSE variant: switches this held connection into a text/event-stream and keeps
	 * delivering onto it (instead of returning after the first delivery) until
	 * AJAX_POLL_SSE_MAX_WAIT_SECONDS elapses or the client disconnects.
	 *
	 * Suppresses Egw::__destruct()'s normal end-of-request JSON response send
	 * (Request::isJSONRequest(false), the exact mechanism json.php's own early-exit paths
	 * already use for the same reason) - everything this method wants to send has already been
	 * echoed and flushed directly, incompatible with also serializing+sending a second, separate
	 * JSON response afterwards the way a normal ajax_poll() call relies on.
	 *
	 * Known, accepted narrow race: Api\Json\Request::handle() unconditionally calls
	 * notifications_push::get() again right after handleRequest() returns (the older,
	 * per-response piggyback Phase 3's own docs already describe), regardless of what menuaction
	 * just ran. For the plain (non-SSE) wait above that's harmless - whatever it finds still gets
	 * sent normally. Here, since isJSONRequest(false) means nothing sends the response again,
	 * a message that happens to arrive in the brief window between this method's own last check
	 * and that piggyback call would have already_send advanced past it without ever actually
	 * being delivered - a genuine, if extremely narrow (sub-millisecond), silent loss for that
	 * one message on this one connection. Not fixed here: doing so would mean threading a guard
	 * through Api\Json\Request's own generic dispatch path for this one method, a bigger and
	 * more invasive change than this edge case's real-world likelihood/impact currently justifies.
	 */
	protected static function ajaxPollSse()
	{
		// Best-effort: some hosts disable set_time_limit() entirely (a fatal error to even call
		// if so) - if disabled, max_execution_time's own default simply ends this connection
		// earlier than AJAX_POLL_SSE_MAX_WAIT_SECONDS, which just means an earlier, harmless
		// client-side reconnect (see this constant's own docs), not a fatal error.
		if (function_exists('set_time_limit'))
		{
			set_time_limit((int)self::AJAX_POLL_SSE_MAX_WAIT_SECONDS + 5);
		}

		// Disable every buffering layer we can reach from here - nginx's fastcgi_buffering (via
		// the X-Accel-Buffering response header it specifically recognises) and PHP's own output
		// buffering. Neither guarantees an intermediate proxy/CDN we have no visibility into
		// won't still buffer regardless - that residual risk is exactly what the client-side
		// ack-vs-timeout race (egw_push_fallback.ts) exists to catch, not something this method
		// can fully rule out on its own.
		header('Content-Type: text/event-stream');
		header('Cache-Control: no-cache');
		header('X-Accel-Buffering: no');
		while (ob_get_level() > 0)
		{
			ob_end_flush();
		}

		Request::isJSONRequest(false);

		echo "event: ack\ndata: {}\n\n";
		flush();

		$deadline = microtime(true) + self::AJAX_POLL_SSE_MAX_WAIT_SECONDS;
		while (microtime(true) < $deadline)
		{
			if (connection_aborted())
			{
				break;
			}

			$already_send = notifications_push::alreadySend();
			$max_id = notifications_push::maxId();

			if ((!isset($already_send) || (isset($max_id) && $max_id > $already_send)) &&
				notifications_push::get())
			{
				echo 'data: '.json_encode(['response' => Response::get()->initResponseArray()])."\n\n";
				flush();
			}
			usleep(self::AJAX_POLL_TICK_USECONDS);
		}
	}
}
