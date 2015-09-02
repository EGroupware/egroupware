/**
 * EGroupware eTemplate2 - JS Number object
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
	phpgwapi.Resumable.resumable;
*/

/**
 * Class which implements file upload
 *
 * @augments et2_inputWidget
 */
var et2_file = et2_inputWidget.extend(
{
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
			"default":0,
			"description": "Largest file accepted, in bytes.  Subject to server limitations.  8MB = 8388608"
		},
		"mime": {
			"name": "Allowed file types",
			"type": "string",
			"default": et2_no_init,
			"description": "Mime type (eg: image/png) or regex (eg: /^text\//i) for allowed file types"
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
			"description": "The ID of an alternate node (div) to display progress and results.  The Node is fetched with et2 getWidgetById so you MUST use the id assigned in XET-File (it may not be available at creation time, so we (re)check on createStatus time)"
		},
		"onStart": {
			"name": "Start event handler",
			"type": "any",
			"default": et2_no_init,
			"description": "A (js) function called when an upload starts.  Return true to continue with upload, false to cancel."
		},
		"onFinish": {
			"name": "Finish event handler",
			"type": "any",
			"default": et2_no_init,
			"description": "A (js) function called when all files to be uploaded are finished."
		},
		drop_target: {
			"name": "Optional, additional drop target for HTML5 uploads",
			"type": "string",
			"default": et2_no_init,
			"description": "The ID of an additional drop target for HTML5 drag-n-drop file uploads"
		},
		label: {
			"name": "Label of file upload",
			"type": "string",
			"default": "Choose file...",
			"description": "String caption to be displayed on file upload span"
		},
		progress_dropdownlist: {
			"name": "List on files in progress like dropdown",
			"type": "boolean",
			"default": false,
			"description": "Style list of files in uploading progress like dropdown list with a total upload progress indicator"
		},
		onFinishOne: {
			"name": "Finish event handler for each one",
			"type": "any",
			"default": et2_no_init,
			"description": "A (js) function called when a file to be uploaded is finished."
		}
	},

	asyncOptions: {},

	/**
	 * Constructor
	 *
	 * @memberOf et2_file
	 */
	init: function() {
		this._super.apply(this, arguments);

		this.node = null;
		this.input = null;
		this.progress = null;
		this.span = null;

		if(!this.options.id) {
			console.warn("File widget needs an ID.  Used 'file_widget'.");
			this.options.id = "file_widget";
		}

		// Legacy - id ending in [] means multiple
		if(this.options.id.substr(-2) == "[]")
		{
			this.options.multiple = true;
		}

		// Set up the URL to have the request ID & the widget ID
		var instance = this.getInstanceManager();

		var self = this;

		this.asyncOptions = jQuery.extend({
			// Callbacks
			onStart: function(event, file_count) {
				return self.onStart(event, file_count);
			},
			onFinish: function(event, file_count) {
				self.onFinish.apply(self, [event, file_count])
			},
			onStartOne: function(event, file_name, index, file_count) {

			},
			onFinishOne: function(event, response, name, number, total) { return self.finishUpload(event,response,name,number,total);},
			onProgress: function(event, progress, name, number, total) { return self.onProgress(event,progress,name,number,total);},
			onError: function(event, name, error) { return self.onError(event,name,error);},
			beforeSend: function(form) { return self.beforeSend(form);},


			target: egw.ajaxUrl(self.egw().getAppName()+".etemplate_widget_file.ajax_upload.etemplate"),
			query: function(file) {return self.beforeSend(file);},
			// Disable checking for already uploaded chunks
			testChunks: false
		},this.asyncOptions);
		this.asyncOptions.fieldName = this.options.id;
		this.createInputWidget();
	},

	destroy: function() {
		this._super.apply(this, arguments);
		this.set_drop_target(null);
		this.node = null;
		this.input = null;
		this.span = null;
		this.progress = null;
	},

	createInputWidget: function() {
		this.node = $j(document.createElement("div")).addClass("et2_file");
		this.span = $j(document.createElement("span"))
			.addClass('et2_file_span et2_button et2_button_text')
			.appendTo (this.node);
		var span = this.span;
		this.input = $j(document.createElement("input"))
			.attr("type", "file").attr("placeholder", this.options.blur)
			.addClass ("et2_file_upload")
			.appendTo(this.node)
			.hover(function(e){
				$j(span)
					.toggleClass('et2_file_spanHover');
			})
			.on({
				mousedown:function (e){
					$j(span).addClass('et2_file_spanActive');
				},
				mouseup:function (e){
					$j(span).removeClass('et2_file_spanActive');
				}
			});

		// Check for File interface, should fall back to normal form submit if missing
		if(typeof File != "undefined" && typeof (new XMLHttpRequest()).upload != "undefined")
		{
			this.resumable = new Resumable(this.asyncOptions);
			this.resumable.assignBrowse(this.input);
			this.resumable.on('fileAdded', jQuery.proxy(this._fileAdded, this));
			this.resumable.on('fileProgress', jQuery.proxy(this._fileProgress, this));
			this.resumable.on('fileSuccess', jQuery.proxy(this.finishUpload, this));
			this.resumable.on('complete', jQuery.proxy(this.onFinish, this));
		}
		else
		{
			// This may be a problem submitting via ajax
		}
		if(this.options.progress)
		{
			var widget = this.getRoot().getWidgetById(this.options.progress);
			if(widget)
			{
				//may be not available at createInputWidget time
				this.progress = $j(widget.getDOMNode());
			}
		}
		if(!this.progress)
		{
			this.progress = $j(document.createElement("div")).appendTo(this.node);
		}
		this.progress.addClass("progress");

		if(this.options.multiple)
		{
			this.input.attr("multiple","multiple");
		}

		this.setDOMNode(this.node[0]);
	},

	/**
	 * Set a widget or DOM node as a HTML5 file drop target
	 *
	 * @param String new_target widget ID or DOM node ID to be used as a new target
	 */
	set_drop_target: function(new_target)
	{
		// Cancel old drop target
		if(this.options.drop_target)
		{
			var widget = this.getRoot().getWidgetById(this.options.drop_target);
			var drop_target = widget && widget.getDOMNode() || document.getElementById(this.options.drop_target);
			if(drop_target)
			{
				this.resumable.unAssignDrop(drop_target);
			}
		}

		this.options.drop_target = new_target;

		if(!this.options.drop_target) return;

		// Set up new drop target
		var widget = this.getRoot().getWidgetById(this.options.drop_target);
		var drop_target = widget && widget.getDOMNode() || document.getElementById(this.options.drop_target);
		if(drop_target)
		{
			this.resumable.assignDrop([drop_target]);
		}
		else
		{
			this.egw().debug("warn", "Did not find file drop target %s", this.options.drop_target);
		}

	},
	attachToDOM: function() {
		this._super.apply(this, arguments);
		// Override parent's change, file widget will fire change when finished uploading
		this.input.unbind("change.et2_inputWidget");
	},
	getValue: function() {
		var value = this.options.value ? this.options.value : this.input.val();
		return value;
	},

	/**
	 * Set the value of the file widget.
	 *
	 * If you pass a FileList or list of files, it will trigger the async upload
	 *
	 * @param {FileList|File[]|false} value List of files to be uploaded, or false to reset.
	 * @param {Event} event Most browsers require the user to initiate file transfers in some way.
	 *	Pass the event in, if you have it.
	 */
	set_value: function(value, event) {
		if(!value || typeof value == "undefined")
		{
			value = {};
		}
		if(jQuery.isEmptyObject(value))
		{
			this.options.value = {};
			if (this.resumable.progress() == 1) this.progress.empty();

			// Reset the HTML element
			this.input.wrap('<form>').closest('form').get(0).reset();
			this.input.unwrap();

			return;
		}

		if(typeof value == 'object' && value.length && typeof value[0] == 'object' && value[0].name)
		{
			try
			{
				this.input[0].files = value;
			}
			catch (e)
			{
				var self = this;
				var args = arguments;
				jQuery.each(value, function(i,file) {self.resumable.addFile(this,event);});
			}
		}
	},

	/**
	 * Set the value for label
	 * The label is used as caption for span tag which customize the HTML file upload styling
	 *
	 * @param {string} value text value of label
	 */
	set_label: function (value)
	{
		if (this.span != null && value != null)
		{
			this.span.text(value);
		}
	},

	getInputNode: function() {
		if (typeof this.input == 'undefined') return false;
		return this.input[0];
	},


	set_mime: function(mime) {
		if(!mime)
		{
			this.options.mime = null;
		}
		if(mime.indexOf("/") != 0)
		{
			// Lower case it now, if it's not a regex
			this.options.mime = mime.toLowerCase();
		}
		else
		{
			// Convert into a js regex
			var parts = mime.substr(1).match(/(.*)\/([igm]?)$/);
			this.options.mime = new RegExp(parts[1],parts.length > 2 ? parts[2] : "");
		}
	},

	set_multiple: function(_multiple) {
		this.options.multiple = _multiple;
		if(_multiple)
		{
			return this.input.attr("multiple", "multiple");
		}
		return this.input.removeAttr("multiple");
	},
	/**
	 * Check to see if the provided file's mimetype matches
	 *
	 * @param f File object
	 * @return boolean
	 */
	checkMime: function(f) {
		// If missing, let the server handle it
		if(!this.options.mime || !f.type) return true;

		var is_preg = (typeof this.options.mime == "object");
		if(!is_preg && f.type.toLowerCase() == this.options.mime || is_preg && this.options.mime.test(f.type))
		{
			return true;
		}

		// Not right mime
		return false;
	},

	_fileAdded: function(file,event) {
		// Manual additions have no event
		if(typeof event == 'undefined')
		{
			event = {};
		}
		// Trigger start of uploading, calls callback
		if(!this.resumable.isUploading())
		{
			if (!(this.onStart(event,this.resumable.files.length))) return;
		}

		// Here 'this' is the input
		if(this.checkMime(file.file))
		{
			if(this.createStatus(event,file))
			{
				// Actually start uploading
				this.resumable.upload();
			}
		}
		else
		{
			// Wrong mime type - show in the list of files
			return this.createStatus(
				this.egw().lang("File is of wrong type (%1 != %2)!", file.file.type, this.options.mime),
				file
			);
		}

	},

	/**
	 * Add in the request id
	 */
	beforeSend: function(form) {
		var instance = this.getInstanceManager();

		return {
			request_id: instance.etemplate_exec_id,
			widget_id: this.id
		};
	},

	/**
	 * Disables submit buttons while uploading
	 */
	onStart: function(event, file_count) {
		// Hide any previous errors
		this.hideMessage();

		// Disable buttons
		this.disabled_buttons = $j("input[type='submit'], button")
				.not("[disabled]")
				.attr("disabled", true)
				.addClass('et2_button_ro')
				.removeClass('et2_clickable')
				.css('cursor', 'default');

		event.data = this;

		//Add dropdown_progress
		if (this.options.progress_dropdownlist)
		{
			this._build_progressDropDownList();
		}

		// Callback
		if(this.options.onStart) return et2_call(this.options.onStart, event, file_count);
		return true;
	},

	/**
	 * Re-enables submit buttons when done
	 */
	onFinish: function() {
		this.disabled_buttons.attr("disabled", false).css('cursor','pointer');

		var file_count = this.resumable.files.length;

		// Remove files from list
		while(this.resumable.files.length > 0)
		{
			this.resumable.removeFile(this.resumable.files[this.resumable.files.length -1]);
		}

		var event = jQuery.Event('upload');

		event.data = this;

		var result = false;

		//Remove progress_dropDown_fileList class and unbind the click handler from body
		if (this.options.progress_dropdownlist)
		{
			this.progress.removeClass("progress_dropDown_fileList");
			jQuery(this.node).find('span').removeClass('totalProgress_loader');
			jQuery('body').off('click');
		}

		if(this.options.onFinish && !jQuery.isEmptyObject(this.getValue()))
		{
			result =  et2_call(this.options.onFinish, event, file_count);
		}
		else
		{
			result = (file_count == 0 || !jQuery.isEmptyObject(this.getValue()));
		}
		if(result)
		{
			// Fire legacy change action when done
			this.change(this.input);
		}
	},

	/**
	 * Build up dropdown progress with total count indicator
	 *
	 * @todo Implement totalProgress bar instead of ajax-loader, in order to show how much percent of uploading is completed
	 */
	_build_progressDropDownList: function ()
	{
		this.progress.addClass("progress_dropDown_fileList");

		//Add uploading indicator and bind hover handler on it
		jQuery(this.node).find('span').addClass('totalProgress_loader');

		jQuery(this.node).find('input').hover(function(){
					jQuery('.progress_dropDown_fileList').show();
		});
		//Bind click handler to dismiss the dropdown while uploading
		jQuery('body').on('click', function(event){
			if (event.target.className != 'remove')
			{
				jQuery('.progress_dropDown_fileList').hide();
			}
		});

	},

	/**
	 * Creates the elements used for displaying the file, and it's upload status, and
	 * attaches them to the DOM
	 *
	 * @param _event Either the event, or an error message
	 */
	createStatus: function(_event, file) {
		var error = (typeof _event == "object" ? "" : _event);

		if(this.options.max_file_size && file.size > this.options.max_file_size) {
			error = this.egw().lang("File too large.  Maximum %1", et2_vfsSize.prototype.human_size(this.options.max_file_size));
		}

		if(this.options.progress)
		{
			var widget = this.getRoot().getWidgetById(this.options.progress);
			if(widget)
			{
				this.progress = $j(widget.getDOMNode());
				this.progress.addClass("progress");
			}
		}
		if(this.progress)
		{
			var fileName = file.fileName || 'file';
			var status = $j("<li data-file='"+fileName+"'>"+fileName
					+"<div class='remove'/><span class='progressBar'><p/></span></li>")
				.appendTo(this.progress);
			$j("div.remove",status).on('click', file, jQuery.proxy(this.cancel,this));
			if(error != "")
			{
				status.addClass("message ui-state-error");
				status.append("<div>"+error+"</diff>");
				$j(".progressBar",status).css("display", "none");
			}
		}
		return error == "";
	},

	_fileProgress: function(file) {
		if(this.progress)
		{
			$j("li[data-file='"+file.fileName+"'] > span.progressBar > p").css("width", Math.ceil(file.progress()*100)+"%");

		}
		return true;
	},

	onError: function(event, name, error) {
		console.warn(event,name,error);
	},

	/**
	 * A file upload is finished, update the UI
	 */
	finishUpload: function(file, response) {
		var name = file.fileName || 'file';

		if(typeof response == 'string') response = jQuery.parseJSON(response);
		if(response.response[0] && typeof response.response[0].data.length == 'undefined') {
			if(typeof this.options.value != 'object') this.options.value = {};
			for(var key in response.response[0].data) {
				if(typeof response.response[0].data[key] == "string")
				{
					// Message from server - probably error
					$j("[data-file='"+name+"']",this.progress)
						.addClass("error")
						.css("display", "block")
						.text(response.response[0].data[key]);
				}
				else
				{
					this.options.value[key] = response.response[0].data[key];
					if(this.progress)
					{
						$j("[data-file='"+name+"']",this.progress).addClass("message success");
					}
				}
			}
		}
		else if (this.progress)
		{
			$j("[data-file='"+name+"']",this.progress)
				.addClass("ui-state-error")
				.css("display", "block")
				.text(this.egw().lang("Server error"));
		}
		var event = jQuery.Event('upload');

		event.data = this;

		// Callback
		if(this.options.onFinishOne)
		{
			return et2_call(this.options.onFinishOne,event,response,name);
		}
		return true;
	},

	/**
	 * Remove a file from the list of values
	 *
	 * @param {File|string} File object, or file name, to remove
	 */
	remove_file: function(file)
	{
		//console.info(filename);
		if(typeof file == 'string')
		{
			file = {fileName: file};
		}
		for(var key in this.options.value)
		{
			if(this.options.value[key].name == file.fileName)
			{
				delete this.options.value[key];
				$j('[data-file="'+file.fileName+'"]',this.node).remove();
				return;
			}
		}
		if(file.isComplete && !file.isComplete() && file.cancel) file.cancel();
	},

	/**
	 * Cancel a file - event callback
	 */
	cancel: function(e)
	{
		e.preventDefault();
		// Look for file name in list
		var target = $j(e.target).parents("li");

		this.remove_file(e.data);

		// In case it didn't make it to the list (error)
		target.remove();
		$j(e.target).remove();
	}
});

et2_register_widget(et2_file, ["file"]);

