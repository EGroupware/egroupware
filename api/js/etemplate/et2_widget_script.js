"use strict";
/**
 * EGroupware eTemplate2 - JS widget class containing javascript
 *
 * @license http://opensource.org/licenses/gpl-license.php GPL - GNU General Public License
 * @package etemplate
 * @subpackage api
 * @link http://www.egroupware.org
 * @author Ralf Becker
 */
var __extends = (this && this.__extends) || (function () {
    var extendStatics = function (d, b) {
        extendStatics = Object.setPrototypeOf ||
            ({ __proto__: [] } instanceof Array && function (d, b) { d.__proto__ = b; }) ||
            function (d, b) { for (var p in b) if (b.hasOwnProperty(p)) d[p] = b[p]; };
        return extendStatics(d, b);
    };
    return function (d, b) {
        extendStatics(d, b);
        function __() { this.constructor = d; }
        d.prototype = b === null ? Object.create(b) : (__.prototype = b.prototype, new __());
    };
})();
Object.defineProperty(exports, "__esModule", { value: true });
/*egw:uses
    et2_core_widget;
*/
var et2_core_widget_1 = require("./et2_core_widget");
var et2_core_widget_2 = require("./et2_core_widget");
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
var et2_script = /** @class */ (function (_super) {
    __extends(et2_script, _super);
    function et2_script(_parent, _attrs, _child) {
        var _this = _super.call(this) || this;
        // Allow no child widgets
        _this.supportedWidgetClasses = [];
        return _this;
    }
    ;
    /**
     * We can NOT create a script tag containing the content, as this violoates our CSP policy!
     *
     * @param {string} _content
     */
    et2_script.prototype.loadContent = function (_content) {
        try {
            var func = new Function(_content);
            func.call(window);
        }
        catch (e) {
            this.egw.debug('error', 'Error while executing script: ', _content, e);
        }
    };
    return et2_script;
}(et2_core_widget_2.et2_widget));
et2_core_widget_1.et2_register_widget(et2_script, ["script"]);
//# sourceMappingURL=et2_widget_script.js.map