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
		var _this = this;

		style = document.createElement('link');
		style.href = GLOBALS['serverRoot'] + "phpgwapi/js/dTabs/dTabs.css";
		style.rel = "stylesheet";
		style.type = "text/css";

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

		var create = function ()
		{
			document.body.appendChild(style);
			document.body.appendChild(_this._Tabs['root']);
			_this.created = true;
		}

		this.created = false;
		//JsLib.postponeFunction(create);
		create();
	}

	/*
		@method addTab
		@abstract Inserts a tab
	*/
	dTabsManager.prototype.addTab = function (params)
	{
		var _this = this;

		if (this.created)
		{
			return this.addTabIE(params);
		}

		//JsLib.postponeFunction(function(){ _this.addTabIE(params);});
	}

	dTabsManager.prototype.addTabIE = function (params)
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
		
	//	element.parentNode.removeChild(element);
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

		this._Tabs.contentsDIV.appendChild(this._Tabs['contents'][params['id']]);

		this._nTabs++;

		if (this._nTabs == 1)
		{
			this._showTab(params['id']);
		}
		
		return;
	}

	dTabsManager.prototype.enableTab = function(id)
	{
		var _this = this;

		var enable = function()
		{
			if (_this._Tabs.contents[id])
			{
				_this._Tabs.tabIndexTDs[id].className = 'dTabs_unselected';
				_this._Tabs.tabIndexTDs[id].onclick = function() {_this._showTab(id);};
			}
		}
		
		if (!this.created)
		{
			JsLib.postponeFunction(enable);
		}
		else
		{
			enable();
		}
	}

	dTabsManager.prototype.disableTab = function(id)
	{
		var _this = this;

		var disable = function ()
		{
			if (_this._Tabs.contents[id])
			{
				_this._Tabs.tabIndexTDs[id].className = 'dTabs_disabled';
				_this._Tabs.tabIndexTDs[id].onclick = null;
			}
		}
		
		if (!this.created)
		{
			JsLib.postponeFunction(disable);
		}
		else
		{
			disable();
		}
	}

	/****************************************************************************\
	 *                         Private Methods                                  *
	\****************************************************************************/

