/**
 * eGroupWare eTemplate2 - Stylesheet class
 *
 * @license http://opensource.org/licenses/gpl-license.php GPL - GNU General Public License
 * @package etemplate
 * @subpackage api
 * @link http://www.egroupware.org
 * @author Andreas Stöckel
 * @copyright Stylite 2011
 */

import './egw_core';

export interface CssModule
{
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
	css(_selector : string, _rule? : string) : void;
}

declare global
{
	interface IegwWndLocal extends CssModule
	{
	}
}

/**
 * Module which allows to add stylesheet rules at runtime.
 */
class Css implements CssModule
{
	/**
	 * Assoziative array which stores the current css rule for a given selector.
	 */
	#selectors : {[selector : string] : number} = {};

	/**
	 * Variable used to calculate unique id for the selectors.
	 */
	#selectorCount = 0;
	#sheet : any;
	#wnd : Window;

	constructor(_wnd : Window)
	{
		this.#wnd = _wnd;
	}

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
	css = (_selector : string, _rule? : string) : void =>
	{
		// Set the current index to the maximum index
		var index = this.#sheet ? Math.min(this.#selectorCount, this.#sheet.cssRules.length) : 0;

		if (!this.#sheet || !this.#sheet.ownerNode || this.#sheet.ownerNode.ownerDocument !== this.#wnd.document)
		{
			// Generate a style tag, which will be used to hold the newly generated css
			// rules.
			var style = this.#wnd.document.createElement('style');
			this.#wnd.document.getElementsByTagName('head')[0].appendChild(style);

			// Obtain the reference to the styleSheet object of the generated style tag
			this.#sheet = style.sheet ? style.sheet : (<any>style).styleSheet;

			this.#selectorCount = 0;
			this.#selectors = {};
		}

		// Remove any existing rule first, of no rule exists for the
		if (typeof this.#selectors[_selector] !== "undefined")
		{
			// Store the old index
			index = this.#selectors[_selector];
			if(index < this.#sheet.cssRules.length)
			{
				if (typeof this.#sheet.removeRule !== "undefined")
				{
					this.#sheet.removeRule(index);
				}
				else
				{
					this.#sheet.deleteRule(index);
				}
			}

			delete (this.#selectors[_selector]);
			if(!_rule)
			{
				this.#selectorCount--;
			}
		}
		else
		{
			this.#selectorCount++;
		}

		if (_rule)
		{
			// Add the rule to the stylesheet
			if (typeof this.#sheet.addRule !== "undefined")
			{
				this.#sheet.addRule(_selector, _rule, index);
			}
			else
			{
				this.#sheet.insertRule(_selector + "{" + _rule + "}", index);
			}

			// Store the new index
			this.#selectors[_selector] = index;
		}
	}
}

egw.extend('css', egw.MODULE_WND_LOCAL, (_app : string, _wnd : Window) => new Css(_wnd));
