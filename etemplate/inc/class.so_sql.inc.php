<?php
/**
 * EGroupware generalized SQL Storage Object
 *
 * @license http://opensource.org/licenses/gpl-license.php GPL - GNU General Public License
 * @package etemplate
 * @link http://www.egroupware.org
 * @author Ralf Becker <RalfBecker@outdoor-training.de>
 * @copyright 2002-16 by RalfBecker@outdoor-training.de
 * @version $Id$
 */

use EGroupware\Api;

/**
 * generalized SQL Storage Object
 *
 * the class can be used in following ways:
 * 1) by calling the constructor with an app and table-name or
 * 2) by setting the following documented class-vars in a class derived from this one
 * Of cause you can derive from the class and call the constructor with params.
 *
 * @deprecated use Api\Storage\Base
 */
class so_sql extends Api\Storage\Base {}

/**
 * Iterator applying a so_sql's db2data method on each element retrived
 *
 * @deprecated use Api\Storage\Db2DataIterator
 */
class so_sql_db2data_iterator extends Api\Storage\Db2DataIterator {}
