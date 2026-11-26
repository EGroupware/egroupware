<?php
/**
 * EGroupware Api: Test Api\Mail\Sieve\Script::retrieveRules()'s "does this script already exist"
 * check against a script that exists but isn't the active one
 *
 * @link https://www.egroupware.org
 * @package api
 * @subpackage mail
 * @license http://opensource.org/licenses/gpl-license.php GPL - GNU General Public License
 */

namespace EGroupware\Api\Mail\Sieve;

/**
 * Found live 2026-11-26 (Nordenham) while investigating a different, related bug
 * (Jmap::getScript()'s own array_filter()/list-destructure bug): retrieveRules()'s own
 * `in_array($this->name, $connection->listScripts())` compared a script NAME (string) against
 * listScripts()'s full 3-element `[names[], activeName, fullList[]]` return value, rather than
 * just the names array (element 0). PHP's loose in_array() comparison against that mixed-type
 * array only ever matches element 1 (activeName) - a string is never loosely equal to an array
 * (elements 0 and 2) - so this condition actually meant "name !== the currently active script"
 * instead of its stated intent "no script by this name exists at all", silently calling
 * updateScript() (which creates/overwrites a script) merely because the requested name wasn't
 * the active one, even though it genuinely already existed.
 */
class ScriptRetrieveRulesExistingScriptTest extends \PHPUnit\Framework\TestCase
{
	private function fakeConnection() : Connection
	{
		return new class implements Connection
		{
			public bool $updateScriptCalled = false;
			public function __construct($params=[]) {}
			public function listScripts() { return [['sieve', 'EGW18', 'felamimail', 'mail'], 'EGW18', []]; }
			public function getActive() { return 'EGW18'; }
			public function getScript($scriptname)
			{
				// recognised encoding header (Script::retrieveRules()'s own format check), no rules
				return "# Mail filter rules for test\n# Generated\n";
			}
			public function installScript($scriptname, $script, $makeactive=false)
			{
				$this->updateScriptCalled = true;
			}
			public function removeScript($scriptname) {}
			public function hasSpace($scriptname, $size) { return true; }
			public function getExtensions() { return []; }
			public function hasExtension($extension) { return false; }
		};
	}

	public function testExistingNonActiveScriptIsNotRecreated()
	{
		$connection = $this->fakeConnection();

		$script = new Script('felamimail');
		$script->retrieveRules($connection);

		$this->assertFalse($connection->updateScriptCalled,
			"'felamimail' already exists (per listScripts()[0]) - retrieveRules() must not call updateScript()/installScript() for it merely because it isn't the active script");
	}

	public function testTrulyMissingScriptIsCreated()
	{
		$connection = $this->fakeConnection();

		$script = new Script('does-not-exist');
		$script->retrieveRules($connection);

		$this->assertTrue($connection->updateScriptCalled, 'a genuinely missing script name must still be created');
	}
}
