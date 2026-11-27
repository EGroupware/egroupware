<?php
/**
 * EGroupware Mail: tests for TNEF/winmail.dat detection in a NESTED (not top-level) body part
 * (ticket #128271)
 *
 * @link http://www.egroupware.org
 * @package mail
 * @license http://opensource.org/licenses/gpl-license.php GPL - GNU General Public License
 */

namespace EGroupware\Mail;

use EGroupware\Api\Mail\Jmap\Imap as JmapShim;

/**
 * Regression tests (ticket #128271, found live 2026-11-27 investigating a report from Birgit/a
 * customer): mail/js/jmap.ts's MailJmap.isSpecialCase() has always been a recursive, depth-first
 * walk of the WHOLE bodyStructure for a winmail.dat/TNEF part - matching Outlook's common
 * multipart/mixed(text/plain, application/ms-tnef) shape, not just the rarer whole-message-is-TNEF
 * case. JmapShim::specialCaseType() only ever checked the TOP-LEVEL type for TNEF, so the client
 * routed this common shape to ajax_resolveSpecialCaseBody() while the server, finding no top-level
 * match, returned null - fine for a classic/Dovecot account (the pre-existing classic IMAP path
 * then renders the sibling text/plain part fine), but fatal for a Stalwart account, which has no
 * classic IMAP fallback and instead hard-fails with "Could not load this message."
 * (MessageDisplayHandler::get_load_email_data()'s Stalwart-only guard, added for an unrelated
 * ticket #127361).
 *
 * Deliberately pure, no Horde_Imap_Client connection needed - specialCaseType()/findTnefPart()
 * only inspect the plain array shape bodyPartToJmap()/Email/get already produce.
 */
class TnefNestedSpecialCaseTest extends \PHPUnit\Framework\TestCase
{
	/** the real-world shape from the ticket: a plain-text fallback alongside the TNEF part */
	private function nestedTnefStructure() : array
	{
		return [
			'type' => 'multipart/mixed',
			'partId' => null,
			'subParts' => [
				['type' => 'text/plain', 'partId' => '1', 'name' => null],
				['type' => 'application/ms-tnef', 'partId' => '2', 'name' => 'winmail.dat'],
			],
		];
	}

	public function testNestedTnefPartIsDetectedAsSpecialCase() : void
	{
		$this->assertSame('tnef', JmapShim::specialCaseType($this->nestedTnefStructure()),
			"a multipart/mixed message with a sibling text/plain part must still be routed to the ".
			"TNEF resolver when it also contains a nested application/ms-tnef part (ticket #128271)");
	}

	public function testFindTnefPartReturnsTheNestedPartNotTheContainer() : void
	{
		$tnefPart = JmapShim::findTnefPart($this->nestedTnefStructure());

		$this->assertNotNull($tnefPart);
		$this->assertSame('2', $tnefPart['partId'],
			"the resolver must be called with the TNEF part's OWN partId, not the surrounding ".
			"multipart/mixed container's (which is meaningless/wrong for a nested match) - this is ".
			"exactly the plumbing bug MessageDisplayHandler::tryJmapNativeSpecialCase() had before ".
			"being fixed to call findTnefPart() instead of reading \$bodyStructure['partId'] directly");
	}

	public function testDeeplyNestedTnefPartIsStillFound() : void
	{
		$structure = [
			'type' => 'multipart/mixed',
			'subParts' => [
				[
					'type' => 'multipart/alternative',
					'subParts' => [
						['type' => 'text/plain', 'partId' => '1.1'],
						['type' => 'application/ms-tnef', 'partId' => '1.2', 'name' => 'winmail.dat'],
					],
				],
			],
		];

		$this->assertSame('tnef', JmapShim::specialCaseType($structure));
		$this->assertSame('1.2', JmapShim::findTnefPart($structure)['partId']);
	}

	public function testWinmailNamedPartIsDetectedEvenWithAGenericContentType() : void
	{
		// some senders/gateways mislabel the TNEF part (eg. application/octet-stream) but keep the
		// conventional filename - mirrors mail/js/jmap.ts's own name-based fallback match
		$structure = [
			'type' => 'multipart/mixed',
			'subParts' => [
				['type' => 'text/plain', 'partId' => '1'],
				['type' => 'application/octet-stream', 'partId' => '2', 'name' => 'Winmail.DAT'],
			],
		];

		$this->assertSame('tnef', JmapShim::specialCaseType($structure));
		$this->assertSame('2', JmapShim::findTnefPart($structure)['partId']);
	}

	/**
	 * The critical negative case: an ordinary multipart/mixed message with a normal text body plus
	 * a normal (non-TNEF) attachment must never be routed through the TNEF special case.
	 */
	public function testMultipartMessageWithOrdinaryAttachmentIsNotTnef() : void
	{
		$structure = [
			'type' => 'multipart/mixed',
			'subParts' => [
				['type' => 'text/plain', 'partId' => '1'],
				['type' => 'application/pdf', 'partId' => '2', 'name' => 'invoice.pdf'],
			],
		];

		$this->assertNull(JmapShim::specialCaseType($structure));
		$this->assertNull(JmapShim::findTnefPart($structure));
	}

	// --- regression guard: the pre-existing whole-message-is-TNEF (top-level) case stays intact --

	public function testTopLevelTnefStillDetected() : void
	{
		$structure = ['type' => 'application/ms-tnef', 'partId' => '1'];

		$this->assertSame('tnef', JmapShim::specialCaseType($structure));
		$this->assertSame('1', JmapShim::findTnefPart($structure)['partId']);
	}
}
