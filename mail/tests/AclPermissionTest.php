<?php

/**
 * Test the admin-permission check used by mail_acl's recursive grant/delete endpoints
 *
 * @link http://www.egroupware.org
 * @package mail
 * @license http://opensource.org/licenses/gpl-license.php GPL - GNU General Public License
 */

namespace EGroupware\Mail;

use EGroupware\Api;

/**
 * Test-only subclass exposing the protected _require_admin_permission() check, so it can
 * be tested in isolation without needing a live IMAP connection or database session.
 */
class TestableAcl extends \mail_acl
{
	public static function checkPermission($account_id)
	{
		self::_require_admin_permission($account_id);
	}
}

class AclPermissionTest extends \PHPUnit\Framework\TestCase
{
	protected $admin_apps_backup;

	protected function setUp() : void
	{
		$this->admin_apps_backup = $GLOBALS['egw_info']['user']['apps']['admin'] ?? null;
	}

	protected function tearDown() : void
	{
		if ($this->admin_apps_backup === null)
		{
			unset($GLOBALS['egw_info']['user']['apps']['admin']);
		}
		else
		{
			$GLOBALS['egw_info']['user']['apps']['admin'] = $this->admin_apps_backup;
		}
	}

	/**
	 * No account_id given (own mailbox): never requires admin rights
	 */
	public function testNoAccountIdNeverThrows()
	{
		unset($GLOBALS['egw_info']['user']['apps']['admin']);

		TestableAcl::checkPermission(null);
		$this->assertTrue(true, 'checkPermission(null) must not throw');
	}

	/**
	 * account_id given (another user's mailbox) + admin app permission: allowed
	 */
	public function testAccountIdWithAdminPermissionDoesNotThrow()
	{
		$GLOBALS['egw_info']['user']['apps']['admin'] = true;

		TestableAcl::checkPermission(42);
		$this->assertTrue(true, 'checkPermission(42) with admin app rights must not throw');
	}

	/**
	 * account_id given (another user's mailbox) + no admin app permission: denied
	 */
	public function testAccountIdWithoutAdminPermissionThrows()
	{
		unset($GLOBALS['egw_info']['user']['apps']['admin']);

		$this->expectException(Api\Exception\NoPermission\Admin::class);
		TestableAcl::checkPermission(42);
	}
}
