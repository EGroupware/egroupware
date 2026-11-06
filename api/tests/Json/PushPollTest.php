<?php
/**
 * EGroupware Api: Push::ajax_poll() bounded long-poll regression tests
 *
 * @link http://www.egroupware.org
 * @package api
 * @subpackage json
 * @license http://opensource.org/licenses/gpl-license.php GPL - GNU General Public License
 */

namespace EGroupware\Api\Json;

require_once realpath(__DIR__.'/../LoggedInTest.php');

use EGroupware\Api;
use EGroupware\Api\LoggedInTest;
use notifications_push;

/**
 * Api\Json\Push::ajax_poll() (doc/ai/projects/push-fallback-longpoll.md Phase 3) - the bounded
 * long-poll endpoint that stands in for a real push server on installs with no swoolepush daemon.
 * Client-side driver: api/js/jsapi/egw_push_fallback.ts (only calls this while
 * egw.pushAvailable() is false).
 *
 * Setup strategy: LoggedInTest boots a real session/DB. Each test starts by clearing any
 * notifications_push cache state (Api\Cache::getSession()/getInstance() 'already_send'/'max_id')
 * a prior test in this same PHPUnit process may have left, and any leftover 'push'-type rows for
 * this account - notifications_push::get()'s baseline-seeding behaviour otherwise makes a test's
 * outcome depend on execution order.
 *
 * Pass criteria:
 * - get() returns false when nothing is queued, true when it actually delivered something.
 * - alreadySend()/maxId() reflect that without needing a session reopen of their own.
 * - ajax_poll() returns essentially immediately (well under one tick) when something is already
 *   queued when it's called - the one behaviour that would be expensive/slow to get wrong (the
 *   whole point of the tick-based check is to avoid the more expensive get() call, and of get()
 *   itself is to avoid a session_start()/write_close() round-trip, on every tick).
 *
 * NOT covered: the "nothing ever arrives, wait the full AJAX_POLL_MAX_WAIT_SECONDS and give up"
 * path - same bounded-loop-with-sleep shape already exercised by the "found immediately" case,
 * just with real time slept instead of returning early; testing it would only add ~20s per run
 * for no additional coverage of anything that could actually go wrong differently. Also not
 * covered: the admin-configured override of AJAX_POLL_DEFAULT_MAX_CONCURRENT
 * (notifications' 'ajax_poll_max_concurrent' config) - that's just Api\Config::read(), not new
 * behaviour introduced by this project.
 */
class PushPollTest extends LoggedInTest
{
	private int $account_id;

	protected function setUp() : void
	{
		parent::setUp();

		$this->account_id = $GLOBALS['egw_info']['user']['account_id'];

		Api\Cache::unsetSession(notifications_push::class, 'already_send');
		Api\Cache::unsetInstance(notifications_push::class, 'max_id');
		Api\Cache::unsetInstance(Push::class, 'ajax_poll_active');

		$GLOBALS['egw']->db->delete('egw_notificationpopup', [
			'account_id' => $this->account_id,
			'notify_type' => 'push',
		], __LINE__, __FILE__, 'notifications');
	}

	/**
	 * Queue a push message directly via notifications_push::addGeneric() - the fallback
	 * PushBackend get()/ajax_poll() actually read from - rather than via Api\Json\Push itself,
	 * whose backend SELECTION (real push vs. this SQL fallback, Push::checkSetBackend()) depends
	 * on whether eg. swoolepush happens to be installed on whatever instance runs this test and
	 * is out of scope here; what's under test is only "does something already sitting in the
	 * fallback queue get noticed/delivered", not "does Push() route here in the first place".
	 */
	private function queuePushMessage()
	{
		(new notifications_push())->addGeneric($this->account_id, 'apply',
			['func' => 'egw.push', 'parms' => [['app' => 'test', 'id' => 1, 'type' => 'update']]]);
	}

	public function testGetReturnsFalseWithNothingQueued()
	{
		// first call ever this session just seeds the baseline - nothing could be "new" yet
		$this->assertFalse(notifications_push::get());
		// a second call, still with nothing queued since that baseline, must also report nothing
		$this->assertFalse(notifications_push::get());
	}

	public function testGetReturnsTrueAndDeliversAQueuedMessage()
	{
		notifications_push::get();	// seed the baseline first, same as testGetReturnsFalse...

		$this->queuePushMessage();

		$this->assertTrue(notifications_push::get());
		// already delivered - immediately calling get() again must not re-report it
		$this->assertFalse(notifications_push::get());
	}

	public function testAlreadySendAndMaxIdReflectStateWithoutReopeningTheSession()
	{
		$this->assertNull(notifications_push::alreadySend(), 'no baseline seeded yet');

		notifications_push::get();	// seeds already_send = current max_id
		$seeded = notifications_push::alreadySend();
		$this->assertNotNull($seeded);
		$this->assertSame($seeded, notifications_push::maxId());

		$this->queuePushMessage();
		$this->assertGreaterThan($seeded, notifications_push::maxId(),
			'maxId() must reflect the newly queued row (addGeneric() updates the instance cache)');
		$this->assertSame($seeded, notifications_push::alreadySend(),
			'alreadySend() must NOT advance on its own - only a real get() delivery does that');
	}

	public function testAjaxPollReturnsImmediatelyWhenSomethingIsAlreadyQueued()
	{
		notifications_push::get();	// seed the baseline
		$this->queuePushMessage();

		$start = microtime(true);
		Push::ajax_poll();
		$elapsed = microtime(true) - $start;

		$this->assertLessThan(Push::AJAX_POLL_TICK_USECONDS / 1e6, $elapsed,
			'ajax_poll() must deliver on its very first check, not sleep through a tick first');
	}

	public function testAjaxPollActuallyDeliversTheMessageIntoTheResponse()
	{
		notifications_push::get();
		$this->queuePushMessage();

		Push::ajax_poll();

		// the message was Push()->apply('egw.push', [...]) - replayed via
		// notifications_push::get() into Json\Response::get(), same object every ajax response
		// assembles from - a real apply() call, not a mock, means this is only reachable if
		// ajax_poll() actually replayed it (as opposed to eg. an exception being silently caught)
		$response = Response::get()->initResponseArray();
		$applyCalls = array_filter($response, fn($entry) => ($entry['type'] ?? null) === 'apply' &&
			($entry['data']['func'] ?? null) === 'egw.push');
		$this->assertNotEmpty($applyCalls, 'expected an egw.push apply() call in the assembled response');
	}

	/**
	 * The concurrency safety valve (Phase 4): a normal, non-degraded call must not leak its own
	 * "I'm holding a slot" claim behind after it returns - the counter incrementCache()d on entry
	 * must be back at 0 once ajax_poll() returns, whether it found something or waited it out.
	 */
	public function testConcurrencyCounterReturnsToZeroAfterANormalCall()
	{
		notifications_push::get();
		$this->queuePushMessage();

		Push::ajax_poll();

		$this->assertSame(0, Api\Cache::getInstance(Push::class, 'ajax_poll_active'));
	}

	/**
	 * Once AJAX_POLL_DEFAULT_MAX_CONCURRENT calls are already "holding" (simulated here the same
	 * way concurrent real calls would each increment it), a further call must degrade to a
	 * single, immediate, non-holding check instead of joining that queue - and must say so via
	 * Response::data(['degraded' => true]), so the client (egw_push_fallback.ts) knows to back
	 * off instead of re-issuing immediately against an endpoint that just responded instantly for
	 * that very reason.
	 */
	public function testAjaxPollDegradesOnceConcurrencyBudgetIsExhausted()
	{
		Api\Cache::setInstance(Push::class, 'ajax_poll_active', Push::AJAX_POLL_DEFAULT_MAX_CONCURRENT);
		notifications_push::get();	// seed the baseline
		$this->queuePushMessage();	// something IS queued - a degraded call must still not wait/deliver via the loop

		$start = microtime(true);
		Push::ajax_poll();
		$elapsed = microtime(true) - $start;

		$this->assertLessThan(Push::AJAX_POLL_TICK_USECONDS / 1e6, $elapsed,
			'a degraded call must return immediately, not join the wait loop');

		$response = Response::get()->initResponseArray();
		$dataCalls = array_filter($response, fn($entry) => ($entry['type'] ?? null) === 'data');
		$this->assertNotEmpty($dataCalls, 'a degraded call must report Response::data([\'degraded\' => true])');
		$this->assertTrue((array_values($dataCalls)[0]['data']['degraded'] ?? null) === true);

		// must not have left its own (never-actually-claimed) slot counted either
		$this->assertSame(Push::AJAX_POLL_DEFAULT_MAX_CONCURRENT,
			Api\Cache::getInstance(Push::class, 'ajax_poll_active'),
			'a degraded call must release the slot it briefly claimed while checking the budget');
	}

	/**
	 * The one-below-the-limit case must NOT degrade - only a call that pushes the count strictly
	 * ABOVE the configured budget does.
	 */
	public function testAjaxPollDoesNotDegradeExactlyAtTheBudget()
	{
		Api\Cache::setInstance(Push::class, 'ajax_poll_active', Push::AJAX_POLL_DEFAULT_MAX_CONCURRENT - 1);
		notifications_push::get();
		$this->queuePushMessage();

		Push::ajax_poll();

		$response = Response::get()->initResponseArray();
		$dataCalls = array_filter($response, fn($entry) => ($entry['type'] ?? null) === 'data');
		$this->assertEmpty($dataCalls, 'the call that exactly fills the budget must not degrade');

		$applyCalls = array_filter($response, fn($entry) => ($entry['type'] ?? null) === 'apply');
		$this->assertNotEmpty($applyCalls, 'and must still have delivered the queued message normally');
	}
}
