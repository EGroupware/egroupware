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
 * The "certificate mismatch" branch itself (testDiagnoseConnectionReportsCertificateMismatch*
 * below) needs a real, locally-hosted TLS server presenting a mismatched certificate - started
 * here as its own short-lived PHP process (openssl-generated self-signed cert), one per test and
 * terminated again in that test's finally, rather than a long-running daemon.
 */
class AdminMailCheckCertTest extends Api\LoggedInTest
{
	private function callPrivateStatic(string $class, string $method, array $args)
	{
		$ref = new ReflectionMethod($class, $method);
		$ref->setAccessible(true);
		return $ref->invokeArgs(null, $args);
	}

	/**
	 * Per-class temp dir holding the generated certificate/key and the listener script. Randomised
	 * per run so two PHPUnit processes sharing a host cannot clobber each other's fixtures.
	 */
	private static $fixture_dir;

	/**
	 * Creates, once per class, the fixture dir, the listener script and a self-signed certificate
	 * whose CN deliberately does NOT match 127.0.0.1 - mimicking a real mismatched-certificate
	 * server, eg. the reported "cert issued for Arens_IMAP, not for 10.28.1.6" case.
	 *
	 * @return array{0: string, 1: string, 2: string} [cert, key, listener script] paths
	 */
	private static function certFixtures() : array
	{
		if (!isset(self::$fixture_dir))
		{
			self::$fixture_dir = sys_get_temp_dir().'/AdminMailCheckCertTest-'.bin2hex(random_bytes(4));
			mkdir(self::$fixture_dir);
			file_put_contents(self::$fixture_dir.'/listener.php', self::listenerSource());
			exec('openssl req -x509 -newkey rsa:2048 -keyout '.escapeshellarg(self::$fixture_dir.'/key.pem').
				' -out '.escapeshellarg(self::$fixture_dir.'/cert.pem').
				' -days 1 -nodes -subj "/CN=not-127.0.0.1.invalid" 2>/dev/null');
			self::assertFileExists(self::$fixture_dir.'/cert.pem', 'openssl did not generate a test certificate');
		}
		return [self::$fixture_dir.'/cert.pem', self::$fixture_dir.'/key.pem', self::$fixture_dir.'/listener.php'];
	}

	/**
	 * Source of the listener, which runs as its own PHP process: it binds an ephemeral loopback
	 * port and prints it on stdout, which tells the caller both which port to connect to and that
	 * the socket is already accepting - no polling needed.
	 *
	 * It then serves implicit TLS: accept() a plain TCP connection and immediately negotiate TLS
	 * server-side, matching a real "IMAP (TLS/SSL)" account ($secure='tlsv1', no STARTTLS text)
	 * and therefore diagnoseConnection()'s/probeCertVerification()'s own client-side timing for
	 * that mode. It keeps serving until the caller terminates it, so a single listener covers
	 * however many probes one diagnoseConnection() call makes; the accept() timeout exists only so
	 * a listener orphaned by a crashed caller eventually exits by itself.
	 */
	private static function listenerSource() : string
	{
		return <<<'LISTENER'
<?php
[, $cert, $key] = $argv;
$server = stream_socket_server('tcp://127.0.0.1:0', $errno, $errstr, STREAM_SERVER_BIND | STREAM_SERVER_LISTEN);
if (!$server)
{
	fwrite(STDERR, "bind failed: $errstr\n");
	exit(1);
}
$name = stream_socket_get_name($server, false);
fwrite(STDOUT, substr($name, strrpos($name, ':') + 1)."\n");
while (($conn = @stream_socket_accept($server, 30)))
{
	stream_context_set_option($conn, 'ssl', 'local_cert', $cert);
	stream_context_set_option($conn, 'ssl', 'local_pk', $key);
	@stream_socket_enable_crypto($conn, true, STREAM_CRYPTO_METHOD_TLS_SERVER);
	fclose($conn);
}

LISTENER;
	}

	/**
	 * Starts the mismatched-certificate listener as its OWN process, the way this suite's other
	 * helper processes are started (AdminAccountDeleteAclTest, RestClientTraitTest).
	 *
	 * Deliberately NOT pcntl_fork(): a forked child inherits this process's already-open MySQL
	 * connection, and running its shutdown sends COM_QUIT over that shared socket - which ends the
	 * session the PARENT is still using. Api\Db\Pdo::$pdo is a process-wide static that nothing
	 * resets, and Sqlfs\StreamWrapper never reconnects, so every VFS access for the rest of the
	 * PHPUnit run then dies with "MySQL server has gone away".
	 *
	 * Returns once the child has reported its port, ie. once it is accepting connections.
	 *
	 * @return array{0: int, 1: array} [port, server handle for stopMismatchedCertServer()]
	 */
	private function startMismatchedCertServer() : array
	{
		[$cert, $key, $script] = self::certFixtures();

		$process = proc_open([PHP_BINARY, '-f', $script, '--', $cert, $key],
			[1 => ['pipe', 'w'], 2 => ['pipe', 'w']], $pipes);
		self::assertIsResource($process, 'failed to start the TLS test listener');
		$server = ['process' => $process, 'pipes' => $pipes];

		stream_set_timeout($pipes[1], 10);
		$port = (int)fgets($pipes[1]);
		if (!$port)
		{
			$err = stream_get_contents($pipes[2]);
			self::stopMismatchedCertServer($server);
			self::fail('TLS test listener did not report a port'.($err ? ": $err" : ''));
		}
		return [$port, $server];
	}

	/**
	 * Terminates a listener started by startMismatchedCertServer(). Safe to call twice.
	 */
	private static function stopMismatchedCertServer(array $server) : void
	{
		foreach ($server['pipes'] as $pipe)
		{
			if (is_resource($pipe)) fclose($pipe);
		}
		if (is_resource($server['process']))
		{
			proc_terminate($server['process']);
			proc_close($server['process']);
		}
	}

	/**
	 * Remove the generated certificate/key and listener script - the parent also ends the session
	 * every test class gets, so our own cleanup has to happen before it.
	 */
	public static function tearDownAfterClass() : void
	{
		if (isset(self::$fixture_dir))
		{
			array_map('unlink', glob(self::$fixture_dir.'/*'));
			rmdir(self::$fixture_dir);
			self::$fixture_dir = null;
		}
		parent::tearDownAfterClass();
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

	/**
	 * Baseline: against a real, locally-hosted server presenting a certificate for a different
	 * name than the one connected to, diagnoseConnection() must report 'certificate' - proves the
	 * fake server helper itself actually reproduces a genuine mismatch, so the $verifyDisabled=true
	 * test right below is checking a real behavior change, not a fake server that never triggers
	 * the branch in the first place.
	 */
	public function testDiagnoseConnectionReportsCertificateMismatch()
	{
		[$port, $server] = $this->startMismatchedCertServer();
		try {
			$result = Mail\Account::diagnoseConnection('127.0.0.1', $port, 'tlsv1');

			self::assertSame('certificate', $result['problem']);
			self::assertStringContainsString('127.0.0.1', $result['message']);
		}
		finally {
			self::stopMismatchedCertServer($server);
		}
	}

	/**
	 * The actual fix (see Mail\Account::diagnoseConnection()'s own $verifyDisabled docblock): an
	 * account that already has VERIFY_DISABLED set for this exact mismatch must NOT have it
	 * re-reported as 'certificate' - the user already saw and accepted that risk, so from here on
	 * a successful lenient connection is reported as 'none', leaving any LATER, unrelated failure
	 * free to be diagnosed on its own merits instead of being misattributed back to "it's still
	 * the certificate" (the live bug report this fix addresses: a customer checked "disable
	 * certificate validation", saved, and kept seeing the exact same certificate-mismatch wizard
	 * popup on every later connection hiccup, looking as if the checkbox simply did nothing).
	 */
	public function testDiagnoseConnectionSkipsCertificateReportWhenVerifyDisabled()
	{
		[$port, $server] = $this->startMismatchedCertServer();
		try {
			$result = Mail\Account::diagnoseConnection('127.0.0.1', $port, 'tlsv1', '', true);

			self::assertSame('none', $result['problem']);
			self::assertNull($result['message']);
		}
		finally {
			self::stopMismatchedCertServer($server);
		}
	}

	/**
	 * checkCertDiagnosis() must derive $verifyDisabled from the account's OWN already-saved
	 * acc_imap_ssl value (VERIFY_DISABLED bit), not default to always-strict - proven end-to-end
	 * through the real 'imap' branch, the same one admin_mail::edit()'s checkCert GET-param
	 * handling actually calls.
	 */
	public function testCheckCertDiagnosisReadsVerifyDisabledFromAccountSsl()
	{
		[$port, $server] = $this->startMismatchedCertServer();
		try {
			$content = [
				'acc_imap_host' => '127.0.0.1',
				'acc_imap_port' => $port,
				'acc_imap_ssl' => Mail\Account::SSL_TLS | Mail\Account::VERIFY_DISABLED,
			];
			$result = $this->callPrivateStatic(admin_mail::class, 'checkCertDiagnosis', [$content, 'imap']);

			self::assertSame('none', $result['problem']);
		}
		finally {
			self::stopMismatchedCertServer($server);
		}
	}
}
