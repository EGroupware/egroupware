/**
 * EGroupware API: switch jQuery to NOT use $ as early as possible
 *
 * @link http://www.egroupware.org
 * @license http://opensource.org/licenses/gpl-license.php GPL - GNU General Public License
 * @package api
 * @subpackage ajax
 * @author Ralf Becker <rb@egroupware.org>
 */

/*egw:uses
	/vendor/bower-asset/jquery/dist/jquery.js;
	/vendor/bower-asset/jquery-ui/jquery-ui.js;
	/api/js/jquery/chosen/chosen.jquery.js;
	/api/js/jquery/jquery-tab-and-hold/jquery.tabandhold.js;
	/api/js/jquery/mousewheel/mousewheel.js;
	/api/js/jquery/jquery-ui-timepicker-addon.js;
	/vendor/bower-asset/jquery-touchswipe/jquery.touchSwipe.js;
*/
jQuery.noConflict();