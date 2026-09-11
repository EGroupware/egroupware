<?php
/**
 * EGroupware Calendar: Test calendar_timezones' session-cache persistence
 *
 * @link https://www.egroupware.org
 * @package calendar
 * @subpackage test
 * @license http://opensource.org/licenses/gpl-license.php GPL - GNU General Public License
 */

namespace EGroupware\calendar;

require_once realpath(__DIR__.'/../../api/tests/AppTest.php');	// Application test base

use EGroupware\Api;
use calendar_timezones;

/**
 * calendar_timezones::$tz_cache/$tz2id were previously bound BY REFERENCE to
 * Api\Cache::getSession(), so tz2id()/id2tz() mutating them auto-persisted to the
 * session. Migrated to plain reads + explicit Api\Cache::setSession() calls (dropping
 * the deprecated =& pattern) - this proves a freshly-resolved tzid actually lands in
 * the session, not just in the in-process static property.
 */
class TimezonesSessionCacheTest extends \EGroupware\Api\AppTest
{
	public function testTz2idPersistsToSession()
	{
		// self::$tz_cache/$tz2id are plain (not live-reference) process-wide statics now, so a
		// prior test class's login/session doesn't automatically get replaced when this test's
		// own session becomes current - re-bind to it explicitly, exactly as real app bootstrap
		// code does on every request, before asserting anything about THIS session's content.
		calendar_timezones::init_static();

		$id = calendar_timezones::tz2id('Europe/Berlin');
		$this->assertNotEmpty($id, "calendar_timezones::tz2id('Europe/Berlin') did not resolve - is the timezone DB populated?");

		$session_tz2id = Api\Cache::getSession(calendar_timezones::class, 'tz2id');
		$this->assertSame($id, $session_tz2id['Europe/Berlin'] ?? null,
			'tz2id for Europe/Berlin was not persisted to the session');

		$session_tz_cache = Api\Cache::getSession(calendar_timezones::class, 'tz_cache');
		$this->assertSame('Europe/Berlin', $session_tz_cache[$id]['tzid'] ?? null,
			'tz_cache entry for the resolved id was not persisted to the session');
	}

	public function testId2tzPersistsToSession()
	{
		$id = calendar_timezones::tz2id('Europe/Berlin');
		// clear tz_cache's copy of this id (but keep tz2id) to force id2tz() to re-populate it from the DB
		$tz_cache = Api\Cache::getSession(calendar_timezones::class, 'tz_cache');
		unset($tz_cache[$id]);
		Api\Cache::setSession(calendar_timezones::class, 'tz_cache', $tz_cache);
		calendar_timezones::init_static();

		$tzid = calendar_timezones::id2tz($id);
		$this->assertSame('Europe/Berlin', $tzid);

		$session_tz_cache = Api\Cache::getSession(calendar_timezones::class, 'tz_cache');
		$this->assertSame('Europe/Berlin', $session_tz_cache[$id]['tzid'] ?? null,
			'id2tz() re-populating tz_cache from the DB did not persist it to the session');
	}
}
