/**
 * EGroupware eTemplate2 - JS Placeholder widgets
 *
 * @license http://opensource.org/licenses/gpl-license.php GPL - GNU General Public License
 * @package etemplate
 * @subpackage api
 * @link https://www.egroupware.org
 * @author Nathan Gray
 * @copyright Nathan Gray 2021
 */

import {et2_register_widget, WidgetConfig} from "./et2_core_widget";
import {ClassWithAttributes} from "./et2_core_inheritance";
import {et2_inputWidget} from "./et2_core_inputWidget";
import {Et2Dialog} from "./Et2Dialog/Et2Dialog";
import {Et2LinkEntry} from "./Et2Link/Et2LinkEntry";
import {Et2Select} from "./Et2Select/Et2Select";
import {Et2Description} from "./Et2Description/Et2Description";
import {Et2Button} from "./Et2Button/Et2Button";

/**
 * Display a dialog to choose a placeholder
 */
export class et2_placeholder_select extends et2_inputWidget
{
	static readonly _attributes : any = {
		insert_callback: {
			"name": "Insert callback",
			"description": "Method called with the selected placeholder text",
			"type": "js"
		},
		dialog_title: {
			"name": "Dialog title",
			"type": "string",
			"default": "Insert Placeholder"
		}
	};

	static placeholders : Object | null = null;

	button : JQuery;
	submit_callback : any;
	dialog : Et2Dialog;
	protected value : any;

	protected LIST_URL = 'EGroupware\\Api\\Etemplate\\Widget\\Placeholder::ajax_get_placeholders';
	protected TEMPLATE = '/api/templates/default/insert_merge_placeholder.xet?1';

	/**
	 * Constructor
	 *
	 * @param _parent
	 * @param _attrs
	 * @memberOf et2_vfsSelect
	 */
	constructor(_parent, _attrs? : WidgetConfig, _child? : object)
	{
		// Call the inherited constructor
		super(_parent, _attrs, ClassWithAttributes.extendAttributes(et2_placeholder_select._attributes, _child || {}));

		// Allow no child widgets
		this.supportedWidgetClasses = [];
	}

	/**
	 * The placeholders this widget offers.
	 *
	 * Read through the actual class instead of et2_placeholder_select, so a subclass that brings
	 * its own fixed list (and therefore needs no server request at all) is not made to use - or
	 * overwrite - the full list the base class asks the server for.
	 */
	protected get placeholders() : Object | null
	{
		return (<typeof et2_placeholder_select>this.constructor).placeholders;
	}

	protected set placeholders(_placeholders : Object | null)
	{
		(<typeof et2_placeholder_select>this.constructor).placeholders = _placeholders;
	}

	_content(_content, _callback)
	{
		let self = this;
		if(this.dialog)
		{
			this.dialog.close();
		}

		var callback = _callback || this._buildDialog;
		if(this.placeholders !== null)
		{
			this._buildDialog(this.placeholders);
			return;
		}

		this.egw().loading_prompt('placeholder_select', true, '', 'body');

		// Clear the (body wide, blocking) loading prompt on every way out of the request, or the
		// user is left staring at an overlay with no dialog and no idea that anything went wrong.
		const failed = (_message) =>
		{
			this.egw().loading_prompt('placeholder_select', false);
			this.egw().message(_message, 'error');
		};

		this.egw().json(
			this.LIST_URL,
			[],
			function(_content)
			{
				if(typeof _content === 'object' && _content !== null && _content.message)
				{
					// Something went wrong
					failed(_content.message);
					return;
				}
				if(!_content || typeof _content !== 'object' || Object.keys(_content).length === 0)
				{
					// Nothing to show - building the dialog with this would just throw
					failed(this.egw().lang('No placeholders found'));
					return;
				}
				this.egw().loading_prompt('placeholder_select', false);
				this.placeholders = _content;
				callback.apply(self, arguments);
			}.bind(this)
		).sendRequest(true, 'POST', (_err) =>
		{
			// Request itself failed (network, server error, ...), the success callback never runs
			console.error("Could not load placeholders", _err);
			failed(this.egw().lang('Error loading placeholders'));
		});
	}

	/**
	 * Builds placeholder selection dialog
	 *
	 * @param {object} _data content
	 */
	protected _buildDialog(_data)
	{
		let buttons = [
			{
				label: this.egw().lang("Insert"),
				id: "submit",
				image: "export"
			}
		];
		let extra_buttons_action = {};

		if(this.options.extra_buttons && this.options.method)
		{
			for(let i = 0; i < this.options.extra_buttons.length; i++)
			{
				delete (this.options.extra_buttons[i]['click']);
				buttons.push(this.options.extra_buttons[i]);
				extra_buttons_action[this.options.extra_buttons[i]['id']] = this.options.extra_buttons[i]['id'];
			}

		}
		buttons.push({label: this.egw().lang("Cancel"), id: "cancel", image: "cancel"});

		let data = {
			content: {app: '', group: '', entry: {}},
			sel_options: {app: [], group: []},
			modifications: {
					entry: {
						application_list: []
					}

			}
		};

		Object.keys(_data).map((key) =>
		{
			data.sel_options.app.push(
				{
					value: key,
					label: this.egw().lang(key)
				});
		});
		data.sel_options.group = this._get_group_options(Object.keys(_data)[0]);
		data.content.app = data.sel_options.app[0].value;
		data.content.group = data.sel_options.group[0]?.value;
		data.content.entry = {app: data.content.app};
		// Remove non-app placeholders (user & general).  Filter instead of splice(indexOf(...)):
		// indexOf() returns -1 for an app that is not in the list and splice(-1, 1) would then
		// throw away the last real application instead.
		let non_apps = ['user', 'general'];
		data.modifications.entry.application_list = Object.keys(_data).filter(app => non_apps.indexOf(app) < 0);

		// callback for dialog
		this.submit_callback = function(submit_button_id, submit_value)
		{
			if((submit_button_id == 'submit' || (extra_buttons_action && extra_buttons_action[submit_button_id])) && submit_value)
			{
				this._do_insert_callback(submit_value);
				return true;
			}
			else if(submit_button_id == 'cancel')
			{
				return true;
			}
			else
			{
				// Keep dialog open
				return false;
			}
		}.bind(this);

		this.dialog = new Et2Dialog(this.egw());
		this.dialog.transformAttributes({
			callback: this.submit_callback,
			title: this.options.dialog_title || "Insert Placeholder",
			buttons: buttons,
			value: data,
			template: this.egw().webserverUrl + this.TEMPLATE,
			resizable: true,
			width: ''
		});
		document.body.appendChild(<HTMLElement><unknown>this.dialog);
		this.dialog.addEventListener('load', this._on_template_load.bind(this));
	}

	doLoadingFinished()
	{
		this._content.call(this, null);
		return true;
	}

	/**
	 * Post-load of the dialog
	 * Bind internal events, set some things that are difficult to do in the template
	 */
	_on_template_load()
	{
		let app = <Et2Select><unknown>this.dialog.eTemplate.widgetContainer.getDOMWidgetById("app");
		let group = <Et2Select><unknown>this.dialog.eTemplate.widgetContainer.getDOMWidgetById("group");
		let placeholder_list = <Et2Select><unknown>this.dialog.eTemplate.widgetContainer.getDOMWidgetById("placeholder_list");
		let preview = <Et2Description><unknown>this.dialog.eTemplate.widgetContainer.getDOMWidgetById("preview_placeholder");
		let entry = <Et2LinkEntry><unknown>this.dialog.eTemplate.widgetContainer.getDOMWidgetById("entry");

		placeholder_list.set_select_options(this._get_placeholders(app.get_value(), group.get_value()));

		// Bind some handlers
		app.onchange = (node, widget) =>
		{
			preview.set_value("");
			if(['user', 'filemanager'].indexOf(widget.get_value()) >= 0)
			{
				// These ones don't let you select an entry for preview (they don't work)
				entry.set_disabled(true);
				entry.set_value({app: 'user', id: '', query: ''});
			}
			else if(widget.get_value() == 'general')
			{
				// Don't change entry app, leave it
				entry.set_disabled(false);
			}
			else
			{
				// Load app translations
				this.egw().langRequireApp(this.egw().window, widget.get_value());
				entry.set_disabled(false);
				entry.set_value({app: widget.get_value(), id: '', query: ''});
			}
			let groups = this._get_group_options(widget.get_value());
			group.set_select_options(groups);
			group.set_value(groups[0].value);
			group.onchange();
		}
		group.onchange = (select_node, select_widget) =>
		{
			let options = this._get_placeholders(app.get_value(), group.get_value())
			placeholder_list.set_select_options(options);
			preview.set_value("");
			placeholder_list.updateComplete.then(() => placeholder_list.set_value(options[0].value));
		}
		placeholder_list.onchange = this._on_placeholder_select.bind(this);
		entry.onchange = this._on_placeholder_select.bind(this);
		(<Et2Button><unknown>this.dialog.eTemplate.widgetContainer.getDOMWidgetById("insert_placeholder")).onclick = () =>
		{
			this.options.insert_callback(this.dialog.eTemplate.widgetContainer.getDOMWidgetById("preview_placeholder").getDOMNode().textContent);
		};
		(<Et2Button><unknown>this.dialog.eTemplate.widgetContainer.getDOMWidgetById("insert_content")).onclick = () =>
		{
			this.options.insert_callback(this.dialog.eTemplate.widgetContainer.getDOMWidgetById("preview_content").getDOMNode().textContent);
		};

		app.set_value(app.get_value());
	}

	/**
	 * User has selected a placeholder
	 * Update the UI, and if they have an entry selected do the replacement and show that.
	 */
	_on_placeholder_select()
	{
		let app = <Et2LinkEntry><unknown>this.dialog.eTemplate.widgetContainer.getDOMWidgetById("app");
		let entry = <Et2LinkEntry><unknown>this.dialog.eTemplate.widgetContainer.getDOMWidgetById("entry");
		let placeholder_list = <Et2Select><unknown>this.dialog.eTemplate.widgetContainer.getDOMWidgetById("placeholder_list");
		let preview = <Et2Description><unknown>this.dialog.eTemplate.widgetContainer.getDOMWidgetById("preview_placeholder");
		let preview_content = <Et2Description><unknown>this.dialog.eTemplate.widgetContainer.getDOMWidgetById("preview_content");

		// Show the selected placeholder
		this.set_value(placeholder_list.get_value());
		preview.set_value(placeholder_list.get_value());
		preview.getDOMNode().parentNode.style.visibility = placeholder_list.get_value()?.trim() ? null : 'hidden';

		if(placeholder_list.get_value() && entry.get_value())
		{
			this._fill_preview(placeholder_list.get_value(), entry.get_value(), preview_content);
		}
		else
		{
			// No value, hide the row
			preview_content.getDOMNode().parentNode.style.visibility = 'hidden';
		}
	}

	/**
	 * Ask the server to merge the placeholder with the selected entry and show the result.
	 *
	 * The preview is the only thing that tells the user what they are about to insert, so it
	 * always says something: the merged text, or why there is none.  Silently leaving it empty
	 * (or hidden) is indistinguishable from the dialog being broken.
	 *
	 * @param _placeholder placeholder text to merge, eg. "{{n_fn}}"
	 * @param _entry entry to merge with, either the id or {app: ..., id: ...}
	 * @param _preview widget showing the merged result
	 * @param _onContent optional, called with the merged content ('' if there was none) for
	 *	subclasses that insert the merged text rather than the placeholder
	 */
	protected _fill_preview(_placeholder : string, _entry : any, _preview : Et2Description, _onContent? : (_content : string) => void)
	{
		const show = (_content) =>
		{
			_preview.set_value(_content);
			// Always visible - an empty preview with a reason in it is feedback, a hidden one is not
			_preview.getDOMNode().parentNode.style.visibility = null;
		};

		this.egw().json(
			'EGroupware\\Api\\Etemplate\\Widget\\Placeholder::ajax_fill_placeholders',
			[_placeholder, _entry],
			function(_content)
			{
				const merged = _content && ('' + _content).trim() ? _content : '';
				if(_onContent)
				{
					_onContent(merged);
				}
				show(merged || this.egw().lang('No data for the selected entry'));
			}.bind(this)
		).sendRequest(true, 'POST', (_err) =>
		{
			console.error("Could not merge placeholder", _placeholder, _entry, _err);
			if(_onContent)
			{
				_onContent('');
			}
			show(this.egw().lang('Error merging placeholder'));
		});
	}

	/**
	 * Get the list of placeholder groups under the selected application
	 * @param appname
	 * @returns {value:string, label:string}[]
	 */
	_get_group_options(appname : string)
	{
		let options = [];
		let placeholders = this.placeholders ? this.placeholders[appname] : null;
		if(!placeholders)
		{
			return options;
		}
		Object.keys(placeholders).map((key) =>
		{
			// @ts-ignore
			if(Object.keys(placeholders[key]).filter((key) => isNaN(key)).length > 0)
			{
				// Handle groups of groups
				if(typeof placeholders[key].label !== "undefined")
				{
					options.push({label:key, value: placeholders[key]});
				}
				else
				{
					let a = {label: key, value:[]};
					for(let sub of Object.keys(placeholders[key]))
					{
						if(!placeholders[key][sub])
						{
							continue;
						}
						a.value.push({
							value: key + '-' + sub,
							label: this.egw().lang(sub)
						});
					}
					options.push(a);
				}
			}
			else
			{
				options.push({
					value: key,
					label: this.egw().lang(key)
				});
			}
		});
		return options;
	}

	/**
	 * Get a list of placeholders under the given application + group
	 *
	 * @param appname
	 * @param group
	 * @returns {value:string, label:string}[]
	 */
	_get_placeholders(appname : string, group : string)
	{
		let _group = (group || "").split('-', 2);
		let ph = this.placeholders ? this.placeholders[appname] : undefined;
		for(let i = 0; typeof ph !== "undefined" && i < _group.length; i++)
		{
			ph = ph[_group[i]];
		}
		return ph || [];
	}

	/**
	 * Get the correct insert text call the insert callback with it
	 *
	 * @param dialog_values
	 */
	_do_insert_callback(dialog_values : Object)
	{
		this.options.insert_callback(this.get_value());
	}

	set_value(value)
	{
		this.value = value;
	}

	getValue()
	{
		return this.value;
	}
};
et2_register_widget(et2_placeholder_select, ["placeholder-select"]);

/**
 * Display a dialog to choose from a set list of placeholder snippets
 */
export class et2_placeholder_snippet_select extends et2_placeholder_select
{
	static readonly _attributes : any = {
		dialog_title: {
			"default": "Insert address"
		}
	};
	static placeholders = {
		"addressbook": {
			"addresses": {
				"{{org_name}}\n{{n_fn}}\n{{adr_one_street}}{{NELF adr_one_street2}}\n{{adr_one_formatted}}": "Business address",
				"{{n_fn}}\n{{adr_two_street}}{{NELF adr_two_street2}}\n{{adr_two_formatted}}": "Home address",
				"{{n_fn}}\n{{email}}\n{{tel_work}}": "Name, email, phone"
			}
		}
	};

	button : JQuery;
	submit_callback : any;
	dialog : Et2Dialog;
	protected value : any;

	protected TEMPLATE = '/api/templates/default/placeholder_snippet.xet?1';

	/**
	 * Constructor
	 *
	 * @param _parent
	 * @param _attrs
	 * @memberOf et2_vfsSelect
	 */
	constructor(_parent, _attrs? : WidgetConfig, _child? : object)
	{
		// Call the inherited constructor
		super(_parent, _attrs, ClassWithAttributes.extendAttributes(et2_placeholder_select._attributes, _child || {}));

		// Load app translations
		this.egw().langRequireApp(this.egw().window, "addressbook");
	}

	/**
	 * Post-load of the dialog
	 * Bind internal events, set some things that are difficult to do in the template
	 */
	_on_template_load()
	{
		let app = <Et2Select><unknown>this.dialog.eTemplate.widgetContainer.getDOMWidgetById("app");
		let placeholder_list = <Et2Select><unknown>this.dialog.eTemplate.widgetContainer.getDOMWidgetById("placeholder_list");
		let preview = <Et2Description><unknown>this.dialog.eTemplate.widgetContainer.getDOMWidgetById("preview_content");
		let entry = <Et2LinkEntry><unknown>this.dialog.eTemplate.widgetContainer.getDOMWidgetById("entry");

		const options = this._get_placeholders("addressbook", "addresses");
		placeholder_list.set_select_options(options);

		// Start on the first address format, the way the merge placeholder dialog does.  Picking the
		// contact first is the natural order, and with nothing selected here that shows no preview
		// at all - which reads as the dialog not working rather than as a missing choice.
		if(options.length)
		{
			placeholder_list.updateComplete.then(() =>
			{
				placeholder_list.set_value(options[0].value);
				this._on_placeholder_select();
			});
		}

		// Further setup / styling that can't be done in etemplate
		app.setAttribute("readonly", true);

		// Bind some handlers
		app.onchange = (node, widget) =>
		{
			entry.set_value({app: widget.get_value()});
			placeholder_list.set_select_options(this._get_placeholders(app.value, "addresses"));
		}
		placeholder_list.onchange = this._on_placeholder_select.bind(this);
		entry.onchange = this._on_placeholder_select.bind(this);

		app.set_value(app.value);
		this._on_placeholder_select();
	}

	/**
	 * User has selected a placeholder
	 * Update the UI, and if they have an entry selected do the replacement and show that.
	 */
	_on_placeholder_select()
	{
		let app = <Et2Select><unknown>this.dialog.eTemplate.widgetContainer.getDOMWidgetById("app");
		let entry = <Et2LinkEntry><unknown>this.dialog.eTemplate.widgetContainer.getDOMWidgetById("entry");
		let placeholder_list = <Et2Select><unknown>this.dialog.eTemplate.widgetContainer.getDOMWidgetById("placeholder_list");
		let preview_content = <Et2Description><unknown>this.dialog.eTemplate.widgetContainer.getDOMWidgetById("preview_content");
		// The snippets are keyed by application, and only addressbook has any.  Fall back to it
		// rather than indexing with whatever the (readonly) app selectbox happens to hold, which
		// would throw and leave the dialog looking like it just does not work.
		const snippets = this._get_snippets(<string>app?.value);
		const placeholder = snippets ? Object.keys(snippets)[<string>placeholder_list.value] : "";

		this.set_value("");
		if(placeholder && entry.get_value())
		{
			this._fill_preview(placeholder, {app: "addressbook", id: entry.get_value()}, preview_content,
				(_content) => this.set_value(_content));
		}
		else
		{
			// No value, hide the row
			preview_content.getDOMNode().parentNode.style.visibility = 'hidden';
		}
		if(!entry.get_value())
		{
			entry._searchNode.focus();
		}
	}

	/**
	 * The address snippets for the given application
	 *
	 * Only addressbook has any, and the application selectbox is readonly, so anything else falls
	 * back to addressbook rather than leaving the dialog with nothing to offer.
	 *
	 * @param appname
	 * @returns the snippets keyed by placeholder text, or null if there are none at all
	 */
	protected _get_snippets(appname : string) : Object | null
	{
		return this.placeholders?.[appname]?.["addresses"] ??
		       this.placeholders?.["addressbook"]?.["addresses"] ?? null;
	}

	/**
	 * Get the list of placeholder groups under the selected application
	 * @param appname
	 * @returns {value:string, label:string}[]
	 */
	_get_group_options(appname : string)
	{
		let options = [];
		Object.keys(this.placeholders?.[appname] ?? {}).map((key) =>
		{
			options.push(
				{
					value: key,
					label: this.egw().lang(key)
				});
		});
		return options;
	}

	/**
	 * Get a list of placeholders under the given application + group
	 *
	 * @param appname
	 * @param group
	 * @returns {value:string, label:string}[]
	 */
	_get_placeholders(appname : string, group : string)
	{
		let options = [];
		const snippets = this.placeholders?.[appname]?.[group];
		if(!snippets)
		{
			return options;
		}
		Object.keys(snippets).map((key, index) =>
		{
			options.push(
				{
					value: index,
					label: this.egw().lang(snippets[key])
				});
		});
		return options;
	}

	/**
	 * Get the correct insert text call the insert callback with it
	 *
	 * @param dialog_values
	 */
	_do_insert_callback(dialog_values : Object)
	{
		this.options.insert_callback(this.get_value());
	}

	set_value(value)
	{
		this.value = value;
	}

	getValue()
	{
		return this.value;
	}
};
et2_register_widget(et2_placeholder_snippet_select, ["placeholder-snippet"]);
