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
	/phpgwapi/js/dhtmlxtree/codebase/dhtmlxcommon.js;
	/phpgwapi/js/dhtmlxMenu/sources/dhtmlxmenu.js;
	/phpgwapi/js/dhtmlxMenu/sources/ext/dhtmlxmenu_ext.js;
*/

// Need CSS, or it doesn't really work
if(typeof egw != 'undefined') egw(window).includeCSS(egw.webserverUrl + "/phpgwapi/js/egw_action/test/skins/dhtmlxmenu_egw.css");
function egwMenuImpl(_structure)
{
	//Create a new dhtmlxmenu object
	this.dhtmlxmenu = new dhtmlXMenuObject();
	this.dhtmlxmenu.setSkin("egw");
	this.dhtmlxmenu.renderAsContextMenu();
	// TODO: Keyboard navigation of the menu

	var self = this;

	//Attach the simple click handler
	this.dhtmlxmenu.attachEvent("onClick", function(id) {
		if (id)
		{
			var elem = self.dhtmlxmenu.getUserData(id, 'egw_menu');

			if (elem && elem.onClick)
			{
				if (elem.checkbox)
				{
					self.dhtmlxmenu.setContextMenuHideAllMode(false);
				}

				var res = elem.onClick(elem);

				if (elem.checkbox && (res === false || res === true))
				{
					var checked = res;
					if (elem.groupIndex != 0)
					{
						self.dhtmlxmenu.setRadioChecked(id, checked);
					}
					else
					{
						self.dhtmlxmenu.setCheckboxState(id, checked);
					}
				}
			}
		}
	});

	//Attach the radiobutton click handler
	this.dhtmlxmenu.attachEvent("onRadioClick", function(group, idChecked, idClicked, zoneId, casState) {
		if (idClicked)
		{
			var elem = self.dhtmlxmenu.getUserData(idClicked, 'egw_menu');
			if (elem)
			{
				elem.set_checked(true);
			}
		}

		return true;
	});

	//Attach the radiobutton click handler
	this.dhtmlxmenu.attachEvent("onCheckboxClick", function(id, state, zoneId, casState) {
		if (id)
		{
			var elem = self.dhtmlxmenu.getUserData(id, 'egw_menu');
			if (elem)
			{
				elem.set_checked(!state);
			}
		}

		return true;
	});


	//Translate the given structure to the dhtmlx object structure
	this._translateStructure(_structure, this.dhtmlxmenu.topId, 0);
}

egwMenuImpl.prototype._translateStructure = function(_structure, _parentId, _idCnt)
{
	//Initialize the counter which we will use to generate unique id's for all
	//dhtmlx menu objects
	var counter = 0;
	var last_id = null;

	for (var i = 0; i < _structure.length; i++)
	{
		var elem = _structure[i];
		var id = elem.id || 'elem_' + (_idCnt + counter);

		counter++;

		//Check whether this element is a seperator
		if (elem.caption == '-' && last_id != null)
		{
			//Add the separator next to last_id with the id "id"
			this.dhtmlxmenu.addNewSeparator(last_id, id);
		}
		else
		{
			if (elem.checkbox && elem.groupIndex === 0)
			{
				//Add checkbox
				this.dhtmlxmenu.addCheckbox("child", _parentId, i, id,
					elem.caption, elem.checked, !elem.enabled);
			}
			else if (elem.checkbox && elem.groupIndex > 0)
			{
				//Add radiobox
				elem._dhtmlx_grpid = "grp_" + _idCnt + '_' + elem.groupIndex;
				this.dhtmlxmenu.addRadioButton("child", _parentId, i, id,
					elem.caption, elem._dhtmlx_grpid, elem.checked, !elem.enabled);
			}
			else
			{
				var caption = elem.caption;
				if (elem["default"])
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

		var last_id = id;
	}

	return counter;
}


egwMenuImpl.prototype.showAt = function(_x, _y, _onHide)
{
	var self = this;

	if (_onHide)
	{
		this.dhtmlxmenu.attachEvent("onHide", function(id) {
			if (id === null)
			{
				_onHide();
			}
		});
	}

	var self = this;
	window.setTimeout(function() {
		self.dhtmlxmenu.showContextMenu(_x, _y);
		// TODO: Get keybard focus
	}, 0);
}

egwMenuImpl.prototype.hide = function()
{
	this.dhtmlxmenu.hide();
}


