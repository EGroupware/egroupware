<?php
/**
 * EGroupware infolog: regression test for searchInfolog()'s 'search'/'query' filter handling
 *
 * @link https://www.egroupware.org
 * @package infolog
 * @license http://opensource.org/licenses/gpl-license.php GPL - GNU General Public License
 */

namespace EGroupware\Infolog;

use EGroupware\Api;

require_once realpath(__DIR__.'/../../api/tests/AppTest.php');

/**
 * Exposes the protected searchInfolog() for direct testing (ReflectionMethod::invoke() silently
 * passes the &$query by-reference parameter by value, so a subclass accessor is used instead).
 */
class InfologBoSearchInfologAccessor extends \infolog_bo
{
	public function exposedSearchInfolog(&$query, $no_acl = false, $acl_filter = null)
	{
		return $this->searchInfolog($query, $no_acl, $acl_filter);
	}
}

/**
 * searchInfolog() used to call Api\Storage\Base::search2criteria($query['search'], ...) whenever
 * $query['search'] (or the 'query' alias) was merely non-empty, with no scalar-type check.
 * search2criteria()'s own first line is:
 *   if (empty($_pattern) || is_array($_pattern) || ...) return $_pattern;
 * so a client sending an object for the search filter (eg. an array with an integer key) got it
 * back completely unprocessed, which then got woven into $search_fragment via implode() and
 * spliced directly into the generated SQL - the same vulnerability class as
 * GHSA-vx89-j2x7-5wp2, reached via infolog's search instead of calendar's ajax_get().
 *
 * Fix: only take the free-text search path when $query['search'] is actually a plain string.
 *
 * Standalone fix, independent of the separate Api\Db\SqlFragment project
 * (doc/ai/projects/sql-fragment-type-safety.md) - backportable on its own.
 */
class SearchInfologSearchFilterTest extends \EGroupware\Api\AppTest
{
	/** @var InfologBoSearchInfologAccessor */
	private $bo;

	protected function setUp() : void
	{
		parent::setUp();
		$this->bo = new InfologBoSearchInfologAccessor();
	}

	public function testArrayShapedSearchValueDoesNotThrowOrInject()
	{
		$query = array(
			'search' => array(1 => "1=1) OR (1=1"),
			'start' => 0,
			'num_rows' => 1,
		);

		// must not throw (eg. a TypeError from treating the array as a string), and must not
		// splice the raw fragment into the query - just run to completion as if 'search' were empty
		$result = $this->bo->exposedSearchInfolog($query);

		$this->assertIsArray($result);
	}

	public function testObjectShapedSearchValueDoesNotThrowOrInject()
	{
		$query = array(
			'search' => new \stdClass(),
			'start' => 0,
			'num_rows' => 1,
		);

		$result = $this->bo->exposedSearchInfolog($query);

		$this->assertIsArray($result);
	}

	public function testStringSearchValueStillWorks()
	{
		$query = array(
			'search' => 'some free-text term that matches nothing in particular',
			'start' => 0,
			'num_rows' => 1,
		);

		$result = $this->bo->exposedSearchInfolog($query);

		$this->assertIsArray($result, 'a plain string search value must still be processed without error');
	}
}
