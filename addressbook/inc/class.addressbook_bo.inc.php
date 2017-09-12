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
	static public $pgp_key_regexp = '/-----BEGIN PGP PUBLIC KEY BLOCK-----.*-----END PGP PUBLIC KEY BLOCK-----\r?\n/s';

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
		return $this->get_keys($recipients, self::$pgp_key_regexp, '%-----BEGIN PGP PUBLIC KEY BLOCK-----%');
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
		$message = $this->set_keys($keys, self::$pgp_key_regexp, $allow_user_updates);
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
	 * Set keys for given email or account_id and key type based on regexp (SMIME or PGP), if user has necessary rights
	 *
	 * @param array $keys email|account_id => public key pairs to store
	 * @param string $key_regexp regular expresion for key type indication (SMIME|PGP)
	 * @param boolean $allow_user_updates = null for admins, set config to allow regular users to store their key
	 *
	 * @return string message of the update operation result
	 */
	public function set_keys ($keys, $key_regexp, $allow_user_updates = null)
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
		$criteria = array();
		foreach($keys as $recipient => $key)
		{
			if (!preg_match($key_regexp, $key)) continue;

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
		foreach($this->search($criteria, false, '', '', '', false, 'OR') as $contact)
		{
			if ($contact['account_id'] && isset($keys[$contact['account_id']]))
			{
				$key = $keys[$contact['account_id']];
			}
			elseif (isset($keys[$contact['email']]))
			{
				$key = $keys[$contact['email']];
			}
			if (empty($contact['pubkey']) || !preg_match($key_regexp, $contact['pubkey']))
			{
				$contact['pubkey'] .= $key;
			}
			else
			{
				$contact['pubkey'] = preg_replace($key_regexp, $key, $contact['pubkey']);
			}
			if ($this->check_perms(Acl::EDIT, $contact) && $this->save($contact))
			{
				++$updated;
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
	 * @param string|int|array $recipients (array of) email addresses or numeric account-ids
	 * @param string $key_regexp
	 * @param string $criteria_filter
	 *
	 * @return array email|account_id => key pairs
	 */
	public function get_keys ($recipients, $key_regexp, $criteria_filter)
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
				$criteria['contact_email'][] = $recipient = strtolower($recipient);
			}
		}
		foreach($this->search($criteria, array('account_id', 'contact_email', 'contact_pubkey'), '', '', '', false, 'OR', false,
			"contact_pubkey LIKE '". $criteria_filter ."'" ) as $contact)
		{
			$matches = null;
			if (preg_match($key_regexp, $contact['pubkey'], $matches))
			{
				$contact['email'] = strtolower($contact['email']);
				if (empty($criteria['account_id']) || in_array($contact['email'], $recipients))
				{
					$result[$contact['email']] = $matches[0];
				}
				else
				{
					$result[$contact['account_id']] = $matches[0];
				}
			}
		}
		return $result;
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
		return $this->get_keys($recipients, Api\Mail\Smime::$certificate_regexp, '%-----BEGIN CERTIFICATE-----%');
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
		return $this->set_keys($keys, Api\Mail\Smime::$certificate_regexp, $allow_user_updates);
	}
}
