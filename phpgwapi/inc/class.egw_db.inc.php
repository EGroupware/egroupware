<?php
/**
 * EGroupware API: Database abstraction library
 *
 * @link http://www.egroupware.org
 * @license http://opensource.org/licenses/gpl-license.php GPL - GNU General Public License
 * @package api
 * @subpackage db
 * @author Ralf Becker <RalfBecker-AT-outdoor-training.de>
 * @copyright (c) 2003-16 by Ralf Becker <RalfBecker-AT-outdoor-training.de>
 * @version $Id$
 */

use EGroupware\Api\Db;

/**
 * You only need to clone the global database object $GLOBALS['egw']->db if:
 * - you use the old methods f(), next_record(), row(), num_fields(), num_rows()
 * - you access an application table (non phpgwapi) and you want to call set_app()
 *
 * Otherwise you can simply use $GLOBALS['egw']->db or a reference to it.
 *
 * Avoiding next_record() or row() can be done by looping with the recordset returned by query() or select():
 *
 * @deprecated use just EGroupware\Api\Db or EGroupware\Api\Db\Deprecated
 */
class egw_db extends Db\Deprecated {}

/**
 * @deprecated use EGroupware\Api\Db\CallbackIterator
 */
class egw_db_callback_iterator extends Db\CallbackIterator {}
