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
	}

	/**
	 * Authenticate a user with a token
	 *
	 * @param string $user
	 * @param string $token must start with "token<token_id>:", or function will return null
	 * @param ?array& $limits on return limits of token
	 * @return bool|null null: $token is no token, probably a password, false: invalid token, true: valid token for $user
	 * @throws \Exception
	 */
	public static function authenticate(string $user, string $token, array& $limits=null)
	{
		if (!preg_match(self::TOKEN_REGEXP, $token, $matches))
		{
			return null;    // no a token
		}
		if (!($data = self::getInstance()->read([
				'token_id' => $matches[1],
				'account_id' => [0, Api\Accounts::getInstance()->name2id($user)],
				'token_revoked' => null,
				'(token_valid_until IS NULL OR token_valid_until > NOW())'
			])) || !password_verify($matches[2], $data['token_hash']))
		{
			return false;   // wrong/invalid token
		}
		$limits = $data['token_limits'] ? json_decode($data['token_limits'], true) : null;
		return true;
	}

	/**
	 * Create a token and return it
	 *
	 * @param int $account_id
	 * @param ?DateTime $until
	 * @param ?string $remark
	 * @param ?array $limits app-name => rights pairs, run rights are everything evaluation to true,
	 * the rights can be an array with more granulate rights, but the app needs to check this itself!
	 * @return string
	 */
	public static function create(int $account_id, DateTime $until=null, string $remark=null, array $limits=null): string
	{
		$token = Api\Auth::randomstring(16);
		$inst = self::getInstance();
		$inst->init([
			'account_id' => $account_id,
			'token_hash' => password_hash($token, PASSWORD_DEFAULT),
			'token_created' => new Api\DateTime(),
			'token_created_by' => $GLOBALS['egw_info']['user']['account_id'],
			'token_valid_until' => $until,
			'token_remark' => $remark,
			'token_limits' => $limits ? json_encode($limits) : null,
		]);
		if (!($token_id = $inst->save()))
		{
			throw new Api\Exception('Error storing token');
		}
		return self::PREFIX.$token_id.'_'.$token;
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