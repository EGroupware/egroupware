<?php
/**
 * EGroupware Setup - Account import from LDAP (incl. ADS) to SQL
 *
 * @link https://www.egroupware.org
 * @package setup
 * @author Ralf Becker <rb@egroupware.org>
 * @license https://opensource.org/licenses/gpl-license.php GPL - GNU General Public License
 */

namespace EGroupware\Api\Accounts;

use EGroupware\Api;

/**
 * Account import from LDAP (incl. ADS) to SQL
 *
 * @todo check that ADS and LDAP update modification time of account, if memberships change
 */
class Import
{
	public function __construct()
	{
		// if we run from setup, we need to take care of loading db and egw_info/server
		if (isset($GLOBALS['egw_setup']))
		{
			if (!is_object($GLOBALS['egw_setup']->db))
			{
				$GLOBALS['egw_setup']->loaddb();
			}
			$GLOBALS['egw_info']['server'] += Api\Config::read('phpgwapi');
		}
	}

	/**
	 * @param bool $initial_import true: initial sync, false: incremental sync
	 * @param callable|null $logger function($str, $level) level: "debug", "detail", "info", "error" or "fatal"
	 * @return array with int values for keys 'created', 'updated', 'uptodate', 'errors' and string 'result'
	 * @throws \Exception also gets logged as level "fatal"
	 * @throws \InvalidArgumentException if not correctly configured
	 */
	public function run(bool $initial_import=true, callable $logger=null)
	{
		try {
			if (!isset($logger))
			{
				$logger = static function($str, $level){};
			}

			// determine from where we migrate to what
			if (!in_array($source = $GLOBALS['egw_info']['server']['account_import_source'], ['ldap', 'ads']))
			{
				throw new \InvalidArgumentException("Invalid account_import_source='{$GLOBALS['egw_info']['server']['account_import_source']}'!");
			}
			if (!in_array($type = $GLOBALS['egw_info']['server']['account_import_type'], ['users', 'users_groups']))
			{
				throw new \InvalidArgumentException("Invalid account_import_type='{$GLOBALS['egw_info']['server']['account_import_type']}'!");
			}
			if (!in_array($delete = $GLOBALS['egw_info']['server']['account_import_delete'], ['yes', 'deactivate', 'no']))
			{
				throw new \InvalidArgumentException("Invalid account_import_delete='{$GLOBALS['egw_info']['server']['account_import_delete']}'!");
			}
			if (!$initial_import && empty($GLOBALS['egw_info']['server']['account_import_lastrun']))
			{
				throw new \InvalidArgumentException(lang("You need to run the inital import first!"));
			}

			$class = 'EGroupware\\Api\\Contacts\\'.ucfirst($source);
			/** @var Api\Contacts\Ldap $contacts */
			$contacts = new $class($GLOBALS['egw_info']['server']);
			$contacts_sql = new Api\Contacts\Sql();

			$class = 'EGroupware\\Api\\Accounts\\'.ucfirst($source);
			/** @var Api\Accounts\Ldap $accounts */
			$accounts = new $class(new Api\Accounts(['account_repository' => $source]+$GLOBALS['egw_info']['server']));
			$accounts_sql = new Api\Accounts\Sql(new Api\Accounts(['account_repository' => 'sql']+$GLOBALS['egw_info']['server']));
			Api\Accounts::cache_invalidate();   // to not get any cached data eg. from the wrong backend

			$created = $updated = $uptodate = $errors = $deleted = 0;
			if (in_array('groups', explode('_', $type)))
			{
				[$created, $updated, $uptodate, $errors, $deleted] = $this->groups(
					$initial_import ? null : $GLOBALS['egw_info']['server']['account_import_lastrun'],
					$accounts, $accounts_sql, $logger, $delete, $groups);
			}

			$filter = [
				'owner' => '0',
			];
			if (!$initial_import)
			{
				$filter[] = 'modified>='.$GLOBALS['egw_info']['server']['account_import_lastrun'];
			}
			$last_modified = null;
			$start_import = time();
			$cookie = '';
			$start = ['', 5, &$cookie]; // cookie must be a reference!
			do
			{
				foreach ($contacts->search('', false, '', 'account_lid', '', '', 'AND', $start, $filter) as $contact)
				{
					$new = null;
					if (!isset($last_modified) || (int)$last_modified < (int)$contact['modified'])
					{
						$last_modified = $contact['modified'];
					}
					$account = $accounts->read($contact['account_id']);
					$logger(json_encode($contact + $account), 'debug');
					// check if account exists in sql
					if (!($account_id = $accounts_sql->name2id($account['account_lid'])))
					{
						$sql_account = $account;
						// check if account_id is not yet taken by another user or group --> unset it to let DB assign a new one
						if ($accounts_sql->read($account['account_id']))
						{
							unset($sql_account['account_id']);
						}
						if (($account_id = $accounts_sql->save($sql_account, true)) > 0)
						{
							$logger("Successful created user '$account[account_lid]' (#$account[account_id]".
								($account['account_id'] != $account_id ? " as #$account_id" : '').')', 'detail');
						}
						else
						{
							$logger("Error creaing user '$account[account_lid]' (#$account[account_id])", 'error');
							$errors++;
							continue;
						}
					}
					elseif ($account_id < 0)
					{
						throw new \Exception("User '$account[account_lid]' already exists as group!");
					}
					elseif (!($sql_account = $accounts_sql->read($account_id)))
					{
						throw new \Exception("User '$account[account_lid]' (#$account_id) should exist, but not found!");
					}
					else
					{
						// ignore LDAP specific fields, and empty fields
						$relevant = array_filter(array_intersect_key($account, $sql_account), static function ($attr) {
							return $attr !== null && $attr !== '';
						});
						unset($relevant['person_id']);  // is always different as it's the UID, no need to consider
						$to_update = $relevant + $sql_account;
						// fix accounts without firstname
						if (!isset($to_update['account_firstname']) && $to_update['account_lastname'] === $to_update['account_fullname'])
						{
							$to_update['account_firstname'] = null;
						}
						if (($diff = array_diff_assoc($to_update, $sql_account)))
						{
							if ($accounts_sql->save($to_update) > 0)
							{
								$logger("Successful updated user '$account[account_lid]' (#$account_id): " .
									json_encode($diff, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES), 'detail');
								if (!$new) $new = false;
							}
							else
							{
								$logger("Error updating user '$account[account_lid]' (#$account_id)", 'error');
								$errors++;
								continue;
							}
						}
						else
						{
							$logger("User '$account[account_lid]' (#$account_id) already up to date", 'debug');
						}
					}
					if (!($sql_contact = $contacts_sql->read(['account_id' => $account_id])))
					{
						$sql_contact = $contact;
						unset($sql_contact['id']);  // LDAP contact-id is the UID!
						if (!$contacts_sql->save($sql_contact))
						{
							$sql_contact['id'] = $contacts_sql->data['id'];
							$logger("Successful created contact for user '$account[account_lid]' (#$account_id)", 'detail');
							$new = true;
						}
						else
						{
							$logger("Error creating contact for user '$account[account_lid]' (#$account_id)", 'error');
							$errors++;
							continue;
						}
					}
					else
					{
						$to_update = array_merge($sql_contact, array_filter($contact, static function ($attr) {
							return $attr !== null && $attr !== '';
						}));
						$to_update['id'] = $sql_contact['id'];
						if (($diff = array_diff_assoc($to_update, $sql_contact)))
						{
							if ($contacts_sql->save($to_update) === 0)
							{
								$logger("Successful updated contact data of '$account[account_lid]' (#$account_id): ".
									json_encode($diff, JSON_UNESCAPED_SLASHES|JSON_UNESCAPED_UNICODE), 'detail');
								if (!$new) $new = false;
							}
							else
							{
								$logger("Error updating contact data of '$account[account_lid]' (#$account_id)", 'error');
								++$errors;
								continue;
							}
						}
						else
						{
							$logger("Contact data of '$account[account_lid]' (#$account_id) already up to date", 'debug');
						}
					}
					// if requested, also set memberships
					if ($type === 'users_groups')
					{
						// we need to convert the account_id's of memberships, in case we use different ones in SQL
						$accounts_sql->set_memberships(array_filter(array_map(static function($account_lid) use ($groups)
						{
							return array_search($account_lid, $groups);
						}, $account['memberships'])), $account_id);
					}
					if ($new)
					{
						++$created;
					}
					elseif ($new === false)
					{
						++$updated;
					}
					else
					{
						++$uptodate;
					}
				}
			}
			while ($start[2] !== '');

			$last_run = max($start_import-1, $last_modified);
			Api\Config::save_value('account_import_lastrun', $last_run, 'phpgwapi');
			$str = gmdate('Y-m-d H:i:s', $last_run). ' UTC';
			if (!$errors)
			{
				$logger("Setting new incremental import time to: $str ($last_run)", 'detail');
			}
			if ($created || $updated || $errors || $deleted)
			{
				$result = "Created $created, updated $updated and deleted $deleted accounts, with $errors errors.";
			}
			else
			{
				$result = "All accounts are up-to-date.";
			}
			$logger($result, 'info');

			if ($initial_import && self::installAsyncJob())
			{
				$logger('Async job for periodic import installed', 'info');
			}
		}
		catch(\Exception $e) {
			$logger($e->getMessage(), 'fatal');
			throw $e;
		}
		return [
			'created'  => $created,
			'updated'  => $updated,
			'uptodate' => $uptodate,
			'errors'   => $errors,
			'deleted'  => $deleted,
			'result'   => $result,
		];
	}

	/**
	 * Import all groups
	 *
	 * We assume we can list all groups without running into memory or timeout issues.
	 * Groups with identical names as users are skipped, but logged as error.
	 *
	 * We can only delete no longer existing groups, if we query all groups!
	 * So $delete !== 'no', requires $modified === null.
	 *
	 * @param Ldap|Ads $accounts
	 * @param Sql $accounts_sql
	 * @param callable $logger function($str, $level) level: "debug", "detail", "info", "error" or "fatal"
	 * @param string $delete what to do with no longer existing groups: "yes": delete incl. data, "deactivate": delete group, "no": do nothing
	 * @param int|null $modified null: initial import, int: timestamp of last import
	 * @param array|null &$groups on return all current groups as account_id => account_lid pairs
	 * @return array with int values [$created, $updated, $uptodate, $errors, $deleted]
	 */
	protected function groups($modified, object $accounts, Sql $accounts_sql, callable $logger, string $delete, array &$groups=null)
	{
		// to delete no longer existing groups, we have to query all groups!
		if ($delete !== 'no')
		{
			$modified = null;
		}

		// query all groups in SQL
		$sql_groups = $groups = [];
		foreach($GLOBALS['egw']->db->select(Sql::TABLE, 'account_id,account_lid', ['account_type' => 'g'], __LINE__, __FILE__) as $row)
		{
			$sql_groups[-$row['account_id']] = $row['account_lid'];
		}
		// fill groups with existing ones, for incremental sync, as we need to return all groups
		if (!empty($modified))
		{
			$groups = $sql_groups;
		}

		$created = $updated = $uptodate = $errors = $deleted = 0;
		foreach($accounts->search(['type' => 'groups', 'modified' => $modified]) as $account_id => $group)
		{
			$logger(json_encode($group, JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES), 'debug');

			if (!($sql_id = array_search($group['account_lid'], $sql_groups)))
			{
				if ($accounts_sql->name2id($group['account_lid']) > 0)
				{
					$logger("Group '$group[account_lid]' already exists as user --> skipped!", 'error');
					$errors++;
					continue;
				}
				// check if the numeric account_id is not yet taken --> unset account_id and let DB create a new one
				if ($accounts_sql->read($account_id))
				{
					unset($group['account_id']);
				}
				if (($sql_id = $accounts_sql->save($group, true)) < 0)
				{
					$logger("Successful created group '$group[account_lid]' (#$account_id".($sql_id != $account_id ? " as #$sql_id" : '').')', 'detail');
					$created++;
				}
				else
				{
					$logger("Error creating group '$group[account_lid]' (#$account_id)", 'error');
					$errors++;
				}
			}
			elseif (!($sql_group = $accounts_sql->read($sql_id)))
			{
				throw new \Exception("Group '$group[account_lid]' (#$sql_id) should exist, but not found!");
			}
			else
			{
				$group['account_id'] = $sql_id;
				unset($sql_group['account_fullname'], $sql_group['account_firstname'], $sql_group['account_lastname']); // not stored anywhere
				// ignore LDAP specific fields, and empty fields
				$relevant = array_filter(array_intersect_key($group, $sql_group), static function ($attr) {
					return $attr !== null && $attr !== '';
				});
				$to_update = $relevant + $sql_group;
				if (($diff = array_diff_assoc($to_update, $sql_group)))
				{
					if ($accounts_sql->save($group, true) > 0)
					{
						$logger("Successful updated group '$group[account_lid]' (#$sql_id): " .
							json_encode($diff, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE), 'detail');
						$updated++;
					}
					else
					{
						$logger("Error updating group '$group[account_lid]' (#$sql_id)", 'error');
						$errors++;
					}
				}
				else
				{
					$logger("Group '$group[account_lid]' (#$sql_id) already up to date", 'debug');
					$uptodate++;
				}
				// unset the updated groups, so we can delete the ones not returned from LDAP
				unset($sql_groups[$sql_id]);
			}
			$groups[$sql_id] = $group['account_lid'];
		}

		// delete the groups not returned from LDAP, groups can NOT be deactivated, we just delete them in the DB
		foreach($delete !== 'no' ? $sql_groups : [] as $account_id => $account_lid)
		{
			static $acl=null;
			if ($delete === 'yes')
			{
				try {
					$cmd = new \admin_cmd_delete_account($account_id);
					$cmd->run();
				}
				catch (\Exception $e) {
					$logger("Error deleting no longer existing group '$account_lid' (#$account_id).", 'error');
					$errors++;
				}
			}
			// still run the SQL commands, as an LDAP/ADS system will not run them
			if ($accounts_sql->delete($account_id))
			{
				if (!isset($acl)) $acl = new Api\Acl();
				$acl->delete_account($account_id);
				$logger("Successful deleted no longer existing group '$account_lid' (#$account_id).", 'detail');
				$deleted++;
			}
			elseif (!isset($e))
			{
				$logger("Error deleting no longer existing group '$account_lid' (#$account_id).", 'error');
				$errors++;
			}
		}
		return [$created, $updated, $uptodate, $errors, $deleted];
	}

	/**
	 * Hook called when setup configuration is being stored:
	 * - install/removing cron job to periodic import accounts from LDAP/ADS
	 *
	 * @param array $location key "newsettings" with reference to changed settings from setup > configuration
	 * @throws \Exception for errors
	 */
	public static function setupConfig(array $location)
	{
		$config =& $location['newsettings'];

		// check if periodic import is configured AND initial sync already done
		foreach(['account_import_type', 'account_import_source', 'account_import_frequency'] as $name)
		{
			if (empty($config[$name]))
			{
				self::installAsyncJob();
				return;
			}
		}
		if (empty(Api\Config::read('phpgwapi')['account_import_lastrun']))
		{
			self::installAsyncJob();
		}

		self::installAsyncJob((float)$config['account_import_frequency'], $config['account_import_time']);
	}

	const ASYNC_JOB_ID = 'AccountsImport';

	/**
	 * Install async job for periodic import, if configured
	 *
	 * @param float $frequency
	 * @param string|null $time
	 * @return bool true: job installed, false: job canceled, if it was already installed
	 */
	protected static function installAsyncJob(float $frequency=0.0, string $time=null)
	{
		$async = new Api\Asyncservice();
		$async->cancel_timer(self::ASYNC_JOB_ID);

		if (empty($frequency) && !empty($time) && preg_match('/^\d{2}:\d{2}$/', $time))
		{
			$frequency = 24;
		}
		if ($frequency > 0.0)
		{
			[$hour, $min] = explode(':', $time ?: '00:00');
			$times = ['hour' => (int)$hour, 'min' => (int)$min];
			if ($frequency >= 36)
			{
				$times['day'] = '*/'.round($frequency/24.0);    // 48h => day: */2
			}
			elseif ($frequency >= 24)
			{
				$times['day'] = '*';
			}
			elseif ($frequency >= 1)
			{
				$times['hour'] = round($frequency) == 1 ? '*' : '*/'.round($frequency);
			}
			elseif ($frequency >= .1)
			{
				$times = ['min' => '*/'.(5*round(12*$frequency))];   // .1 => */5, .5 => */30
			}
			$async->set_timer($times, self::ASYNC_JOB_ID, self::class.'::async');

			return true;
		}

		return false;
	}

	const LOG_FILE = 'setup/account-import.log';

	/**
	 * Run incremental import via async job
	 *
	 * @return void
	 */
	public static function async()
	{
		try {
			$import = new self();
			$log = $GLOBALS['egw_info']['server']['files_dir'].'/'.self::LOG_FILE;
			if (!file_exists($dir=dirname($log)) && !mkdir($dir) || !is_dir($dir) ||
				!($fp = fopen($log, 'a+')))
			{
				$logger = static function($str, $level)
				{
					if (!in_array($level, ['debug', 'detail']))
					{
						error_log(__METHOD__.' '.strtoupper($level).' '.$str);
					}
				};
			}
			else
			{
				$logger = static function($str, $level) use ($fp)
				{
					if (!in_array($level, ['debug', 'detail']))
					{
						fwrite($fp, date('Y-m-d H:i:s O').' '.strtoupper($level).' '.$str."\n");
					}
				};
			}
			$logger(date('Y-m-d H:i:s O').' LDAP account import started', 'info');
			$import->run(false, $logger);
			$logger(date('Y-m-d H:i:s O').' LDAP account import finished'.(!empty($fp)?"\n":''), 'info');
		}
		catch (\InvalidArgumentException $e) {
			_egw_log_exception($e);
			// disable async job, something is not configured correct
			self::installAsyncJob();
			$logger('Async job for periodic import canceled', 'fatal');
		}
		catch (\Exception $e) {
			_egw_log_exception($e);
		}
		if (!empty($fp)) fclose($fp);
	}

	/**
	 * Tail the async import log
	 *
	 * @return void
	 * @throws Api\Exception\WrongParameter
	 * @todo get this working in setup
	 */
	public function showLog()
	{
		echo (new Api\Framework\Minimal())->header(['pngfix' => '']);
		$tailer = new Api\Json\Tail(self::LOG_FILE);
		echo $tailer->show();
	}
}