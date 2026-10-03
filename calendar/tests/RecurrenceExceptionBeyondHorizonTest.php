<?php
/**
 * Exceptions of a recurring event beyond the calendar horizont
 *
 * Recurrences of an unlimited series are only stored in egw_cal_dates up to the horizont, and an exception is only
 * a flag on such a row, so an exception behind the horizont (eg. an EXDATE imported by CalDAV) used to be lost.
 * Moving the horizont continues after MAX(cal_start) of the series, which therefore must not be affected by a
 * stored exception behind the horizont (it would leave a gap), and moving it must keep the exception flag.
 *
 * The tests do NOT move the global horizont (it would create rows for all recurring events of the installation),
 * they replay what calendar_bo::check_move_horizont() does for the test event only.
 *
 * @link http://www.egroupware.org
 * @package calendar
 * @subpackage tests
 * @license http://opensource.org/licenses/gpl-license.php GPL - GNU General Public License
 */

namespace EGroupware\calendar;

require_once realpath(__DIR__.'/../../api/tests/AppTest.php');	// Application test base

use EGroupware\Api;

class RecurrenceExceptionBeyondHorizonTest extends \EGroupware\Api\AppTest
{
	const TZID = 'Europe/Berlin';

	/** @var \calendar_boupdate */
	protected $bo;

	/** @var int[] */
	protected $event_ids = [];

	/** @var \DateTimeZone */
	protected $tz;

	protected function setUp() : void
	{
		parent::setUp();
		$this->bo = new \calendar_boupdate();
		$this->tz = new \DateTimeZone(self::TZID);
		// pin the horizont of OUR bo object (not persisted), the one of the installation depends on what was viewed before
		$this->bo->config['horizont'] = (new \DateTimeImmutable('now', new \DateTimeZone('UTC')))->modify('+380 days')->getTimestamp();
	}

	protected function tearDown() : void
	{
		foreach(array_unique($this->event_ids) as $id)
		{
			$this->bo->delete($id, 0, true);
			$this->bo->delete($id, 0, true);	// again to remove from delete history
		}
		$this->event_ids = [];
		parent::tearDown();
	}

	/**
	 * n-th occurrence of the monthly series: start + n months
	 */
	protected function occurrence(int $n) : Api\DateTime
	{
		$start = new Api\DateTime($this->seriesStart(), $this->tz);
		return $n ? (clone $start)->modify("+$n months") : $start;
	}

	/**
	 * Start of the series: the 15th of a month, at least 20 days from now (day <= 28: no overflow in later months)
	 */
	protected function seriesStart() : string
	{
		$d = (new \DateTimeImmutable('today', $this->tz))->modify('+20 days');
		if ((int)$d->format('j') > 15) $d = $d->modify('first day of next month');
		return $d->format('Y-m-15').' 10:00:00';
	}

	/**
	 * Index of the first occurrence behind the current horizont plus $offset
	 */
	protected function indexBehindHorizont(int $offset = 1) : int
	{
		$horizont = (int)$this->bo->config['horizont'];
		for($n = 0; $n < 400; ++$n)
		{
			if ($this->occurrence($n)->getTimestamp() > $horizont) return $n + $offset;
		}
		$this->fail('No occurrence behind the horizont');
	}

	protected function createSeries(array $exceptions) : int
	{
		$start = $this->occurrence(0);
		$end = (clone $start)->modify('+1 hour');
		$event = [
			'title'           => 'Monthly '.uniqid(),
			'owner'           => $GLOBALS['egw_info']['user']['account_id'],
			'start'           => $start,
			'end'             => $end,
			'tzid'            => self::TZID,
			'recur_type'      => MCAL_RECUR_MONTHLY_MDAY,
			'recur_interval'  => 1,
			'recur_exception' => $exceptions,
			'participants'    => [$GLOBALS['egw_info']['user']['account_id'] => 'A'],
		];
		$id = (int)$this->bo->save($event, true);
		$this->assertGreaterThan(0, $id, 'Series could not be created');
		$this->event_ids[] = $id;

		return $id;
	}

	/**
	 * @return array cal_start (Y-m-d) => recur_exception flag, as stored in egw_cal_dates
	 */
	protected function rows(int $id) : array
	{
		$rows = [];
		foreach($GLOBALS['egw']->db->select('egw_cal_dates', 'cal_start,recur_exception', ['cal_id' => $id],
			__LINE__, __FILE__, false, 'ORDER BY cal_start', 'calendar') as $row)
		{
			$rows[(new Api\DateTime((int)$row['cal_start'], Api\DateTime::$server_timezone))->setTimezone($this->tz)->format('Y-m-d')] = (int)$row['recur_exception'];
		}
		return $rows;
	}

	/**
	 * Replay calendar_bo::check_move_horizont() for just the given event: continue where unfinished_recuring() says
	 *
	 * @return int[] cal_id => continuation (max cal_start) as returned by unfinished_recuring() for the old horizont
	 */
	protected function moveHorizont(int $id, int $new_horizont) : array
	{
		$old = (int)$this->bo->config['horizont'];
		$recuring = $this->bo->so->unfinished_recuring(new Api\DateTime($old, Api\DateTime::$server_timezone));
		$this->assertArrayHasKey($id, $recuring, 'unfinished_recuring() does not return the series');
		$this->bo->config['horizont'] = $new_horizont;
		try
		{
			$next_start = new Api\DateTime($recuring[$id], Api\DateTime::$server_timezone);
			$next_start->modify('+1 second');
			$next_start->setUser();
			$this->bo->set_recurrences($this->bo->read($id), $next_start);
		}
		finally
		{
			$this->bo->config['horizont'] = $old;
		}
		return $recuring;
	}

	/**
	 * Pass criteria: an exception behind the horizont is stored and read back, not silently dropped
	 */
	public function testExceptionBehindHorizontIsStored()
	{
		$far = $this->occurrence($this->indexBehindHorizont(2));
		$id = $this->createSeries([clone $far]);

		$stored = array_map(fn($e) => $e->setTimezone($this->tz)->format('Y-m-d'), $this->bo->read($id)['recur_exception'] ?? []);
		$this->assertSame([$far->format('Y-m-d')], $stored, 'exception behind the horizont lost, rows: '.json_encode($this->rows($id)));
	}

	/**
	 * Pass criteria: the continuation point for moving the horizont is NOT the far exception, otherwise the occurrences
	 * between the horizont and the exception would never be created
	 */
	public function testContinuationPointIgnoresExceptionBehindHorizont()
	{
		$far = $this->occurrence($this->indexBehindHorizont(2));
		$id = $this->createSeries([clone $far]);

		$recuring = $this->bo->so->unfinished_recuring(new Api\DateTime((int)$this->bo->config['horizont'], Api\DateTime::$server_timezone));
		$this->assertArrayHasKey($id, $recuring);
		$this->assertLessThanOrEqual((int)$this->bo->config['horizont'], $recuring[$id]->getTimestamp(),
			'continuation point '.$recuring[$id]->format('Y-m-d').' is behind the horizont, rows: '.json_encode($this->rows($id)));
	}

	/**
	 * Pass criteria: after moving the horizont past the exception there is a row for every occurrence (no gap), the
	 * exception is still flagged (not overwritten) and it is the only flagged one
	 */
	public function testMovingHorizontKeepsExceptionAndHasNoGap()
	{
		$n_far = $this->indexBehindHorizont(2);
		$far = $this->occurrence($n_far);
		$id = $this->createSeries([clone $far]);

		$this->moveHorizont($id, $this->occurrence($n_far + 3)->getTimestamp());

		$rows = $this->rows($id);
		for($n = 0; $n <= $n_far + 2; ++$n)
		{
			$this->assertArrayHasKey($this->occurrence($n)->format('Y-m-d'), $rows, "occurrence #$n has no row (gap), rows: ".json_encode($rows));
		}
		$this->assertSame([$far->format('Y-m-d')], array_keys(array_filter($rows)), 'exception flag lost or moved, rows: '.json_encode($rows));
		$this->assertCount(1, $this->bo->read($id)['recur_exception'] ?? [], 'exception not read back');
	}

	/**
	 * Pass criteria: the excluded occurrence is not found by a search over its date, while its neighbours are
	 */
	public function testExcludedOccurrenceIsNotListed()
	{
		$n_far = $this->indexBehindHorizont(2);
		$far = $this->occurrence($n_far);
		$id = $this->createSeries([clone $far]);
		$this->moveHorizont($id, $this->occurrence($n_far + 3)->getTimestamp());

		// search() moves AND PERSISTS the horizont if the range ends behind it, so make sure it does not
		$this->bo->config['horizont'] = $this->occurrence($n_far + 3)->getTimestamp();
		$count = function(Api\DateTime $day) use ($id)
		{
			$start = (clone $day)->setTime(0, 0, 0);
			$end = (clone $day)->setTime(23, 59, 59);
			$found = $this->bo->search(['start' => $start, 'end' => $end, 'users' => $GLOBALS['egw_info']['user']['account_id'],
				'enum_recuring' => true, 'daywise' => false, 'date_format' => 'ts', 'cfs' => []]);
			return count(array_filter((array)$found, fn($ev) => $ev['id'] == $id));
		};
		$this->assertSame(1, $count($this->occurrence($n_far - 1)), 'control: the occurrence before the exception must be listed');
		$this->assertSame(0, $count($far), 'the excluded occurrence is listed');
		$this->assertSame(1, $count($this->occurrence($n_far + 1)), 'control: the occurrence after the exception must be listed');
	}

	/**
	 * Pass criteria: removing the exception again clears the flag (nothing is excluded anymore), and the occurrence is
	 * created like any other when the horizont moves past it
	 */
	public function testRemovingTheExceptionRestoresTheOccurrence()
	{
		$n_far = $this->indexBehindHorizont(2);
		$far = $this->occurrence($n_far);
		$id = $this->createSeries([clone $far]);
		$this->moveHorizont($id, $this->occurrence($n_far + 3)->getTimestamp());

		$event = $this->bo->read($id);
		$event['recur_exception'] = [];
		$this->bo->save($event, true);

		$this->assertSame([], array_keys(array_filter($this->rows($id))), 'flag still set, rows: '.json_encode($this->rows($id)));
		$this->assertSame([], $this->bo->read($id)['recur_exception'] ?? [], 'exception still read back');

		$this->moveHorizont($id, $this->occurrence($n_far + 3)->getTimestamp());
		$rows = $this->rows($id);
		$this->assertArrayHasKey($far->format('Y-m-d'), $rows, 'occurrence not created when moving the horizont, rows: '.json_encode($rows));
		$this->assertSame(0, $rows[$far->format('Y-m-d')], 'occurrence still flagged as exception');
	}

	/**
	 * Pass criteria: only exceptions up to the maximum horizont (default 1000 days) are stored, so a client can not make
	 * us create an unlimited number of rows: an exception behind it is dropped, one just inside is kept
	 */
	public function testExceptionsBehindTheMaximumHorizontAreNotStored()
	{
		$max_days = !empty($GLOBALS['egw_info']['server']['calendar_horizont']) ? abs((int)$GLOBALS['egw_info']['server']['calendar_horizont']) : 1000;
		$n_inside = $n_outside = null;
		for($n = 1; $n < 400; ++$n)
		{
			$days = ($this->occurrence($n)->getTimestamp() - time()) / 86400;
			if ($days < $max_days - 40) $n_inside = $n;
			if ($n_outside === null && $days > $max_days + 40) $n_outside = $n;
		}
		$this->assertGreaterThan($this->indexBehindHorizont(0), $n_inside, 'setup: no occurrence behind the horizont but inside the maximum');

		$id = $this->createSeries([$this->occurrence($n_inside), $this->occurrence($n_outside)]);

		$this->assertSame([$this->occurrence($n_inside)->format('Y-m-d')], array_keys(array_filter($this->rows($id))),
			'rows: '.json_encode($this->rows($id)));
		$this->assertLessThanOrEqual(1 + $n_inside, count($this->rows($id)), 'unexpected number of rows');
	}

	/**
	 * Pass criteria: a long list of exceptions behind the maximum horizont creates no rows at all
	 */
	public function testManyExceptionsBehindTheMaximumHorizontCreateNoRows()
	{
		$exceptions = [];
		for($n = 1; $n <= 60; ++$n)
		{
			$exceptions[] = $this->occurrence($this->indexBehindHorizont(0) + 40 + $n);	// years in the future
		}
		$id = $this->createSeries($exceptions);

		$rows = $this->rows($id);
		$this->assertSame([], array_keys(array_filter($rows)), 'rows: '.json_encode($rows));
		$this->assertLessThanOrEqual($this->indexBehindHorizont(0) + 1, count($rows), 'too many rows: '.count($rows));
	}
}
