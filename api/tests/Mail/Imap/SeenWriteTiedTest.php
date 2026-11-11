<?php
/**
 * EGroupware Api: Mail\Imap\Jmap::seenWriteTied() only asserts the Seen/Write tie for Stalwart
 *
 * @link https://www.egroupware.org
 * @package api
 * @subpackage mail
 * @license http://opensource.org/licenses/gpl-license.php GPL - GNU General Public License
 */

namespace EGroupware\Api\Mail\Imap;

use EGroupware\Api\Mail\Smtp\Stalwart;

require_once realpath(__DIR__.'/../../AppTest.php');

/**
 * Stalwart ties the IMAP ACL rights 's' (Seen) and 'w' (Write) into a single right (confirmed by
 * Stalwart upstream, see project_mail_jmap_acl_sharing_status memory) - seenWriteTied() hard-codes
 * this for Stalwart specifically, detected via the 'urn:stalwart:jmap' capability, same idiom
 * mailShareSupported() already uses for 'urn:ietf:params:jmap:mail:share'. Confirmed live on
 * boulder.egroupware.org that this capability is account-scoped (accountCapabilities), not
 * session-scoped - a different mail:share-capable JMAP server must NOT be assumed to tie these
 * rights the same way.
 */
class SeenWriteTiedTest extends \EGroupware\Api\AppTest
{
	private function jmapWithCapabilities(array $capabilities) : Jmap
	{
		// onlyMethods([]) - mock nothing, keep every real method (including __get()/__isset(),
		// which the default no-args getMock() would otherwise stub out as dummies always
		// returning null/false, defeating the reflection-set property below entirely)
		$client = $this->getMockBuilder(\EGroupware\Api\Jmap::class)
			->disableOriginalConstructor()
			->onlyMethods([])
			->getMock();
		// __get()/__isset() read the real (protected, typed) $accountCapabilities property -
		// setting it directly via reflection (rather than mocking the magic methods) keeps
		// isset()/?? behaving correctly, see Api\Jmap::__isset()'s own docblock on typed-property
		// pitfalls
		$prop = new \ReflectionProperty(\EGroupware\Api\Jmap::class, 'accountCapabilities');
		$prop->setAccessible(true);
		$prop->setValue($client, $capabilities);

		$jmap = $this->getMockBuilder(Jmap::class)
			->disableOriginalConstructor()
			->onlyMethods(['jmapClient'])
			->getMock();
		$jmap->method('jmapClient')->willReturn($client);
		return $jmap;
	}

	public function testTrueForStalwart()
	{
		$jmap = $this->jmapWithCapabilities([
			Stalwart::USING_STALWART => [],
			'urn:ietf:params:jmap:core' => [],
		]);

		$this->assertTrue($jmap->seenWriteTied());
	}

	public function testFalseForGenericJmapServer()
	{
		$jmap = $this->jmapWithCapabilities([
			'urn:ietf:params:jmap:core' => [],
			'urn:ietf:params:jmap:mail' => [],
		]);

		$this->assertFalse($jmap->seenWriteTied());
	}

	public function testFalseWhenCapabilitiesEmpty()
	{
		$jmap = $this->jmapWithCapabilities([]);

		$this->assertFalse($jmap->seenWriteTied());
	}
}
