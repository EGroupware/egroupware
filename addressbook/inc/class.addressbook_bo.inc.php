<?php
/**
 * EGroupware addressbook: Contacts
 *
 * @link http://www.egroupware.org
 * @author Cornelius Weiss <egw@von-und-zu-weiss.de>
 * @author Ralf Becker <RalfBecker-AT-outdoor-training.de>
 * @author Joerg Lehrke <jlehrke@noc.de>
 * @package addressbook
 * @copyright (c) 2005-16 by Ralf Becker <RalfBecker-AT-outdoor-training.de>
 * @copyright (c) 2005/6 by Cornelius Weiss <egw@von-und-zu-weiss.de>
 * @license http://opensource.org/licenses/gpl-license.php GPL - GNU General Public License
 * @version $Id$
 */

use EGroupware\Api;
use EGroupware\Api\Acl;

/**
 * Business object for addressbook
 *
 * Currently this only contains PGP stuff, which needs to be called via Ajax
 */
class addressbook_bo extends Api\Contacts
{
	static public $pgp_key_regexp = '/-----BEGIN PGP PUBLIC KEY BLOCK-----.*-----END PGP PUBLIC KEY BLOCK-----\r?\n?/s';

	/**
	 * Search addressbook for PGP public keys of given recipients
	 *
	 * EMail addresses are lowercased to make search case-insensitive
	 *
	 * @param string|int|array $recipients (array of) email addresses or numeric account-ids
	 * @return array email|account_id => key pairs
	 */
	public function get_pgp_keys($recipients)
	{
		return $this->get_keys($recipients, true);
	}

	/**
	 * Keyserver URL and CA to verify ssl connection
	 */
	const KEYSERVER = 'https://hkps.pool.sks-keyservers.net/pks/lookup?op=get&exact=on&search=';
	const KEYSERVER_CA = '/addressbook/doc/sks-keyservers.netCA.pem';

	/**
	 * Search keyserver for PGP public keys
	 *
	 * @param int|string|array $recipients (array of) email addresses or numeric account-ids
	 * @param array $result =array()
	 */
	public static function get_pgp_keyserver($recipients, array $result=array())
	{
		foreach($recipients as $recipient)
		{
			$id = $recipient;
			if (is_numeric($recipient))
			{
				$recipient = $GLOBALS['egw']->accounts->id2name($recipient, 'account_email');
			}
			$matches = null;
			if (($response = file_get_contents(self::KEYSERVER.urlencode($recipient), false, stream_context_create(array(
					'ssl' => array(
						'verify_peer' => true,
						'cafile' => EGW_SERVER_ROOT.self::KEYSERVER_CA,
					)
				)))) && preg_match(self::$pgp_key_regexp, $response, $matches))
			{
				$result[$id] = $matches[0];
			}
		}
		return $result;
	}

	/**
	 * Search addressbook for PGP public keys of given recipients
	 *
	 * EMail addresses are lowercased to make search case-insensitive
	 *
	 * @param string|int|array $recipients (array of) email addresses or numeric account-ids
	 * @return array email|account_id => key pairs
	 */
	public function ajax_get_pgp_keys($recipients)
	{
		if (!$recipients) return array();

		if (!is_array($recipients)) $recipients = array($recipients);

		$result = $this->get_pgp_keys($recipients);

		if (($missing = array_diff($recipients, array_keys($result))))
		{
			$result = self::get_pgp_keyserver($missing, $result);
		}
		//error_log(__METHOD__."(".array2string($recipients).") returning ".array2string($result));
		Api\Json\Response::get()->data($result);
	}

	/**
	 * Set PGP keys for given email or account_id, if user has necessary rights
	 *
	 * @param array $keys email|account_id => public key pairs to store
	 * @param boolean $allow_user_updates =null for admins, set config to allow regular users to store their pgp key
	 * @return int number of pgp keys stored
	 */
	public function ajax_set_pgp_keys($keys, $allow_user_updates=null)
	{
		$message = $this->set_keys($keys, true, $allow_user_updates);
		// add all keys to public keyserver too
		$message .= "\n".lang('%1 key(s) added to public keyserver "%2".',
			self::set_pgp_keyserver($keys), PARSE_URL(self::KEYSERVER_ADD, PHP_URL_HOST));

		Api\Json\Response::get()->data($message);
	}

	/**
	 * Keyserver add URL
	 */
	const KEYSERVER_ADD = 'https://hkps.pool.sks-keyservers.net/pks/add';

	/**
	 * Upload PGP keys to public keyserver
	 *
	 * @param array $keys email|account_id => public key pairs to store
	 * @return int number of pgp keys stored
	 */
	public static function set_pgp_keyserver($keys)
	{
		$added = 0;
		foreach($keys as $email => $cert)
		{
			if (is_numeric($email))
			{
				$email = $GLOBALS['egw']->accounts->id2name($email, 'account_email');
			}
			if (($response = file_get_contents(self::KEYSERVER_ADD, false, stream_context_create(array(
					'ssl' => array(
						'verify_peer' => true,
						'cafile' => EGW_SERVER_ROOT.self::KEYSERVER_CA,
					),
					'http' => array(
						'header'  => "Content-type: text/plain",
						'method'  => 'POST',
						'content' => http_build_query(array(
							'keytext' => $cert,
						)),
					),
				)))))
			{
				$added++;
			}
		}
		return $added;
	}

	/**
	 * Where to store public key depending on type and storage backend
	 *
	 * @param boolean $pgp true: PGP, false: S/Mime
	 * @param ?array $contact =null contact array to pass to get_backend()
	 * @return boolean true: store as file, false: store with contact
	 */
	public function pubkey_use_file($pgp, ?array $contact=null)
	{
		return $pgp || empty($contact) || get_class($this->get_backend($contact)) == 'EGroupware\\Api\\Contacts\\Sql';
	}

	/**
	 * Set keys for given email or account_id and key type based on regexp (SMIME or PGP), if user has necessary rights
	 *
	 * Storage is per-address, not per-contact-record: a contact with a business AND a home email,
	 * each using their own real-world key, can now store both without one clobbering the other -
	 * confirmed live 2026-09-09 this was NOT the case before (a single VFS file per contact,
	 * regardless of how many of its addresses get_keys() already searched). The one VFS file
	 * (Api\Contacts::FILES_PGP_PUBKEY/FILES_SMIME_PUBKEY, unchanged path/ACL/backend-dispatch via
	 * pubkey_use_file()) now holds a JSON object keyed by lowercased address, eg.:
	 *   {"rb@egroupware.org": "-----BEGIN PGP PUBLIC KEY BLOCK-----...",
	 *    "ralfbecker@outdoor-training.de": "rb@egroupware.org",
	 *    "autocrypt": {"rb@egroupware.org": {"prefer-encrypt": "mutual"}}}
	 * - a value is EITHER the armored key/cert text itself, OR (ralf's own proposed shape,
	 * 2026-09-09) a plain address string naming another entry in the SAME object to use instead -
	 * an alias, for when the identical key/cert legitimately covers more than one of a contact's
	 * addresses, without duplicating the (often multi-KB) key text once per address. See
	 * merge_keys_json() (which also creates these aliases automatically - see its own docblock)/
	 * extract_key_for_address() for the exact format and the legacy-file (pre-fix, bare armored
	 * text with no address distinction at all, still read by get_key() below) backward-
	 * compatibility fallback.
	 *
	 * `"autocrypt"` (PGP only - ralf's own addition, 2026-09-09, "sorry I forgot about the
	 * Autocrypt attributes") is a RESERVED top-level key, never a real address (a real address
	 * always contains "@", or is the literal "*" fallback) - it holds Autocrypt
	 * (https://docs.autocrypt.org/level1.html) per-address attributes like `prefer-encrypt`, keyed
	 * ONLY by a "main"/root address that directly holds armored key text, never by an address
	 * that's merely an ALIAS to another address' key - see get_autocrypt_attributes()/
	 * set_autocrypt_attributes()/resolve_root_address() for how these are read/written/resolved.
	 *
	 * @param array $keys email|account_id => public key pairs to store
	 * @param boolean $pgp true: PGP, false: S/Mime
	 * @param boolean $allow_user_updates = null for admins, set config to allow regular users to store their key
	 *
	 * @return string message of the update operation result
	 */
	public function set_keys ($keys, $pgp, $allow_user_updates = null)
	{
		if (isset($allow_user_updates) && isset($GLOBALS['egw_info']['user']['apps']['admin']))
		{
			$update = false;
			if ($allow_user_updates && !in_array('pubkey', $this->own_account_acl))
			{
				$this->own_account_acl[] = 'pubkey';
				$update = true;
			}
			elseif (!$allow_user_updates && ($key = array_search('pubkey', $this->own_account_acl)) !== false)
			{
				unset($this->own_account_acl[$key]);
				$update = true;
			}
			if ($update)
			{
				Api\Config::save_value('own_account_acl', $this->own_account_acl, 'phpgwapi');
			}
		}

		$key_regexp = $pgp ? self::$pgp_key_regexp : Api\Mail\Smime::$certificate_regexp;
		$file = $pgp ? Api\Contacts::FILES_PGP_PUBKEY : Api\Contacts::FILES_SMIME_PUBKEY;

		$criteria = array();
		foreach($keys as $recipient => $key)
		{
			if (!preg_match($key_regexp, $key))
			{
				return lang('File is not a %1 public key!', $pgp ? lang('PGP') : lang('S/MIME'));
			}

			if (is_numeric($recipient))
			{
				$criteria['egw_addressbook.account_id'][] = (int)$recipient;
			}
			else
			{
				// search BOTH addresses - previously only contact_email (primary), silently never
				// finding a contact whose matching address was ONLY their email_home
				$criteria['contact_email_home'][] = $criteria['contact_email'][] = strtolower($recipient);
			}
		}
		if (!$criteria) return 0;

		$updated = 0;
		$filters = array(null);
		// if accounts-backend is NOT SQL, we need to search the accounts separate
		if ($this->so_accounts)
		{
			$filters[] = array('owner' => '0');
		}
		foreach($filters as $filter)
		{
			foreach((array)$this->search($criteria, false, '', '', '', false, 'OR', false, $filter) as $contact)
			{
				// every (address => key) pair from $keys that actually applies to THIS contact -
				// a single set_keys() call can carry keys for BOTH of a contact's addresses at
				// once, each stored under its own address instead of one clobbering the other
				$forThisContact = [];
				if ($contact['account_id'] && isset($keys[$contact['account_id']]))
				{
					// no specific address known for an account-id-keyed entry - store as the
					// address-unknown fallback, same meaning as an upgraded legacy file
					$forThisContact['*'] = $keys[$contact['account_id']];
				}
				foreach (['email', 'email_home'] as $field)
				{
					$address = strtolower($contact[$field] ?? '');
					if ($address !== '' && isset($keys[$address]))
					{
						$forThisContact[$address] = $keys[$address];
					}
				}
				if (!$forThisContact) continue;

				// key is stored in file for sql backend or allways for pgp key
				$path = $this->key_storage_path($contact, $pgp);
				if ($path)
				{
					$contact['files'] |= $pgp ? self::FILES_BIT_PGP_PUBKEY : self::FILES_BIT_SMIME_PUBKEY;
					$existing = file_exists($path) ? file_get_contents($path) : '';
					// remove evtl. existing legacy (non-JSON) pubkey from the CONTACT field too -
					// file storage and the pubkey field are never both populated for the same key
					if (preg_match($key_regexp, $contact['pubkey']))
					{
						$contact['pubkey'] = preg_replace($key_regexp, '', $contact['pubkey']);
					}
					$newContent = self::merge_keys_json($existing, $forThisContact, $pgp);
					$updated++;
				}
				else
				{
					$existing = $contact['pubkey'] ?? '';
					$newContent = self::merge_keys_json($existing, $forThisContact, $pgp);
					$contact['pubkey'] = $newContent;
				}
				$contact['photo_unchanged'] = true;	// otherwise photo will be lost, because $contact['jpegphoto'] is not set
				if ($this->check_perms(Acl::EDIT, $contact) && $this->save($contact))
				{
					if ($path)
					{
						if ($this->write_key_file($path, $newContent)) ++$updated;
					}
					else
					{
						++$updated;
					}
				}
			}
		}
		if ($criteria == array('egw.addressbook.account_id' => array((int)$GLOBALS['egw_info']['user']['account_id'])))
		{
			$message = !$updated ? lang('Permissiong denied! Ask your administrator to allow regular uses to update their public keys.') :
				lang('Your new public key has been stored in accounts addressbook.');
		}
		else
		{
			$message = !$updated ? false: lang('%1 public keys added.', $updated);
		}
		return $message;
	}

	/**
	 * VFS path a contact's key/cert content is stored under, or null if this contact/backend
	 * stores it in the `pubkey` DB/LDAP/AD field instead (see pubkey_use_file()) - factored out of
	 * set_keys() so set_autocrypt_attributes() can locate/update the SAME storage without
	 * duplicating the file-vs-field decision.
	 *
	 * @param array $contact
	 * @param bool $pgp true: PGP, false: S/MIME
	 * @return ?string
	 */
	private function key_storage_path(array $contact, bool $pgp) : ?string
	{
		if (!$contact['id'] || !$this->pubkey_use_file($pgp, $contact))
		{
			return null;
		}
		return Api\Link::vfs_path('addressbook', $contact['id'],
			$pgp ? Api\Contacts::FILES_PGP_PUBKEY : Api\Contacts::FILES_SMIME_PUBKEY);
	}

	/**
	 * Write new key/cert JSON content to the given VFS path, run as root like set_keys() itself
	 * does (check_perms()/save() already checked ACL before this is called; access via own-account
	 * ACL alone otherwise can't write the VFS file) - see set_keys()'s own comment for why the
	 * `.files/` directory guard is needed (a contact that never had ANY file attached before has no
	 * such directory yet, and file_put_contents() can't create it implicitly).
	 *
	 * @param string $path
	 * @param string $content
	 * @return bool
	 */
	private function write_key_file(string $path, string $content) : bool
	{
		$backup = Api\Vfs::$is_root; Api\Vfs::$is_root = true;
		if (!Api\Vfs::is_dir($dir = dirname($path)))
		{
			Api\Vfs::mkdir($dir, 0700, true);
		}
		$ok = (bool)file_put_contents($path, $content);
		Api\Vfs::$is_root = $backup;
		return $ok;
	}

	/**
	 * Decode a contact's stored key content into the per-address array shape, migrating legacy
	 * (pre-fix, bare-armored-text) content into it first if it isn't already JSON - see
	 * merge_keys_json()'s own docblock for the exact migration rule (S/MIME: keyed by whatever
	 * detect_smime_address() reads out of it, falling back to `"*"`; PGP: always `"*"`, no
	 * server-side address detection possible). Shared by merge_keys_json() (write side) and
	 * get_autocrypt_attributes()/set_autocrypt_attributes() (read side), so both agree on exactly
	 * where an existing key/cert ends up.
	 *
	 * @param string $existing current file/pubkey-field content, '' if nothing stored yet
	 * @param bool $pgp true: PGP (no legacy-address-detection possible), false: S/MIME
	 * @return array
	 */
	private static function decode_key_content(string $existing, bool $pgp) : array
	{
		$decoded = $existing !== '' ? json_decode($existing, true) : null;
		if (is_array($decoded) && json_last_error() === JSON_ERROR_NONE)
		{
			return $decoded;
		}
		if ($existing === '')
		{
			return [];
		}
		$detectedAddress = !$pgp ? self::detect_smime_address($existing) : null;
		return [($detectedAddress ?? '*') => $existing];
	}

	/**
	 * Merge new (address => armored key) pairs, and/or (PGP only) new Autocrypt attributes, into a
	 * contact's existing stored key content.
	 *
	 * $existing is decoded (and legacy content migrated) via decode_key_content() - see its own
	 * docblock. A value is EITHER the armored key/cert text itself, OR a plain address string
	 * naming another entry in the SAME object to use instead - an alias, see set_keys()'s own
	 * docblock for the full format. An address NOT explicitly being updated by this call keeps
	 * resolving to whatever entry it already had (get_key()'s own one key applied to whichever
	 * address was asked about). Addresses that ARE being updated this call always overwrite
	 * whatever was there for that specific address (new key replaces old, no history).
	 *
	 * Same-content dedup: when the exact armored text being stored for $address is ALREADY present
	 * under a different address, the new entry becomes an alias to that address instead of
	 * duplicating the (often multi-KB) key/cert text again - the common case of one real-world key
	 * legitimately covering more than one of a contact's addresses.
	 *
	 * Autocrypt attributes (`$autocryptByAddress`, PGP only - silently ignored for S/MIME, which
	 * has no equivalent concept) attach to whichever address ALREADY holds the real key content for
	 * $address after the address-key merge above (resolve_root_address()) - never created
	 * standalone without a key: an address with no resolvable key gets no `"autocrypt"` entry at
	 * all, since these attributes describe a property of an EXISTING key/identity, not a reason on
	 * their own to invent one. Merged (not replaced) into whatever attributes that address already
	 * had, so eg. setting `prefer-encrypt` doesn't wipe out an unrelated attribute set earlier.
	 *
	 * @param string $existing current file/pubkey-field content, '' if nothing stored yet
	 * @param array $newKeysByAddress lowercased-address (or '*') => armored key/cert pairs to add/overwrite
	 * @param bool $pgp true: PGP, false: S/MIME
	 * @param array $autocryptByAddress lowercased-address => [attribute => value, ...] pairs to
	 *  merge, eg. ['rb@x.com' => ['prefer-encrypt' => 'mutual']] - PGP only
	 * @return string new JSON content to store
	 */
	private static function merge_keys_json(string $existing, array $newKeysByAddress, bool $pgp,
		array $autocryptByAddress=[]) : string
	{
		$decoded = self::decode_key_content($existing, $pgp);
		foreach ($newKeysByAddress as $address => $key)
		{
			$existingAddress = array_search($key, $decoded, true);
			$decoded[$address] = $existingAddress !== false && $existingAddress !== $address ?
				$existingAddress : $key;
		}
		if ($pgp)
		{
			foreach ($autocryptByAddress as $address => $attributes)
			{
				$root = self::resolve_root_address($decoded, strtolower($address), self::$pgp_key_regexp);
				if ($root !== null)
				{
					$decoded['autocrypt'][$root] = array_merge($decoded['autocrypt'][$root] ?? [], $attributes);
				}
			}
		}
		return json_encode($decoded);
	}

	/**
	 * Try to determine which email address an S/MIME certificate itself claims to belong to -
	 * checked via openssl_x509_parse(), subjectAltName (RFC 5280's preferred, multi-value location
	 * for an email SAN - "email:foo@example.org", possibly comma-separated with other SAN types)
	 * first, falling back to the legacy subject DN emailAddress attribute. Used both to upgrade a
	 * legacy (pre-fix, address-unaware) stored cert into the new per-address JSON shape without
	 * losing which address it actually belongs to (merge_keys_json()) and to cross-check a
	 * caller-supplied address against a contact's single stored key (extract_key_for_address()).
	 *
	 * PGP has NO equivalent here - there's no gnupg extension or PHP OpenPGP library anywhere in
	 * this stack, and armored PGP text is base64-encoded binary, so a key's own User ID isn't
	 * readable server-side at all (client-side JS/openpgp.js can do this, see MailJmap.
	 * keyClaimsAddress() in mail/js/jmap.ts, but that's a different, browser-only context).
	 *
	 * @param string $cert PEM-encoded certificate text
	 * @return ?string lowercased email address, or null if none could be determined
	 */
	private static function detect_smime_address(string $cert) : ?string
	{
		if (!($parsed = @openssl_x509_parse($cert)))
		{
			return null;
		}
		$matches = null;
		if (!empty($parsed['extensions']['subjectAltName']) &&
			preg_match('/email:([^,\s]+)/i', $parsed['extensions']['subjectAltName'], $matches))
		{
			return strtolower($matches[1]);
		}
		if (!empty($parsed['subject']['emailAddress']))
		{
			return strtolower(is_array($parsed['subject']['emailAddress']) ?
				reset($parsed['subject']['emailAddress']) : $parsed['subject']['emailAddress']);
		}
		return null;
	}

	/**
	 * Resolve which address, within an already-decoded per-address key/cert JSON object, actually
	 * holds the real armored key/cert text for a given requested address - following any alias
	 * chain (a value that's itself a plain address string, not armored key/cert text - see
	 * set_keys()'s own docblock for the format) to whatever address it ultimately points at,
	 * bounded by $seen against a malformed/cyclic chain. Falls back to the `"*"` (address-unknown)
	 * entry when the requested address has no entry of its own. Never resolves through the
	 * reserved `"autocrypt"` attributes object (a real address always contains "@", or is the
	 * literal "*").
	 *
	 * @param array $decoded
	 * @param ?string $address lowercased address to look up, or null
	 * @param string $key_regexp what an entry's value must match to count as real key/cert text,
	 *  rather than an alias pointing elsewhere
	 * @return ?string the resolved address (a real key of $decoded whose value is $key_regexp-
	 *  matching key/cert text), or null if nothing resolves
	 */
	private static function resolve_root_address(array $decoded, ?string $address, string $key_regexp) : ?string
	{
		$lookup = $address !== null && array_key_exists($address, $decoded) ?
			$address : (array_key_exists('*', $decoded) ? '*' : null);
		$seen = [];
		while ($lookup !== null && $lookup !== 'autocrypt' && !isset($seen[$lookup]) && array_key_exists($lookup, $decoded))
		{
			$seen[$lookup] = true;
			$value = $decoded[$lookup];
			if (is_string($value) && preg_match($key_regexp, $value))
			{
				return $lookup;
			}
			// not itself an armored key/cert - treat as an alias pointing to another address entry
			$lookup = is_string($value) ? strtolower($value) : null;
		}
		return null;
	}

	/**
	 * Search addressbook for keys of given recipients
	 *
	 * EMail addresses are lowercased to make search case-insensitive
	 *
	 * @param string|int|array $recipients (array of) email addresses or numeric account-ids or "contact:$id" for contacts by id
	 * @param boolean $pgp true: PGP, false: S/Mime public keys
	 * @return array email|account_id => key pairs
	 */
	protected function get_keys ($recipients, $pgp)
	{
		if (!$recipients) return array();

		if (!is_array($recipients)) $recipients = array($recipients);

		$criteria = $result = array();
		foreach($recipients as &$recipient)
		{
			if (is_numeric($recipient))
			{
				$criteria['egw_addressbook.account_id'][] = (int)$recipient;
			}
			else
			{
				$criteria['contact_email_home'][] = $criteria['contact_email'][] = $recipient = strtolower($recipient);
			}
		}
		$filters = array(null);
		// if accounts-backend is NOT SQL, we need to search the accounts separate
		if ($this->so_accounts)
		{
			$filters[] = array('owner' => '0');
		}
		foreach ($filters as $filter)
		{
			foreach((array)$this->search($criteria, array('account_id', 'contact_email', 'contact_email_home', 'contact_pubkey', 'contact_id'),
				'', '', '', false, 'OR', false, $filter) as $contact)
			{
				$email = strtolower($contact['email'] ?? '');
				$emailHome = strtolower($contact['email_home'] ?? '');

				if (empty($criteria['account_id']) || in_array($email, $recipients) || in_array($emailHome, $recipients))
				{
					// return EACH of this contact's addresses that was actually asked about, with
					// its OWN key - a contact with both a business and home address asked about
					// together, each having a different stored key, must return both correctly
					// (a single "one key per contact" result, like before this fix, would have
					// silently dropped one of them)
					foreach ([$email, $emailHome] as $address)
					{
						if ($address !== '' && in_array($address, $recipients) && !isset($result[$address]) &&
							($content = $this->get_key($contact, $pgp, $address)))
						{
							$result[$address] = $content;
						}
					}
				}
				elseif (($content = $this->get_key($contact, $pgp, $email)))
				{
					$result[$contact['account_id']] = $content;
				}
			}
		}
		return $result;
	}

	/**
	 * Extract PGP or S/Mime pubkey from contact array, for a specific address
	 *
	 * @param array $contact
	 * @param boolean $pgp
	 * @param ?string $address lowercased address the key is wanted for - a contact's per-address
	 *  stored key wins if present, falling back to the "*" (address-unknown/legacy) entry if not;
	 *  null behaves like the "*"-only lookup (used for the account-id-keyed case, where there's no
	 *  single specific address to prefer)
	 * @return string pubkey or NULL
	 */
	function get_key(array $contact, $pgp, ?string $address=null)
	{
		if ($pgp)
		{
			$key_regexp = self::$pgp_key_regexp;
			$file = Api\Contacts::FILES_PGP_PUBKEY;
		}
		else
		{
			$key_regexp = Api\Mail\Smime::$certificate_regexp;
			$file = Api\Contacts::FILES_SMIME_PUBKEY;
		}
		// first check for file and second for pubkey field (LDAP, AD or old SQL)
		$content = null;
		if (file_exists($path = Api\Link::vfs_path('addressbook', $contact['id'], $file)))
		{
			$content = file_get_contents($path) ?: null;
		}
		if (!$content && !empty($contact['pubkey']))
		{
			$content = $contact['pubkey'];
		}
		if (!$content) return null;

		return self::extract_key_for_address($content, $address, $key_regexp, $pgp);
	}

	/**
	 * Get stored Autocrypt attributes (eg. `prefer-encrypt`) for a contact's PGP key at a given
	 * address.
	 *
	 * Autocrypt (https://docs.autocrypt.org/level1.html) attributes are stored under a reserved
	 * top-level `"autocrypt"` object in the same per-address JSON this class already uses for keys
	 * (see set_keys()'s own docblock for the full format), keyed ONLY by the "main"/root address
	 * that directly holds the armored key text - an address that's merely an ALIAS to another
	 * address' key resolves to THAT address' attributes, not a separate copy, since `prefer-encrypt`
	 * etc. describe the key/identity, not the alias pointer.
	 *
	 * PGP only - Autocrypt is an OpenPGP/MIME-specific mechanism, S/MIME has no equivalent concept.
	 *
	 * @param array $contact
	 * @param ?string $address lowercased address to look up, or null
	 * @return array attribute-name => value pairs, eg. ['prefer-encrypt' => 'mutual'] - empty if none stored
	 */
	public function get_autocrypt_attributes(array $contact, ?string $address) : array
	{
		$content = null;
		if (file_exists($path = Api\Link::vfs_path('addressbook', $contact['id'], Api\Contacts::FILES_PGP_PUBKEY)))
		{
			$content = file_get_contents($path) ?: null;
		}
		if (!$content && !empty($contact['pubkey']))
		{
			$content = $contact['pubkey'];
		}
		if (!$content) return [];

		$decoded = self::decode_key_content($content, true);
		if (empty($decoded['autocrypt']))
		{
			return [];
		}
		$root = self::resolve_root_address($decoded, $address, self::$pgp_key_regexp);
		return $root !== null ? ($decoded['autocrypt'][$root] ?? []) : [];
	}

	/**
	 * Search addressbook for the `prefer-encrypt` Autocrypt attribute of given recipients' own
	 * stored PGP keys - Phase 5 item 6's recipient-side check for the "mutual" auto-encrypt
	 * preference (doc/ai/projects/mail-pgp-signature-verification.md): does a compose recipient's
	 * OWN key already carry `prefer-encrypt=mutual` (learned from a previous Autocrypt header, or
	 * set explicitly)?
	 *
	 * Same contact/address search shape as get_keys() (business `email` vs home `email_home`, an
	 * account owning BOTH addresses only counted once each) - mapped through
	 * get_autocrypt_attributes() per found contact instead of get_key(). PGP only, Autocrypt
	 * attributes are a PGP-only concept (S/MIME has no equivalent).
	 *
	 * @param string|array $recipients email addresses
	 * @return array address => 'mutual' pairs - an address is present ONLY when its stored value is
	 *  exactly 'mutual' (never 'nopreference' or any other value, and never absent-but-present)
	 */
	public function get_autocrypt_prefer_encrypt($recipients) : array
	{
		if (!$recipients) return [];
		if (!is_array($recipients)) $recipients = [$recipients];
		$recipients = array_map('strtolower', $recipients);

		$criteria = ['contact_email_home' => $recipients, 'contact_email' => $recipients];
		$result = [];
		$filters = [null];
		// if accounts-backend is NOT SQL, we need to search the accounts separate
		if ($this->so_accounts)
		{
			$filters[] = ['owner' => '0'];
		}
		foreach ($filters as $filter)
		{
			foreach ((array)$this->search($criteria, ['account_id', 'contact_email', 'contact_email_home', 'contact_pubkey', 'contact_id'],
				'', '', '', false, 'OR', false, $filter) as $contact)
			{
				foreach ([strtolower($contact['email'] ?? ''), strtolower($contact['email_home'] ?? '')] as $address)
				{
					if ($address !== '' && in_array($address, $recipients, true) && !isset($result[$address]) &&
						($this->get_autocrypt_attributes($contact, $address)['prefer-encrypt'] ?? null) === 'mutual')
					{
						$result[$address] = 'mutual';
					}
				}
			}
		}
		return $result;
	}

	/**
	 * Ajax wrapper for get_autocrypt_prefer_encrypt() - see its own docblock.
	 *
	 * @param string|array $recipients email addresses
	 */
	public function ajax_get_autocrypt_prefer_encrypt($recipients)
	{
		Api\Json\Response::get()->data($this->get_autocrypt_prefer_encrypt($recipients));
	}

	/**
	 * Set/merge Autocrypt attributes (eg. `prefer-encrypt`) for a contact's PGP key at a given
	 * address, if user has necessary rights (same ACL/save() path as set_keys()).
	 *
	 * PGP only (see get_autocrypt_attributes()'s own docblock) - attributes attach to whichever
	 * address ALREADY holds the real key content for $recipient (resolve_root_address(), following
	 * an alias chain the same way get_key() does), never created standalone without a key on file:
	 * this describes a property of an EXISTING key/identity, not a reason on its own to create or
	 * touch a contact entry. Silently does nothing (returns false) for a contact with no PGP key
	 * stored at all yet for $recipient.
	 *
	 * @param string|int $recipient email address or account_id
	 * @param array $attributes attribute-name => value pairs to merge, eg. ['prefer-encrypt' => 'mutual']
	 * @return bool true if the attributes were merged into at least one contact's stored key
	 */
	public function set_autocrypt_attributes($recipient, array $attributes) : bool
	{
		if (!$attributes) return false;

		if (is_numeric($recipient))
		{
			$criteria = ['egw_addressbook.account_id' => [(int)$recipient]];
			$address = null;
		}
		else
		{
			$address = strtolower($recipient);
			$criteria = ['contact_email_home' => [$address], 'contact_email' => [$address]];
		}

		$updated = false;
		$filters = array(null);
		// if accounts-backend is NOT SQL, we need to search the accounts separate
		if ($this->so_accounts)
		{
			$filters[] = array('owner' => '0');
		}
		foreach ($filters as $filter)
		{
			foreach ((array)$this->search($criteria, false, '', '', '', false, 'OR', false, $filter) as $contact)
			{
				$path = $this->key_storage_path($contact, true);
				$existing = $path ? (file_exists($path) ? file_get_contents($path) : '') : ($contact['pubkey'] ?? '');

				$decoded = self::decode_key_content($existing, true);
				if (self::resolve_root_address($decoded, $address, self::$pgp_key_regexp) === null)
				{
					continue;	// no PGP key stored for this contact/address yet - nothing to attach attributes to
				}

				$newContent = self::merge_keys_json($existing, [], true, [($address ?? '*') => $attributes]);
				if ($path)
				{
					$contact['files'] |= self::FILES_BIT_PGP_PUBKEY;
				}
				else
				{
					$contact['pubkey'] = $newContent;
				}
				$contact['photo_unchanged'] = true;	// otherwise photo will be lost, because $contact['jpegphoto'] is not set
				if ($this->check_perms(Acl::EDIT, $contact) && $this->save($contact))
				{
					if (!$path || $this->write_key_file($path, $newContent))
					{
						$updated = true;
					}
				}
			}
		}
		return $updated;
	}

	/**
	 * Pick the right key out of a contact's stored key content for a given address.
	 *
	 * Follows alias entries (a value that's itself a plain address string, not armored key/cert
	 * text - see set_keys()'s own docblock for the format) to whatever address they ultimately
	 * point at, bounded by $seen against a malformed/cyclic chain.
	 *
	 * Single-key cross-check: when the ONLY thing resolving for this contact is the `"*"`
	 * (address-unknown) fallback entry - eg. a legacy pre-fix contact never migrated to a specific
	 * address, or one whose stored address just doesn't match what's being asked - detect_smime_
	 * address() is used (S/MIME only, see its own docblock for why PGP can't do this) to verify the
	 * key actually claims the requested address before returning it, rather than handing back
	 * possibly-stale-or-wrong-contact key content just because it's the only one on file. This does
	 * NOT clean up/remove a stale key when a contact's email changes (deferred, a separate concern)
	 * - it only stops a single leftover/mismatched key from being silently offered for an address it
	 * doesn't belong to.
	 *
	 * Root-address resolution itself (following an alias chain to whatever address actually holds
	 * the key/cert text) is delegated to resolve_root_address() - shared with merge_keys_json()'s
	 * own Autocrypt-attribute merging and get_autocrypt_attributes()/set_autocrypt_attributes().
	 *
	 * @param string $content raw file/pubkey-field content - either the new per-address JSON
	 *  shape (merge_keys_json()'s own output) or a legacy pre-fix bare armored key/cert
	 * @param ?string $address lowercased address to look up, or null
	 * @param string $key_regexp only used for the legacy (non-JSON) fallback extraction
	 * @param bool $pgp true: PGP (no single-key address cross-check possible), false: S/MIME
	 * @return string|null
	 */
	private static function extract_key_for_address(string $content, ?string $address, string $key_regexp, bool $pgp) : ?string
	{
		$decoded = json_decode($content, true);
		if (!is_array($decoded) || json_last_error() !== JSON_ERROR_NONE)
		{
			// legacy bare-armored-text format - applies to any address, same as before this fix
			$matches = null;
			return preg_match($key_regexp, $content, $matches) ? $matches[0] : null;
		}
		$root = self::resolve_root_address($decoded, $address, $key_regexp);
		if ($root === null) return null;
		$value = $decoded[$root];

		$usedFallback = !($address !== null && array_key_exists($address, $decoded));
		// "autocrypt" is reserved metadata (see set_keys()'s own docblock), not a key entry -
		// excluded from the "is this the contact's ONLY stored key" count below
		$addressEntryCount = count($decoded) - (isset($decoded['autocrypt']) ? 1 : 0);
		if ($usedFallback && $address !== null && !$pgp && $addressEntryCount === 1 &&
			($detected = self::detect_smime_address($value)) !== null && $detected !== $address)
		{
			return null;
		}
		return $value;
	}

	/**
	 * Search addressbook for SMIME Certificate keys of given recipients
	 *
	 * EMail addresses are lowercased to make search case-insensitive
	 *
	 * @param string|int|array $recipients (array of) email addresses or numeric account-ids
	 * @return array email|account_id => key pairs
	 */
	public function get_smime_keys($recipients)
	{
		return $this->get_keys($recipients, false);
	}

	/**
	 * Set SMIME keys for given email or account_id, if user has necessary rights
	 *
	 * @param array $keys email|account_id => public key pairs to store
	 * @param boolean $allow_user_updates =null for admins, set config to allow regular users to store their smime key
	 *
	 * @return string message of the update operation result
	 */
	public function set_smime_keys($keys, $allow_user_updates=null)
	{
		return $this->set_keys($keys, false, $allow_user_updates);
	}

	/**
	 * Set PGP keys for given email or account_id, if user has necessary rights - plain set_keys()
	 * wrapper, same shape as set_smime_keys() above. Deliberately NOT the same as ajax_set_pgp_keys()
	 * above, which additionally uploads to a public keyserver and appends that to its return message -
	 * side effects only appropriate for that method's own "user explicitly pastes/manages their own
	 * key" UI flow, not for MailApp.pgpAutoOfferAddToContact()'s (mail/js/app.ts) "silently store a
	 * message-supplied key for an already-known contact" use (2026-09-09, Autocrypt Phase 5 items 5/7 -
	 * see doc/ai/projects/mail-pgp-signature-verification.md).
	 *
	 * @param array $keys email|account_id => public key pairs to store
	 * @param boolean $allow_user_updates =null for admins, set config to allow regular users to store their pgp key
	 *
	 * @return string message of the update operation result
	 */
	public function set_pgp_keys($keys, $allow_user_updates=null)
	{
		return $this->set_keys($keys, true, $allow_user_updates);
	}

	/**
	 * Saves contact
	 *
	 * Reimplemented to strip pubkeys pasted into pubkey field or imported and store them as files in Vfs.
	 * We allways store PGP pubkeys to Vfs, but S/Mime ones only for SQL backend, not for LDAP or AD.
	 *
	 * @param array &$contact contact array from etemplate::exec
	 * @param boolean $ignore_acl =false should the acl be checked or not
	 * @param boolean $touch_modified =true should modified/r be updated
	 * @return int|string|boolean id on success, false on failure, the error-message is in $this->error
	 */
	function save(&$contact, $ignore_acl=false, $touch_modified=true)
	{
		if (($id = parent::save($contact, $ignore_acl, $touch_modified)) && !empty($contact['pubkey']))
		{
			$files = 0;
			foreach(array(
				array(addressbook_bo::$pgp_key_regexp, Api\Contacts::FILES_PGP_PUBKEY, Api\Contacts::FILES_BIT_PGP_PUBKEY),
				array(Api\Mail\Smime::$certificate_regexp, Api\Contacts::FILES_SMIME_PUBKEY, Api\Contacts::FILES_BIT_SMIME_PUBKEY),
			) as $data)
			{
				list($regexp, $file, $bit) = $data;
				$matches = null;
				if (!empty($contact['pubkey']) && preg_match($regexp, $contact['pubkey'], $matches) &&
					// check if we store that pubkey as file (PGP allways, but S/Mime only for SQL backend, not for LDAP or AD!)
					$this->pubkey_use_file($bit === Api\Contacts::FILES_BIT_PGP_PUBKEY, $contact))
				{
					// check_perms && save check ACL, in case of access only via own-account we have to use root to allow the update
					$backup = Api\Vfs::$is_root; Api\Vfs::$is_root = true;
					if (file_put_contents(Api\Link::vfs_path('addressbook', $id, $file), $matches[0]))
					{
						$files |= $bit;
						$contact['pubkey'] = str_replace($matches[0], '', $contact['pubkey']);
					}
					Api\Vfs::$is_root = $backup;
				}
			}
			// if we stripped a pubkey / stored it as file --> remove it from DB
			if ($files)
			{
				if (!trim($contact['pubkey'])) $contact['pubkey'] = null;
				$contact['files'] |= $files;
				parent::save($contact, $ignore_acl, $touch_modified);
			}
		}
		return $id;
	}
}