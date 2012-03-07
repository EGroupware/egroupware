/**
 * eGroupWare eTemplate2 - Stylesheet class
 *
 * @license http://opensource.org/licenses/gpl-license.php GPL - GNU General Public License
 * @package etemplate
 * @subpackage api
 * @link http://www.egroupware.org
 * @author Andreas Stöckel
 * @copyright Stylite 2011
 * @version $Id$
 */

"use strict"

/*egw:uses
	egw_core;
*/

/**
 * Module which allows to add stylesheet rules at runtime. Exports the following
 * functions:
 * - css
 */
egw.extend('css', egw.MODULE_WND_LOCAL, function(_egw, _wnd) {

	/**
	 * Assoziative array which stores the current css rule for a given selector.
	 */
	var selectors = {};

	/**
	 * Variable used to calculate unique id for the selectors.
	 */
	var selectorCount = 0;

	// Generate a style tag, which will be used to hold the newly generated css
	// rules.
	var style = _wnd.document.createElement('style');
	_wnd.document.getElementsByTagName('head')[0].appendChild(style);

	// Obtain the reference to the styleSheet object of the generated style tag
	var sheet = style.sheet ? style.sheet : style.styleSheet;

	return {
		/**
		 * The css function can be used to introduce a rule for the given css
		 * selector. So you're capable of adding new custom css selector while
		 * runtime and also update them.
		 *
		 * @param _selector is the css select which can be used to apply the
		 * 	stlyes to the html elements.
		 * @param _rule is the rule which should be connected to the selector.
		 * 	if empty or omitted, the given selector gets removed.
		 */
		css: function(_selector, _rule) {
			// Set the current index to the maximum index
			var index = selectorCount;

			// Remove any existing rule first, of no rule exists for the
			if (typeof selectors[_selector] !== "undefined")
			{
				// Store the old index
				index = selectors[_selector];
				if (typeof sheet.removeRule !== "undefined")
				{
					sheet.removeRule(index);
				}
				else
				{
					sheet.deleteRule(index);
				}

				delete (selectors[_selector]);
			}
			else
			{
				selectorCount++;
			}

			if (_rule)
			{
				// Add the rule to the stylesheet
				if (typeof sheet.addRule !== "undefined")
				{
					sheet.addRule(_selector, _rule, index);
				}
				else
				{
					sheet.insertRule(_selector + "{" + _rule + "}", index);
				}

				// Store the new index
				selectors[_selector] = index;
			}
		}
	}

});

