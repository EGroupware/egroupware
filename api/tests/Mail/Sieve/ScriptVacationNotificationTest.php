<?php
/**
 * EGroupware Api: Test Api\Mail\Sieve\Script's vacation/email-notification interaction against a
 * real Sieve engine (Dovecot Pigeonhole's sieve-test)
 *
 * @link https://www.egroupware.org
 * @package api
 * @subpackage mail
 * @license http://opensource.org/licenses/gpl-license.php GPL - GNU General Public License
 */

namespace EGroupware\Api\Mail\Sieve;

/**
 * Regression tests for two real bugs found live 2026-09-09 investigating a follow-up report on
 * commit c7db44df ("mail: fix vacation notice + forward still keeping mail in INBOX"):
 *
 * 1. The vacation modus="notice" fix itself (c7db44df) only guarded the explicit `keep;` INSIDE
 *    the vacation-forward rule - updateScript()'s own "email notification on new mail" feature
 *    appended a SECOND, entirely unconditional `keep;` at the very end of the script whenever that
 *    (unrelated) feature was also enabled, silently undoing the fix for any account that also has
 *    it on.
 * 2. Separately, whenever vacation and email-notification were combined with NO other active mail
 *    filter rules, the generated `require [...]` header line was either double-closed (when
 *    vacation was currently active) or split into two unclosed/garbled require statements (when
 *    vacation was configured but not currently active) - genuinely invalid Sieve syntax that made
 *    the WHOLE script fail to compile, silently disabling forwarding/the vacation notice/discard
 *    alike, not just the "keep" behaviour.
 *
 * Rather than trust RFC 5228/5230 reading alone (redirect/discard DO cancel Sieve's "implicit
 * keep", vacation itself does not affect it either way), these tests generate the ACTUAL script
 * updateScript() produces and run it through a real Sieve interpreter - Dovecot Pigeonhole's
 * `sieve-test` CLI (part of the dovecot-sieve/dovecot-pigeonhole package) - against a sample
 * message, asserting on its own reported compile result and performed actions. This is the
 * reusable "how do I test Sieve script behaviour" technique this investigation established:
 * no live mail server needed, just the sieve-test binary and a plain .sieve + .eml file pair.
 */
class ScriptVacationNotificationTest extends \PHPUnit\Framework\TestCase
{
	private static ?string $sieveTestBinary = null;

	public static function setUpBeforeClass() : void
	{
		$path = trim((string)shell_exec('command -v sieve-test 2>/dev/null'));
		self::$sieveTestBinary = $path !== '' ? $path : null;
	}

	protected function setUp() : void
	{
		if (self::$sieveTestBinary === null)
		{
			$this->markTestSkipped('sieve-test (Dovecot Pigeonhole) is not installed - install it '.
				'(e.g. "apt-get install dovecot-sieve") to run these regression tests.');
		}
	}

	private function fakeConnection() : Connection
	{
		return new class implements Connection
		{
			public function __construct($params=[]) {}
			public function listScripts() { return []; }
			public function getActive() { return null; }
			public function getScript($scriptname) { return ''; }
			public function installScript($scriptname, $script, $makeactive=false) { return true; }
			public function removeScript($scriptname) { return true; }
			public function hasSpace($scriptname, $size) { return true; }
			public function getExtensions()
			{
				return ['vacation', 'variables', 'regex', 'date', 'relational', 'envelope', 'editheader', 'body', 'enotify'];
			}
			public function hasExtension($extension) { return in_array($extension, $this->getExtensions()); }
		};
	}

	private function generateScript(?array $vacation, array $emailNotification=[]) : string
	{
		$script = new Script('sieve-test-fixture');
		if ($vacation !== null) $script->vacation = $vacation;
		$script->emailNotification = $emailNotification;
		$vac_rule = null;
		$script->updateScript($this->fakeConnection(), false, $vac_rule, true);
		return $script->script;
	}

	/**
	 * Runs a generated script against a sample message via sieve-test and returns its combined
	 * stdout+stderr, exactly as a developer would from the shell.
	 */
	private function runSieveTest(string $script, string $toAddress='user@example.org') : string
	{
		$dir = sys_get_temp_dir().'/sieve-test-'.bin2hex(random_bytes(6));
		mkdir($dir.'/maildir', 0777, true);

		$mailUser = posix_getpwnam('www-data') ? 'www-data' : (posix_getpwuid(posix_geteuid())['name'] ?? 'nobody');

		file_put_contents($dir.'/script.sieve', $script);
		file_put_contents($dir.'/message.eml',
			"From: sender@elsewhere.example\r\n".
			"To: $toAddress\r\n".
			"Subject: Test message\r\n".
			"Date: Mon, 09 Sep 2026 10:00:00 +0000\r\n".
			"Message-Id: <test@elsewhere.example>\r\n".
			"\r\n".
			"This is a test message body.\r\n");
		file_put_contents($dir.'/dovecot-sieve-test.conf',
			"mail_uid = $mailUser\n".
			"mail_gid = $mailUser\n".
			// docker/CI typically runs as root - sieve-test refuses outright to drop privileges
			// TO root, so a real (non-root) mail_uid + a low first_valid_uid are both needed
			"first_valid_uid = 0\n".
			"first_valid_gid = 0\n".
			"mail_location = maildir:".$dir.'/maildir'."\n");

		$cmd = escapeshellarg(self::$sieveTestBinary).' -c '.escapeshellarg($dir.'/dovecot-sieve-test.conf').
			' '.escapeshellarg($dir.'/script.sieve').' '.escapeshellarg($dir.'/message.eml').' 2>&1';
		$output = (string)shell_exec($cmd);

		foreach (glob($dir.'/maildir/*/*') ?: [] as $f) @unlink($f);
		foreach (glob($dir.'/maildir/*') ?: [] as $d) @rmdir($d);
		@unlink($dir.'/script.sieve');
		@unlink($dir.'/message.eml');
		@unlink($dir.'/dovecot-sieve-test.conf');
		@rmdir($dir.'/maildir');
		@rmdir($dir);

		return $output;
	}

	private function assertCompiledSuccessfully(string $output) : void
	{
		$this->assertStringContainsString('final result: success', $output,
			"the generated script must be valid, compilable Sieve syntax:\n$output");
	}

	private function assertMessageIsKept(string $output) : void
	{
		$this->assertCompiledSuccessfully($output);
		$this->assertStringContainsString('store message in folder: INBOX', $output,
			"expected the message to be kept (explicitly or implicitly) in INBOX:\n$output");
	}

	private function assertMessageIsNotKept(string $output) : void
	{
		$this->assertCompiledSuccessfully($output);
		$this->assertStringNotContainsString('store message in folder: INBOX', $output,
			"expected the message NOT to be kept anywhere:\n$output");
	}

	private function baseVacation(array $overrides=[]) : array
	{
		return array_merge([
			'status' => 'on',
			'text' => 'I am out of office',
			'days' => 7,
			'addresses' => ['user@example.org'],
			'forwards' => 'forward@example.org',
			'modus' => 'notice',
		], $overrides);
	}

	private function notificationOn() : array
	{
		return ['status' => 'on', 'externalEmail' => 'notify@example.org', 'displaySubject' => true];
	}

	// --- the original bug (c7db44df) - confirmed fixed ---

	public function testVacationNoticeWithForwardDoesNotKeepTheMessage()
	{
		$output = $this->runSieveTest($this->generateScript($this->baseVacation()));

		$this->assertMessageIsNotKept($output);
		$this->assertStringContainsString('redirect message to: <forward@example.org>', $output);
		$this->assertStringContainsString('discard', $output);
	}

	// --- bug 1: the unconditional trailing keep when email notification is also on ---

	public function testVacationNoticeWithForwardAndEmailNotificationStillDoesNotKeepTheMessage()
	{
		$output = $this->runSieveTest($this->generateScript($this->baseVacation(), $this->notificationOn()));

		$this->assertMessageIsNotKept($output);
		$this->assertStringContainsString('redirect message to: <forward@example.org>', $output);
		$this->assertStringContainsString("send notification with method 'mailto:'", $output,
			"the notify action itself must still fire - only the trailing keep should be suppressed");
	}

	// --- bug 2: require[...] syntax corruption when vacation + notification combine ---

	public function testVacationActiveWithEmailNotificationAndNoOtherRulesCompiles()
	{
		$output = $this->runSieveTest($this->generateScript($this->baseVacation(), $this->notificationOn()));

		$this->assertCompiledSuccessfully($output);
	}

	public function testVacationConfiguredButInactiveWithEmailNotificationCompilesAndKeepsNormally()
	{
		$output = $this->runSieveTest($this->generateScript(
			$this->baseVacation(['status' => 'off', 'text' => '']), $this->notificationOn()));

		$this->assertMessageIsKept($output);
		$this->assertStringNotContainsString('redirect', $output, "an inactive vacation rule must not redirect at all");
	}

	// --- regression guards: normal keep-preserving combinations must be unaffected ---

	public function testNoVacationWithEmailNotificationStillKeepsTheMessage()
	{
		$output = $this->runSieveTest($this->generateScript(null, $this->notificationOn()));

		$this->assertMessageIsKept($output);
		$this->assertStringContainsString("send notification with method 'mailto:'", $output);
	}

	public function testVacationModusStoreWithEmailNotificationStillKeepsTheMessage()
	{
		$output = $this->runSieveTest($this->generateScript(
			$this->baseVacation(['modus' => 'store', 'forwards' => '']), $this->notificationOn()));

		$this->assertMessageIsKept($output);
	}

	public function testVacationModusNoticeAndStoreWithEmailNotificationStillKeepsTheMessage()
	{
		$output = $this->runSieveTest($this->generateScript(
			$this->baseVacation(['modus' => 'notice+store']), $this->notificationOn()));

		$this->assertMessageIsKept($output);
		$this->assertStringContainsString('redirect message to: <forward@example.org>', $output);
		$this->assertStringContainsString('send vacation message', $output);
		$this->assertStringContainsString("send notification with method 'mailto:'", $output);
	}
}
