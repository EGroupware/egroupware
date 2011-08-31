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

	init: function() {
		this._super.apply(this, arguments)

		this.node = null;

		this.createInputWidget();
	},
	
	createInputWidget: function() {
		this.node = $j(document.createElement("div")).addClass("et2_file");
		this.input = $j(document.createElement("input"))
			.attr("type", "file").attr("placeholder", this.options.blur)
			.appendTo(this.node)
			.change(this, this.change);
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
	 * User has selected some files file, send them off
	 */
	change: function(e) {
console.log(e);		
		if(!e.target.files || e.target.files.length == 0) return;

		for(var i = 0; i < e.target.files.length; i++) {
			var file = e.target.files[i];

			// Add to list
			var status = this.createStatus(file);
			this.progress.append(status);

			// Check if OK
			if(file.size > this.options.max_file_size) {
				// TODO: Try to slice into acceptable pieces
				status.addClass("ui-state-error");
				this.showMessage(egw.lang("File is too large."), "validation_error");
				var self = this;
				window.setTimeout(function() {self.hideMessage(true); status.remove();}, 5000);
			}
		}

		// Reset field
		e.data.input.attr("type", "input").attr("type","file");
	},

	/**
	 * Upload a single file asyncronously
	 */
	_upload_file: function(file) {
		// Start upload
	console.log(this, file);
		
	},

	/**
	 * Creates the elements used for displaying the file, and it's upload status
	 *
	 * @param file A JS File object
	 * @return a jQuery object with the elements
	 */
	createStatus: function(file) {
		return $j("<li>"+file.name+"<div class='progressBar'><p/></div></li>");
	}
	
});

et2_register_widget(et2_file, ["file"]);

