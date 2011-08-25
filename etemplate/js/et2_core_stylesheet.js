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

/**
 * Contains the egwDynStyleSheet class which allows dynamic generation of stylesheet
 * rules - updating a single stylesheet rule is way more efficient than updating
 * the element style of many objects.
 */
var EGW_DYNAMIC_STYLESHEET = null;

/**
 * Main egwDynStyleSheet class - all egwDynStyleSheets share the same stylesheet
 * which is dynamically inserted into the head section of the DOM-Tree.
 * This stylesheet is created with the first egwDynStyleSheet class.
 */
function et2_dynStyleSheet()
{
	// Check whether the EGW_DYNAMIC_STYLESHEET has already be created
	if (!EGW_DYNAMIC_STYLESHEET)
	{
		var style = document.createElement("style");
		document.getElementsByTagName("head")[0].appendChild(style);

		this.styleSheet = style.sheet ? style.sheet : style.styleSheet;
		this.selectors = {};
		this.selectorCount = 0;

		EGW_DYNAMIC_STYLESHEET = this;

		return this;
	}
	else
	{
		return EGW_DYNAMIC_STYLESHEET;
	}
}

/**
 * Creates/Updates the given stylesheet rule. Example call:
 *
 * styleSheet.updateRule("#container", "background-color: blue; font-family: sans;")
 *
 * @param string _selector is the css selector to which the given rule should apply
 * @param string _rule is the rule which is bound to the selector.
 */
et2_dynStyleSheet.prototype.updateRule = function (_selector, _rule)
{
	var ruleObj = {
		"index": this.selectorCount
	}

	// Remove any existing rule first
	if (typeof this.selectors[_selector] !== "undefined")
	{
		var ruleObj = this.selectors[_selector];
		if (typeof this.styleSheet.removeRule !== "undefined")
		{
			this.styleSheet.removeRule(ruleObj.index);
		}
		else
		{
			this.styleSheet.deleteRule(ruleObj.index);
		}

		delete (this.selectors[_selector]);
	}
	else
	{
		this.selectorCount++;
	}

	// Add the rule to the stylesheet
	if (typeof this.styleSheet.addRule !== "undefined")
	{
		this.styleSheet.addRule(_selector, _rule, ruleObj.index);
	}
	else
	{
		this.styleSheet.insertRule(_selector + "{" + _rule + "}", ruleObj.index);
	}

	this.selectors[_selector] = ruleObj;
}

