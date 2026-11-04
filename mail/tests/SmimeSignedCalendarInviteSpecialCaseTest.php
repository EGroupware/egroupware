<?php
/**
 * EGroupware Mail: ticket #127061 - an S/MIME-signed meeting invitation was shown as a plain text
 * invitation instead of the usual server-rendered Accept/Decline/Tentative buttons.
 *
 * @link http://www.egroupware.org
 * @package mail
 * @license http://opensource.org/licenses/gpl-license.php GPL - GNU General Public License
 */

namespace EGroupware\Mail;

use EGroupware\Api\Mail\Jmap\Imap as JmapShim;

/**
 * JmapShim::specialCaseType()'s `multipart/signed` branch returned 'smime' the instant it found an
 * S/MIME (pkcs7) detached-signature sub-part, without ever checking whether the signed content
 * itself was (or contained) an inline text/calendar part - so a signed meeting invite got routed
 * through resolveSmime()/structureToHtml() (zero text/calendar awareness) instead of being
 * recognized as a meeting invite at all.
 *
 * ticket #127361 (2026-11-04) then found that falling through to null here meant EVERY meeting
 * invite - S/MIME-wrapped or not - silently reached the classic, raw-IMAP-based calendar path
 * (MessageDisplayHandler's own $calendar_part detection), which crashes for a JMAP-only/Stalwart
 * account. specialCaseType() now returns 'calendar' (checked ahead of the smime/tnef/bare checks)
 * for ANY inline text/calendar part, S/MIME- or PGP-wrapped or plain, so
 * Imap\Jmap::resolveCalendarJmap() handles it JMAP-natively instead of falling through.
 *
 * Deliberately pure, no Horde_Imap_Client connection needed - same style as
 * BareContentSpecialCaseTest.php, specialCaseType() only inspects the plain array shape
 * bodyPartToJmap()/Email/get already produce.
 */
class SmimeSignedCalendarInviteSpecialCaseTest extends \PHPUnit\Framework\TestCase
{
	/** multipart/alternative with text/plain + text/html + an inline (non-attachment) text/calendar part - the common meeting-invite shape */
	private function calendarAlternative(array $calendarOverrides = []) : array
	{
		return [
			'type' => 'multipart/alternative',
			'subParts' => [
				['type' => 'text/plain', 'size' => 50],
				['type' => 'text/html', 'size' => 100],
				array_merge(['type' => 'text/calendar', 'size' => 300, 'disposition' => 'inline'], $calendarOverrides),
			],
		];
	}

	public function testSignedMeetingInviteUsesJmapNativeCalendarResolver() : void
	{
		$bodyStructure = [
			'type' => 'multipart/signed',
			'subParts' => [
				$this->calendarAlternative(),
				['type' => 'application/pkcs7-signature'],
			],
		];
		$this->assertSame('calendar', JmapShim::specialCaseType($bodyStructure));
	}

	public function testSignedNonCalendarMessageIsStillDetectedAsSmime() : void
	{
		$bodyStructure = [
			'type' => 'multipart/signed',
			'subParts' => [
				['type' => 'text/plain', 'size' => 50],
				['type' => 'application/pkcs7-signature'],
			],
		];
		$this->assertSame('smime', JmapShim::specialCaseType($bodyStructure));
	}

	/**
	 * An attached .ics file (eg. a travel-booking confirmation's journey file) is a plain
	 * attachment, not a meeting invite - the classic S/MIME handling must still apply, same
	 * inline/attachment distinction as ticket #126491's own client-side carve-out.
	 */
	public function testSignedMessageWithOnlyAnAttachedIcsIsStillDetectedAsSmime() : void
	{
		$bodyStructure = [
			'type' => 'multipart/signed',
			'subParts' => [
				[
					'type' => 'multipart/mixed',
					'subParts' => [
						['type' => 'text/plain', 'size' => 50],
						['type' => 'text/calendar', 'size' => 300, 'disposition' => 'attachment', 'name' => 'invite.ics'],
					],
				],
				['type' => 'application/pkcs7-signature'],
			],
		];
		$this->assertSame('smime', JmapShim::specialCaseType($bodyStructure));
	}

	public function testSignedMessageWithEmptyCalendarPartIsStillDetectedAsSmime() : void
	{
		// size 0 - same "not a real invite" exclusion as an attached one, see hasInlineCalendarPart()
		$bodyStructure = [
			'type' => 'multipart/signed',
			'subParts' => [
				$this->calendarAlternative(['size' => 0]),
				['type' => 'application/pkcs7-signature'],
			],
		];
		$this->assertSame('smime', JmapShim::specialCaseType($bodyStructure));
	}

	public function testPgpSignedMeetingInviteAlsoUsesJmapNativeCalendarResolver() : void
	{
		// not S/MIME at all (no pkcs7 sub-part) - the calendar check (ticket #127361) runs ahead of
		// and independently from the S/MIME-specific multipart/signed branch, so this is unaffected
		// by which signature scheme wraps it
		$bodyStructure = [
			'type' => 'multipart/signed',
			'subParts' => [
				$this->calendarAlternative(),
				['type' => 'application/pgp-signature'],
			],
		];
		$this->assertSame('calendar', JmapShim::specialCaseType($bodyStructure));
	}
}
