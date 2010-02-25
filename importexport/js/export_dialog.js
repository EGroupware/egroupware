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
			xajax_doXMLHTTP('importexport.uiexport.ajax_get_plugins',document.getElementById('exec[appname]') ? document.getElementById('exec[appname]').value : this.appname);
			// next two lines fix some strange layout bug
			//set_style_by_class('tr','select_definition','display','none');
			//set_style_by_class('tr','select_definition','display','inline');
			set_style_by_class('tr','select_plugin','display','table-row');
			set_style_by_class('tr','save_definition','display','inline');
		}
		else {
			xajax_doXMLHTTP('importexport.uiexport.ajax_get_definition_description',sel_obj.value);
			set_style_by_class('tr','select_plugin','display','none');
			set_style_by_class('tr','save_definition','display','none');
		}
	};
	this.appname = '';
}
var export_dialog = new export_dialog();
