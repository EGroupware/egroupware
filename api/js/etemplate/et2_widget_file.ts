/**
 * EGroupware eTemplate2 - JS Number object
 *
 * @license http://opensource.org/licenses/gpl-license.php GPL - GNU General Public License
 * @package etemplate
 * @subpackage api
 * @link https://www.egroupware.org
 * @author Nathan Gray
 * @copyright Nathan Gray 2011
 */

/*egw:uses
	et2_core_inputWidget;
	api.Resumable.resumable;
*/
import "../Resumable/resumable.js";
import {et2_inputWidget} from "./et2_core_inputWidget";
import {et2_register_widget, WidgetConfig} from "./et2_core_widget";
import {ClassWithAttributes} from "./et2_core_inheritance";
import {et2_no_init} from "./et2_core_common";
import {et2_DOMWidget} from "./et2_core_DOMWidget";
import {et2_vfsSize} from "./et2_widget_vfs";

/**
 * Class which implements file upload
 *
 * @augments et2_inputWidget
 */
export class et2_file extends et2_inputWidget
{
	static readonly _attributes : any = {
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
			"type": "js",
			"default": et2_no_init,
			"description": "A (js) function called when a file to be uploaded is finished."
		},
		accept: {
			"name": "Acceptable extensions",
			"type": "string",
			"default": '',
			"description": "Define types of files that the server accepts. Multiple types can be seperated by comma and the default is to accept everything."
		},
		chunk_size: {
			"name": "Chunk size",
			"type": "integer",
			"default": 1024*1024,
			"description": "Max chunk size, gets set from server-side PHP (max_upload_size-1M)/2"	// last chunk can be up to 2*chunk_size!
		}
	};

	asyncOptions : any = {};
	input : JQuery = null;
	progress : JQuery = null;
	span : JQuery = null;
	disabled_buttons : JQuery;
	resumable : any;

	/**
	 * Constructor
	 *
	 * @memberOf et2_file
	 */
	constructor(_parent, _attrs? : WidgetConfig, _child? : object)
	{
		// Call the inherited constructor
		super(_parent, _attrs, ClassWithAttributes.extendAttributes(et2_file._attributes, _child || {}));

		this.node = null;
		this.input = null;
		this.progress = null;
		this.span = null;
		// Contains all submit buttons need to be disabled during upload process
		this.disabled_buttons = jQuery("input[type='submit'], button");

		// Make sure it's an object, not an array, or values get lost when sent to server
		this.options.value = jQuery.extend({},this.options.value);

		if(!this.options.id) {
			console.warn("File widget needs an ID.  Used 'file_widget'.");
			this.options.id = "file_widget";
		}

		// Legacy - id ending in [] means multiple
		if(this.options.id.substr(-2) == "[]")
		{
			this.options.multiple = true;
		}
		// If ID ends in /, it's a directory - allow multiple
		else if (this.options.id.substr(-1) === "/")
		{
			this.options.multiple = true;
			_attrs.multiple = true;
		}

		// Set up the URL to have the request ID & the widget ID
		var instance = this.getInstanceManager();

		let self = this;

		this.asyncOptions = jQuery.extend({

		},this.getAsyncOptions(this));
		this.asyncOptions.fieldName = this.options.id;
		this.createInputWidget();
		this.set_readonly(this.options.readonly);
	}

	destroy()
	{
		super.destroy();
		this.set_drop_target(null);
		this.node = null;
		this.input = null;
		this.span = null;
		this.progress = null;
	}

	createInputWidget()
	{
		this.node = <HTMLElement><unknown>jQuery(document.createElement("div")).addClass("et2_file");
		this.span = jQuery(document.createElement("et2-button"))
			.addClass('et2_file_span')
			.attr("image", "attach")
			.attr('label', this.options.label || '')
			.attr("noSubmit", true)
			.appendTo(this.node);
		
		let span = this.span;
		this.input = jQuery(document.createElement("input"))
			.attr("type", "file").attr("placeholder", this.options.blur)
			.addClass("et2_file_upload")
			.appendTo(this.node)
			.hover(function()
			{
				jQuery(span)
					.toggleClass('et2_file_spanHover');
			})
			.on({
				mousedown:function (){
					jQuery(span).addClass('et2_file_spanActive');
				},
				mouseup:function (){
					jQuery(span).removeClass('et2_file_spanActive');
				}
			});
		let self = this;
		// trigger native input upload file
		if (!this.options.readonly) this.span.click(function(){self.input.click()});
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
		if (this.options.accept) this.input.attr('accept', this.options.accept);

		if(this.options.progress)
		{
			let widget = this.getRoot().getWidgetById(this.options.progress);
			if(widget)
			{
				//may be not available at createInputWidget time
				this.progress = jQuery(widget.getDOMNode());
			}
		}
		if(!this.progress)
		{
			this.progress = jQuery(document.createElement("div")).appendTo(this.node);
		}
		this.progress.addClass("progress");

		if(this.options.multiple)
		{
			this.input.attr("multiple","multiple");
		}

		this.setDOMNode(this.node[0]);
		// set drop target to widget dom node if no target option is specified
		if(!this.options.drop_target)
		{
			this.resumable.assignDrop([this.getDOMNode()]);
		}
		else
		{
			this.set_drop_target(this.options.drop_target);
		}
	}

	/**
	 * Get any specific async upload options
	 */
	getAsyncOptions(self: et2_file)
	{
		return {
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

			chunkSize: this.options.chunk_size || 1024*1024,

			target: egw.ajaxUrl("EGroupware\\Api\\Etemplate\\Widget\\File::ajax_upload"),
			query: function(file) {return self.beforeSend(file);},
			// Disable checking for already uploaded chunks
			testChunks: false
		};
	}
	/**
	 * Set a widget or DOM node as a HTML5 file drop target
	 *
	 * @param {string} new_target widget ID or DOM node ID to be used as a new target
	 */
	set_drop_target(new_target : string)
	{
		// Cancel old drop target
		if(this.options.drop_target)
		{
			let widget = this.getRoot().getWidgetById(this.options.drop_target);
			let drop_target = widget && widget.getDOMNode() || document.getElementById(this.options.drop_target);
			if(drop_target)
			{
				this.resumable.unAssignDrop([drop_target]);
			}
		}

		this.options.drop_target = new_target;

		if(!this.options.drop_target) return;

		// Set up new drop target
		let widget = <et2_DOMWidget>this.getRoot().getWidgetById(this.options.drop_target);
		let drop_target = widget && widget.getDOMNode() || document.getElementById(this.options.drop_target);
		if(drop_target)
		{
			this.resumable.assignDrop([drop_target]);
		}
		else
		{
			this.egw().debug("warn", "Did not find file drop target %s", this.options.drop_target);
		}

	}

	attachToDOM()
	{
		let res = super.attachToDOM();
		// Override parent's change, file widget will fire change when finished uploading
		this.input.unbind("change.et2_inputWidget");
		return res;
	}

	getValue()
	{
		return this.options.value ? this.options.value : this.input.val();
	}

	/**
	 * Set the value of the file widget.
	 *
	 * If you pass a FileList or list of files, it will trigger the async upload
	 *
	 * @param {FileList|File[]|false} value List of files to be uploaded, or false to reset.
	 * @param {Event} event Most browsers require the user to initiate file transfers in some way.
	 *	Pass the event in, if you have it.
	 */
	set_value(value, event?) : boolean
	{
		if (!value || typeof value == "undefined") {
			value = {};
		}
		if (jQuery.isEmptyObject(value)) {
			this.options.value = {};
			if (this.resumable.progress() == 1) this.progress.empty();

			// Reset the HTML element
			this.input.wrap('<form>').closest('form').get(0).reset();
			this.input.unwrap();

			return;
		}

		let addFile = jQuery.proxy(function (i, file) {
			this.resumable.addFile(file, event);
		}, this);
		if (typeof value == 'object' && value.length && typeof value[0] == 'object' && value[0].name) {
			try {
				this.input[0].files = value;

				jQuery.each(value, addFile);
			} catch (e) {
				var self = this;
				var args = arguments;
				jQuery.each(value, addFile);
			}
		}
	}

	/**
	 * Set the value for label
	 * The label is used as caption for span tag which customize the HTML file upload styling
	 *
	 * @param {string} value text value of label
	 */
	set_label(value)
	{
		if (this.span != null && value != null)
		{
			this.span.label = value;
		}
	}

	getInputNode()
	{
		if (typeof this.input == 'undefined') return <HTMLElement><unknown>false;
		return this.input[0];
	}


	set_mime(mime)
	{
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
			this.options.mime = mime;
		}
	}

	set_multiple(_multiple)
	{
		this.options.multiple = _multiple;
		if(_multiple)
		{
			return this.input.attr("multiple", "multiple");
		}
		return this.input.removeAttr("multiple");
	}

	/**
	 * Check to see if the provided file's mimetype matches
	 *
	 * @param f File object
	 * @return boolean
	 */
	checkMime(f)
	{
		if(!this.options.mime) return true;

		let mime: string | RegExp = '';
		if(this.options.mime.indexOf("/") != 0)
		{
			// Lower case it now, if it's not a regex
			mime = this.options.mime.toLowerCase();
		}
		else
		{
			// Convert into a js regex
			var parts = this.options.mime.substr(1).match(/(.*)\/([igm]?)$/);
			mime = new RegExp(parts[1],parts.length > 2 ? parts[2] : "");
		}

		// If missing, let the server handle it
		if(!mime || !f.type) return true;

		var is_preg = (typeof mime == "object");
		if(!is_preg && f.type.toLowerCase() == mime || is_preg && mime.test(f.type))
		{
			return true;
		}

		// Not right mime
		return false;
	}

	private _fileAdded(file,event)
	{
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

				// Disable buttons
				this.disabled_buttons
					.not("[disabled]")
					.attr("disabled", true)
					.addClass('et2_button_ro')
					.removeClass('et2_clickable')
					.css('cursor', 'default');

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

	}

	/**
	 * Add in the request id
	 */
	beforeSend(form) {
		var instance = this.getInstanceManager();

		return {
			request_id: instance.etemplate_exec_id,
			widget_id: this.id
		};
	}

	/**
	 * Disables submit buttons while uploading
	 */
	onStart(event, file_count) {
		// Hide any previous errors
		this.hideMessage();


		event.data = this;

		//Add dropdown_progress
		if (this.options.progress_dropdownlist)
		{
			this._build_progressDropDownList();
		}

		// Callback
		if(this.options.onStart) return et2_call(this.options.onStart, event, file_count);
		return true;
	}

	/**
	 * Re-enables submit buttons when done
	 */
	onFinish() {
		this.disabled_buttons.removeAttr("disabled").css('cursor','pointer').removeClass('et2_button_ro');

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
	}

	/**
	 * Build up dropdown progress with total count indicator
	 *
	 * @todo Implement totalProgress bar instead of ajax-loader, in order to show how much percent of uploading is completed
	 */
	private _build_progressDropDownList()
	{
		this.progress.addClass("progress_dropDown_fileList");

		//Add uploading indicator and bind hover handler on it
		jQuery(this.node).find('span').addClass('totalProgress_loader');

		jQuery(this.node).find('span.et2_file_span').hover(function(){
			jQuery('.progress_dropDown_fileList').show();
		});
		//Bind click handler to dismiss the dropdown while uploading
		jQuery('body').on('click', function(event){
			if (event.target.className != 'remove')
			{
				jQuery('.progress_dropDown_fileList').hide();
			}
		});

	}

	/**
	 * Creates the elements used for displaying the file, and it's upload status, and
	 * attaches them to the DOM
	 *
	 * @param _event Either the event, or an error message
	 */
	createStatus(_event, file)
	{
		var error = (typeof _event == "object" ? "" : _event);

		if(this.options.max_file_size && file.size > this.options.max_file_size) {
			error = this.egw().lang("File too large.  Maximum %1", et2_vfsSize.prototype.human_size(this.options.max_file_size));
		}

		if(this.options.progress)
		{
			var widget = <et2_DOMWidget>this.getRoot().getWidgetById(this.options.progress);
			if(widget)
			{
				this.progress = jQuery(widget.getDOMNode());
				this.progress.addClass("progress");
			}
		}
		if(this.progress)
		{
			var fileName = file.fileName || 'file';
			var status = jQuery("<li data-file='"+fileName.replace(/'/g, '&quot')+"'>"+fileName
				+"<div class='remove'/><span class='progressBar'><p/></span></li>")
				.appendTo(this.progress);
			jQuery("div.remove",status).on('click', file, jQuery.proxy(this.cancel,this));
			if(error != "")
			{
				status.addClass("message ui-state-error");
				status.append("<div>"+error+"</diff>");
				jQuery(".progressBar",status).css("display", "none");
			}
		}
		return error == "";
	}

	private _fileProgress(file)
	{
		if(this.progress)
		{
			jQuery("li[data-file='"+file.fileName.replace(/'/g, '&quot')+"'] > span.progressBar > p").css("width", Math.ceil(file.progress()*100)+"%");

		}
		return true;
	}

	onError(event, name, error)
	{
		console.warn(event,name,error);
	}

	/**
	 * A file upload is finished, update the UI
	 */
	finishUpload(file, response)
	{
		var name = file.fileName || 'file';

		if(typeof response == 'string') response = jQuery.parseJSON(response);
		if(response.response[0] && typeof response.response[0].data.length == 'undefined') {
			if(typeof this.options.value !== 'object' || !this.options.multiple)
			{
				this.set_value({});
			}
			for(var key in response.response[0].data) {
				if(typeof response.response[0].data[key] == "string")
				{
					// Message from server - probably error
					jQuery("[data-file='"+name.replace(/'/g, '&quot')+"']",this.progress)
						.addClass("error")
						.css("display", "block")
						.text(response.response[0].data[key]);
				}
				else
				{
					this.options.value[key] = response.response[0].data[key];
					// If not multiple, we already destroyed the status, so re-create it
					if(!this.options.multiple)
					{
						this.createStatus({}, file);
					}
					if(this.progress)
					{
						jQuery("[data-file='"+name.replace(/'/g, '&quot')+"']",this.progress).addClass("message success");
					}
				}
			}
		}
		else if (this.progress)
		{
			jQuery("[data-file='"+name.replace(/'/g, '&quot')+"']",this.progress)
				.addClass("ui-state-error")
				.css("display", "block")
				.text(this.egw().lang("Server error"));
		}
		var event = jQuery.Event('upload');

		event.data = this;

		// Callback
		if(typeof this.onFinishOne == 'function')
		{
			this.onFinishOne(event, response, name);
		}
		return true;
	}

	/**
	 * Remove a file from the list of values
	 *
	 * @param {File|string} File object, or file name, to remove
	 */
	remove_file(file)
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
				jQuery('[data-file="'+file.fileName.replace(/'/g, '&quot')+'"]',this.node).remove();
				return;
			}
		}
		if(file.isComplete && !file.isComplete() && file.cancel) file.cancel();
	}

	/**
	 * Cancel a file - event callback
	 */
	cancel(e)
	{
		e.preventDefault();
		// Look for file name in list
		var target = jQuery(e.target).parents("li");

		this.remove_file(e.data);

		// In case it didn't make it to the list (error)
		target.remove();
		jQuery(e.target).remove();
	}

	/**
	 * Set readonly
	 *
	 * @param {boolean} _ro boolean readonly state, true means readonly
	 */
	set_readonly(_ro)
	{
		if (typeof _ro != "undefined")
		{
			this.options.readonly = _ro;
			this.span.toggleClass('et2_file_ro', _ro);
			this.span[0].readonly = _ro;
			if (this.options.readonly)
			{
				this.span.unbind('click');
			}
			else
			{
				var self = this;
				this.span.off().bind('click',function(){self.input.click()});
			}
		}
	}
}
et2_register_widget(et2_file, ["file"]);