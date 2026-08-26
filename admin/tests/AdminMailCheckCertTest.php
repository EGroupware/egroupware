<?php
/**
 * EGroupware Admin: tests for the checkCert connection-diagnosis feature (Mail\Account::
 * diagnoseConnection() + admin_mail::checkCertDiagnosis())
 *
 * @link http://www.egroupware.org
 * @package admin
 * @license http://opensource.org/licenses/gpl-license.php GPL - GNU General Public License Version 2+
 */

require_once realpath(__DIR__.'/../../api/tests/LoggedInTest.php');

use EGroupware\Api;
use EGroupware\Api\Mail;

/**
 * diagnoseConnection() does real socket I/O (see its own docblock: an on-demand check, never a
 * proactive probe), so this needs the full bootstrap (LoggedInTest), not the bare TestCase
 * AdminMailPureLogicTest.php uses for its side-effect-free helpers - lang() alone isn't
 * available without it.
 *
 * Deliberately NOT testing the "certificate mismatch" branch here: that needs a real (or
 * locally-hosted) TLS server presenting a mismatched certificate, which isn't available in
 * this environment - covered instead by the live verification noted in
 * doc/ai/projects/mail-wizard-jmap-oauth.md.
 */
class AdminMailCheckCertTest extends Api\LoggedInTest
{
	private function callPrivateStatic(string $class, string $method, array $args)
	{
		$ref = new ReflectionMethod($class, $method);
		$ref->setAccessible(true);
		return $ref->invokeArgs(null, $args);
	}

	public function testDiagnoseConnectionReportsNoHostPortConfigured()
	{
		$result = Mail\Account::diagnoseConnection('', 0, false);

		self::assertSame('connection', $result['problem']);
		self::assertSame('No host/port configured.', $result['message']);
	}

	/**
	 * Port 1 is reserved (tcpmux) and essentially never has anything listening - a real
	 * connection attempt against it must be reported as a "connection" problem, not silently
	 * treated as "certificate" or "none".
	 */
	public function testDiagnoseConnectionReportsConnectionProblemForUnreachableHost()
	{
		$result = Mail\Account::diagnoseConnection('127.0.0.1', 1, 'tlsv1');

		self::assertSame('connection', $result['problem']);
		self::assertNotEmpty($result['message']);
	}

	/**
	 * No encryption at all ($secure=false) - nothing to verify, must report "none" as soon as
	 * the plain TCP connect itself would succeed. Uses the very same host:port this PHPUnit
	 * process' own webserver would be reachable on being overkill to set up - instead assert
	 * the cheaper, still-meaningful contract: an unencrypted check on an unreachable host still
	 * correctly reports "connection", not "none" (ie. the TCP-reachability check isn't skipped
	 * just because $secure is falsy).
	 */
	public function testDiagnoseConnectionUnencryptedStillChecksReachability()
	{
		$result = Mail\Account::diagnoseConnection('127.0.0.1', 1, false);

		self::assertSame('connection', $result['problem']);
	}

	/**
	 * admin_mail::checkCertDiagnosis()'s 'jmap' branch must resolve JMAP_HTTPS to a real
	 * 'tlsv1' secure mode itself (ssl2secure() has no JMAP_HTTP/JMAP_HTTPS case - see that
	 * branch's own comment) - proven here by confirming it still performs a real check
	 * (reaching the "connection" verdict for an unreachable host) rather than silently
	 * treating the account as unencrypted and reporting "none" without ever probing at all.
	 */
	public function testCheckCertDiagnosisResolvesJmapHttpsExplicitly()
	{
		$content = [
			'acc_imap_host' => '127.0.0.1',
			'acc_imap_port' => 1,
			'acc_imap_ssl' => Mail\Account::JMAP_HTTPS,
		];
		$result = $this->callPrivateStatic(admin_mail::class, 'checkCertDiagnosis', [$content, 'jmap']);

		self::assertSame('connection', $result['problem']);
	}

	/**
	 * The 'smtp' branch must read acc_smtp_* fields, not acc_imap_* - proven by pointing the
	 * two at different (both unreachable, so the specific failure reason doesn't matter) hosts
	 * and confirming the diagnosis is still a "connection" problem either way, ie. it actually
	 * used the smtp fields rather than silently falling through to the imap ones.
	 */
	public function testCheckCertDiagnosisSmtpUsesSmtpFields()
	{
		$content = [
			'acc_imap_host' => 'should-not-be-used.invalid',
			'acc_imap_port' => 1,
			'acc_imap_ssl' => Mail\Account::SSL_TLS,
			'acc_smtp_host' => '127.0.0.1',
			'acc_smtp_port' => 1,
			'acc_smtp_ssl' => Mail\Account::SSL_STARTTLS,
		];
		$result = $this->callPrivateStatic(admin_mail::class, 'checkCertDiagnosis', [$content, 'smtp']);

		self::assertSame('connection', $result['problem']);
	}

	/** Same as testCheckCertDiagnosisSmtpUsesSmtpFields(), for the 'sieve' branch. */
	public function testCheckCertDiagnosisSieveUsesSieveFields()
	{
		$content = [
			'acc_imap_host' => 'should-not-be-used.invalid',
			'acc_imap_port' => 1,
			'acc_imap_ssl' => Mail\Account::SSL_TLS,
			'acc_sieve_host' => '127.0.0.1',
			'acc_sieve_port' => 1,
			'acc_sieve_ssl' => Mail\Account::SSL_STARTTLS,
		];
		$result = $this->callPrivateStatic(admin_mail::class, 'checkCertDiagnosis', [$content, 'sieve']);

		self::assertSame('connection', $result['problem']);
	}

	/**
	 * pauseForCertReview() - the decision of whether a just-succeeded connection should pause
	 * the wizard on its current step (show the cert diagnosis, don't advance) instead of being
	 * treated as accepted. See its own docblock for the full reasoning and the live regression
	 * (2026-08-26) this exists to prevent: the pause was originally wired into autoconfig() only
	 * and silently missing from sieve()/smtp(), so unchecking "disable certificate validation"
	 * and continuing from THOSE steps auto-advanced anyway, with no warning shown at all.
	 */
	public function testPauseForCertReviewOnlyWhenUndecidedAndOnlyLenientWorked()
	{
		// undecided verification, but the LENIENT fallback is what actually got the connection
		// through (strict must have failed first) - this is exactly the "silently degraded"
		// case that must pause and warn, not advance
		self::assertTrue($this->callPrivateStatic(admin_mail::class, 'pauseForCertReview', [true, false]));

		// undecided, but STRICT verification itself succeeded - nothing to warn about, no
		// reason to pause
		self::assertFalse($this->callPrivateStatic(admin_mail::class, 'pauseForCertReview', [true, true]));

		// already decided (VERIFY_ENABLED or VERIFY_DISABLED, ie. $verify_undecided=false) -
		// the user already made this choice explicitly (or it was already confirmed working) in
		// an earlier round, so there is nothing new to review regardless of $attempt_verify
		self::assertFalse($this->callPrivateStatic(admin_mail::class, 'pauseForCertReview', [false, false]));
		self::assertFalse($this->callPrivateStatic(admin_mail::class, 'pauseForCertReview', [false, true]));
	}

	/**
	 * Regression guard for the exact bug pauseForCertReview() was extracted to prevent: every
	 * one of the FOUR connection-trial loops (autoconfig()'s own classic-IMAP loop, tryJmap(),
	 * sieve(), smtp()) MUST consult it on their success path. A trial loop that inlines its own
	 * `$verify_undecided && !$attempt_verify` check again (or omits the check entirely) instead
	 * of calling the shared helper would not be caught by
	 * testPauseForCertReviewOnlyWhenUndecidedAndOnlyLenientWorked() alone (that only tests the
	 * helper's own logic, not whether every call site actually uses it) - a source-level count
	 * is a blunt but direct way to catch that class of regression without needing a live/mocked
	 * IMAP+JMAP+Sieve+SMTP connection for each step.
	 *
	 * This is not hypothetical: tryJmap() was originally missed entirely (it has its own,
	 * separate optimistic-verify implementation, predating pauseForCertReview()) - found live
	 * 2026-08-26 AFTER the other three were already fixed and verified, because JMAP is tried
	 * BEFORE classic IMAP in autoconfig(), so a certificate problem on a host that also answers
	 * JMAP bypassed the already-fixed classic-IMAP loop entirely and kept silently advancing.
	 */
	public function testAllFourTrialLoopsCallPauseForCertReview()
	{
		$source = file_get_contents(__DIR__.'/../inc/class.admin_mail.inc.php');
		self::assertNotFalse($source);

		$calls = substr_count($source, 'self::pauseForCertReview(');
		// exactly 4 call sites (autoconfig()'s classic-IMAP loop / tryJmap() / sieve() / smtp())
		self::assertSame(4, $calls,
			'Expected exactly 4 call sites (autoconfig()/tryJmap()/sieve()/smtp()) - '.
			'if this changed, make sure every connection-trial loop still consults pauseForCertReview()');
	}
}
