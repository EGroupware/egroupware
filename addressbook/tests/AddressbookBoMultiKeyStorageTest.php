<?php
/**
 * Test EGroupware addressbook_bo's per-address PGP/S-MIME key storage helpers
 *
 * @link http://www.egroupware.org
 * @package addressbook
 * @license http://opensource.org/licenses/gpl-license.php GPL - GNU General Public License
 */

/**
 * addressbook_bo::merge_keys_json()/extract_key_for_address() (private static, accessed via
 * reflection like mail's own JmapAttachmentsToLegacyTest.php) are the pure data-transform core of
 * the 2026-09-09 fix for a real bug: set_keys()/get_keys() (shared by both PGP and S/MIME, `$pgp`
 * flag) used to store a contact's key as a single VFS file per CONTACT RECORD - one file,
 * regardless of how many of the contact's addresses (business `email` + home `email_home`) it
 * actually has - so storing a key for one address silently overwrote whatever was stored for the
 * other. The fix stores a small JSON object keyed by lowercased address instead, each holding its
 * own `key`; a `"*"` entry means "applies to any address" (used for the pre-fix legacy-file
 * upgrade path, and for an account-id-only lookup with no specific address).
 *
 * Deliberately does NOT test the surrounding search()/save()/ACL/VFS plumbing in set_keys()/
 * get_keys() themselves - that requires a full EGroupware bootstrap (Api\AppTest) and, found while
 * writing this test (2026-09-09), the shared docker PHPUnit environment's own account search hangs
 * indefinitely even for the ORIGINAL, unmodified set_keys() call chain (confirmed by reverting this
 * fix and reproducing the exact same hang) - a pre-existing environment limitation unrelated to
 * this fix, not something to chase down here. Testing the two pure functions in isolation (no
 * database/session/IMAP connection required, same reasoning as JmapAttachmentsToLegacyTest.php)
 * gives real coverage of the actual new logic without depending on that.
 */
class AddressbookBoMultiKeyStorageTest extends \PHPUnit\Framework\TestCase
{
	private function merge(string $existing, array $newKeysByAddress) : string
	{
		$ref = new ReflectionMethod(addressbook_bo::class, 'merge_keys_json');
		$ref->setAccessible(true);
		return $ref->invoke(null, $existing, $newKeysByAddress);
	}

	private function extract(string $content, ?string $address) : ?string
	{
		$ref = new ReflectionMethod(addressbook_bo::class, 'extract_key_for_address');
		$ref->setAccessible(true);
		return $ref->invoke(null, $content, $address, addressbook_bo::$pgp_key_regexp);
	}

	private function pgpKey(string $label) : string
	{
		return "-----BEGIN PGP PUBLIC KEY BLOCK-----\n$label\n-----END PGP PUBLIC KEY BLOCK-----\n";
	}

	/**
	 * The literal bug this fix closes: storing a key for one address, then a DIFFERENT key for a
	 * second address on the SAME contact, must not lose the first one - both survive, each
	 * retrievable by its own address.
	 */
	public function testDistinctKeysForTwoAddressesBothSurvive()
	{
		$businessKey = $this->pgpKey('business-key');
		$homeKey = $this->pgpKey('home-key');

		$afterBusiness = $this->merge('', ['business@example.invalid' => $businessKey]);
		$afterHome = $this->merge($afterBusiness, ['home@example.invalid' => $homeKey]);

		$this->assertSame($businessKey, $this->extract($afterHome, 'business@example.invalid'),
			'the business address must still have its OWN key after the home address was added');
		$this->assertSame($homeKey, $this->extract($afterHome, 'home@example.invalid'));
	}

	/**
	 * Both addresses' keys can also be merged in via a SINGLE call (mirrors set_keys() being
	 * given both a business and home key for the same contact in one $keys array).
	 */
	public function testBothAddressesInOneMergeCall()
	{
		$businessKey = $this->pgpKey('business-key');
		$homeKey = $this->pgpKey('home-key');

		$merged = $this->merge('', [
			'business@example.invalid' => $businessKey,
			'home@example.invalid' => $homeKey,
		]);

		$this->assertSame($businessKey, $this->extract($merged, 'business@example.invalid'));
		$this->assertSame($homeKey, $this->extract($merged, 'home@example.invalid'));
	}

	/**
	 * Re-storing a key for the SAME address overwrites it - only cross-address clobbering was the
	 * bug, re-storing for the identical address is a deliberate update, unchanged from before.
	 */
	public function testReplacingKeyForSameAddressOverwrites()
	{
		$afterFirst = $this->merge('', ['a@example.invalid' => $this->pgpKey('old-key')]);
		$afterSecond = $this->merge($afterFirst, ['a@example.invalid' => $this->pgpKey('new-key')]);

		$this->assertSame($this->pgpKey('new-key'), $this->extract($afterSecond, 'a@example.invalid'));
	}

	/**
	 * A pre-fix contact - a bare armored key, exactly how set_keys() itself used to store the
	 * whole file - must still be readable for ANY address asked about (today's/pre-fix behaviour:
	 * one key applies to whichever address is asked about). No migration happens on a pure read.
	 */
	public function testLegacyBareArmoredContentReadableForAnyAddress()
	{
		$legacyKey = $this->pgpKey('legacy-key');

		$this->assertSame($legacyKey, $this->extract($legacyKey, 'business@example.invalid'));
		$this->assertSame($legacyKey, $this->extract($legacyKey, 'home@example.invalid'));
		$this->assertSame($legacyKey, $this->extract($legacyKey, null));
	}

	/**
	 * Migration continuity: merging a NEW key for one address into pre-fix legacy content must
	 * keep the OLD key resolvable for an address that was never explicitly touched - the "*"
	 * fallback entry the legacy content gets upgraded into. This is the specific "no data loss
	 * during the format upgrade" guarantee.
	 */
	public function testMigratingLegacyContentKeepsOldKeyForUntouchedAddress()
	{
		$legacyKey = $this->pgpKey('legacy-key');
		$newBusinessKey = $this->pgpKey('new-business-key');

		$migrated = $this->merge($legacyKey, ['business@example.invalid' => $newBusinessKey]);

		$this->assertSame($newBusinessKey, $this->extract($migrated, 'business@example.invalid'),
			'the explicitly-updated address must have the new key');
		$this->assertSame($legacyKey, $this->extract($migrated, 'home@example.invalid'),
			'an address never explicitly touched must still resolve to the old legacy key, not lose it');
		$this->assertSame($legacyKey, $this->extract($migrated, null),
			'the "*" fallback itself must still resolve to the old legacy key too');
	}

	/**
	 * An address-unknown ('*') entry (an account-id-keyed store, or already-migrated legacy
	 * content) is the fallback ONLY when the specific requested address has nothing of its own -
	 * a specific address's own key always wins over "*" once one exists for it.
	 */
	public function testSpecificAddressWinsOverWildcardFallback()
	{
		$wildcardKey = $this->pgpKey('wildcard-key');
		$specificKey = $this->pgpKey('specific-key');

		$merged = $this->merge('', ['*' => $wildcardKey]);
		$merged = $this->merge($merged, ['business@example.invalid' => $specificKey]);

		$this->assertSame($specificKey, $this->extract($merged, 'business@example.invalid'));
		$this->assertSame($wildcardKey, $this->extract($merged, 'home@example.invalid'),
			'an address with no key of its own still falls back to the wildcard entry');
	}

	/**
	 * Extracting for an address that has neither its own entry nor any "*" fallback returns null,
	 * not an exception or the wrong key.
	 */
	public function testNoMatchingKeyReturnsNull()
	{
		$merged = $this->merge('', ['business@example.invalid' => $this->pgpKey('business-key')]);

		$this->assertNull($this->extract($merged, 'someone-else@example.invalid'));
	}

	/**
	 * merge_keys_json()'s own output must always be valid JSON round-trippable by
	 * extract_key_for_address() - a basic sanity check independent of the address-matching logic
	 * above.
	 */
	public function testMergeOutputIsValidJson()
	{
		$merged = $this->merge('', ['a@example.invalid' => $this->pgpKey('key')]);

		$decoded = json_decode($merged, true);
		$this->assertSame(JSON_ERROR_NONE, json_last_error());
		$this->assertSame($this->pgpKey('key'), $decoded['a@example.invalid']['key'] ?? null);
	}
}
