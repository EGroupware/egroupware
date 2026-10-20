<?php
/**
 * EGroupware InfoLog: regression test for infolog_bo/Infolog\Storage under Db::requireSqlFragmentObjects()
 *
 * @link https://www.egroupware.org
 * @package infolog
 * @license http://opensource.org/licenses/gpl-license.php GPL - GNU General Public License
 */

namespace EGroupware\Infolog;

require_once realpath(__DIR__.'/../../api/tests/AppTest.php');

/**
 * Infolog\Storage opted into Api\Db::requireSqlFragmentObjects(true) on its own Db clone
 * (doc/ai/projects/sql-fragment-type-safety.md). Several raw SQL fragments infolog builds
 * itself - infolog_bo::searchInfolog()'s $filtermethod (the ACL/status/date/responsible/cat_id
 * filter, wrapped as one fragment at $filter[0]), Infolog\Storage::write()'s etag increment,
 * and the "#"-prefixed unregistered-customfield NOT IN filters in read()/readRows() - are
 * exercised here to prove none of them throw once opted in.
 */
class InfologSqlFragmentTest extends \EGroupware\Api\AppTest
{
	/** @var \infolog_bo */
	private $bo;
	private array $info_ids = [];

	protected function setUp() : void
	{
		parent::setUp();
		$this->bo = new \infolog_bo();
		$this->mockTracking($this->bo, 'infolog_tracking');
	}

	protected function tearDown() : void
	{
		foreach (array_unique($this->info_ids) as $info_id)
		{
			$this->bo->delete($info_id);
		}
		$this->info_ids = [];
		parent::tearDown();
	}

	private function makeInfolog(array $fields = []) : int
	{
		$info = array_merge([
			'info_type' => 'task',
			'info_subject' => 'InfologSqlFragmentTest-'.uniqid(),
		], $fields);
		$this->info_ids[] = $info_id = $this->bo->write($info, true, true, true, true);
		$this->assertNotFalse($info_id, 'writing the fixture infolog entry failed');
		return $info_id;
	}

	public function testDefaultSearchDoesNotThrow()
	{
		$id = $this->makeInfolog();

		$query = ['order' => 'info_datemodified', 'sort' => 'DESC'];
		$ret = $this->bo->search($query);

		$this->assertIsArray($ret);
		$this->assertArrayHasKey($id, $ret);
	}

	public function testSearchWithResponsibleFilterDoesNotThrow()
	{
		$id = $this->makeInfolog(['info_responsible' => [$GLOBALS['egw_info']['user']['account_id']]]);

		$query = [
			'col_filter' => ['info_responsible' => $GLOBALS['egw_info']['user']['account_id']],
			'order' => 'info_datemodified', 'sort' => 'DESC',
		];
		$ret = $this->bo->search($query);

		$this->assertIsArray($ret);
		$this->assertArrayHasKey($id, $ret);
	}

	public function testSearchWithCatIdFilterDoesNotThrow()
	{
		$cat_id = $GLOBALS['egw']->categories->add(['name' => 'InfologSqlFragmentTest-'.uniqid(), 'appname' => 'infolog']);
		$id = $this->makeInfolog(['info_cat' => $cat_id]);

		$query = ['cat_id' => $cat_id, 'order' => 'info_datemodified', 'sort' => 'DESC'];
		$ret = $this->bo->search($query);

		$this->assertIsArray($ret);
		$this->assertArrayHasKey($id, $ret);

		$GLOBALS['egw']->categories->delete($cat_id);
	}

	public function testUpdateWithModifiedCheckIncrementsEtagWithoutThrowing()
	{
		$id = $this->makeInfolog();
		$entry = $this->bo->read($id);
		$this->assertIsArray($entry);

		// pass the unchanged info_datemodified back - triggers write()'s optimistic-lock
		// $check_modified path, which hits the 'info_etag=info_etag+1' SqlFragment-wrapped update
		$entry['info_subject'] = 'InfologSqlFragmentTest-updated-'.uniqid();
		$updated_id = $this->bo->write($entry, false, true, true, true);

		$this->assertSame($id, $updated_id);
	}

	public function testAnzSubsDoesNotThrow()
	{
		$parent_id = $this->makeInfolog();
		$child_id = $this->makeInfolog(['info_id_parent' => $parent_id]);

		$count = $this->bo->so->anzSubs($parent_id);

		$this->assertSame(1, $count);
		unset($child_id);
	}
}
