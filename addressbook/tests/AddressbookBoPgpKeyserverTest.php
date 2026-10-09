<?php
/**
 * EGroupware Addressbook: test addressbook_bo's PGP keyserver integration
 *
 * @link https://www.egroupware.org
 * @package addressbook
 * @license http://opensource.org/licenses/gpl-license.php GPL - GNU General Public License
 */

use PHPUnit\Framework\TestCase;

/**
 * help.egroupware.org/t/80089 (a real customer): get_pgp_keyserver()/set_pgp_keyserver() used to
 * hit a hardcoded, no-timeout, dead public keyserver (hkps.pool.sks-keyservers.net, shut down in
 * 2021) on every cache miss - every mail send (buildAutocryptHeader(), the sender's own identity)
 * and every PGP-signed message opened from an unknown sender waited out a ~10s DNS failure +
 * connection timeout for zero benefit. Now disabled entirely unless an admin configures a
 * keyserver (the 'pgp_keyserver' admin config, addressbook/templates/default/config.xet) - these
 * tests only cover that disabled-by-default no-op behaviour, since mocking the actual HTTP call
 * (a plain file_get_contents() in the global namespace) isn't practical without network access.
 */
#[\PHPUnit\Framework\Attributes\AllowMockObjectsWithoutExpectations]
class AddressbookBoPgpKeyserverTest extends TestCase
{
	protected function tearDown() : void
	{
		unset($GLOBALS['egw_info']['server']['pgp_keyserver']);
	}

	private function callPgpKeyserver() : ?string
	{
		$reflection = new ReflectionMethod(addressbook_bo::class, 'pgp_keyserver');
		$reflection->setAccessible(true);
		return $reflection->invoke(null);
	}

	public function testPgpKeyserverIsNullWhenUnset()
	{
		unset($GLOBALS['egw_info']['server']['pgp_keyserver']);
		$this->assertNull($this->callPgpKeyserver());
	}

	public function testPgpKeyserverIsNullWhenEmptyOrWhitespace()
	{
		$GLOBALS['egw_info']['server']['pgp_keyserver'] = '   ';
		$this->assertNull($this->callPgpKeyserver());
	}

	public function testPgpKeyserverReturnsConfiguredUrlWithoutTrailingSlash()
	{
		$GLOBALS['egw_info']['server']['pgp_keyserver'] = 'https://keys.openpgp.org/';
		$this->assertSame('https://keys.openpgp.org', $this->callPgpKeyserver());
	}

	public function testGetPgpKeyserverIsNoOpWhenNotConfigured()
	{
		unset($GLOBALS['egw_info']['server']['pgp_keyserver']);
		$seed = ['someone@example.com' => 'should not be touched'];

		// a real network call here (no keyserver configured) would mean this test either hangs
		// for seconds or fails in a sandboxed/offline CI environment - confirming it returns
		// immediately IS the point of this test
		$result = addressbook_bo::get_pgp_keyserver(['someone@example.com', 'other@example.com'], $seed);

		$this->assertSame($seed, $result, 'with no keyserver configured, the seed result must be returned completely unchanged');
	}

	public function testSetPgpKeyserverIsNoOpWhenNotConfigured()
	{
		unset($GLOBALS['egw_info']['server']['pgp_keyserver']);

		$added = addressbook_bo::set_pgp_keyserver(['someone@example.com' => '-----BEGIN PGP PUBLIC KEY BLOCK-----...']);

		$this->assertSame(0, $added, 'with no keyserver configured, nothing must be uploaded');
	}
}
