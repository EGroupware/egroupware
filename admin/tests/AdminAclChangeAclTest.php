<?php
/**
 * Tests for admin_acl::checkValidLocation(), used by ajax_change_acl()
 *
 * @link http://www.egroupware.org
 * @package admin
 * @license http://opensource.org/licenses/gpl-license.php GPL - GNU General Public License
 */

// test base providing common stuff
require_once __DIR__.'/CommandBase.php';

use EGroupware\Api;

/**
 * admin_acl::checkValidLocation() restricts a posted ACL row's location to what the editor's
 * account selector can actually produce: 'run', or a plain account/group id.
 *
 * Called directly here via reflection, since it is private and ajax_change_acl() itself needs a
 * live etemplate_exec_id (CSRF) plus a full ACL round trip to exercise - this isolates the one
 * thing that changed.
 */
class AdminAclChangeAclTest extends CommandBase
{
	private function checkValidLocation($location)
	{
		$method = new \ReflectionMethod('admin_acl', 'checkValidLocation');
		$method->setAccessible(true);
		$method->invoke(null, $location);
	}

	/**
	 * 'everywhere' is not a value the editor's account selector can produce.
	 */
	public function testWildcardLocationIsRejected()
	{
		$this->expectException(Api\Exception\WrongUserinput::class);

		$this->checkValidLocation('everywhere');
	}

	/**
	 * Nor is any other app-specific string.
	 */
	public function testArbitraryStringLocationIsRejected()
	{
		$this->expectException(Api\Exception\WrongUserinput::class);

		$this->checkValidLocation('superadmin');
	}

	/**
	 * 'run' is a value the editor's account selector can produce (run-rights) and must pass.
	 */
	public function testRunLocationIsAccepted()
	{
		$this->checkValidLocation('run');
		$this->addToAssertionCount(1);	// no exception thrown
	}

	/**
	 * A plain account id must pass.
	 */
	public function testPositiveNumericLocationIsAccepted()
	{
		$this->checkValidLocation('44');
		$this->addToAssertionCount(1);	// no exception thrown
	}

	/**
	 * A group id (negative) must pass too.
	 */
	public function testNegativeNumericLocationIsAccepted()
	{
		$this->checkValidLocation('-5');
		$this->addToAssertionCount(1);	// no exception thrown
	}
}
