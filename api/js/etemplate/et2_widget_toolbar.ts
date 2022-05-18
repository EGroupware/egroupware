/**
 * EGroupware eTemplate2 - JS toolbar object
 *
 * @license http://opensource.org/licenses/gpl-license.php GPL - GNU General Public License
 * @package etemplate
 * @subpackage api
 * @link https://www.egroupware.org
 * @author Nathan Gray
 * @copyright Nathan Gray 2013
 */

/*egw:uses
	/vendor/bower-asset/jquery/dist/jquery.js;
	/vendor/bower-asset/jquery-ui/jquery-ui.js;
	et2_DOMWidget;
*/

import {et2_DOMWidget} from "./et2_core_DOMWidget";
import {et2_createWidget, et2_register_widget, WidgetConfig} from "./et2_core_widget";
import {ClassWithAttributes} from "./et2_core_inheritance";
import {egw_getObjectManager, egwActionObject, egwActionObjectManager} from '../egw_action/egw_action.js';
import {et2_checkbox} from "./et2_widget_checkbox";
import {et2_IInput} from "./et2_core_interfaces";
import {egw} from "../jsapi/egw_global";
import {egwIsMobile} from "../egw_action/egw_action_common.js";
import {Et2Dialog} from "./Et2Dialog/Et2Dialog";
import {Et2DropdownButton} from "./Et2DropdownButton/Et2DropdownButton";
import {loadWebComponent} from "./Et2Widget/Et2Widget";

/**
 * This toolbar gets its contents from its actions
 *
 * @augments et2_valueWidget
 */
export class et2_toolbar extends et2_DOMWidget implements et2_IInput
{
	static readonly _attributes : any = {
		"view_range": {
			"name": "View range",
			"type": "string",
			"default": "5",
			"description": "Define minimum action view range to show actions by both icons and caption"
		},
		"flat_list": {
			"name": "Flat list",
			"type": "boolean",
			"default": true,
			"description": "Define whether the actions with children should be shown as dropdown or flat list"
		},
		"list_header": {
			"name": "list header style",
			"type": "string",
			"default": "more",
			"description": "Define a style for list header (more ...), which can get short 3dots with no caption or bigger button with caption more ..."
		},
		"preference_id": {
			"name": "Preference id",
			"type": "string",
			"default": false,
			"description": "Define a custom preference id for saving the toolbar preferences." +
				           "This is useful when you have the same toolbar and you use it in a pop up but also in a tab, which have different dom ids" +
				           "When not set it defaults to the dom id of the form."
		},
		"preference_app": {
			"name": "Preference application",
			"type": "string",
			"default": false,
			"description": 	"Define a custom preference application for saving the toolbar preferences." +
							"This is useful when you have the same toolbar and you use it in a pop up but also in a tab, wich have different application names" +
							"When not set it defaults to the result of this.egw().app_name();"
		}
	};

	/**
	 * Default buttons, so there is something for the widget browser / editor to show
	 */
	static default_toolbar : any = {
		view: {caption:'View', icons: {primary: 'ui-icon-check'}, group:1, toolbarDefault:true},
		edit: {caption:'Edit', group:1, toolbarDefault:true},
		save: {caption:'Save', group:2, toolbarDefault:true}
	};

	/**
	 * id of last action executed / value of toolbar if submitted
	 */
	value : string = null;

	/**
	 * actionbox is a div for stored actions
	 */
	private readonly actionbox : JQuery = null;

	/**
	 * actionlist is a div for active actions
 	 */
	private readonly actionlist : JQuery = null;
	div : JQuery = null;
	private countActions : number = 0;
	private dropdowns : object = {};
	private preference : object = {};
	menu : any = null;
	private _objectManager : egwActionObject = null;

	constructor(_parent, _attrs? : WidgetConfig, _child? : object)
	{
		// Call the inherited constructor
		super(_parent, _attrs, ClassWithAttributes.extendAttributes(et2_toolbar._attributes, _child || {}));

		this.div = jQuery(document.createElement('div'))
			.addClass('et2_toolbar ui-widget-header ui-corner-all');

		// Set proper id and dom_id for the widget
		this.set_id(this.id);

		if(!this.options.preference_id){
			this.options.preference_id = this.dom_id;
		}

		if(!this.options.preference_app){
			this.options.preference_app = this.egw().app_name();
		}

		this.actionbox = jQuery(document.createElement('details'))
			.addClass("et2_toolbar_more")
			.attr('id',this.id +'-'+ 'actionbox');

		this.actionlist = jQuery(document.createElement('div'))
			.addClass("et2_toolbar_actionlist")
			.attr('id',this.id +'-'+ 'actionlist');

		this.countActions = 0;
		this.dropdowns = {};
		this.preference = {};

		this._build_menu(et2_toolbar.default_toolbar, true);
	}

	destroy()
	{
		// Destroy widget
		if(this.div && this.div.data('ui-menu')) this.menu.menu("destroy");

		// Null children

		// Remove
		this.div.empty().remove();
		this.actionbox.empty().remove();
		this.actionlist.empty().remove();
	}

	/**
	 * Fix function in order to fix toolbar preferences with the new preference structure
	 * @param {action object} _action
	 * @todo ** SEE IMPORTANT TODO **
	 */
	private _fix_preference(_action)
	{

		// ** IMPORTANT TODO: This switch case should be removed for new release **
		// This is an ugly hack but we need to add this switch becuase to update and fix
		// current users toolbar preferences with the new structure which is:
		// - All actions should be stored in preference
		// - Actions inside menu set as true
		// - Actions outside menu set as false
		// - if an action gets added to toolbar it would be undefined in
		//  the preference which we need to consider to add it to the preference
		//  according to its toolbarDefault option.
		if (this.dom_id === 'mail-display_displayToolbar' || this.dom_id === 'mail-index_toolbar')
		{
			switch (_action.id)
			{
				// Actions newly added to mail index and display toolbar
				case 'read':
				case 'label1':
				case 'label2':
				case 'label3':
				case 'label4':
				case 'label5':
					this.set_prefered(_action.id, !_action.toolbarDefault);
					break;
				default:
					// Fix structure and add the actions not the preference
					// into the preference with value false, as they're already
					// outside of the menu.
					this.set_prefered(_action.id, false);
			}
		}
		else
		{
			// ** IMPORTANT TODO: This line needs to stay and be fixed with !toolbarDefault after the if condition
			// has been removed.
			this.set_prefered(_action.id, false/*!toolbarDefault*/);
		}
	}

	/**
	 * Count number of actions including their children
	 * @param {object} actions
	 * @return {number} return total number of actions
	 */
	private _countActions(actions)
	{
		let totalCount = 0;
		let childCounter = function (action, count)
		{
			let children = action.children || 0,
				returnCounter = count || 0;
			if (children)
			{
				returnCounter -= 1;
				for (let nChild in children)
				{
					returnCounter += 1;
					returnCounter =	childCounter (children[nChild], returnCounter);
				}
			}
			else
			{
				returnCounter =  count;
			}
			return returnCounter;
		};
		for (let nAction in actions)
		{
			if (this.options.flat_list)
			{
				totalCount += childCounter(actions[nAction] ,1);
			}
			else
			{
				totalCount ++;
			}
		}
		return totalCount;
	}

	/**
	 * Go through actions and build buttons for the toolbar
	 *
	 * @param {Object} actions egw-actions to build menu from
	 * @param {boolean} isDefault setting isDefault with true will
	 *  avoid actions get into the preferences, for instandce, first
	 *  time toolbar_default actions initialization.
	 */
	private _build_menu(actions : object, isDefault? : boolean)
	{
		// Clear existing
		this.div.empty();
		this.actionbox.empty();
		this.actionlist.empty();
		let admin_setting = this.options.is_admin ? '<span class="toolbar-admin-pref" title="'+egw.lang('Admin settings')+' ..."></span>': '';
		const list_header = this.options.list_header == 'more'?true:false;
		this.actionbox.append('<summary class="ui-toolbar-menulistHeader'+(!list_header?' list_header-short':' ')+'">'+(list_header?egw.lang('more')+' ...':'')+admin_setting+'</summary>');
		this.actionbox.append('<div id="' + this.id + '-menulist' +'" class="ui-toolbar-menulist" ></div>');
		let that = this;
		if (this.options.is_admin)
		{
			this.actionbox.find('.toolbar-admin-pref').click(function(e){
				egw.json('EGroupware\\Api\\Etemplate\\Widget\\Toolbar::ajax_get_default_prefs', [that.options.preference_app, that.options.preference_id], function(_prefs){
					let prefs = [];
					for (let p in _prefs)
					{
						if (_prefs[p] === false) prefs.push(p);
					}
					that._admin_settings_dialog.call(that, actions, prefs);
				}).sendRequest(true);
				return false;
			});
			this.actionbox.addClass('admin');
		}

		let pref = (!egwIsMobile())? egw.preference(this.options.preference_id, this.options.preference_app): undefined;
		if (pref && !jQuery.isArray(pref)) this.preference = pref;

		//Set the default actions for the first time
		if (typeof pref === 'undefined' && !isDefault)
		{
			for (var name in actions)
			{
				if ((typeof actions[name].children === 'undefined' || !this.options.flat_list) && actions[name].id)
				{
					this.set_prefered(actions[name].id,!actions[name].toolbarDefault);
				}
			}
		}
		else if(!isDefault)
		{
			for (var name in actions)
			{
				// Check if the action is not in the preference, means it's an new added action
				// therefore it needs to be added to the preference with taking its toolbarDefault
				// option into account.
				if ((typeof actions[name].children === 'undefined' || !this.options.flat_list)
					&& typeof pref[name] === 'undefined')
				{
					this._fix_preference(actions[name]);
				}
			}
		}


		let menuLen = 0;
		for (let key in this.preference)
		{
			if (this.preference[key]) menuLen++;
		}

		this.countActions = this._countActions(actions) - menuLen;

		let last_group = null;
		let last_group_id = null;
		for(let name in actions)
		{
			let action = actions[name];
			if (typeof action == 'string') action = {id: name, caption: action};
			if(typeof action.group == 'undefined')
			{
				action.group = 'default';
			}

			// Add in divider
			if(last_group_id != action.group)
			{
				last_group = jQuery('[data-group="' + action.group + '"]',this.actionlist);
				if(last_group.length == 0)
				{
					jQuery('<span data-group="'+action.group+'">').appendTo(this.actionlist);
				}
				last_group_id = action.group;
			}

			// Make sure there's something to display
			if(!action.caption && !action.icon && !action.iconUrl) continue;

			if(action.children)
			{
				let children = {};
				let add_children = function(root, children) {
					for(let id in root.children)
					{
						let info = {
							id: id || root.children[id].id,
							value: id || root.children[id].id,
							label: root.children[id].caption
						};
						let childaction = {};
						if(root.children[id].iconUrl)
						{
							info['icon'] = root.children[id].iconUrl;
						}
						if(root.children[id].children)
						{
							add_children(root.children[id], info);
						}
						children[id] = info;

						if (that.options.flat_list)
						{
							childaction = root.children[id];
							if (typeof pref === 'undefined' && !isDefault)
							{
								if (!childaction['toolbarDefault'])
								{
									that.set_prefered(childaction['id'],true);
								}
								else
								{
									that.set_prefered(childaction['id'],false);
								}
							}
							else if(!isDefault)
							{
								if (typeof pref[childaction['id']] === 'undefined')
								{
									that._fix_preference(childaction);
								}
							}
							if (typeof root.children[id].group !== 'undefined' &&
								typeof root.group !== 'undefined')
							{
								childaction['group'] = root.group;
							}
							that._make_button(childaction);
						}
					}
				};
				add_children(action, children);
				if (this.options.flat_list && children)
				{
					continue;
				}

				let dropdown = <Et2DropdownButton><unknown>loadWebComponent("et2-dropdown-button", {
					id: this.id + "-" + action.id,
					label: action.caption,
					class: this.preference[action.id] ? 'et2_toolbar-dropdown et2_toolbar-dropdown-menulist' : 'et2_toolbar-dropdown',
					onchange: function(ev)
					{
						let action = that._actionManager.getActionById(dropdown.value);
						dropdown.set_label(action.caption);
						if(action)
						{
							this.value = action.id;
							action.execute([]);
						}
						//console.debug(selected, this, action);
					}.bind(action),
					image: action.iconUrl || ''
				}, this);

				dropdown.select_options = Object.values(children);

				//Set default selected action
				if (typeof action.children !='undefined')
				{
					for (let child in action.children)
					{
						if(action.children[child].default)
						{
							dropdown.label = action.children[child].caption;
						}
					}
				}

				dropdown.onclick = function(selected, dropdown)
				{
					let action = that._actionManager.getActionById(this.getValue());
					if(action)
					{
						this.value = action.id;
						action.execute([]);
					}
					//console.debug(selected, this, action);
				}.bind(dropdown);
				jQuery(dropdown.getDOMNode()).appendTo(this.preference[action.id] ? this.actionbox.children()[1] : jQuery('[data-group=' + action.group + ']', this.actionlist));
			}
			else
			{
				this._make_button(action);
			}
		}

		// ************** Drag and Drop feature for toolbar *****
		this.actionlist.find('span[data-group]').sort( function (lg,g){
			return +lg.getAttribute('data-group') - +g.getAttribute('data-group');
		}).appendTo(this.actionlist);

		this.actionlist.appendTo(this.div);
		this.actionbox.appendTo(this.div);

		let toolbar = this.actionlist.find('span[data-group]').children(),
			toolbox = this.actionbox,
			menulist = jQuery(this.actionbox.children()[1]);

		toolbar.draggable({
			cancel:'',
			zIndex: 1000,
			delay: 500,
			//revert:"invalid",
			containment: "document",
			cursor: "move",
			helper: "clone",
			appendTo:'body',
			stop: function(event, ui){
				that._build_menu(actions);
			}
		});
		menulist.children().draggable({
			cancel:'',
			containment:"document",
			helper:"clone",
			appendTo:'body',
			zIndex: 1000,
			cursor:"move",
			start: function()
			{
				jQuery(that.actionlist).addClass('et2_toolbarDropArea');
			},
			stop: function()
			{
				jQuery(that.actionlist).removeClass('et2_toolbarDropArea');
			}
		});
		toolbox.children().droppable({
			accept:toolbar,
			drop:function (event, ui) {
				that.set_prefered(ui.draggable[0].id.replace(that.id + '-', ''), true);
				ui.draggable.appendTo(menulist);
				if (that.actionlist.find(".ui-draggable").length == 0)
				{
					that.preference = {};
					egw.set_preference(that.options.preference_app,that.options.preference_id,that.preference);
				}
			},
			tolerance:"touch"
		});

		toolbox.on('toggle', (e)=>{
			const details = <HTMLDetailsElement>e.target;
			if (details.open)
			{
				jQuery('html').on('click.outsideOfMenu', function(e)
				{
					// Clicking on dropdown button should not close the details, we'd like to see the dropdown
					if(e.target instanceof Et2DropdownButton)
					{
						return;
					}
					if(e.target != details && e.target != details.firstChild)
					{
						details.open = false;
					}

					jQuery('html').unbind('click.outsideOfMenu');
				});
			}
		});

		this.actionlist.droppable({
			tolerance:"pointer",
			drop:function (event,ui) {
				that.set_prefered(ui.draggable[0].id.replace(that.id + '-', ''), false);
				ui.draggable.appendTo(that.actionlist);
				that._build_menu(actions);
			}
		});
	}

	/**
	 * Add/Or remove an action from prefence
	 *
	 * @param {string} _action name of the action which needs to be stored in pereference
	 * @param {boolean} _state if set to true action will be set to actionbox, false will set it to actionlist
	 *
	 */
	set_prefered(_action,_state)
	{
		this.preference[_action] = _state;
		if (egwIsMobile()) return;
		egw.set_preference(this.options.preference_app,this.options.preference_id,this.preference);
	}

	/**
	 * Make a button based on the given action
	 *
	 * @param {Object} action action object with attributes icon, caption, ...
	 */
	_make_button(action)
	{
		let button_options = {
		};
		let button = jQuery(document.createElement('button'))
			.addClass("et2_button et2_button_text et2_button_with_image")
			.attr('id', this.id+'-'+action.id)
			.attr('type', 'button')
			.appendTo(this.preference[action.id]?this.actionbox.children()[1]:jQuery('[data-group='+action.group+']',this.actionlist));

		if (action && action.checkbox)
		{
			if (action.data.toggle_on || action.data.toggle_off)
			{
				let toggle = <et2_checkbox>et2_createWidget('checkbox', {
					id: this.id+'-'+action.id,
					toggle_on: action.data.toggle_on,
					toggle_off: action.data.toggle_off
				}, this);
				toggle.doLoadingFinished();
				toggle.set_value(action.checked);
				action.data.widget = toggle;
				let toggle_div = toggle.toggle;
				toggle_div.appendTo(button.parent())
					.attr('id', this.id+'-'+action.id);
				button.remove();
				button = toggle_div;

			}
			else
			{
				if (this.checkbox(action.id)) button.addClass('toolbar_toggled'+ (typeof action.toggledClass != 'undefined'?" "+action.toggledClass:''));
			}
		}
		this.egw().tooltipBind(button, action.hint ? action.hint : action.caption) + (action.shortcut ? ' ('+action.shortcut.caption+')' : '');

		if ( action.iconUrl)
		{
			button.attr('style','background-image:url(' + action.iconUrl + ')');
		}
		if (action.caption)
		{
			if ((this.countActions <= parseInt(this.options.view_range) ||
				this.preference[action.id] || !action.iconUrl)	&&
				typeof button[0] !== 'undefined' &&
				!(action.checkbox && action.data && (action.data.toggle_on || action.data.toggle_off))) // no caption for slideswitch checkboxes
			{
				button.addClass(action.iconUrl?'et2_toolbar_hasCaption':'et2_toolbar_onlyCaption');
				button[0].textContent = action.caption;
			}
		}
		if(action.icon)
		{
			button_options['icon'] = action.icon;
		}
		if (!jQuery.isEmptyObject(button_options))
		{
			button.button(button_options);
		}
		let self = this;
		// Set up the click action
		let click = function(e)
		{
			let action = this._actionManager.getActionById(e.data);
			if(action)
			{
				if (action.checkbox)
				{
					self.checkbox(action.id, !action.checked);
				}
				this.value = action.id;
				action.data.event = e;
				action.execute([]);
			}
		};

		button.click(action.id, jQuery.proxy(click, this));
	}

	/**
	 * Link the actions to the DOM nodes / widget bits.
	 *
	 * @param {Object} actions egw-actions to build menu from
	 */
	_link_actions(actions)
	{
		this._build_menu(actions);

		let self = this;
		let gom = egw_getObjectManager(this.egw().app_name(),true,1);
		if(this._objectManager == null)
		{
			this._objectManager = gom.addObject(
				new egwActionObjectManager(this.id, this._actionManager));

			this._objectManager.handleKeyPress = function(_keyCode, _shift, _ctrl, _alt) {
				for(let i = 0; i < self._actionManager.children.length; i++)
				{
					let action = self._actionManager.children[i];
					if(typeof action.shortcut === 'object' &&
						action.shortcut &&
						_keyCode == action.shortcut.keyCode &&
						_ctrl == action.shortcut.ctrl &&
						_alt == action.shortcut.alt &&
						_shift == action.shortcut.shift
					)
					{
						self.value = action.id;
						action.execute([]);
						return true;
					}
				}
				return egwActionObject.prototype.handleKeyPress.call(this, _keyCode,_shift,_ctrl,_alt);
			};
			this._objectManager.parent.updateFocusedChild(this._objectManager, true);
		}
	}

	/**
	 * Set/Get the checkbox toolbar action
	 *
	 * @param {string} _action action name of the selected toolbar
	 * @param {boolean} _value value that needs to be set for the action true|false
	 *	- if no value means checkbox value returns the current value
	 *
	 * @returns {boolean} returns boolean result of get checkbox value
	 * or returns undefined as Set result or failure
	 */
	checkbox(_action, _value?)
	{
		if (!_action || typeof this._actionManager == 'undefined') return undefined;
		let action_event = this._actionManager.getActionById(_action);

		if (action_event && typeof _value !='undefined')
		{
			action_event.set_checked(_value);
			var btn = jQuery('#'+this.id+'-'+_action);
			if(action_event.data && action_event.data.widget)
			{
				action_event.data.widget.set_value(_value);
			}
			else if (btn.length > 0)
			{
				btn.toggleClass('toolbar_toggled'+ (typeof action_event.data.toggledClass != 'undefined'?" "+action_event.data.toggledClass:''), _value);
			}
		}
		else if (action_event)
		{
			return action_event.checked;
		}
		else
		{
			return undefined;
		}
	}

	getDOMNode()
	{
		return this.div[0];
	}

	/**
	 * getValue has to return the value of the input widget
	 */
	getValue()
	{
		return this.value;
	}

	/**
	 * Is dirty returns true if the value of the widget has changed since it
	 * was loaded.  We don't consider toolbars as dirtyable
	 */
	isDirty()
	{
		return false;
	}

	/**
	 * Causes the dirty flag to be reseted.
	 */
	resetDirty()
	{
		this.value = null;
	}

	/**
	 * Checks the data to see if it is valid, as far as the client side can tell.
	 * Return true if it's not possible to tell on the client side, because the server
	 * will have the chance to validate also.
	 *
	 * The messages array is to be populated with everything wrong with the data,
	 * so don't stop checking after the first problem unless it really makes sense
	 * to ignore other problems.
	 *
	 * @param {String[]} messages List of messages explaining the failure(s).
	 *	messages should be fairly short, and already translated.
	 *
	 * @return {boolean} True if the value is valid (enough), false to fail
	 */
	isValid(messages)
	{
		return true;
	}

	/**
	 * Attach the container node of the widget to DOM-Tree
	 * @returns {Boolean}
	 */
	doLoadingFinished()
	{
		super.doLoadingFinished();
		return false;
	}

	/**
	 * Builds dialog for possible admin settings (e.g. default actions pref)
	 *
	 * @param {type} _actions
	 * @param {object} _default_prefs
	 */
	private _admin_settings_dialog(_actions, _default_prefs)
	{
		let buttons = [
			{label: egw.lang("Save"), id: "save"},
			{label: egw.lang("Close"), id: "close"}
		];
		let self = this;
		let sel_options = {actions:[]};
		let content = {actions:[], reset:false};
		for (let key in _actions)
		{
			if (_actions[key]['children'] && this.options.flat_list)
			{
				for (let child in _actions[key]['children'])
				{
					sel_options.actions.push({
						id:child,
						value: child,
						label: _actions[key]['children'][child]['caption'],
						app: self.options.preference_app,
						icon: _actions[key]['children'][child]['iconUrl']
					});
				}
			}
			else
			{
				sel_options.actions.push({
					id: key,
					value: key,
					label: _actions[key]['caption'],
					app: self.options.preference_app,
					icon: _actions[key]['iconUrl']
				});
			}
			if((!_default_prefs || _default_prefs.length == 0) && _actions[key]['toolbarDefault'])
			{
				content.actions.push(key);
			}
		}
		if(_default_prefs && _default_prefs.length > 0)
		{
			content.actions = _default_prefs;
		}
		let dialog = new Et2Dialog(this.egw());
		dialog.transformAttributes({
				callback: function(_button_id, _value)
				{
					if(_button_id == 'save' && _value)
					{
						if(_value.actions)
						{
							let pref = jQuery.extend({}, self.preference);
							for(let i in pref)
							{
								pref[i] = true;
								if(_value.actions.includes(i))
								{
									pref[i] = false;
								}
							}
							_value.actions = pref;
						}
						egw.json('EGroupware\\Api\\Etemplate\\Widget\\Toolbar::ajax_setAdminSettings',
							[_value, self.options.preference_id, self.options.preference_app], function(_result)
							{
								egw.message(_result);
							}).sendRequest(true);
					}
				},
				title: egw.lang('admin settings for %1', this.options.preference_id),
				buttons: buttons,
				minWidth: 600,
				minHeight: 300,
				value: {content: content, sel_options: sel_options},
				template: egw.webserverUrl + '/api/templates/default/toolbarAdminSettings.xet?1',
				resizable: false
			}
		);
		document.body.appendChild(dialog);
	}
}
et2_register_widget(et2_toolbar, ["toolbar"]);

