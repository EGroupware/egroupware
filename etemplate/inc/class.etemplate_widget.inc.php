<?php
/**
 * EGroupware - eTemplate widget moved to EGroupware\Api\Etemplate\Widget
 *
 * @license http://opensource.org/licenses/gpl-license.php GPL - GNU General Public License
 * @package etemplate
 * @subpackage api
 * @link http://www.egroupware.org
 * @author Ralf Becker <RalfBecker@outdoor-training.de>
 * @copyright 2002-16 by RalfBecker@outdoor-training.de
 * @version $Id$
 */

use EGroupware\Api\Etemplate\Widget;
use EGroupware\Api\Etemplate;

/**
 * eTemplate widget baseclass
 *
 * @deprecated use Api\Etemplate\Widget
 */
class etemplate_widget extends Etemplate\Widget {}

/**
 * eTemplate Extension: Entry widget
 *
 * This widget can be used to fetch fields of any entry specified by its ID.
 * The entry is loaded once and shared amoung widget that need it.
 *
 * @deprecated use Api\Etemplate\Widget\Entry
 */
abstract class etemplate_widget_entry extends Widget\Entry {}

/**
 * eTemplate Select widget
 *
 * @deprecated use Api\Etemplate\Widget\Select
 */
class etemplate_widget_menupopup extends Widget\Select {}

/**
 * eTemplate Link widgets
 *
 * @deprecated use Api\Etemplate\Widget\Link
 */
class etemplate_widget_link extends Widget\Link {}

/**
 * eTemplate Nextmatch widgets
 *
 * @deprecated use Api\Etemplate\Widget\Nextmatch
 */
class etemplate_widget_nextmatch extends Widget\Nextmatch {}

/**
 * eTemplate Taglist widgets
 *
 * @deprecated use Api\Etemplate\Widget\Taglist
 */
class etemplate_widget_taglist extends Widget\Taglist {}

/**
 * eTemplate File widgets
 *
 * @deprecated use Api\Etemplate\Widget\File
 */
class etemplate_widget_file extends Widget\File {}

/**
 * eTemplate Vfs widgets
 *
 * @deprecated use Api\Etemplate\Widget\Vfs
 */
class etemplate_widget_vfs extends Widget\Vfs {}
