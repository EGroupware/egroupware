<?php
/**
 * EGroupware Api: regression test for Saml::checkJoin()'s login-throttle sharing
 *
 * @link https://www.egroupware.org
 * @package api
 * @subpackage test
 * @license http://opensource.org/licenses/gpl-license.php GPL - GNU General Public License
 */

namespace EGroupware\Api\Auth;

require_once __DIR__ . '/../LoggedInTest.php';

use EGroupware\Api;
use EGroupware\Api\LoggedInTest;

/**
 * Saml::checkJoin() used to call the local auth backend directly with no throttling at all.
 * Reaching it requires a REAL SAML IdP login first (as some identity the attacker controls), but
 * from there it lets the caller supply arbitrary local $login/$password guesses to "join" a
 * DIFFERENT, existing local account - without any rate limit, unlike the main login path's
 * Session::login_blocked() guard. Fix: checkJoin() now shares the same
 * isLoginBlocked()/recordFailedLogin() brute-force guard a normal login uses.
 *
 * checkJoin() never touches the SAML IdP itself - it only calls Api\Auth::backend(...)
 * ->authenticate() directly against the local credential - so it's exercised here directly via
 * Reflection (it's private), without needing a real IdP round-trip. Constructing a real Saml
 * instance still runs checkDefaultConfig() (writes a throwaway SimpleSAMLphp config), so this
 * uses the same temp files_dir setup as SamlDefaultConfigTest.
 */
class SamlJoinThrottleTest extends LoggedInTest
{
	const PASSWORD = 'correct-horse-battery-staple';

	protected string $files_dir;
	protected ?int $account_id = null;
	protected $old_saml_join;
	protected array $old_server = [];

	protected function setUp() : void
	{
		parent::setUp();
		$this->files_dir = sys_get_temp_dir().'/saml-join-throttle-test-'.uniqid();
		mkdir($this->files_dir);

		$this->old_server = $GLOBALS['egw_info']['server'] ?? [];
		$GLOBALS['egw_info']['server'] = array_merge($this->old_server, [
			'files_dir' => $this->files_dir,
			'webserver_url' => '/egroupware',
			'saml_idp' => 'https://idp.example.org/idp/shibboleth',
			'saml_username' => 'emailAddress',
			'saml_sp' => 'EGroupware Test',
			'saml_contact_name' => 'Admin',
			'saml_contact_email' => 'admin@example.org',
			'saml_join' => 'username',
		]);
	}

	protected function tearDown() : void
	{
		$GLOBALS['egw_info']['server'] = $this->old_server;
		exec('rm -rf '.escapeshellarg($this->files_dir));

		if ($this->account_id)
		{
			$id = $this->account_id;
			$this->account_id = null;
			$this->asAdmin(function() use ($id)
			{
				$command = new \admin_cmd_delete_account($id, null, true);
				$command->comment = 'Removing in tearDown for unit test '.$this->getName();
				$command->run();
			});
		}
		parent::tearDown();
	}

	/**
	 * A fresh local test account with a known password, so checkJoin()'s authenticate() call has
	 * something real to check the guesses against.
	 */
	protected function makeUser() : string
	{
		$login = 'samljointhrottle'.bin2hex(random_bytes(4));
		$this->asAdmin(function() use ($login)
		{
			$command = new \admin_cmd_edit_user(false, [
				'account_lid' => $login,
				'account_firstname' => 'Saml',
				'account_lastname' => 'JoinThrottleTest',
				'account_passwd' => self::PASSWORD,
				'account_passwd_2' => self::PASSWORD,
			]);
			$command->comment = 'Needed for unit test '.$this->getName();
			$command->run();
			$this->account_id = $command->account;
		});
		return $login;
	}

	/**
	 * $username (the 3rd arg) is the SAML IdP's own username attribute - unrelated to the local
	 * $login/$password being guessed, randomized here so it never collides with $login itself.
	 */
	protected function checkJoin(string $login, string $password)
	{
		$saml = new Saml();
		$method = new \ReflectionMethod($saml, 'checkJoin');
		$method->setAccessible(true);
		return $method->invoke($saml, $login, $password, 'unrelated-saml-identity-'.bin2hex(random_bytes(4)));
	}

	/**
	 * The actual fix: enough wrong guesses in a row locks the LOCAL login out even for the
	 * CORRECT password - proving checkJoin() now shares the real brute-force guard, not just
	 * "still rejects wrong guesses" (already true before this fix).
	 */
	public function testTooManyWrongGuessesLocksOutEvenTheCorrectPassword()
	{
		$login = $this->makeUser();
		// login_blocked()'s own check is strictly "> num_unsuccessful_id", so exactly that many
		// failures is still allowed - one more is what actually trips it
		$attempts = max(1, (int)($GLOBALS['egw_info']['server']['num_unsuccessful_id'] ?? 3)) + 1;

		for ($i = 0; $i < $attempts; $i++)
		{
			$result = $this->checkJoin($login, 'wrong-guess-'.$i);
			$this->assertFalse($result, "wrong guess #$i should be rejected");
		}

		// now locked out - even the CORRECT password must be rejected
		$result = $this->checkJoin($login, self::PASSWORD);

		$this->assertFalse($result,
			"the correct password was accepted right after $attempts wrong guesses - checkJoin() must ".
			"share the same brute-force guard a normal login uses");
	}

	/**
	 * The correct password with no prior failed guesses must still work - the throttle must not
	 * break the legitimate join flow.
	 */
	public function testCorrectPasswordWithoutPriorGuessesStillWorks()
	{
		$login = $this->makeUser();

		$result = $this->checkJoin($login, self::PASSWORD);

		$this->assertSame($login, $result,
			'a correct password with no prior failed guesses must still be accepted');
	}
}
