/**
 * EGroupware eTemplate2 - JS Box object
 *
 * @license http://opensource.org/licenses/gpl-license.php GPL - GNU General Public License
 * @package etemplate
 * @subpackage api
 * @link http://www.egroupware.org
 * @author Andreas Stöckel
 * @copyright Stylite 2011
 * @version $Id$
 */

/*egw:uses
	jquery.jquery;
	et2_core_baseWidget;
*/

/**
 * Class which implements box and vbox tag
 *
 * @augments et2_baseWidget
 */
var et2_box = (function(){ "use strict"; return et2_baseWidget.extend([et2_IDetachedDOM],
{
	attributes: {
		// Not needed
		"rows": {"ignore": true},
		"cols": {"ignore": true}
	},

	createNamespace: true,

	/**
	 * Constructor
	 *
	 * @memberOf et2_box
	 */
	init: function() {
		this._super.apply(this, arguments);

		this.div = $j(document.createElement("div"))
			.addClass("et2_" + this._type)
			.addClass("et2_box_widget");

		this.setDOMNode(this.div[0]);
	},

	/**
	 * Overriden so we can check for autorepeating children.  We only check for
	 * $ in the immediate children & grandchildren of this node.
	 *
	 * @param {object} _node
	 */
	loadFromXML: function(_node) {
		if(this._type != "box")
		{
			return this._super.apply(this, arguments);
		}
		// Load the child nodes.
		var childIndex = 0;
		var repeatNode = null;
		for (var i=0; i < _node.childNodes.length; i++)
		{
			var node = _node.childNodes[i];
			var widgetType = node.nodeName.toLowerCase();

			if (widgetType == "#comment")
			{
				continue;
			}

			if (widgetType == "#text")
			{
				if (node.data.replace(/^\s+|\s+$/g, ''))
				{
					this.loadContent(node.data);
				}
				continue;
			}

			// Create the new element, if no expansion needed
			var id = et2_readAttrWithDefault(node, "id", "");
			if(id.indexOf('$') < 0 || widgetType != 'box')
			{
				this.createElementFromNode(node);
				childIndex++;
			}
			else
			{
				repeatNode = node;
			}
		}

		// Only the last child repeats(?)
		if(repeatNode != null)
		{
			var currentPerspective = this.getArrayMgr("content").perspectiveData;
			// Extra content
			for(childIndex; typeof this.getArrayMgr("content").data[childIndex] != "undefined" && this.getArrayMgr("content").data[childIndex]; childIndex++) {
				// Adjust for the row
				var mgrs = this.getArrayMgrs();
				for(var name in mgrs)
				{
					if(this.getArrayMgr(name).getEntry(childIndex))
					{
						this.getArrayMgr(name).perspectiveData.row = childIndex;
					}
				}

				this.createElementFromNode(repeatNode);
			}

			// Reset
			for(var name in this.getArrayMgrs())
			{
				this.getArrayMgr(name).perspectiveData = currentPerspective;
			}
		}
	},

	/**
	 * Code for implementing et2_IDetachedDOM
	 * This doesn't need to be implemented.
	 * Individual widgets are detected and handled by the grid, but the interface is needed for this to happen
	 *
	 * @param {array} _attrs array to add further attributes to
	 */
	getDetachedAttributes: function(_attrs)
	{
		_attrs.push('data');
	},

	getDetachedNodes: function()
	{
		return [this.getDOMNode()];
	},

	setDetachedAttributes: function(_nodes, _values)
	{
		if (_values.data)
		{
			var pairs = _values.data.split(/,/g);
			for(var i=0; i < pairs.length; ++i)
			{
				var name_value = pairs[i].split(':');
				$j(_nodes[0]).attr('data-'+name_value[0], name_value[1]);
			}
		}
	}

});}).call(this);
et2_register_widget(et2_box, ["vbox", "box"]);

/**
 * Details widget implementation
 * widget name is "details" and can be use as a wrapping container
 * in order to make its children collapsible.
 *
 * Note: details widget does not represent html5 "details" tag in DOM
 *
 * <details>
 *		<widgets>
 *		....
 * <details/>
 *
 */
var et2_details = (function(){ "use strict"; return et2_box.extend(
{
	attributes:{
		"toggle_align": {
			name: "Toggle button alignment",
			description:" Defines where to align the toggle button, default is right alignment",
			type:"string",
			default: "right"
		},
		title: {
			name: "title",
			description:"Set a header title for box and shows it next to toggle button, default is no title",
			type:"string",
			default: ""
		}
	},

	init: function() {
		this._super.apply(this, arguments);

		this.div = jQuery(document.createElement('div')).addClass('et2_details');
		this.title = jQuery(document.createElement('span'))
				.addClass('et2_label et2_details_title')
				.appendTo(this.div);
		this.span = jQuery(document.createElement('span'))
				.addClass('et2_details_toggle')
				.appendTo(this.div);
		this.wrapper = jQuery(document.createElement('div'))
				.addClass('et2_details_wrapper')
				.appendTo(this.div);


		this._createWidget();
	},

	/**
	 * Function happens on toggle action
	 */
	_toggle: function (){
		this.div.toggleClass('et2_details_expanded');
	},

	/**
	 * Create widget, set contents, and binds handlers
	 */
	_createWidget: function () {
		var self = this;

		this.span.on('click', function (e){
			self._toggle();
		});

		//Set header title
		if (this.options.title)
		{
			this.title
					.click (function(){self._toggle();})
					.text(this.options.title);
		}

		// Align toggle button left/right
		if (this.options.toggle_align === "left") this.span.css({float:'left'});
	},

	getDOMNode: function(_sender) {
		if (!_sender || _sender === this)
		{
			return this.div[0];
		}
		else
		{
			return this.wrapper[0];
		}
	}
});}).call(this);
et2_register_widget(et2_details, ["details"]);