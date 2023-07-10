/**
 * eGroupWare egw_action framework - JS Menu abstraction
 *
 * @link http://www.egroupware.org
 * @author Andreas Stöckel <as@stylite.de>
 * @copyright 2011 by Andreas Stöckel
 * @license http://opensource.org/licenses/gpl-license.php GPL - GNU General Public License
 * @package egw_action
 * @version $Id$
 */


import {egwMenuItem} from "./egw_menu";

/**
 *
 * @param {type} _structure
 */
export class egwMenuImpl
{
	readonly dhtmlxmenu:any;
	constructor(_structure)
	{
		//Create a new dhtmlxmenu object
		// @ts-ignore       //origin not easily ts compatible --> soon to be replaced by shoelace anyway
		this.dhtmlxmenu = new dhtmlXMenuObject();
		this.dhtmlxmenu.setSkin("egw");
		this.dhtmlxmenu.renderAsContextMenu();
		// TODO: Keyboard navigation of the menu


		//Attach the simple click handler
		this.dhtmlxmenu.attachEvent("onClick", (id) => {
			if (id)
			{
				const elem = this.dhtmlxmenu.getUserData(id, 'egw_menu');

				if (elem && elem.onClick)
				{
					if (elem.checkbox)
					{
						this.dhtmlxmenu.setContextMenuHideAllMode(false);
					}

					const res = elem.onClick(elem);

					if (elem.checkbox && (res === false || res === true))
					{
						const checked = res;
						if (elem.groupIndex != 0)
						{
							this.dhtmlxmenu.setRadioChecked(id, checked);
						} else
						{
							this.dhtmlxmenu.setCheckboxState(id, checked);
						}
					}
				}
			}
		});

		//Attach the radiobutton click handler
		this.dhtmlxmenu.attachEvent("onRadioClick", (group, idChecked, idClicked) => {
			if (idClicked)
			{
				const elem = this.dhtmlxmenu.getUserData(idClicked, 'egw_menu');
				if (elem)
				{
					elem.set_checked(true);
				}
			}

			return true;
		});

		//Attach the radiobutton click handler
		this.dhtmlxmenu.attachEvent("onCheckboxClick", (id, state) => {
			if (id)
			{
				const elem = this.dhtmlxmenu.getUserData(id, 'egw_menu');
				if (elem)
				{
					elem.set_checked(!state);
				}
			}

			return true;
		});


		//Translate the given structure to the dhtmlx object structure
		this._translateStructure(_structure, this.dhtmlxmenu.topId, 0);

		// Add disableIfNoEPL class to the relevant action's DOM
		for (const i in this.dhtmlxmenu.idPull)
		{
			if (this.dhtmlxmenu.userData[i + '_egw_menu'] &&
				this.dhtmlxmenu.userData[i + '_egw_menu']['data'] &&
				this.dhtmlxmenu.userData[i + '_egw_menu']['data']['disableIfNoEPL'])
			{
				this.dhtmlxmenu.idPull[i].className += ' disableIfNoEPL';
			}
		}
	}

	private _translateStructure(_structure:egwMenuItem[], _parentId: string, _idCnt: number)
	{
		//Initialize the counter which we will use to generate unique id's for all
		//dhtmlx menu objects
		let counter: number = 0;
		let last_id = null;

		_structure.forEach((elem: egwMenuItem, i: number) =>
		{
			const id = elem.id || 'elem_' + (_idCnt + counter);

			counter++;

			//Check whether this element is a separator
			if (elem.caption == '-' && last_id != null)
			{
				//Add the separator next to last_id with the id "id"
				this.dhtmlxmenu.addNewSeparator(last_id, id);
			} else
			{
				if (elem.checkbox && elem.groupIndex === 0)
				{
					//Add checkbox
					this.dhtmlxmenu.addCheckbox("child", _parentId, i, id,
						elem.caption, elem.checked, !elem.enabled);
				} else if (elem.checkbox && elem.groupIndex > 0)
				{
					//Add radiobox
					elem._dhtmlx_grpid = "grp_" + _idCnt + '_' + elem.groupIndex;
					this.dhtmlxmenu.addRadioButton("child", _parentId, i, id,
						elem.caption, elem._dhtmlx_grpid, elem.checked, !elem.enabled);
				} else
				{
					let caption = elem.caption;
					if (elem.default)
						caption = "<b>" + caption + "</b>";
					this.dhtmlxmenu.addNewChild(_parentId, i, id, caption, !elem.enabled,
						elem.iconUrl, elem.iconUrl);
				}

				if (elem.shortcutCaption != null)
				{
					this.dhtmlxmenu.setHotKey(id, elem.shortcutCaption);
				}

				if (elem.children.length > 0)
				{
					counter += this._translateStructure(elem.children, id, (_idCnt + counter));
				}
			}

			//Set the actual egw menu as user data element
			this.dhtmlxmenu.setUserData(id, 'egw_menu', elem);

			// Set the tooltip if one has been set
			if (elem.hint)
			{
				this.dhtmlxmenu.setTooltip(id, elem.hint);
			}

			last_id = id;
		})

		return counter;
	};

	public showAt(_x, _y, _onHide)
	{
		if (_onHide)
		{
			this.dhtmlxmenu.attachEvent("onHide", (id) => {
				if (id === null)
				{
					_onHide();
				}
			});
		}

		window.setTimeout(() => {
			this.dhtmlxmenu.showContextMenu(_x, _y);
			// TODO: Get keyboard focus
		}, 0);
	};

	public hide()
	{
		this.dhtmlxmenu.hide();
	};

}