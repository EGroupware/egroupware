/**
 * eGroupWare eTemplate2 - JS Number object
 *
 * @license http://opensource.org/licenses/gpl-license.php GPL - GNU General Public License
 * @package etemplate
 * @subpackage api
 * @link http://www.egroupware.org
 * @author Nathan Gray
 * @copyright Nathan Gray 2011
 * @version $Id$
 */

"use strict";

/*egw:uses
	et2_core_inputWidget;
	phpgwapi.jquery.jquery.html5_upload;
*/

/**
 * Class which implements file upload
 */ 
var et2_file = et2_inputWidget.extend({

	attributes: {
		"multiple": {
			"name": "Multiple files",
			"type": "boolean",
			"default": false,
			"description": "Allow the user to select more than one file to upload at a time.  Subject to browser support."
		},
		"max_file_size": {
			"name": "Maximum file size",
			"type": "integer",
			"default":"8388608",
			"description": "Largest file accepted, in bytes.  Subject to server limitations.  8Mb = 8388608"
		},
		"blur": {
                        "name": "Placeholder",
                        "type": "string",
                        "default": "",
                        "description": "This text get displayed if an input-field is empty and does not have the input-focus (blur). It can be used to show a default value or a kind of help-text."
		},
		"progress": {
			"name": "Progress node",
			"type": "string",
			"default": et2_no_init,
			"description": "The ID of an alternate node (div) to display progress and results."
		}
	},

	asyncOptions: {},

	init: function() {
		this._super.apply(this, arguments)

		this.node = null;

		// Set up the URL to have the request ID & the widget ID
		var instance = this.getInstanceManager();
		
		var self = this;
		this.asyncOptions = {
			// Callbacks
			onStartOne: function(event, file_name) { return self.createStatus(event,file_name);},
			onFinishOne: function(event, response, name, number, total) { return self.finishUpload(event,response,name,number,total);},

			sendBoundary: window.FormData || jQuery.browser.mozilla,
			url: egw_json_request.prototype._assembleAjaxUrl("etemplate_widget_file::ajax_upload") + 
				"&request_id="+ instance.etemplate_exec_id
		};
		this.asyncOptions.fieldName = this.options.id;
		this.createInputWidget();
	},
	
	createInputWidget: function() {
		this.node = $j(document.createElement("div")).addClass("et2_file");
		this.input = $j(document.createElement("input"))
			.attr("type", "file").attr("placeholder", this.options.blur)
			.appendTo(this.node);

		// Check for File interface, should fall back to normal form submit if missing
		if(typeof File != "undefined" && typeof (new XMLHttpRequest()).upload != "undefined")
		{
			this.input.html5_upload(this.asyncOptions);
		}
		this.progress = this.options.progress ? 
			$j(document.getElementById(this.options.progress)) : 
			$j(document.createElement("div")).appendTo(this.node);
		this.progress.addClass("progress");

		if(this.options.multiple) {
			this.input.attr("multiple","multiple");
		}
		this.setDOMNode(this.node[0]);
	},
	
	getInputNode: function() {
		return this.input[0];
	},

	/**
	 * Creates the elements used for displaying the file, and it's upload status, and
	 * attaches them to the DOM
	 */
	createStatus: function(event, file_name) {
		if(this.progress)
		{
			$j("<li file='"+file_name+"'>"+file_name+"<div class='progressBar'><p/></div></li>").appendTo(this.progress);
		}
		return true;
	},

	/**
	 * A file upload is finished, update the UI
	 */
	finishUpload: function(event, response, name, number, total) {
		if(this.progress)
		{
			$j("[file='"+name+"']",this.progress).addClass("upload_finished");
		}
	}
});

et2_register_widget(et2_file, ["file"]);

