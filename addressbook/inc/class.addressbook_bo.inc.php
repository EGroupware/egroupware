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
	 * @param array $contact =null contact array to pass to get_backend()
	 * @return boolean true: store as file, false: store with contact
	 */
	public function pubkey_use_file($pgp, array $contact=null)
	{
		return $pgp || empty($contact) || get_class($this->get_backend($contact)) == 'EGroupware\\Api\\Contacts\\Sql';
	}

	/**
	 * Set keys for given email or account_id and key type based on regexp (SMIME or PGP), if user has necessary rights
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
				Config::save_value('own_account_acl', $this->own_account_acl, 'phpgwapi');
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
				$criteria['contact_email'][] = $recipient;
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
				if ($contact['account_id'] && isset($keys[$contact['account_id']]))
				{
					$key = $keys[$contact['account_id']];
				}
				elseif (isset($keys[$contact['email']]))
				{
					$key = $keys[$contact['email']];
				}

				// key is stored in file for sql backend or allways for pgp key
				$path = null;
				if ($contact['id'] && $this->pubkey_use_file($pgp, $contact))
				{
					$path =  Api\Link::vfs_path('addressbook', $contact['id'], $file);
					$contact['files'] |= $pgp ? self::FILES_BIT_PGP_PUBKEY : self::FILES_BIT_SMIME_PUBKEY;
					// remove evtl. existing old pubkey
					if (preg_match($key_regexp, $contact['pubkey']))
					{
						$contact['pubkey'] = preg_replace($key_regexp, '', $contact['pubkey']);
					}
					$updated++;
				}
				elseif (empty($contact['pubkey']) || !preg_match($key_regexp, $contact['pubkey']))
				{
					$contact['pubkey'] .= $key;
				}
				else
				{
					$contact['pubkey'] = preg_replace($key_regexp, $key, $contact['pubkey']);
				}
				$contact['photo_unchanged'] = true;	// otherwise photo will be lost, because $contact['jpegphoto'] is not set
				if ($this->check_perms(Acl::EDIT, $contact) && $this->save($contact))
				{
					if ($path)
					{
						// check_perms && save check ACL, in case of access only via own-account we have to use root to allow the update
						$backup = Api\Vfs::$is_root; Api\Vfs::$is_root = true;
						if (file_put_contents($path, $key)) ++$updated;
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
			foreach($this->search($criteria, array('account_id', 'contact_email', 'contact_email_home', 'contact_pubkey', 'contact_id'),
				'', '', '', false, 'OR', false, $filter) as $contact)
			{
				// first check for file and second for pubkey field (LDAP, AD or old SQL)
				if (($content = $this->get_key($contact, $pgp)))
				{
					$contact['email'] = strtolower($contact['email']);
					if (empty($criteria['account_id']) || in_array($contact['email'], $recipients))
					{
						if (in_array($contact['email_home'], $recipients))
						{
							$result[$contact['email_home']] = $content;
						}
						else
						{
							$result[$contact['email']] = $content;
						}
					}
					else
					{
						$result[$contact['account_id']] = $content;
					}
				}
			}
		}
		return $result;
	}

	/**
	 * Extract PGP or S/Mime pubkey from contact array
	 *
	 * @param array $contact
	 * @param boolean $pgp
	 * @return string pubkey or NULL
	 */
	function get_key(array $contact, $pgp)
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
		$matches = null;
		if (file_exists($path = Api\Link::vfs_path('addressbook', $contact['id'], $file)) &&
			($content = file_get_contents($path)) &&
			preg_match($key_regexp, $content, $matches) ||
			preg_match($key_regexp, $contact['pubkey'], $matches))
		{
			return $matches[0];
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
