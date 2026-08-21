<?php

/**
 * Test harness for infolog_bo::aclFilter()'s "user<id>" filter (view a specific other user's
 * tasks - used by infolog_groupdav.inc.php/infolog_zpush.inc.php and the
 * calendar_include_todos hook), written while fixing a pre-existing bug found during the
 * "eliminating searchInfolog()'s row-duplicating JOINs" work
 * (doc/ai/projects/infolog-storage-migration.md): one of aclFilter()'s Db::expression() calls
 * concatenated an array literal directly with a string via ".", which PHP silently stringifies
 * to the literal "Array" (E_WARNING) instead of passing real column data to expression() -
 * producing invalid SQL ("... AND (Array AND ...") every time this branch was reached. No
 * coverage existed for the "user" filter anywhere before this file.
 *
 * @link http://www.egroupware.org
 * @package infolog
 * @license http://opensource.org/licenses/gpl-license.php GPL - GNU General Public License
 */

namespace EGroupware\Infolog;

require_once realpath(__DIR__.'/../../api/tests/AppTest.php');	// Application test base

class AclUserFilterTest extends \EGroupware\Api\AppTest
{
	protected $bo;

	protected $info_ids = array();

	protected $other_user;

	protected function setUp() : void
	{
		$this->bo = new \infolog_bo();
		$this->mockTracking($this->bo, 'infolog_tracking');
		$this->other_user = $GLOBALS['egw']->accounts->name2id($GLOBALS['EGW_ADMIN_USER']);
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
		$info = array('info_type' => 'task', 'info_subject' => 'AclUserFilterTest '.$this->name());
		foreach($fields as $field => $value) { $info[$field] = $value; }
		$this->info_ids[] = $info_id = $this->bo->write($info, true, true, true, true);
		return $info_id;
	}

	protected function searchByUserFilter($f_user)
	{
		$query = array('filter' => 'user'.$f_user, 'order' => 'info_datemodified', 'sort' => 'DESC');
		return $this->bo->search($query);
	}

	/**
	 * The "user<id>" filter must not throw/produce a SQL error at all - the bug this test was
	 * written for made every use of this branch fail with an SQL error ("Array" as a bare SQL
	 * token), regardless of what matched.
	 */
	public function testUserFilterDoesNotErrorOut()
	{
		$this->makeInfolog(array('info_owner' => $this->bo->user));

		$ret = $this->searchByUserFilter($this->bo->user);

		$this->assertIsArray($ret);
	}

	/**
	 * An entry owned by the filtered-for user, with no delegation at all, must match (the
	 * owner-fallback branch of the "user" filter's expression()).
	 */
	public function testUserFilterMatchesOwnerWithNoDelegation()
	{
		$info_id = $this->makeInfolog(array('info_owner' => $this->bo->user));

		$ret = $this->searchByUserFilter($this->bo->user);

		$this->assertArrayHasKey($info_id, $ret);
	}

	/**
	 * An entry delegated to the filtered-for user (regardless of owner) must match (the
	 * responsible_exists() branch of the "user" filter's expression()) - owner stays the
	 * logged-in test user here (not other_user) so write() doesn't need any extra ACL grant to
	 * create the fixture; only the delegation, not the ownership, is what's under test.
	 */
	public function testUserFilterMatchesDelegation()
	{
		$info_id = $this->makeInfolog(array(
			'info_owner' => $this->bo->user,
			'info_responsible' => array($this->other_user),
		));

		$ret = $this->searchByUserFilter($this->other_user);

		$this->assertArrayHasKey($info_id, $ret);
	}
}
