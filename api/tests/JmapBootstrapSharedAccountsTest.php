<?php
/**
 * EGroupware Api: Jmap::bootstrap() must retain every non-personal Session account, not discard them
 *
 * @link https://www.egroupware.org
 * @package api
 * @license http://opensource.org/licenses/gpl-license.php GPL - GNU General Public License
 */

namespace EGroupware\Api;

use PHPUnit\Framework\TestCase;

/**
 * doc/ai/projects/mail-jmap-shared-mailboxes.md: bootstrap() used to loop the Session's own
 * `accounts` map and `break` on the first isPersonal entry, discarding every shared account a
 * Mailbox.shareWith grant had already made visible there (confirmed live, 2026-10-29, against the
 * real egroupware.org Stalwart tenant: 22 non-personal accounts, zero extra action needed to make
 * them appear). Retained instead, via the new $sharedAccounts property, so
 * Api\Mail\Imap\Jmap::getNameSpaceArray() can populate its 'others' key from real data.
 */
class JmapBootstrapSharedAccountsTest extends TestCase
{
	private function jmapWithApiResponse(array $response) : Jmap
	{
		$jmap = $this->getMockBuilder(Jmap::class)
			->disableOriginalConstructor()
			->onlyMethods(['api'])
			->getMock();
		$jmap->method('api')->willReturn($response);
		$ref = new \ReflectionProperty(Jmap::class, 'url');
		$ref->setAccessible(true);
		$ref->setValue($jmap, 'https://stalwart.example.invalid');
		return $jmap;
	}

	private function sessionResponse() : array
	{
		return [
			'accounts' => [
				'ca' => ['name' => 'ralf@example.invalid', 'isPersonal' => true,
					'accountCapabilities' => ['urn:ietf:params:jmap:mail' => []]],
				'u' => ['name' => 'info@example.invalid', 'isPersonal' => false],
				'l' => ['name' => 'birgit@example.invalid', 'isPersonal' => false],
			],
			'capabilities' => [],
			'apiUrl' => 'https://stalwart.example.invalid/jmap/',
			'downloadUrl' => 'https://stalwart.example.invalid/jmap/download/',
			'uploadUrl' => 'https://stalwart.example.invalid/jmap/upload/',
			'eventSourceUrl' => 'https://stalwart.example.invalid/jmap/eventsource/',
		];
	}

	public function testBootstrapStillSetsThePersonalAccountId()
	{
		$jmap = $this->jmapWithApiResponse($this->sessionResponse());
		$accountId = null;

		$jmap->bootstrap(true, $accountId);

		$this->assertSame('ca', $accountId);
		$this->assertArrayHasKey('urn:ietf:params:jmap:mail', $jmap->accountCapabilities);
	}

	public function testBootstrapRetainsEveryNonPersonalAccount()
	{
		$jmap = $this->jmapWithApiResponse($this->sessionResponse());
		$accountId = null;

		$jmap->bootstrap(true, $accountId);

		$this->assertSame(['u', 'l'], array_keys($jmap->sharedAccounts));
		$this->assertSame('info@example.invalid', $jmap->sharedAccounts['u']['name']);
		$this->assertArrayNotHasKey('ca', $jmap->sharedAccounts, 'the personal account must never appear as shared too');
	}

	public function testSharedAccountsIsEmptyArrayNotUnsetWhenThereAreNone()
	{
		$jmap = $this->jmapWithApiResponse([
			'accounts' => ['ca' => ['name' => 'solo@example.invalid', 'isPersonal' => true]],
			'apiUrl' => 'https://stalwart.example.invalid/jmap/',
			'downloadUrl' => 'https://stalwart.example.invalid/jmap/download/',
			'uploadUrl' => 'https://stalwart.example.invalid/jmap/upload/',
			'eventSourceUrl' => 'https://stalwart.example.invalid/jmap/eventsource/',
		]);
		$accountId = null;

		$jmap->bootstrap(true, $accountId);

		$this->assertTrue(isset($jmap->sharedAccounts), 'must read as set (empty array), not throw/warn like the uninitialized accountId case');
		$this->assertSame([], $jmap->sharedAccounts);
	}

	public function testReBootstrapDoesNotLeakStaleSharedAccountsFromAPreviousCall()
	{
		$jmap = $this->getMockBuilder(Jmap::class)
			->disableOriginalConstructor()
			->onlyMethods(['api'])
			->getMock();
		$jmap->method('api')->willReturnOnConsecutiveCalls(
			$this->sessionResponse(),
			[
				'accounts' => ['ca' => ['name' => 'ralf@example.invalid', 'isPersonal' => true]],
				'apiUrl' => 'https://stalwart.example.invalid/jmap/',
				'downloadUrl' => 'https://stalwart.example.invalid/jmap/download/',
				'uploadUrl' => 'https://stalwart.example.invalid/jmap/upload/',
				'eventSourceUrl' => 'https://stalwart.example.invalid/jmap/eventsource/',
			]
		);
		$ref = new \ReflectionProperty(Jmap::class, 'url');
		$ref->setAccessible(true);
		$ref->setValue($jmap, 'https://stalwart.example.invalid');
		$accountId = null;

		$jmap->bootstrap(true, $accountId);
		$this->assertCount(2, $jmap->sharedAccounts);

		// re-bootstrap the SAME instance against a session that no longer has any shared accounts
		$jmap->bootstrap(true, $accountId);

		$this->assertSame([], $jmap->sharedAccounts);
	}
}
