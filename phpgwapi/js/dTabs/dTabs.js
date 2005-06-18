  /****************************************************************************\
   * Dynamic Tabs - Javascript Object                                         *
   *                                                                          *
   * Written by:                                                              *
   *  - Raphael Derosso Pereira <raphaelpereira@users.sourceforge.net>        *
   * ------------------------------------------------------------------------ *
   *  This program is free software; you can redistribute it and/or modify it *
   *  under the terms of the GNU General Public License as published by the   *
   *  Free Software Foundation; either version 2 of the License, or (at your  *
   *  option) any later version.                                              *
  \****************************************************************************/

	/*
	 * Dynamic Tabs - On-The-Fly Tabs in Javascript
	 *
	 * Usage:
	 *		var tabs = new dTabsManager({'id': <DIV id to be used>, 'width': '498px'});
	 *
	 *		tabs.addTab({'id': <ID of the Contents DIV (must be absolute)>,
	 *		             'name': <text to be shown on tab selector>,
	 *                   'selectedClass': <name of the class to be used when Tab is selected>,
	 *                   'unselectedClass': <name of the class to be used when Tab is not selected>});
	 */
	 
	/* Mozilla 1.6 has a bug which impossibilitates it to manage DOM contents that are defined in the
	 * original document. So we have to workaround it.
	 */
	navigator.userAgent.toLowerCase().match('mozilla.*rv[:]1\.6.*gecko') ? is_moz1_6 = true : is_moz1_6 = false;
		
	function dTabsManager(params)
	{
		this._tabEvents = { show: {}, hide: {}};
		this.init(params);
	}

	dTabsManager.prototype.init = function(params)
	{
		/* Attributes definition */
		this._Tabs = new Array();
		this._Tabs['root'] = null;
		this._Tabs['tabIndexTR'] = null;
		this._Tabs['tabIndexTDs'] = new Array();
		this._Tabs['contents'] = null;
		this._Tabs['contentsDIV'] = null;
		this._selectedIndex = null;
		
		this._nTabs   = params['nTabs'] ? params['nTabs'] : 0;
		this._maxTabs = params['maxTabs'] ? params['maxTabs'] : 0;


		/* Create and insert the container */
		var table, tbody, tr, td, style;

		style = document.createElement('link');
		style.href = GLOBALS['serverRoot'] + "phpgwapi/js/dTabs/dTabs.css";
		style.rel = "stylesheet";
		style.type = "text/css";
		document.body.appendChild(style);

		this._Tabs['root'] = document.createElement('div');
		this._Tabs['root'].id = params['id'];
		this._Tabs['root'].style.position = 'absolute';
		//this._Tabs['root'].style.visibility = 'hidden';
		this._Tabs['root'].style.top = '150px';
		this._Tabs['root'].style.left = '0px';
		this._Tabs['root'].style.width = params['width'] ? params['width'] : 0;
		
		table = document.createElement('table');
		tbody = document.createElement('tbody');
		table.style.border = '0px solid black';
		table.style.width  = '100%';
		table.style.height = '100%';
		table.cellpadding = '10px';

		this._Tabs['tabIndexTR'] = document.createElement('tr');
		this._Tabs['tabIndexTR'].style.height = '30px';
		this._Tabs['tabIndexTR'].className = 'dTabs_tr_index';
		//this._Tabs['tabIndexTR'].style.width  = '100%';
		
		this._Tabs['emptyTab'] = document.createElement('td');
		this._Tabs['emptyTab'].className = 'dTabs_noTabs';
		this._Tabs['emptyTab'].innerHTML = '&nbsp;';
		this._Tabs['tabIndexTR'].appendChild(this._Tabs['emptyTab']);
		
		tr = document.createElement('tr');
		td = document.createElement('td');

		tr.style.width  = '100%';
		tr.style.height = '100%';

		//this._Tabs['contentsDIV'] = document.createElement('div');
		//this._Tabs['contentsDIV'].style.position = 'relative';
		this._Tabs['contentsDIV'] = td;

		this._Tabs['root'].appendChild(table);
		table.appendChild(tbody);
		tbody.appendChild(this._Tabs['tabIndexTR']);
		tbody.appendChild(tr);
		tr.appendChild(td);
		//td.appendChild(this._Tabs['contentsDIV']);
		tr.appendChild(this._Tabs['contentsDIV']);
		
		this._Tabs['contents'] = new Array();

		document.body.appendChild(this._Tabs['root']);
	}

	/*
		@method addTab
		@abstract Inserts a tab
	*/
	dTabsManager.prototype.addTab = function (params)
	{
		if (typeof(params) != 'object')
		{
			return false;
		}

		if (!params['id'] || !Element(params['id']) || 
		    Element(params['id']).tagName.toLowerCase() != 'div' ||
			Element(params['id']).style.position.toLowerCase() != 'absolute')
		{
			return false;
		}
		
		if (this._Tabs['contents'][params['id']])
		{
			this.replaceTab(params);
			return;
		}

		//var contents, tdIndex;
		var element = Element(params['id']);
		
		if (is_moz1_6)
		{/*
			var element_ = element;
			element = element.cloneNode(true);
			//element_.parentNode.replaceChild(element, element_);
			element_.id = element_.id+'CLONED';
			element_.style.display = 'none';*/
			element.style.position = 'absolute';
		}
		else
		{
			element.parentNode.removeChild(element);
		}
		element.style.top = parseInt(this._Tabs['tabIndexTR'].style.height) + 5 + 'px';
		element.style.left = this._Tabs['root'].style.left;
		element.style.zIndex = '-1';
		
		this._Tabs['contents'][params['id']] = element;
		
		this._Tabs.tabIndexTDs[params['id']] = document.createElement('td');
		
		var _this = this;
		this._Tabs.tabIndexTDs[params['id']].innerHTML           = '&nbsp;&nbsp;'+(params['name'] ? params['name'] : 'undefined')+'&nbsp;&nbsp;';
		this._Tabs.tabIndexTDs[params['id']].selectedClassName   = 'dTabs_selected';
		this._Tabs.tabIndexTDs[params['id']].unselectedClassName = 'dTabs_unselected';
		this._Tabs.tabIndexTDs[params['id']].className           = 'dTabs_unselected';
		this._Tabs.tabIndexTDs[params['id']].onclick             = function() {_this._showTab(params['id']);};

		/* Old Version
		this._Tabs.tabIndexTDs[params['id']].innerHTML           = params['name'] ? params['name'] : 'undefined';
		this._Tabs.tabIndexTDs[params['id']].selectedClassName   = params['selectedClass'];
		this._Tabs.tabIndexTDs[params['id']].unselectedClassName = params['unselectedClass'];
		this._Tabs.tabIndexTDs[params['id']].className           = params['unselectedClass'];
		this._Tabs.tabIndexTDs[params['id']].onclick             = function() {_this._showTab(params['id']);};
		*/

		this._Tabs.tabIndexTR.removeChild(this._Tabs['emptyTab']);
		this._Tabs.tabIndexTR.appendChild(this._Tabs.tabIndexTDs[params['id']]);
		this._Tabs.tabIndexTR.appendChild(this._Tabs['emptyTab']);

		if (!is_moz1_6)
		{
			this._Tabs.contentsDIV.appendChild(this._Tabs['contents'][params['id']]);
		}

		this._nTabs++;

		if (this._nTabs == 1)
		{
			this._showTab(params['id']);
		}
		
		return;
	}

	dTabsManager.prototype.removeTab = function (id)
	{
		if (!this._Tabs.contents[params['id']])
		{
			return false;
		}
		
		this._Tabs.tabIndexTR.removeChild(this._Tabs.tabIndexTDs[params['id']]);
		this._Tabs.contentsDIV.removeChild(this._Tabs.contents[params['id']]);
		this._Tabs.contents[params['id']] = null;
		this._Tabs.tabIndexTDs[params['id']] = null;

		return true;
	}
	
	dTabsManager.prototype.getTabObject = function()
	{
		return this._Tabs.root;
	}
	
	dTabsManager.prototype.enableTab = function(id)
	{
		if (this._Tabs.contents[id])
		{
			var _this = this;
			this._Tabs.tabIndexTDs[id].className = 'dTabs_unselected';
			this._Tabs.tabIndexTDs[id].onclick = function() {_this._showTab(id);};
		}
	}

	dTabsManager.prototype.disableTab = function(id)
	{
		if (this._Tabs.contents[id])
		{
			this._Tabs.tabIndexTDs[id].className = 'dTabs_disabled';
			this._Tabs.tabIndexTDs[id].onclick = false;
		}
	}

	/****************************************************************************\
	 *                         Private Methods                                  *
	\****************************************************************************/

	dTabsManager.prototype._showTab = function (id)
	{
		if (!this._Tabs.contents[id])
		{
			return false;
		}

		/* Ajust the tabIndexTR width to be the same as the contents width*/
		if (this._Tabs.contents[id].style.width)
		{
			this._Tabs['root'].style.width = this._Tabs.contents[id].style.width;
			this._Tabs['root'].style.height = this._Tabs.contents[id].style.height;
		}
		/*
		else 
		{
			this._Tabs['root'].style.width = '0px';
		}*/

		this._Tabs.tabIndexTDs[id].className = this._Tabs.tabIndexTDs[id].selectedClassName;

		for (var i in this._Tabs.contents)
		{
			if (i == 'length')
			{
				continue;
			}
			
			//viniciuscb: mystery
			if (this._Tabs.contents[i].style != null)
			{
				this._Tabs.contents[i].style.visibility = 'hidden';
				this._Tabs.contents[i].style.display = 'none';
				this._Tabs.contents[i].style.zIndex = '-1';
				if (this._tabEvents['hide'][i]) this._tabEvents['hide'][i]();
			}
		}
		
		this._Tabs.contents[id].style.visibility = 'visible';
		this._Tabs.contents[id].style.display = '';
		this._Tabs.contents[id].style.zIndex = '10';
		if (this._tabEvents['show'][id]) this._tabEvents['show'][id]();

		if (this._selectedIndex && this._selectedIndex != id)
		{
			this._Tabs.tabIndexTDs[this._selectedIndex].className = this._Tabs.tabIndexTDs[this._selectedIndex].unselectedClassName;
			this._focus(this._Tabs.contents[id]);
		}
		
		this._selectedIndex = id;
	}

	dTabsManager.prototype._focus = function(obj)
	{
		for (var i in obj.childNodes)
		{
			if (obj.childNodes[i].focus)
			{
				obj.childNodes[i].focus();
				return true;
			}
			else
			{
				if (this._focus(obj.childNodes[i]))
				{
					return true;
				}
			}
		}
	}
