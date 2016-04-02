<?php
/**
 * EGroupware - Ajax log file viewer (tail -f)
 *
 * @link http://www.egroupware.org
 * @author Ralf Becker <RalfBecker@outdoor-training.de>
 * @copyright 2012-16 by RalfBecker@outdoor-training.de
 * @license http://opensource.org/licenses/gpl-license.php GPL - GNU General Public License
 * @package api
 * @subpackage json
 * @version $Id$
 */

use EGroupware\Api\Json\Tail;

/**
 * Ajax log file viewer (tail -f)
 *
 * To not allow to view arbitrary files, allowed filenames are stored in the session.
 * Class fetches log-file periodically in chunks for 8k.
 * If fetch returns no new content next request will be in 2s, otherwise in 200ms.
 * As logfiles can be quiet huge, we display at max the last 32k of it!
 *
 * Example usage:
 *
 * $error_log = new egw_tail('/var/log/apache2/error_log');
 * echo $error_log->show();
 *
 * Strongly prefered for security reasons is to use a path relative to EGroupware's files_dir,
 * eg. new egw_tail('groupdav/somelog')!
 *
 * @deprecated use Api\Json\Tail
 */
class egw_tail extends Tail {}
