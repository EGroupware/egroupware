/**
 * EGroupware eTemplate2 - JS Select account widget
 * 
 * Selecting accounts needs special UI, and displaying needs special consideration
 * to avoid sending the entire user list to the client.
 *
 * @license http://opensource.org/licenses/gpl-license.php GPL - GNU General Public License
 * @package etemplate
 * @subpackage api
 * @link http://www.egroupware.org
 * @author Nathan Gray
 * @copyright Nathan Gray 2012
 * @version $Id$
 */

"use strict";

/*egw:uses
	et2_widget_link;
*/

/**
 * et2_selectAccount_ro is the readonly implementation of select account
 * It extends et2_link to avoid needing the whole user list on the client.
 * Instead, it just asks for the names of the ones needed, as needed.
 */
var et2_selectAccount_ro = et2_link_string.extend([et2_IDetachedDOM], {

	init: function(_parent, options) {
		/**
		Resolve some circular dependency problems here
		selectAccount extends link, link is in a file that needs select, 
		select has menulist wrapper, which needs to know about selectAccount before it allows it
		*/
		if(_parent.supportedWidgetClasses.indexOf(et2_selectAccount_ro) < 0)
		{
			_parent.supportedWidgetClasses.push(et2_selectAccount_ro);
		}

		this._super.apply(this, arguments);

		this.options.application = 'home-accounts';

		// Don't make it look like a link though
		this.list.removeClass("et2_link_string").addClass("et2_selectbox");
	},

	set_value: function(_value) {
		this._super.apply(this, arguments);

		// Don't make it look like a link though
		jQuery('li',this.list).removeClass("et2_link et2_link_string");
	}
});
et2_register_widget(et2_selectAccount_ro, ["select-account_ro"]);
