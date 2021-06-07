/**
 * EGroupware eTemplate2 - JS barcode widget
 *
 * @license http://opensource.org/licenses/gpl-license.php GPL - GNU General Public License
 * @package etemplate
 * @subpackage api
 * @link https://www.egroupware.org
 * @author Hadi Nategh <hn[at]stylite.de>
 * @copyright EGroupware GmbH
 */

/*egw:uses
	/vendor/bower-asset/jquery/dist/jquery.js;
	/api/js/jquery/barcode/jquery-barcode.min.js;
	et2_core_interfaces;
	et2_core_baseWidget;
*/

/**
 * This widget creates barcode out of a given text
 *
 * The widget can be created in the following ways:
 * <code>
 * var barcodeTag = et2_createWidget("barcode", {
 *	code_type:et2_barcode.TYPE_CSS,
 *	bgColor:"#FFFFFF",
 *	barColor:"#000000",
 *	format:et2_barcode.FORMAT_SVG,
 *	barWidth:"1",
 *	barHeight:"50"
 * });
 * </code>
 * Or by adding XET-tag in your template (.xet) file:
 * <code>
 * <barcode [attributes...]/>
 * </code>
 *
 * Further information about types and formats are defined in static part of the class at the end
 */

import {et2_register_widget, WidgetConfig} from "./et2_core_widget";
import {ClassWithAttributes} from "./et2_core_inheritance";
import {et2_valueWidget} from "./et2_core_valueWidget";

/**
 * Class which implements the "barcode" XET-Tag
 *
 */
export class et2_barcode extends et2_valueWidget
{
	// Class Constants

	/*
	 * type const
	 */
	public static readonly TYPE_CODEBAR = "codebar";
	public static readonly TYPE_CODE11 = "code11"; //(code 11)
	public static readonly TYPE_CODE39 = "code39"; //(code 39)
	public static readonly TYPE_CODE128 = "code128"; //(code 128)
	public static readonly TYPE_EAN8 = "ean8"; //(ean 8) - http://barcode-coder.com/en/ean-8-specification-101.html
	public static readonly TYPE_EAN13 = "ean13"; //(ean 13) - http://barcode-coder.com/en/ean-13-specification-102.html
	public static readonly TYPE_STD25 = "std25"; //(standard 2 of 5 - industrial 2 of 5) - http://barcode-coder.com/en/standard-2-of-5-specification-103.html
	public static readonly TYPE_INT25 = "int25"; //(interleaved 2 of 5)
	public static readonly TYPE_MSI = "msi";
	public static readonly TYPE_DATAMATRIX = "datamatrix"; //(ASCII + extended) - http://barcode-coder.com/en/datamatrix-specification-104.html

	/**
	 * Formats consts
	 */
	public static readonly FORMAT_CSS = "css";
	public static readonly FORMAT_SVG = "svg";
	public static readonly FORMAT_bmp = "bmp";
	public static readonly FORMAT_CANVAS = "canvas";

	static readonly _attributes = {
		"code_type": {
			"name": "code type",
			"type": "string",
			"default": et2_barcode.TYPE_DATAMATRIX,
			"description": "Barcode type to be generated, default is QR barcode"
		},
		bgColor: {
			"name":"bgColor",
			"type": "string",
			"default":'#FFFFFF',
			"description": "Defines backgorund color of barcode container"
		},
		barColor: {
			"name":"barColor",
			"type": "string",
			"default":'#000000',
			"description": "Defines color of the bars in barcode."
		},
		format: {
			"name":"format",
			"type": "string",
			"default":'css', //et2_barcode.FORMAT_CSS
			"description": "Defines in which format the barcode should be rendered. Default is SVG."
		},
		barWidth: {
			"name":"bar width",
			"type": "string",
			"default":'1',
			"description": "Defines width of each bar in the barcode."
		},
		barHeight: {
			"name":"bar height",
			"type": "string",
			"default":'50',
			"description": "Defines heigh of each bar in the barcode."
		},
	};
	private div: JQuery;
	private settings: { output: string; barWidth: string; barHeight: string; bgColor: string; color: string };

	/**
	 * Constructor
	 */
	constructor(_parent, _attrs? : WidgetConfig, _child? : object)
	{
		// Call the inherited constructor
		super(_parent, _attrs, ClassWithAttributes.extendAttributes(et2_barcode._attributes, _child || {}));


		this.div = jQuery(document.createElement('div')).attr({	class:'et2_barcode'	});

		// Set domid
		this.set_id(this.id);

		this.setDOMNode(this.div[0]);
		this.createWidget();
	}

	createWidget()
	{
		this.settings = {
			output:this.options.format,
			bgColor: this.options.bgColor,
			color: this.options.barColor,
			barWidth: this.options.barWidth,
			barHeight: this.options.barHeight,
		};
		if (this.get_value())
		{
			// @ts-ignore
			this.div.barcode(this.get_value(), this.options.code_type, this.settings);
		}
	}

	set_value(_val)
	{
		if (typeof _val !== 'undefined')
		{
			this.value = _val;
			this.createWidget();
		}
	}
}
et2_register_widget(et2_barcode, ["barcode"]);