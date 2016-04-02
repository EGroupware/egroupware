<?php
/**
 * eGroupWare API - Hooks
 *
 * @link http://www.egroupware.org
 * @author Dan Kuykendall <seek3r@phpgroupware.org>
 * @author Ralf Becker <RalfBecker-AT-outdoor-training.de>
 * Copyright (C) 2000, 2001 Dan Kuykendall
 * New method hooks and docu are written by <RalfBecker@outdoor-training.de>
 * @license http://opensource.org/licenses/lgpl-license.php LGPL - GNU Lesser General Public License
 * @package api
 * @version $Id$
 */

use EGroupware\Api;

/**
 * class which gives ability for applications to set and use hooks to communicate with each other
 *
 * @deprecated use static methods of Api\Hooks::process() and Api\Hooks::single
 */
class hooks extends Api\Hooks
{
	/**
	 * check if a given hook for an  application is registered
	 *
	 * @param string $location location-name
	 * @param string $app appname
	 * @deprecated use exists($location, $app)
	 * @return int the number of found hooks
	 */
	public static function hook_exists($location, $app)
	{
		return self::exists($location, $app);
	}

	/**
	 * check which apps implement a given hook
	 *
	 * @param string $location location-name
	 * @deprecated use implemented($location)
	 * @return array of apps implementing given hook
	 */
	public static function hook_implemented($location)
	{
		return self::implemented($location);
	}

	/**
	 * Register and/or de-register an application's hooks
	 *
	 * First all existing hooks of $appname get deleted in the db and then the given ones get registered.
	 *
	 * @param string $appname Application 'name'
	 * @param array $hooks =null hooks to register, eg $setup_info[$app]['hooks'] or not used for only deregister the hooks
	 * @deprecated use Api\Hooks::read(true) to force rescan of hooks
	 * @return boolean|int false on error, true if new hooks are supplied and registed or number of removed hooks
	 */
	public static function register_hooks($appname,$hooks=null)
	{
		unset($appname, $hooks);

		self::read(true);

		return true;
	}

	/**
	 * Add or/update a single application hook
	 *
 	 * setup file of app will be included and the hook required will be added/or updated
	 *
	 * @param string $appname Application 'name'
	 * @param string $location is required, the hook itself
	 * @deprecated use Api\Hooks::read(true) to force rescan of hooks
	 * @return boolean|int false on error, true if new hooks are supplied and registed or number of removed hooks
	 */
	public static function register_single_app_hook($appname, $location)
	{
		self::read(true);

		return !!self::exists($location, $appname);
	}

	/**
	 * Register the hooks of all applications (used by admin)
	 *
	 * @deprecated use Api\Hooks::read(true) to force rescan of hooks
	 */
	public static function register_all_hooks()
	{
		self::read(true);
	}

	/**
	 * Static function to build egw tutorial sidebox menu
	 *
	 * @deprecated can be removed 2016, as replaced by home_tutorial_ui::tutorial_menu
	 */
	public static function egw_tutorial_menu()
	{
		home_tutorial_ui::tutorial_menu();
	}
}
