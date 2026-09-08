<?php
/**
 * EGroupware - eTemplate serverside of tag list widget
 *
 * @license http://opensource.org/licenses/gpl-license.php GPL - GNU General Public License
 * @package api
 * @subpackage etemplate
 * @link http://www.egroupware.org
 * @author Nathan Gray
 * @copyright 2013-18 Nathan Gray
 */

namespace EGroupware\Api\Etemplate\Widget;

/**
 * @deprecated there is no more client-side taglist widget (et2-email/et2-select-thumbnail/etc. are
 * all Select-family webcomponents now) - this class' real implementation, including its
 * ajax_search()/ajax_email() methods and constant, has been merged into Select. Kept as an empty
 * subclass purely so a 3rd-party template hardcoding
 * searchUrl="EGroupware\Api\Etemplate\Widget\Taglist::ajax_search" (or ::ajax_email) still resolves
 * - both are now inherited from Select unchanged. Do not add anything to this class; extend Select
 * instead.
 */
class Taglist extends Select
{
}