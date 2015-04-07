<?php
/**
 * EGroupware API: VFS - new DB based VFS stream wrapper
 *
 * @link http://www.egroupware.org
 * @license http://opensource.org/licenses/gpl-license.php GPL - GNU General Public License
 * @package api
 * @subpackage vfs
 * @author Ralf Becker <RalfBecker-AT-outdoor-training.de>
 * @copyright (c) 2008-15 by Ralf Becker <RalfBecker-AT-outdoor-training.de>
 * @version $Id$
 */

use EGroupware\Api\Vfs\Sqlfs;

/**
 * @depredated use EGroupware\Api\Vfs\Sqlfs\StreamWrapper
 */
class sqlfs_stream_wrapper extends Sqlfs\StreamWrapper {}
