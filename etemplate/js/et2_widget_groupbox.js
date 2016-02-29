/**
 * EGroupware eTemplate2 - JS Groupbox object
 *
 * @license http://opensource.org/licenses/gpl-license.php GPL - GNU General Public License
 * @package etemplate
 * @subpackage api
 * @link http://www.egroupware.org
 * @author Nathan Gray
 * @copyright Nathan Gray 2012
 * @version $Id$
 */

/*egw:uses
	et2_core_baseWidget;
*/

/**
 * Class which implements the groupbox tag
 *
 * @augments et2_baseWidget
 */
var et2_groupbox = (function(){ "use strict"; return et2_baseWidget.extend(
{
	/**
	 * Constructor
	 *
	 * @memberOf et2_groupbox
	 */
	init: function() {
		this._super.apply(this, arguments);

		this.setDOMNode(document.createElement("fieldset"));
	}
});}).call(this);
et2_register_widget(et2_groupbox, ["groupbox"]);

/**
 * @augments et2_baseWidget
 */
var et2_groupbox_legend = (function(){ "use strict"; return et2_baseWidget.extend(
{
	attributes: {
		"label": {
			"name": "Label",
			"type": "string",
			"default": "",
			"description": "Label for group box",
			"translate" : true
		}
	},

	/**
	 * Constructor
	 *
	 * @memberOf et2_groupbox_legend
	 */
	init: function() {
		this._super.apply(this, arguments);

		var legend = jQuery(document.createElement("legend")).text(this.options.label);
		this.setDOMNode(legend[0]);
	}
});}).call(this);
et2_register_widget(et2_groupbox_legend, ["caption"]);
