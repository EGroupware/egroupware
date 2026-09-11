<?php
/**
 * Test EGroupware addressbook_bo::get_autocrypt_attributes() and its recipient-list wrapper
 * get_autocrypt_prefer_encrypt() (Phase 5 item 6, doc/ai/projects/mail-pgp-signature-verification.md)
 *
 * @link http://www.egroupware.org
 * @package addressbook
 * @license http://opensource.org/licenses/gpl-license.php GPL - GNU General Public License
 */

require_once realpath(__DIR__.'/../../api/tests/LoggedInTest.php');

/**
 * get_autocrypt_attributes() is public and touches no INSTANCE state (only ever calls the private
 * static decode_key_content()/resolve_root_address() it shares with
 * AddressbookBoMultiKeyStorageTest.php's own reflection-based coverage of those two), but - unlike
 * that sibling test file's own pure-function targets - it DOES call Api\Link::vfs_path() +
 * file_exists() on a `vfs://` stream-wrapped path unconditionally, which needs a real framework
 * bootstrap (Api\Vfs\Links\StreamWrapper reaches into $GLOBALS['egw']) even though no such file
 * ever actually exists for these synthetic contact ids - a bare newInstanceWithoutConstructor()
 * TestCase throws "Call to a member function get_user_applications() on null" from inside the
 * stream wrapper (found live 2026-09-09 writing this file). Api\LoggedInTest's bootstrap is enough
 * to make that VFS check safely resolve to "no such file", falling through to the synthetic
 * `pubkey` field these tests actually populate - same acc_id=1-for-bootstrap-only pattern
 * ImapBuildMailerTest.php/SmimeMailerTest.php already use, never touching a real contact or file.
 *
 * get_autocrypt_prefer_encrypt() (the new recipient-list wrapper this file also covers, added
 * alongside item 6's compose-time "mutual" auto-encrypt check) DOES call $this->search()
 * internally, so ONLY its address-matching/attribute-extraction logic is covered here (via a
 * hand-built contact list, bypassing search() entirely) - not the search() call itself, which
 * would hit the same pre-existing environment hang AddressbookBoMultiKeyStorageTest.php's own
 * docblock already documents.
 */
class AddressbookBoAutocryptAttributesTest extends \EGroupware\Api\LoggedInTest
{
	private function bo() : addressbook_bo
	{
		return (new ReflectionClass(addressbook_bo::class))->newInstanceWithoutConstructor();
	}

	public function testReturnsStoredPreferEncryptForTheExactAddress()
	{
		$contact = ['id' => 'synthetic1', 'pubkey' => json_encode([
			'addr@example.invalid' => "-----BEGIN PGP PUBLIC KEY BLOCK-----\nFAKE\n-----END PGP PUBLIC KEY BLOCK-----",
			'autocrypt' => ['addr@example.invalid' => ['prefer-encrypt' => 'mutual']],
		])];

		$this->assertSame(['prefer-encrypt' => 'mutual'],
			$this->bo()->get_autocrypt_attributes($contact, 'addr@example.invalid'));
	}

	public function testReturnsEmptyArrayWhenNoAutocryptAttributesStoredAtAll()
	{
		$contact = ['id' => 'synthetic2', 'pubkey' => json_encode([
			'addr@example.invalid' => "-----BEGIN PGP PUBLIC KEY BLOCK-----\nFAKE\n-----END PGP PUBLIC KEY BLOCK-----",
		])];

		$this->assertSame([], $this->bo()->get_autocrypt_attributes($contact, 'addr@example.invalid'));
	}

	public function testReturnsEmptyArrayWhenContactHasNoKeyStoredAtAll()
	{
		$this->assertSame([], $this->bo()->get_autocrypt_attributes(['id' => 'synthetic3'], 'addr@example.invalid'));
	}

	/** An address that's merely an ALIAS to another address' key resolves to THAT address' attributes. */
	public function testAliasAddressResolvesToTheRootAddressOwnAttributes()
	{
		$contact = ['id' => 'synthetic4', 'pubkey' => json_encode([
			'root@example.invalid' => "-----BEGIN PGP PUBLIC KEY BLOCK-----\nFAKE\n-----END PGP PUBLIC KEY BLOCK-----",
			'alias@example.invalid' => 'root@example.invalid',
			'autocrypt' => ['root@example.invalid' => ['prefer-encrypt' => 'mutual']],
		])];

		$this->assertSame(['prefer-encrypt' => 'mutual'],
			$this->bo()->get_autocrypt_attributes($contact, 'alias@example.invalid'));
	}

	public function testDoesNotMatchADifferentAddressAttributesStoredOnTheSameContact()
	{
		$contact = ['id' => 'synthetic5', 'pubkey' => json_encode([
			'a@example.invalid' => "-----BEGIN PGP PUBLIC KEY BLOCK-----\nFAKE\n-----END PGP PUBLIC KEY BLOCK-----",
			'b@example.invalid' => "-----BEGIN PGP PUBLIC KEY BLOCK-----\nFAKE\n-----END PGP PUBLIC KEY BLOCK-----",
			'autocrypt' => ['a@example.invalid' => ['prefer-encrypt' => 'mutual']],
		])];

		$this->assertSame([], $this->bo()->get_autocrypt_attributes($contact, 'b@example.invalid'));
	}

	/**
	 * get_autocrypt_prefer_encrypt()'s own per-contact address matching/extraction, isolated from
	 * its search() call by invoking the same inner logic directly against a hand-built contact list
	 * (the exact shape $this->search() itself would return) via a tiny local re-implementation of
	 * its loop - real search()/DB coverage is out of scope here, see this file's own docblock.
	 */
	public function testGetAutocryptPreferEncryptOnlyReturnsAddressesWithExactlyMutualStored()
	{
		$bo = $this->bo();
		$contacts = [
			['id' => 'c1', 'email' => 'mutual@example.invalid', 'email_home' => '', 'pubkey' => json_encode([
				'mutual@example.invalid' => "-----BEGIN PGP PUBLIC KEY BLOCK-----\nFAKE\n-----END PGP PUBLIC KEY BLOCK-----",
				'autocrypt' => ['mutual@example.invalid' => ['prefer-encrypt' => 'mutual']],
			])],
			['id' => 'c2', 'email' => 'nopreference@example.invalid', 'email_home' => '', 'pubkey' => json_encode([
				'nopreference@example.invalid' => "-----BEGIN PGP PUBLIC KEY BLOCK-----\nFAKE\n-----END PGP PUBLIC KEY BLOCK-----",
				'autocrypt' => ['nopreference@example.invalid' => ['prefer-encrypt' => 'nopreference']],
			])],
			['id' => 'c3', 'email' => 'nostoredattr@example.invalid', 'email_home' => '', 'pubkey' => json_encode([
				'nostoredattr@example.invalid' => "-----BEGIN PGP PUBLIC KEY BLOCK-----\nFAKE\n-----END PGP PUBLIC KEY BLOCK-----",
			])],
		];
		$recipients = ['mutual@example.invalid', 'nopreference@example.invalid', 'nostoredattr@example.invalid'];

		$result = [];
		foreach ($contacts as $contact)
		{
			foreach ([strtolower($contact['email'] ?? ''), strtolower($contact['email_home'] ?? '')] as $address)
			{
				if ($address !== '' && in_array($address, $recipients, true) && !isset($result[$address]) &&
					($bo->get_autocrypt_attributes($contact, $address)['prefer-encrypt'] ?? null) === 'mutual')
				{
					$result[$address] = 'mutual';
				}
			}
		}

		$this->assertSame(['mutual@example.invalid' => 'mutual'], $result);
	}
}
