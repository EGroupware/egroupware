<?php
/**
 * EGroupware - eTemplate serverside
 *
 * @license http://opensource.org/licenses/gpl-license.php GPL - GNU General Public License
 * @package etemplate
 * @subpackage api
 * @link http://www.egroupware.org
 * @author Ralf Becker <RalfBecker@outdoor-training.de>
 * @copyright 2002-16 by RalfBecker@outdoor-training.de
 * @version $Id$
 */

use EGroupware\Api;

/**
 * New eTemplate serverside contains:
 * - main server methods like read, exec
 * -
 *
 * Not longer available methods:
 * - set_(row|column)_attributes modifies template on run-time, was only used internally by etemplate itself
 * - disable_(row|column) dto.
 *
 * @deprecated use Api\Etemplate
 */
class etemplate_new extends Api\Etemplate {}
