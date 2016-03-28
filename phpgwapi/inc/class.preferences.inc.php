<?php
/**
 * EGroupware API - Preferences
 *
 * @link http://www.egroupware.org
 * @author Joseph Engo <jengo@phpgroupware.org>
 * @author Mark Peters <skeeter@phpgroupware.org>
 * @author Ralf Becker <RalfBecker-AT-outdoor-training.de> merging prefs on runtime, session prefs and reworked the class
 * Copyright (C) 2000, 2001 Joseph Engo
 * @license http://opensource.org/licenses/lgpl-license.php LGPL - GNU Lesser General Public License
 * @package api
 * @version $Id$
 */

use EGroupware\Api;

/**
 * preferences class used for setting application preferences
 *
 * preferences are read into following arrays:
 * - $data effective prefs used everywhere in EGroupware
 * Effective prefs are merged together in following precedence from:
 * - $forced forced preferences set by the admin, they take precedence over user or default prefs
 * - $session temporary prefs eg. language set on login just for session
 * - $user the stored user prefs, only used for manipulating and storeing the user prefs
 * - $group the stored prefs of all group-memberships of current user, can NOT be deleted or stored directly!
 * - $default the default preferences, always used when the user has no own preference set
 *
 * To update the prefs of a certain group, not just the primary group of the user, you have to
 * create a new instance of preferences class, with the given id of the group. This takes into
 * account the offset of DEFAULT_ID, we are using currently for groups (as -1, and -2) are already
 * taken!
 *
 * Preferences get now json-encoded and no longer PHP serialized and addslashed,
 * thought they only change when they get updated.
 */
class preferences extends Api\Preferences
{
	/**
	 * @deprecated use add
	 */
	function change($app_name,$var,$value = "")
	{
		return $this->add($app_name,$var,$value);
	}
}
