  /****************************************************************************\
   * Dynamic JS Win - Javascript Object                                       *
   *                                                                          *
   * Written by:                                                              *
   *  - Raphael Derosso Pereira <raphaelpereira@users.sourceforge.net>        *
   * ------------------------------------------------------------------------ *
   *  This program is free software; you can redistribute it and/or modify it *
   *  under the terms of the GNU General Public License as published by the   *
   *  Free Software Foundation; either version 2 of the License, or (at your  *
   *  option) any later version.                                              *
  \****************************************************************************/

	dJSWin.prototype.init = function(params)
	{
		if (!params || typeof(params) != 'object' || !params.id || !params.width || !params.height || !params.content_id)
		{
			throw("Can't create empty window or window without width, height or ID");
		}

		/* Internal Variables */
		this.winContainer = document.createElement('iframe');
		this.clientArea = document.createElement('div');
		this.title = document.createElement('div');
		this.title_text = null; //document.createElement('');
		this.buttons = new Array();
		this.shadows = new Array();
		this.border = new Array();
		this.content_id = params.content_id;
		this.includedContents = params['include_contents'];
		var pixelTABLE, pixelTBODY, pixelTR, pixelTD;
		var _this = this;
		var style;

		/* Create 1 pixel table to be able to create freely
		 * sizeble DIVs on IE
		 */
		pixelTABLE = document.createElement('table');
		pixelTBODY = document.createElement('tbody');
		pixelTR = document.createElement('tR');
		pixelTD = document.createElement('td');

		pixelTABLE.appendChild(pixelTBODY);
		pixelTBODY.appendChild(pixelTR);
		pixelTR.appendChild(pixelTD);
	
		this.title_text_cont = pixelTABLE.cloneNode(true);
		this.title_text = this.title_text_cont.firstChild.firstChild.firstChild;
	
		pixelTABLE.style.width  = '1px';
		pixelTABLE.style.height = '1px';
		
		pixelTD.style.width  = '1px';
		pixelTD.style.height = '1px';

		this.border['t'] = document.createElement('div');
		this.border['b'] = document.createElement('div');
		this.border['l'] = document.createElement('div');
		this.border['r'] = document.createElement('div');

		this.shadows['r'] = document.createElement('div');
		this.shadows['b'] = document.createElement('div');

		this.buttons['xDIV'] = document.createElement('div');

		if (params['button_x_img'])
		{
			this.buttons['xIMG'] = document.createElement('IMG');
			this.buttons['xIMG'].src = params['button_x_img'];
			this.buttons['xIMG'].style.cursor = 'hand';
			this.buttons['xDIV'].appendChild(this.buttons['xIMG']);
		}
		else
		{
			this.buttons.xDIV.innerHTML = 'X';
		}
		
		/* Inicialization */
		this.title.id = params['id'];
		this.title.style.position = 'absolute';
		this.title.style.visibility = 'hidden';
		this.title.style.width = parseInt(params['width']) + 2 + 'px';
		this.title.style.height = params['title_height'] ? params['title_height'] : '18px';
		this.title.style.backgroundColor = '#3978d6';
	//	this.title.className = 'dJSWin_title';
		this.title.style.top = '0px';
		this.title.style.left = '0px';
		this.title.style.zIndex = '1';
		
		this.title_text_cont.id = params['id'] + '_text';
		this.title_text_cont.style.position = 'relative';
//		this.title_text_cont.border = '0';
		this.title_text_cont.style.width = '100%';
		this.title_text_cont.style.height = '100%';
		this.title_text.style.cursor = 'move';
		this.title_text_cont.className = 'dJSWin_title_text';
		this.title_text.className = 'dJSWin_title_text';
//		this.title_text.style.cursor = 'move';
		this.title_text.innerHTML = params['title'];
		this.title_text_cont.style.zIndex = '1';

		this.winContainer.id = params['id']+'_winContainer';
		this.winContainer.style.position = 'absolute';
		this.winContainer.style.visibility = 'hidden';
		this.winContainer.style.width  = params['width'];
		this.winContainer.style.height = parseInt(params['height']) + parseInt(this.title.style.height) + 'px';
//		this.winContainer.style.top = '0px';
		this.winContainer.style.top = '0px'; //this.title.style.height;
		this.winContainer.style.left = '0px';
		this.winContainer.style.zIndex = '-1';
//		this.winContainer.style.backgroundColor = params['bg_color'];
//		this.winContainer.className = params['win_class'];
		this.winContainer.src = '';
		
		this.clientArea.id = params['id']+'_clientArea';
		this.clientArea.style.position = 'absolute';
		this.clientArea.style.visibility = 'hidden';
		this.clientArea.style.width  = parseInt(params['width']) + 2 + 'px';
		this.clientArea.style.height = params['height'];
		this.clientArea.style.top = parseInt(this.title.style.height) + 'px';
		this.clientArea.style.left = '0px';
	//	this.clientArea.style.backgroundColor = params['bg_color'];
//		this.clientArea.style.overflow = 'auto';
		this.clientArea.className = 'dJSWin_main';
		
		this.buttons.xDIV.id = params['id']+'_button';
		this.buttons.xDIV.style.position = 'absolute';
		this.buttons.xDIV.style.visibility = 'hidden';
		this.buttons.xDIV.style.cursor = 'hand';
		this.buttons.xDIV.style.top = '1px';
		this.buttons.xDIV.style.left = parseInt(params['width']) - 13 + 'px';
		this.buttons.xDIV.style.zIndex = '1';
		this.buttons.xDIV.onclick = function() {_this.close();};
		
		this.shadows.b.id = params['id']+'_shadowb';
		this.shadows.b.style.position = 'absolute';
		this.shadows.b.style.visibility = 'hidden';
		this.shadows.b.style.backgroundColor = '#666';
		this.shadows.b.style.width = params['width'];
		this.shadows.b.style.height = '4px';
		this.shadows.b.style.top = parseInt(this.title.style.height) + parseInt(params['height']) + 2 + 'px';
		this.shadows.b.style.left = '4px';
		this.shadows.b.appendChild(pixelTABLE.cloneNode(true));
		
		this.shadows.r.id = params['id']+'_shadowr';
		this.shadows.r.style.position = 'absolute';
		this.shadows.r.style.visibility = 'hidden';
		this.shadows.r.style.backgroundColor = '#666';
		this.shadows.r.style.width = '4px';
		this.shadows.r.style.height = parseInt(params['height']) + parseInt(this.title.style.height) + 'px';
		this.shadows.r.style.top  = '4px';
		this.shadows.r.style.left = params['width'];

		this.border.t.id = params['id']+'_border_t';
		this.border.b.id = params['id']+'_border_b';
		this.border.l.id = params['id']+'_border_l';
		this.border.r.id = params['id']+'_border_r';
		
		this.border.t.style.position = 'absolute';
		this.border.b.style.position = 'absolute';
		this.border.l.style.position = 'absolute';
		this.border.r.style.position = 'absolute';

		this.border.t.style.visibility = 'hidden';
		this.border.b.style.visibility = 'hidden';
		this.border.l.style.visibility = 'hidden';
		this.border.r.style.visibility = 'hidden';

		this.border.t.className = 'dJSWin_title';
		this.border.b.className = 'dJSWin_title';
		this.border.l.className = 'dJSWin_title';
		this.border.r.className = 'dJSWin_title';
		
		this.border.t.style.border = '0px';
		this.border.b.style.border = '0px';
		this.border.l.style.border = '0px';
		this.border.r.style.border = '0px';

		this.border.t.appendChild(pixelTABLE.cloneNode(true));
		this.border.b.appendChild(pixelTABLE.cloneNode(true));
		
		if (params['border'])
		{
			this.border.t.style.width = parseInt(params['width']) + 2 + 'px';
			this.border.b.style.width = parseInt(params['width']) + 4 + 'px';
			this.border.l.style.width = '2px';
			this.border.r.style.width = '2px';

			this.border.t.style.height = '2px';
			this.border.b.style.height = '2px';
			this.border.l.style.height = parseInt(params['height']) + parseInt(this.title.style.height) + 4 + 'px';
			this.border.r.style.height = parseInt(params['height']) + parseInt(this.title.style.height) + 4 + 'px';

			this.border.t.style.top = '-2px';
			this.border.b.style.top = parseInt(params['height']) + parseInt(this.title.style.height) + 'px';
			this.border.l.style.top = '-2px'; 
			this.border.r.style.top = '-2px';

			this.border.t.style.left = '-2px';
			this.border.b.style.left = '-2px';
			this.border.l.style.left = '-2px';
			this.border.r.style.left = params['width'];

			this.shadows.b.style.top = parseInt(this.shadows.b.style.top) + 2 + 'px';
			this.shadows.r.style.top = parseInt(this.shadows.r.style.top) + 2 + 'px';
			this.shadows.b.style.left = parseInt(this.shadows.b.style.left) + 2 + 'px';
			this.shadows.r.style.left = parseInt(this.shadows.r.style.left) + 2 + 'px';
		
			this.winContainer.style.top = '-3px';
			this.winContainer.style.left = '-2px';
			this.winContainer.style.width = parseInt(this.winContainer.style.width) + 8 + 'px';
			this.winContainer.style.height = parseInt(this.winContainer.style.height) + 10 + 'px';
		}
		else
		{
			this.border.t.style.width = '0px';
			this.border.b.style.width = '0px';
			this.border.l.style.width = '0px';
			this.border.r.style.width = '0px';
		}
		
		//JsLib.postponeFunction(function(){_this._createWin();});
		this._createWin();
	}

	dJSWin.prototype.open = function()
	{
		this.moveTo(document.body.offsetWidth/2 + document.body.scrollLeft - dd.elements[this.title.id].w/2,
					document.body.offsetHeight/2 + document.body.scrollTop - dd.elements[this.winContainer.id].h/2);
		
		dd.elements[this.title.id].maximizeZ();
		dd.elements[this.title.id].show();
	}

	dJSWin.prototype.draw = function()
	{
		if (this.drawn)
		{
			return;
		}

		this.drawn = true;
		
		if (dd.elements && dd.elements[this.title.id])
		{
			return;
		}

		ADD_DHTML(this.title.id+CURSOR_MOVE);
		ADD_DHTML(this.title_text_cont.id+NO_DRAG);
		ADD_DHTML(this.winContainer.id+NO_DRAG);
		ADD_DHTML(this.clientArea.id+NO_DRAG);
		ADD_DHTML(this.buttons.xDIV.id+NO_DRAG);
		ADD_DHTML(this.content.id+NO_DRAG);
		ADD_DHTML(this.shadows.r.id+NO_DRAG);
		ADD_DHTML(this.shadows.b.id+NO_DRAG);
		ADD_DHTML(this.border.t.id+NO_DRAG);
		ADD_DHTML(this.border.b.id+NO_DRAG);
		ADD_DHTML(this.border.l.id+NO_DRAG);
		ADD_DHTML(this.border.r.id+NO_DRAG);
		

		dd.elements[this.winContainer.id].setZ(-1);
		dd.elements[this.title.id].setZ(0);
		dd.elements[this.title.id].addChild(dd.elements[this.title_text_cont.id]);
		dd.elements[this.title.id].addChild(dd.elements[this.winContainer.id]);
		dd.elements[this.title.id].addChild(dd.elements[this.clientArea.id]);
		dd.elements[this.title.id].addChild(dd.elements[this.buttons.xDIV.id]);
		dd.elements[this.title.id].addChild(dd.elements[this.content.id]);
		dd.elements[this.title.id].addChild(dd.elements[this.shadows.r.id]);
		dd.elements[this.title.id].addChild(dd.elements[this.shadows.b.id]);
		dd.elements[this.title.id].addChild(dd.elements[this.border.t.id]);
		dd.elements[this.title.id].addChild(dd.elements[this.border.b.id]);
		dd.elements[this.title.id].addChild(dd.elements[this.border.l.id]);
		dd.elements[this.title.id].addChild(dd.elements[this.border.r.id]);


		if (typeof(this.includedContents) == 'object')
		{
			for (var i in this.includedContents)
			{
				ADD_DHTML(this.includedContents[i]+NO_DRAG);
				dd.elements[this.title.id].addChild(dd.elements[this.includedContents[i]]);
			}
		}

		dd.elements[this.title.id].moveTo(document.body.offsetWidth/2 - dd.elements[this.winContainer.id].w/2,
		                                  document.body.offsetHeight/2 - dd.elements[this.winContainer.id].h/2)
		
		dd.elements[this.title.id].hide();
	}

	dJSWin.prototype._createWin = function()
	{		
		style = document.createElement('link');
		style.href = GLOBALS['serverRoot'] + "phpgwapi/js/dJSWin/dJSWin.css";
		style.rel = "stylesheet";
		style.type = "text/css";
		document.body.appendChild(style);

		this.content = Element(this.content_id);
		this.content.style.visibility = 'hidden';
		//this.content.style.top = parseInt(this.title.style.height) + 'px';
		this.content.style.top = '0px';
		this.content.style.left = '0px';
		
		this.title.appendChild(this.title_text_cont);
		this.title.appendChild(this.winContainer);
		this.title.appendChild(this.clientArea);
		this.title.appendChild(this.buttons.xDIV);
		this.title.appendChild(this.border.t);
		this.title.appendChild(this.border.b);
		this.title.appendChild(this.border.l);
		this.title.appendChild(this.border.r);
		this.title.appendChild(this.shadows.r);
		this.title.appendChild(this.shadows.b);
		this.clientArea.appendChild(this.content);
		
		document.body.appendChild(this.title);

		this.draw();
	}
