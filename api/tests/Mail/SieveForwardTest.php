<?php
/**
 * EGroupware Api: Test Mail\Sieve\Forward, forwarding of all mail by a Sieve rule
 *
 * @link https://www.egroupware.org
 * @package api
 * @subpackage mail
 * @license http://opensource.org/licenses/gpl-license.php GPL - GNU General Public License
 */

use EGroupware\Api\Mail\Sieve\Connection;
use EGroupware\Api\Mail\Sieve\Forward;
use EGroupware\Api\Mail\Sieve\Script;
use PHPUnit\Framework\TestCase;

/**
 * Stand-in Sieve server holding at most one script
 */
class SieveForwardMemConnection implements Connection
{
	public int $installs = 0;

	public ?string $text;
	public array $extensions = ['vacation', 'fileinto', 'variables'];

	public function __construct($params=[])
	{
		$this->text = $params['text'] ?? null;
	}
	public function listScripts() { return [$this->text !== null ? ['mail'] : [], $this->text !== null ? 'mail' : null, []]; }
	public function getActive() { return $this->text !== null ? 'mail' : throw new \Exception('NO active script found'); }
	public function getScript($scriptname) { return $this->text; }
	public function installScript($scriptname, $script, $makeactive=false) { ++$this->installs; $this->text = $script; return true; }
	public function removeScript($scriptname) { $this->text = null; return true; }
	public function hasSpace($scriptname, $size) { return true; }
	public function getExtensions() { return $this->extensions; }
	public function hasExtension($extension) { return in_array($extension, $this->extensions); }
}

class SieveForwardTest extends TestCase
{
	protected function setUp() : void
	{
		$GLOBALS['egw_info'] = [
			'user' => ['account_lid' => 'test', 'domain' => '', 'preferences' => ['common' => ['dateformat' => 'Y-m-d']]],
			'apps' => ['mail' => ['version' => '26.1']],
			'server' => ['site_title' => 'EGroupware'],
		];
		$GLOBALS['default'] = (object)['vacation_days' => 3, 'vacation_text' => '', 'lib_dir' => '', 'script_date_format' => ''];
	}

	public function testNoScript() : void
	{
		$conn = new SieveForwardMemConnection();
		$this->assertNull(Forward::get($conn));
		$this->assertFalse(Forward::set($conn, []), 'removing a not existing forward changes nothing');
		$this->assertNull($conn->text);
	}

	public function testCreateReadChangeRemove() : void
	{
		$conn = new SieveForwardMemConnection();
		$this->assertTrue(Forward::set($conn, ['a@example.org', 'Name <b@example.org>'], false));
		$this->assertSame(['addresses' => ['a@example.org', 'b@example.org'], 'keep' => false], Forward::get($conn));
		$this->assertStringContainsString('redirect', $conn->text);

		$installs = $conn->installs;
		$this->assertFalse(Forward::set($conn, ['A@example.org', 'b@example.org'], false), 'same forward (case-insensitive) is no change');
		$this->assertSame($installs, $conn->installs);

		$this->assertTrue(Forward::set($conn, ['c@example.org'], true));
		$this->assertSame(['addresses' => ['c@example.org'], 'keep' => true], Forward::get($conn));
		$this->assertCount(1, array_filter($this->rules($conn), [Forward::class, 'isForwardRule']), 'one rule only');

		$this->assertTrue(Forward::set($conn, []));
		$this->assertNull(Forward::get($conn));
	}

	public function testBehindSpamRule() : void
	{
		$conn = new SieveForwardMemConnection();
		$script = new Script('mail');
		$script->rules = [[
			'description' => 'SpamTitan', 'tabs' => 'action', 'anyof' => '0', 'from' => '', 'to' => '', 'subject' => '', 'gthan' => '0',
			'size' => '0', 'field' => 'X-Spam-Flag', 'comparator' => 'contains', 'field_val' => 'YES', 'bodytransform' => '0',
			'field_bodytransform' => '', 'ctype' => '0', 'field_ctype_val' => '', 'action' => 'folder', 'continue' => false,
			'keep' => false, 'regexp' => false, 'tokenized' => false, 'ruleID' => 1, 'priority' => 1, 'status' => 'ENABLED',
			'action_arg' => 'Junk', 'action_flags' => '\\Seen', 'flg' => 0,
		]];
		$script->updateScript($conn, false, $vac, true);

		$this->assertTrue(Forward::set($conn, ['a@example.org']));
		$rules = $this->rules($conn);
		$this->assertTrue(Forward::isSpamRule($rules[0]), 'spam rule stays first');
		$this->assertTrue(!empty($rules[0]['stop']), 'spam rule stops, spam is not forwarded');
		$this->assertTrue(Forward::isForwardRule($rules[1]));
	}

	public function testForeignScript() : void
	{
		$foreign = "require [\"fileinto\"];\nif header :contains \"subject\" \"x\" { fileinto \"X\"; }\n";
		$conn = new SieveForwardMemConnection(['text' => $foreign]);
		$this->assertNull(Forward::get($conn), 'reading ignores a foreign script');
		$this->assertFalse(Forward::set($conn, []), 'nothing to remove from a foreign script, no error');
		try
		{
			Forward::set($conn, ['a@example.org']);
			$this->fail('writing into a foreign script must throw');
		}
		catch (\Exception $e)
		{
			$this->assertStringContainsString('not in a format of EGroupware', $e->getMessage());
		}
		$this->assertSame($foreign, $conn->text, 'foreign script is untouched');
	}

	public function testInvalidAddress() : void
	{
		$this->expectException(\InvalidArgumentException::class);
		Forward::set(new SieveForwardMemConnection(), ['not-an-address']);
	}

	public function testLegacyFormatIsConverted() : void
	{
		$legacy = "# Mail filter rules for test\n\n##PSEUDO script start\n#rule&&1&&ENABLED&&&&&&folder&&Spam&&0&&X-Spam-Flag&&YES&&0&&0&&&&0&&\n##PSEUDO script end\n";
		$conn = new SieveForwardMemConnection(['text' => $legacy]);
		$this->assertNull(Forward::get($conn));
		$this->assertTrue(Forward::set($conn, ['a@example.org']));
		$rules = $this->rules($conn);
		$this->assertCount(2, $rules, 'old rule kept, forward added');
		$this->assertSame(['a@example.org'], Forward::get($conn)['addresses']);
	}

	protected function rules(SieveForwardMemConnection $conn) : array
	{
		$script = new Script('mail');
		$this->assertTrue($script->retrieveRules($conn));
		return array_values($script->rules);
	}
}
