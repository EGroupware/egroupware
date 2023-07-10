<?php
/**
 * EGroupware Api: Access token for limited user access instead of passwords
 *
 * @link https://www.egroupware.org
 * @package api
 * @subpackage auth
 * @author Ralf Becker <rb@egroupware.org>
 * @copyright (c) 2023 by Ralf Becker <rb@egroupware.org>
 * @license http://opensource.org/licenses/gpl-license.php GPL - GNU General Public License
 */

namespace EGroupware\Api\Auth;

use EGroupware\Api;

/**
 * Token can be used instead of password to create sessions, which are:
 * - optionally more limited in the allowed apps than the user
 * - can be created to be valid to impersonate arbitrary user e.g. for REST API
 */
class Token extends APi\Storage\Base
{
	const APP = 'api';
	const TABLE = 'egw_tokens';
	const PREFIX = 'token';
	const TOKEN_REGEXP = '/^'.self::PREFIX.'(\d+)_(.*)$/';

	/**
	 * Constructor
	 * @throws Api\Exception\WrongParameter
	 */
	public function __construct()
	{
		parent::__construct(self::APP, self::TABLE, null, '', true, 'object');
		$this->convert_all_timestamps();
	}

	/**
	 * Check if given token / password looks like a token
	 *
	 * @param string $token
	 * @return bool
	 */
	public static function isToken(string $token)
	{
		return (bool)preg_match(self::TOKEN_REGEXP, $token);
	}

	/**
	 * Authenticate a user with a token
	 *
	 * @param string $user
	 * @param string $token must start with "token<token_id>:", or function will return null
	 * @param ?array& $limits on return limits of token
	 * @return bool|null null: $token is no token, probably a password, false: invalid token, true: valid token for $user
	 */
	public static function authenticate(string $user, string $token, array& $limits=null)
	{
		if (!preg_match(self::TOKEN_REGEXP, $token, $matches))
		{
			return null;    // not a token
		}
		try {
			$log_passwd = substr($token, 0, strlen(self::PREFIX)+1+strlen($matches[1]));
			$log_passwd .= str_repeat('*', strlen($token)-strlen($log_passwd));
			$data = self::getInstance()->read([
				'token_id' => $matches[1],
				'account_id' => [0, Api\Accounts::getInstance()->name2id($user)],
				'token_revoked' => null,
				'(token_valid_until IS NULL OR token_valid_until > NOW())'
			]);
			if (!password_verify($matches[2], $data['token_hash']))
			{
				Api\Auth::log(__METHOD__."('$user', '$log_passwd', ...') returned false (no active token found)");
				return false;   // invalid token password
			}
			$limits = $data['token_limits'];
			Api\Auth::log(__METHOD__."('$user', '$log_passwd', ...) returned true");
			return true;
		}
		catch (Api\Exception\NotFound $e) {
			Api\Auth::log(__METHOD__."('$user', '$log_passwd, ...) returned false: ".$e->getMessage());
			return false;   // token not found
		}
	}

	/**
	 * Create a token and return it
	 *
	 * @param int $account_id
	 * @param ?\DateTimeInterface $until
	 * @param ?string $remark
	 * @param ?array $limits app-name => rights pairs, run rights are everything evaluation to true,
	 * the rights can be an array with more granulate rights, but the app needs to check this itself!
	 * @return array full token record plus token under key "token"
	 * @throws Api\Exception\NoPermission\Admin if non-admin user tries to create token for anyone else
	 * @throws Api\Exception\NotFound if token_id does NOT exist
	 * @throws Api\Db\Exception if token could not be stored
	 */
	public static function create(int $account_id, \DateTimeInterface $until=null, string $remark=null, array $limits=null): array
	{
		if (empty($GLOBALS['egw_info']['user']['apps']['admin']))
		{
			$account_id = $GLOBALS['egw_info']['user']['account_id'];
		}
		$inst = self::getInstance();
		$inst->init([
			'account_id' => $account_id,
			'new_token' => true,
			'token_valid_until' => $until,
			'token_remark' => $remark,
			'token_limits' => $limits,
		]);
		$inst->save();

		return $inst->data;
	}

	/**
	 * Revoke or (re-)activate a token
	 *
	 * @param int $token_id
	 * @param bool $revoke true: revoke, false: (re-)activate
	 * @throws Api\Exception\NoPermission\Admin if non-admin user tries to create token for anyone else
	 * @throws Api\Exception\NotFound if token_id does NOT exist
	 * @throws Api\Db\Exception if token could not be stored
	 */
	public static function revoke(int $token_id, bool $revoke=true)
	{
		$inst = self::getInstance();
		$inst->read($token_id);
		return $inst->save([
			'token_revoked_by' => $GLOBALS['egw_info']['user']['account_id'],
			'token_revoked' => $revoke ? $inst->now : null,
		]);
	}

	/**
	 * saves the content of data to the db
	 *
	 * @param array $keys =null if given $keys are copied to data before saveing => allows a save as
	 * @param string|array $extra_where =null extra where clause, eg. to check an etag, returns true if no affected rows!
	 * @return int|boolean 0 on success, or errno != 0 on error, or true if $extra_where is given and no rows affected
	 * @throws Api\Exception\NoPermission\Admin if non-admin user tries to create token for anyone else
	 * @throws Api\Db\Exception if token could not be stored
	 */
	function save($keys=null,$extra_where=null)
	{
		if (is_array($keys) && count($keys)) $this->data_merge($keys);

		if (empty($GLOBALS['egw_info']['user']['apps']['admin']) && $this->data['account_id'] != $GLOBALS['egw_info']['user']['account_id'])
		{
			throw new Api\Exception\NoPermission\Admin();
		}

		if (empty($this->data['token_id']))
		{
			$this->data['token_created_by'] = $GLOBALS['egw_info']['user']['account_id'];
			$this->data['token_created'] = $this->now;
		}
		else
		{
			$this->data['token_updated_by'] = $GLOBALS['egw_info']['user']['account_id'];
			$this->data['token_updated'] = $this->now;
		}
		if (!empty($keys['new_token']))
		{
			$token = Api\Auth::randomstring(16);
			$this->data['token_hash'] = password_hash($token, PASSWORD_DEFAULT);
			$this->data['token_revoked'] = null;
		}
		if (($ret = parent::save(null, $extra_where)))
		{
			throw new Api\Db\Exception(lang('Error storing token'));
		}
		if (isset($token))
		{
			$this->data['token'] = self::PREFIX.$this->data['token_id'].'_'.$token;
		}
		return $ret;
	}

	/**
	 * reads row matched by key and puts all cols in the data array
	 *
	 * @param array $keys array with keys in form internalName => value, may be a scalar value if only one key
	 * @param string|array $extra_cols ='' string or array of strings to be added to the SELECT, eg. "count(*) as num"
	 * @param string $join ='' sql to do a join, added as is after the table-name, eg. ", table2 WHERE x=y" or
	 * @return array data if row could be retrieved
	 * @throws Api\Exception\NotFound if entry was NOT found
	 */
	function read($keys,$extra_cols='',$join='')
	{
		if (!($data = parent::read($keys, $extra_cols, $join)))
		{
			throw new Api\Exception\NotFound();
		}
		return $data;
	}

	/**
	 * Convert limits to allowed apps
	 *
	 * @param array|null $limits
	 * @return array of app-names
	 */
	public static function limits2apps(array $limits=null): array
	{
		return $limits ? array_keys(array_filter($limits)) : [];
	}

	/**
	 * Convert apps to (default, value === true) limits
	 *
	 * @param array $apps
	 * @return array|null
	 */
	public static function apps2limits(array $apps): ?array
	{
		return $apps ? array_combine($apps, array_fill(0, count($apps), true)) : null;
	}

	/**
	 * Changes the data from the db-format to your work-format
	 *
	 * @param array $data =null if given works on that array and returns result, else works on internal data-array
	 * @return array
	 */
	function db2data($data=null)
	{
		if (($intern = !is_array($data)))
		{
			$data =& $this->data;
		}

		if (is_string($data['token_limits']))
		{
			$data['token_limits'] = json_decode($data['token_limits'], true);
		}

		return parent::db2data($intern ? null : $data);	// important to use null, if $intern!
	}

	/**
	 * Changes the data from your work-format to the db-format
	 *
	 * @param array $data =null if given works on that array and returns result, else works on internal data-array
	 * @return array
	 */
	function data2db($data=null)
	{
		if (($intern = !is_array($data)))
		{
			$data =& $this->data;
		}

		if (is_array($data['token_limits']))
		{
			$data['token_limits'] = $data['token_limits'] ? json_encode($data['token_limits']) : null;
		}

		return parent::data2db($intern ? null : $data);
	}

	private static self $instance;
	public static function getInstance()
	{
		if (!isset(self::$instance))
		{
			self::$instance = new self();
		}
		return self::$instance;
	}
}