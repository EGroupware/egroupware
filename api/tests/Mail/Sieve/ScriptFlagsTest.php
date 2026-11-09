<?php
/**
 * EGroupware Api: tests for flags as optional add-on to any Sieve rule action
 *
 * @link https://www.egroupware.org
 * @package api
 * @subpackage mail
 * @license http://opensource.org/licenses/gpl-license.php GPL - GNU General Public License
 */

namespace EGroupware\Api\Mail\Sieve;

use PHPUnit\Framework\TestCase;

/**
 * Flags ("mark as read" etc.) used to be an exclusive rule action. They are now an optional add-on to
 * any other action, so a single rule can e.g. file spam into Junk AND mark it as read.
 * Old rules with action "flags" have to generate the same script as before.
 */
class ScriptFlagsTest extends TestCase
{
	private function fakeConnection() : Connection
	{
		return new class implements Connection
		{
			public function __construct($params=[]) {}
			public function listScripts() { return []; }
			public function getActive() { return null; }
			public function getScript($scriptname) { return ''; }
			public function installScript($scriptname, $script, $makeactive=false) { return true; }
			public function removeScript($scriptname) { return true; }
			public function hasSpace($scriptname, $size) { return true; }
			public function getExtensions() { return ['imap4flags', 'variables', 'regex', 'envelope']; }
			public function hasExtension($extension) { return in_array($extension, $this->getExtensions()); }
		};
	}

	private function generate(array $rule) : string
	{
		$script = new Script('flags-test');
		$script->rules = [$rule + ['priority' => 1, 'status' => 'ENABLED', 'flg' => 0]];
		$vac_rule = null;
		$script->updateScript($this->fakeConnection(), false, $vac_rule, true);
		return $script->script;
	}

	public function testLegacyFlagsActionIsNormalized() : void
	{
		$this->assertSame(
			['action' => '', 'action_arg' => '', 'action_flags' => '\\\\Seen \\\\Flagged'],
			array_intersect_key(Script::normalizeRuleActions(['action' => 'flags', 'action_arg' => '\\\\Seen \\\\Flagged']),
				array_flip(['action', 'action_arg', 'action_flags'])));
	}

	public function testNoneActionIsUnset() : void
	{
		$this->assertSame('', Script::normalizeRuleActions(['action' => 'none', 'action_flags' => '\\\\Seen'])['action']);
	}

	public function testOtherActionsAreNotTouched() : void
	{
		$rule = Script::normalizeRuleActions(['action' => 'folder', 'action_arg' => 'Junk', 'action_flags' => '\\\\Seen']);
		$this->assertSame('folder', $rule['action']);
		$this->assertSame('Junk', $rule['action_arg']);
		$this->assertSame('\\\\Seen', $rule['action_flags']);
	}

	public function testFlagsAreSetBeforeFileinto() : void
	{
		$script = $this->generate([
			'field' => 'X-Spam-Flag', 'field_val' => 'YES', 'comparator' => 'contains',
			'action' => 'folder', 'action_arg' => 'Junk', 'action_flags' => '\\\\Seen',
		]);
		$this->assertStringContainsString('header :contains "X-Spam-Flag" "YES"', $script);
		$flag = strpos($script, 'addflag "\\\\Seen";');
		$file = strpos($script, 'fileinto "Junk";');
		$this->assertNotFalse($flag, $script);
		$this->assertNotFalse($file, $script);
		$this->assertLessThan($file, $flag, "addflag must come before fileinto to apply to it:\n$script");
	}

	public function testLegacyFlagsRuleGeneratesSameAsBefore() : void
	{
		$legacy = $this->generate(['from' => 'a@b.example', 'action' => 'flags', 'action_arg' => '\\\\Seen']);
		$new = $this->generate(['from' => 'a@b.example', 'action' => '', 'action_flags' => '\\\\Seen']);
		$this->assertStringContainsString('addflag "\\\\Seen";', $legacy);
		$strip = fn($s) => preg_replace('/^#.*$/m', '', $s);
		$this->assertSame($strip($legacy), $strip($new));
	}

	public function testFlagsOnlyRuleIsValidWithoutAction() : void
	{
		$script = $this->generate(['action' => '', 'action_flags' => '\\\\Seen']);
		$this->assertStringContainsString('addflag "\\\\Seen";', $script);
	}

	public function testNoFlagsMeansNoAddflag() : void
	{
		$script = $this->generate(['from' => 'a@b.example', 'action' => 'folder', 'action_arg' => 'Junk']);
		$this->assertStringNotContainsString('addflag', $script);
	}

	public function testDescriptionDoesNotChangeGeneratedScript() : void
	{
		$rule = ['from' => 'a@b.example', 'action' => 'folder', 'action_arg' => 'Junk'];
		$strip = fn($s) => preg_replace('/^#.*$/m', '', $s);
		$this->assertSame($strip($this->generate($rule)), $strip($this->generate($rule + ['description' => 'SpamTitan'])));
	}
}
