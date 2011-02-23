/**
 * eGroupWare egw_action framework - JS Menu abstraction
 *
 * @link http://www.egroupware.org
 * @author Andreas Stöckel <as@stylite.de>
 * @copyright 2011 by Andreas Stöckel
 * @license http://opensource.org/licenses/gpl-license.php GPL - GNU General Public License
 * @package egw_action
 * @version $Id:$
 */

function egwMenuImpl(_structure)
{
	//Create a new dhtmlxmenu object
	this.dhtmlxmenu = new dhtmlXMenuObject();
	this.dhtmlxmenu.setSkin("egw");
	this.dhtmlxmenu.renderAsContextMenu();

	var self = this;

	//Attach the simple click handler
	this.dhtmlxmenu.attachEvent("onClick", function(id) {
		if (id)
		{
			var elem = self.dhtmlxmenu.getUserData(id, 'egw_menu');
			if (elem && elem.onClick)
			{
				elem.onClick(elem);
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
		var id = 'elem_' + (_idCnt + counter);
		var elem = _structure[i];

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
				if (elem.default)
					caption = "<b>" + caption + "</b>"
				this.dhtmlxmenu.addNewChild(_parentId, i, id, caption, !elem.enabled,
					elem.iconUrl, elem.iconUrl);
			}

			if (elem.children.length > 0)
			{
				counter += this._translateStructure(elem.children, id, (_idCnt + counter));
			}
		}

		//Set the actual egw menu as user data element
		this.dhtmlxmenu.setUserData(id, 'egw_menu', elem);

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
	this.dhtmlxmenu.showContextMenu(_x, _y);
}

egwMenuImpl.prototype.hide = function()
{
	this.dhtmlxmenu.hide();
}


