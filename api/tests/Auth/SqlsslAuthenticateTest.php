<?php
/**
 * EGroupware Api: tests for Sqlssl::isAssertedAccount(), used by authenticate()
 *
 * @link https://www.egroupware.org
 * @package api
 * @subpackage test
 * @license http://opensource.org/licenses/gpl-license.php GPL - GNU General Public License
 */

namespace EGroupware\Api\Auth;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * A presented client certificate authenticates only the account its own Email attribute
 * asserts, not whatever username the call happened to be made with.
 *
 * Called directly via reflection, since it is private and pure string logic - no DB or session
 * needed, same approach as SamlAuthenticateTest.
 */
class SqlsslAuthenticateTest extends TestCase
{
	protected function isAssertedAccount(string $username, string $dn) : bool
	{
		$method = new \ReflectionMethod(Sqlssl::class, 'isAssertedAccount');
		$method->setAccessible(true);
		return $method->invoke(null, $username, $dn);
	}

	public static function providerDn() : array
	{
		$dn = 'CN=john.doe/OU=Department/O=Company/C=xx/Email=john@company.tld/L=City/';
		return [
			'matching email' => [true, 'john@company.tld', $dn],
			'different username' => [false, 'victim@company.tld', $dn],
			'comparison is case-insensitive' => [true, 'JOHN@COMPANY.TLD', $dn],
			'no Email attribute' => [false, 'john@company.tld', 'CN=john.doe/OU=Department/O=Company/C=xx/L=City/'],
			'empty DN' => [false, 'john@company.tld', ''],
		];
	}

	/**
	 * Pass criteria: only the DN's own Email attribute, compared case-insensitively, may match
	 */
	#[DataProvider('providerDn')]
	public function testIsAssertedAccount(bool $expected, string $username, string $dn)
	{
		$this->assertSame($expected, $this->isAssertedAccount($username, $dn));
	}
}
