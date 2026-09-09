<?php
/**
 * EGroupware Mail: Test ApiHandler's returnVacation()/parseAddressList() helpers
 *
 * @link http://www.egroupware.org
 * @package mail
 * @license http://opensource.org/licenses/gpl-license.php GPL - GNU General Public License
 */

namespace EGroupware\Mail;

/**
 * doc/ai/projects/mail-test-coverage.md's priority-3 entry: ApiHandler.php's vacation-handling
 * methods are almost entirely untested. getVacation()/updateVacation() themselves need a real
 * IMAP/Sieve connection (Account::imapServer()), out of scope here, but the two pure helpers they
 * both go through - returnVacation() (sieve-internal shape -> REST JSON shape) and
 * parseAddressList() (also used directly by updateVacation() for forwards/addresses) - have no
 * such dependency at all, so tested directly via ReflectionMethod (both protected static), same
 * pattern as ImapBuildMailerTest.php's invokeBuildMailer().
 */
class ApiHandlerVacationTest extends \PHPUnit\Framework\TestCase
{
	private function invokeReturnVacation(array $vacation) : array
	{
		$method = new \ReflectionMethod(ApiHandler::class, 'returnVacation');
		$method->setAccessible(true);
		return $method->invoke(null, $vacation);
	}

	private function invokeParseAddressList(array $addresses, ?string $name=null) : array
	{
		$method = new \ReflectionMethod(ApiHandler::class, 'parseAddressList');
		$method->setAccessible(true);
		return $method->invoke(null, $addresses, $name);
	}

	// --- returnVacation() ---

	public function testReturnVacationMapsStartAndEndDateTimestampsToYMdStrings()
	{
		$vacation = $this->invokeReturnVacation(['status' => 'on', 'start_date' => strtotime('2026-01-01'), 'end_date' => strtotime('2026-01-10')]);

		$this->assertSame('2026-01-01', $vacation['start']);
		$this->assertSame('2026-01-10', $vacation['end']);
	}

	public function testReturnVacationSplitsTheCommaSeparatedForwardsStringIntoAnArray()
	{
		$vacation = $this->invokeReturnVacation(['forwards' => 'a@example.org, b@example.org']);

		$this->assertSame(['a@example.org', 'b@example.org'], $vacation['forwards']);
	}

	public function testReturnVacationPassesAddressesArrayThroughUnchanged()
	{
		$vacation = $this->invokeReturnVacation(['addresses' => ['user@example.org']]);

		$this->assertSame(['user@example.org'], $vacation['addresses']);
	}

	public function testReturnVacationDefaultsStatusToOffAndModusToNoticeAndStoreWhenMissing()
	{
		$vacation = $this->invokeReturnVacation([]);

		$this->assertSame('off', $vacation['status']);
		$this->assertSame('notice+store', $vacation['modus']);
	}

	/**
	 * array_filter() (no callback) drops every falsy value - documents current behaviour, not
	 * necessarily ideal: an explicit "0 days" or a missing start/end/text/addresses/script simply
	 * has NO key at all in the returned array, rather than an explicit null/0/[] - a caller reading
	 * this JSON response can't distinguish "not set" from "explicitly zero/empty" for any of these.
	 */
	public function testReturnVacationOmitsFalsyFieldsEntirelyRatherThanReturningThemAsNullOrZero()
	{
		$vacation = $this->invokeReturnVacation(['status' => 'on', 'days' => 0]);

		$this->assertArrayNotHasKey('days', $vacation, "an explicit 0 days is indistinguishable from 'not set' in the response");
		$this->assertArrayNotHasKey('start', $vacation);
		$this->assertArrayNotHasKey('end', $vacation);
		$this->assertArrayNotHasKey('text', $vacation);
		$this->assertArrayNotHasKey('addresses', $vacation);
		$this->assertArrayNotHasKey('forwards', $vacation);
		$this->assertArrayNotHasKey('script', $vacation);
	}

	public function testReturnVacationCastsDaysToInt()
	{
		$vacation = $this->invokeReturnVacation(['days' => '5']);

		$this->assertSame(5, $vacation['days']);
	}

	// --- parseAddressList() ---

	public function testParseAddressListReturnsBareMailboxAtHostForEachValidAddress()
	{
		$parsed = $this->invokeParseAddressList(['User Name <user@example.org>', 'other@example.org']);

		$this->assertSame(['user@example.org', 'other@example.org'], $parsed);
	}

	public function testParseAddressListReturnsAnEmptyArrayForAnEmptyInput()
	{
		$this->assertSame([], $this->invokeParseAddressList([]));
	}

	public function testParseAddressListThrowsWithTheAttributeNameAndRawInputWhenAnAddressIsInvalid()
	{
		try
		{
			$this->invokeParseAddressList(['not-an-email'], 'forwards');
			$this->fail('expected an Exception');
		}
		catch (\Exception $e)
		{
			$this->assertStringContainsString('forwards', $e->getMessage());
			$this->assertStringContainsString('not-an-email', $e->getMessage());
		}
	}
}
