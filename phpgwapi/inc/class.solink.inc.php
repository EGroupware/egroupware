<?php
/**
 * EGroupware API - Interapplicaton links storage
 *
 * Links have two ends each pointing to an entry, each entry is a double:
 * 	 - app   app-name or directory-name of an egw application, eg. 'infolog'
 * 	 - id    this is the id, eg. an integer or a tupple like '0:INBOX:1234'
 *
 * @link http://www.egroupware.org
 * @author Ralf Becker <RalfBecker-AT-outdoor-training.de>
 * @copyright 2001-2016 by RalfBecker@outdoor-training.de
 * @license http://opensource.org/licenses/gpl-license.php GPL - GNU General Public License
 * @package api
 * @subpackage link
 * @version $Id$
 */

use EGroupware\Api;

/**
 * generalized linking between entries of eGroupware apps - SO layer
 *
 * @deprecated use Api\Link\Storage
 */
class solink extends Api\Link\Storage {}
