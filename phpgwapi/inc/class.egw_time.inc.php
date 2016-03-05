<?php
/**
 * EGroupware time and timezone handling
 *
 * @package api
 * @link http://www.egroupware.org
 * @author Ralf Becker <RalfBecker@outdoor-training.de>
 * @copyright 2009-16 by RalfBecker@outdoor-training.de
 * @license http://opensource.org/licenses/gpl-license.php GPL - GNU General Public License
 * @version $Id$
 */

use EGroupware\Api;

/**
 * EGroupware time and timezone handling class extending PHP's DateTime
 *
 * @deprecated use Api\DateTime
 */
class egw_time extends Api\DateTime {}
