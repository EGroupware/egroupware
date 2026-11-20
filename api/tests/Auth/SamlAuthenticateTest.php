<?php
/**
 * EGroupware Api: Saml::authenticate() only authenticates the account the SAML session was asserted for
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
 * isAssertedAccount() is exercised directly via Reflection (it's private), without needing a real
 * IdP round-trip - same approach as SamlJoinThrottleTest. Constructing a real Saml instance still
 * runs checkDefaultConfig() (writes a throwaway SimpleSAMLphp config), so this uses the same temp
 * files_dir setup as SamlDefaultConfigTest.
 */
class SamlAuthenticateTest extends LoggedInTest
{
	protected string $files_dir;
	protected array $old_server = [];
	protected ?string $old_host = null;

	protected function setUp() : void
	{
		parent::setUp();
		$this->files_dir = sys_get_temp_dir().'/saml-authenticate-test-'.uniqid();
		mkdir($this->files_dir);
		$this->old_server = $GLOBALS['egw_info']['server'] ?? [];
		$this->old_host = $_SERVER['HTTP_HOST'] ?? null;

		$GLOBALS['egw_info']['server'] = array_merge($GLOBALS['egw_info']['server'] ?? [], [
			'files_dir' => $this->files_dir,
			'webserver_url' => '/egroupware',
			'saml_idp' => 'https://idp.example.org/idp/shibboleth',
			'saml_username' => 'uid',
			'saml_sp' => 'EGroupware Test',
			'saml_contact_name' => 'Admin',
			'saml_contact_email' => 'admin@example.org',
			'saml_replace' => '',
		]);
		$_SERVER['HTTP_HOST'] = 'saml-authenticate-test.example.org';
	}

	protected function tearDown() : void
	{
		$GLOBALS['egw_info']['server'] = $this->old_server;
		if (isset($this->old_host)) $_SERVER['HTTP_HOST'] = $this->old_host; else unset($_SERVER['HTTP_HOST']);
		exec('rm -rf '.escapeshellarg($this->files_dir));
		parent::tearDown();
	}

	protected function isAssertedAccount(string $username, array $attrs) : bool
	{
		$saml = new Saml();
		$method = new \ReflectionMethod($saml, 'isAssertedAccount');
		$method->setAccessible(true);
		return $method->invoke($saml, $username, $attrs);
	}

	public function testMatchingUsernameIsAccepted()
	{
		$this->assertTrue($this->isAssertedAccount('attacker', ['uid' => ['attacker']]));
	}

	public function testComparisonIsCaseInsensitive()
	{
		$this->assertTrue($this->isAssertedAccount('ATTACKER', ['uid' => ['attacker']]));
	}

	public function testDifferentUsernameIsRejected()
	{
		$this->assertFalse($this->isAssertedAccount('sysop', ['uid' => ['attacker']]),
			'a username the SAML session did not assert must be refused');
	}

	public function testNoAttributesRejectsEveryUsername()
	{
		$this->assertFalse($this->isAssertedAccount('attacker', []),
			'a session without an asserted username must authenticate nobody');
	}

	public function testMultiValuedAttributeIsRejected()
	{
		$this->assertFalse($this->isAssertedAccount('attacker', ['uid' => ['attacker', 'sysop']]),
			'an ambiguous (multi-valued) asserted username must not match either value');
	}
}
