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

// $Id$ 

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

/*UploadImage.prototype.zzzbuttonPress = function(editor, id) 
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

/*break;
  }
  };
 */


// Called when the user clicks on "InsertImage" button.  If an image is already
// there, it will just modify it's properties.
UploadImage.prototype.buttonPress = function(image) 
{
	//var doc = editor._doc;
	//var editor = this;	// for nested functions
	var outparam = null;

/*	var init = 
	{
		f_doctype      : editor.doctype,
		editor         : editor
	};
*/
/*	if (typeof image == "undefined") 
	{
		image = editor.getParentElement();
		if (image && !/^img$/i.test(image.tagName))
			image = null;
	}
*/
	Dialog(_editor_url+"plugins/UploadImage/popups/insert_image.php", function(param)
	{ 
	
			if (!param) 
			{	
				// user must have pressed Cancel
				return false;
			}

			var img = image;
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
//		alert('');

	}, outparam);
};
