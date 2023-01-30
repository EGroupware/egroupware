<?php
/**
 * EGroupware Api: Mail account credentials
 *
 * @link http://www.stylite.de
 * @package api
 * @subpackage mail
 * @author Ralf Becker <rb-AT-stylite.de>
 * @copyright (c) 2013-16 by Ralf Becker <rb-AT-stylite.de>
 * @author Stylite AG <info@stylite.de>
 * @license http://opensource.org/licenses/gpl-license.php GPL - GNU General Public License
 * @version $Id$
 */

namespace EGroupware\Api\Mail;

use EGroupware\Api;
use Jumbojett\OpenIDConnectClientException;

/**
 * Mail account credentials are stored in egw_ea_credentials for given
 * account_id, users and types (imap, smtp and optional admin connection).
 *
 * Passwords in credentials are encrypted with either user password from session
 * or the database password.
 *
 * If OpenSSL extension is available it is used to store credentials with AES-128-CBC,
 * with key generated via hash_pbkdf2 sha256 hash and 16 byte binary salt (=24 char base64).
 * OpenSSL can be also used to read old MCrypt credentials (OpenSSL 'des-ede3').
 *
 * If only MCrypt is available (or EGroupware versions 14.x) credentials are are stored
 * with MCrypt algo 'tripledes' and mode 'ecb'. Key is direct user password or system secret,
 * key-size 24 (truncated to 23 byte, if greater then 24 byte! This is a bug, but thats how it is stored.).
 */
class Credentials
{
	/**
	 * App tables belong to
	 */
	const APP = 'api';
	/**
	 * Name of credentials table
	 */
	const TABLE = 'egw_ea_credentials';
	/**
	 * Join to check account is user-editable
	 */
	const USER_EDITABLE_JOIN = 'JOIN egw_ea_accounts ON egw_ea_accounts.acc_id=egw_ea_credentials.acc_id AND acc_user_editable=';

	/**
	 * Credentials for type IMAP
	 */
	const IMAP = 1;
	/**
	 * Credentials for type SMTP
	 */
	const SMTP = 2;
	/**
	 * Credentials for admin connection
	 */
	const ADMIN = 8;
	/**
	 * Credentials for SMIME private key
	 */
	const SMIME = 16;
	/**
	 * Two factor auth secret key
	 */
	const TWOFA = 32;

	/**
	 * Collabora key
	 *
	 * @link https://github.com/EGroupware/collabora/blob/master/src/Credentials.php#L20
	 */
	const COLLABORA = 64;

	/**
	 * SpamTitan API Token
	 */
	const SPAMTITAN = 128;

	/**
	 * Refresh token for IMAP & SMTP via OAuth
	 */
	const OAUTH_REFRESH_TOKEN = 256;

	/**
	 * All credentials
	 */
	const ALL = self::IMAP|self::SMTP|self::ADMIN|self::SMIME|self::TWOFA|self::SPAMTITAN|self::OAUTH_REFRESH_TOKEN;

	/**
	 * Password in cleartext
	 */
	const CLEARTEXT = 0;
	/**
	 * Password encrypted with user password
	 *
	 * MCrypt algo 'tripledes' and mode 'ecb' or OpenSSL 'des-ede3'
	 * Key is direct user password, key-size 24 (truncated to 23 byte, if greater then 24 byte!)
	 */
	const USER = 1;
	/**
	 * Password encrypted with system secret
	 *
	 * MCrypt algo 'tripledes' and mode 'ecb' or OpenSSL 'des-ede3'
	 * Key is direct system secret, key-size 24 (truncated to 23 byte, if greater then 24 byte!)
	 */
	const SYSTEM = 2;
	/**
	 * Password encrypted with user password
	 *
	 * OpenSSL: AES-128-CBC, with key generated via hash_pbkdf2 sha256 hash and 12 byte binary salt (=16 char base64)
	 */
	const USER_AES = 3;
	/**
	 * Password encrypted with system secret
	 *
	 * OpenSSL: AES-128-CBC, with key generated via hash_pbkdf2 sha256 hash and 12 byte binary salt (=16 char base64)
	 */
	const SYSTEM_AES = 4;

	/**
	 * Returned for passwords, when an admin reads an accounts with a password encrypted with users session password
	 */
	const UNAVAILABLE = '**unavailable**';

	/**
	 * Translate type to prefix
	 *
	 * @var array
	 */
	protected static $type2prefix = array(
		self::IMAP => 'acc_imap_',
		self::SMTP => 'acc_smtp_',
		self::ADMIN => 'acc_imap_admin_',
		self::SMIME => 'acc_smime_',
		self::TWOFA => '2fa_',
		self::SPAMTITAN => 'acc_spam_',
		self::OAUTH_REFRESH_TOKEN => 'acc_oauth_'
	);

	/**
	 * Mcrypt instance initialised with system specific key
	 *
	 * @var resource
	 */
	static protected $system_mcrypt;

	/**
	 * Mcrypt instance initialised with user password from session
	 *
	 * @var resource
	 */
	static protected $user_mcrypt;

	/**
	 * Cache for credentials to minimize database access
	 *
	 * @var array
	 */
	protected static $cache = array();

	/**
	 * Read credentials for a given mail account
	 *
	 * @param int $acc_id
	 * @param int $type =null default return all credentials
	 * @param int|array $account_id =null default use current user or all (in that order)
	 * @param array& $on_login =null on return array with callable and further arguments
	 *	to run on successful login to trigger password migration
	 * @param string|null $mailserver mailserver to detect oauth hosts
	 * @return array with values for (imap|smtp|admin)_(username|password|cred_id)
	 */
	public static function read($acc_id, $type=null, $account_id=null, &$on_login=null, $mailserver=null)
	{
		if (is_null($type)) $type = self::ALL;
		if (is_null($account_id))
		{
			$account_id = array(0, $GLOBALS['egw_info']['user']['account_id']);
		}

		// check cache, if nothing found, query database
		// check assumes always same accounts (eg. 0=all plus own account_id) are asked
		if (!isset(self::$cache[$acc_id]) ||
			!($rows = array_intersect_key(self::$cache[$acc_id], array_flip((array)$account_id))))
		{
			$rows = self::get_db()->select(self::TABLE, '*', array(
				'acc_id' => $acc_id,
				'account_id' => $account_id,
				'(cred_type & '.(int)$type.') > 0',	// postgreSQL require > 0, or gives error as it expects boolean
			), __LINE__, __FILE__, false,
				// account_id DESC ensures 0=all always overwrite (old user-specific credentials)
				'ORDER BY account_id ASC, cred_type ASC', self::APP);
			//error_log(__METHOD__."($acc_id, $type, ".array2string($account_id).") nothing in cache");
		}
		else
		{
			ksort($rows);	// ORDER BY account_id ASC

			// flatten account_id => cred_type => row array again, to have format like from database
			$rows = call_user_func_array('array_merge', $rows);
			//error_log(__METHOD__."($acc_id, $type, ".array2string($account_id).") read from cache ".array2string($rows));
		}
		$on_login = null;
		$results = array();
		foreach($rows as $row)
		{
			// update cache (only if we have database-iterator and all credentials asked!)
			if (!is_array($rows) && $type == self::ALL)
			{
				self::$cache[$acc_id][$row['account_id']][$row['cred_type']] = $row;
				//error_log(__METHOD__."($acc_id, $type, ".array2string($account_id).") stored to cache ".array2string($row));

				if (!isset($on_login) && self::needMigration($row['cred_pw_enc']))
				{
					$on_login = array(__CLASS__.'::migrate', $acc_id);
				}
			}
			// do NOT attempt to use credentials encrypted with user password in an async context (where user password is not available)
			// otherwise an s/mime certificate or user specific password will stall sending notification, even if no smtp authentication required
			if (!empty($GLOBALS['egw_info']['flags']['async-service']) && in_array($row['cred_pw_enc'], [self::USER_AES, self::USER]))
			{
				continue;
			}
			$password = self::decrypt($row);

			// Remove special x char added to the end for \0 trimming escape.
			if ($type == self::SMIME && substr($password, -1) === 'x') $password = substr($password, 0, -1);

			foreach(static::$type2prefix as $pattern => $prefix)
			{
				if ($row['cred_type'] & $pattern)
				{
					$results[$prefix.'username'] = $row['cred_username'];
					$results[$prefix.'password'] = $password;
					$results[$prefix.'cred_id'] = $row['cred_id'];
					$results[$prefix.'account_id'] = $row['account_id'];
					$results[$prefix.'pw_enc'] = $row['cred_pw_enc'];

					// for OAuth we return the access- and not the refresh-token
					if ($pattern == self::OAUTH_REFRESH_TOKEN)
					{
						unset($results[$prefix.'password']);
						$results[$prefix.'refresh_token'] = self::UNAVAILABLE;  // no need to make it available
						$results[$prefix.'access_token'] = self::getAccessToken($row['cred_username'], $password, $mailserver);
						// if no extra imap&smtp username set, set the oauth one
						foreach(['acc_imap_', 'acc_smtp_'] as $pre)
						{
							if (empty($results[$pre.'username']))
							{
								$results[$pre.'username'] = $row['cred_username'];
								$results[$pre.'password'] = '**oauth**';
							}
						}
					}
				}
			}
		}
		return $results;
	}

	/**
	 * Get cached access-token, or use refresh-token to get a new one
	 *
	 * @param string $username
	 * @param string $refresh_token
	 * @param string|null $mailserver mailserver to detect oauth hosts
	 * @return string|null
	 */
	static protected function getAccessToken($username, $refresh_token, $mailserver=null)
	{
		return Api\Cache::getInstance(__CLASS__, 'access-token-'.$username.'-'.md5($refresh_token), static function() use ($username, $refresh_token, $mailserver)
		{
			if (!($oidc = Api\Auth\OpenIDConnectClient::byDomain($username, $mailserver)))
			{
				return null;
			}
			try
			{
				$token = $oidc->refreshToken($refresh_token);
				return $token->access_token;
			}
			catch (OpenIDConnectClientException $e) {
				_egw_log_exception($e);
			}
			return null;
		}, [], 3500);   // access-token have a livetime of 3600s, give it some margin
	}

	/**
	 * Generate username according to acc_imap_logintype and fetch password from session
	 *
	 * @param array $data values for acc_imap_logintype and acc_domain
	 * @param boolean $set_identity =true true: also set identity values realname&email, if not yet set
	 * @return array with values for keys 'acc_(imap|smtp)_(username|password|cred_id)'
	 */
	public static function from_session(array $data, $set_identity=true)
	{
		switch($data['acc_imap_logintype'])
		{
			case 'standard':
				$username = $GLOBALS['egw_info']['user']['account_lid'];
				break;

			case 'vmailmgr':
				$username = $GLOBALS['egw_info']['user']['account_lid'].'@'.$data['acc_domain'];
				break;

			case 'email':
				$username = $GLOBALS['egw_info']['user']['account_email'];
				break;

			case 'uidNumber':
				$username = 'u'.$GLOBALS['egw_info']['user']['account_id'].'@'.$data['acc_domain'];
				break;

			case 'admin':
				// data should have been stored in credentials table
				throw new Api\Exception\AssertionFailed('data[acc_imap_logintype]=admin and no stored username/password for data[acc_id]='.$data['acc_id'].'!');

			default:
				throw new Api\Exception\WrongParameter("Unknown data[acc_imap_logintype]=".array2string($data['acc_imap_logintype']).'!');
		}
		$password = base64_decode(Api\Cache::getSession('phpgwapi', 'password'));
		$realname = !$set_identity || !empty($data['ident_realname']) ? $data['ident_realname'] :
			($GLOBALS['egw_info']['user']['account_fullname'] ?? null);
		$email = !$set_identity || !empty($data['ident_email']) ? $data['ident_email'] :
			($GLOBALS['egw_info']['user']['account_email'] ?? null);

		return array(
			'ident_realname' => $realname,
			'ident_email' => $email,
			'acc_imap_username' => $username,
			'acc_imap_password' => $password,
			'acc_imap_cred_id'  => $data['acc_imap_logintype'],	// to NOT store it
			'acc_imap_account_id' => 'c',
		) + (!empty($data['acc_smtp_auth_session']) ? array(
			// only set smtp
			'acc_smtp_username' => $username,
			'acc_smtp_password' => $password,
			'acc_smtp_cred_id'  => $data['acc_imap_logintype'],	// to NOT store it
			'acc_smtp_account_id' => 'c',
		) : array());
	}

	/**
	 * Write and encrypt credentials
	 *
	 * @param int $acc_id id of account
	 * @param string $username
	 * @param string $password cleartext password to write
	 * @param int $type self::IMAP, self::SMTP, self::ADMIN or self::SMIME
	 * @param int $account_id if of user-account for whom credentials are
	 * @param int $cred_id =null id of existing credentials to update
	 * @return int cred_id
	 */
	public static function write($acc_id, $username, $password, $type, $account_id=0, $cred_id=null)
	{
		//error_log(__METHOD__."(acc_id=$acc_id, '$username', \$password, type=$type, account_id=$account_id, cred_id=$cred_id)");
		if (!empty($cred_id) && !is_numeric($cred_id) || !is_numeric($account_id))
		{
			//error_log(__METHOD__."($acc_id, '$username', \$password, $type, $account_id, ".array2string($cred_id).") not storing session credentials!");
			return;	// do NOT store credentials from session of current user!
		}

		// Add arbitary char to the ending to make sure the Smime binary content
		// with \0 at the end not getting trimmed of while trying to decrypt.
		if ($type == self::SMIME) $password .= 'x';

		// no need to write empty usernames, but delete existing row
		if ((string)$username === '')
		{
			if ($cred_id) self::get_db()->delete(self::TABLE, array('cred_id' => $cred_id), __LINE__, __FILE__, self::APP);
			return;	// nothing to save
		}
		$pw_enc = self::CLEARTEXT;
		$data = array(
			'acc_id' => $acc_id,
			'account_id' => $account_id,
			'cred_username' => $username,
			'cred_password' => (string)$password === '' ? '' :
				self::encrypt($password, $account_id, $pw_enc),
			'cred_type' => $type,
			'cred_pw_enc' => $pw_enc,
		);
		// check if password is unavailable (admin edits an account with password encrypted with users session PW) and NOT store it
		if ($password == self::UNAVAILABLE)
		{
			//error_log(__METHOD__."(".array2string(func_get_args()).") can NOT store unavailable password, storing without password!");
			unset($data['cred_password'], $data['cred_pw_enc']);
		}
		//error_log(__METHOD__."($acc_id, '$username', '$password', $type, $account_id, $cred_id) storing ".array2string($data).' '.function_backtrace());
		if ($cred_id > 0)
		{
			self::get_db()->update(self::TABLE, $data, array('cred_id' => $cred_id), __LINE__, __FILE__, self::APP);
		}
		else
		{
			self::get_db()->insert(self::TABLE, $data, array(
				'acc_id' => $acc_id,
				'account_id' => $account_id,
				'cred_type' => $type,
			), __LINE__, __FILE__, self::APP);
			$cred_id = self::get_db()->get_last_insert_id(self::TABLE, 'cred_id');
		}
		// invalidate cache
		unset(self::$cache[$acc_id][$account_id]);

		//error_log(__METHOD__."($acc_id, '$username', \$password, $type, $account_id) returning $cred_id");
		return $cred_id;
	}

	/**
	 * Delete credentials from database
	 *
	 * @param int $acc_id
	 * @param int|array $account_id =null
	 * @param int $type = self::IMAP, self::SMTP, self::ADMIN or self::SMIME
	 * @param boolean $exact_type =false true: delete only cred_type=$type, false: delete cred_type&$type
	 * @return int number of rows deleted
	 */
	public static function delete($acc_id, $account_id=null, $type=self::ALL, $exact_type=false)
	{
		if (!($acc_id > 0) && !isset($account_id))
		{
			throw new Api\Exception\WrongParameter(__METHOD__."() no acc_id AND no account_id parameter!");
		}
		$where = array();
		if ($acc_id > 0) $where['acc_id'] = $acc_id;
		if (isset($account_id)) $where['account_id'] = $account_id;
		if ($exact_type)
		{
			$where['cred_type'] = $type;
		}
		elseif ($type != self::ALL)
		{
			$where[] = '(cred_type & '.(int)$type.') > 0';	// postgreSQL require > 0, or gives error as it expects boolean
		}
		self::get_db()->delete(self::TABLE, $where, __LINE__, __FILE__, self::APP);

		// invalidate cache: we allways unset everything about an account to simplify cache handling
		foreach($acc_id > 0 ? (array)$acc_id : array_keys(self::$cache) as $acc_id)
		{
			unset(self::$cache[$acc_id]);
		}
		$ret = self::get_db()->affected_rows();
		//error_log(__METHOD__."($acc_id, ".array2string($account_id).", $type) affected $ret rows");
		return $ret;
	}

	/**
	 * Encrypt password for storing in database with MCrypt and tripledes mode cbc
	 *
	 * @param string $password cleartext password
	 * @param int $account_id user-account password is for
	 * @param int& $pw_enc on return encryption used
	 * @return string encrypted password
	 */
	public static function encrypt($password, $account_id, &$pw_enc)
	{
		try {
			return self::encrypt_openssl_aes($password, $account_id, $pw_enc);
		}
		catch (Api\Exception\AssertionFailed $ex) {
			try {
				return self::encrypt_mcrypt_3des($password, $account_id, $pw_enc);
			}
			catch (Api\Exception\AssertionFailed $ex) {
				$pw_enc = self::CLEARTEXT;
				return base64_encode($password);
			}
		}
	}

	/**
	 * OpenSSL method to use for AES encrypted credentials
	 */
	const AES_METHOD = 'AES-128-CBC';

	/**
	 * Len (binary) of salt/iv used for pbkdf2 and openssl
	 */
	const SALT_LEN = 16;

	/**
	 * Len of base64 encoded salt prefixing AES encoded credentials (4*ceil(SALT_LEN/3))
	 */
	const SALT_LEN64 = 24;

	/**
	 * Encrypt password for storing in database via OpenSSL and AES
	 *
	 * @param string $password cleartext password
	 * @param int $account_id user-account password is for
	 * @param int& $pw_enc on return encryption used
	 * @param string $key =null key/password to use, default password according to account_id
	 * @param string $salt =null (binary) salt to use, default generate new random salt
	 * @return string encrypted password
	 */
	protected static function encrypt_openssl_aes($password, $account_id, &$pw_enc, $key=null, $salt=null)
	{
		if (empty($key))
		{
			if ($account_id > 0 && $account_id == $GLOBALS['egw_info']['user']['account_id'] &&
				($key = Api\Cache::getSession('phpgwapi', 'password')))
			{
				$pw_enc = self::USER_AES;
				$key = base64_decode($key);
			}
			else
			{
				$pw_enc = self::SYSTEM_AES;
				$key = self::get_db()->Password;
			}
		}
		// using a pbkdf2 password derivation with a (stored) salt
		$aes_key = self::aes_key($key, $salt);

		return base64_encode($salt).base64_encode(openssl_encrypt($password, self::AES_METHOD, $aes_key, OPENSSL_RAW_DATA, $salt));
	}

	/**
	 * Derive an encryption key from a password
	 *
	 * Using a pbkdf2 password derivation with a (stored) salt
	 * With a 12 byte binary (16 byte base64) salt we can store 39 byte password in our varchar(80) column.
	 *
	 * @param string $password
	 * @param string& $salt binary salt to use or null to generate one, on return used salt
	 * @param int $iterations =2048 iterations of passsword
	 * @param int $length =16 length of binary aes key
	 * @param string $hash ='sha256'
	 * @return string
	 */
	protected static function aes_key($password, &$salt, $iterations=2048, $length=16, $hash='sha256')
	{
		if (empty($salt))
		{
			$salt = openssl_random_pseudo_bytes(self::SALT_LEN);
		}
		// load hash_pbkdf2 polyfill for php < 5.5
		if (!function_exists('hash_pbkdf2'))
		{
			require_once __DIR__.'/hash_pbkdf2.php';
		}
		$aes_key = hash_pbkdf2($hash, $password, $salt, $iterations, $length, true);

		//error_log(__METHOD__."('$password', '".base64_encode($salt)."') returning ".base64_encode($aes_key).' '.function_backtrace());
		return $aes_key;
	}

	/**
	 * Encrypt password for storing in database with MCrypt and tripledes mode cbc
	 *
	 * @param string $password cleartext password
	 * @param int $account_id user-account password is for
	 * @param int& $pw_enc on return encryption used
	 * @return string encrypted password
	 */
	protected static function encrypt_mcrypt_3des($password, $account_id, &$pw_enc)
	{
		if ($account_id > 0 && $account_id == $GLOBALS['egw_info']['user']['account_id'] &&
			($mcrypt = self::init_crypt(true)))
		{
			$pw_enc = self::USER;
			$password = mcrypt_generic($mcrypt, $password);
		}
		elseif (($mcrypt = self::init_crypt(false)))
		{
			$pw_enc = self::SYSTEM;
			$password = mcrypt_generic($mcrypt, $password);
		}
		else
		{
			$pw_enc = self::CLEARTEXT;
		}
		//error_log(__METHOD__."(, $account_id, , $mcrypt) pw_enc=$pw_enc returning ".array2string(base64_encode($password)));
		return base64_encode($password);
	}

	/**
	 * Decrypt password from database
	 *
	 * @param array $row database row
	 * @param string $key =null key/password to use, default user pw from session or database pw, see get_key
	 * @return string cleartext password
	 * @throws Api\Exception\WrongParameter
	 * @throws Api\Exception\AssertionFailed if neither OpenSSL nor MCrypt extension available
	 */
	public static function decrypt(array $row, $key=null)
	{
		// empty/unset passwords only give warnings ...
		if (empty($row['cred_password'])) return '';

		if (self::isUser($row['cred_pw_enc']) && $row['account_id'] != $GLOBALS['egw_info']['user']['account_id'])
		{
			return self::UNAVAILABLE;
		}

		switch($row['cred_pw_enc'])
		{
			case self::CLEARTEXT:
				return base64_decode($row['cred_password']);

			case self::USER_AES:
			case self::SYSTEM_AES:
				return self::decrypt_openssl_aes($row, $key);

			case self::USER:
			case self::SYSTEM:
				try {
					$password = self::decrypt_openssl_3des($row, $key);
					// ToDo store as AES
					return $password;
				}
				catch(Api\Exception\AssertionFailed $e) {
					unset($e);
					// try Mcrypt
					return self::decrypt_mcrypt_3des($row);
				}
		}
		throw new Api\Exception\WrongParameter("Password encryption type $row[cred_pw_enc] NOT available for mail account #$row[acc_id] and user #$row[account_id]/$row[cred_username]!");
	}

	/**
	 * Decrypt tripledes password from database with Mcrypt
	 *
	 * @param array $row database row
	 * @return string cleartext password
	 * @param string $key =null key/password to use, default user pw from session or database pw, see get_key
	 * @throws Api\Exception\WrongParameter
	 * @throws Api\Exception\AssertionFailed if MCrypt extension not available
	 */
	protected static function decrypt_mcrypt_3des(array $row, $key=null)
	{
		check_load_extension('mcrypt', true);

		if (!($mcrypt = self::init_crypt(isset($key) ? $key : $row['cred_pw_enc'] == self::USER)))
		{
			throw new Api\Exception\WrongParameter("Password encryption type $row[cred_pw_enc] NOT available for mail account #$row[acc_id] and user #$row[account_id]/$row[cred_username]!");
		}
		return trim(mdecrypt_generic($mcrypt, base64_decode($row['cred_password'])), "\0");
	}

	/**
	 * Get key/password to decrypt credentials
	 *
	 * @param int $pw_enc self::(SYSTEM|USER)(_AES)?
	 * @return string
	 * @throws Api\Exception\AssertionFailed if not session password is available
	 */
	protected static function get_key($pw_enc)
	{
		if (self::isUser($pw_enc))
		{
			$session_key = Api\Cache::getSession('phpgwapi', 'password');
			if (empty($session_key))
			{
				throw new Api\Exception\AssertionFailed("No session password available!");
			}
			$key = base64_decode($session_key);
		}
		else
		{
			$key = self::get_db()->Password;
		}
		return $key;
	}

	/**
	 * OpenSSL equivalent for Mcrypt  $algo='tripledes', $mode='ecb'
	 */
	const TRIPLEDES_ECB_METHOD = 'des-ede3';

	/**
	 * Decrypt tripledes password from database with OpenSSL
	 *
	 * Seems iv is NOT used for mcrypt "tripledes/ecb" = openssl "des-ede3", only key-size 24.
	 *
	 * @link https://github.com/tom--/mcrypt2openssl/blob/master/mapping.md
	 * @link http://thefsb.tumblr.com/post/110749271235/using-opensslendecrypt-in-php-instead-of
	 * @param array $row database row
	 * @param string $key =null password to use
	 * @return string cleartext password
	 * @throws Api\Exception\WrongParameter
	 * @throws Api\Exception\AssertionFailed if OpenSSL extension not available
	 */
	protected static function decrypt_openssl_3des(array $row, $key=null)
	{
		check_load_extension('openssl', true);

		if (!isset($key) || !is_string($key))
		{
			$key = self::get_key($row['cred_pw_enc']);
		}
		// seems iv is NOT used for mcrypt "tripledes/ecb" = openssl "des-ede3", only key-size 24
		$keySize = 24;
		if (bytes($key) > $keySize) $key = cut_bytes($key,0,$keySize-1);	// $keySize-1 is wrong, but that's what's used!
		return trim(openssl_decrypt($row['cred_password'], self::TRIPLEDES_ECB_METHOD, $key, OPENSSL_ZERO_PADDING, ''), "\0");
	}

	/**
	 * Decrypt aes encrypted and salted password from database via OpenSSL and AES
	 *
	 * @param array $row database row
	 * @param string $key =null password to use
	 * @param string $salt_len =16 len of base64 encoded salt (binary is 3/4)
	 * @return string cleartext password
	 * @throws Api\Exception\WrongParameter
	 * @throws Api\Exception\AssertionFailed if OpenSSL extension not available
	 */
	protected static function decrypt_openssl_aes(array $row, $key=null)
	{
		check_load_extension('openssl', true);

		if (!isset($key) || !is_string($key))
		{
			$key = self::get_key($row['cred_pw_enc']);
		}
		$salt = base64_decode(substr($row['cred_password'], 0, self::SALT_LEN64));
		$aes_key = self::aes_key($key, $salt);

		return trim(openssl_decrypt(base64_decode(substr($row['cred_password'], self::SALT_LEN64)),
			self::AES_METHOD, $aes_key, OPENSSL_RAW_DATA, $salt), "\0");
	}

	/**
	 * Check if credentials need migration to AES
	 *
	 * @param string $pw_enc
	 * @return boolean
	 */
	static public function needMigration($pw_enc)
	{
		return $pw_enc == self::USER || $pw_enc == self::SYSTEM || $pw_enc == self::CLEARTEXT;
	}

	/**
	 * Run password migration for credentials of given account
	 *
	 * @param int $acc_id
	 */
	static function migrate($acc_id)
	{
		try {
			if (isset(self::$cache[$acc_id]))
			{
				foreach(self::$cache[$acc_id] as $account_id => &$rows)
				{
					foreach($rows as $cred_type => &$row)
					{
						if (self::needMigration($row['cred_pw_enc']) && ($row['cred_pw_enc'] != self::USER ||
							$row['cred_pw_enc'] == self::USER && $account_id == $GLOBALS['egw_info']['user']['account_id']))
						{
							self::write($acc_id, $row['cred_username'], self::decrypt($row), $cred_type, $account_id, $row['cred_id']);
						}
					}
				}
			}
		}
		catch(\Exception $e) {
			// do not stall regular use, if password migration fails
			_egw_log_exception($e);
		}
	}

	/**
	 * Hook called when user changes his password, to re-encode his credentials with his new password
	 *
	 * It also changes all user credentials encoded with system password!
	 *
	 * It only changes credentials from user-editable accounts, as user probably
	 * does NOT know password set by admin!
	 *
	 * @param array $data values for keys 'old_passwd', 'new_passwd', 'account_id'
	 */
	static public function changepassword(array $data)
	{
		if (empty($data['old_passwd'])) return;

		// as self::encrypt will use password in session, check it is identical to given new password
		if ($data['new_passwd'] !== base64_decode(Api\Cache::getSession('phpgwapi', 'password')))
		{
			throw new Api\Exception\AssertionFailed('Password in session !== password given in $data[new_password]!');
		}

		foreach(self::get_db()->select(self::TABLE, self::TABLE.'.*', array(
			'account_id' => $data['account_id']
		),__LINE__, __FILE__, false, '', self::APP) as $row)
		{
			$password = self::decrypt($row, self::isUser($row['cred_pw_enc']) ? $data['old_passwd'] : null);

			self::write($row['acc_id'], $row['cred_username'], $password, $row['cred_type'],
				$row['account_id'], $row['cred_id']);
		}
	}

	/**
	 * Check if session encryption is configured, possible and initialise it
	 *
	 * @param boolean|string $user =false true: use user-password from session,
	 *	false: database password or string with password to use
	 * @param string $algo ='tripledes'
	 * @param string $mode ='ecb'
	 * @return ressource|boolean mcrypt ressource to use or false if not available
	 */
	static public function init_crypt($user=false, $algo='tripledes',$mode='ecb')
	{
		if (is_string($user))
		{
			// do NOT use/set/change static object
		}
		elseif ($user)
		{
			$mcrypt =& self::$user_mcrypt;
		}
		else
		{
			$mcrypt =& self::$system_mcrypt;
		}
		if (!isset($mcrypt))
		{
			if (is_string($user))
			{
				$key = $user;
			}
			elseif ($user)
			{
				$session_key = Api\Cache::getSession('phpgwapi', 'password');
				if (empty($session_key))
				{
					error_log(__METHOD__."() no session password available!");
					return false;
				}
				$key = base64_decode($session_key);
			}
			else
			{
				$key = self::get_db()->Password;
			}
			check_load_extension('mcrypt', true);

			if (!($mcrypt = mcrypt_module_open($algo, '', $mode, '')))
			{
				error_log(__METHOD__."() required PHP extension mcrypt not loaded and can not be loaded, passwords can be NOT encrypted!");
				$mcrypt = false;
			}
			elseif (!($mcrypt = mcrypt_module_open($algo, '', $mode, '')))
			{
				error_log(__METHOD__."() could not mcrypt_module_open(algo='$algo','',mode='$mode',''), passwords can be NOT encrypted!");
				$mcrypt = false;
			}
			else
			{
				$iv_size = mcrypt_enc_get_iv_size($mcrypt);
				$iv = !isset($GLOBALS['egw_info']['server']['mcrypt_iv']) || strlen($GLOBALS['egw_info']['server']['mcrypt_iv']) < $iv_size ?
					mcrypt_create_iv ($iv_size, MCRYPT_DEV_RANDOM) : substr($GLOBALS['egw_info']['server']['mcrypt_iv'],0,$iv_size);

				$key_size = mcrypt_enc_get_key_size($mcrypt);
				if (bytes($key) > $key_size) $key = cut_bytes($key,0,$key_size-1);

				if (!$iv || mcrypt_generic_init($mcrypt, $key, $iv) < 0)
				{
					error_log(__METHOD__."() could not initialise mcrypt, passwords can be NOT encrypted!");
					$mcrypt = false;
				}
			}
		}
		//error_log(__METHOD__."(".array2string($user).") key=".array2string($key)." returning ".array2string($mcrypt));
		return $mcrypt;
	}

	/**
	 * Check if credentials are encrypted with users session password
	 *
	 * @param string $pw_enc
	 * @return boolean
	 */
	static public function isUser($pw_enc)
	{
		return $pw_enc == self::USER_AES || $pw_enc == self::USER;
	}

	/**
	 * Get the current Db object, from either setup or egw
	 *
	 * @return Api\Db
	 */
	static public function get_db()
	{
		return isset($GLOBALS['egw_setup']) ? $GLOBALS['egw_setup']->db : $GLOBALS['egw']->db;
	}
}