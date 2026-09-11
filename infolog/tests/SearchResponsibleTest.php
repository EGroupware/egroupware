<?php

/**
 * Test harness for searchInfolog()'s col_filter['info_responsible'] handling, written before
 * removing the users_table JOIN it currently relies on
 * (doc/ai/projects/infolog-storage-migration.md, "eliminating searchInfolog()'s row-duplicating
 * JOINs" research, phase 2) - locks down today's behavior (direct delegation match, the
 * owner-fallback when an entry has no active delegation at all, and that an existing-but-
 * different delegation does NOT fall back to the owner) before replacing the JOIN with an
 * EXISTS-subquery. No coverage existed for this filter anywhere before this file.
 *
 * @link http://www.egroupware.org
 * @package infolog
 * @license http://opensource.org/licenses/gpl-license.php GPL - GNU General Public License
 */

namespace EGroupware\Infolog;

require_once realpath(__DIR__.'/../../api/tests/AppTest.php');	// Application test base

class SearchResponsibleTest extends \EGroupware\Api\AppTest
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
		$info = array('info_type' => 'task', 'info_subject' => 'SearchResponsibleTest '.$this->name());
		foreach($fields as $field => $value) { $info[$field] = $value; }
		$this->info_ids[] = $info_id = $this->bo->write($info, true, true, true, true);
		return $info_id;
	}

	protected function searchByResponsible($user)
	{
		$query = array(
			'col_filter' => array('info_responsible' => $user),
			'order' => 'info_datemodified', 'sort' => 'DESC', 'filter' => '',
		);
		return $this->bo->search($query, true);	// $no_acl=true: only the filter itself is under test
	}

	/**
	 * An entry delegated directly to a user must match a filter for that user.
	 */
	public function testMatchesDirectDelegation()
	{
		$info_id = $this->makeInfolog(array('info_responsible' => array($this->other_user)));

		$ret = $this->searchByResponsible($this->other_user);

		$this->assertArrayHasKey($info_id, $ret);
	}

	/**
	 * An entry delegated to someone else must NOT match a filter for a different user, even
	 * though an active delegation row exists on the entry (just not to the filtered-for user) -
	 * this is the case the owner-fallback branch must NOT trigger for.
	 */
	public function testDelegationToSomeoneElseDoesNotFallBackToOwner()
	{
		$info_id = $this->makeInfolog(array(
			'info_owner' => $this->bo->user,
			'info_responsible' => array($this->other_user),
		));

		$ret = $this->searchByResponsible($this->bo->user);

		$this->assertArrayNotHasKey($info_id, $ret);
	}

	/**
	 * An entry with NO delegation at all falls back to matching its owner.
	 */
	public function testNoDelegationFallsBackToOwner()
	{
		$info_id = $this->makeInfolog(array('info_owner' => $this->bo->user));

		$ret = $this->searchByResponsible($this->bo->user);

		$this->assertArrayHasKey($info_id, $ret);
	}

	/**
	 * The owner-fallback must not match a DIFFERENT user than the actual owner.
	 */
	public function testNoDelegationOwnerFallbackDoesNotMatchOtherUser()
	{
		$info_id = $this->makeInfolog(array('info_owner' => $this->bo->user));

		$ret = $this->searchByResponsible($this->other_user);

		$this->assertArrayNotHasKey($info_id, $ret);
	}

	/**
	 * A delegation that was removed again (soft-deleted, info_res_deleted=true - the row still
	 * exists in egw_infolog_users, write()'s way of retracting a delegation) must NOT count as
	 * an active delegation - the entry should behave as if it had none, i.e. fall back to the
	 * owner match.
	 */
	public function testRemovedDelegationFallsBackToOwner()
	{
		$info_id = $this->makeInfolog(array(
			'info_owner' => $this->bo->user,
			'info_responsible' => array($this->other_user),
		));
		// retract the delegation again - write() marks the egw_infolog_users row info_res_deleted=true
		$entry = $this->bo->read($info_id);
		$entry['info_responsible'] = array();
		$this->bo->write($entry, true, true, true, true);

		$ret = $this->searchByResponsible($this->bo->user);

		$this->assertArrayHasKey($info_id, $ret);
	}
}
