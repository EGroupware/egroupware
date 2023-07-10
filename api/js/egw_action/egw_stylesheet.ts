/**
 * eGroupWare egw_action framework - egw action framework
 *
 * @link http://www.egroupware.org
 * @author Andreas Stöckel <as@stylite.de>
 * @copyright 2011 by Andreas Stöckel
 * @license http://opensource.org/licenses/gpl-license.php GPL - GNU General Public License
 * @package egw_action
 * @version $Id$
 */

/**
 * Contains the egwDynStyleSheet class which allows dynamic generation of stylesheet
 * rules - updating a single stylesheet rule is way more efficient than updating
 * the element style of many objects.
 */
class EgwDynamicStyleSheet {
    private readonly styleSheet: CSSStyleSheet;
    //mapping of selectors to indices in the Stylesheet's CSSRuleList
    private selectors: Map<string, number>;

    //selector count is no longer needed, since insert rules returns the index to store in the map above

    constructor() {
        const style = document.createElement("style");
        document.head.appendChild(style);
        this.styleSheet = style.sheet;
        this.selectors = new Map;
    }

    /**
     * Creates/Updates the given stylesheet rule. Example call:
     *
     * styleSheet.updateRule(".someCssClass", "background-color: blue; font-family: sans;")
     *
     * @param selector is the css selector to which the given rule should apply
     * @param rule is the rule which is bound to the selector.
     */
    public updateRule(selector: string, rule: string): void {
        // Remove any existing rule first
        if (this.selectors.has(selector)) {
            let index: number = this.selectors.get(selector)
            this.styleSheet.deleteRule(index)
        }

        //Add the rule to the stylesheet
        let index = this.styleSheet.insertRule(selector + "{" + rule + "}")
        this.selectors.set(selector, index)
    }
}

export const EGW_DYNAMIC_STYLESHEET: EgwDynamicStyleSheet = new EgwDynamicStyleSheet();