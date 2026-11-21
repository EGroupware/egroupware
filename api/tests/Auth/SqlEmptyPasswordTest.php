<?php
/**
 * EGroupware Api: Auth\Sql::authenticate() must not accept an empty password for an account
 * stored without one
 *
 * @link https://www.egroupware.org
 * @package api
 * @subpackage test
 * @license http://opensource.org/licenses/gpl-license.php GPL - GNU General Public License
 */

namespace EGroupware\Api\Auth;

require_once __DIR__ . '/../LoggedInTest.php';

use EGroupware\Api\LoggedInTest;

/**
 * authenticate() must refuse an empty password for an account stored without one, on every
 * passwd_type, the same way it already does for the plain-text one.
 */
class SqlEmptyPasswordTest extends LoggedInTest
{
	/**
	 * Accounts created by this test, removed again afterwards
	 *
	 * @var int[]
	 */
	protected $accounts = array();

	/**
	 * Create one account, optionally without a password
	 *
	 * @param string $lid
	 * @param string $passwd empty string: no local password
	 * @return int account_id
	 */
	protected function createAccount(string $lid, string $passwd) : int
	{
		$data = array(
			'account_lid' => $lid,
			'account_type' => 'u',
			'account_firstname' => 'Test',
			'account_lastname' => 'Empty password',
			'account_email' => $lid.'@example.invalid',
			'account_status' => 'A',
			'account_passwd' => $passwd,
		);
		$account_id = $GLOBALS['egw']->accounts->save($data);
		$this->assertNotEmpty($account_id, 'could not create account '.$lid);
		$this->accounts[] = $account_id;
		return $account_id;
	}

	/**
	 * Remove the accounts this test added
	 */
	protected function tearDown() : void
	{
		foreach ($this->accounts as $account_id)
		{
			$GLOBALS['egw']->accounts->delete($account_id);
		}
		$this->accounts = array();
		parent::tearDown();
	}

	/**
	 * The stored password of the given account
	 */
	protected function storedPassword(string $lid) : string
	{
		$row = $GLOBALS['egw']->db->select('egw_accounts', 'account_pwd', array('account_lid' => $lid), __LINE__, __FILE__)->fetch();
		$this->assertNotEmpty($row, 'account '.$lid.' not found');
		return (string)$row['account_pwd'];
	}

	/**
	 * Write a literal value into account_pwd directly, bypassing save()'s hashing - the only way
	 * a non-empty, non-hash value (eg. whitespace) ends up stored
	 */
	protected function setStoredPassword(string $lid, string $value) : void
	{
		$GLOBALS['egw']->db->update('egw_accounts', array('account_pwd' => $value), array('account_lid' => $lid), __LINE__, __FILE__);
	}

	/**
	 * An account with no local password refuses an empty password on every passwd_type
	 */
	public function testEmptyStoredPasswordRefusesEmptyPassword()
	{
		$lid = 'emptypwd'.uniqid();
		$this->createAccount($lid, '');
		$this->assertSame('', $this->storedPassword($lid), 'the account was not stored with an empty password');

		$backend = new Sql();
		$this->assertFalse($backend->authenticate($lid, '', 'md5'), 'an empty stored password authenticated an empty md5 password');
		$this->assertFalse($backend->authenticate($lid, '', ''), 'an empty stored password authenticated an empty password');
		$this->assertFalse($backend->authenticate($lid, '', 'text'), 'an empty stored password authenticated on the text branch');
	}

	/**
	 * An account whose stored password is whitespace-only (never produced by the admin dialog,
	 * which hashes any typed password - only reachable by writing account_pwd directly) refuses a
	 * whitespace-only posted password too, not just a strictly empty one
	 */
	public function testWhitespaceStoredPasswordRefusesWhitespacePassword()
	{
		$lid = 'wspwd'.uniqid();
		$this->createAccount($lid, '');
		$this->setStoredPassword($lid, ' ');
		$this->assertSame(' ', $this->storedPassword($lid), 'the account was not stored with a whitespace-only password');
		$backend = new Sql();
		$this->assertFalse($backend->authenticate($lid, ' ', 'md5'), 'a single-space stored password authenticated a single-space md5 password');

		$lid2 = 'wspwd'.uniqid();
		$this->createAccount($lid2, '');
		$this->setStoredPassword($lid2, "\t");
		$this->assertFalse($backend->authenticate($lid2, "\t", 'md5'), 'a tab-only stored password authenticated a tab-only md5 password');
	}

	/**
	 * An account with a real password still authenticates with it, on both branches
	 */
	public function testRealPasswordStillAuthenticates()
	{
		$lid = 'realpwd'.uniqid();
		$password = 'correct-horse-'.uniqid();
		$this->createAccount($lid, $password);
		$hash = $this->storedPassword($lid);
		$this->assertNotSame('', $hash, 'the account was stored without a password');

		$backend = new Sql();
		$this->assertTrue($backend->authenticate($lid, $password, 'text'), 'the text branch refused the real password');
		$this->assertTrue($backend->authenticate($lid, $hash, 'md5'), 'the stored hash did not authenticate on the non-text branch');
		$this->assertFalse($backend->authenticate($lid, '', 'md5'), 'an account with a real password authenticated an empty one');
	}
}
