<?php
/**
 * Test EGroupware\Api\Mail\Jmap\Imap::dispatch() logs an exception it turns into a "serverFail"
 * JMAP method-error, instead of only the client ever seeing it
 *
 * @link http://www.egroupware.org
 * @package mail
 * @license http://opensource.org/licenses/gpl-license.php GPL - GNU General Public License
 */

namespace EGroupware\Mail;

use EGroupware\Api\Mail\Jmap\Imap;

/**
 * Ticket #125161 (a real customer, forwards consistently failing with a raw, untranslated
 * "The object could not be deleted because it does not exist." - RFC 5530's generic NONEXISTENT
 * IMAP response, worded for "deleted" regardless of which command actually triggered it):
 * dispatch()'s own per-method-call `catch (\Throwable $e)` turns ANY exception into a "serverFail"
 * JMAP method-error, `description` set to the exception's raw, untranslated message - a NORMAL
 * JMAP protocol response shape, not something any app-level exception boundary (Api\Json\Request,
 * json.php, egw_exception_handler) ever sees, so NOTHING logged it - confirmed live: the
 * customer's own server error log had no trace of it at all, despite it clearly reaching the
 * client. Fixed by calling _egw_log_exception() here too, so the NEXT occurrence shows exactly
 * which underlying exception it actually was.
 */
class JmapShimDispatchLogsUncaughtExceptionsTest extends \PHPUnit\Framework\TestCase
{
	/**
	 * An unsupported method name is the cheapest way to reach this exact catch block without a
	 * live IMAP connection - dispatch()'s own `default: throw new \Exception(...)` case is INSIDE
	 * the same try/catch as every real per-method call, so it's caught (and now logged) identically.
	 */
	public function testUnsupportedMethodIsLoggedNotJustReturnedToTheClient()
	{
		$log = tempnam(sys_get_temp_dir(), 'egw-jmap-dispatch-uncaught-');
		$error_log = ini_get('error_log') ?: '';
		ini_set('error_log', $log);
		// _egw_log_exception() deliberately no-ops under true CLI operation (its own docblock:
		// "if not running as cli, which outputs the error_log to stderr and therefore output it
		// twice") - an EARLIER test in the same PHPUnit process (any LoggedInTest-based one) can
		// leave this flag set to 'cli' for the rest of the run, silently defeating this test's own
		// assertion depending on run order alone. Pinned here so this test's own pass/fail reflects
		// only the fix under test, not what happened to run before it.
		$no_exception_handler = $GLOBALS['egw_info']['flags']['no_exception_handler'] ?? null;
		$GLOBALS['egw_info']['flags']['no_exception_handler'] = false;

		try
		{
			$responses = Imap::dispatch([['NoSuchMethod/get', [], 'call1']]);
		}
		finally
		{
			$logged = file_get_contents($log);
			ini_set('error_log', $error_log);
			unlink($log);
			$GLOBALS['egw_info']['flags']['no_exception_handler'] = $no_exception_handler;
		}

		// unchanged client-facing shape - the client must still get exactly the same response
		$this->assertCount(1, $responses);
		[$method, $result, $callId] = $responses[0];
		$this->assertSame('error', $method);
		$this->assertSame('serverFail', $result['type']);
		$this->assertStringContainsString("Unsupported method 'NoSuchMethod/get'", $result['description']);
		$this->assertSame('call1', $callId);

		// the actual fix: the same exception must ALSO have reached the server error log
		$this->assertStringContainsString("Unsupported method 'NoSuchMethod/get'", $logged,
			'nothing logged server-side for an exception the client is shown verbatim');
	}
}
