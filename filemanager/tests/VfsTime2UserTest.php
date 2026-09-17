<?php
/**
 * EGroupware filemanager: regression test for filemanager_ui::vfs_time2user() double-conversion
 *
 * @link http://www.egroupware.org
 * @package filemanager
 * @license http://opensource.org/licenses/gpl-license.php GPL - GNU General Public License
 */

require_once realpath(__DIR__.'/../../api/tests/AppTest.php');

use EGroupware\Api;

/**
 * filemanager_ui::file() runs vfs_time2user() on $content['mtime']/['ctime'] unconditionally on
 * every (re-)render - including a postback that does NOT close the dialog, eg. the "Apply" button,
 * or the Stylite versioning "Revert to" action (ticket #124741). On such a postback,
 * $content['mtime'] is no longer the raw Unix timestamp Vfs stat() returns, but the value from the
 * PREVIOUS conversion, round-tripped through the date widget as a formatted string like
 * "2023-05-09 16:21:24". vfs_time2user() blindly prefixed '@' onto that, producing
 * "@2023-05-09 16:21:24" - not a valid PHP "@timestamp" string, which Api\DateTime's underlying
 * native \DateTime parser rejected with "Double timezone specification".
 */
class VfsTime2UserTest extends \EGroupware\Api\AppTest
{
	private function call($vfs_time)
	{
		$method = new \ReflectionMethod('filemanager_ui', 'vfs_time2user');
		$method->setAccessible(true);
		return $method->invoke(null, $vfs_time);
	}

	/**
	 * First (fresh) call: a real Unix timestamp, as Vfs::lstat()/url_stat() return it.
	 */
	public function testRealTimestampConverts()
	{
		$ts = strtotime('2023-05-09 16:21:24 UTC');
		$result = $this->call($ts);

		$this->assertInstanceOf(Api\DateTime::class, $result);
		$this->assertSame($ts, $result->getTimestamp());
	}

	/**
	 * Second call on the SAME field, with the value already converted by the first call - this
	 * is exactly what happens on an "Apply"/"Revert to" postback, since $content round-trips
	 * through the date widget as a formatted string, not a raw timestamp. Must not throw, and
	 * must represent the same point in time as the original.
	 */
	public function testAlreadyConvertedValueDoesNotThrow()
	{
		$ts = strtotime('2023-05-09 16:21:24 UTC');
		$first = $this->call($ts);

		$second = $this->call((string)$first);

		$this->assertInstanceOf(Api\DateTime::class, $second);
		$this->assertSame($first->getTimestamp(), $second->getTimestamp(),
			'Re-converting the already-converted value must yield the same point in time');
	}

	/**
	 * An Api\DateTime object itself (not just its string form) must also be accepted, since
	 * Api\DateTime's constructor natively handles DateTimeInterface instances.
	 */
	public function testAlreadyConvertedObjectDoesNotThrow()
	{
		$ts = strtotime('2023-05-09 16:21:24 UTC');
		$first = $this->call($ts);

		$second = $this->call($first);

		$this->assertInstanceOf(Api\DateTime::class, $second);
		$this->assertSame($first->getTimestamp(), $second->getTimestamp());
	}
}
