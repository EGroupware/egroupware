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

	if (document.all)
	{
		navigator.userAgent.toLowerCase().indexOf('msie 5') != -1 ? is_ie5 = true : is_ie5 = false;
		is_ie = true;
		is_moz1_6 = false;
		is_mozilla = false;
		is_ns4 = false;
	}
	else if (document.getElementById)
	{
		navigator.userAgent.toLowerCase().match('mozilla.*rv[:]1\.6.*gecko') ? is_moz1_6 = true : is_moz1_6 = false;
		is_ie = false;
		is_ie5 = false;
		is_mozilla = true;
		is_ns4 = false;
	}
	else if (document.layers)
	{
		is_ie = false;
		is_ie5 = false
		is_moz1_6 = false;
		is_mozilla = false;
		is_ns4 = true;
	}

	/* The code below is a wrapper to call the dTabs content insertion
	 * just after the document is loaded in IE... I still don't know why people
	 * use this crap!
	 */
	var _dTabs_onload;
	
	if (document.all)
	{
		_dTabs_onload = false;
	
		var _dTabs_onload_f = document.body.onload;
		var _dTabs_f = function(e)
		{
			_dTabs_onload = true;
			
			if (_dTabs_onload_f)
			{
				_dTabs_onload_f();
			}
		};

		document.body.onload = _dTabs_f;
	}
	else
	{
		_dTabs_onload = true;
	}
	
	function dTabsManager(params)
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
		var table, tbody, tr, td;

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

		this._Tabs['tabIndexTR'] = document.createElement('tr');
		this._Tabs['tabIndexTR'].style.height = '30px';
		//this._Tabs['tabIndexTR'].style.width  = '100%';
		
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

		if (!_dTabs_onload)
		{
			if (document.getElementById && !document.all)
			{
				document.body.appendChild(this._Tabs['root']);
			}
			else
			{
				var _this = this;
				var now = document.body.onload ? document.body.onload : null;
				var f = function(e)
				{
					document.body.appendChild(_this._Tabs['root']);
					now ? now() : false;
				};

				document.body.onload = f;
			}
		}
		else
		{
			document.body.appendChild(this._Tabs['root']);
		}
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

		if (!params['id'] || !_dtElement(params['id']) || 
		    _dtElement(params['id']).tagName.toLowerCase() != 'div' ||
			_dtElement(params['id']).style.position.toLowerCase() != 'absolute')
		{
			return false;
		}
		
		if (this._Tabs['contents'][params['id']])
		{
			this.replaceTab(params);
			return;
		}

		//var contents, tdIndex;
		var element = _dtElement(params['id']);
		
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
		this._Tabs.tabIndexTDs[params['id']].innerHTML           = params['name'] ? params['name'] : 'undefined';
		this._Tabs.tabIndexTDs[params['id']].selectedClassName   = params['selectedClass'];
		this._Tabs.tabIndexTDs[params['id']].unselectedClassName = params['unselectedClass'];
		this._Tabs.tabIndexTDs[params['id']].className           = params['unselectedClass'];
		this._Tabs.tabIndexTDs[params['id']].onclick             = function() {_this._showTab(params['id']);};

		for (var i in this._Tabs.tabIndexTDs)
		{
			if (i == 'length')
			{
				return;
			}
			
			this._Tabs.tabIndexTDs[i].style.width = (100/(this._nTabs+1)) + '%';
		}

		this._Tabs.tabIndexTR.appendChild(this._Tabs.tabIndexTDs[params['id']]);

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
			
			this._Tabs.contents[i].style.visibility = 'hidden';
			this._Tabs.contents[i].style.zIndex = '-1';
		}
		
		this._Tabs.contents[id].style.visibility = 'visible';
		this._Tabs.contents[id].style.zIndex = '10';

		if (this._selectedIndex && this._selectedIndex != id)
		{
			this._Tabs.tabIndexTDs[this._selectedIndex].className = this._Tabs.tabIndexTDs[this._selectedIndex].unselectedClassName;
			if (!document.all)
			{
				this._focus(this._Tabs.contents[id]);
			}
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

	function _dtElement(id)
	{
		if (document.getElementById)
		{
			return document.getElementById(id);
		}
		else if (document.all)
		{
			return document.all[id];
		}
		else
		{
			throw('Browser not supported');
		}
	}
