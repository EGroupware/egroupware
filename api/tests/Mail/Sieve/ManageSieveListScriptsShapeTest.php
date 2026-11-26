<?php
/**
 * EGroupware Api: Test Sieve\ManageSieve::listScripts()'s return shape
 *
 * @link https://www.egroupware.org
 * @package api
 * @subpackage mail
 * @license http://opensource.org/licenses/gpl-license.php GPL - GNU General Public License
 */

namespace EGroupware\Api\Mail\Sieve;

use PHPUnit\Framework\TestCase;

/**
 * Regression test (ticket #128741, found live 2026-11-26): a regular, non-admin user activating
 * the vacation notice on a standard ManageSieve/dovecot account fataled in Script::retrieveRules()'s
 * `in_array($this->name, $connection->listScripts()[0])` with "Argument #2 ($haystack) must be of
 * type array, string given".
 *
 * Root cause: Sieve\ManageSieve never overrode listScripts() and so inherited
 * Horde\ManageSieve::listScripts(), which just returns the flat names array (its own 2nd/3rd
 * element, the active script, comes from a separate _cmdListScripts() call instead) - a different
 * shape than Sieve\Jmap::listScripts()'s documented/actual [names[], activeName, fullList[]]
 * 3-tuple, which every caller reading index [0] as an array (Script::retrieveRules(), Forward.php)
 * assumes regardless of which Connection implementation they were handed.
 */
class ManageSieveListScriptsShapeTest extends TestCase
{
	/** Build a ManageSieve instance without ever opening a real connection */
	private function uninitializedManageSieve(array $scripts, ?string $active) : ManageSieve
	{
		return new class(['scripts' => $scripts, 'active' => $active]) extends ManageSieve
		{
			public function __construct($params=[])
			{
				// deliberately skip parent::__construct() - no network/auth needed for this test
				$this->fixture = $params;
			}

			private array $fixture = [];

			protected function _cmdListScripts()
			{
				return [$this->fixture['scripts'], $this->fixture['active']];
			}
		};
	}

	public function testListScriptsReturnsThreeElementArrayWithNamesArrayFirst()
	{
		$connection = $this->uninitializedManageSieve(['sieve', 'EGW18'], 'EGW18');

		$result = $connection->listScripts();

		$this->assertIsArray($result);
		$this->assertCount(3, $result,
			"listScripts() must return the documented [names[], activeName, fullList[]] 3-tuple");
		$this->assertIsArray($result[0],
			"listScripts()[0] must be an array of script names, not a bare name string - ".
			"otherwise in_array(\$name, listScripts()[0]) fatals (ticket #128741)");
		$this->assertSame(['sieve', 'EGW18'], $result[0]);
		$this->assertSame('EGW18', $result[1]);
	}

	public function testListScriptsWithNoActiveScript()
	{
		$connection = $this->uninitializedManageSieve(['felamimail'], null);

		$result = $connection->listScripts();

		$this->assertIsArray($result[0]);
		$this->assertSame(['felamimail'], $result[0]);
		$this->assertNull($result[1]);
	}

	public function testScriptNameLookupAgainstListScriptsNoLongerFatals()
	{
		// exercises the actual failing expression from Script::retrieveRules() (line 109)
		$connection = $this->uninitializedManageSieve(['sieve', 'EGW18'], 'EGW18');

		$this->assertFalse(in_array('not-there', $connection->listScripts()[0]));
		$this->assertTrue(in_array('EGW18', $connection->listScripts()[0]));
	}
}
