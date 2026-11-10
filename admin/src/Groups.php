<?php
/**
 * EGroupware: Group administration
 *
 * @link https://www.egroupware.org
 * @package admin
 * @author Ralf Becker <rb@egroupware.org>
 * @copyright (c) 2014-22 by Ralf Becker <rb@egroupware.org>
 */

namespace EGroupware\Admin;

use admin_cmd_account_app;
use admin_cmd_edit_group;
use EGroupware\Api;
use EGroupware\Api\Framework;
use EGroupware\Api\Egw;
use EGroupware\Api\Acl;
use EGroupware\Api\Etemplate;
use EGroupware\Api\Mail;

/**
 * Group administration:
 * - hooks into admin to add and edit groups
 */
class Groups
{
	/**
	 * Methods callable via menuaction
	 *
	 * @var array
	 */
	var $public_functions = array(
		'edit' => true,
	);

	/**
	 * Reference to global accounts object
	 *
	 * @var Api\Accounts
	 */
	protected $accounts;
	/**
	 * Reference to global acl class (instantiated for current user)
	 *
	 * @var Acl
	 */
	protected $acl;

	/**
	 * Apps supporting (group) ACL
	 *
	 * @var type
	 */
	protected $apps_with_acl = array(
		'calendar'       => True,
		'infolog'        => True,
		'filemanager'    => array(
			'menuaction' => 'filemanager.filemanager_ui.file',
			'path'       => '/home/$account_lid',
			'tabs'       => 'eacl',
			'popup'      => '495x400',
		),
		'bookmarks'      => True,
		'phpbrain'       => True,
		'projectmanager' => True,
		'timesheet'      => True
	);

	/**
	 * Constructor
	 */
	public function __construct()
	{
		$this->acl = $GLOBALS['egw']->acl;
		$this->accounts = $GLOBALS['egw']->accounts;

		foreach(Api\Hooks::process('group_acl','',true) as $app => $data)
		{
			if ($data) $this->apps_with_acl[$app] = $data;
		}
		// we need admin translations
		Api\Translation::add_app('admin');
	}

	/**
	 * Edit / add a group
	 *
	 * @param ?array $content =null
	 */
	public function edit(?array $content=null)
	{
		$sel_options = $readonlys = array();
		$tpl = new Etemplate('admin.group.edit');

		if (!is_array($content))
		{
			if (isset($_GET['account_id']))
			{
				// invalidate account, before reading it, to code with changed to DB or LDAP outside EGw
				Api\Accounts::cache_invalidate((int)$_GET['account_id']);
				if ($this->accounts->exists((int)$_GET['account_id']) != 2 ||    // 2 = group
					!($content = $this->accounts->read((int)$_GET['account_id'])))
				{
					Framework::window_close(lang('Entry not found!'));
				}
				if ($GLOBALS['egw']->acl->checkAdminDeny('group_access', 8))    // no view
				{
					Framework::window_close(lang('Permission denied!'));
				}
				$content['account_members'] = array_keys($content['members']);
				unset($content['members']);
				// we might not see all (system) users, so preserve them
				foreach($content['account_members'] as $key => $id)
				{
					if ($id < 0 || !$this->accounts->id2name($id))
					{
						$content['unaccessible_members'][] = $id;
						unset($content['account_members'][$key]);
					}
				}
				$acl = new Acl($content['account_id']);
				$acl->read_repository();
				$content['hidden'] = $acl->check('hidden', 1, 'phpgwapi');
				// must run BEFORE the $content['old'] snapshot below - see this method's own
				// docblock for why
				$this->addStalwartSyncOptions($content, $sel_options);
				$content['old'] = $content;
			}
			else
			{
				if ($GLOBALS['egw']->acl->checkAdminDeny('group_access', 4))    // no add
				{
					Framework::window_close(lang('Permission denied!'));
				}
				$content = array();
			}
			//call_user_func(base64_decode('c3R5bGl0ZV9saWNlbnNlX2JvOjp2YWxpZGF0ZQ=='));
		}
		elseif(!empty($content['button']))
		{
			$button = key($content['button']);
			unset($content['button']);
			$msg = '';

			switch($button)
			{
				case 'apply':
				case 'save':
					// gate the actual save, not just the button's readonly state below - otherwise a
					// restricted admin (denied group_access add/edit) could still save via a direct POST
					if ($GLOBALS['egw']->acl->checkAdminDeny('group_access', $content['account_id'] ? 16 : 4))    // no edit / add
					{
						Framework::window_close(lang('Permission denied!'));
					}
					try {
						$refresh_type = !$content['old'] ? 'add' : 'edit';
						// check if some account-data changed
						if (!$content['old'] || $content['old'] != array_intersect_key($content, $content['old']))
						{
							if (!empty($content['unaccessible_members']))
							{
								$content['account_members'] = array_merge($content['account_members'], $content['unaccessible_members']);
							}

							// Only set real changes
							$content['account_id'] = $this->run_command($content, $msg);

							if (!empty($content['unaccessible_members']))
							{
								$content['account_members'] = array_diff($content['account_members'], $content['unaccessible_members']);
							}
							$content['old'] = array_intersect_key($content, $content['old'] ? $content['old'] :
								array_flip(array('account_id','account_lid','account_email','account_members','stalwart_sync')));
						}
						$apps = array();
						foreach((array)$content['apps'] as $data)
						{
							if ($data['run']) $apps[] = $data['appname'];
						}
						//error_log(__METHOD__."() apps=".array2string($apps).", old=".array2string($content['old_run']).", content[apps]=".array2string($content['apps']));
						// check if new apps added
						if (($added = array_diff($apps, $content['old_run'])))
						{
							//error_log(__METHOD__."() apps added: ".array2string($added));
							$allow = array(
									'allow'   => true,
									'account' => $content['account_id'],
									'apps'    => $added,
									// This is the documentation from policy app
								)+(array)$content['admin_cmd'];
							$add_cmd = new admin_cmd_account_app($allow);
							$msg .= $add_cmd->run();
						}
						// check if apps being removed
						if (($removed = array_diff($content['old_run'], $apps)))
						{
							//error_log(__METHOD__."() apps removed: ".array2string($removed));
							$allow = array(
									'allow'   => false,
									'account' => $content['account_id'],
									'apps'    => $removed,
									// This is the documentation from policy app
								)+(array)$content['admin_cmd'];
							$rm_cmd = new admin_cmd_account_app($allow);
							$msg .= $rm_cmd->run();
						}
						$content['old_run'] = $apps;
					}
					catch (Exception $ex) {
						$msg .= $ex->getMessage();
						unset($button);    // do NOT close dialog
					}
					if (!$msg)
					{
						$msg = lang('Nothing to save.');
					}
					else
					{
						Framework::refresh_opener($msg, 'admin', $content['account_id'], $refresh_type, null, null, null,
												  isset($ex) ? 'error' : 'success');
					}
					if ($button != 'save')
					{
						Framework::message($msg, isset($ex) ? 'error' : 'success');
						break;
					}
					Framework::window_close();
			}
		}
		$run_rights = $content['account_id'] ? $this->acl->get_user_applications($content['account_id'], false, false) : array();
		$content['apps'] = $content['old_run'] = array();
		$content['default_quota'] = lang('(EPL Only)');
		foreach($GLOBALS['egw_info']['apps'] as $app => $data)
		{
			if (!$data['enabled'] || !$data['status'] || $data['status'] == 3)
			{
				continue;    // do NOT show disabled apps, or our API (status = 3)
			}

			$popup = null;
			$acl_action = $this->_acl_action($app, $content['account_id'], $content['account_lid'], $popup);

			$content['apps'][] = array(
				'appname' => $app,
				'title'   => lang($app),
				'action'  => $acl_action,
				'popup'   => $popup,
				'run'     => (int)(bool)$run_rights[$app],
			);
			if ($run_rights[$app]) $content['old_run'][] = $app;
			$readonlys['apps']['button['.$app.']'] = !$acl_action;
		}
		usort($content['apps'], static function($a, $b)
		{
			if ($a['run'] !== $b['run']) return $b['run']-$a['run'];
			return strcasecmp($a['title'], $b['title']);
		});

		$readonlys['button[delete]'] = !$content['account_id'] ||
			$GLOBALS['egw']->acl->checkAdminDeny('group_access', 32);    // no delete
		if ($GLOBALS['egw']->acl->checkAdminDeny('group_access', $content['account_id'] ? 16 : 4))    // no edit / add
		{
			$readonlys['button[save]'] = $readonlys['button[apply]'] = true;
		}
		// disable DN for LDAP, AD or synced groups were the real DN is stored here
		$content['disable_dn'] = $GLOBALS['egw_info']['server']['account_repository'] !== 'sql' ||
			!empty($GLOBALS['egw_info']['server']['account_import_source']) && $GLOBALS['egw_info']['server']['account_import_type'] !== 'users';

		$this->addStalwartSyncOptions($content, $sel_options);

		$tpl->exec('admin.'.self::class.'.edit', $content, $sel_options, $readonlys, $content, 2);
	}

	/**
	 * Detect whether a managed Stalwart account exists for this group (same account-finding
	 * logic as Mail\Hooks::run_plugin_hooks(), so "will the hook fire" and "does the UI show
	 * the selector" stay in sync by construction, rather than guessing independently) and, if
	 * so, populate $content['stalwart_available'], $sel_options['stalwart_sync'] and (when
	 * editing an existing group) $content['stalwart_sync'] with its current mode.
	 *
	 * Must be called BEFORE edit()'s own $content['old'] = $content snapshot (initial-load
	 * branch), not just at the end of edit() - found live, 2026-11-10: selecting a different
	 * sync mode and saving silently reverted to the previously stored value. Root cause: this
	 * logic used to run only once, at the very end of edit() - after $content['old'] had
	 * already been captured without a 'stalwart_sync' key. edit()'s save-button handler only
	 * calls run_command() when `$content['old'] != array_intersect_key($content,
	 * $content['old'])` - since array_intersect_key() restricts to keys already present in
	 * $content['old'], a field missing from that snapshot can never make the two sides differ,
	 * no matter how it changes, so changing ONLY stalwart_sync was invisible to that check and
	 * the whole save was silently treated as a no-op. Called again at the end of edit() too,
	 * since $sel_options is rebuilt fresh on every call (including the post-save re-render)
	 * regardless - the resulting one extra read on the initial load is harmless.
	 *
	 * @param array $content
	 * @param array $sel_options
	 * @return void
	 */
	private function addStalwartSyncOptions(array &$content, array &$sel_options) : void
	{
		$content['stalwart_available'] = false;
		foreach (Mail\Account::search($content['account_id'] ?: 0, 'params') as $params)
		{
			if (Mail\Account::is_multiple($params) &&
				is_a($params['acc_smtp_type'] ?? '', Mail\Smtp\Stalwart::class, true))
			{
				$content['stalwart_available'] = true;
				$options = [
					Mail\Smtp\Stalwart::GROUP_SYNC_NONE => lang('No sync to Stalwart'),
					Mail\Smtp\Stalwart::GROUP_SYNC_ACL => lang('ACL group (no mailbox)'),
				];
				$needs_email = [
					Mail\Smtp\Stalwart::GROUP_SYNC_LIST => lang('Distribution list'),
					Mail\Smtp\Stalwart::GROUP_SYNC_MAILBOX => lang('Shared mailbox'),
				];
				if ($content['account_id'])
				{
					$content['stalwart_sync'] = (new Mail\Account($params))->smtpServer()
						->groupSyncMode($content['account_id'], $content['account_email'] ?? null);
				}
				// modes requiring an email are only offered once the group actually has one - avoids
				// ever presenting a choice admin_cmd_edit_group::exec() would reject on save anyway;
				// still shown if that's already the stored mode, so an (otherwise-prevented) existing
				// email-less list/mailbox group doesn't have its real value hidden from the admin
				if (!empty($content['account_email']) || isset($needs_email[$content['stalwart_sync'] ?? null]))
				{
					$options += $needs_email;
				}
				$sel_options['stalwart_sync'] = $options;
				break;
			}
		}
	}

	/**
	 * Run the admin command to save the account change & log it
	 *
	 * @param Array $content Content from etemplate save
	 *
	 * @return int Command account
	 */
	public function run_command($content, &$msg)
	{
		$fields = array(
			'account_email',
			'account_lid',
			'account_description',
			'account_members',
			'hidden',
			'stalwart_sync',
		);
		// Only send real changes
		$account = array();
		$old = array_intersect_key((array)$content['old'], array_flip($fields));
		foreach($fields as $field)
		{
			if($old && $content[$field] == $old[$field])
			{
				unset($old[$field]);
				continue;
			}
			switch($field)
			{
				case 'account_members':
					sort($content[$field]);
					if (is_array($old[$field])) sort($old[$field]);
					if($content[$field] == $old[$field])
					{
						unset($old[$field]);
						continue 2;
					}
				default:
					$account[$field] = $content[$field];
			}
		}

		// No changes here
		if(count($account) == 0) return $content['account_id'];

		$cmd = new admin_cmd_edit_group(array(
			'account' => (int)$content['account_id'],
			'set'     => $account,
			'old'     => $old,
			// This is the documentation from policy app
		)+(array)$content['admin_cmd']);
		$msg = $cmd->run();
		return $cmd->account;
	}

	/**
	 * Check entered data and return error-msg via json data or null
	 *
	 * @param array $data values for account_id and account_lid
	 */
	public static function ajax_check(array $data)
	{
		// this is a static method with no constructor to gate it, and admin_cmd::_check_admin()
		// (run further down via admin_cmd_edit_group::exec()) fails open for a caller with no
		// admin ACL rows at all - require granted admin rights explicitly here
		if(!isset($GLOBALS['egw_info']['user']['apps']['admin']))
		{
			throw new Api\Exception\NoPermission\Admin();
		}
		// set dummy member to get no error about no members yet
		$data['account_members'] = array($GLOBALS['egw_info']['user']['account_id']);

		try {
			$cmd = new admin_cmd_edit_group($data['account_id'], $data);
			$cmd->run(null, false, false, true);
		}
		catch(Exception $e)
		{
			Api\Json\Response::get()->data($e->getMessage());
		}
	}

	/**
	 * Return actions for groups / edit_group hook
	 *
	 * @param string|array $location
	 */
	public static function edit_group($location)
	{
		unset($location);    // unused, but required by hooks signature

		$ret = array(
			array(
				'id'      => 'edit',
				'caption' => 'Edit group',
				'icon'    => 'edit',
				'popup'   => '600x400',
				'url'     => 'menuaction=admin.'.self::class.'.edit&account_id=$id',
				'group'   => 2,
			),
			array(
				'id'       => 'add_group',
				'caption'  => 'Add group',
				'icon'     => 'new',
				'popup'    => '600x400',
				'url'      => 'menuaction=admin.'.self::class.'.edit',
				'group'    => 2,
				'enableId' => '',
			),
			'delete' => array(
				'id'      => 'delete',
				'caption' => 'Delete',
				'icon'    => 'delete',
				'confirm' => 'Delete this group',
				'group'   => 99,
			)
		);
		// if policy app is used, use admin_account delete to delete groups
		if ($GLOBALS['egw_info']['user']['apps']['policy'])
		{
			$ret['delete'] += array(
				'confirm_handler' => 'app.policy.confirm',
				'url'                 => 'menuaction=admin.admin_account.delete&account_id=$id'
			);
		}
		return $ret;
	}

	/**
	 * Check if app uses group ACL
	 *
	 * @param string $app
	 * @param int $account_id
	 * @param string $account_lid
	 * @param string &$popup on return $width.'x'.$height or null
	 * @return boolean|string false or link for action
	 */
	private function _acl_action($app, $account_id, $account_lid, &$popup)
	{
		if (!($acl_action = $this->apps_with_acl[$app]) || !$account_id)
		{
			return false;
		}
		if ($acl_action === true)
		{
			return true;
		}
		$replacements = array(
			'$app'         => $app,
			'$account_id'  => $account_id,
			'$account_lid' => $account_lid,
		);
		foreach($acl_action as &$value)
		{
			$value = str_replace(array_keys($replacements), array_values($replacements), $value);
		}
		$popup = $acl_action['popup'];
		unset($acl_action['popup']);

		return Egw::link('/index.php',$acl_action);
	}
}