<?php
/**
 * EGgroupware admin - admin command: edit preferences
 *
 * @link http://www.egroupware.org
 * @author Hadi Nategh <hn@egroupware.org>
 * @package admin
 * @copyright (c) 2018 by Hadi Nategh <hn@egroupware.org>
 * @license http://opensource.org/licenses/gpl-license.php GPL - GNU General Public License
 * @version $Id$
 */

/**
 * admin command: edit preferences
 */
class admin_cmd_edit_preferences extends admin_cmd
{
	/**
	 * Constructor
	 * @param string|int|array $account account name or id (!$account to add a new account), or array with all parameters
	 * @param array $set changed values
	 * @param array $old old values
	 */
	function __construct($account,$set=null, $old=null)
	{
		if (!is_array($account))
		{
			$account = array(
				'account' => $account,
				'set' => $set,
				'old' => $old
			);
		}
		admin_cmd::__construct($account);
	}

	/**
	 * Edit a preference
	 *
	 * @param boolean $check_only =false only run the checks (and throw the exceptions), but not the command itself
	 * @return string
	 */
	protected function exec($check_only=false)
	{
		if ($check_only) return;
		$GLOBALS['egw']->preferences->save_repository(True, $this->type);
		return lang('Preferences saved.');
	}
}