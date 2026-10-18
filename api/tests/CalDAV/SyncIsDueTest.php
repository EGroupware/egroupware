<?php
/**
 * EGroupware API: an iCal feed is synced at most once an hour (CalDAV\Sync::isDue())
 *
 * @link https://www.egroupware.org
 * @package api
 * @subpackage caldav
 * @license http://opensource.org/licenses/gpl-license.php GPL - GNU General Public License
 */

namespace EGroupware\Api\CalDAV;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * A subscription of an iCal feed without ETag (a plain ics file, eg. the "secret address in iCal format" of Google) with an
 * automatic update every 5 minutes ran all the time: the whole file is downloaded and imported again, if anything in it changed,
 * which took more than 400s for 2,000 events. The cronjob now skips such a feed, which was synced less than 55 minutes ago,
 * whatever interval the user chose. Feeds with an ETag, which a conditional request checks cheaply, and CalDAV are not limited.
 */
class SyncIsDueTest extends TestCase
{
	const NOW = 1_800_000_000;

	public static function cases() : array
	{
		$now = self::NOW;
		return [
			'iCal feed, never synced' => [['sync_type' => 'calendar-get'], true],
			'iCal feed, synced 5 minutes ago (the 5 min timer)' => [['sync_type' => 'calendar-get', 'sync_time' => $now - 300], false],
			'iCal feed, synced 54 minutes ago' => [['sync_type' => 'calendar-get', 'sync_time' => $now - 54*60], false],
			'iCal feed, synced 55 minutes ago' => [['sync_type' => 'calendar-get', 'sync_time' => $now - 55*60], true],
			'iCal feed, hourly timer which started a few seconds early' => [['sync_type' => 'calendar-get', 'sync_time' => $now - 3590], true],
			'iCal feed with ETag is not limited (conditional request is cheap)' => [['sync_type' => 'calendar-get-etag', 'sync_time' => $now - 600], true],
			'iCal feed with ETag, synced a minute ago' => [['sync_type' => 'calendar-get-etag', 'sync_time' => $now - 60], true],
			'sync-collection REPORT is not limited' => [['sync_type' => 'sync-collection-report', 'sync_time' => $now - 60], true],
			'calendar-propfind is not limited' => [['sync_type' => 'calendar-propfind', 'sync_time' => $now - 60], true],
			'calendar-propfind-ctag is not limited' => [['sync_type' => 'calendar-propfind-ctag', 'sync_time' => $now - 60], true],
			'unknown type is not limited' => [['sync_time' => $now - 60], true],
			'last sync in the future (clock change) does not block' => [['sync_type' => 'calendar-get', 'sync_time' => $now + 7200], true],
			'empty sync_time' => [['sync_type' => 'calendar-get', 'sync_time' => 0], true],
		];
	}

	#[DataProvider('cases')]
	public function testIsDue(array $data, bool $expected)
	{
		$this->assertSame($expected, Sync::isDue($data, self::NOW));
	}

	public function testIcalFeedTypes()
	{
		$this->assertTrue(Sync::isIcalFeed('calendar-get'));
		$this->assertFalse(Sync::isIcalFeed('calendar-get-etag'), 'a conditional request with the ETag is cheap');
		$this->assertFalse(Sync::isIcalFeed('sync-collection-report'));
		$this->assertFalse(Sync::isIcalFeed('calendar-propfind'));
		$this->assertFalse(Sync::isIcalFeed(null));
	}

	public function testMinimumIsLessThanTheShortestHourlyTimer()
	{
		$this->assertLessThan(Sync::MIN_ICAL_FEED_FREQUENCE * 60, Sync::MIN_ICAL_FEED_INTERVAL);
		$this->assertGreaterThan(Sync::MIN_ICAL_FEED_FREQUENCE * 60 - 600, Sync::MIN_ICAL_FEED_INTERVAL);
	}
}
