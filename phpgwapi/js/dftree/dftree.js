/* Dynamic Folder Tree
 * Generates DHTML tree dynamically (on the fly).
 * License: GNU LGPL
 * 
 * Copyright (c) 2004, Vinicius Cubas Brand, Raphael Derosso Pereira
 * {viniciuscb,raphaelpereira} at users.sourceforge.net
 * All rights reserved.
 *
 *
 * This library is free software; you can redistribute it and/or
 * modify it under the terms of the GNU Lesser General Public
 * License as published by the Free Software Foundation; either
 * version 2.1 of the License, or (at your option) any later version.
 * 
 * This library is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the GNU
 * Lesser General Public License for more details.
 * 
 * You should have received a copy of the GNU Lesser General Public
 * License along with this library; if not, write to the Free Software
 * Foundation, Inc., 59 Temple Place, Suite 330, Boston, MA 02111-1307 USA
 */

// NODE 
//Usage: a = new dNode({id:2, caption:'tree root', url:'http://www.w3.org'});
function dNode(arrayProps) {
	//mandatory fields
	this.id;          //node id
	this.caption;     //node caption

	//optional fields
	this.url;         //url to open
	this.target;      //target to open url
	this.onClick;     //javascript to execute onclick
	this.onOpen;      //javascript to execute when a node of the tree opens
	this.onClose;     //javascript to execute when a node of the tree closes
	this.onFirstOpen; //javascript to execute only on the first open
	this.iconClosed;  //img.src of closed icon
	this.iconOpen;    //img.src of open icon
	this.runJS = true;       //(bool) if true, runs the on* props defined above
	this.plusSign = true;    //(bool) if the plus sign will appear or not
	this.captionClass = 'l'; //(string) the class for this node's caption


	//The parameters below are private
	this._opened = false; //already opened
	this._io = false; //is opened

	this._children = []; //references to children
	this._parent; //pointer to parent
	this._myTree; //pointer to myTree
	this._drawn = false;

	for (var i in arrayProps)
	{
		if (i.charAt(0) != '_')
		{
			eval('this.'+i+' = arrayProps[\''+i+'\'];');
		}
	}
}

//changes node state from open to closed, and vice-versa
dNode.prototype.changeState = function()
{
	if (this._io)
	{
		this.close();
	}
	else
	{
		this.open();
	}

	//cons = COokie of Node Status
	//setCookie("cons"+this.id,this._io);
}

dNode.prototype.open = function () {
	if (!this._io)
	{
		if (!this._opened && this.runJS && this.onFirstOpen != null)
		{
			eval(this.onFirstOpen);
		}
		else if (this.runJS && this.onOpen != null)
		{
			eval(this.onOpen);
		}

		this._debug = true;
		this._opened = true;
		this._io = true;
		this._refresh();
	}
}


dNode.prototype.close = function() {
	if (this._io)
	{
		if (this.runJS && this.onClose != null)
		{
			eval(this.onClose);
		}
		this._io = false;
		this._refresh();
	}
}

//alter node label and other properties
dNode.prototype.alter = function(arrayProps)
{
	for (var i in arrayProps)
	{
		if (i != 'id' && i.charAt(0) != '_')
		{
			eval('this.'+i+' = arrayProps[\''+i+'\'];');
		}
	}
}

//css and dhtml refresh part
dNode.prototype._refresh = function() {
	var nodeDiv      = getObjectById("n"+this.id+this._myTree.name);
	var plusSpan     = getObjectById("p"+this.id+this._myTree.name);
	var captionSpan  = getObjectById("l"+this.id+this._myTree.name);
	var childrenDiv  = getObjectById("ch"+this.id+this._myTree.name);

	if (nodeDiv != null)
	{
		//Handling open and close: checks this._io and changes class as needed
		if (!this._io) //just closed
		{
			childrenDiv.className = "closed";
		}
		else //just opened
		{
			//prevents IE undesired behaviour when displaying empty DIVs
/*			if (this._children.length > 0) 
			{*/
				childrenDiv.className = "opened";
		//	}
		}

		plusSpan.innerHTML = this._properPlus();

		captionSpan.innerHTML = this.caption;
	}

//alter onLoad, etc

}

//gets the proper plus for this moment
dNode.prototype._properPlus = function()
{
	if (!this._io) 
	{
		if (this._myTree.useIcons)
		{
			return (this.plusSign)?imageHTML(this._myTree.icons.plus):"";
		}
		else
		{
			return (this.plusSign)?"+":"";
		}
	}
	else 
	{
		if (this._myTree.useIcons)
		{
			return (this.plusSign)?imageHTML(this._myTree.icons.minus):"";
		}
		else
		{
			return (this.plusSign)?"-":"";
		}
	}
}

//changes node to selected style class. Perform further actions.
dNode.prototype._select = function()
{
	var captionSpan;

	if (this._myTree._selected)
	{
		this._myTree._selected._unselect();
	}
	this._myTree._selected = this;
	
	captionSpan  = getObjectById("l"+this.id+this._myTree.name);

	//changes class to selected link
	if (captionSpan)
	{
		captionSpan.className = 'sl';
	}

}

//changes node to unselected style class. Perform further actions.
dNode.prototype._unselect = function()
{
	var captionSpan  = getObjectById("l"+this.id+this._myTree.name);

	this._myTree._lastSelected = this._myTree._selected;
	this._myTree._selected = null;

	//changes class to selected link
	if (captionSpan)
	{
		captionSpan.className = this.captionClass;
	}
}


//state can be open or closed
//warning: if drawed node is not child or root, bugs will happen
dNode.prototype._draw = function()
{
	var str;
	var _this = this;
	var divN, divCH, spanP, spanL, parentChildrenDiv;
	var myClass = (this._io)? "opened" : "closed";
	var myPlus = this._properPlus();
	var append = true;
	var captionOnClickEvent = null;
//	var cook;

	var plusEventHandler = function(){
		_this.changeState();
	}

	var captionEventHandler = function(){
		eval(captionOnClickEvent);
	}

	
/*	if (this.myTree.followCookies)
	{
		this._io = getCookie("cons"+this.id);
	}*/

	//FIXME put this in a separate function, as this will be necessary in 
	//various parts

	if (this.onClick) //FIXME when onclick && url
	{
		captionEventHandler = function () { _this._select(); typeof(_this.onClick) == 'function' ? _this.onClick() : eval(_this.onClick); };
	}
	else if (this.url && this.target)
	{
		captionEventHandler = function () { _this._select(); window.open(_this.url,_this.target); };
	}
	else if (this.url)
	{
		captionEventHandler = function () { _this._select(); window.location=_this.url; };
	}
	

	//The div of this node
	divN = document.createElement('div');
	divN.id = 'n'+this.id+this._myTree.name;
	divN.className = 'son';
//	divN.style.border = '1px solid black';
	
	//The span that holds the plus/minus sign
	spanP = document.createElement('span');
	spanP.id = 'p'+this.id+this._myTree.name;
	spanP.className = 'plus';
	spanP.onclick = plusEventHandler;
	spanP.innerHTML = myPlus;
//	spanP.style.border = '1px solid green';

	//The span that holds the label/caption
	spanL = document.createElement('span');
	spanL.id = 'l'+this.id+this._myTree.name;
	spanL.className = this.captionClass;
	spanL.onclick = captionEventHandler;
	spanL.innerHTML = this.caption;
//	spanL.style.border = '1px solid red';

	//The div that holds the children
	divCH = document.createElement('div');
	divCH.id = 'ch'+this.id+this._myTree.name;
	divCH.className = myClass;
//	divCH.style.border = '1px solid blue';
	
//	div.innerHTML = str;
	divN.appendChild(spanP);
	divN.appendChild(spanL);
	divN.appendChild(divCH);
	

	if (this._parent != null)
	{
		parentChildrenDiv = getObjectById("ch"+this._parent.id+this._myTree.name);
	}
	else //is root
	{
		parentChildrenDiv = getObjectById("dftree_"+this._myTree.name);
	}
	
	if (parentChildrenDiv)
	{
		parentChildrenDiv.appendChild(divN);
	}
}

// TREE 
//Usage: t = new dFTree({name:t, caption:'tree root', url:'http://www.w3.org'});
function dFTree(arrayProps) {
	//mandatory fields
	this.name;      //the value of this must be the name of the object

	//optional fields
	this.is_dynamic = true;   //tree is dynamic, i.e. updated on the fly
	this.followCookies = true;//use previous state (o/c) of nodes
	this.useIcons = false;     //use icons or not
	

	//arrayProps[icondir]: Icons Directory
	iconPath = (arrayProps['icondir'] != null)? arrayProps['icondir'] : '';

	this.icons = {
		root        : iconPath+'/foldertree_base.gif',
		folder      : iconPath+'/foldertree_folder.gif',
		folderOpen  : iconPath+'/foldertree_folderopen.gif',
		node        : iconPath+'/foldertree_folder.gif',
		empty       : iconPath+'/foldertree_empty.gif',
		line        : iconPath+'/foldertree_line.gif',
		join        : iconPath+'/foldertree_join.gif',
		joinBottom  : iconPath+'/foldertree_joinbottom.gif',
		plus        : iconPath+'/foldertree_plus.gif',
		plusBottom  : iconPath+'/foldertree_plusbottom.gif',
		minus       : iconPath+'/foldertree_minus.gif',
		minusBottom : iconPath+'/foldertree_minusbottom.gif',
		nlPlus      : iconPath+'/foldertree_nolines_plus.gif',
		nlMinus     : iconPath+'/foldertree_nolines_minus.gif'
	};

	//private
	this._root = false; //reference to root node
	this._aNodes = [];
	this._lastSelected; //The last selected node
	this._selected; //The actual selected node

	for (var i in arrayProps)
	{
		if (i.charAt(0) != '_')
		{
			eval('this.'+i+' = arrayProps[\''+i+'\'];');
		}
	}
}

dFTree.prototype.draw = function(dest_element) {
	var main_div;
	
	if (!getObjectById("dftree_"+this.name) && dest_element)
	{
		main_div = document.createElement('div');
		main_div.id = 'dftree_'+this.name;
		dest_element.appendChild(main_div);
		this._drawn = true;
	}

	if (this._root != false)
	{
		this._root._draw();
		this._drawBranch(this._root._children);
	}

}

//Transforms tree in HTML code
dFTree.prototype.toString = function() {
	var str = '';

	if (!getObjectById("dftree_"+this.name))
	{
		str = '<div id="dftree_'+this.name+'"></div>';
	}
	return str;

/*	if (this.root != false)
	{
		this.root._draw();
		this._drawBranch(this.root.children);
	}*/
}

//Recursive function, draws children
dFTree.prototype._drawBranch = function(childrenArray) {
	var a=0;
	for (a;a<childrenArray.length;a++)
	{
		childrenArray[a]._draw();
		this._drawBranch(childrenArray[a]._children);
	}
}

//add into a position
dFTree.prototype.add = function(node,pid) {
	var auxPos;
	var addNode = false;

	if (typeof (auxPos = this._searchNode(node.id)) != "number")
	{
		// if parent exists, add node as its child
		if (typeof (auxPos = this._searchNode(pid)) == "number")
		{
			node._parent = this._aNodes[auxPos];
			this._aNodes[auxPos]._children[this._aNodes[auxPos]._children.length] = node;
			addNode = true;
		}
		else //if parent cannot be found and there is a tree root, ignores node
		{
			if (!this._root)
			{
				this._root = node;
				addNode = true;
			}
		}
		if (addNode)
		{
			this._aNodes[this._aNodes.length] = node;
			node._myTree = this;
			if (this.is_dynamic && this._drawn)
			{
				node._draw();
			}
		}
	} 
	else {
		var arrayProps = new Array();

		arrayProps['id'] = node.id;
		arrayProps['caption'] = node.caption;

		arrayProps['url'] = node.url;
		arrayProps['target'] = node.target;
		arrayProps['onClick'] = node.onClick;
		arrayProps['onOpen'] = node.onOpen;
		arrayProps['onClose'] = node.onClose;
		arrayProps['onFirstOpen'] = node.onFirstOpen;
		arrayProps['iconClosed'] = node.iconClosed;
		arrayProps['iconOpen'] = node.iconOpen;
		arrayProps['runJS'] = node.runJS;
		arrayProps['plusSign'] = node.plusSign;
		arrayProps['captionClass'] = node.captionClass;

		delete node;

		this.alter(arrayProps);
	}

}

//arrayProps: same properties of Node
dFTree.prototype.alter = function(arrayProps) {
	this.getNodeById(arrayProps['id']).alter(arrayProps);
}

dFTree.prototype.getNodeById = function(nodeid) {
	return this._aNodes[this._searchNode(nodeid)];
}

dFTree.prototype.openTo = function(nodeid)
{
	var node = this.getNodeById(nodeid);

	if (node && node._parent)
	{
		node._parent.open();
		this.openTo(node._parent.id);
	}
}

//Searches for a node in the node array, returning the position of the array 4it
dFTree.prototype._searchNode = function(id) {
	var a=0;
	for (a;a<this._aNodes.length;a++)
	{
		if (this._aNodes[a].id == id)
		{
			return a;
		}
	}
	return false;
}


//Auxiliar functions

//For multi-browser compatibility
function getObjectById(name)
{   
    if (document.getElementById)
    {
        return document.getElementById(name);
    }
    else if (document.all)
    {
        return document.all[name];
    }
    else if (document.layers)
    {
        return document.layers[name];
    }
    return false;
}

// [Cookie] Clears a cookie
function clearCookie(cookieName) {
	var now = new Date();
	var yesterday = new Date(now.getTime() - 1000 * 60 * 60 * 24);
	this.setCookie(cookieName, 'cookieValue', yesterday);
	this.setCookie(cookieName, 'cookieValue', yesterday);
};

// [Cookie] Sets value in a cookie
function setCookie(cookieName, cookieValue, expires, path, domain, secure) {
	document.cookie =
		escape(cookieName) + '=' + escape(cookieValue)
		+ (expires ? '; expires=' + expires.toGMTString() : '')
		+ (path ? '; path=' + path : '')
		+ (domain ? '; domain=' + domain : '')
		+ (secure ? '; secure' : '');
};

// [Cookie] Gets a value from a cookie
function getCookie(cookieName) {
	var cookieValue = '';
	var posName = document.cookie.indexOf(escape(cookieName) + '=');
	if (posName != -1) {
		var posValue = posName + (escape(cookieName) + '=').length;
		var endPos = document.cookie.indexOf(';', posValue);
		if (endPos != -1)
		{
			cookieValue = unescape(document.cookie.substring(posValue, endPos));
		}
		else 
		{
			cookieValue = unescape(document.cookie.substring(posValue));
		}
	}
	return (cookieValue);
};


function imageHTML(src,attributes) {
	if (attributes != null)
	{
		attributes = '';
	}
	return "<img "+attributes+" src=\""+src+"\">";
}
