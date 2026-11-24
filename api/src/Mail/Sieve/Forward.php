<?php
/**
 * EGroupware Api: Forwarding of all mail via a Sieve rule "Forward mail to ..."
 *
 * Used by Smtp\Stalwart, which has no forwarding of its own, and by the migration of Dovecot/Postfix accounts.
 *
 * @link https://www.egroupware.org
 * @package api
 * @subpackage mail
 * @author Ralf Becker <rb@egroupware.org>
 * @license https://opensource.org/license/gpl-2-0 GPL 2.0+ - GNU General Public License 2.0 or any higher version of your choice
 */

namespace EGroupware\Api\Mail\Sieve;

/**
 * Forward all (not spam) mail by an unconditional Sieve redirect rule, which shows up as ordinary rule in Mail > Filter
 *
 * Only scripts EGroupware's Script class understands (JSON comment, legacy "#rule&&" lines) are read or changed.
 * get() ignores everything else, set() throws, if a script exists, which we cannot parse, so it is never overwritten.
 */
class Forward
{
	/**
	 * Description of the rule we create and recognize
	 */
	const DESCRIPTION = 'Forward mail to ';

	/**
	 * Name of the script, if the account has none
	 */
	const DEFAULT_SCRIPT_NAME = 'mail';

	/**
	 * Rule template: forward ALL mail (condition From matches * is only there, as the rule must not be empty)
	 */
	const RULE = [
		'description' => '', 'tabs' => 'action', 'anyof' => '0', 'from' => '*', 'to' => '', 'subject' => '', 'gthan' => '0',
		'size' => '0', 'field' => '', 'comparator' => 'contains', 'field_val' => '', 'bodytransform' => '0',
		'field_bodytransform' => '', 'ctype' => '0', 'field_ctype_val' => '', 'action' => 'address', 'continue' => true,
		'keep' => false, 'regexp' => false, 'tokenized' => false, 'ruleID' => 2, 'priority' => 2, 'status' => 'ENABLED',
		'action_arg' => '', 'action_flags' => '', 'flg' => 0,
	];

	/**
	 * Get the forward addresses of an account
	 *
	 * @param Connection $connection
	 * @param string|null $scriptName =null default active script
	 * @return array|null null if there is no script, no forward rule or a script we do not understand,
	 *  otherwise array with values for keys "addresses" (array of bare addresses) and "keep" (bool: keep a copy)
	 */
	public static function get(Connection $connection, ?string $scriptName=null) : ?array
	{
		try
		{
			$script = self::load($connection, $scriptName, $exists);
		}
		catch (\Throwable $e)
		{
			unset($e);
			return null;
		}
		if (!$exists || !$script)
		{
			return null;
		}
		foreach ($script->rules as $rule)
		{
			if (self::isForwardRule($rule))
			{
				return [
					'addresses' => self::addresses($rule['action_arg']),
					'keep' => !empty($rule['keep']),
				];
			}
		}
		return null;
	}

	/**
	 * Set, change or remove (empty $addresses) the forward of an account
	 *
	 * @param Connection $connection
	 * @param string[] $addresses bare addresses, empty to remove the forward rule
	 * @param bool $keep =false true: keep a copy of the mail in the mailbox
	 * @param string|null $scriptName =null default active script
	 * @return bool true if the script was changed, false if it already was as requested
	 * @throws \InvalidArgumentException for an invalid address
	 * @throws \Exception if a script exists, which is not in a format of EGroupware (it is not touched) or storing it failed
	 */
	public static function set(Connection $connection, array $addresses, bool $keep=false, ?string $scriptName=null) : bool
	{
		$addresses = self::addresses($addresses);
		foreach ($addresses as $address)
		{
			if (!preg_match('/^[^\s@<>,;"]+@[^\s@<>,;"]+$/', $address))
			{
				throw new \InvalidArgumentException("Invalid forward address '$address'!");
			}
		}
		$script = self::load($connection, $scriptName, $exists, $error);
		if (!$script)
		{
			if (!$addresses)
			{
				return false;   // a script we do not understand has no forward of ours to remove
			}
			throw new \Exception("Sieve script is not in a format of EGroupware ($error), forwards can not be set there!");
		}
		$changed = false;
		$first = null;
		foreach ($script->rules as $i => $rule)
		{
			if (!self::isForwardRule($rule))
			{
				continue;
			}
			if ($first !== null || !$addresses)
			{
				unset($script->rules[$i]);    // duplicate or forward removed
				$changed = true;
			}
			else
			{
				$first = $i;
				if (array_map('strtolower', self::addresses($rule['action_arg'])) != array_map('strtolower', $addresses) ||
					!empty($rule['keep']) !== $keep)
				{
					$script->rules[$i]['description'] = self::DESCRIPTION.implode(', ', $addresses);
					$script->rules[$i]['action_arg'] = implode(',', $addresses);
					$script->rules[$i]['keep'] = $keep;
					$changed = true;
				}
			}
		}
		$script->rules = array_values($script->rules);
		if ($addresses && $first === null)
		{
			$rule = self::RULE;
			$rule['description'] = self::DESCRIPTION.implode(', ', $addresses);
			$rule['action_arg'] = implode(',', $addresses);
			$rule['keep'] = $keep;
			$rule['ruleID'] = $script->rules ? max(array_map(static fn($r) => (int)($r['ruleID'] ?? 0), $script->rules)) + 1 : 1;
			// behind a SpamTitan rule (X-Spam-Flag -> Junk), so spam does not get forwarded
			$pos = $script->rules && self::isSpamRule($script->rules[0]) ? 1 : 0;
			array_splice($script->rules, $pos, 0, [$rule]);
			$changed = true;
		}
		if ($addresses && $script->rules && self::isSpamRule($script->rules[0]) && empty($script->rules[0]['stop']))
		{
			$script->rules[0]['stop'] = true;
			$changed = true;
		}
		if (!$changed)
		{
			return false;
		}
		// nothing to remove, if there was no script
		if (!$exists && !$script->rules)
		{
			return false;
		}
		$vac_rule = null;
		if (!$script->updateScript($connection, false, $vac_rule, true))
		{
			throw new \Exception("Storing Sieve script '{$script->name}' failed: ".$script->errstr);
		}
		return true;
	}

	/**
	 * Load the script
	 *
	 * @param Connection $connection
	 * @param string|null $scriptName =null default active script
	 * @param bool|null &$exists on return: true if the script exists on the server
	 * @param string|null &$error on return: reason, if the script exists, but we do not understand it
	 * @return Script|null null if the script exists, but we do not understand it
	 */
	protected static function load(Connection $connection, ?string $scriptName, ?bool &$exists=null, ?string &$error=null) : ?Script
	{
		if (!$scriptName)
		{
			try
			{
				$scriptName = $connection->getActive();
			}
			catch (\Throwable $e)
			{
				unset($e);   // no active script
			}
			$scriptName = $scriptName ?: self::DEFAULT_SCRIPT_NAME;
		}
		$script = new Script($scriptName);
		$exists = in_array($scriptName, (array)($connection->listScripts()[0] ?? []));
		if (!$exists)
		{
			return $script;
		}
		// Script::retrieveRules() is not free of warnings (eg. scripts without rules)
		$old = error_reporting(error_reporting() & ~E_WARNING & ~E_DEPRECATED);
		try
		{
			$ok = $script->retrieveRules($connection);
		}
		finally
		{
			error_reporting($old);
		}
		if (!$ok)
		{
			$error = "script '{$script->name}': ".($script->errstr ?: 'unknown format');
			return null;
		}
		return $script;
	}

	/**
	 * Is the rule one created by us
	 */
	public static function isForwardRule(array $rule) : bool
	{
		return ($rule['action'] ?? '') === 'address' && str_starts_with($rule['description'] ?? '', self::DESCRIPTION);
	}

	/**
	 * Is the rule the SpamTitan rule: X-Spam-Flag into Junk
	 */
	public static function isSpamRule(array $rule) : bool
	{
		return trim($rule['field'] ?? '') === 'X-Spam-Flag' && ($rule['action'] ?? '') === 'folder' &&
			($rule['action_arg'] ?? '') === 'Junk' && ($rule['status'] ?? 'ENABLED') === 'ENABLED';
	}

	/**
	 * Bare, unique addresses from an array or the comma-separated action_arg of a rule
	 *
	 * @param string|array $addresses
	 * @return string[]
	 */
	protected static function addresses(string|array $addresses) : array
	{
		if (is_string($addresses))
		{
			$addresses = preg_split('/\s*[,;]\s*/', $addresses);
		}
		$bare = [];
		foreach ($addresses as $address)
		{
			// "Name <user@example.org>" --> user@example.org
			$address = trim(preg_match('/<([^>]+)>/', $address, $m) ? $m[1] : $address);
			if ($address !== '' && !in_array($address, $bare, true))
			{
				$bare[] = $address;
			}
		}
		return $bare;
	}
}
