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
var et2_toolbar = et2_DOMWidget.extend(
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
	 * Constructor
	 *
	 * @memberOf et2_dropdown_button
	 */
	init: function() {
		this._super.apply(this, arguments);
		this.div = $j(document.createElement('div'))
			.addClass('et2_toolbar ui-widget-header ui-corner-all');

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
		
		var pref = egw.preference(this.id,this.egw().getAppName());
		if (pref && !jQuery.isArray(pref)) this.preference = pref;

		//Set the default actions for the first time
		if (typeof pref === 'undefined')
		{
			for (var name in actions)
			{
				if (!actions[name].toolbarDefault)
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
			}
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
		}

		this.countActions = countActions(actions) - Object.keys(this.preference).length;
		
		var last_group = false;
		var last_group_id = false;
		for(var name in actions)
		{
			var action = actions[name];

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
							if (typeof root.children[id].group != 'undefined' &&
									typeof root.group != 'undefined')
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
				
				dropdown.set_image (action.iconUrl);
				dropdown.onchange = jQuery.proxy(function(selected, dropdown)
				{
					var action = that._actionManager.getActionById(selected.attr('data-id'));
					if(action)
					{
						if(action)
						{
							action.onExecute.exec(action);
						}
					}
					console.debug(selected, this, action);
				},action);
				dropdown.onclick = jQuery.proxy(function(selected, dropdown)
				{
					var action = that._actionManager.getActionById(this.getValue());
					if(action)
					{
						if(action)
						{
							action.onExecute.exec(action);
						}
					}
					console.debug(selected, this, action);
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
			return +lg.dataset.group - +g.dataset.group;
			}).appendTo(this.actionlist);

		this.actionlist.appendTo(this.div);
		this.actionbox.appendTo(this.div);

		var toolbar =jQuery('#'+this.id+'-'+'actionlist').find('span').children(),
			toolbox = jQuery('#'+this.id+'-'+'actionbox'),
			menulist = jQuery('#'+this.id+'-'+'menulist');
		
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
			cursor:"move"
		});
		toolbox.children().droppable({
			accept:toolbar,
			drop:function (event, ui) {
					that.set_prefered(ui.draggable.attr('id').replace(that.id+'-',''),"add");
					ui.draggable.appendTo(menulist);
					if (that.actionlist.find(".ui-draggable").length == 0)
					{
						that.preference = {};
						egw.set_preference(that.egw().getAppName(),that.id,that.preference);
					}
			},
			tolerance:"touch"
		});

		jQuery('#'+this.id+'-'+'actionlist').droppable({
			tolerance:"pointer",
			drop:function (event,ui) {
				that.set_prefered(ui.draggable.attr('id').replace(that.id+'-',''),"remove");
				ui.draggable.appendTo(jQuery('#'+that.id+'-'+'actionlist'));
				that._build_menu(actions);
			}
		});
		toolbox.accordion({
			heightStyle:"fill",
			collapsible: true,
			active:'none'
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
				egw.set_preference(this.egw().getAppName(),this.id,this.preference);
				break;
			case "remove":
				delete this.preference[_action];
				egw.set_preference(this.egw().getAppName(),this.id,this.preference);
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

		if ( action.iconUrl)
		{
			button.attr('style','background-image:url(' + action.iconUrl + ')');
		}
		if (action.caption)
		{
			if ((this.countActions <= parseInt(this.view_range) ||
					this.preference[action.id])	&&
					typeof button[0] != 'undefined')
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
		var click = function(e) {
			var action = this._actionManager.getActionById(e.data);
			if(action)
			{
				action.data.event = e;
				action.onExecute.exec(action);
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
	}
});
et2_register_widget(et2_toolbar, ["toolbar"]);
