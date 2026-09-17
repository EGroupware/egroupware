<?php
/**
 * EGroupware Api: Jmap's magic __isset() must agree with its own __get()
 *
 * @link https://www.egroupware.org
 * @package api
 * @license http://opensource.org/licenses/gpl-license.php GPL - GNU General Public License
 */

namespace EGroupware\Api;

use PHPUnit\Framework\TestCase;

/**
 * Jmap::__get() makes accountId/accountCapabilities/capabilities/downloadUrl/uploadUrl readable
 * from outside the class (they're declared protected). But Jmap\Base::__isset() - inherited,
 * never overridden here before this fix - only ever checks $this->types (the lazy per-type
 * accessor mechanism, eg. $session->mailbox), which knows nothing about these properties.
 *
 * Consequence: `$jmap->accountCapabilities ?? []` (the ??/isset() operators call __isset() FIRST,
 * and only fall through to __get() if it returns true) always saw "not set" and returned the
 * fallback - even immediately after bootstrap() had genuinely populated the real property.
 *
 * Confirmed live (ticket #124351 follow-up, via temporary diagnostic logging against a real
 * Stalwart account): bootstrap() found the account and set $this->accountCapabilities to a map
 * that genuinely included "urn:ietf:params:jmap:mail:share" - but Mail\Imap\Jmap::
 * mailShareSupported()'s `$this->jmapClient()->accountCapabilities ?? []` still evaluated as
 * empty on that exact same object right afterward, misdetecting real mail-sharing support as
 * absent and falling through to a doomed classic-IMAP ACL attempt (see also
 * JmapNoRawImapFallbackTest.php).
 */
class JmapIssetTest extends TestCase
{
	private function jmapWithProperty(string $property, $value) : Jmap
	{
		$jmap = $this->getMockBuilder(Jmap::class)
			->disableOriginalConstructor()
			->onlyMethods([])
			->getMock();
		$ref = new \ReflectionProperty(Jmap::class, $property);
		$ref->setAccessible(true);
		$ref->setValue($jmap, $value);
		return $jmap;
	}

	public function testAccountCapabilitiesIsSetAfterBeingPopulated()
	{
		$jmap = $this->jmapWithProperty('accountCapabilities', ['urn:ietf:params:jmap:mail:share' => []]);

		$this->assertTrue(isset($jmap->accountCapabilities));
		$this->assertArrayHasKey('urn:ietf:params:jmap:mail:share', $jmap->accountCapabilities ?? []);
	}

	/**
	 * The exact failure mode this bug caused: ?? silently falling back instead of returning the
	 * real, already-populated value.
	 */
	public function testNullCoalescingReturnsTheRealValueNotTheFallback()
	{
		$jmap = $this->jmapWithProperty('accountCapabilities', ['urn:ietf:params:jmap:mail:share' => []]);

		$result = $jmap->accountCapabilities ?? 'FALLBACK-WAS-WRONGLY-USED';

		$this->assertSame(['urn:ietf:params:jmap:mail:share' => []], $result);
	}

	public function testAccountIdIsSetAfterBeingPopulated()
	{
		$jmap = $this->jmapWithProperty('accountId', 'b');

		$this->assertTrue(isset($jmap->accountId));
		$this->assertSame('b', $jmap->accountId ?? 'FALLBACK-WAS-WRONGLY-USED');
	}

	/**
	 * Before bootstrap() ever runs, these properties are genuinely unset (typed, no default) -
	 * isset() must still correctly report false then, not throw and not always report true.
	 */
	public function testUninitializedPropertyIsNotSet()
	{
		$jmap = $this->getMockBuilder(Jmap::class)
			->disableOriginalConstructor()
			->onlyMethods([])
			->getMock();

		$this->assertFalse(isset($jmap->accountCapabilities));
		$this->assertSame('FALLBACK', $jmap->accountCapabilities ?? 'FALLBACK');
	}

	/**
	 * __isset() must still delegate to Base's own $this->types mechanism for anything it doesn't
	 * itself special-case (eg. 'mailbox'/'email') - this fix must not break that.
	 */
	public function testUnrelatedPropertyStillDelegatesToBase()
	{
		$jmap = $this->getMockBuilder(Jmap::class)
			->disableOriginalConstructor()
			->onlyMethods([])
			->getMock();

		$this->assertFalse(isset($jmap->notARealProperty));
	}
}
