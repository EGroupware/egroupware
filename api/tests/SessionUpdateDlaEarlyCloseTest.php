<?php
/**
 * EGroupware Api: Test that Session::update_dla() survives the early-session-close scheme
 *
 * @link https://www.egroupware.org
 * @package api
 * @subpackage test
 * @license http://opensource.org/licenses/gpl-license.php GPL - GNU General Public License
 */

namespace EGroupware\Api;

require_once __DIR__ . '/LoggedInTest.php';

/**
 * A 2022 attempt at closing PHP sessions early for json.php requests (opening them with
 * session_start(['read_and_close' => true]), fe4d0dbbe32a57b7f5d690a04770f4aea1b78fa0)
 * broke Collabora editing: Session::update_dla() (called from verify() on almost every
 * authenticated request) writes the session-timeout timestamp raw into $_SESSION, and
 * under read_and_close that write was silently discarded (the session was already closed
 * at session_start() time, before verify()/update_dla() even ran) - so the timeout
 * timestamp stopped advancing during AJAX-heavy activity, and verify()'s own timeout
 * check eventually killed the session out from under an active edit.
 *
 * The fix this time is structural, not a change to update_dla() itself: the session is
 * only closed explicitly AFTER verify() (and therefore update_dla()) has completed
 * (json.php, right after header.inc.php's bootstrap), so update_dla()'s write always
 * happens on a normally-open session - it can never fall into the closed-session buffer
 * in the first place. This test proves the write actually persists across a real
 * close+reopen cycle using that ordering.
 */
class SessionUpdateDlaEarlyCloseTest extends LoggedInTest
{
	public function testDlaSurvivesVerifyThenEarlyClose()
	{
		$session = $GLOBALS['egw']->session;
		$sessionid = $session->sessionid;
		$kp3 = $session->kp3;

		// CLI test runs have no real client IP ($_SERVER['REMOTE_ADDR'] is unset), so on an
		// install with sessions_checkip enabled (eg. a fresh CI install - it's not on this dev
		// box, which is why this only surfaced there) verify()'s IP check trips: it treats an
		// empty/never-recorded session_ip as invalid even when getuser_ip() would also be empty.
		// LoggedInTest::setUpBeforeClass() already logged in with no REMOTE_ADDR, so session_ip
		// is already empty in storage - fix both sides to a consistent, real-looking value while
		// the session is still open (a direct $_SESSION write here is fine; it's only writes made
		// AFTER the session is closed that need the tracked-write buffer).
		$_SERVER['REMOTE_ADDR'] = '127.0.0.1';
		$_SESSION[Session::EGW_SESSION_VAR]['session_ip'] = '127.0.0.1';

		// Framework.php has its own PRE-EXISTING, unrelated commit_session() calls (eg. while
		// serving static-ish content) that can leave writes buffered from the LoggedInTest
		// bootstrap itself - flush those first, so the assertions below only reflect what
		// happens in THIS test's own verify()/commit_session() sequence.
		Cache::flush_session_writes();
		$session->commit_session();
		$this->assertNotSame(PHP_SESSION_ACTIVE, session_status());

		// simulate some time having passed since the last dla update, well within any
		// reasonable sessions_timeout so verify() does not consider the session expired
		$stale_dla = time() - 5;
		$_SESSION[Session::EGW_SESSION_VAR]['session_dla'] = $stale_dla;
		$session->commit_session();
		$this->assertNotSame(PHP_SESSION_ACTIVE, session_status());

		// reattach explicitly (as json.php's header.inc.php bootstrap does via
		// Egw::verify_session()) - this is what runs update_dla() internally
		$this->assertTrue($session->verify($sessionid, $kp3),
			'Session unexpectedly failed to verify/was considered expired');

		// close early, exactly as json.php now does right after verify_session() completes
		$session->commit_session();
		$this->assertNotSame(PHP_SESSION_ACTIVE, session_status());

		// nothing should be buffered - update_dla()'s write happened while the session was
		// still normally open, so it needed no special tracking and no reopen is required
		Cache::flush_session_writes();
		$this->assertNotSame(PHP_SESSION_ACTIVE, session_status(),
			'flush_session_writes() reopened the session - update_dla() write was not persisted normally');

		// reopen for real (as the next request would) and confirm the dla actually advanced
		$this->assertTrue($session->verify($sessionid, $kp3));
		$this->assertGreaterThan($stale_dla, $_SESSION[Session::EGW_SESSION_VAR]['session_dla'],
			'session_dla was not updated/persisted - this is the exact mechanism that broke Collabora in 2022');
	}

	protected function tearDown() : void
	{
		if (session_status() !== PHP_SESSION_ACTIVE)
		{
			$GLOBALS['egw']->session->verify($GLOBALS['egw']->session->sessionid, $GLOBALS['egw']->session->kp3);
		}
		parent::tearDown();
	}
}
