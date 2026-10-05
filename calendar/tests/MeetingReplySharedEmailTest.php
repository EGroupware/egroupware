<?php
/**
 * EGroupware calendar: an iCal reply is applied to the participants sharing the sender's email address
 *
 * @link http://www.egroupware.org
 * @package calendar
 * @license http://opensource.org/licenses/gpl-license.php GPL - GNU General Public License
 */

namespace EGroupware\calendar;

require_once realpath(__DIR__.'/../../api/tests/AppTest.php');

use EGroupware\Api;

/**
 * A reply's sender is resolved by an addressbook search, which picks an arbitrary one of several
 * contacts sharing an address and can not see contacts the user has no access to. The reply has
 * to end up on the contact(s) really invited to the event instead.
 */
class MeetingReplySharedEmailTest extends \EGroupware\Api\AppTest
{
	protected $contacts;
	protected $contact_ids = [];

	protected function setUp() : void
	{
		parent::setUp();
		$this->contacts = new Api\Contacts();
	}

	protected function tearDown() : void
	{
		foreach($this->contact_ids as $id)
		{
			$this->contacts->delete($id, false);
		}
		parent::tearDown();
	}

	protected function contact(string $email, string $home='', ?int $owner=null, bool $private=false) : string
	{
		$contact = ['n_given' => 'Shared', 'n_family' => 'Mail '.bin2hex(random_bytes(3)),
			'email' => $email, 'email_home' => $home, 'private' => (int)$private,
			'owner' => $owner ?? $GLOBALS['egw_info']['user']['account_id']];
		$id = $this->contacts->save($contact, true);
		$this->assertNotEmpty($id);
		return 'c'.($this->contact_ids[] = $id);
	}

	protected function sharing(array $participants, $uid, ?int $owner=null) : array
	{
		$method = new \ReflectionMethod(\calendar_uiforms::class, 'participantsSharingEmail');
		$method->setAccessible(true);
		return $method->invoke(new \calendar_uiforms(), [
			'participants' => $participants,
			'owner' => $owner ?? $GLOBALS['egw_info']['user']['account_id'],
		], $uid);
	}

	public function testOnlyInvitedContactOfDuplicatesIsMatched()
	{
		$mail = 'dup-'.bin2hex(random_bytes(4)).'@example.org';
		$invited = $this->contact($mail);
		$other = $this->contact(strtoupper($mail));

		$this->assertSame([$invited], $this->sharing([$invited => 'U'], $other));
		$this->assertSame([$invited], $this->sharing([$invited => 'U'], 'e'.$mail));
		$this->assertSame([$invited], $this->sharing([$invited => 'U'], 'eName <'.$mail.'>'));
	}

	public function testAllInvitedDuplicatesAreMatchedParsedOneFirst()
	{
		$mail = 'dup-'.bin2hex(random_bytes(4)).'@example.org';
		$a = $this->contact($mail);
		$b = $this->contact('', $mail);	// home address

		$this->assertSame([$b, $a], $this->sharing([$a => 'U', $b => 'U'], $b));
		$this->assertSame([$a, $b], $this->sharing([$a => 'U', $b => 'U', 'eother@example.org' => 'U'], $a));
	}

	public function testNoMatchForUnrelatedParticipants()
	{
		$mail = 'dup-'.bin2hex(random_bytes(4)).'@example.org';
		$a = $this->contact('someone-else-'.bin2hex(random_bytes(4)).'@example.org');

		$this->assertSame([], $this->sharing([$a => 'U'], 'e'.$mail));
	}

	/**
	 * A contact the user can not read is only matched, if the user may edit the event owner's calendar
	 */
	public function testHiddenContactNeedsEditRightOnEventOwner()
	{
		$me = (int)$GLOBALS['egw_info']['user']['account_id'];
		$stranger = (int)$GLOBALS['egw']->accounts->name2id($GLOBALS['EGW_ADMIN_USER'] ?? 'sysop');
		if (!$stranger || $stranger === $me)
		{
			$this->markTestSkipped('No second account to own the hidden contact');
		}
		$mail = 'hidden-'.bin2hex(random_bytes(4)).'@example.org';
		$hidden = $this->contact($mail, '', $stranger, true);
		$this->assertFalse($this->contacts->read(substr($hidden, 1)), 'Contact should not be readable for the test user');

		$this->assertSame([$hidden], $this->sharing([$hidden => 'U'], 'e'.$mail, $me));
		$this->assertSame([], $this->sharing([$hidden => 'U'], 'e'.$mail, $stranger),
			'Hidden contact must not be matched without EDIT right on the event owner');
	}
}
