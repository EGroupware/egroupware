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
	},

	/**
	 * Default buttons, so there is something for the widget browser / editor to show
	 */
	default_toolbar: {
		view: {caption:'View', icons: {primary: 'ui-icon-check'}, group:1},
		edit: {caption:'Edit', group:1},
		save: {caption:'Save', group:2}
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

		this.dropdowns = {};

		this._build_menu(this.default_toolbar);
	},

	destroy: function() {
		// Destroy widget
		if(this.div && this.div.data('ui-menu')) this.menu.menu("destroy");

		// Null children

		// Remove
		this.div.empty().remove();
	},

	/**
	 * Go through actions and build buttons for the toolbar
	 */
	_build_menu: function(actions)
	{
		// Clear existing
		this.div.empty();

		var last_group = false;
		for(var name in actions)
		{
			var action = actions[name];

			// Add in divider
			if(!last_group) last_group = action.group;
			if(last_group != action.group)
			{
				this.div.append(" ");
				last_group = action.group;
			}

			// Make sure there's something to display
			if(!action.caption && !action.icon && !action.iconUrl) continue;

			if(action.children)
			{
				this.dropdowns[action.id] = $j(document.createElement('span'))
					.addClass("ui-state-default")
					.appendTo(this.div);
				var children = {};
				var add_children = function(root, children) {
					for(var id in root.children)
					{
						var info = {
							id: id || root.children[id].id,
							label: root.children[id].caption
						};
						if(root.children[id].iconUrl)
						{
							info.icon = root.children[id].iconUrl;
						}
						if(root.children[id].children)
						{
							add_children(root.children[id], info);
						}
						children[id] = info;
					}
				};
				add_children(action, children);
				var dropdown = et2_createWidget("dropdown_button", {
					id: action.id,
					label: action.caption
				},this);
				dropdown.set_select_options(children);
				var toolbar = this;
				dropdown.onchange = jQuery.proxy(function(selected, dropdown)
				{
					var action = toolbar._actionManager.getActionById(selected.attr('data-id'));
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
					var action = toolbar._actionManager.getActionById(this.getValue());
					if(action)
					{
						if(action)
						{
							action.onExecute.exec(action);
						}
					}
					console.debug(selected, this, action);
				},dropdown);
			}
			else
			{
				this._make_button(action);
			}
		}
	},

	/**
	 * Make a button based on the given action
	 */
	_make_button: function(action)
	{
		var button_options = {
		};
		var button = $j(document.createElement('button'))
			.addClass("et2_button")
			.attr('id', this.id+'-'+action.id)
			.appendTo(this.div);
		if(action.iconUrl)
		{
			button.prepend("<img src='"+action.iconUrl+"' title='"+action.caption+"' class='et2_button_icon'/>");
		}
		if(action.icon)
		{
			button_options.icon = action.icon
		}
		button.button(button_options);

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
	 */
	_link_actions: function(actions)
	{
		this._build_menu(actions);
	},

	getDOMNode: function(asker)
	{
		if(asker != this && asker.id && this.dropdowns[asker.id])
		{
			return this.dropdowns[asker.id][0];
		}
		return this.div[0];
	}
});
et2_register_widget(et2_toolbar, ["toolbar"]);

