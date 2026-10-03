<?php
/**
 * calendar_groupdav::get() with nothing to export
 *
 * exportVCal() returns false if no event was exported. That used to be sent as status 200 with an empty body, which
 * makes clients like DAVx5 abort the sync of the whole calendar. A GET not matching anything is a 404.
 *
 * @link http://www.egroupware.org
 * @package calendar
 * @subpackage tests
 * @license http://opensource.org/licenses/gpl-license.php GPL - GNU General Public License
 */

namespace EGroupware\calendar;

require_once realpath(__DIR__.'/../../../api/tests/AppTest.php');	// Application test base

use EGroupware\Api;

/**
 * calendar_groupdav with a fixed event and export result
 */
class GroupdavWithFixedExport extends \calendar_groupdav
{
	/** @var string|false|null what the export returns */
	public $export_result;

	public function _common_get_put_delete($method, &$options, &$id, &$return_no_access=false, $ignore_if_match=false, $check_return_representation=true)
	{
		return ['id' => 4711, 'uid' => 'empty-export-test', 'caldav_name' => 'empty-export-test.ics', 'title' => 'Test',
			'modified' => time(), 'etag' => 1, 'owner' => 1, 'participants' => []];
	}

	protected function iCal(array $event, $user=null, $method=null, $expand=false, ?array $events=null, $json=null)
	{
		return $this->export_result;
	}

	public function get_etag($event, &$schedule_tag=null)
	{
		return '4711:1:'.time();
	}
}

class EmptyExportIsNotFoundTest extends \EGroupware\Api\AppTest
{
	/** @var int number of groupdav objects created, each constructing Api\CalDAV, which sets an exception handler */
	protected $handlers = 0;

	protected function tearDown() : void
	{
		while($this->handlers-- > 0)
		{
			restore_exception_handler();
		}
		parent::tearDown();
	}

	/**
	 * @param string|false|null $export_result
	 * @return GroupdavWithFixedExport
	 */
	protected function groupdav($export_result) : GroupdavWithFixedExport
	{
		$groupdav = new GroupdavWithFixedExport('calendar', new Api\CalDAV());
		++$this->handlers;
		$groupdav->export_result = $export_result;
		return $groupdav;
	}

	public static function emptyExportProvider() : array
	{
		return ['false (nothing exported)' => [false], 'empty string' => [''], 'null' => [null]];
	}

	/**
	 * Pass criteria: nothing exported gives "404 Not Found" and no data, instead of success with an empty body
	 */
	#[\PHPUnit\Framework\Attributes\DataProvider('emptyExportProvider')]
	public function testGetWithoutExportIsNotFound($export_result)
	{
		$options = ['path' => '/demo/calendar/empty-export-test.ics'];
		$id = 'empty-export-test.ics';

		$status = $this->groupdav($export_result)->get($options, $id);

		$this->assertSame('404 Not Found', $status);
		$this->assertArrayNotHasKey('data', $options, 'no data may be sent');
	}

	/**
	 * Pass criteria: a real export is still returned (control)
	 */
	public function testGetWithExportStillWorks()
	{
		$options = ['path' => '/demo/calendar/empty-export-test.ics'];
		$id = 'empty-export-test.ics';
		$ics = "BEGIN:VCALENDAR\r\nBEGIN:VEVENT\r\nEND:VEVENT\r\nEND:VCALENDAR\r\n";

		$status = $this->groupdav($ics)->get($options, $id);

		$this->assertTrue($status);
		$this->assertSame($ics, $options['data']);
		$this->assertStringStartsWith('text/calendar', $options['mimetype']);
	}
}
