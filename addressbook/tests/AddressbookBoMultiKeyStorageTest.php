<?php
/**
 * Test EGroupware addressbook_bo's per-address PGP/S-MIME key storage helpers
 *
 * @link http://www.egroupware.org
 * @package addressbook
 * @license http://opensource.org/licenses/gpl-license.php GPL - GNU General Public License
 */

/**
 * addressbook_bo::merge_keys_json()/extract_key_for_address()/detect_smime_address() (private
 * static, accessed via reflection like mail's own JmapAttachmentsToLegacyTest.php) are the pure
 * data-transform core of the 2026-09-09 fix for a real bug: set_keys()/get_keys() (shared by both
 * PGP and S/MIME, `$pgp` flag) used to store a contact's key as a single VFS file per CONTACT
 * RECORD - one file, regardless of how many of the contact's addresses (business `email` + home
 * `email_home`) it actually has - so storing a key for one address silently overwrote whatever was
 * stored for the other.
 *
 * The fix stores a small JSON object keyed by lowercased address instead: a value is EITHER the
 * armored key/cert text itself, OR a plain address string naming another entry in the SAME object
 * to use instead - an alias, for when one real-world key legitimately covers more than one address
 * without duplicating the (often multi-KB) text; merge_keys_json() creates these automatically on
 * same-content dedup. A `"*"` entry means "applies to any address" (the pre-fix legacy-file
 * upgrade fallback, and an account-id-only lookup with no specific address).
 *
 * Also covers the follow-up 2026-09-09 rework: legacy (non-JSON) content is migrated using
 * detect_smime_address() (S/MIME only - PGP has no server-side way to read a key's own User IDs,
 * see the production code's own docblocks) to recover which address it actually belongs to instead
 * of always falling back to "*", and a single remaining "*"-only S/MIME entry is cross-checked
 * against the requested address before being returned at all.
 *
 * Deliberately does NOT test the surrounding search()/save()/ACL/VFS plumbing in set_keys()/
 * get_keys() themselves - that requires a full EGroupware bootstrap (Api\AppTest) and, found while
 * writing the original version of this test (2026-09-09), the shared docker PHPUnit environment's
 * own account search hangs indefinitely even for the ORIGINAL, unmodified set_keys() call chain
 * (confirmed by reverting this fix and reproducing the exact same hang) - a pre-existing
 * environment limitation unrelated to this fix, not something to chase down here. Testing the pure
 * functions in isolation (no database/session/IMAP connection required, same reasoning as
 * JmapAttachmentsToLegacyTest.php) gives real coverage of the actual new logic without depending on
 * that.
 */
class AddressbookBoMultiKeyStorageTest extends \PHPUnit\Framework\TestCase
{
	/**
	 * A real self-signed cert (openssl req -x509, 2026-09-09, synthetic *.invalid identity, no
	 * subjectAltName - matches the shape Api\Mail\Smime::generate_certificate() itself produces),
	 * so detect_smime_address() exercises its real subject-DN-emailAddress fallback path against
	 * actual ASN.1 rather than a hand-typed fixture.
	 */
	private const SMIME_CERT_ADDRESS = 'phpunit-multikey@example.invalid';
	private const SMIME_CERT = <<<'CERT'
-----BEGIN CERTIFICATE-----
MIIEAzCCAuugAwIBAgIUeN6F6sBQClgmv6pI/etp+nEyuVEwDQYJKoZIhvcNAQEL
BQAwgZAxCzAJBgNVBAYTAkRFMQ8wDQYDVQQIDAZCZXJsaW4xDzANBgNVBAcMBkJl
cmxpbjETMBEGA1UECgwKRUdyb3Vwd2FyZTEZMBcGA1UEAwwQUEhQVW5pdCBNdWx0
aUtleTEvMC0GCSqGSIb3DQEJARYgcGhwdW5pdC1tdWx0aWtleUBleGFtcGxlLmlu
dmFsaWQwHhcNMjYwOTA5MTQwMzQyWhcNMjYxMDA5MTQwMzQyWjCBkDELMAkGA1UE
BhMCREUxDzANBgNVBAgMBkJlcmxpbjEPMA0GA1UEBwwGQmVybGluMRMwEQYDVQQK
DApFR3JvdXB3YXJlMRkwFwYDVQQDDBBQSFBVbml0IE11bHRpS2V5MS8wLQYJKoZI
hvcNAQkBFiBwaHB1bml0LW11bHRpa2V5QGV4YW1wbGUuaW52YWxpZDCCASIwDQYJ
KoZIhvcNAQEBBQADggEPADCCAQoCggEBAPcgpGhvhfdjiC04R5ITDvXT6GJii6fN
keDbYl8McA4vb1nM5sWaFRoWNuc7cdq1gD8t5HuxFGac3HkR8N2+r2h3CycT1VEP
KzdoR78rIP3xVl4iIwSr30+F4WGNcMacVxfSrtFEgQNDr67g5+OcM+bV+PMkuc8V
he0hcfRC7IlMGhuE0rw9s2qk01Te2C9DdkIKawKmRFUAtmoijfzn8FnssR53k+mT
aUBKwMOV8i9TCp7RiKZxai/6EQgY6Gi9+MaTB96fnaM0tQH9Bjc0vbelJB0Hmiii
Y4yHB7G6elqZvZGX9jMdiCQ07412HBbKWw9tzGixPmt71rQDa+ZttjkCAwEAAaNT
MFEwHQYDVR0OBBYEFPzQ7dVqlZ0gsmAU3uhB7AEiJRV2MB8GA1UdIwQYMBaAFPzQ
7dVqlZ0gsmAU3uhB7AEiJRV2MA8GA1UdEwEB/wQFMAMBAf8wDQYJKoZIhvcNAQEL
BQADggEBAMRN05qG6edjlrpYFP3GnWkJlbs7i5YZXZAYsppXrM51aeJO4/i6UfjF
Md1RMnNXmFrmgqha+w1GDS00hGa3WnyGhJAuwAWeuMoGxrA4wuBiKyJHfgsvKvuk
iNE0SCr1be8/OLKPoB+2qzIihKSJIEyqb2JLLQTjgajzOuHxhAoV9+TbsrMW2rAT
8oAzR30psZ9qQvoyAljPjZIIl+hWukLUPhfV08bYXwEMm+e4YfhV3AX2EqqRrF2F
u3Mo9yka+ar9NDRkU1RX6PJCylSi75TM3TGcmDeM6sxgp1OI4w+NAWHPMZCLNDcg
76xrVYto2Hldin+OSSDDr04avz3m670=
-----END CERTIFICATE-----

CERT;

	private function merge(string $existing, array $newKeysByAddress, bool $pgp=true) : string
	{
		$ref = new ReflectionMethod(addressbook_bo::class, 'merge_keys_json');
		$ref->setAccessible(true);
		return $ref->invoke(null, $existing, $newKeysByAddress, $pgp);
	}

	private function extract(string $content, ?string $address, bool $pgp=true) : ?string
	{
		$ref = new ReflectionMethod(addressbook_bo::class, 'extract_key_for_address');
		$ref->setAccessible(true);
		$key_regexp = $pgp ? addressbook_bo::$pgp_key_regexp : EGroupware\Api\Mail\Smime::$certificate_regexp;
		return $ref->invoke(null, $content, $address, $key_regexp, $pgp);
	}

	private function detectSmimeAddress(string $cert) : ?string
	{
		$ref = new ReflectionMethod(addressbook_bo::class, 'detect_smime_address');
		$ref->setAccessible(true);
		return $ref->invoke(null, $cert);
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
	 * Migration continuity (PGP - no address-detection possible, always upgrades to "*"): merging
	 * a NEW key for one address into pre-fix legacy content must keep the OLD key resolvable for
	 * an address that was never explicitly touched - the "*" fallback entry the legacy content
	 * gets upgraded into. This is the specific "no data loss during the format upgrade" guarantee.
	 */
	public function testMigratingLegacyPgpContentKeepsOldKeyForUntouchedAddress()
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
	 * Migration for S/MIME instead upgrades legacy content into a SPECIFIC address entry (keyed by
	 * whatever detect_smime_address() reads out of the cert's own subject emailAddress), not the
	 * address-unknown "*" fallback - so the cert stays correctly attributed even for an address
	 * that was never explicitly touched by any set_keys() call after the format upgrade.
	 */
	public function testMigratingLegacySmimeContentDetectsItsOwnAddress()
	{
		$otherCert = "-----BEGIN CERTIFICATE-----\nsome-other-cert\n-----END CERTIFICATE-----\n";

		$migrated = $this->merge(self::SMIME_CERT, ['other@example.invalid' => $otherCert], false);

		$this->assertSame(self::SMIME_CERT,
			$this->extract($migrated, self::SMIME_CERT_ADDRESS, false),
			'legacy cert must be resolvable under the address detect_smime_address() read out of it');
		$this->assertSame($otherCert, $this->extract($migrated, 'other@example.invalid', false));
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
	 * extract_key_for_address(), and (new alias-pointer format) store the armored text DIRECTLY as
	 * the address' value - no nested {"key": ...} wrapper.
	 */
	public function testMergeOutputIsValidJsonFlatFormat()
	{
		$merged = $this->merge('', ['a@example.invalid' => $this->pgpKey('key')]);

		$decoded = json_decode($merged, true);
		$this->assertSame(JSON_ERROR_NONE, json_last_error());
		$this->assertSame($this->pgpKey('key'), $decoded['a@example.invalid'] ?? null);
	}

	/**
	 * Same-content dedup: merging the IDENTICAL key/cert text for a second address must not
	 * duplicate the (often multi-KB) text - it becomes a plain address-string alias pointing at
	 * the address that already has it, and both addresses still resolve to the real key content.
	 */
	public function testIdenticalKeyForSecondAddressBecomesAlias()
	{
		$sharedKey = $this->pgpKey('shared-key');

		$merged = $this->merge('', ['business@example.invalid' => $sharedKey]);
		$merged = $this->merge($merged, ['home@example.invalid' => $sharedKey]);

		$decoded = json_decode($merged, true);
		$this->assertSame($sharedKey, $decoded['business@example.invalid']);
		$this->assertSame('business@example.invalid', $decoded['home@example.invalid'],
			'the second address must be stored as a plain alias string, not a duplicate of the key text');
		$this->assertSame($sharedKey, $this->extract($merged, 'home@example.invalid'),
			'resolving the alias must still return the real key content');
	}

	/**
	 * An alias chain (address B aliases to address A, which itself aliases to address ROOT holding
	 * the real key - eg. built up over several merge_keys_json() calls each adding one more shared
	 * address) must be followed all the way through to the real key content.
	 */
	public function testAliasChainIsFollowedToRealKey()
	{
		$sharedKey = $this->pgpKey('shared-key');

		$merged = $this->merge('', ['root@example.invalid' => $sharedKey]);
		$merged = $this->merge($merged, ['a@example.invalid' => $sharedKey]);
		$merged = $this->merge($merged, ['b@example.invalid' => $sharedKey]);

		$this->assertSame($sharedKey, $this->extract($merged, 'b@example.invalid'));
	}

	/**
	 * A malformed/cyclic alias chain (two addresses pointing at each other, never reaching real
	 * key content - not producible via merge_keys_json() itself, but content could have been
	 * hand-edited or corrupted) must return null rather than looping forever.
	 */
	public function testCyclicAliasReturnsNullInsteadOfLooping()
	{
		$cyclic = json_encode(['a@example.invalid' => 'b@example.invalid', 'b@example.invalid' => 'a@example.invalid']);

		$this->assertNull($this->extract($cyclic, 'a@example.invalid'));
	}

	/** detect_smime_address() reads the real cert's subject-DN emailAddress (no SAN present). */
	public function testDetectSmimeAddressReadsSubjectEmailAddress()
	{
		$this->assertSame(self::SMIME_CERT_ADDRESS, $this->detectSmimeAddress(self::SMIME_CERT));
	}

	/** Non-certificate input must not throw, just return null. */
	public function testDetectSmimeAddressReturnsNullForGarbage()
	{
		$this->assertNull($this->detectSmimeAddress('this is not a certificate'));
	}

	/**
	 * Single-key S/MIME cross-check: when the ONLY thing on file for a contact is a "*"
	 * (address-unknown) entry, and the cert's OWN detected address does not match what's being
	 * asked for, extract_key_for_address() must refuse to hand it back at all (null) rather than
	 * offering a key/cert for the wrong address just because it's the only one on file.
	 */
	public function testSingleWildcardSmimeEntryRejectedForMismatchedAddress()
	{
		$content = json_encode(['*' => self::SMIME_CERT]);

		$this->assertNull($this->extract($content, 'someone-else@example.invalid', false),
			'the single stored cert does not claim the requested address - must not be returned');
	}

	/** The same single-"*"-entry cert IS returned when the requested address actually matches. */
	public function testSingleWildcardSmimeEntryReturnedForMatchingAddress()
	{
		$content = json_encode(['*' => self::SMIME_CERT]);

		$this->assertSame(self::SMIME_CERT, $this->extract($content, self::SMIME_CERT_ADDRESS, false));
	}

	/**
	 * The single-key cross-check only applies when there's genuinely nothing else on file (count
	 * === 1) - a "*" fallback alongside OTHER address entries is left alone (that's the normal,
	 * intentional multi-address fallback case tested above), even for an address whose own cert
	 * wouldn't match if checked.
	 */
	public function testWildcardFallbackNotCrossCheckedWhenOtherEntriesExist()
	{
		$otherCert = "-----BEGIN CERTIFICATE-----\nother-cert\n-----END CERTIFICATE-----\n";
		$content = json_encode(['*' => self::SMIME_CERT, 'other@example.invalid' => $otherCert]);

		$this->assertSame(self::SMIME_CERT, $this->extract($content, 'someone-else@example.invalid', false),
			'"*" fallback is used as-is (no cross-check) once more than one entry exists on the contact');
	}

	/**
	 * PGP has no server-side address-detection at all (no gnupg extension/PHP OpenPGP library) -
	 * the single-key cross-check must therefore never apply to a "*" PGP entry, even though the
	 * exact same shape would be rejected for S/MIME above.
	 */
	public function testSingleWildcardPgpEntryNeverCrossChecked()
	{
		$content = json_encode(['*' => $this->pgpKey('only-key')]);

		$this->assertSame($this->pgpKey('only-key'),
			$this->extract($content, 'whoever@example.invalid', true));
	}
}
