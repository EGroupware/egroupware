<?php
/**
 * EGroupware API: test for responding early to a long running "Sync now" of a subscribed calendar
 *
 * @link https://www.egroupware.org
 * @package api
 * @subpackage caldav
 * @license http://opensource.org/licenses/gpl-license.php GPL - GNU General Public License
 */

namespace EGroupware\Api\CalDAV;

use EGroupware\Api;
use PHPUnit\Framework\TestCase;

/**
 * Exposes the protected helpers of Sync
 */
class SyncRespondEarlyStub extends Sync
{
	public static function slow(float $now, ?float $start) : bool
	{
		return self::isSlow($now, $start);
	}

	public static function acquire(int $cat_id) : bool
	{
		return self::lock($cat_id);
	}

	public static function release(int $cat_id) : void
	{
		self::unlock($cat_id);
	}
}

/**
 * A "Sync now" of a big calendar takes minutes, the browser warns after 30s "No response from server: your data is
 * probably NOT saved", and the sync could be aborted by PHP's max_execution_time.
 *
 * Sync therefore responds to the client after RESPOND_AFTER seconds (Api\Egw::finish_request()) and continues in
 * the background. A cron-job or an other "Sync now" must not start a second sync of the same subscription meanwhile.
 */
class SyncRespondEarlyTest extends TestCase
{
	const CAT_ID = 987654321;

	protected function tearDown() : void
	{
		SyncRespondEarlyStub::release(self::CAT_ID);
	}

	public function testRespondsOnlyAfterTheTimeLimit()
	{
		$this->assertFalse(SyncRespondEarlyStub::slow(1000.0 + Sync::RESPOND_AFTER - 0.1, 1000.0));
		$this->assertTrue(SyncRespondEarlyStub::slow(1000.0 + Sync::RESPOND_AFTER, 1000.0));
		$this->assertTrue(SyncRespondEarlyStub::slow(1000.0 + 10 * Sync::RESPOND_AFTER, 1000.0));
	}

	public function testRespondingNeedsAKnownStartOfTheRequest()
	{
		$this->assertFalse(SyncRespondEarlyStub::slow(1000000.0, null), 'start of request unknown, eg. in CLI');
	}

	public function testRespondingIsBeforeTheBrowserWarning()
	{
		$this->assertLessThan(30, Sync::RESPOND_AFTER, 'the browser warns "No response from server" after 30s');
		$this->assertLessThan(90, Sync::RESPOND_AFTER, 'PHPs max_execution_time of the farm is 90s');
	}

	public function testALockPreventsAParallelSyncUntilItIsReleased()
	{
		$this->assertFalse(Sync::isRunning(self::CAT_ID));

		$this->assertTrue(SyncRespondEarlyStub::acquire(self::CAT_ID));
		$this->assertTrue(Sync::isRunning(self::CAT_ID));
		$this->assertFalse(SyncRespondEarlyStub::acquire(self::CAT_ID), 'a second sync of the same subscription is refused');

		SyncRespondEarlyStub::release(self::CAT_ID);
		$this->assertFalse(Sync::isRunning(self::CAT_ID));
		$this->assertTrue(SyncRespondEarlyStub::acquire(self::CAT_ID), 'free again after the first one finished');
	}

	public function testLocksOfDifferentSubscriptionsAreIndependent()
	{
		$this->assertTrue(SyncRespondEarlyStub::acquire(self::CAT_ID));
		try {
			$this->assertTrue(SyncRespondEarlyStub::acquire(self::CAT_ID+1));
			$this->assertFalse(Sync::isRunning(self::CAT_ID+2));
		}
		finally {
			SyncRespondEarlyStub::release(self::CAT_ID+1);
		}
	}

	public function testFinishRequestIsNotPossibleWithoutFastCgi()
	{
		$this->assertFalse(function_exists('fastcgi_finish_request'), 'tests run in CLI');
		$this->assertFalse(Api\Egw::finish_request(), 'must not send anything or close the session, if we can not finish the request');
		$this->assertFalse(Api\Egw::finish_request());
	}
}
