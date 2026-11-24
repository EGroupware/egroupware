<?php
/**
 * EGroupware Api: Test Mail\Smtp\Stalwart saving only the forwards (saveSMTPForwarding(), eg. a user changing his own forward)
 *
 * @link https://www.egroupware.org
 * @package api
 * @subpackage mail
 * @license http://opensource.org/licenses/gpl-license.php GPL - GNU General Public License
 */

use EGroupware\Api\Mail\Smtp\Stalwart;
use PHPUnit\Framework\TestCase;

class StalwartForwardOnlyTest extends TestCase
{
	private function stalwart(?string $stalwartId='s1') : Stalwart
	{
		return new class($stalwartId) extends Stalwart
		{
			public array $forwards = [];
			public function __construct(private ?string $stalwartId)
			{
			}
			public function getUserData($user, $match_uid_at_domain=false, bool $no_cache=false)
			{
				return $this->stalwartId ? ['stalwart' => ['id' => $this->stalwartId]] : [];
			}
			protected function forward(string $accountId, ?array $addresses=null, bool $keep=false)
			{
				$this->forwards[] = [$accountId, $addresses, $keep];
				return true;
			}
		};
	}

	public function testForwardWithCopy() : void
	{
		$stalwart = $this->stalwart();
		$this->assertTrue($stalwart->saveSMTPForwarding(5, ['a@example.org'], 'yes'));
		$this->assertSame([['s1', ['a@example.org'], true]], $stalwart->forwards);
	}

	public function testForwardOnly() : void
	{
		$stalwart = $this->stalwart();
		$this->assertTrue($stalwart->saveSMTPForwarding(5, 'a@example.org', 'no'));
		$this->assertSame([['s1', ['a@example.org'], false]], $stalwart->forwards);
	}

	public function testRemoveForward() : void
	{
		$stalwart = $this->stalwart();
		$stalwart->saveSMTPForwarding(5, '', 'yes');
		$this->assertSame([['s1', [], true]], $stalwart->forwards);
	}

	public function testAccountNotInStalwart() : void
	{
		$stalwart = $this->stalwart(null);
		$this->assertTrue($stalwart->saveSMTPForwarding(5, ['a@example.org'], 'yes'));
		$this->assertSame([], $stalwart->forwards);
	}
}
