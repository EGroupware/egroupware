<?php
/**
 * DST edge-case tests for recurring events
 *
 * These tests ensure daily recurrences keep their wall-clock time across DST transitions
 * for a couple of representative timezones (Europe/Berlin and America/New_York).
 *
 * @package calendar
 * @subpackage tests
 */

namespace EGroupware\calendar;

require_once realpath(__DIR__.'/../../api/tests/AppTest.php');

use EGroupware\Api;

class TimezoneDSTTest extends \EGroupware\Api\AppTest
{
    protected $bo;
    protected $event_ids = [];
    protected static $orig_date_tz;

    public static function setUpBeforeClass(): void
    {
        parent::setUpBeforeClass();
        self::$orig_date_tz = date_default_timezone_get();
    }

    public static function tearDownAfterClass(): void
    {
        // restore PHP default timezone
        date_default_timezone_set(self::$orig_date_tz);
        parent::tearDownAfterClass();
    }

    protected function setUp(): void
    {
        parent::setUp();
        $this->bo = new \calendar_boupdate();
    }

    protected function tearDown(): void
    {
        foreach(array_unique($this->event_ids) as $id)
        {
            $this->bo->delete($id, 0, true);
            $this->bo->delete($id, 0, true);
        }
        parent::tearDown();
    }

    protected function setTimezones(string $client, string $server)
    {
        $GLOBALS['egw_info']['server']['server_timezone'] = $server;
        $GLOBALS['egw_info']['user']['preferences']['common']['tz'] = $client;
        // Keep PHP date/time functions aligned with server timezone for consistency
        date_default_timezone_set($server);
        Api\DateTime::init();
    }

    protected function createRecurringEvent(string $startStr, int $durationHours, string $tzid, string $recurEndStr, bool $whole_day=false)
    {
        // Api\DateTime uses the user timezone set by Api\DateTime::init() when no tz passed to constructor
        $start = new Api\DateTime($startStr);
        if ($whole_day)
        {
            // whole-day event: start at 00:00:00 user-time, end at 23:59:59 user-time
            $start->setTime(0,0,0);
            $end = clone $start;
            $end->setTime(23,59,59);
        }
        else
        {
            $end = new Api\DateTime($startStr);
            $end->add($durationHours.' hours');
        }

        $event = [
            'title' => 'DST test '.$startStr,
            'owner' => $GLOBALS['egw_info']['user']['account_id'],
            'start' => $start,
            'end' => $end,
            'tzid' => $tzid,
            'recur_type' => 1, // daily
            'recur_enddate' => new Api\DateTime($recurEndStr),
            'whole_day' => $whole_day,
            'participants' => [
                $GLOBALS['egw_info']['user']['account_id'] => 'A'
            ],
        ];

        return $event;
    }

    /**
     * Verify Berlin DST start keeps daily recurrence wall time stable.
     *
     * Pass criteria:
     * - Recurrence rows are generated.
     * - Every generated occurrence resolves to 09:00:00 in user timezone.
     */
    public function testBerlinDSTStartKeepsWallTime()
    {
        $this->setTimezones('Europe/Berlin', 'UTC');

        // Start day before DST starts in Europe (2020-03-29 is DST start). Daily event at 09:00
        $event = $this->createRecurringEvent('2020-03-28 09:00:00', 1, 'Europe/Berlin', '2020-04-02 00:00:00');
        $id = $this->bo->save($event);
        $this->assertGreaterThan(0, $id);
        $this->event_ids[] = $id;

        $so = new \calendar_so();
        $recs = $so->get_recurrences($id);
        unset($recs[0]);
        $this->assertNotEmpty($recs);
        foreach ($recs as $recur_start => $participant)
        {
            $this->assertEquals('09:00:00', Api\DateTime::to(Api\DateTime::server2user($recur_start), 'H:i:s'));
        }
    }

    /**
     * Verify Berlin DST end keeps daily recurrence wall time stable.
     *
     * Pass criteria:
     * - Recurrence rows are generated.
     * - Every generated occurrence resolves to 09:00:00 in user timezone.
     */
    public function testBerlinDSTEndKeepsWallTime()
    {
        $this->setTimezones('Europe/Berlin', 'UTC');

        // Start day before DST ends in Europe (2020-10-25 is DST end). Daily event at 09:00
        $event = $this->createRecurringEvent('2020-10-24 09:00:00', 1, 'Europe/Berlin', '2020-10-30 00:00:00');
        $id = $this->bo->save($event);
        $this->assertGreaterThan(0, $id);
        $this->event_ids[] = $id;

        $so = new \calendar_so();
        $recs = $so->get_recurrences($id);
        unset($recs[0]);
        $this->assertNotEmpty($recs);
        foreach ($recs as $recur_start => $participant)
        {
            $this->assertEquals('09:00:00', Api\DateTime::to(Api\DateTime::server2user($recur_start), 'H:i:s'));
        }
    }

    /**
     * Verify New York DST start keeps daily recurrence wall time stable.
     *
     * Pass criteria:
     * - Recurrence rows are generated.
     * - Every generated occurrence resolves to 09:00:00 in user timezone.
     */
    public function testNYDSTStartKeepsWallTime()
    {
        $this->setTimezones('America/New_York', 'UTC');

        // US DST start 2020-03-08 - create daily event crossing that date
        $event = $this->createRecurringEvent('2020-03-07 09:00:00', 1, 'America/New_York', '2020-03-12 00:00:00');
        $id = $this->bo->save($event);
        $this->assertGreaterThan(0, $id);
        $this->event_ids[] = $id;

        $so = new \calendar_so();
        $recs = $so->get_recurrences($id);
        unset($recs[0]);
        $this->assertNotEmpty($recs);
        foreach ($recs as $recur_start => $participant)
        {
            $this->assertEquals('09:00:00', Api\DateTime::to(Api\DateTime::server2user($recur_start), 'H:i:s'));
        }
    }

    /**
     * Verify New York DST end keeps daily recurrence wall time stable.
     *
     * Pass criteria:
     * - Recurrence rows are generated.
     * - Every generated occurrence resolves to 09:00:00 in user timezone.
     */
    public function testNYDSTEndKeepsWallTime()
    {
        $this->setTimezones('America/New_York', 'UTC');

        // US DST end 2020-11-01 - create daily event crossing that date
        $event = $this->createRecurringEvent('2020-10-31 09:00:00', 1, 'America/New_York', '2020-11-05 00:00:00');
        $id = $this->bo->save($event);
        $this->assertGreaterThan(0, $id);
        $this->event_ids[] = $id;

        $so = new \calendar_so();
        $recs = $so->get_recurrences($id);
        unset($recs[0]);
        $this->assertNotEmpty($recs);
        foreach ($recs as $recur_start => $participant)
        {
            $this->assertEquals('09:00:00', Api\DateTime::to(Api\DateTime::server2user($recur_start), 'H:i:s'));
        }
    }

    /**
     * Verify half-hour timezone DST transition keeps wall time stable.
     *
     * Pass criteria:
     * - Recurrence rows are generated.
     * - Every generated occurrence resolves to 09:00:00 in user timezone.
     */
    public function testAdelaideHalfHourDSTKeepsWallTime()
    {
        $this->setTimezones('Australia/Adelaide', 'UTC');

        // Start day before DST starts in Adelaide (early Oct). Daily event at 09:00
        $event = $this->createRecurringEvent('2020-10-03 09:00:00', 1, 'Australia/Adelaide', '2020-10-08 00:00:00');
        $id = $this->bo->save($event);
        $this->assertGreaterThan(0, $id);
        $this->event_ids[] = $id;

        $so = new \calendar_so();
        $recs = $so->get_recurrences($id);
        unset($recs[0]);
        $this->assertNotEmpty($recs);
        foreach ($recs as $recur_start => $participant)
        {
            $this->assertEquals('09:00:00', Api\DateTime::to(Api\DateTime::server2user($recur_start), 'H:i:s'));
        }
    }

    /**
     * Verify whole-day Berlin recurrence keeps calendar date over DST start.
     *
     * Pass criteria:
     * - Recurrence rows are generated and sorted.
     * - Each recurrence date equals start date + n days (date-only invariant).
     */
    public function testBerlinWholeDayAcrossDSTStartKeepsDate()
    {
        $this->setTimezones('Europe/Berlin', 'UTC');

        $start = '2020-03-28';
        $event = $this->createRecurringEvent($start, 0, 'Europe/Berlin', '2020-04-02', true);
        $id = $this->bo->save($event);
        $this->assertGreaterThan(0, $id);
        $this->event_ids[] = $id;

        $so = new \calendar_so();
        $recs = $so->get_recurrences($id);
        unset($recs[0]);
        $this->assertNotEmpty($recs);

        // iterate recurrences in order and compare dates
        ksort($recs);
        $i = 0;
        foreach ($recs as $recur_start => $participant)
        {
            $expected = new Api\DateTime($start, Api\DateTime::$server_timezone);
            $expected->add($i.' days');
            $actual = new Api\DateTime($recur_start, Api\DateTime::$server_timezone);
            $this->assertEquals($expected->format('Ymd'), $actual->format('Ymd'));
            $i++;
        }
    }

    /**
     * Verify whole-day New York recurrence keeps calendar date over DST start.
     *
     * Pass criteria:
     * - Recurrence rows are generated and sorted.
     * - Each recurrence date equals start date + n days (date-only invariant).
     */
    public function testNYWholeDayAcrossDSTStartKeepsDate()
    {
        $this->setTimezones('America/New_York', 'UTC');

        $start = '2020-03-07';
        $event = $this->createRecurringEvent($start, 0, 'America/New_York', '2020-03-12', true);
        $id = $this->bo->save($event);
        $this->assertGreaterThan(0, $id);
        $this->event_ids[] = $id;

        $so = new \calendar_so();
        $recs = $so->get_recurrences($id);
        unset($recs[0]);
        $this->assertNotEmpty($recs);

        ksort($recs);
        $i = 0;
        foreach ($recs as $recur_start => $participant)
        {
            $expected = new Api\DateTime($start, Api\DateTime::$server_timezone);
            $expected->add($i.' days');
            $actual = new Api\DateTime($recur_start, Api\DateTime::$server_timezone);
            $this->assertEquals($expected->format('Ymd'), $actual->format('Ymd'));
            $i++;
        }
    }
}
