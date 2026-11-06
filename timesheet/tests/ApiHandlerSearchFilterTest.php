<?php
/**
 * EGroupware timesheet: regression test for ApiHandler::filter2col_filter()'s 'search' handling
 *
 * @link https://www.egroupware.org
 * @package timesheet
 * @license http://opensource.org/licenses/gpl-license.php GPL - GNU General Public License
 */

use EGroupware\Api;
use EGroupware\Timesheet\ApiHandler;

require_once realpath(__DIR__.'/../../api/tests/AppTest.php');

/**
 * filter2col_filter()'s 'search' case used to hand $value (client-supplied, from the REST/tool-call
 * filters[search] parameter) straight to Api\Storage\Base::search2criteria() and merge the result
 * into $cols. search2criteria()'s own first line is:
 *   if (empty($_pattern) || is_array($_pattern) || ...) return $_pattern;
 * so a client sending an object instead of a string for filters[search] (eg.
 * "filters[search][1]=...", which parses to a PHP array with an INTEGER key) got it back
 * completely unprocessed - and array_merge() preserves that shape (renumbering, not removing, the
 * integer key). That reaches Storage\Base::search()'s $criteria/$filter handling, which treats an
 * integer-keyed value as a pre-built, trusted raw SQL fragment and splices it into the WHERE
 * clause unquoted - the same vulnerability class as GHSA-vx89-j2x7-5wp2, reached via a different
 * app's REST API instead of calendar's ajax_get().
 *
 * Fix: only call search2criteria() when $value is actually a plain string.
 *
 * This is a standalone fix, independent of the separate Api\Db\SqlFragment project
 * (doc/ai/projects/sql-fragment-type-safety.md) - backportable on its own.
 */
class ApiHandlerSearchFilterTest extends \EGroupware\Api\AppTest
{
	/** @var ApiHandler */
	private $handler;

	protected function setUp() : void
	{
		parent::setUp();
		$this->handler = new ApiHandler('timesheet', new Api\CalDAV());
	}

	/**
	 * Api\CalDAV::__construct() calls set_exception_handler() for every instance - restore it so
	 * this doesn't leak into later tests in the same PHPUnit run.
	 */
	protected function tearDown() : void
	{
		restore_exception_handler();
		parent::tearDown();
	}

	/**
	 * @return array the $cols result of filter2col_filter($filter)
	 */
	private function filter2colFilter(array $filter) : array
	{
		$method = new \ReflectionMethod($this->handler, 'filter2col_filter');
		$method->setAccessible(true);
		return $method->invoke($this->handler, $filter);
	}

	public function testStringSearchValueStillWorks()
	{
		// a legitimate string search LEGITIMATELY produces an integer-keyed entry (the combined
		// OR-condition fragment search2criteria() builds) - that's the normal, safe shape; only
		// its *presence for a non-string $value* is the bug
		$cols = $this->filter2colFilter(['search' => 'some free-text term']);

		$this->assertNotEmpty($cols, 'a plain string search value must still produce search criteria');
	}

	public function testArrayShapedSearchValueIsIgnoredNotPassedThrough()
	{
		$cols = $this->filter2colFilter(['search' => [1 => "1=1) OR (1=1"]]);

		$this->assertSame([], $cols,
			'an array-shaped (eg. client-supplied object) search value must never reach search2criteria() - '.
			'it must be dropped entirely, not merged into $cols - got: '.print_r($cols, true));
	}

	public function testObjectShapedSearchValueIsIgnoredNotPassedThrough()
	{
		// an object (not array/string) is an even more clearly malformed client value
		$cols = $this->filter2colFilter(['search' => new \stdClass()]);

		$this->assertSame([], $cols, 'an object-shaped search value must never be merged into $cols');
	}
}
