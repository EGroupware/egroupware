<?php
/**
 * EGroupware Mail: security test for mail_ui's smimeExportCert()/smimeExportCsr() account_id guard
 *
 * @link http://www.egroupware.org
 * @package mail
 * @license http://opensource.org/licenses/gpl-license.php GPL - GNU General Public License Version 2+
 */

require_once realpath(__DIR__.'/../../api/tests/LoggedInTest.php');

use EGroupware\Api;

/**
 * Test-only subclass exposing the private smimeAccountId(), so it can be tested without going
 * through the full GET-download endpoints (which echo binary/PEM data and exit()).
 */
class TestableMailUiSmimeAccountId extends \mail_ui
{
	public function callSmimeAccountId()
	{
		$ref = new ReflectionMethod(\mail_ui::class, 'smimeAccountId');
		$ref->setAccessible(true);
		return $ref->invoke($this);
	}
}

/**
 * mail_ui::smimeAccountId() vets $_GET['account_id'] for smimeExportCert()/smimeExportCsr():
 * without this check, ANY logged in user could pass an arbitrary account_id to export another
 * user's S/MIME private key/p12, since Mail\Smime::get_acc_smime() otherwise looks up credentials
 * under whatever account_id it's given (used when an admin manages a shared/other user's mail
 * account via admin_mail's called_for).
 *
 * Only users with admin app rights may have $_GET['account_id'] honoured at all; everyone else's
 * value is silently ignored (falls back to their own account, same as not passing it).
 */
class SmimeAccountIdTest extends Api\LoggedInTest
{
	private $admin_apps_backup;
	private $get_backup;

	protected function setUp() : void
	{
		$this->admin_apps_backup = $GLOBALS['egw_info']['user']['apps']['admin'] ?? null;
		$this->get_backup = $_GET['account_id'] ?? null;
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
		if ($this->get_backup === null)
		{
			unset($_GET['account_id']);
		}
		else
		{
			$_GET['account_id'] = $this->get_backup;
		}
	}

	/**
	 * A non-admin user's $_GET['account_id'] must be ignored entirely (null returned), so
	 * get_acc_smime() falls back to looking up the CURRENT user's own credentials only.
	 */
	public function testNonAdminAccountIdIsIgnored()
	{
		unset($GLOBALS['egw_info']['user']['apps']['admin']);
		$_GET['account_id'] = 999;

		$mail_ui = new TestableMailUiSmimeAccountId(false);

		$this->assertNull($mail_ui->callSmimeAccountId(),
			'a non-admin user must not be able to make the export endpoints look up another account_id');
	}

	/**
	 * An admin user's $_GET['account_id'] IS honoured (needed for exporting a S/MIME key stored
	 * on behalf of another user via admin_mail's called_for).
	 */
	public function testAdminAccountIdIsHonoured()
	{
		$GLOBALS['egw_info']['user']['apps']['admin'] = true;
		$_GET['account_id'] = 999;

		$mail_ui = new TestableMailUiSmimeAccountId(false);

		$this->assertSame(999, $mail_ui->callSmimeAccountId(),
			'an admin user\'s account_id must be passed through to get_acc_smime()');
	}

	/**
	 * No $_GET['account_id'] at all must return null regardless of admin rights (get_acc_smime()
	 * then uses its own current-user default) - the common case for a user managing their own key.
	 */
	public function testNoAccountIdGivenReturnsNull()
	{
		$GLOBALS['egw_info']['user']['apps']['admin'] = true;
		unset($_GET['account_id']);

		$mail_ui = new TestableMailUiSmimeAccountId(false);

		$this->assertNull($mail_ui->callSmimeAccountId());
	}
}
