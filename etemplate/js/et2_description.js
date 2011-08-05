/**
 * eGroupWare eTemplate2 - JS Template base class
 *
 * @license http://opensource.org/licenses/gpl-license.php GPL - GNU General Public License
 * @package etemplate
 * @subpackage api
 * @link http://www.egroupware.org
 * @author Andreas Stöckel
 * @copyright Stylite 2011
 * @version $Id$
 */

/*egw:uses
	jquery.jquery;
	et2_widget;
*/

/**
 * Class which implements the "description" XET-Tag
 */ 
et2_description = et2_DOMWidget.extend({

	init: function(_parent) {
		this.span = $j(document.createElement("span"));

		this._super.apply(this, arguments);
		this.value = "";
	},

	set_value: function(_value) {
		if (_value != this.value)
		{
			this.value = _value;

			this.span.text(_value);
		}
	},

	getDOMNode: function() {
		return this.span[0];
	}

});

et2_register_widget(et2_description, ["description"]);


