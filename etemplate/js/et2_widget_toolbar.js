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

"use strict";

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
var et2_toolbar = et2_DOMWidget.extend([et2_IInput],
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
				.addClass("et2_toolbar_activeList")
				.attr('id',this.id +'-'+ 'actionbox');
		//actionlist is div for active actions
		this.actionlist = $j(document.createElement('div'))
				.addClass("et2_toolbar_actionlist")
				.attr('id',this.id +'-'+ 'actionlist');

		this.countActions = 0;
		this.dropdowns = {};
		this.preference = {};
		
		this._build_menu(this.default_toolbar);
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
	 * Go through actions and build buttons for the toolbar
	 *
	 * @param {Object} actions egw-actions to build menu from
	 */
	_build_menu: function(actions)
	{
		// Clear existing
		this.div.empty();
		this.actionbox.empty();
		this.actionlist.empty();
		this.actionbox.append('<h class="ui-toolbar-menulistHeader">'+egw.lang('more')+' ...'+'</h>');
		this.actionbox.append('<div id="' + this.id + '-menulist' +'" class="ui-toolbar-menulist" ></div>');
		var that = this;
		
		// toolbar preference id correction,
		// before toolbar used to address pereference with this.id which were not scalable
		// need to convert them to dom_id prefix for old pref and the new one come with dom_id automatically
		//TODO: this part of code needs to be removed later
		var old_pref = egw.preference(this.id,this.egw().getAppName());
		if (old_pref)
		{
			//Set the old preference with correct id
			egw.set_preference(this.egw().getAppName(), this.dom_id,old_pref);
			// delete stored pref with wrong id
			egw.set_preference(this.egw().getAppName(),this.id);
		}
		
		
		var pref = egw.preference(this.dom_id,this.egw().getAppName());
		if (pref && !jQuery.isArray(pref)) this.preference = pref;
			
		//Set the default actions for the first time
		if (typeof pref === 'undefined')
		{
			for (var name in actions)
			{
				if (!actions[name].toolbarDefault &&
						(typeof actions[name].children === 'undefined' || !this.options.flat_list))
					this.set_prefered(actions[name].id,'add');
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

		this.countActions = countActions(actions) - Object.keys(this.preference).length;

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
							if (typeof pref === 'undefined')
							{
								if (!childaction.toolbarDefault)
								{
									that.set_prefered(childaction.id,'add');
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
		this.actionlist.find('span').sort( function (lg,g){
			return +lg.getAttribute('data-group') - +g.getAttribute('data-group');
			}).appendTo(this.actionlist);

		this.actionlist.appendTo(this.div);
		this.actionbox.appendTo(this.div);

		var toolbar = this.actionlist.find('span').children(),
			toolbox = this.actionbox,
			menulist = jQuery(this.actionbox.children()[1]);

		toolbar.draggable({
			cancel:true,
			zIndex: 1000,
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
			},
		});
		toolbox.children().droppable({
			accept:toolbar,
			drop:function (event, ui) {
					that.set_prefered(ui.draggable.attr('id').replace(that.id+'-',''),"add");
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
				that.set_prefered(ui.draggable.attr('id').replace(that.id+'-',''),"remove");
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
					});
				}
			},
			create: function (event, ui) {
				$j('html').unbind('click.outsideOfMenu');
			}
		});
	},

	/**
	 * Add/Or remove an action from prefence
	 *
	 * @param {string} _action name of the action which needs to be stored in pereference
	 * @param {string} _do if set to "add" add the action to preference and "remove" remove one from preference
	 *
	 */
	set_prefered: function(_action,_do)
	{
		switch(_do)
		{
			case "add":
				this.preference[_action] = true;
				egw.set_preference(this.egw().getAppName(),this.dom_id,this.preference);
				break;
			case "remove":
				delete this.preference[_action];
				egw.set_preference(this.egw().getAppName(),this.dom_id,this.preference);
		}
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
			.appendTo(this.preference[action.id]?this.actionbox.children()[1]:$j('[data-group='+action.group+']',this.actionlist));
		
		if (action && action.checkbox)
		{
			var action_event = typeof this._actionManager != 'undefined'?this._actionManager.getActionById(action.id):null; 
			if (action_event && action_event.checked) button.addClass('toolbar_toggled');	
		}	
		if ( action.iconUrl)
		{
			button.attr('style','background-image:url(' + action.iconUrl + ')');
		}
		if (action.caption)
		{
			if ((this.countActions <= parseInt(this.options.view_range) ||
					this.preference[action.id])	&&
					typeof button[0] !== 'undefined')
			{
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
		// Set up the click action
		var click = function(e)
		{
			var action = this._actionManager.getActionById(e.data);
			if(action)
			{
				if (action.checkbox)
				{
					action.set_checked(!action.checked);
					jQuery(button).toggleClass('toolbar_toggled');
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
});
et2_register_widget(et2_toolbar, ["toolbar"]);
