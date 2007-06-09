/**
 * eGroupWare
 *
 * @license http://opensource.org/licenses/gpl-license.php GPL - GNU General Public License
 * @package importexport
 * @link http://www.egroupware.org
 * @author Cornelius Weiss <nelius@cwtech.de>
 * @copyright Cornelius Weiss <nelius@cwtech.de>
 * @version $Id:$
 */

function export_dialog() {
	
	this.change_definition = function(sel_obj) {
		if(sel_obj.value == 'expert') {
			// next two lines fix some strange layout bug
			set_style_by_class('tr','select_definition','display','none');
			set_style_by_class('tr','select_definition','display','inline');
			set_style_by_class('tr','select_plugin','display','inline');
			set_style_by_class('tr','save_definition','display','inline');
		}
		else {
			set_style_by_class('tr','select_plugin','display','none');
			set_style_by_class('tr','save_definition','display','none');
		}
	};
}
var export_dialog = new export_dialog();