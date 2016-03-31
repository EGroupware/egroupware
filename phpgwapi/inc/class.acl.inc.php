<?php
/**
 * EGroupware API - Applications
 *
 * @link http://www.egroupware.org
 * @author Dan Kuykendall <seek3r@phpgroupware.org>
 * Copyright (C) 2000, 2001 Dan Kuykendall
 * @license http://opensource.org/licenses/lgpl-license.php LGPL - GNU Lesser General Public License
 * @package api
 * @subpackage acl
 * @version $Id$
 */

use EGroupware\Api;

/**
 * Access Control List System
 *
 * This class provides an ACL security scheme.
 * This can manage rights to 'run' applications, and limit certain features within an application.
 * It is also used for granting a user "membership" to a group, or making a user have the security equivilance of another user.
 * It is also used for granting a user or group rights to various records, such as todo or calendar items of another user.
 *
 * $acl = new acl(5);  // 5 is the user id
 */
class acl extends Api\Acl {}