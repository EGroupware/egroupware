<?php
/**
 * EGroupware Api: Test Cache's closed-session write-tracking buffer
 *
 * @link https://www.egroupware.org
 * @package api
 * @subpackage test
 * @license http://opensource.org/licenses/gpl-license.php GPL - GNU General Public License
 */

namespace EGroupware\Api;

require_once __DIR__ . '/LoggedInTest.php';

/**
 * When the PHP session is closed early (eg. by json.php, to not block other concurrent
 * requests), a Cache::setSession()/getSession()/unsetSession() write must survive an eventual
 * session_start() reload (which otherwise reloads $_SESSION fresh from storage, discarding
 * anything written to it in the meantime) - Cache buffers such writes (Cache::
 * $closed_session_writes) and Cache::flush_session_writes() reopens the session and re-applies
 * them. Writes are ALSO applied to $_SESSION immediately (not just buffered) so that a
 * getSession() call later in the SAME still-closed request sees them correctly too (found live
 * 2026-09-02: Mail\Smime's session-cached passphrase, set and re-read within one json.php
 * request, silently returned null - see testSameRequestReadAfterWriteWhileClosed()). This is
 * safe specifically because flush_session_writes() always replays every buffered write back
 * into $_SESSION after reopening it, regardless of what was in $_SESSION in between.
 *
 * This is the mechanism a naive raw `$_SESSION[...] = ...` write (like the one that broke
 * Collabora editing in 2022, see Session::update_dla()) bypasses entirely - these tests
 * pin down that the tracked path actually survives a close/flush cycle.
 */
class CacheSessionWriteTrackingTest extends LoggedInTest
{
	/**
	 * setSession()/unsetSession() calls made while the session is closed must be buffered,
	 * not lost, and must be applied once flush_session_writes() reopens the session.
	 */
	public function testSetUnsetSurviveCloseAndFlush()
	{
		$app = __CLASS__;

		Cache::setSession($app, 'open_before_close', 'value-while-open');
		$this->assertSame('value-while-open', Cache::getSession($app, 'open_before_close'));

		$GLOBALS['egw']->session->commit_session();
		$this->assertNotSame(PHP_SESSION_ACTIVE, session_status());

		// writes made while closed are also applied to $_SESSION immediately (see class docblock)
		Cache::setSession($app, 'set_while_closed', 'value-while-closed');
		$this->assertSame('value-while-closed', $_SESSION[Session::EGW_APPSESSION_VAR][$app]['set_while_closed'] ?? null);

		Cache::unsetSession($app, 'open_before_close');
		$this->assertArrayNotHasKey('open_before_close', $_SESSION[Session::EGW_APPSESSION_VAR][$app] ?? [],
			'unsetSession() must apply to $_SESSION immediately while closed too');

		Cache::flush_session_writes();
		$this->assertSame(PHP_SESSION_ACTIVE, session_status(),
			'flush_session_writes() must reopen the session when there is something to write');

		$this->assertSame('value-while-closed', $_SESSION[Session::EGW_APPSESSION_VAR][$app]['set_while_closed'] ?? null,
			'Buffered setSession() write was not applied on flush');
		$this->assertArrayNotHasKey('open_before_close', $_SESSION[Session::EGW_APPSESSION_VAR][$app] ?? [],
			'Buffered unsetSession() write was not applied on flush');

		// leave the session open, as a normal request would find it after Cache::flush_session_writes()
		$GLOBALS['egw']->session->commit_session();
	}

	/**
	 * flush_session_writes() must be a no-op (must NOT reopen the session) when nothing was
	 * written while closed - reopening unconditionally would defeat the point of closing early.
	 */
	public function testFlushIsNoopWhenNothingBuffered()
	{
		$GLOBALS['egw']->session->commit_session();
		$this->assertNotSame(PHP_SESSION_ACTIVE, session_status());

		Cache::flush_session_writes();

		$this->assertNotSame(PHP_SESSION_ACTIVE, session_status(),
			'flush_session_writes() reopened the session even though nothing was buffered');

		// re-open for any tests running after this one
		$GLOBALS['egw']->session->verify($GLOBALS['egw']->session->sessionid, $GLOBALS['egw']->session->kp3);
	}

	/**
	 * A getSession() call must see a setSession()/unsetSession() write made moments earlier in
	 * the SAME still-closed request - not just after an eventual flush_session_writes() reopen.
	 * Real-world case that surfaced this (2026-09-02): Mail\Smime caches the user's S/MIME
	 * passphrase via setSession() then re-reads it via getSession() within one json.php send
	 * request (both while the session stays closed the whole time) - the passphrase was silently
	 * lost, so the user got re-prompted for it on every send.
	 */
	public function testSameRequestReadAfterWriteWhileClosed()
	{
		$app = __CLASS__;

		$GLOBALS['egw']->session->commit_session();
		$this->assertNotSame(PHP_SESSION_ACTIVE, session_status());

		Cache::setSession($app, 'passphrase', 'secret-value');
		$this->assertSame('secret-value', Cache::getSession($app, 'passphrase'),
			'setSession() then getSession() in the same closed-session request must round-trip');

		Cache::unsetSession($app, 'passphrase');
		$this->assertNull(Cache::getSession($app, 'passphrase'),
			'unsetSession() then getSession() in the same closed-session request must see the removal');

		// re-open for any tests running after this one
		$GLOBALS['egw']->session->verify($GLOBALS['egw']->session->sessionid, $GLOBALS['egw']->session->kp3);
	}

	/**
	 * The deprecated by-reference getSession() pattern (mutate the returned reference instead
	 * of calling setSession() explicitly) must still work across a close/flush cycle, via the
	 * snapshot-and-compare closure recorded at getSession() call time.
	 */
	public function testByReferenceMutationSurvivesCloseAndFlush()
	{
		$app = __CLASS__;
		Cache::setSession($app, 'by_ref', 'initial');
		$GLOBALS['egw']->session->commit_session();
		$this->assertNotSame(PHP_SESSION_ACTIVE, session_status());

		$ref =& Cache::getSession($app, 'by_ref');
		$ref = 'mutated-via-reference';

		Cache::flush_session_writes();

		$this->assertSame('mutated-via-reference', $_SESSION[Session::EGW_APPSESSION_VAR][$app]['by_ref'] ?? null,
			'By-reference mutation of getSession() was lost across the close/flush cycle');

		$GLOBALS['egw']->session->commit_session();
	}

	protected function tearDown() : void
	{
		// make sure a session is open again for whatever runs next, regardless of test outcome
		if (session_status() !== PHP_SESSION_ACTIVE)
		{
			$GLOBALS['egw']->session->verify($GLOBALS['egw']->session->sessionid, $GLOBALS['egw']->session->kp3);
		}
		parent::tearDown();
	}
}
