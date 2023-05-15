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

/*egw:uses
	egw_menu;
	/api/js/dhtmlxtree/codebase/dhtmlxcommon.js;
	/api/js/dhtmlxMenu/sources/dhtmlxmenu.js;
	/api/js/dhtmlxMenu/sources/ext/dhtmlxmenu_ext.js;
*/

// Need CSS, or it doesn't really work
//import {dhtmlXMenuObject} from "../dhtmlxMenu/sources/dhtmlxmenu.js"

export class EgwMenuImpl
{
	//Create a new dhtmlxMenu object
	// noinspection JSPotentiallyInvalidConstructorUsage
	private readonly dhtmlxMenu = new dhtmlXMenuObject();

	constructor(_structure)
	{
		//Attach the simple click handler
		this.dhtmlxMenu.attachEvent("onClick", (id) => {
			if (id)
			{
				const elem = this.dhtmlxMenu.getUserData(id, 'egw_menu');

				if (elem && elem.onClick)
				{
					if (elem.checkbox)
					{
						this.dhtmlxMenu.setContextMenuHideAllMode(false);
					}

					const res = elem.onClick(elem);

					if (elem.checkbox && (res === false || res === true))
					{
						const checked = res;
						if (elem.groupIndex != 0)
						{
							this.dhtmlxMenu.setRadioChecked(id, checked);
						} else
						{
							this.dhtmlxMenu.setCheckboxState(id, checked);
						}
					}
				}
			}
		});
		//Attach the radiobutton click handler
		this.dhtmlxMenu.attachEvent("onRadioClick", (group, idChecked, idClicked) => {
			if (idClicked)
			{
				const elem = this.dhtmlxMenu.getUserData(idClicked, 'egw_menu');
				if (elem)
				{
					elem.set_checked(true);
				}
			}

			return true;
		});
		//Attach the checkbox click handler
		this.dhtmlxMenu.attachEvent("onCheckboxClick", (id, state) => {
			if (id)
			{
				const elem = this.dhtmlxMenu.getUserData(id, 'egw_menu');
				if (elem)
				{
					elem.set_checked(!state);
				}
			}

			return true;
		});
		//Translate the given structure to the dhtmlx object structure
		this._translateStructure(_structure, this.dhtmlxMenu.topId, 0);
		// Add disableIfNoEPL class to the relevant action's DOM
		for (const i in this.dhtmlxMenu.idPull)
		{
			if (this.dhtmlxMenu.userData[i + '_egw_menu'] &&
				this.dhtmlxMenu.userData[i + '_egw_menu']['data'] &&
				this.dhtmlxMenu.userData[i + '_egw_menu']['data']['disableIfNoEPL'])
			{
				this.dhtmlxMenu.idPull[i].className += ' disableIfNoEPL';
			}
		}
	}

	/**
	 *
	 * @param {EgwMenuItem[]}_structure       ??
	 * @param {string}_parentId
	 * @param _idCnt
	 * @private
	 */
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
				this.dhtmlxMenu.addNewSeparator(last_id, id);
			} else
			{
				if (elem.checkbox && elem.groupIndex === 0)
				{
					//Add checkbox
					this.dhtmlxMenu.addCheckbox("child", _parentId, i, id,
						elem.caption, elem.checked, !elem.enabled);
				} else if (elem.checkbox && elem.groupIndex > 0)
				{
					//Add radiobox
					elem._dhtmlx_grpid = "grp_" + _idCnt + '_' + elem.groupIndex;
					this.dhtmlxMenu.addRadioButton("child", _parentId, i, id,
						elem.caption, elem._dhtmlx_grpid, elem.checked, !elem.enabled);
				} else
				{
					let caption = elem.caption;
					if (elem.default)
						caption = "<b>" + caption + "</b>";
					this.dhtmlxMenu.addNewChild(_parentId, i, id, caption, !elem.enabled,
						elem.iconUrl, elem.iconUrl);
				}

				if (elem.shortcutCaption != null)
				{
					this.dhtmlxMenu.setHotKey(id, elem.shortcutCaption);
				}

				if (elem.children.length > 0)
				{
					counter += this._translateStructure(elem.children, id, (_idCnt + counter));
				}
			}

			//Set the actual egw menu as user data element
			this.dhtmlxMenu.setUserData(id, 'egw_menu', elem);

			// Set the tooltip if one has been set
			if (elem.hint)
			{
				this.dhtmlxMenu.setTooltip(id, elem.hint);
			}

			last_id = id;
		})

		return counter;
	};

}

export class egwMenuImpl extends  EgwMenuImpl{

}