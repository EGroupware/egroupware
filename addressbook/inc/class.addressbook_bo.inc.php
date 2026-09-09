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
	 *    "ralfbecker@outdoor-training.de": "rb@egroupware.org"}
	 * - a value is EITHER the armored key/cert text itself, OR (ralf's own proposed shape,
	 * 2026-09-09) a plain address string naming another entry in the SAME object to use instead -
	 * an alias, for when the identical key/cert legitimately covers more than one of a contact's
	 * addresses, without duplicating the (often multi-KB) key text once per address. See
	 * merge_keys_json() (which also creates these aliases automatically - see its own docblock)/
	 * extract_key_for_address() for the exact format and the legacy-file (pre-fix, bare armored
	 * text with no address distinction at all, still read by get_key() below) backward-
	 * compatibility fallback.
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
				$path = null;
				if ($contact['id'] && $this->pubkey_use_file($pgp, $contact))
				{
					$path =  Api\Link::vfs_path('addressbook', $contact['id'], $file);
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
						// check_perms && save check ACL, in case of access only via own-account we have to use root to allow the update
						$backup = Api\Vfs::$is_root; Api\Vfs::$is_root = true;
						// a contact that never had ANY file (photo/key/...) attached before has no
						// .files/ directory yet - file_put_contents() can't create it implicitly
						// (found live 2026-09-09 writing a test for this fix: the very first key
						// ever stored for a brand-new contact silently failed to write)
						if (!Api\Vfs::is_dir($dir = dirname($path)))
						{
							Api\Vfs::mkdir($dir, 0700, true);
						}
						if (file_put_contents($path, $newContent)) ++$updated;
						Api\Vfs::$is_root = $backup;
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
	 * Merge new (address => armored key) pairs into a contact's existing stored key content.
	 *
	 * $existing may be: empty (nothing stored yet), the new per-address JSON object this method
	 * itself produces (a value is EITHER the armored key/cert text itself, OR a plain address
	 * string naming another entry in the SAME object to use instead - an alias, see set_keys()'s
	 * own docblock for the full format), or a legacy pre-fix bare armored key/cert - upgraded here
	 * into the new shape, keyed by whichever address detect_smime_address() can determine it
	 * actually belongs to (S/MIME only - PGP has no server-side way to read a key's own User IDs,
	 * see this class' docblock), falling back to `"*"` (address-unknown) when that's not possible.
	 * An address NOT explicitly being updated by this call keeps resolving to that entry exactly
	 * like before this fix (get_key()'s own one key applied to whichever address was asked about).
	 * Addresses that ARE being updated this call always overwrite whatever was there for that
	 * specific address (new key replaces old, no history).
	 *
	 * Same-content dedup: when the exact armored text being stored for $address is ALREADY present
	 * under a different address, the new entry becomes an alias to that address instead of
	 * duplicating the (often multi-KB) key/cert text again - the common case of one real-world key
	 * legitimately covering more than one of a contact's addresses.
	 *
	 * @param string $existing current file/pubkey-field content, '' if nothing stored yet
	 * @param array $newKeysByAddress lowercased-address (or '*') => armored key/cert pairs to add/overwrite
	 * @param bool $pgp true: PGP (no legacy-address-detection possible), false: S/MIME
	 * @return string new JSON content to store
	 */
	private static function merge_keys_json(string $existing, array $newKeysByAddress, bool $pgp) : string
	{
		$decoded = $existing !== '' ? json_decode($existing, true) : null;
		if (!is_array($decoded) || json_last_error() !== JSON_ERROR_NONE)
		{
			if ($existing === '')
			{
				$decoded = [];
			}
			else
			{
				$detectedAddress = !$pgp ? self::detect_smime_address($existing) : null;
				$decoded = [($detectedAddress ?? '*') => $existing];
			}
		}
		foreach ($newKeysByAddress as $address => $key)
		{
			$existingAddress = array_search($key, $decoded, true);
			$decoded[$address] = $existingAddress !== false && $existingAddress !== $address ?
				$existingAddress : $key;
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
		$usedFallback = !($address !== null && array_key_exists($address, $decoded));
		$lookup = !$usedFallback ? $address : (array_key_exists('*', $decoded) ? '*' : null);
		$seen = [];
		while ($lookup !== null && !isset($seen[$lookup]) && array_key_exists($lookup, $decoded))
		{
			$seen[$lookup] = true;
			$value = $decoded[$lookup];
			if (is_string($value) && preg_match($key_regexp, $value))
			{
				if ($usedFallback && $address !== null && !$pgp && count($decoded) === 1 &&
					($detected = self::detect_smime_address($value)) !== null && $detected !== $address)
				{
					return null;
				}
				return $value;
			}
			// not itself an armored key/cert - treat as an alias pointing to another address entry
			$lookup = is_string($value) ? strtolower($value) : null;
		}
		return null;
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