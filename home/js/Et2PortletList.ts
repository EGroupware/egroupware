import {Et2Portlet} from "../../api/js/etemplate/Et2Portlet/Et2Portlet";
import {et2_createWidget} from "../../api/js/etemplate/et2_core_widget";
import {css, html, TemplateResult} from "@lion/core";
import shoelace from "../../api/js/etemplate/Styles/shoelace";
import type {SelectOption} from "../../api/js/etemplate/Et2Select/FindSelectOptions";
import {Et2Dialog} from "../../api/js/etemplate/Et2Dialog/Et2Dialog";

/**
 * Home portlet to show a list of entries
 */
export class Et2PortletList extends Et2Portlet
{
	static get styles()
	{
		return [
			...shoelace,
			...(super.styles || []),
			css`
			  .delete_button {
				padding-right: 10px;
			  }
			`
		]
	}

	constructor()
	{
		super();
		this.link_change = this.link_change.bind(this);
	}

	/**
	 * For list_portlet - opens a dialog to add a new entry to the list
	 *
	 * @param {egwAction} action Drop or add action
	 * @param {egwActionObject[]} Selected entries
	 * @param {egwActionObject} target_action Drop target
	 */
	add_link(action, source, target_action)
	{
		// TODO
		debugger;

		// Actions got confused drop vs popup
		if(source[0].id == 'portlets')
		{
			return this.add_link(action);
		}

		// Get widget
		let widget = null;
		while(action.parent != null)
		{
			if(action.data && action.data.widget)
			{
				widget = action.data.widget;
				break;
			}
			action = action.parent;
		}
		if(target_action == null)
		{
			// use template base url from initial template, to continue using webdav, if that was loaded via webdav
			let splitted = 'home.edit'.split('.');
			let path = app.home.portlet_container.getRoot()._inst.template_base_url + splitted.shift() + "/templates/default/" +
				splitted.join('.') + ".xet";
			let dialog = et2_createWidget("dialog", {
				callback: function(button_id, value)
				{
					if(button_id == et2_dialog.CANCEL_BUTTON)
					{
						return;
					}
					let new_list = widget.options.settings.list || [];
					for(let i = 0; i < new_list.length; i++)
					{
						if(new_list[i].app == value.add.app && new_list[i].id == value.add.id)
						{
							// Duplicate - skip it
							return;
						}
					}
					value.add.link_id = value.add.app + ':' + value.add.id;
					// Update server side
					new_list.push(value.add);
					widget._process_edit(button_id, {list: new_list});
					// Update client side
					let list = widget.getWidgetById('list');
					if(list)
					{
						list.set_value(new_list);
					}
				},
				buttons: et2_dialog.BUTTONS_OK_CANCEL,
				title: app.home.egw.lang('add'),
				template: path,
				value: {content: [{label: app.home.egw.lang('add'), type: 'link-entry', name: 'add', size: ''}]}
			});
		}
		else
		{
			// Drag'n'dropped something on the list - just send action IDs
			let new_list = widget.options.settings.list || [];
			let changed = false;
			for(let i = 0; i < new_list.length; i++)
			{
				// Avoid duplicates
				for(let j = 0; j < source.length; j++)
				{
					if(!source[j].id || new_list[i].app + "::" + new_list[i].id == source[j].id)
					{
						// Duplicate - skip it
						source.splice(j, 1);
					}
				}
			}
			for(let i = 0; i < source.length; i++)
			{
				let explode = source[i].id.split('::');
				new_list.push({app: explode[0], id: explode[1], link_id: explode.join(':')});
				changed = true;
			}
			if(changed)
			{
				widget._process_edit(et2_dialog.OK_BUTTON, {
					list: new_list || {}
				});
			}
			// Filemanager support - links need app = 'file' and type set
			for(let i = 0; i < new_list.length; i++)
			{
				if(new_list[i]['app'] == 'filemanager')
				{
					new_list[i]['app'] = 'file';
					new_list[i]['path'] = new_list[i]['title'] = new_list[i]['icon'] = new_list[i]['id'];
				}
			}

			widget.getWidgetById('list').set_value(new_list);
		}
	}

	/**
	 * Remove a link from the list
	 */
	link_change(event)
	{
		if(!this.getInstanceManager()?.isReady)
		{
			return;
		}

		// Not used, but delete puts link in event.data
		let link_data = event.data || false;

		// Update settings on link delete
		if(link_data)
		{
			this.update_settings({list: this.settings.list});
		}
	}

	/**
	 * Get a list of user-configurable properties
	 * @returns {[{name : string, type : string, select_options? : [SelectOption]}]}
	 */
	get portletProperties() : { name : string, type : string, label : string, select_options? : SelectOption[] }[]
	{
		return [
			...super.portletProperties,
			{name: "title", type: "et2-textbox", label: "Title"},
			{name: "add", type: "et2-link-entry", label: "Add"}
		]
	}

	_process_edit(button_id, value)
	{
		if(button_id == Et2Dialog.OK_BUTTON && value.add)
		{
			// Add in to list, remove from value or it will be saved
			value.list = [...this.settings.list, value.add];
			delete value.add;
		}
		super._process_edit(button_id, value);
	}

	bodyTemplate() : TemplateResult
	{
		return html`
            <et2-link-list .value=${this.settings?.list || []}
                           @change=${this.link_change}
            >
            </et2-link-list>`
	}

}

if(!customElements.get("et2-portlet-list"))
{
	customElements.define("et2-portlet-list", Et2PortletList);
}