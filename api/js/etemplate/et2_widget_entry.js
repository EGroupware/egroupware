"use strict";
/*
 * Egroupware etemplate2 JS Entry widget
 * @license http://opensource.org/licenses/gpl-license.php GPL - GNU General Public License
 * @package etemplate
 * @subpackage api
 * @link http://www.egroupware.org
 * @author Nathan Gray
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
    et2_core_valueWidget;
*/
var et2_core_widget_1 = require("./et2_core_widget");
var et2_core_valueWidget_1 = require("./et2_core_valueWidget");
var et2_core_inheritance_1 = require("./et2_core_inheritance");
/**
 * A widget to display a value from an entry
 *
 * Since we have Etemplate\Widget\Transformer, this client side widget exists
 * mostly to resolve the problem where the ID for the entry widget is the same
 * as the widget where you actually set the value, which prevents transformer
 * from working.
 *
 * Server side will find the associated entry, and load it into ~<entry_id> to
 * avoid overwriting the widget with id="entry_id".  This widget will reverse
 * that, and the modifications from transformer will be applied.
 *
 * @augments et2_valueWidget
 */
var et2_entry = /** @class */ (function (_super) {
    __extends(et2_entry, _super);
    function et2_entry(_parent, _attrs, _child) {
        var _this = 
        // Call the inherited constructor
        _super.call(this, _parent, _attrs, et2_core_inheritance_1.ClassWithAttributes.extendAttributes(et2_entry._attributes, _child || {})) || this;
        _this.widget = null;
        // Often the ID conflicts, so check prefix
        if (_attrs.id && _attrs.id.indexOf(et2_entry.prefix) < 0) {
            _attrs.id = et2_entry.prefix + _attrs.id;
        }
        var value = _attrs.value;
        _this = _super.call(this, _parent, _attrs, et2_core_inheritance_1.ClassWithAttributes.extendAttributes(et2_entry._attributes, _child || {})) || this;
        // Save value from parsing, but only if set
        if (value) {
            _this.options.value = value;
        }
        _this.widget = null;
        _this.setDOMNode(document.createElement('span'));
        return _this;
    }
    et2_entry.prototype.loadFromXML = function (_node) {
        // Load the nodes as usual
        _super.prototype.loadFromXML.call(this, _node);
        // Do the magic
        this.loadField();
    };
    /**
     * Initialize widget for entry field
     */
    et2_entry.prototype.loadField = function () {
        // Create widget of correct type
        var attrs = {
            id: this.id + (this.options.field ? '[' + this.options.field + ']' : ''),
            type: 'label',
            readonly: this.options.readonly
        };
        var modifications = this.getArrayMgr("modifications");
        if (modifications && this.options.field) {
            jQuery.extend(attrs, modifications.getEntry(attrs.id));
        }
        // Supress labels on templates
        if (attrs.type == 'template' && this.options.label) {
            this.egw().debug('log', "Surpressed label on <" + this.getType() + ' label="' + this.options.label + '" id="' + this.id + '"...>');
            this.options.label = '';
        }
        var widget = et2_createWidget(attrs.type, attrs, this);
        // If value is not set, etemplate takes care of everything
        // If value was set, find the record explicitly.
        if (typeof this.options.value == 'string') {
            widget.options.value = this.getArrayMgr('content').getEntry(this.id + '[' + this.options.field + ']') ||
                this.getRoot().getArrayMgr('content').getEntry(et2_entry.prefix + this.options.value + '[' + this.options.field + ']');
        }
        else if (this.options.field && this.options.value && this.options.value[this.options.field]) {
            widget.options.value = this.options.value[this.options.field];
        }
        if (this.options.compare) {
            widget.options.value = widget.options.value == this.options.compare ? 'X' : '';
        }
        if (this.options.alternate_fields) {
            var sum = 0;
            var fields = this.options.alternate_fields.split(':');
            for (var i = 0; i < fields.length; i++) {
                var negate = (fields[i][0] == "-");
                var value = this.getArrayMgr('content').getEntry(fields[i].replace('-', ''));
                sum += typeof value === 'undefined' ? 0 : (parseFloat(value) * (negate ? -1 : 1));
                if (value && this.options.field !== 'sum') {
                    widget.options.value = value;
                    break;
                }
            }
            if (this.options.field == 'sum') {
                if (this.options.precision && jQuery.isNumeric(sum))
                    sum = parseFloat(sum).toFixed(this.options.precision);
                widget.options.value = sum;
            }
        }
    };
    et2_entry._attributes = {
        field: {
            'name': 'Fields',
            'description': 'Which entry field to display, or "sum" to add up the alternate_fields',
            'type': 'string'
        },
        compare: {
            name: 'Compare',
            description: 'if given, the selected field is compared with its value and an X is printed on equality, nothing otherwise',
            default: et2_no_init,
            type: 'string'
        },
        alternate_fields: {
            name: 'Alternate fields',
            description: 'colon (:) separated list of alternative fields.  The first non-empty one is used if the selected field is empty, (-) used for subtraction',
            type: 'string',
            default: et2_no_init
        },
        precision: {
            name: 'Decimals to be shown',
            description: 'Specifies the number of decimals for sum of alternates, the default is 2',
            type: 'string',
            default: '2'
        },
        regex: {
            name: 'Regular expression pattern',
            description: 'Only used server-side in a preg_replace with regex_replace to modify the value',
            default: et2_no_init,
            type: 'string'
        },
        regex_replace: {
            name: 'Regular expression replacement pattern',
            description: 'Only used server-side in a preg_replace with regex to modify the value',
            default: et2_no_init,
            type: 'string'
        },
        value: {
            type: 'any'
        },
        readonly: {
            default: true
        }
    };
    et2_entry.legacyOptions = ["field", "compare", "alternate_fields"];
    et2_entry.prefix = '~';
    return et2_entry;
}(et2_core_valueWidget_1.et2_valueWidget));
et2_core_widget_1.et2_register_widget(et2_entry, ["entry", 'contact-value', 'contact-account', 'contact-template', 'infolog-value', 'tracker-value', 'records-value']);
//# sourceMappingURL=et2_widget_entry.js.map