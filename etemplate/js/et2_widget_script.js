/**
 * EGroupware eTemplate2 - JS widget class containing javascript
 *
 * @license http://opensource.org/licenses/gpl-license.php GPL - GNU General Public License
 * @package etemplate
 * @subpackage api
 * @link http://www.egroupware.org
 * @author Ralf Becker
 * @copyright Stylite 2015
 * @version $Id$
 */

"use strict";

/*egw:uses
	et2_core_widget;
*/

/**
 * Function which executes the encapsulated script data.
 *
 * This should only be used for customization and NOT for regular EGroupware code!
 *
 * We can NOT create a script tag containing the content, as this violoates our CSP policy!
 *
 * We use new Function(_content) instead. Therefore you have to use window to address global context:
 *
 * window.some_func = function() {...}
 *
 * instead of not working
 *
 * function some_funct() {...}
 *
 * @augments et2_widget
 */
var et2_script = et2_widget.extend(
{
	/**
	 * Constructor
	 *
	 * @memberOf et2_script
	 */
	init: function()
	{
		this._super.apply(this, arguments);

		// Allow no child widgets
		this.supportedWidgetClasses = [];
	},

	/**
	 * We can NOT create a script tag containing the content, as this violoates our CSP policy!
	 *
	 * @param {string} _content
	 */
	loadContent: function(_content)
	{
		try
		{
			var func = new Function(_content);
			func.call(window);
		}
		catch (e)
		{
			this.egw.debug('error', 'Error while executing script: ',_content,e);
		}
	}
});
et2_register_widget(et2_script, ["script"]);
