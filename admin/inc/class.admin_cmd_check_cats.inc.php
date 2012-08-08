<?php
/**
 * EGroupware admin - admin command: check categories for not (longer) existing accounts
 *
 * @link http://www.egroupware.org
 * @author Ralf Becker <RalfBecker-AT-outdoor-training.de>
 * @package admin
 * @copyright (c) 2012 by Ralf Becker <RalfBecker-AT-outdoor-training.de>
 * @license http://opensource.org/licenses/gpl-license.php GPL - GNU General Public License
 * @version $Id$
 */

/**
 * admin command: check categories for not (longer) existing accounts
 */
class admin_cmd_check_cats extends admin_cmd
{
	const SETUP_CLI_CALLABLE = true;

	/**
	 * Constructor
	 *
	 * @param array $data=array() default parm from parent class, no real parameters
	 */
	function __construct($data=array())
	{
		admin_cmd::__construct($data);
	}

	/**
	 * give or remove run rights from a given account and application
	 *
	 * @param boolean $check_only=false only run the checks (and throw the exceptions), but not the command itself
	 * @return string success message
	 */
	protected function exec($check_only=false)
	{
		if ($check_only) return true;

		admin_cmd::_instanciate_accounts();

		return lang("%1 categories of not (longer) existing accounts deleted.", categories::delete_orphans());
	}

	/**
	 * Return a title / string representation for a given command, eg. to display it
	 *
	 * @return string
	 */
	function __tostring()
	{
		return lang('Check categories for not (longer) existing accounts');
	}
}
