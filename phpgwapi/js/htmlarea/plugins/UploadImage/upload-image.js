/**************************************************************************\
* eGroupWare - UploadImage-plugin for htmlArea in eGroupWare               *
* http://www.eGroupWare.org                                                *
* Written and (c) by Xiang Wei ZHUO <wei@zhuo.org>                         *
* Used code fragments from plugins by Mihai Bazon                          *
* Modified for eGW by and (c) by Pim Snel <pim@lingewoud.nl>               *
* --------------------------------------------                             *
* This program is free software; you can redistribute it and/or modify it  *
* under the terms of the GNU General Public License as published by the    *
* Free Software Foundation; version 2 of the License.                      *
\**************************************************************************/

// $id$ 

// FIXME: clean up code

function UploadImage(editor) {
	this.editor = editor;

	var cfg = editor.config;
//	cfg.fullPage = true;
	var tt = UploadImage.I18N;
	var self = this;

/*	cfg.registerButton("UploadImage", tt["Upload Image"], editor.imgURL("up_image.gif", "UploadImage"), false,
	function(editor, id) {
		self.buttonPress(editor, id);
	});
*/
	cfg.registerButton("UploadImage", tt["Upload Image"], editor.imgURL("up_image.gif", "UploadImage"), false,
			   function(editor, id) {
				   self.buttonPress();
			   });

	// add a new line in the toolbar
	cfg.toolbar[0].splice(29, 0, "separator");
	cfg.toolbar[0].splice(30, 0, "UploadImage");
};

UploadImage._pluginInfo = {
	name          : "UploadImage for eGroupWare",
	version       : "1.0",
	developer     : "Pim Snel",
	developer_url : "http://lingewoud.com",
	c_owner       : "Pim Snel, Xiang Wei ZHUO, Mihai Bazon",
	sponsor       : "Lingewoud bv., Netherlands",
	sponsor_url   : "http://lingewoud.com",
	license       : "GPL"
};

UploadImage.prototype.zzzbuttonPress = function(editor, id) 
{
	var self = this;
	switch (id) 
	{
	    case "UploadImage":
		var doc = editor._doc;
		var links = doc.getElementsByTagName("link");
		var style1 = '';
		var style2 = '';
		for (var i = links.length; --i >= 0;) 
		{
			var link = links[i];
			if (/stylesheet/i.test(link.rel)) 
			{
				if (/alternate/i.test(link.rel))
					style2 = link.href;
				else
					style1 = link.href;
			}
		}
		var title = doc.getElementsByTagName("title")[0];
		title = title ? title.innerHTML : '';

		var init = 
		{
			f_doctype      : editor.doctype,
			f_title        : title,
			f_body_bgcolor : HTMLArea._colorToRgb(doc.body.style.backgroundColor),
			f_body_fgcolor : HTMLArea._colorToRgb(doc.body.style.color),
			f_base_style   : style1,
			f_alt_style    : style2,

			editor         : editor
		};

		Dialog(_editor_url+"plugins/UploadImage/popups/insert_image.php", function(image) 
		{
			self._insertImage(image);
//			self.setDocProp(params);
//			alert(params[1]);
		}, init);

/*		editor._popupDialog("plugin://UploadImage/insert_image.php", function(params) {
				self.setDocProp(params);
				}, init);*/
	break;
	}
};



// Called when the user clicks on "InsertImage" button.  If an image is already
// there, it will just modify it's properties.
UploadImage.prototype.buttonPress = function(image) {
	
	var doc = editor._doc;
//	var editor = this;	// for nested functions
	var outparam = null;

	var init = 
	{
		f_doctype      : editor.doctype,
		editor         : editor
	};

	if (typeof image == "undefined") {
		image = editor.getParentElement();
		if (image && !/^img$/i.test(image.tagName))
			image = null;
	}

	if (image) outparam = {
		f_url    : HTMLArea.is_ie ? editor.stripBaseURL(image.src) : image.getAttribute("src"),
		f_alt    : image.alt,
		f_border : image.border,
		f_align  : image.align,
		f_vert   : image.vspace,
		f_horiz  : image.hspace
	};


	Dialog(_editor_url+"plugins/UploadImage/popups/insert_image.php", function(param){ 
	if (!param) {	// user must have pressed Cancel
	return false;
	}
		
		var img = image;
//		alert(param.f_url);
		if (!img) {
			var sel = editor._getSelection();
			var range = editor._createRange(sel);
			editor._doc.execCommand("insertimage", false, param.f_url);
			
			if (HTMLArea.is_ie) {
				img = range.parentElement();
				// wonder if this works...
				if (img.tagName.toLowerCase() != "img") {
					img = img.previousSibling;
				}
			} else {
				img = range.startContainer.previousSibling;
			}
		} else {
			img.src = param.f_url;
		}
		for (field in param) {
			var value = param[field];
			switch (field) {
				case "f_alt"    : img.alt	 = value; break;
				case "f_border" : img.border = parseInt(value || "0"); break;
				case "f_align"  : img.align	 = value; break;
				case "f_vert"   : img.vspace = parseInt(value || "0"); break;
				case "f_horiz"  : img.hspace = parseInt(value || "0"); break;
			}
		}
	}, outparam);
};





UploadImage.prototype.setDocProp = function(params) {
	var txt = "";
	var doc = this.editor._doc;
	var head = doc.getElementsByTagName("head")[0];
	var links = doc.getElementsByTagName("link");
	var style1 = null;
	var style2 = null;
	for (var i = links.length; --i >= 0;) {
		var link = links[i];
		if (/stylesheet/i.test(link.rel)) {
			if (/alternate/i.test(link.rel))
				style2 = link;
			else
				style1 = link;
		}
	}
	function createLink(alt) {
		var link = doc.createElement("link");
		link.rel = alt ? "alternate stylesheet" : "stylesheet";
		head.appendChild(link);
		return link;
	};

	if (!style1 && params.f_base_style)
		style1 = createLink(false);
	if (params.f_base_style)
		style1.href = params.f_base_style;
	else if (style1)
		head.removeChild(style1);

	if (!style2 && params.f_alt_style)
		style2 = createLink(true);
	if (params.f_alt_style)
		style2.href = params.f_alt_style;
	else if (style2)
		head.removeChild(style2);


	//cfg.registerButton("my-sample", "Class: sample", "ed_custom.gif", false,
			function testinsert(arg1,arg2) {
			if (HTMLArea.is_ie) {
			editor.insertHTML("<img src=\"arg1\" alt=\"\" />");
			var r = editor._doc.selection.createRange();
			r.move("character", -2);
			r.moveEnd("character", 2);
			r.select();
			} else { // Gecko/W3C compliant
			var n = editor._doc.createElement("img");
			n.className = "sample";
			editor.insertNodeAtSelection(n);
			var sel = editor._iframe.contentWindow.getSelection();
			sel.removeAllRanges();
			var r = editor._doc.createRange();
			r.setStart(n, 0);
			r.setEnd(n, 0);
			sel.addRange(r);
			}
			}

			testinsert(params[1]);

			for (var i in params) {

	

//		alert(params[i]);
		var val = params[i];
		switch (i) {
		    case "f_title":
			var title = doc.getElementsByTagName("title")[0];
			if (!title) {
				title = doc.createElement("title");
				head.appendChild(title);
			} else while (node = title.lastChild)
				title.removeChild(node);
			if (!HTMLArea.is_ie)
				title.appendChild(doc.createTextNode(val));
			else
				doc.title = val;
			break;
		    case "f_doctype":
			this.editor.setDoctype(val);
			break;
		    case "f_body_bgcolor":
			doc.body.style.backgroundColor = val;
			break;
		    case "f_body_fgcolor":
			doc.body.style.color = val;
			break;
		}
	}
};
