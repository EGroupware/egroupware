<?php

/**
 * Does a group being responsible for an entry grant its members access, with no ACL grant
 * from the owner at all?
 *
 * infolog_bo::is_responsible_user() matches info_responsible against the user *and* his
 * memberships, so a group in info_responsible is supposed to give every member implicit
 * access. AclCheckAccessTest covers responsible naming a user directly; this covers it
 * naming a group, which is a different code path (the array_intersect() against
 * accounts::memberships(), rather than the plain "is it me" hit).
 *
 * Written because the clientside push ACL pre-check (EgwApp._push_grant_check(), which drops
 * push notifications about entries the user cannot see so the browser skips a pointless
 * refresh round-trip) only ever sees egw.grants(), and grants never contain the user's own
 * memberships - only accounts that actually granted something. If a group being responsible
 * really does grant access, that pre-check has to consult memberships too or it discards
 * refreshes for entries the user can see. These tests pin down the server-side rule the
 * clientside check has to match.
 *
 * The grants array is passed in explicitly (via the same reflection helper and for the same
 * reason as AclCheckAccessTest: this dev database has real pre-existing grants, so a "no
 * grant" case routed through the live get_grants() lookup would be at the mercy of the box's
 * ACL config), which also mirrors exactly what the clientside check faces - a grants array
 * with no entry for the owner.
 *
 * @link http://www.egroupware.org
 * @package infolog
 * @license http://opensource.org/licenses/gpl-license.php GPL - GNU General Public License
 */

namespace EGroupware\Infolog;

require_once realpath(__DIR__.'/../../api/tests/AppTest.php');	// Application test base

use EGroupware\Api\Acl;

class AclResponsibleGroupTest extends \EGroupware\Api\AppTest
{
	protected $bo;

	protected $info_ids = array();

	/**
	 * A second, non-owner account, and a group it is a member of - the group we make
	 * responsible. Discovered from the accounts backend rather than hardcoded, as which
	 * groups exist differs per install.
	 */
	protected $other_user;
	protected $other_user_group;

	protected function setUp() : void
	{
		$this->bo = new \infolog_bo();
		$this->mockTracking($this->bo, 'infolog_tracking');
		$this->other_user = $GLOBALS['egw']->accounts->name2id($GLOBALS['EGW_ADMIN_USER']);

		$memberships = $GLOBALS['egw']->accounts->memberships($this->other_user, true);
		if(!$memberships)
		{
			$this->markTestSkipped('needs a second account that is a member of at least one group');
		}
		$this->other_user_group = (int)current($memberships);
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
		$info = array('info_type' => 'task', 'info_subject' => 'AclResponsibleGroupTest '.$this->name());
		foreach($fields as $field => $value) { $info[$field] = $value; }
		$this->info_ids[] = $info_id = $this->bo->write($info, true, true, true, true);
		return $info_id;
	}

	protected function checkAccessGrants(array $info, $required_rights, $implicit_edit, array $grants, $user)
	{
		$method = new \ReflectionMethod($this->bo, 'checkAccessGrants');
		$method->setAccessible(true);
		return $method->invoke($this->bo, $info, $required_rights, $implicit_edit, $grants, $user);
	}

	/**
	 * A group in info_responsible survives the round trip through egw_infolog_users and comes
	 * back as an account ID in the info_responsible array - the shape the push message's
	 * acl data, and therefore the clientside check, is built from.
	 */
	public function testGroupResponsibleRoundTrips()
	{
		$info_id = $this->makeInfolog(array('info_responsible' => array($this->other_user_group)));
		$info = $this->bo->read($info_id);

		$this->assertIsArray($info['info_responsible']);
		$this->assertContains($this->other_user_group, array_map('intval', $info['info_responsible']),
			'a group made responsible must come back in info_responsible');
	}

	/**
	 * The point of the whole exercise: a member of the responsible group gets implicit READ
	 * with an EMPTY grants array - ie. with no grant from the owner whatsoever. Any ACL
	 * pre-check that only consults grants would wrongly deny this user.
	 */
	public function testResponsibleGroupMemberGetsReadWithoutAnyGrant()
	{
		$info_id = $this->makeInfolog(array('info_responsible' => array($this->other_user_group)));
		$info = $this->bo->read($info_id);

		$this->assertNotEquals($this->other_user, $info['info_owner'],
			'test needs the other user to NOT be the owner');

		$this->assertTrue((bool)$this->checkAccessGrants($info, Acl::READ, false, array(), $this->other_user),
			'a member of the responsible group must get implicit READ access with no grant from the owner');
	}

	/**
	 * ...and the grants array really is empty of anything that could explain it: the decision
	 * above comes from the membership, not from a grant that happens to exist on this box.
	 */
	public function testGrantsAloneWouldDenyTheSameEntry()
	{
		$info_id = $this->makeInfolog(array('info_responsible' => array($this->other_user_group)));
		$info = $this->bo->read($info_id);

		$this->assertFalse((bool)$this->checkAccessGrants(
				array('info_owner' => $info['info_owner'], 'info_access' => 'public', 'info_responsible' => array()),
				Acl::READ, false, array(), $this->other_user),
			'without the responsible group, the very same owner/grants combination must be denied');
	}

	/**
	 * Negative control: being responsible has to mean *our* group, not any group.
	 */
	public function testForeignGroupResponsibleDenied()
	{
		$foreign_group = -99999;
		$this->assertNotContains($foreign_group, $GLOBALS['egw']->accounts->memberships($this->other_user, true),
			'test needs a group the other user is NOT a member of');

		$info_id = $this->makeInfolog(array('info_responsible' => array($foreign_group)));
		$info = $this->bo->read($info_id);

		$this->assertFalse((bool)$this->checkAccessGrants($info, Acl::READ, false, array(), $this->other_user),
			'a group we are not a member of being responsible must not grant access');
	}
}
