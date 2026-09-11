<?php
/**
 * EGroupware API - Api\Accounts\Import: daily catch-up scheduling (Phase 3)
 *
 * @link https://www.egroupware.org
 * @license https://opensource.org/licenses/gpl-license.php GPL - GNU General Public License
 */

namespace EGroupware\Api\Accounts\Tests;

require_once __DIR__.'/ImportTestCase.php';

use EGroupware\Api;
use EGroupware\Api\Accounts\Import;
use PHPUnit\Framework\Attributes\DataProvider;

/**
 * Covers doc/ai/projects/accounts-import-test-coverage.md's Phase 3 test-case plan item 6
 * (daily full catch-up dispatch): Import::firstRunToday() and Import::installAsyncJob()'s
 * frequency -> cron-timer-shape mapping. Both are pure config/date logic, no fixture backends
 * needed - but installAsyncJob() writes a REAL row to the shared egw_async table under the fixed
 * id Import::ASYNC_JOB_ID ('AccountsImport') - every test that calls it cancels that timer again
 * in a finally block, and the class itself confirms (in setUp()) that no such job exists before
 * touching it, refusing to run otherwise rather than risk clobbering a real scheduled job.
 *
 * NOT covered here (see doc): Import::firstRunToday()'s actual pass/fail boundary is wall-clock-
 * dependent AND, on reading, appears to have its own bug (it derives an hour/minute purely from
 * account_import_frequency's own numeric value via `mktime(floor($frequency), round((60*
 * $frequency)%60), 60)` - it never reads account_import_time at all, despite that config existing
 * specifically to configure this). This looks wrong but is a *design* question, not a
 * find-the-bounds-and-fix fix, so it's deliberately left unverified rather than encoding a
 * possibly-still-wrong assumption into a flaky, clock-dependent test - see the doc's "suspected
 * bugs, not fixed" section.
 */
class ImportSchedulingTest extends ImportTestCase
{
	protected function setUp() : void
	{
		parent::setUp();

		if ((new Api\Asyncservice())->read(Import::ASYNC_JOB_ID))
		{
			$this->markTestSkipped("A real '".Import::ASYNC_JOB_ID."' async job already exists - ".
				'refusing to run installAsyncJob() tests against it (would cancel/overwrite a real scheduled job).');
		}
	}

	/**
	 * account_import_frequency=0 (or unset) must never install a periodic job, and must cancel
	 * any existing one - Import::installAsyncJob()'s unconditional cancel_timer() call up front,
	 * only reinstalling if $frequency > 0.
	 */
	public function testZeroFrequencyNeverInstallsJob() : void
	{
		$installed = $this->invokeInstallAsyncJob(0.0, null);
		try
		{
			$this->assertFalse($installed, 'installAsyncJob(0.0) must report no job installed');
			$this->assertFalse((new Api\Asyncservice())->read(Import::ASYNC_JOB_ID),
				'No async job should exist after installAsyncJob(0.0)');
		}
		finally
		{
			(new Api\Asyncservice())->cancel_timer(Import::ASYNC_JOB_ID);
		}
	}

	/**
	 * Data-provider-style coverage of installAsyncJob()'s frequency -> cron-shape mapping
	 * (api/src/Accounts/Import.php, the "Install async job for periodic import" method):
	 * >=36h -> day: * /N; >=24h -> daily; >=1h -> hourly (or every Nh); >=.1h -> every-N-minutes.
	 */
	public static function frequencyMappingProvider() : array
	{
		return [
			'48h -> every 2 days'   => [48.0, '05:30', ['hour' => 5, 'min' => 30, 'day' => '*/2']],
			'24h -> daily'          => [24.0, '05:30', ['hour' => 5, 'min' => 30, 'day' => '*']],
			'2h -> every 2 hours'   => [2.0, '05:30', ['hour' => '*/2', 'min' => 30]],
			'1h -> hourly'          => [1.0, '05:30', ['hour' => '*', 'min' => 30]],
			'.5h -> every 30 min'   => [0.5, null, ['min' => '*/30']],
			'.1h -> every 5 min'    => [0.1, null, ['min' => '*/5']],
		];
	}

	#[DataProvider('frequencyMappingProvider')]
	public function testFrequencyMapsToExpectedCronShape(float $frequency, ?string $time, array $expected_times) : void
	{
		$installed = $this->invokeInstallAsyncJob($frequency, $time);
		try
		{
			$this->assertTrue($installed, "installAsyncJob($frequency) should report a job was installed");
			$job = $this->readAsyncJob();
			$this->assertNotNull($job, 'The async job should exist after installAsyncJob()');
			$this->assertSame($expected_times, $job['times'], 'Stored cron-shape (async_times) did not match');
		}
		finally
		{
			(new Api\Asyncservice())->cancel_timer(Import::ASYNC_JOB_ID);
		}
	}

	/**
	 * frequency=0 but a valid account_import_time is still configured must be treated as "daily"
	 * (frequency implicitly becomes 24.0) - Import::installAsyncJob()'s
	 * `if (empty($frequency) && !empty($time) && preg_match('/^\d{2}:\d{2}$/', $time)) $frequency = 24.0;`
	 */
	public function testZeroFrequencyWithTimeBecomesDaily() : void
	{
		$installed = $this->invokeInstallAsyncJob(0.0, '06:15');
		try
		{
			$this->assertTrue($installed, 'A configured account_import_time with frequency=0 should still install a daily job');
			$job = $this->readAsyncJob();
			$this->assertNotNull($job, 'The async job should exist after installAsyncJob()');
			$this->assertSame(['hour' => 6, 'min' => 15, 'day' => '*'], $job['times']);
		}
		finally
		{
			(new Api\Asyncservice())->cancel_timer(Import::ASYNC_JOB_ID);
		}
	}

	private function invokeInstallAsyncJob(float $frequency, ?string $time) : bool
	{
		$rm = new \ReflectionMethod(Import::class, 'installAsyncJob');
		$rm->setAccessible(true);
		return $rm->invoke(null, $frequency, $time);
	}

	/**
	 * Asyncservice::read($id) returns jobs keyed by async_id (`['AccountsImport' => [...]]`), not
	 * the job row directly - a real gotcha for anyone else reaching for this API the "obvious" way.
	 */
	private function readAsyncJob() : ?array
	{
		$jobs = (new Api\Asyncservice())->read(Import::ASYNC_JOB_ID);
		return $jobs[Import::ASYNC_JOB_ID] ?? null;
	}
}
