<?php

/**
 * Baseline test harness for searchInfolog()'s category (cat_id) and action/action_id
 * (link-based CRM view) filtering, written before phase 3 of
 * doc/ai/projects/infolog-storage-migration.md (delegating searchInfolog()'s query assembly to
 * the inherited Api\Storage::search(), overriding search() and calling parent::search() as most
 * apps do) - locks down today's behavior for these two paths, which had no dedicated coverage
 * before this file, so the rewrite can be checked against it.
 *
 * @link http://www.egroupware.org
 * @package infolog
 * @license http://opensource.org/licenses/gpl-license.php GPL - GNU General Public License
 */

namespace EGroupware\Infolog;

require_once realpath(__DIR__.'/../../api/tests/AppTest.php');	// Application test base

use EGroupware\Api\Categories;
use EGroupware\Api\Link;

class SearchCategoryAndLinkFilterTest extends \EGroupware\Api\AppTest
{
	protected $bo;

	protected $info_ids = array();

	protected $cat_ids = array();

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

		$categories = new Categories('', 'infolog');
		foreach(array_unique($this->cat_ids) as $cat_id)
		{
			$categories->delete($cat_id);
		}
		$this->cat_ids = array();

		$this->bo = null;
	}

	protected function makeInfolog(array $fields = array())
	{
		$info = array('info_type' => 'task', 'info_subject' => 'SearchCategoryAndLinkFilterTest '.$this->name());
		foreach($fields as $field => $value) { $info[$field] = $value; }
		$this->info_ids[] = $info_id = $this->bo->write($info, true, true, true, true);
		return $info_id;
	}

	/**
	 * A single category filter must match only entries in that category.
	 */
	public function testCatIdFilterMatchesExactCategory()
	{
		$categories = new Categories('', 'infolog');
		$this->cat_ids[] = $cat_id = $categories->add(array('name' => 'SearchCategoryAndLinkFilterTest '.$this->name()));

		$match_id = $this->makeInfolog(array('info_cat' => $cat_id));
		$other_id = $this->makeInfolog();

		$query = array('cat_id' => $cat_id, 'order' => 'info_datemodified', 'sort' => 'DESC');
		$ret = $this->bo->search($query);

		$this->assertArrayHasKey($match_id, $ret);
		$this->assertArrayNotHasKey($other_id, $ret);
	}

	/**
	 * A category filter must also match entries in a CHILD category (return_all_children()).
	 */
	public function testCatIdFilterMatchesChildCategory()
	{
		$categories = new Categories('', 'infolog');
		$this->cat_ids[] = $parent_id = $categories->add(array('name' => 'SearchCategoryAndLinkFilterTest parent '.$this->name()));
		$this->cat_ids[] = $child_id = $categories->add(array('name' => 'SearchCategoryAndLinkFilterTest child '.$this->name(), 'parent' => $parent_id));

		$info_id = $this->makeInfolog(array('info_cat' => $child_id));

		$query = array('cat_id' => $parent_id, 'order' => 'info_datemodified', 'sort' => 'DESC');
		$ret = $this->bo->search($query);

		$this->assertArrayHasKey($info_id, $ret);
	}

	/**
	 * The "action"/"action_id" CRM-view filter (entries linked to a specific other app's entry)
	 * must match only entries actually linked to that action_id.
	 */
	public function testActionFilterMatchesLinkedEntry()
	{
		$linked_id = $this->makeInfolog(array('info_subject' => 'linked '.$this->name()));
		$unlinked_id = $this->makeInfolog(array('info_subject' => 'unlinked '.$this->name()));

		$addr_id = 12345678;	// arbitrary id - link storage doesn't validate the target exists
		Link::link('addressbook', $addr_id, 'infolog', $linked_id);

		try
		{
			$query = array('action' => 'addr', 'action_id' => $addr_id, 'order' => 'info_datemodified', 'sort' => 'DESC');
			$ret = $this->bo->search($query);

			$this->assertArrayHasKey($linked_id, $ret);
			$this->assertArrayNotHasKey($unlinked_id, $ret);
		}
		finally
		{
			Link::unlink(0, 'addressbook', $addr_id, 'infolog', $linked_id);
		}
	}

	/**
	 * $query['total'] must be written back correctly, and $query['start'] pagination must
	 * return non-overlapping pages covering exactly the matching entries - basic pagination
	 * correctness, not specific to category/link filtering, but no dedicated coverage existed
	 * for it either before this file.
	 */
	public function testPaginationTotalAndNonOverlappingPages()
	{
		$subject = 'SearchCategoryAndLinkFilterTest pagination '.$this->name();
		$ids = array();
		for ($i = 0; $i < 5; $i++)
		{
			$ids[] = $this->makeInfolog(array('info_subject' => $subject));
		}

		$query = array(
			'col_filter' => array('info_subject' => $subject),
			'order' => 'info_datemodified', 'sort' => 'DESC',
			'start' => 0, 'num_rows' => 2,
		);
		$page1 = $this->bo->search($query);
		$this->assertEquals(5, $query['total']);
		$this->assertCount(2, $page1);

		$query['start'] = 2;
		$page2 = $this->bo->search($query);
		$this->assertEquals(5, $query['total']);
		$this->assertCount(2, $page2);
		$this->assertEmpty(array_intersect_key($page1, $page2), 'page 1 and page 2 must not overlap');

		$query['start'] = 4;
		$page3 = $this->bo->search($query);
		$this->assertCount(1, $page3);

		$this->assertEqualsCanonicalizing($ids, array_merge(array_keys($page1), array_keys($page2), array_keys($page3)),
			'the 3 pages together must cover exactly the 5 created entries, none missing/duplicated');
	}
}
