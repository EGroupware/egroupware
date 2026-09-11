<?php

/**
 * Regression test for the ACL relocation described in
 * doc/ai/projects/infolog-storage-migration.md decision #4: check_access()'s
 * grant/ownership/responsible decision logic and aclFilter() were moved from
 * infolog_so to infolog_bo (2026-08-19) - ACL belongs in the BO layer, like
 * every other app, not in the storage class.
 *
 * These tests target infolog_bo::check_access() directly, using the
 * pre-configured admin test account (EGW_ADMIN_USER, "sysop") as a stand-in
 * "other user" with no ACL grant over the logged-in demo user, WITHOUT
 * switching sessions (check_access() takes an explicit $user parameter, so
 * "would sysop have access to demo's entry" can be checked while still
 * logged in as demo).
 *
 * @link http://www.egroupware.org
 * @package infolog
 * @license http://opensource.org/licenses/gpl-license.php GPL - GNU General Public License
 */

namespace EGroupware\Infolog;

require_once realpath(__DIR__.'/../../api/tests/AppTest.php');	// Application test base

use EGroupware\Api\Acl;

class AclCheckAccessTest extends \EGroupware\Api\AppTest
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
		$info = array(
			'info_type'    => 'task',
			'info_subject' => 'AclCheckAccessTest ' . $this->name(),
		);
		foreach($fields as $field => $value)
		{
			$info[$field] = $value;
		}
		$this->info_ids[] = $info_id = $this->bo->write($info, true, true, true, true);
		return $info_id;
	}

	/**
	 * The owner always has full rights, regardless of ACL grants -
	 * checkAccessGrants()'s `$owner == $user` short-circuit.
	 */
	public function testOwnerAlwaysGranted()
	{
		$info_id = $this->makeInfolog();
		$info = $this->bo->read($info_id);

		$this->assertTrue((bool)$this->bo->check_access($info, Acl::READ));
		$this->assertTrue((bool)$this->bo->check_access($info, Acl::EDIT));
		$this->assertTrue((bool)$this->bo->check_access($info, Acl::DELETE));
	}

	/**
	 * Invoke the protected checkAccessGrants() decision function directly,
	 * with an explicit $grants array, bypassing the live
	 * $GLOBALS['egw']->acl->get_grants() lookup entirely.
	 *
	 * This dev database has real, pre-existing ACL grants (confirmed:
	 * demo has granted the "sysop"/Default-group role READ+ADD+EDIT=7 over
	 * demo's own entries, entirely unrelated to this change) - going through
	 * the live check_access() wrapper for a "no grant" negative case would
	 * be at the mercy of whatever this particular dev box's ACL config
	 * happens to be, exactly what doc/ai/testing.md warns against ("be
	 * careful with tests that depend on ... user permissions"). Reflection
	 * on the decision function itself, with a $grants array this test fully
	 * controls, is the deterministic way to test it.
	 */
	protected function checkAccessGrants(array $info, $required_rights, $implicit_edit, array $grants, $user)
	{
		$method = new \ReflectionMethod($this->bo, 'checkAccessGrants');
		$method->setAccessible(true);
		return $method->invoke($this->bo, $info, $required_rights, $implicit_edit, $grants, $user);
	}

	/**
	 * A user who is neither the owner, granted access, nor responsible must
	 * be denied - the negative case for the moved ACL decision logic.
	 */
	public function testNonResponsibleStrangerDenied()
	{
		$info_id = $this->makeInfolog();
		$info = $this->bo->read($info_id);

		$this->assertFalse((bool)$this->checkAccessGrants($info, Acl::READ, false, array(), $this->other_user),
			'a user with no grant, not the owner, and not responsible must not get READ access');
	}

	/**
	 * A responsible (but non-owner) user gets implicit READ access even
	 * without an explicit ACL grant - but NOT implicit EDIT unless
	 * $implicit_edit is set. Directly targets the
	 * `is_responsible($info,$user) && $required_rights == Acl::READ` branch
	 * moved into infolog_bo::checkAccessGrants().
	 */
	public function testResponsibleGetsImplicitReadNotEdit()
	{
		$info_id = $this->makeInfolog(array('info_responsible' => array($this->other_user)));
		$info = $this->bo->read($info_id);

		$this->assertTrue((bool)$this->checkAccessGrants($info, Acl::READ, false, array(), $this->other_user),
			'a responsible user must get implicit READ access even without an explicit ACL grant');
		$this->assertFalse((bool)$this->checkAccessGrants($info, Acl::EDIT, false, array(), $this->other_user),
			'a responsible user must NOT get implicit EDIT access when $implicit_edit is false');
		$this->assertTrue((bool)$this->checkAccessGrants($info, Acl::EDIT, true, array(), $this->other_user),
			'a responsible user MUST get implicit EDIT access when $implicit_edit is true');
	}

	/**
	 * aclFilter('own') must still scope a search to the current user's own
	 * entries (incl. those they're responsible for) - the SQL-fragment half
	 * of the same relocation, now built by infolog_bo instead of
	 * infolog_so, and passed into infolog_so::search()'s new $acl_filter
	 * parameter.
	 */
	public function testAclFilterOwnIncludesOwnEntry()
	{
		$info_id = $this->makeInfolog();

		$query = array('filter' => 'own', 'order' => 'info_datemodified', 'sort' => 'DESC');
		$ret = $this->bo->search($query);

		$this->assertArrayHasKey($info_id, $ret,
			'aclFilter("own")-scoped search must include an entry owned by the current user');
	}
}
