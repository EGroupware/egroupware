<?php
/**
 * EGroupware - eTemplate serverside implementation of the nextmatch filter header widget
 *
 * @license http://opensource.org/licenses/gpl-license.php GPL - GNU General Public License
 * @package api
 * @subpackage etemplate
 * @link http://www.egroupware.org
 * @author Ralf Becker <RalfBecker@outdoor-training.de>
 * @copyright 2002-16 by RalfBecker@outdoor-training.de
 * @version $Id$
 */

namespace EGroupware\Api\Etemplate\Widget\Nextmatch;

use EGroupware\Api\Etemplate\Widget;

/**
 * Extend selectbox so select options get parsed properly before being sent to client
 */
class Filterheader extends Widget\Taglist
{
}

Widget::registerWidget(__NAMESPACE__.'\\Filterheader', array('nextmatch-filterheader'));