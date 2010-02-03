/**
 * eGroupWare eTemplate Extension - AJAX Select Widget
 *
 * @license http://opensource.org/licenses/gpl-license.php GPL - GNU General Public License
 * @package etemplate
 * @subpackage api
 * @link http://www.egroupware.org
 * @author Ralf Becker <RalfBecker@outdoor-training.de>
 * @version $Id$
 */

function submitit(form,name)
{
	//alert(name+' pressed');
	form.submit_button.value = name;
	form.submit();
	form.submit_button.value = '';
	return false;
}

function set_element(form,name,value)
{
	//alert('set_element: '+name+'='+value);
	for (i = 0; i < form.length; i++)
	{
		if (form.elements[i].name == name)
		{
			//alert('set_element: '+name+'='+value);
			form.elements[i].value = value;
			//alert(name+'='+form.elements[i].value);
		}
	}
}

function set_element2(form,name,vname)
{
	//alert('set_element2: '+name+'='+vname);
	for (i = 0; i < form.length; i++)
	{
		if (form.elements[i].name == vname)
		{
			value = form.elements[i].value;
		}
	}
	//alert('set_element2: '+name+'='+value);
	for (i = 0; i < form.length; i++)
	{
		if (form.elements[i].name == name)
		{
			form.elements[i].value = value;
		}
	}
}

function activate_tab(tab,all_tabs,name)
{
	var tabs = all_tabs.split('|');
	var parts = tab.split('.');
	var last_part = parts.length-1;

	for (n = 0; n < tabs.length; n++)
	{
		var t = tabs[n];

		if (t.indexOf('.') < 0 && parts.length > 1)
		{
			parts[last_part] = t;
			t = parts.join('.');
		}
		document.getElementById(t).style.display = t == tab ? 'inline' : 'none';
		document.getElementById(t+'-tab').className = 'etemplate_tab'+(t == tab ? '_active th' : ' row_on');
	}
	// activate FCK in newly activated tab for Gecko browsers
	if (!document.all)
	{
		try {
			var t = document.getElementById(tab);
			var inputs = t.getElementsByTagName('input');
			for (i = 0; i < inputs.length;i++) {
		    	editor = FCKeditorAPI.GetInstance(inputs[i].name);
		    	if (editor && editor.EditorDocument && editor.EditMode == FCK_EDITMODE_WYSIWYG) {
		    		editor.SwitchEditMode();
		      		editor.SwitchEditMode();
		      		break;
		    	}
			}
		}
		catch(e) { }	// ignore the error if FCKeditorAPI is not loaded
	}
	if (name) {
		set_element(document.eTemplate,name,tab);
	}
}

/* proxy to to add options to a selectbox, needed by IE, but works everywhere */
function selectbox_add_option(id,label,value,do_onchange)
{
	selectBox = document.getElementById(id);
	/*alert('selectbox_add_option('+id+','+label+','+value+') '+selectBox);*/
	var search_val = value.split(':');
	for (i=0; i < selectBox.length; i++) {
		var selectvalue = selectBox.options[i].value.split(':');
		if (selectvalue[0] == search_val[0]) {
			selectBox.options[i] = null;
			selectBox.options[selectBox.length] = new Option(label,value,false,true);
			break;
		}
	}
	if (i >= selectBox.length) {
		selectBox.options[selectBox.length] = new Option(label,value,false,true);
	}
	if (selectBox.onchange && do_onchange) selectBox.onchange();
}

/* toggles all checkboxes named name in form form, to be used as custom javascript in onclick of a button/image */
function toggle_all(form,name)
{
	var all_set = true;

	/* this is for use with a sub-grid. To use it pass "true" as third parameter */
	if(toggle_all.arguments.length > 2 && toggle_all.arguments[2] == true)
	{
		el = form.getElementsByTagName("input");
		for (var i = 0; i < el.length; i++)
		{
			if(el[i].name.substr(el[i].name.length-12,el[i].name.length) == '[checkbox][]' && el[i].checked)
			{
					all_set = false;
					break;
			}
		}
		for (var i = 0; i < el.length; i++)
		{
			if(el[i].name.substr(el[i].name.length-12,el[i].name.length) == '[checkbox][]')
			{
				el[i].checked = all_set;
			}
		}
	}
	else
	{
		var checkboxes = document.getElementsByName(name);
		for (var i = 0; i < checkboxes.length; i++)
		{
			if (!checkboxes[i].checked)
			{
				all_set = false;
				break;
			}
		}
		for (var i = 0; i < checkboxes.length; i++)
		{
			checkboxes[i].checked = !all_set;
		}
	}
}

/* gets the values of the named widgets (use the etemplate-name, not the form-name) and creates an url from it */
function values2url(form,names)
{
	url = '';
	names = names.split(',');
	for(i=0; i < names.length; i++)
	{
		form_name = names[i];
		b = form_name.indexOf('[');
		if (b < 0) {
			form_name = 'exec['+form_name+']';
		} else {
			form_name = 'exec['+form_name.slice(0,b-1)+']'+form_name.slice(b,99);
		}
		//alert('Searching for '+form_name);
		for (f=0; f < form.elements.length; f++) {
			element = form.elements[f];
			//alert('checking '+element.name);
			if (element.name.slice(0,form_name.length) == form_name) {
				//alert('found '+element.name+', value='+element.value);
				if (element.type == 'checkbox' || element.type == 'radio') {	// checkbox or radio
					if (element.checked) url += '&'+element.name+'='+element.value;
				} else if (element.options) {	// selectbox
					for(opt=0; opt < element.options.length; opt++) {
						//alert('found '+element.name+' option['+opt+'] = '+element.options[opt].value+ ' = '.element.options[opt].text+': '+element.options[opt].selected);
						if (element.options[opt].selected) url += '&'+element.name+(element.name.indexOf('[]') >= 0 || !element.multiple ? '=' : '[]=')+element.options[opt].value;
					}
				} else if (element.value != null) {
					url += '&'+element.name+'='+element.value;
				}
			}
		}
	}
	//alert('url='+url);
	return url+'&etemplate_exec_id='+form['etemplate_exec_id'].value;
}

// submits the whole form via ajax to a given menuaction or the current one if '' passed
function ajax_submit(form,menuaction)
{
	if(!menuaction) menuaction = form.action.replace(/.+menuaction=/,'');

	xajax_doXMLHTTP(menuaction+'./etemplate/process_exec', xajax.getFormValues(form));
}

// sets value (v) of style property (p) for all given elements of type (t) and class (c)
// eg. set_style_by_class('td','hide','visibility','visible')
function set_style_by_class(t,c,p,v)
{
	//alert('set_style_by_class('+t+','+c+','+p+','+v+')');
	var elements;
	if(t == '*') {
		// '*' not supported by IE/Win 5.5 and below
		elements = (document.all) ? document.all : document.getElementsByTagName('*');
	} else {
		elements = document.getElementsByTagName(t);
	}
	for(var i = 0; i < elements.length; i++){
		var node = elements.item(i);
		for(var j = 0; j < node.attributes.length; j++) {
			if(node.attributes.item(j).nodeName.toLowerCase() == 'class') {
				if(node.attributes.item(j).nodeValue == c) {
					eval('node.style.' + p + " = '" +v + "'");
				}
			}
		}
	}
}

function xajax_eT_wrapper(obj) {
	if (typeof(obj) == 'object') {
		set_style_by_class('div','popupManual noPrint','display','none');
		set_style_by_class('div','ajax-loader','display','inline');
		obj.form.submit_button.value = obj.name;
		var menuaction = obj.form.action.replace(/.+menuaction=/,'');
		xajax_doXMLHTTP(menuaction+'./etemplate/process_exec', xajax.getFormValues(obj.form));
	}
	else {
		set_style_by_class('div','ajax-loader','display','none');
		set_style_by_class('div','popupManual noPrint','display','inline');
	}
}

function disable_button(id) {
	document.getElementById(id).disabled = 'true';
	document.getElementById(id).style.color = 'gray';
}

function enable_button(id) {
	document.getElementById(id).disabled = 'false';
	document.getElementById(id).style.color = '';
}

// returns selected checkboxes from given 'var form' which REAL names end with 'var suffix'
function get_selected(form,suffix) {
	selected = '';
	el = form.getElementsByTagName('input');
	for (var i = 0; i < el.length; i++)	{
		if(el[i].name.substr(el[i].name.length-suffix.length,el[i].name.length) == suffix && el[i].checked) {
			if(selected.length > 0)	{
				selected += ',';
			}
			selected += el[i].value;
		}
	}
	return selected;
}

// set certain comma-separated values in a multiselection (div with checkboxes, used as replacement for a multiselection)
function set_multiselection(name,values,reset)
{
	//alert("set_multiselection('"+name+"','"+values+"',"+reset+")");
	checkboxes = document.getElementsByName(name);
	div = document.getElementById(name.substr(0,name.length-2));
	div_first = div.firstChild;
	values = ','+values+',';
	for (var i = 0; i < checkboxes.length; i++)	{
		checkbox = checkboxes[i];
		value = values.indexOf(','+checkbox.value+',') >= 0;
		if (reset || value) {
			//alert(checkbox.name+': value='+checkbox.value+', checked='+checkbox.checked+' --> '+value);
			if (value && checkbox.parentNode != div_first) {
				br = checkbox.parentNode.nextSibling;
				div.insertBefore(div.removeChild(checkbox.parentNode),div_first);
				div.insertBefore(div.removeChild(br),div_first);
			}
			checkbox.checked = value;
		}
	}
}

// add an other upload
function add_upload(upload)
{
	var parent = upload.parentNode;
	var newUpload = upload.cloneNode(true);
	parent.insertBefore(newUpload,upload);
	var br = document.createElement('br');
	parent.insertBefore(br,upload);
	newUpload.value = '';
	newUpload.id += parent.childNodes.length;
	parent.insertBefore(upload,newUpload);
}

function dropdown_menu_hack(el)
{
	if(el.runtimeStyle)
	{
		if(el.runtimeStyle.behavior.toLowerCase()=="none"){return;}
		el.runtimeStyle.behavior="none";

		if (el.multiple ==1) {return;}
		if (el.size > 1) {return;}

		var ie5 = (document.namespaces==null);
		el.ondblclick = function(e)
		{
			window.event.returnValue=false;
			return false;
		}

		if(window.createPopup==null)
		{
			var fid = "dropdown_menu_hack_" + Date.parse(new Date());

			window.createPopup = function()
			{
				if(window.createPopup.frameWindow==null)
				{
					el.insertAdjacentHTML("MyFrame","<iframe id='"+fid+"' name='"+fid+"' src='about:blank' frameborder='1' scrolling='no'></></iframe>");
					var f = document.frames[fid];
					f.document.open();
					f.document.write("<html><body></body></html>");
					f.document.close();
					f.fid = fid;


					var fwin = document.getElementById(fid);
					fwin.style.cssText="position:absolute;top:0;left:0;display:none;z-index:99999;";


					f.show = function(px,py,pw,ph,baseElement)
					{
						py = py + baseElement.getBoundingClientRect().top + Math.max( document.body.scrollTop, document.documentElement.scrollTop) ;
						px = px + baseElement.getBoundingClientRect().left + Math.max( document.body.scrollLeft, document.documentElement.scrollLeft) ;
						fwin.style.width = pw + "px";
						fwin.style.height = ph + "px";
						fwin.style.posLeft =px ;
						fwin.style.posTop = py ;
						fwin.style.display="block";
					}


					f_hide = function(e)
					{
						if(window.event && window.event.srcElement && window.event.srcElement.tagName && window.event.srcElement.tagName.toLowerCase()=="select"){return true;}
						fwin.style.display="none";
					}
					f.hide = f_hide;
					document.attachEvent("onclick",f_hide);
					document.attachEvent("onkeydown",f_hide);

				}
				return f;
			}
		}

		function showMenu()
		{

			function selectMenu(obj)
			{
				var o = document.createElement("option");
				o.value = obj.value;
				//alert("val"+o.value+', text:'+obj.innerHTML+'selected:'+obj.selectedIndex);
				o.text = obj.innerHTML;
				o.text = o.text.replace('<NOBR>','');
				o.text = o.text.replace('</NOBR>','');
				//if there is no value, you should not try to set the innerHTML, as it screws up the empty selection ...
				if (o.value != '') o.innerHTML = o.text;
				while(el.options.length>0){el.options[0].removeNode(true);}
				el.appendChild(o);
				el.title = o.innerHTML;
				el.contentIndex = obj.selectedIndex ;
				el.menu.hide();
				if(el.onchange)
				{
					el.onchange();
				}
			}


			el.menu.show(0 , el.offsetHeight , 10, 10, el);
			var mb = el.menu.document.body;

			mb.style.cssText ="border:solid 1px black;margin:0;padding:0;overflow-y:auto;overflow-x:auto;background:white;font:12px Tahoma, sans-serif;";
			var t = el.contentHTML;
			//alert("1"+t);
			t = t.replace(/<select/gi,'<div');
			//alert("2"+t);
			t = t.replace(/<option/gi,'<span');
			//alert("3"+t);
			t = t.replace(/<\/option/gi,'</span');
			//alert("4"+t);
			t = t.replace(/<\/select/gi,'</div');
			t = t.replace(/<optgroup label=\"([\w\s\wäöüßÄÖÜ]*[^>])*">/gi,'<span value="i-opt-group-lable-i">$1</span>');
			t = t.replace(/<\/optgroup>/gi,'<span value="">---</span>');
			mb.innerHTML = t;
			//mb.innerHTML = "<div><span value='dd:ff'>gfgfg</span></div>";

			el.select = mb.all.tags("div")[0];
			el.select.style.cssText="list-style:none;margin:0;padding:0;";
			mb.options = el.select.getElementsByTagName("span");

			for(var i=0;i<mb.options.length;i++)
			{
				//alert('Value:'+mb.options[i].value + ', Text:'+ mb.options[i].innerHTML);
				mb.options[i].selectedIndex = i;
				mb.options[i].style.cssText = "list-style:none;margin:0;padding:1px 2px;width/**/:100%;white-space:nowrap;"
				if (mb.options[i].value != 'i-opt-group-lable-i') mb.options[i].style.cssText = mb.options[i].style.cssText + "cursor:hand;cursor:pointer;";
				mb.options[i].title =mb.options[i].innerHTML;
				mb.options[i].innerHTML ="<nobr>" + mb.options[i].innerHTML + "</nobr>";
				if (mb.options[i].value == 'i-opt-group-lable-i') mb.options[i].innerHTML = "<b><i>"+mb.options[i].innerHTML+"</b></i>";
				if (mb.options[i].value != 'i-opt-group-lable-i') mb.options[i].onmouseover = function()
				{
					if( mb.options.selected )
					{mb.options.selected.style.background="white";mb.options.selected.style.color="black";}
					mb.options.selected = this;
					this.style.background="#333366";this.style.color="white";
				}
				mb.options[i].onmouseout = function(){this.style.background="white";this.style.color="black";}
				if (mb.options[i].value != 'i-opt-group-lable-i') 
				{
					mb.options[i].onmousedown = function(){selectMenu(this); }
					mb.options[i].onkeydown = function(){selectMenu(this); }
				}
				if(i == el.contentIndex)
				{
					mb.options[i].style.background="#333366";
					mb.options[i].style.color="white";
					mb.options.selected = mb.options[i];
				}
			}
			var mw = Math.max( ( el.select.offsetWidth + 22 ), el.offsetWidth + 22 );
			mw = Math.max( mw, ( mb.scrollWidth+22) );
			var mh = mb.options.length * 15 + 8 ;
			var mx = (ie5)?-3:0;
			var docW = document.documentElement.offsetWidth ;
			var sideW = docW - el.getBoundingClientRect().left ;
			if (sideW < mw)
			{
				//alert(el.getBoundingClientRect().left+' Avail: '+docW+' Mx:'+mx+' My:'+my);
				// if it does not fit into the window on the right side, move it to the left
				mx = mx -mw + sideW-5;
			}
			var my = el.offsetHeight -2;
			my=my+5;
			var docH = document.documentElement.offsetHeight ;
			var bottomH = docH - el.getBoundingClientRect().bottom ;
			mh = Math.min(mh, Math.max(( docH - el.getBoundingClientRect().top - 50),100) );
			if(( bottomH < mh) )
			{
				mh = Math.max( (bottomH - 12),10);
				if( mh <100 )
				{
					my = -100 ;
				}
				mh = Math.max(mh,100);
			}
			self.focus();
			el.menu.show( mx , my , mw, mh , el);
			sync=null;
			if(mb.options.selected)
			{
				mb.scrollTop = mb.options.selected.offsetTop;
			}
			window.onresize = function(){el.menu.hide()};
		}

		function switchMenu()
		{
			if(event.keyCode)
			{
				if(event.keyCode==40){ el.contentIndex++ ;}
				else if(event.keyCode==38){ el.contentIndex--; }
			}
			else if(event.wheelDelta )
			{
				if (event.wheelDelta >= 120)
					el.contentIndex++ ;
				else if (event.wheelDelta <= -120)
					el.contentIndex-- ;
			}
			else{return true;}
			if( el.contentIndex > (el.contentOptions.length-1) ){ el.contentIndex =0;}
			else if (el.contentIndex<0){el.contentIndex = el.contentOptions.length-1 ;}
			var o = document.createElement("option");
			o.value = el.contentOptions[el.contentIndex].value;
			o.innerHTML = el.contentOptions[el.contentIndex].text;
			while(el.options.length>0){el.options[0].removeNode(true);}
			el.appendChild(o);
			el.title = o.innerHTML;
		}
		if(dropdown_menu_hack.menu ==null)
		{
			dropdown_menu_hack.menu = window.createPopup();
			document.attachEvent("onkeydown",dropdown_menu_hack.menu.hide);
		}
		el.menu = dropdown_menu_hack.menu ;
		el.contentOptions = new Array();
		el.contentIndex = el.selectedIndex;
		el.contentHTML = el.outerHTML;

		for(var i=0;i<el.options.length;i++)
		{

			el.contentOptions [el.contentOptions.length] =
			{
				"value": el.options[i].value,"text": el.options[i].innerHTML
			}
			if(!el.options[i].selected){el.options[i].removeNode(true);i--;};
		}
		el.onkeydown = switchMenu;
		el.onclick = showMenu;
		el.onmousewheel= switchMenu;
	}
}

function popup_resize()
{
	var widest = 0, highest = 0, smallest = window.innerWidth, width2grow, height2grow;
	// find all elements and check their size
	var divs = document.getElementsByTagName("div");
	for(var i = 0;i < divs.length;i++)
	{
	  if(divs[i].offsetWidth + divs[i].offsetLeft > widest)
		widest = divs[i].offsetWidth + divs[i].offsetLeft;
	  if(divs[i].offsetHeight + divs[i].offsetTop > highest)
		highest = divs[i].offsetHeight + divs[i].offsetTop;
	  if(divs[i].offsetLeft > 0 && divs[i].offsetLeft < smallest)
		smallest = divs[i].offsetLeft;
	}
	var tables = document.getElementsByTagName("table");
	for(var i = 0;i < tables.length;i++)
	{
	  if(tables[i].offsetWidth + tables[i].offsetLeft > widest)
		widest = tables[0].offsetWidth + tables[i].offsetLeft;
	  if(tables[i].offsetHeight + tables[i].offsetTop > highest)
		highest = tables[0].offsetHeight + tables[i].offsetTop;
	  if(tables[i].offsetLeft > 0 && tables[i].offsetLeft < smallest)
		smallest = tables[i].offsetLeft;
	}
	var labels = document.getElementsByTagName("label");
	for(var i = 0;i < labels.length;i++)
	{
	  if(labels[i].offsetWidth + labels[i].offsetLeft > widest)
		widest = labels[i].offsetWidth + labels[i].offsetLeft;
	  if(labels[i].offsetHeight + labels[i].offsetTop > highest)
		highest = labels[i].offsetHeight + labels[i].offsetTop;
	  if(labels[i].offsetLeft > 0 && labels[i].offsetLeft < smallest)
		smallest = labels[i].offsetLeft;
	}
	var inputs = document.getElementsByTagName("input");
	for(var i = 0;i < inputs.length;i++)
	{
	  if(inputs[i].offsetWidth + inputs[i].offsetLeft > widest)
		widest = inputs[i].offsetWidth + inputs[i].offsetLeft;
	  if(inputs[i].offsetHeight + inputs[i].offsetTop > highest)
		highest = inputs[i].offsetHeight + inputs[i].offsetTop;
	  if(inputs[i].offsetLeft > 0 && inputs[i].offsetLeft < smallest)
		smallest = inputs[i].offsetLeft;
	}
	// calculate the width and height the window has to grow
	width2grow = widest - window.innerWidth + (smallest != window.innerWidth ? Math.max(smallest, 10) : 10);
	height2grow = highest - window.innerHeight + 10;
	if(width2grow > 0 && window.outerWidth + width2grow < screen.availWidth * 0.8)
	{
	  window.moveBy(-(width2grow / 2), 0);
	  window.resizeBy(width2grow, 0);
	}
	if(height2grow > 0)
	{
	  if(window.outerHeight + height2grow > screen.availHeight)
	  {
		window.resizeTo(window.outerWidth, screen.availHeight);
	  }
	  else
	  {
		window.moveBy(0, -(height2grow / 2));
		window.resizeBy(0, height2grow);
	  }
	}
}
