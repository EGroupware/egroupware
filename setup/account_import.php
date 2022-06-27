<?php
/**
 * EGroupware Setup - Account import from LDAP (incl. ADS) to SQL
 *
 * The migration is done from the account-repository configured for EGroupware!
 *
 * @link http://www.egroupware.org
 * @package setup
 * @author Ralf Becker <RalfBecker-AT-outdoor-training.de>
 * @license http://opensource.org/licenses/gpl-license.php GPL - GNU General Public License
 */

use EGroupware\Api;

include('./inc/functions.inc.php');

// Authorize the user to use setup app and load the database
if (!$GLOBALS['egw_setup']->auth('Config') || $_POST['cancel'])
{
	Header('Location: index.php');
	exit;
}
// Does not return unless user is authorized

// check CSRF token for POST requests with any content (setup uses empty POST to call its modules!)
if ($_SERVER['REQUEST_METHOD'] == 'POST' && $_POST)
{
	Api\Csrf::validate($_POST['csrf_token'], __FILE__);
}

// determine from where we migrate to what
if (!is_object($GLOBALS['egw_setup']->db))
{
	$GLOBALS['egw_setup']->loaddb();
}
// Load configuration values account_repository and auth_type, as setup has not yet done so
foreach($GLOBALS['egw_setup']->db->select($GLOBALS['egw_setup']->config_table,'config_name,config_value',
	"config_name LIKE 'ldap%' OR config_name LIKE 'account_%' OR config_name LIKE '%encryption%' OR ".
	"config_name IN ('auth_type','install_id','mail_suffix') OR config_name LIKE 'ads_%' OR config_name LIKE 'account_import_%'",
	__LINE__,__FILE__) as $row)
{
	$GLOBALS['egw_info']['server'][$row['config_name']] = $row['config_value'];
}

try {
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

	if (!($initial_import=!empty($_REQUEST['initial'])) && empty($GLOBALS['egw_info']['server']['account_import_lastrun']))
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

	$filter = [
		'owner' => '0',
	];
	if (!$initial_import)
	{
		$filter[] = 'modified>='.$GLOBALS['egw_info']['server']['account_import_lastrun'];
	}
	$last_modified = null;
	$start_import = time();
	$created = $updated = $uptodate = $errors = 0;
	$cookie = '';
	$start = ['', 5, &$cookie];
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
			echo "<p>" . json_encode($contact + $account) . "</p>\n";
			// check if account exists in sql
			if (!($account_id = $accounts_sql->name2id($account['account_lid'])))
			{
				$sql_account = $account;
				if ($accounts_sql->save($account, true) > 0)
				{
					echo "<p>Successful created user '$account[account_lid]' (#$account_id)<br/>\n";
					$new;
				}
				else
				{
					echo "<p><b>Error creaing user '$account[account_lid]' (#$account_id)<br/>\n";
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
						echo "<p>Successful updated user '$account[account_lid]' (#$account_id): " . json_encode($diff, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) . "<br/>\n";
						if (!$new) $new = false;
					}
					else
					{
						echo "<p><b>Error updating user '$account[account_lid]' (#$account_id)</b><br/>\n";
						$errors++;
						continue;
					}
				}
				else
				{
					echo "<p>User '$account[account_lid]' (#$account_id) already up to date<br/>\n";
				}
			}
			if (!($sql_contact = $contacts_sql->read(['account_id' => $account_id])))
			{
				$sql_contact = $contact;
				unset($sql_contact['id']);  // LDAP contact-id is the UID!
				if (!$contacts_sql->save($sql_contact))
				{
					$sql_contact['id'] = $contacts_sql->data['id'];
					echo "Successful created contact for user '$account[account_lid]' (#$account_id)</p>\n";
					$new = true;
				}
				else
				{
					echo "<b>Error creating contact for user '$account[account_lid]' (#$account_id)</b></p>\n";
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
						echo "Successful updated contact data of '$account[account_lid]' (#$account_id): ".json_encode($diff, JSON_UNESCAPED_SLASHES|JSON_UNESCAPED_UNICODE)."</p>\n";
						if (!$new) $new = false;
					}
					else
					{
						echo "<b>Error updating contact data of '$account[account_lid]' (#$account_id)</b></p>\n";
						++$errors;
						continue;
					}
				}
				else
				{
					echo "Contact data of '$account[account_lid]' (#$account_id) already up to date</p>\n";
				}
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
	if ($created || $updated || $errors)
	{
		echo "<p><b>Created $created and updated $updated users, with $errors errors.</b></p>";
	}
	else
	{
		echo "<p><b>All users are up-to-date.</b></p>\n";
	}
	if (!$errors)
	{
		echo "<p><b>Setting new incremental import time to: $str ($last_run)</b></p>\n";
	}
}
catch (\Exception $e) {
	echo "<p style'color: red'>".$e->getMessage()."</p>\n";
	http_response_code(500);
	exit;
}