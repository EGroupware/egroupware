<?php
/**
 * EGroupware API: letter avatar name parsing tests
 *
 * @package api
 * @subpackage mail
 * @license http://opensource.org/licenses/gpl-license.php GPL - GNU General Public License
 */

namespace EGroupware\Api\Mail;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * Contract: Avatar::getLavatar() picks the first and last name used for a sender's initials from
 * the words of the display name, and emoji never count as a word.
 *
 * Why it matters: an emoji in front of a sender name ("📦 DHL Paketankündigung") became the first
 * name, so the letter avatar showed a broken character plus one letter instead of two initials.
 *
 * Pass criteria: emoji (incl. joined sequences, flags and keycaps) are skipped wherever they are in
 * the name, names without emoji parse as before, and a name of only emoji falls back to the email.
 *
 * Environment: no database or session.
 */
class AvatarLavatarTest extends TestCase
{
	public static function addresses() : array
	{
		return [
			'plain name'            => ['Ralf Becker <rb@egroupware.org>', 'Ralf', 'Becker'],
			'leading emoji'         => ['📦 DHL Paketankündigung (dhl.de) <noreply@dhl.de>', 'DHL', 'Paketankündigung'],
			'emoji glued to word'   => ['📦DHL Paketankündigung <noreply@dhl.de>', 'DHL', 'Paketankündigung'],
			'emoji between words'   => ['Jane ❤️ Doe <jane@example.org>', 'Jane', 'Doe'],
			'joined emoji sequence' => ['👨‍👩‍👧 Family Office <family@example.org>', 'Family', 'Office'],
			'flag and keycap'       => ['🇩🇪 1️⃣ Erste Bank <bank@example.org>', 'Erste', 'Bank'],
			'title then emoji'      => ['Dr. 👨‍⚕️ Max Muster <max@example.org>', 'Max', 'Muster'],
			'digits are kept'       => ['1und1 Service <service@example.org>', '1und1', 'Service'],
			'only emoji'            => ['🎉🎉 <party.time@example.org>', 'party', 'org'],
		];
	}

	#[DataProvider('addresses')]
	public function testNameParts(string $address, string $fname, string $lname) : void
	{
		$lavatar = Avatar::getLavatar($address);

		$this->assertSame($fname, $lavatar['fname']);
		$this->assertSame($lname, $lavatar['lname']);
	}

	/**
	 * The label is the sender's display name as sent, emoji included
	 */
	public function testLabelKeepsEmoji() : void
	{
		$this->assertSame('📦 DHL Paketankündigung', Avatar::getLavatar('📦 DHL Paketankündigung <noreply@dhl.de>')['label']);
	}
}
