/**
 * EGroupware eTemplate2 - JS toolbar object
 *
 * @license http://opensource.org/licenses/gpl-license.php GPL - GNU General Public License
 * @package etemplate
 * @subpackage api
 * @link http://www.egroupware.org
 * @author Nathan Gray
 * @copyright Nathan Gray 2013
 * @version $Id$
 */

/*egw:uses
	jquery.jquery;
	jquery.jquery-ui;
	et2_DOMWidget;
*/

/**
 * This toolbar gets its contents from its actions
 *
 * @augments et2_valueWidget
 */
var et2_toolbar = (function(){ "use strict"; return et2_DOMWidget.extend([et2_IInput],
{
	attributes: {
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
		}
	},

	/**
	 * Default buttons, so there is something for the widget browser / editor to show
	 */
	default_toolbar: {
		view: {caption:'View', icons: {primary: 'ui-icon-check'}, group:1, toolbarDefault:true},
		edit: {caption:'Edit', group:1, toolbarDefault:true},
		save: {caption:'Save', group:2, toolbarDefault:true}
	},

	/**
	 * id of last action executed / value of toolbar if submitted
	 */
	value: null,

	/**
	 * Constructor
	 *
	 * @memberOf et2_dropdown_button
	 */
	init: function() {
		this._super.apply(this, arguments);
		this.div = $j(document.createElement('div'))
			.addClass('et2_toolbar ui-widget-header ui-corner-all');

		// Set proper id and dom_id for the widget
		this.set_id(this.id);

		//actionbox is the div for stored actions
		this.actionbox = $j(document.createElement('div'))
				.addClass("et2_toolbar_more")
				.attr('id',this.id +'-'+ 'actionbox');
		//actionlist is div for active actions
		this.actionlist = $j(document.createElement('div'))
				.addClass("et2_toolbar_actionlist")
				.attr('id',this.id +'-'+ 'actionlist');

		this.countActions = 0;
		this.dropdowns = {};
		this.preference = {};

		this._build_menu(this.default_toolbar, true);
	},

	destroy: function() {
		// Destroy widget
		if(this.div && this.div.data('ui-menu')) this.menu.menu("destroy");

		// Null children

		// Remove
		this.div.empty().remove();
		this.actionbox.empty().remove();
		this.actionlist.empty().remove();
	},

	/**
	 * Fix function in order to fix toolbar preferences with the new preference structure
	 * @param {action object} _action
	 * @todo ** SEE IMPORTANT TODO **
	 */
	_fix_preference: function (_action)
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
	},

	/**
	 * Go through actions and build buttons for the toolbar
	 *
	 * @param {Object} actions egw-actions to build menu from
	 * @param {boolean} isDefault setting isDefault with true will
	 *  avoid actions get into the preferences, for instandce, first
	 *  time toolbar_default actions initialization.
	 */
	_build_menu: function(actions, isDefault)
	{
		// Clear existing
		this.div.empty();
		this.actionbox.empty();
		this.actionlist.empty();
		this.actionbox.append('<h class="ui-toolbar-menulistHeader">'+egw.lang('more')+' ...'+'</h>');
		this.actionbox.append('<div id="' + this.id + '-menulist' +'" class="ui-toolbar-menulist" ></div>');
		var that = this;

		var pref = (!egwIsMobile())? egw.preference(this.dom_id,this.egw().getAppName()): undefined;
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

		//Count number of actions including their children
		var countActions = function (actions)
		{
			var totalCount = 0;
			var childCounter = function (action, count)
			{
				var children = action.children || 0,
				returnCounter = count || 0;
				if (children)
				{
					returnCounter -= 1;
					for (var nChild in children)
						returnCounter += 1;
						returnCounter =	childCounter (children[nChild], returnCounter);
				}
				else
				{
					returnCounter =  count;
				}
				return returnCounter;
			};
			for (var nAction in actions)
			{
				if (that.flat_list)
				{
					totalCount += childCounter(actions[nAction],1);
				}
				else
				{
					totalCount ++;
				}
			}
			return totalCount;
		};
		var menuLen = 0;
		for (var key in this.preference)
		{
			if (this.preference[key]) menuLen++;
		}

		this.countActions = countActions(actions) - menuLen;

		var last_group = false;
		var last_group_id = false;
		for(var name in actions)
		{
			var action = actions[name];
			if (typeof action == 'string') action = {id: name, caption: action};

			// Add in divider
			if(last_group_id != action.group)
			{
				last_group = $j('[data-group="' + action.group + '"]',this.actionlist);
				if(last_group.length == 0)
				{
						$j('<span data-group="'+action.group+'">').appendTo(this.actionlist);
					}
				last_group_id = action.group;
			}

			// Make sure there's something to display
			if(!action.caption && !action.icon && !action.iconUrl) continue;

			if(action.children)
			{
				var children = {};
				var add_children = function(root, children) {
					for(var id in root.children)
					{
						var info = {
							id: id || root.children[id].id,
							label: root.children[id].caption
						};
						var childaction = {};
						if(root.children[id].iconUrl)
						{
							info.icon = root.children[id].iconUrl;
						}
						if(root.children[id].children)
						{
							add_children(root.children[id], info);
						}
						children[id] = info;

						if (that.flat_list)
						{
							childaction = root.children[id];
							if (typeof pref === 'undefined' && !isDefault)
							{
								if (!childaction.toolbarDefault)
								{
									that.set_prefered(childaction.id,true);
								}
								else
								{
									that.set_prefered(childaction.id,false);
								}
							}
							else if(!isDefault)
							{
								if (typeof pref[childaction.id] === 'undefined')
								{
									that._fix_preference(childaction);
								}
							}
							if (typeof root.children[id].group !== 'undefined' &&
									typeof root.group !== 'undefined')
							{
								childaction.group = root.group;
							}
							that._make_button (childaction);
						}
					}
				};
				add_children(action, children);
				if (this.flat_list && children)
				{
					continue;
				}

				var dropdown = et2_createWidget("dropdown_button", {
					id: action.id
				},this);

				dropdown.set_select_options(children);
				dropdown.set_label (action.caption);
				//Set default selected action
				if (typeof action.children !='undefined')
				{
					for (var child in action.children)
					{
						if(action.children[child].default)
						{
							dropdown.set_label(action.children[child].caption);
						}
					}
				}
				dropdown.set_image (action.iconUrl||'');
				dropdown.onchange = jQuery.proxy(function(selected, dropdown)
				{
					var action = that._actionManager.getActionById(selected.attr('data-id'));
					dropdown.set_label(action.caption);
					if(action)
					{
						this.value = action.id;
						action.execute([]);
					}
					//console.debug(selected, this, action);
				},action);
				dropdown.onclick = jQuery.proxy(function(selected, dropdown)
				{
					var action = that._actionManager.getActionById(this.getValue());
					if(action)
					{
						this.value = action.id;
						action.execute([]);
					}
					//console.debug(selected, this, action);
				},dropdown);
				$j(dropdown.getDOMNode())
						.attr('id',this.id + '-' + dropdown.id)
						.addClass(this.preference[action.id]?'et2_toolbar-dropdown et2_toolbar-dropdown-menulist':'et2_toolbar-dropdown')
						.appendTo(this.preference[action.id]?this.actionbox.children()[1]:$j('[data-group='+action.group+']',this.actionlist));
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

		var toolbar = this.actionlist.find('span[data-group]').children(),
			toolbox = this.actionbox,
			menulist = jQuery(this.actionbox.children()[1]);

		toolbar.draggable({
			cancel:true,
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
			cancel:true,
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
					that.set_prefered(ui.draggable.attr('id').replace(that.id+'-',''),true);
					ui.draggable.appendTo(menulist);
					if (that.actionlist.find(".ui-draggable").length == 0)
					{
						that.preference = {};
						egw.set_preference(that.egw().getAppName(),that.dom_id,that.preference);
					}
			},
			tolerance:"touch"
		});

		this.actionlist.droppable({
			tolerance:"pointer",
			drop:function (event,ui) {
				that.set_prefered(ui.draggable.attr('id').replace(that.id+'-',''),false);
				ui.draggable.appendTo(that.actionlist);
				that._build_menu(actions);
			}
		});
		toolbox.accordion({
			heightStyle:"fill",
			collapsible: true,
			active:'none',
			activate: function (event, ui) {
				var menubox = event.target;
				if (ui.oldHeader.length == 0)
				{
					$j('html').on('click.outsideOfMenu', function (event){
						$j(menubox).accordion( "option", "active", 2);
						$j(this).unbind(event);
						// Remove the focus class, user clicked elsewhere
						$j(menubox).children().removeClass('ui-state-focus');
					});
				}
			},
			create: function (event, ui) {
				$j('html').unbind('click.outsideOfMenu');
			},
			beforeActivate: function ()
			{
				// Nothing to show in menulist
				if (menulist.children().length == 0)	return false;
			}
		});
	},

	/**
	 * Add/Or remove an action from prefence
	 *
	 * @param {string} _action name of the action which needs to be stored in pereference
	 * @param {boolean} _state if set to true action will be set to actionbox, false will set it to actionlist
	 *
	 */
	set_prefered: function(_action,_state)
	{
		this.preference[_action] = _state;
		if (egwIsMobile()) return;
		egw.set_preference(this.egw().getAppName(),this.dom_id,this.preference);
	},

	/**
	 * Make a button based on the given action
	 *
	 * @param {Object} action action object with attributes icon, caption, ...
	 */
	_make_button: function(action)
	{
		var button_options = {
		};
		var button = $j(document.createElement('button'))
			.addClass("et2_button et2_button_text et2_button_with_image")
			.attr('id', this.id+'-'+action.id)
			.attr('title', (action.hint ? action.hint : action.caption))
			.attr('type', 'button')
			.appendTo(this.preference[action.id]?this.actionbox.children()[1]:$j('[data-group='+action.group+']',this.actionlist));

		if (action && action.checkbox)
		{
			if (action.data.toggle_on || action.data.toggle_off)
			{
				var toggle = et2_createWidget('checkbox', {
					id: this.id+'-'+action.id,
					toggle_on: action.data.toggle_on,
					toggle_off: action.data.toggle_off
				}, this);
				toggle.doLoadingFinished();
				toggle.set_value(action.checked);
				action.data.widget = toggle;
				toggle =toggle.toggle;
				toggle.appendTo(button.parent())
					.attr('id', this.id+'-'+action.id);
				button.remove();
				button = toggle;

			}
			else
			{
				if (this.checkbox(action.id)) button.addClass('toolbar_toggled'+ (typeof action.toggledClass != 'undefined'?" "+action.toggledClass:''));
			}
		}
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
			button_options.icon = action.icon;
		}
		if (!jQuery.isEmptyObject(button_options))
		{
			button.button(button_options);
		}
		var self = this;
		// Set up the click action
		var click = function(e)
		{
			var action = this._actionManager.getActionById(e.data);
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
	},

	/**
	 * Link the actions to the DOM nodes / widget bits.
	 *
	 * @param {Object} actions egw-actions to build menu from
	 */
	_link_actions: function(actions)
	{
		this._build_menu(actions);
	},

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
	checkbox: function (_action, _value)
	{
		if (!_action || typeof this._actionManager == 'undefined') return undefined;
		var action_event = this._actionManager.getActionById(_action);

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
				btn.toggleClass('toolbar_toggled'+ (typeof action_event.data.toggledClass != 'undefined'?" "+action_event.data.toggledClass:''));
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
	},

	getDOMNode: function(asker)
	{
		return this.div[0];
	},

	/**
	 * getValue has to return the value of the input widget
	 */
	getValue: function()
	{
		return this.value;
	},

	/**
	 * Is dirty returns true if the value of the widget has changed since it
	 * was loaded.
	 */
	isDirty: function()
	{
		return this.value != null;
	},

	/**
	 * Causes the dirty flag to be reseted.
	 */
	resetDirty: function()
	{
		this.value = null;
	},

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
	isValid: function(messages)
	{
		return true;
	},

	/**
	 * Attach the container node of the widget to DOM-Tree
	 * @returns {Boolean}
	 */
	doLoadingFinished: function ()
	{
		this._super.apply(this, arguments);
		return false;
	}
});}).call(this);
et2_register_widget(et2_toolbar, ["toolbar"]);
