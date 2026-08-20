<?php

/**
 * Test harness for infolog_bo::search()'s custom-field col_filter handling, written before
 * delegating it to Api\Storage::cf_filter() (doc/ai/projects/infolog-storage-migration.md,
 * "search() rewrite" work) - the two CF filter code paths (a JOIN-based one, in
 * Api\Storage::cf_filter(), vs. the correlated-IN-subquery one Infolog\Storage::searchInfolog()
 * hand-rolled from infolog_so) looked like they might differ for "text"-type cfs
 * (cf_filter() uses a case-insensitive LIKE, the old code uses "="), but running this test
 * against the pre-rewrite code showed that difference doesn't actually exist in practice -
 * see testTextCfFilterIsCaseInsensitive()'s docblock. Kept as a regression net either way.
 *
 * @link http://www.egroupware.org
 * @package infolog
 * @license http://opensource.org/licenses/gpl-license.php GPL - GNU General Public License
 */

namespace EGroupware\Infolog;

require_once realpath(__DIR__.'/../../api/tests/AppTest.php');	// Application test base

use EGroupware\Api\Storage\Customfields;

class SearchCustomFieldFilterTest extends \EGroupware\Api\AppTest
{
	const SELECT_CF = 'phpunit_search_select_cf';
	const TEXT_CF = 'phpunit_search_text_cf';

	protected static $select_cf = array(
		'app'     => 'infolog',
		'name'    => self::SELECT_CF,
		'label'   => 'PHPUnit search select CF',
		'type'    => 'select',
		'type2'   => array(),
		'help'    => '',
		'values'  => array('red' => 'Red', 'green' => 'Green', 'blue' => 'Blue'),
		'len'     => null,
		'rows'    => null,
		'order'   => null,
		'needed'  => null,
		'private' => array(),
	);

	protected static $text_cf = array(
		'app'     => 'infolog',
		'name'    => self::TEXT_CF,
		'label'   => 'PHPUnit search text CF',
		'type'    => 'text',
		'type2'   => array(),
		'help'    => '',
		'values'  => null,
		'len'     => 100,
		'rows'    => null,
		'order'   => null,
		'needed'  => null,
		'private' => array(),
	);

	protected $bo;

	protected $info_ids = array();

	public static function setUpBeforeClass() : void
	{
		parent::setUpBeforeClass();

		Customfields::update(self::$select_cf);
		Customfields::update(self::$text_cf);
	}

	public static function tearDownAfterClass() : void
	{
		$fields = Customfields::get('infolog');
		unset($fields[self::SELECT_CF], $fields[self::TEXT_CF]);
		Customfields::save('infolog', $fields);

		parent::tearDownAfterClass();
	}

	protected function setUp() : void
	{
		$this->bo = new \infolog_bo();
		$this->mockTracking($this->bo, 'infolog_tracking');
	}

	protected function tearDown() : void
	{
		foreach(array_unique($this->info_ids) as $info_id)
		{
			$this->bo->delete($info_id);
			$this->bo->delete($info_id);
		}
		$this->info_ids = array();
		$this->bo = null;
	}

	protected function makeInfolog(array $fields = array())
	{
		$info = array(
			'info_type'    => 'task',
			'info_subject' => 'SearchCustomFieldFilterTest ' . $this->name(),
		);
		foreach($fields as $field => $value)
		{
			$info[$field] = $value;
		}
		$this->info_ids[] = $info_id = $this->bo->write($info, true, true, true, true);
		return $info_id;
	}

	/**
	 * A single-value match on a "select" type cf must find the entry with that value and
	 * exclude an entry with a different value.
	 */
	public function testSelectCfFilterMatchesSingleValue()
	{
		$red_id = $this->makeInfolog(array('#'.self::SELECT_CF => 'red'));
		$blue_id = $this->makeInfolog(array('#'.self::SELECT_CF => 'blue'));

		$query = array(
			'col_filter' => array('#'.self::SELECT_CF => 'red'),
			'order' => 'info_datemodified', 'sort' => 'DESC',
		);
		$ret = $this->bo->search($query);

		$this->assertArrayHasKey($red_id, $ret, 'a select-cf filter must match the entry with that value');
		$this->assertArrayNotHasKey($blue_id, $ret, 'a select-cf filter must NOT match an entry with a different value');
	}

	/**
	 * Filtering by multiple select-cf values (array) must match ANY entry whose value is one
	 * of the given values ("any entry with the filter value selected matches").
	 */
	public function testSelectCfFilterMatchesAnyOfMultipleValues()
	{
		$red_id = $this->makeInfolog(array('#'.self::SELECT_CF => 'red'));
		$green_id = $this->makeInfolog(array('#'.self::SELECT_CF => 'green'));
		$blue_id = $this->makeInfolog(array('#'.self::SELECT_CF => 'blue'));

		$query = array(
			'col_filter' => array('#'.self::SELECT_CF => array('red', 'green')),
			'order' => 'info_datemodified', 'sort' => 'DESC',
		);
		$ret = $this->bo->search($query);

		$this->assertArrayHasKey($red_id, $ret);
		$this->assertArrayHasKey($green_id, $ret);
		$this->assertArrayNotHasKey($blue_id, $ret);
	}

	/**
	 * A "text" type cf filter must match an entry with the given value.
	 */
	public function testTextCfFilterMatchesExactValue()
	{
		$match_id = $this->makeInfolog(array('#'.self::TEXT_CF => 'ExactValue'));
		$other_id = $this->makeInfolog(array('#'.self::TEXT_CF => 'OtherValue'));

		$query = array(
			'col_filter' => array('#'.self::TEXT_CF => 'ExactValue'),
			'order' => 'info_datemodified', 'sort' => 'DESC',
		);
		$ret = $this->bo->search($query);

		$this->assertArrayHasKey($match_id, $ret);
		$this->assertArrayNotHasKey($other_id, $ret);
	}

	/**
	 * CORRECTED assumption (found by running this test against the pre-rewrite code): a "text"
	 * cf filter is ALREADY case-insensitive today, even though the old code's SQL uses "="
	 * rather than a "LIKE" - MySQL's default collation for the extra-table's value column is
	 * case-insensitive ("_ci"), so a plain "=" comparison is case-insensitive regardless of
	 * operator. This means Api\Storage::cf_filter()'s case-insensitive LIKE for "text"-type
	 * cfs is NOT actually a behavior change from delegating to it - both paths produce the
	 * same case-insensitive result under this schema's default collation. Locked down here so
	 * a future change to the extra table's collation would be caught as a real behavior change
	 * either way.
	 */
	public function testTextCfFilterIsCaseInsensitive()
	{
		$info_id = $this->makeInfolog(array('#'.self::TEXT_CF => 'MixedCase'));

		$query = array(
			'col_filter' => array('#'.self::TEXT_CF => 'mixedcase'),
			'order' => 'info_datemodified', 'sort' => 'DESC',
		);
		$ret = $this->bo->search($query);

		$this->assertArrayHasKey($info_id, $ret,
			'a "text" cf filter is case-insensitive under this schema\'s default collation, both before and after delegating to cf_filter()');
	}
}
