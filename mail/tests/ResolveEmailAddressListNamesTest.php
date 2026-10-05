<?php
/**
 * EGroupware Mail: resolveEmailAddressList() returns the members of a distribution list with their names
 *
 * Ticket #125731/#124811 follow-up: the members were returned as bare addresses, which made it hard to check who a
 * list contains (compose action "Resolve mailing-lists") and sent them without names to the recipients.
 *
 * @link https://www.egroupware.org
 * @package mail
 * @license https://opensource.org/license/gpl-2-0 GPL 2.0+ - GNU General Public License 2.0 or any higher version of your choice
 */

require_once realpath(__DIR__.'/../../api/tests/LoggedInTest.php');

use EGroupware\Api;
use EGroupware\Mail\Compose;

class ResolveEmailAddressListNamesTest extends Api\LoggedInTest
{
	/** @var int[] */
	protected $contact_ids = [];
	protected $list_id;
	protected $contacts;

	protected function setUp() : void
	{
		parent::setUp();
		$this->contacts = new Api\Contacts();
	}

	protected function tearDown() : void
	{
		if ($this->list_id) $this->contacts->delete_list($this->list_id);
		foreach($this->contact_ids as $id)
		{
			$this->contacts->delete($id, false, null, true);
		}
		parent::tearDown();
	}

	protected function contact(array $data) : int
	{
		$contact = $data + ['owner' => $GLOBALS['egw_info']['user']['account_id']];
		$id = $this->contacts->save($contact);
		$this->assertNotEmpty($id, 'contact could not be saved');
		return $this->contact_ids[] = (int)$id;
	}

	/**
	 * Pass criteria: list members come back as "Name <email>" (quoted if needed), a member without a name as bare address,
	 * all other entries unchanged and in front
	 */
	public function testMembersAreReturnedWithTheirNames()
	{
		$uniq = uniqid();
		$ids = [
			$this->contact(['n_given' => 'Jane', 'n_family' => 'Doe'.$uniq, 'email' => "jane.$uniq@example.org"]),
			$this->contact(['n_given' => 'John', 'n_family' => 'Smith, Jr.'.$uniq, 'email' => "john.$uniq@example.org"]),
			$this->contact(['org_name' => 'No Name Org'.$uniq, 'email' => "org.$uniq@example.org"]),
		];
		$this->list_id = $this->contacts->add_list(['list_name' => 'Resolve test '.$uniq], $GLOBALS['egw_info']['user']['account_id']);
		$this->assertNotEmpty($this->list_id, 'list could not be created');
		foreach($ids as $id)
		{
			$this->contacts->add2list($id, $this->list_id);
		}

		$resolved = Compose::resolveEmailAddressList(['first@example.org', '"Team" <'.$this->list_id.'@lists.egroupware.org>']);

		$this->assertSame('first@example.org', $resolved[0], 'other entries stay as they are, in front');
		$members = array_slice($resolved, 1);
		sort($members);
		$this->assertCount(3, $members);
		$this->assertContains("Jane Doe$uniq <jane.$uniq@example.org>", $members);
		$this->assertContains("\"John Smith, Jr.$uniq\" <john.$uniq@example.org>", $members, 'a comma in the name needs quoting');
		$names = array_filter($members, static fn($m) => str_contains($m, "org.$uniq@"));
		$this->assertNotEmpty($names);
		$org = array_values($names)[0];
		// a contact with only an organisation has a full name too (n_fn), it is whatever the addressbook generated, but
		// always a valid address containing the email
		$this->assertMatchesRegularExpression('/(^|<)org\.'.preg_quote($uniq, '/').'@example\.org>?$/', $org);
	}

	/**
	 * Pass criteria: unchanged for plain addresses (no lists)
	 */
	public function testPlainAddressesAreUnchanged()
	{
		$plain = ['a@example.com', 'Name <b@example.com>'];
		$this->assertSame($plain, Compose::resolveEmailAddressList($plain));
	}
}
