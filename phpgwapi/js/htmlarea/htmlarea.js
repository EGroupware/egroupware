// htmlArea v3.0 - Copyright (c) 2003-2004 dynarch.com
//                               2002-2003 interactivetools.com, inc.
// This copyright notice MUST stay intact for use (see license.txt).
//
// A free WYSIWYG editor replacement for <textarea> fields.
// For full source code and docs, visit http://www.interactivetools.com/
//
// Version 3.0 developed by Mihai Bazon.
//   http://dynarch.com/mishoo/
//
// $Id$

if (typeof _editor_url == "string") {
  // Leave exactly one backslash at the end of _editor_url
  _editor_url = _editor_url.replace(/\x2f*$/, '/');
} else {
  alert("WARNING: _editor_url is not set!  You should set this variable to the editor files path; it should preferably be an absolute path, like in '/htmlarea/', but it can be relative if you prefer.  Further we will try to load the editor files correctly but we'll probably fail.");
  _editor_url = '';
}

// make sure we have a language
if (typeof _editor_lang == "string") {
  _editor_lang = _editor_lang.toLowerCase();
} else {
  _editor_lang = "en";
}

var __htmlareas = [ ];

// browser identification
HTMLArea.agt = navigator.userAgent.toLowerCase();
HTMLArea.is_ie	   = ((HTMLArea.agt.indexOf("msie") != -1) && (HTMLArea.agt.indexOf("opera") == -1));
HTMLArea.is_opera  = (HTMLArea.agt.indexOf("opera") != -1);
HTMLArea.is_mac	   = (HTMLArea.agt.indexOf("mac") != -1);
HTMLArea.is_mac_ie = (HTMLArea.is_ie && HTMLArea.is_mac);
HTMLArea.is_win_ie = (HTMLArea.is_ie && !HTMLArea.is_mac);
HTMLArea.is_gecko  = (navigator.product == "Gecko");

// Creates a new HTMLArea object.  Tries to replace the textarea with the given
// ID with it.
function HTMLArea(textarea, config) {
  if (HTMLArea.checkSupportedBrowser()) {
    if (typeof config == "undefined") {
      this.config = new HTMLArea.Config();
    } else {
      this.config = config;
    }
    this._htmlArea = null;
    this._textArea = textarea;
    this._editMode = "wysiwyg";
    this.plugins = {};
    this._timerToolbar = null;
    this._timerUndo = null;
    this._undoQueue = new Array(this.config.undoSteps);
    this._undoPos = -1;
    this._customUndo = true;
    this._mdoc = document; // cache the document, we need it in plugins
    this.doctype = '';
    this.__htmlarea_id_num = __htmlareas.length;
    __htmlareas[this.__htmlarea_id_num] = this;

    this._notifyListeners = { };

    // Panels
    var panels = this._panels =
    {
      right:
      {
        on: true,
        div:    document.createElement('div'),
        panels: [ ]
      },
      left:
      {
        on: true,
        div:    document.createElement('div'),
        panels: [ ]
      },
      top:
      {
        on: true,
        div:    document.createElement('div'),
        panels: [ ]
      },
      bottom:
      {
        on: true,
        div:    document.createElement('div'),
        panels: [ ]
      }
    };

    for(var i in panels)
    {
      panels[i].div.className = 'panels ' + i;
    }
  }
};

HTMLArea.onload = function(){};
HTMLArea._scripts = [];

HTMLArea.loadScript = function(url, plugin) {
  if (plugin)
    url = HTMLArea.getPluginDir(plugin) + '/' + url;
  this._scripts.push(url);
};


HTMLArea.init = function() {

  var head = document.getElementsByTagName("head")[0];
  var current = 0;
  var savetitle = document.title;
  var evt = HTMLArea.is_ie ? "onreadystatechange" : "onload";
  function loadNextScript() {
    if (current > 0 && HTMLArea.is_ie &&
        !/loaded|complete/.test(window.event.srcElement.readyState))
      return;
    if (current < HTMLArea._scripts.length) {
      var url = HTMLArea._scripts[current++];
      document.title = "[HTMLArea: loading script " + current + "/" + HTMLArea._scripts.length + "]";
      var script = document.createElement("script");
      script.type = "text/javascript";
      script.src = url;
      script[evt] = loadNextScript;
      head.appendChild(script);
    } else {
      document.title = savetitle;
      HTMLArea.onload();
    }
  };
  loadNextScript();
};

HTMLArea.loadScript(_editor_url + "dialog.js");
HTMLArea.loadScript(_editor_url + "inline-dialog.js");
HTMLArea.loadScript(_editor_url + "popupwin.js");
HTMLArea.loadScript(_editor_url + "lang/" + _editor_lang + ".js");

// cache some regexps
HTMLArea.RE_tagName = /(<\/|<)\s*([^ \t\n>]+)/ig;
HTMLArea.RE_doctype = /(<!doctype((.|\n)*?)>)\n?/i;
HTMLArea.RE_head    = /<head>((.|\n)*?)<\/head>/i;
HTMLArea.RE_body    = /<body>((.|\n)*?)<\/body>/i;
HTMLArea.RE_Specials = /([\/\^$*+?.()|{}[\]])/g;

HTMLArea.Config = function () {
  var cfg = this;
  this.version = "3.0";

  this.width = "auto";
  this.height = "auto";

  // enable creation of a status bar?
  this.statusBar = true;

  // intercept ^V and use the HTMLArea paste command
  // If false, then passes ^V through to browser editor widget
  this.htmlareaPaste = false;

  // maximum size of the undo queue
  this.undoSteps = 20;

  // the time interval at which undo samples are taken
  this.undoTimeout = 500;	// 1/2 sec.

  // the next parameter specifies whether the toolbar should be included
  // in the size or not.
  this.sizeIncludesToolbar = true;

  // if true then HTMLArea will retrieve the full HTML, starting with the
  // <HTML> tag.
  this.fullPage = false;

  // style included in the iframe document
  this.pageStyle = "";

  // external stylesheets to load (REFERENCE THESE ABSOLUTELY)
  this.pageStyleSheets = [ ];

  // specify a base href for relative links
  this.baseHref  = null;

  // we can strip the base href out of relative links to leave them relative, reason for this
  //   especially if you don't specify a baseHref is that mozilla at least (& IE ?) will prefix
  //   the baseHref to any relative links to make them absolute, which isn't what you want most the time.
  this.stripBaseHref = true;

  // and we can strip the url of the editor page from named links (eg <a href="#top">...</a>)
  //  reason for this is that mozilla at least (and IE ?) prefixes location.href to any
  //  that don't have a url prefixing them
  this.stripSelfNamedAnchors = true;

  // sometimes we want to be able to replace some string in the html comng in and going out
  //  so that in the editor we use the "internal" string, and outside and in the source view
  //  we use the "external" string  this is useful for say making special codes for
  //  your absolute links, your external string might be some special code, say "{server_url}"
  //  an you say that the internal represenattion of that should be http://your.server/
  this.specialReplacements = { }; // { 'external_string' : 'internal_string' }

  // set to true if you want Word code to be cleaned upon Paste
  this.killWordOnPaste = true;

  // enable the 'Target' field in the Make Link dialog
  this.makeLinkShowsTarget = true;

  // BaseURL included in the iframe document
  this.baseURL = document.baseURI || document.URL;
  if (this.baseURL && this.baseURL.match(/(.*)\/([^\/]+)/))
    this.baseURL = RegExp.$1 + "/";

  // URL-s
  this.imgURL = "images/";
  this.popupURL = "popups/";

  // remove tags (these have to be a regexp, or null if this functionality is not desired)
  this.htmlRemoveTags = null;

  /** CUSTOMIZING THE TOOLBAR
   * -------------------------
   *
   * It is recommended that you customize the toolbar contents in an
   * external file (i.e. the one calling HTMLArea) and leave this one
   * unchanged.  That's because when we (InteractiveTools.com) release a
   * new official version, it's less likely that you will have problems
   * upgrading HTMLArea.
   */
  this.toolbar =
  [
    ["popupeditor","separator"],
    ["formatblock","fontname","fontsize","bold","italic","underline","strikethrough","separator"],
    ["forecolor","hilitecolor","textindicator","separator"],
    ["subscript","superscript"],
    ["linebreak","justifyleft","justifycenter","justifyright","justifyfull","separator"],
    ["insertorderedlist","insertunorderedlist","outdent","indent","separator"],
    ["inserthorizontalrule","createlink","insertimage","inserttable","separator"],
    ["killword","removeformat","toggleborders","lefttoright", "righttoleft", "separator","htmlmode","about"]
  ];

  // Width of the "Right Side" panel, when present
  this.panel_dimensions =
  {
    left:   '200px', // Width
    right:  '200px',
    top:    '100px', // Height
    bottom: '100px'
  }

  /*
  this.toolbar = [
    [ "fontname", "space",
      "fontsize", "space",
      "formatblock", "space",
      "bold", "italic", "underline", "strikethrough", "separator",
      "subscript", "superscript", "separator",
      "copy", "cut", "paste", "space", "undo", "redo", "space", "removeformat", "killword" ],

    [ "justifyleft", "justifycenter", "justifyright", "justifyfull", "separator",
      "lefttoright", "righttoleft", "separator",
      "orderedlist", "unorderedlist", "outdent", "indent", "separator",
      "forecolor", "hilitecolor", "separator",
      "inserthorizontalrule", "createlink", "insertimage", "inserttable", "toggleborders", "htmlmode", "separator",
      "popupeditor", "separator", "showhelp", "about" ]
  ];
  */

  this.fontname = {
    "&mdash; font &mdash;":         '',
    "Arial":	   'arial,helvetica,sans-serif',
    "Courier New":	   'courier new,courier,monospace',
    "Georgia":	   'georgia,times new roman,times,serif',
    "Tahoma":	   'tahoma,arial,helvetica,sans-serif',
    "Times New Roman": 'times new roman,times,serif',
    "Verdana":	   'verdana,arial,helvetica,sans-serif',
    "impact":	   'impact',
    "WingDings":	   'wingdings'
  };

  this.fontsize = {
    "&mdash; size &mdash;"  : "",
    "1 (8 pt)" : "1",
    "2 (10 pt)": "2",
    "3 (12 pt)": "3",
    "4 (14 pt)": "4",
    "5 (18 pt)": "5",
    "6 (24 pt)": "6",
    "7 (36 pt)": "7"
  };

  this.formatblock = {
    "&mdash; format &mdash;"  : "",
    "Heading 1": "h1",
    "Heading 2": "h2",
    "Heading 3": "h3",
    "Heading 4": "h4",
    "Heading 5": "h5",
    "Heading 6": "h6",
    "Normal"   : "p",
    "Address"  : "address",
    "Formatted": "pre"
  };

  this.customSelects = {};

  function cut_copy_paste(e, cmd, obj) {
    e.execCommand(cmd);
  };

  this.debug = true;

  // ADDING CUSTOM BUTTONS: please read below!
  // format of the btnList elements is "ID: [ ToolTip, Icon, Enabled in text mode?, ACTION ]"
  //    - ID: unique ID for the button.  If the button calls document.execCommand
  //	    it's wise to give it the same name as the called command.
  //    - ACTION: function that gets called when the button is clicked.
  //              it has the following prototype:
  //                 function(editor, buttonName)
  //              - editor is the HTMLArea object that triggered the call
  //              - buttonName is the ID of the clicked button
  //              These 2 parameters makes it possible for you to use the same
  //              handler for more HTMLArea objects or for more different buttons.
  //    - ToolTip: default tooltip, for cases when it is not defined in the -lang- file (HTMLArea.I18N)
  //    - Icon: path to an icon image file for the button
  //            OR; you can use an 18x18 block of a larger image by supllying an array
  //            that has three elemtents, the first is the larger image, the second is the column
  //            the third is the row.  The ros and columns numbering starts at 0 but there is
  //            a header row and header column which have numbering to make life easier.
  //            See images/buttons_main.gif to see how it's done.
  //    - Enabled in text mode: if false the button gets disabled for text-only mode; otherwise enabled all the time.
  this.btnList = {
    /*
    bold:          [ "Bold", "ed_format_bold.gif", false, function(e) {e.execCommand("bold");} ],
    italic:        [ "Italic", "ed_format_italic.gif", false, function(e) {e.execCommand("italic");} ],
    underline:     [ "Underline", "ed_format_underline.gif", false, function(e) {e.execCommand("underline");} ],
    strikethrough: [ "Strikethrough", "ed_format_strike.gif", false, function(e) {e.execCommand("strikethrough");} ],
    subscript:     [ "Subscript", "ed_format_sub.gif", false, function(e) {e.execCommand("subscript");} ],
    superscript:   [ "Superscript", "ed_format_sup.gif", false, function(e) {e.execCommand("superscript");} ],
    justifyleft: [ "Justify Left", "ed_align_left.gif", false, function(e) {e.execCommand("justifyleft");} ],
    justifycenter: [ "Justify Center", "ed_align_center.gif", false, function(e){e.execCommand("justifycenter");}],
    justifyright: [ "Justify Right", "ed_align_right.gif", false, function(e) {e.execCommand("justifyright");} ],
    justifyfull: [ "Justify Full", "ed_align_justify.gif", false, function(e) {e.execCommand("justifyfull");} ],

    orderedlist: [ "Ordered List", "ed_list_num.gif", false, function(e) {e.execCommand("insertorderedlist");} ],
    unorderedlist: [ "Bulleted List", "ed_list_bullet.gif", false, function(e) {e.execCommand("insertunorderedlist");} ],
    insertorderedlist: [ "Ordered List", "ed_list_num.gif", false, function(e) {e.execCommand("insertorderedlist");} ],
    insertunorderedlist: [ "Bulleted List", "ed_list_bullet.gif", false, function(e) {e.execCommand("insertunorderedlist");} ],

    outdent: [ "Decrease Indent", "ed_indent_less.gif", false, function(e) {e.execCommand("outdent");} ],
    indent: [ "Increase Indent", "ed_indent_more.gif", false, function(e) {e.execCommand("indent");} ],
    forecolor: [ "Font Color", "ed_color_fg.gif", false, function(e) {e.execCommand("forecolor");} ],
    hilitecolor: [ "Background Color", "ed_color_bg.gif", false, function(e) {e.execCommand("hilitecolor");} ],

    undo: [ "Undoes your last action", "ed_undo.gif", false, function(e) {e.execCommand("undo");} ],
    redo: [ "Redoes your last action", "ed_redo.gif", false, function(e) {e.execCommand("redo");} ],
    cut: [ "Cut selection", "ed_cut.gif", false, cut_copy_paste ],
    copy: [ "Copy selection", "ed_copy.gif", false, cut_copy_paste ],
    paste: [ "Paste from clipboard", "ed_paste.gif", false, cut_copy_paste ],

    */
    bold:          [ "Bold",   ["ed_buttons_main.gif",3,2], false, function(e) {e.execCommand("bold");} ],
    italic:        [ "Italic", ["ed_buttons_main.gif",2,2], false, function(e) {e.execCommand("italic");} ],
    underline:     [ "Underline", ["ed_buttons_main.gif",2,0], false, function(e) {e.execCommand("underline");} ],
    strikethrough: [ "Strikethrough", ["ed_buttons_main.gif",3,0], false, function(e) {e.execCommand("strikethrough");} ],
    subscript:     [ "Subscript", ["ed_buttons_main.gif",3,1], false, function(e) {e.execCommand("subscript");} ],
    superscript:   [ "Superscript", ["ed_buttons_main.gif",2,1], false, function(e) {e.execCommand("superscript");} ],

    justifyleft:   [ "Justify Left", ["ed_buttons_main.gif",0,0], false, function(e) {e.execCommand("justifyleft");} ],
    justifycenter: [ "Justify Center", ["ed_buttons_main.gif",1,1], false, function(e){e.execCommand("justifycenter");}],
    justifyright: [ "Justify Right", ["ed_buttons_main.gif",1,0], false, function(e) {e.execCommand("justifyright");} ],
    justifyfull: [ "Justify Full", ["ed_buttons_main.gif",0,1], false, function(e) {e.execCommand("justifyfull");} ],


    orderedlist: [ "Ordered List", ["ed_buttons_main.gif",0,3], false, function(e) {e.execCommand("insertorderedlist");} ],
    unorderedlist: [ "Bulleted List", ["ed_buttons_main.gif",1,3], false, function(e) {e.execCommand("insertunorderedlist");} ],
    insertorderedlist: [ "Ordered List", ["ed_buttons_main.gif",0,3], false, function(e) {e.execCommand("insertorderedlist");} ],
    insertunorderedlist: [ "Bulleted List", ["ed_buttons_main.gif",1,3], false, function(e) {e.execCommand("insertunorderedlist");} ],

    outdent: [ "Decrease Indent", ["ed_buttons_main.gif",1,2], false, function(e) {e.execCommand("outdent");} ],
    indent: [ "Increase Indent",["ed_buttons_main.gif",0,2], false, function(e) {e.execCommand("indent");} ],
    forecolor: [ "Font Color", ["ed_buttons_main.gif",3,3], false, function(e) {e.execCommand("forecolor");} ],
    hilitecolor: [ "Background Color", ["ed_buttons_main.gif",2,3], false, function(e) {e.execCommand("hilitecolor");} ],

    undo: [ "Undoes your last action", ["ed_buttons_main.gif",4,2], false, function(e) {e.execCommand("undo");} ],
    redo: [ "Redoes your last action", ["ed_buttons_main.gif",5,2], false, function(e) {e.execCommand("redo");} ],
    cut: [ "Cut selection", ["ed_buttons_main.gif",5,0], false, cut_copy_paste ],
    copy: [ "Copy selection", ["ed_buttons_main.gif",4,0], false, cut_copy_paste ],
    paste: [ "Paste from clipboard", ["ed_buttons_main.gif",4,1], false, cut_copy_paste ],



    inserthorizontalrule: [ "Horizontal Rule", ["ed_buttons_main.gif",6,0], false, function(e) {e.execCommand("inserthorizontalrule");} ],
    createlink: [ "Insert Web Link", ["ed_buttons_main.gif",6,1], false, function(e) {e._createLink();} ],
    insertimage: [ "Insert/Modify Image", ["ed_buttons_main.gif",6,3], false, function(e) {e.execCommand("insertimage");} ],
    inserttable: [ "Insert Table", ["ed_buttons_main.gif",6,2], false, function(e) {e.execCommand("inserttable");} ],


    htmlmode: [ "Toggle HTML Source", ["ed_buttons_main.gif",7,0], true, function(e) {e.execCommand("htmlmode");} ],
    toggleborders: [ "Toggle Borders", ["ed_buttons_main.gif",7,2], false, function(e) { e._toggleBorders() } ],
    print:         [ "Print document", ["ed_buttons_main.gif",8,1], false, function(e) {e._iframe.contentWindow.print();} ],
    popupeditor: [ "Enlarge Editor", "fullscreen_maximize.gif", true,
      function(e, objname, obj)
      {
        e.execCommand("popupeditor");
      } ],
    about: [ "About this editor", ["ed_buttons_main.gif",8,2], true, function(e) {e.execCommand("about");} ],
    showhelp: [ "Help using editor", ["ed_buttons_main.gif",9,2], true, function(e) {e.execCommand("showhelp");} ],

    splitblock:    [ "Split Block", "ed_splitblock.gif", false, function(e) {e._splitBlock();} ],
    lefttoright: [ "Direction left to right", ["ed_buttons_main.gif",0,4], false, function(e) {e.execCommand("lefttoright");} ],
    righttoleft: [ "Direction right to left", ["ed_buttons_main.gif",1,4], false, function(e) {e.execCommand("righttoleft");} ],

    wordclean:     [ "MS Word Cleaner", ["ed_buttons_main.gif",5,3], false, function(e) {e._wordClean();} ],
    clearfonts:    [ "Clear Inline Font Specifications", ["ed_buttons_main.gif",5,4], false, function(e) {e._clearFonts();} ],
    removeformat:  [ "Remove formatting", ["ed_buttons_main.gif",4,4], false, function(e) {e.execCommand("removeformat");} ],
    killword:      [ "Clear MSOffice tags", ["ed_buttons_main.gif",4,3], false, function(e) {e.execCommand("killword");} ]

  };
  /* ADDING CUSTOM BUTTONS
   * ---------------------
   *
   * It is recommended that you add the custom buttons in an external
   * file and leave this one unchanged.  That's because when we
   * (InteractiveTools.com) release a new official version, it's less
   * likely that you will have problems upgrading HTMLArea.
   *
   * Example on how to add a custom button when you construct the HTMLArea:
   *
   *   var editor = new HTMLArea("your_text_area_id");
   *   var cfg = editor.config; // this is the default configuration
   *   cfg.btnList["my-hilite"] =
   *	[ function(editor) { editor.surroundHTML('<span style="background:yellow">', '</span>'); }, // action
   *	  "Highlight selection", // tooltip
   *	  "my_hilite.gif", // image
   *	  false // disabled in text mode
   *	];
   *   cfg.toolbar.push(["linebreak", "my-hilite"]); // add the new button to the toolbar
   *
   * An alternate (also more convenient and recommended) way to
   * accomplish this is to use the registerButton function below.
   */
  // initialize tooltips from the I18N module and generate correct image path
  for (var i in this.btnList) {
    var btn = this.btnList[i];
    if(typeof btn[1] != 'string')
    {
      btn[1][0] = _editor_url + this.imgURL + btn[1][0];
    }
    else
    {
      btn[1] = _editor_url + this.imgURL + btn[1];
    }
    try
    {
      if (typeof HTMLArea.I18N.tooltips[i] != "undefined")
      {
        btn[0] = HTMLArea.I18N.tooltips[i];
      }
    }
    catch(e) { }
  }
};

/** Helper function: register a new button with the configuration.  It can be
 * called with all 5 arguments, or with only one (first one).  When called with
 * only one argument it must be an object with the following properties: id,
 * tooltip, image, textMode, action.  Examples:
 *
 * 1. config.registerButton("my-hilite", "Hilite text", "my-hilite.gif", false, function(editor) {...});
 * 2. config.registerButton({
 *      id       : "my-hilite",      // the ID of your button
 *      tooltip  : "Hilite text",    // the tooltip
 *      image    : "my-hilite.gif",  // image to be displayed in the toolbar
 *      textMode : false,            // disabled in text mode
 *      action   : function(editor) { // called when the button is clicked
 *                   editor.surroundHTML('<span class="hilite">', '</span>');
 *                 },
 *      context  : "p"               // will be disabled if outside a <p> element
 *    });
 */
HTMLArea.Config.prototype.registerButton = function(id, tooltip, image, textMode, action, context) {
  var the_id;
  if (typeof id == "string") {
    the_id = id;
  } else if (typeof id == "object") {
    the_id = id.id;
  } else {
    alert("ERROR [HTMLArea.Config::registerButton]:\ninvalid arguments");
    return false;
  }
  // check for existing id
  if (typeof this.customSelects[the_id] != "undefined") {
    // alert("WARNING [HTMLArea.Config::registerDropdown]:\nA dropdown with the same ID already exists.");
  }
  if (typeof this.btnList[the_id] != "undefined") {
    // alert("WARNING [HTMLArea.Config::registerDropdown]:\nA button with the same ID already exists.");
  }
  switch (typeof id) {
      case "string": this.btnList[id] = [ tooltip, image, textMode, action, context ]; break;
      case "object": this.btnList[id.id] = [ id.tooltip, id.image, id.textMode, id.action, id.context ]; break;
  }
};

HTMLArea.prototype.registerPanel = function(side, object)
{
  if(!side) side = 'right';
  var panel = this.addPanel(side);
  if(object)
  {
    object.drawPanelIn(panel);
  }
}

/** The following helper function registers a dropdown box with the editor
 * configuration.  You still have to add it to the toolbar, same as with the
 * buttons.  Call it like this:
 *
 * FIXME: add example
 */
HTMLArea.Config.prototype.registerDropdown = function(object) {
  // check for existing id
  if (typeof this.customSelects[object.id] != "undefined") {
    // alert("WARNING [HTMLArea.Config::registerDropdown]:\nA dropdown with the same ID already exists.");
  }
  if (typeof this.btnList[object.id] != "undefined") {
    // alert("WARNING [HTMLArea.Config::registerDropdown]:\nA button with the same ID already exists.");
  }
  this.customSelects[object.id] = object;
};

/** Call this function to remove some buttons/drop-down boxes from the toolbar.
 * Pass as the only parameter a string containing button/drop-down names
 * delimited by spaces.  Note that the string should also begin with a space
 * and end with a space.  Example:
 *
 *   config.hideSomeButtons(" fontname fontsize textindicator ");
 *
 * It's useful because it's easier to remove stuff from the defaul toolbar than
 * create a brand new toolbar ;-)
 */
HTMLArea.Config.prototype.hideSomeButtons = function(remove) {
  var toolbar = this.toolbar;
  for (var i = toolbar.length; --i >= 0;) {
    var line = toolbar[i];
    for (var j = line.length; --j >= 0; ) {
      if (remove.indexOf(" " + line[j] + " ") >= 0) {
        var len = 1;
        if (/separator|space/.test(line[j + 1])) {
          len = 2;
        }
        line.splice(j, len);
      }
    }
  }
};

/** Helper function: replace all TEXTAREA-s in the document with HTMLArea-s. */
HTMLArea.replaceAll = function(config) {
  var tas = document.getElementsByTagName("textarea");
  for (var i = tas.length; i > 0; (new HTMLArea(tas[--i], config)).generate());
};

/** Helper function: replaces the TEXTAREA with the given ID with HTMLArea. */
HTMLArea.replace = function(id, config) {
  var ta = HTMLArea.getElementById("textarea", id);
  return ta ? (new HTMLArea(ta, config)).generate() : null;
};

// Creates the toolbar and appends it to the _htmlarea
HTMLArea.prototype._createToolbar = function () {
  var editor = this;	// to access this in nested functions

  var toolbar = document.createElement("div");
  this._toolbar = toolbar;
  toolbar.className = "toolbar";
  toolbar.unselectable = "1";

  var tb_row = null;
  var tb_objects = new Object();
  this._toolbarObjects = tb_objects;

  // creates a new line in the toolbar
  function newLine() {
    var table = document.createElement("table");
    table.border = "0px";
    table.cellSpacing = "0px";
    table.cellPadding = "0px";
    toolbar.appendChild(table);
    // TBODY is required for IE, otherwise you don't see anything
    // in the TABLE.
    var tb_body = document.createElement("tbody");
    table.appendChild(tb_body);
    tb_row = document.createElement("tr");
    tb_body.appendChild(tb_row);
  }; // END of function: newLine
  // init first line
  newLine();

  // updates the state of a toolbar element.  This function is member of
  // a toolbar element object (unnamed objects created by createButton or
  // createSelect functions below).
  function setButtonStatus(id, newval) {
    var oldval = this[id];
    var el = this.element;
    if (oldval != newval) {
      switch (id) {
          case "enabled":
        if (newval) {
          HTMLArea._removeClass(el, "buttonDisabled");
          el.disabled = false;
        } else {
          HTMLArea._addClass(el, "buttonDisabled");
          el.disabled = true;
        }
        break;
          case "active":
        if (newval) {
          HTMLArea._addClass(el, "buttonPressed");
        } else {
          HTMLArea._removeClass(el, "buttonPressed");
        }
        break;
      }
      this[id] = newval;
    }
  }; // END of function: setButtonStatus

  // this function will handle creation of combo boxes.  Receives as
  // parameter the name of a button as defined in the toolBar config.
  // This function is called from createButton, above, if the given "txt"
  // doesn't match a button.
  function createSelect(txt) {
    var options = null;
    var el = null;
    var cmd = null;
    var customSelects = editor.config.customSelects;
    var context = null;
    var tooltip = "";
    switch (txt) {
        case "fontsize":
        case "fontname":
        case "formatblock":
      // the following line retrieves the correct
      // configuration option because the variable name
      // inside the Config object is named the same as the
      // button/select in the toolbar.  For instance, if txt
      // == "formatblock" we retrieve config.formatblock (or
      // a different way to write it in JS is
      // config["formatblock"].
      options = editor.config[txt];
      cmd = txt;
      break;
        default:
      // try to fetch it from the list of registered selects
      cmd = txt;
      var dropdown = customSelects[cmd];
      if (typeof dropdown != "undefined") {
        options = dropdown.options;
        context = dropdown.context;
        if (typeof dropdown.tooltip != "undefined") {
          tooltip = dropdown.tooltip;
        }
      } else {
        alert("ERROR [createSelect]:\nCan't find the requested dropdown definition");
      }
      break;
    }
    if (options) {
      el = document.createElement("select");
      el.title = tooltip;
      var obj = {
        name	: txt, // field name
        element : el,	// the UI element (SELECT)
        enabled : true, // is it enabled?
        text	: false, // enabled in text mode?
        cmd	: cmd, // command ID
        state	: setButtonStatus, // for changing state
        context : context
      };
      tb_objects[txt] = obj;
      for (var i in options) {
        var op = document.createElement("option");
        op.innerHTML = i;
        op.value = options[i];
        el.appendChild(op);
      }
      HTMLArea._addEvent(el, "change", function () {
        editor._comboSelected(el, txt);
      });
    }
    return el;
  }; // END of function: createSelect

  // appends a new button to toolbar
  function createButton(txt) {
    // the element that will be created
    var el = null;
    var btn = null;
    switch (txt) {
        case "separator":
      el = document.createElement("div");
      el.className = "separator";
      break;
        case "space":
      el = document.createElement("div");
      el.className = "space";
      break;
        case "linebreak":
      newLine();
      return false;
        case "textindicator":
      el = document.createElement("div");
      el.appendChild(document.createTextNode("A"));
      el.className = "indicator";
      el.title = HTMLArea.I18N.tooltips.textindicator;
      var obj = {
        name	: txt, // the button name (i.e. 'bold')
        element : el, // the UI element (DIV)
        enabled : true, // is it enabled?
        active	: false, // is it pressed?
        text	: false, // enabled in text mode?
        cmd	: "textindicator", // the command ID
        state	: setButtonStatus // for changing state
      };
      tb_objects[txt] = obj;
      break;
        default:
      btn = editor.config.btnList[txt];
    }
    if (!el && btn) {
      el = document.createElement("a");
      el.style.display = 'block';
      el.href = 'javascript:void(0)';
      el.style.textDecoration = 'none';
      el.title = btn[0];
      el.className = "button";
      // let's just pretend we have a button object, and
      // assign all the needed information to it.
      var obj = {
        name	: txt, // the button name (i.e. 'bold')
        element : el, // the UI element (DIV)
        enabled : true, // is it enabled?
        active	: false, // is it pressed?
        text	: btn[2], // enabled in text mode?
        cmd	: btn[3], // the command ID
        state	: setButtonStatus, // for changing state
        context : btn[4] || null // enabled in a certain context?
      };
      tb_objects[txt] = obj;
      // handlers to emulate nice flat toolbar buttons
      /* -- This is now handled by a :hover pseudo class in htmlarea.css
      HTMLArea._addEvent(el, "mouseover", function () {
        if (obj.enabled) {
          HTMLArea._addClass(el, "buttonHover");
        }
      });
      */
      HTMLArea._addEvent(el, "mouseout", function () {
        if (obj.enabled) with (HTMLArea) {
          //_removeClass(el, "buttonHover");
          _removeClass(el, "buttonActive");
          (obj.active) && _addClass(el, "buttonPressed");
        }
      });

      HTMLArea._addEvent(el, "mousedown", function (ev) {
        if (obj.enabled) with (HTMLArea) {
          _addClass(el, "buttonActive");
          _removeClass(el, "buttonPressed");
          _stopEvent(is_ie ? window.event : ev);
        }
      });
      // when clicked, do the following:
      HTMLArea._addEvent(el, "click", function (ev) {
        if (obj.enabled) with (HTMLArea) {
          _removeClass(el, "buttonActive");
          //_removeClass(el, "buttonHover");
          if(HTMLArea.is_gecko)
          {
            editor.activateEditor();
          }
          obj.cmd(editor, obj.name, obj);
          _stopEvent(is_ie ? window.event : ev);
        }
      });

      var i_contain = null;
      if(HTMLArea.is_ie && ((!document.compatMode) || (document.compatMode && document.compatMode == "BackCompat")))
      {
        i_contain = document.createElement('span');
      }
      else
      {
        i_contain = document.createElement('div');
        i_contain.style.position = 'relative';
      }

      i_contain.style.overflow = 'hidden';
      i_contain.style.width = "18px";
      i_contain.style.height = "18px";

      var img = document.createElement("img");
      if(typeof btn[1] == 'string')
      {
        img.src = btn[1];
        img.style.width = "18px";
        img.style.height = "18px";
      }
      else
      {
        img.src = btn[1][0];
        img.style.position = 'relative';
        img.style.top  = btn[1][2] ? ('-' + (18 * (btn[1][2] + 1)) + 'px') : '-18px';
        img.style.left = btn[1][1] ? ('-' + (18 * (btn[1][1] + 1)) + 'px') : '-18px';
      }
      i_contain.appendChild(img);
      el.appendChild(i_contain);

      obj.imgel = img;
      obj.swapImage = function(newimg)
      {
        if(typeof newimg != 'string')
        {
          img.src = newimg[0];
          img.style.position = 'relative';
          img.style.top  = newimg[2] ? ('-' + (18 * (newimg[2] + 1)) + 'px') : '-18px';
          img.style.left = newimg[1] ? ('-' + (18 * (newimg[1] + 1)) + 'px') : '-18px';
        }
        else
        {
          obj.imgel.src = newimg;
          img.style.top = '0px';
          img.style.left = '0px';
        }
      }

    } else if (!el) {
      el = createSelect(txt);
    }
    if (el) {
      var tb_cell = document.createElement("td");
      tb_row.appendChild(tb_cell);
      tb_cell.appendChild(el);
    } else {
      alert("FIXME: Unknown toolbar item: " + txt);
    }
    return el;
  };

  var first = true;
  for (var i = 0; i < this.config.toolbar.length; ++i) {
    if (!first) {
      // createButton("linebreak");
    } else {
      first = false;
    }
    var group = this.config.toolbar[i];
    for (var j = 0; j < group.length; ++j) {
      var code = group[j];
      if (/^([IT])\[(.*?)\]/.test(code)) {
        // special case, create text label
        var l7ed = RegExp.$1 == "I"; // localized?
        var label = RegExp.$2;
        if (l7ed) {
          label = HTMLArea.I18N.custom[label];
        }
        var tb_cell = document.createElement("td");
        tb_row.appendChild(tb_cell);
        tb_cell.className = "label";
        tb_cell.innerHTML = label;
      } else {
        createButton(code);
      }
    }
  }

  this._htmlArea.appendChild(toolbar);
};

HTMLArea.prototype._createStatusBar = function() {
  var statusbar = document.createElement("div");
  statusbar.className = "statusBar";
  this._htmlArea.appendChild(statusbar);
  this._statusBar = statusbar;
  // statusbar.appendChild(document.createTextNode(HTMLArea.I18N.msg["Path"] + ": "));
  // creates a holder for the path view
  div = document.createElement("span");
  div.className = "statusBarTree";
  div.innerHTML = HTMLArea.I18N.msg["Path"] + ": ";
  //div.innerHTML = "Path: ";
  this._statusBarTree = div;
  this._statusBar.appendChild(div);
  if (!this.config.statusBar) {
    // disable it...
    statusbar.style.display = "none";
  }
};

// Creates the HTMLArea object and replaces the textarea with it.
HTMLArea.prototype.generate = function ()
{
  var editor = this;	// we'll need "this" in some nested functions

  // get the textarea
  var textarea = this._textArea;
  if (typeof textarea == "string")
  {
    this._textArea = textarea = HTMLArea.getElementById("textarea", textarea);
  }
  this._ta_size =
  {
    w: textarea.offsetWidth,
    h: textarea.offsetHeight
  };

  // create the editor framework
  var htmlarea = document.createElement("div");
  htmlarea.className = "htmlarea";
  this._htmlArea = htmlarea;
  if(this.config.width != 'auto' && this.config.width != 'toolbar')
  {
    htmlarea.style.width = this.config.width;
  }

  // insert the editor before the textarea.
  textarea.parentNode.insertBefore(htmlarea, textarea);

  // creates & appends the toolbar
  this._createToolbar();

  // Create containing div (to hold editor and stylist)
  var innerEditor = document.createElement('div');
  htmlarea.appendChild(innerEditor);
  innerEditor.style.position = 'relative';
  this.innerEditor = innerEditor;

  // extract the textarea and insert it into the htmlarea
  textarea.parentNode.removeChild(textarea);
  innerEditor.appendChild(textarea);

  // create the IFRAME & add to container
  var iframe = document.createElement("iframe");
  innerEditor.appendChild(iframe);
  iframe.src = _editor_url + "popups/blank.html";
  this._iframe = iframe;


  // - I don't think this is required, see the .htmlarea iframe in htmlarea.css
  // remove the default border as it keeps us from computing correctly
  // the sizes.  (somebody tell me why doesn't this work in IE)
  // if (!HTMLArea.is_ie) {
  //   iframe.style.borderWidth = "0px";
  // }


  // Add the panels
  for(var i in this._panels)
  {
    innerEditor.appendChild(this._panels[i].div);
  }

  // creates & appends the status bar
  this._createStatusBar();


  // Set up event listeners for saving the iframe content to the textarea
  if (textarea.form) {
    // we have a form, on submit get the HTMLArea content and
    // update original textarea.
    var f = textarea.form;
    if (typeof f.onsubmit == "function") {
      var funcref = f.onsubmit;
      if (typeof f.__msh_prevOnSubmit == "undefined") {
        f.__msh_prevOnSubmit = [];
      }
      f.__msh_prevOnSubmit.push(funcref);
    }
    f.onsubmit = function() {
      editor._textArea.value = editor.outwardHtml(editor.getHTML());
      var a = this.__msh_prevOnSubmit;
      // call previous submit methods if they were there.
      if (typeof a != "undefined") {
        for (var i = a.length; --i >= 0;) {
          a[i]();
        }
      }
    };
    if (typeof f.onreset == "function") {
      var funcref = f.onreset;
      if (typeof f.__msh_prevOnReset == "undefined") {
        f.__msh_prevOnReset = [];
      }
      f.__msh_prevOnReset.push(funcref);
    }
    f.onreset = function() {
      editor.setHTML(editor._textArea.value);
      editor.updateToolbar();
      var a = this.__msh_prevOnReset;
      // call previous reset methods if they were there.
      if (typeof a != "undefined") {
        for (var i = a.length; --i >= 0;) {
          a[i]();
        }
      }
    };
  }

  // add a handler for the "back/forward" case -- on body.unload we save
  // the HTML content into the original textarea.
  try {
    HTMLArea._addEvent(window, 'unload', function() {textarea.value = editor.outwardHtml(editor.getHTML());} );
  } catch(e) {};

  // Hide textarea
  textarea.style.display = "none";

  // Calculate the starting size, EXCLUDING THE TOOLBAR & STATUS BAR (always)
  var height = null;
  var width  = null;

  switch(this.config.height)
  {
    // "auto" means the same height as the original textarea
    case 'auto' : { height = parseInt(this._ta_size.h);    break; }
    // otherwise we expect it to be a PIXEL height
    default     : { height = parseInt(this.config.height); break; }
  }

  switch(this.config.width)
  {
    // toolbar means the width is the same as the toolbar
    case 'toolbar': {width = parseInt(this._toolbar.offsetWidth); break; }
    // auto means the same as the textarea
    case 'auto'   : {width = parseInt(this._ta_size.w);          break; }
    // otherwise it is expected to be a PIXEL width
    default       : {width = parseInt(this.config.width);        break; }
  }

  if (this.config.sizeIncludesToolbar)
  {
    // substract toolbar height
    height -= this._toolbar.offsetHeight;
    height -= this._statusBar.offsetHeight;
  }

  // Minimal size = 100x100
  width  = Math.max(width, 100);
  height = Math.max(height,100);

  this.setInnerSize(width,height);
  this.notifyOn('panel_change',function(){editor.setInnerSize();});


  // IMPORTANT: we have to allow Mozilla a short time to recognize the
  // new frame.  Otherwise we get a stupid exception.

  setTimeout(function() { editor.initIframe()}, 50);
};

  /** Size the htmlArea according to the available space
   *   Width and Height include toolbar!
   **/

  HTMLArea.prototype.getInnerSize = function()
  {
    return this._innerSize;
  }

  HTMLArea.prototype.setInnerSize = function(width, height)
  {
    if(typeof width == 'undefined' || width == null)
    {
      width  = this._innerSize.width;
    }

    if(typeof height == 'undefined' || height == null)
    {
      height  = this._innerSize.height;
    }

    this._innerSize = {'width':width,'height':height};

    var editorWidth  = width;
    var editorHeight = height;
    var editorLeft   = 0;
    var editorTop    = 0;
    var panels = this._panels;

    var panel = panels.right;
    if(panel.on && panel.panels.length && HTMLArea.hasDisplayedChildren(panel.div))
    {
      panel.div.style.position = 'absolute';
      panel.div.style.width    = parseInt(this.config.panel_dimensions.right) + (HTMLArea.ie_ie ? -1 : -2) + 'px';
      panel.div.style.height   = height + (HTMLArea.is_ie ? -1 : -1) + 'px';
      panel.div.style.top      = '0px';
      panel.div.style.right    = (HTMLArea.is_ie ? 1 : 2) + 'px';
      panel.div.style.padding  = "0px";
      panel.div.style.overflow = "auto";
      panel.div.style.display  = 'block';
      editorWidth -= parseInt(this.config.panel_dimensions.right) + (HTMLArea.is_ie ? 2 : 0);
    }
    else
    {
      panel.div.style.display  = 'none';
    }

    var panel = panels.left;
    if(panel.on && panel.panels.length && HTMLArea.hasDisplayedChildren(panel.div))
    {
      panel.div.style.position = 'absolute';
      panel.div.style.width    = parseInt(this.config.panel_dimensions.left) + (HTMLArea.ie_ie ? -1 : -1) + 'px';
      panel.div.style.height   = height + (HTMLArea.is_ie ? -1 : -1) + 'px';
      panel.div.style.top      = '0px';
      panel.div.style.left     = (HTMLArea.is_ie ? 0 : 0) + 'px';
      panel.div.style.padding  = "0px";
      panel.div.style.overflow = "auto";
      panel.div.style.display  = "block";
      editorWidth -= parseInt(this.config.panel_dimensions.left) + (HTMLArea.is_ie ? 2 : 0);
      editorLeft   = parseInt(this.config.panel_dimensions.left) + (HTMLArea.is_ie ? 2 : 0) + 'px';
    }
    else
    {
      panel.div.style.display  = 'none';
    }

    var panel = panels.top;
    if(panel.on && panel.panels.length && HTMLArea.hasDisplayedChildren(panel.div))
    {
      panel.div.style.position = 'absolute';
      panel.div.style.top      = '0px';
      panel.div.style.left     = '0px';
      panel.div.style.width    = width  + 'px';
      panel.div.style.height   = parseInt(this.config.panel_dimensions.top) + 'px';
      panel.div.style.padding  = "0px";
      panel.div.style.overflow = "auto";
      panel.div.style.display  = "block";
      editorHeight -= parseInt(this.config.panel_dimensions.top);
      editorTop     = parseInt(this.config.panel_dimensions.top) + 'px';
    }
    else
    {
      panel.div.style.display  = 'none';
    }

    var panel = panels.bottom;
    if(panel.on && panel.panels.length && HTMLArea.hasDisplayedChildren(panel.div))
    {
      panel.div.style.position = 'absolute';
      panel.div.style.bottom   = '0px';
      panel.div.style.left     = '0px';
      panel.div.style.width    = width  + 'px';
      panel.div.style.height   = parseInt(this.config.panel_dimensions.bottom) + 'px';
      panel.div.style.padding  = "0px";
      panel.div.style.overflow = "auto";
      panel.div.style.display  = "block";
      editorHeight -= parseInt(this.config.panel_dimensions.bottom);
    }
    else
    {
      panel.div.style.display  = 'none';
    }

    // Set the dimensions of the container
    this.innerEditor.style.width  = width  + 'px';
    this.innerEditor.style.height = height + 'px';
    this.innerEditor.style.position = 'relative';

    // and the iframe
    this._iframe.style.width  = editorWidth  + 'px';
    this._iframe.style.height = editorHeight + 'px';
    this._iframe.style.position = 'absolute';
    this._iframe.style.left = editorLeft;
    this._iframe.style.top  = editorTop;


    // the editor including the toolbar now have the same size as the
    // original textarea.. which means that we need to reduce that a bit.
    this._textArea.style.width  = editorWidth  + 'px';
    this._textArea.style.height = editorHeight + 'px';
    this._textArea.style.position = 'absolute';
    this._textArea.style.left = editorLeft;
    this._textArea.style.top  = editorTop;

    this.notifyOf('resize', {'width':width,'height':height,'editorWidth':editorWidth,'editorHeight':editorHeight,'editorTop':editorTop,'editorLeft':editorLeft});
  }

  HTMLArea.prototype.addPanel = function(side)
  {
    var div = document.createElement('div');
    div.side = side;
    HTMLArea.addClasses(div, 'panel');
    this._panels[side].panels.push(div);
    this._panels[side].div.appendChild(div);

    this.notifyOf('panel_change', {'action':'add','panel':div});

    return div;
  }

  HTMLArea.prototype.removePanel = function(panel)
  {
    panel.side.div.removeChild(panel);
    var clean = [ ];
    for(var i = 0; i < panel.side.panels.length; i++)
    {
      if(panel.side.panels[i] != panel)
      {
        clean.push(panel.side.panels[i]);
      }
    }
    panel.side.panels = clean;
    this.notifyOf('panel_change', {'action':'add','panel':panel});
  }

  HTMLArea.prototype.hidePanel = function(panel)
  {
    panel.style.display = 'none';
    this.notifyOf('panel_change', {'action':'hide','panel':panel});
  }

  HTMLArea.prototype.showPanel = function(panel)
  {
    panel.style.display = '';
    this.notifyOf('panel_change', {'action':'show','panel':panel});
  }

  HTMLArea.prototype.hidePanels = function(sides)
  {
    if(typeof sides == 'undefined')
    {
      sides = ['left','right','top','bottom'];
    }

    var reShow = [];
    for(var i = 0; i < sides.length;i++)
    {
      if(this._panels[sides[i]].on)
      {
        reShow.push(sides[i]);
        this._panels[sides[i]].on = false;
      }
    }
    this.notifyOf('panel_change', {'action':'multi_hide','sides':sides});
  }

  HTMLArea.prototype.showPanels = function(sides)
  {
    if(typeof sides == 'undefined')
    {
      sides = ['left','right','top','bottom'];
    }

    var reHide = [];
    for(var i = 0; i < sides.length;i++)
    {
      if(!this._panels[sides[i]].on)
      {
        reHide.push(sides[i]);
        this._panels[sides[i]].on = true;
      }
    }
    this.notifyOf('panel_change', {'action':'multi_show','sides':sides});
  }

  HTMLArea.objectProperties = function(obj)
  {
    var props = [ ];
    for(var x in obj)
    {
      props[props.length] = x;
    }
    return props;
  }

  HTMLArea.prototype.activateEditor = function()
  {
    if (HTMLArea.is_gecko && this._doc.designMode != 'on')
    {
      try{HTMLArea.last_on.designMode = 'off';} catch(e) { }
      if(this._iframe.style.display == 'none')
      {
        this._iframe.style.display = '';
        this._doc.designMode = 'on';
        this._iframe.style.display = 'none';
      }
      else
      {

        this._doc.designMode = 'on';
      }
    }
    else
    {
      this._doc.body.contentEditable = true;
    }
    HTMLArea.last_on = this._doc;
  }

  HTMLArea.prototype.deactivateEditor = function()
  {
    if(HTMLArea.is_gecko && this._doc.designMode == 'on')
    {
      this._doc.designMode = 'off';
      HTMLArea.last_on = null;
    }
    else
    {
      this._doc.body.contentEditable = false;
    }
  }

  HTMLArea.prototype.initIframe = function()
  {
    var doc = null;
    var editor = this;
    try
    {
      doc = editor._iframe.contentWindow.document;
      if (!doc) {
        // Try again..
        // FIXME: don't know what else to do here.  Normally
        // we'll never reach this point.
        if (HTMLArea.is_gecko) {
          setTimeout(function() { editor.initIframe()}, 50);
          return false;
        } else {
          alert("ERROR: IFRAME can't be initialized.");
        }
      }
    }
    catch(e)
    {
      setTimeout(function() { editor.initIframe()}, 50);
    }

    if (!editor.config.fullPage) {
      doc.open();
      var html = "<html>\n";
      html += "<head>\n";
      if(typeof editor.config.baseHref != 'undefined')
      {
        html += "<base href=\"" + editor.config.baseHref + "\"/>";
      }
      html += "<style title=\"table borders\">"
           + ".htmtableborders, .htmtableborders td, .htmtableborders th {border : 1px dashed lightgrey ! important;} \n"
           + "</style>\n";
      html += "<style>"
           + editor.config.pageStyle + "\n"
           + "html, body { border: 0px; } \n"
           + "span.macro, span.macro ul, span.macro div, span.macro p {background : #CCCCCC;}\n"
           + "</style>\n";
      if(typeof editor.config.pageStyleSheets !== 'undefined')
      {
        for(style_i = 0; style_i < editor.config.pageStyleSheets.length; style_i++)
        {
          if(editor.config.pageStyleSheets[style_i].length > 0)
              html += "<link rel=\"stylesheet\" type=\"text/css\" href=\"" + editor.config.pageStyleSheets[style_i] + "\">";
            //html += "<style> @import url('" + editor.config.pageStyleSheets[style_i] + "'); </style>\n";
        }
      }
      html += "</head>\n";
      html += "<body>\n";
      html +=   editor.inwardHtml(editor._textArea.value);
      html += "</body>\n";
      html += "</html>";
      doc.write(html);
      doc.close();
    } else {
      var html = editor.inwardHtml(editor._textArea.value);
      if (html.match(HTMLArea.RE_doctype)) {
        editor.setDoctype(RegExp.$1);
        html = html.replace(HTMLArea.RE_doctype, "");
      }
      doc.open();
      doc.write(html);
      doc.close();
    }

    this._doc = doc;

    // If we have multiple editors some bug in Mozilla makes some lose editing ability
    if(HTMLArea.is_gecko)
    {
      HTMLArea._addEvents(
        editor._iframe.contentWindow,
        ["mousedown"],
        function() { editor.activateEditor();  }
      );
    }
    else
    {
      editor.activateEditor();
    }

    // editor.focusEditor();
    // intercept some events; for updating the toolbar & keyboard handlers
    HTMLArea._addEvents
      (doc, ["keydown", "keypress", "mousedown", "mouseup", "drag"],
       function (event) {
         return editor._editorEvent(HTMLArea.is_ie ? editor._iframe.contentWindow.event : event);
       });

    // check if any plugins have registered refresh handlers
    for (var i in editor.plugins) {
      var plugin = editor.plugins[i].instance;
      if (typeof plugin.onGenerate == "function")
        plugin.onGenerate();
      if (typeof plugin.onGenerateOnce == "function") {
        plugin.onGenerateOnce();
        plugin.onGenerateOnce = null;
      }
    }

    if(typeof editor._onGenerate == "function") { editor._onGenerate();}

    setTimeout(function() {
      editor.updateToolbar();
    }, 250);

    if (typeof editor.onGenerate == "function")
      editor.onGenerate();
  }

// Switches editor mode; parameter can be "textmode" or "wysiwyg".  If no
// parameter was passed this function toggles between modes.
HTMLArea.prototype.setMode = function(mode) {
  if (typeof mode == "undefined") {
    mode = ((this._editMode == "textmode") ? "wysiwyg" : "textmode");
  }
  switch (mode) {
    case "textmode":
    {
      var html = this.outwardHtml(this.getHTML());
      this._textArea.value = html;

      // Hide the iframe
      this.deactivateEditor();
      this._iframe.style.display   = 'none';
      this._textArea.style.display = "block";
      if (this.config.statusBar)
      {
        this._statusBar.innerHTML = HTMLArea.I18N.msg["TEXT_MODE"];
      }

      this.notifyOf('modechange', {'mode':'text'});
      break;
    }

    case "wysiwyg":
    {
      var html = this.inwardHtml(this.getHTML());
      this.deactivateEditor();
      if (!this.config.fullPage)
      {
        this._doc.body.innerHTML = html;
      }
      else
      {
        this.setFullHTML(html);
      }
      this._iframe.style.display   = '';
      this._textArea.style.display = "none";
      this.activateEditor();
      if (this.config.statusBar)
      {
        this._statusBar.innerHTML = '';
        this._statusBar.appendChild(this._statusBarTree);
      }

      this.notifyOf('modechange', {'mode':'wysiwyg'});
      break;
    }

    default:
    {
      alert("Mode <" + mode + "> not defined!");
      return false;
    }
  }
  this._editMode = mode;
  // this.focusEditor();

  for (var i in this.plugins) {
    var plugin = this.plugins[i].instance;
    if (typeof plugin.onMode == "function") plugin.onMode(mode);
  }
};

HTMLArea.prototype.setFullHTML = function(html) {
  var save_multiline = RegExp.multiline;
  RegExp.multiline = true;
  if (html.match(HTMLArea.RE_doctype)) {
    this.setDoctype(RegExp.$1);
    html = html.replace(HTMLArea.RE_doctype, "");
  }
  RegExp.multiline = save_multiline;
  if (!HTMLArea.is_ie) {
    if (html.match(HTMLArea.RE_head))
      this._doc.getElementsByTagName("head")[0].innerHTML = RegExp.$1;
    if (html.match(HTMLArea.RE_body))
      this._doc.getElementsByTagName("body")[0].innerHTML = RegExp.$1;
  } else {
    var html_re = /<html>((.|\n)*?)<\/html>/i;
    html = html.replace(html_re, "$1");
    this._doc.open();
    this._doc.write(html);
    this._doc.close();
    this.activateEditor();
    // this._doc.body.contentEditable = true;
    return true;
  }
};

/***************************************************
 *  Category: PLUGINS
 ***************************************************/

// Create the specified plugin and register it with this HTMLArea
HTMLArea.prototype.registerPlugin = function() {
  var plugin = arguments[0];
  var args = [];
  for (var i = 1; i < arguments.length; ++i)
    args.push(arguments[i]);
  this.registerPlugin2(plugin, args);
};

// this is the variant of the function above where the plugin arguments are
// already packed in an array.  Externally, it should be only used in the
// full-screen editor code, in order to initialize plugins with the same
// parameters as in the opener window.
HTMLArea.prototype.registerPlugin2 = function(plugin, args) {
  if (typeof plugin == "string")
    plugin = eval(plugin);
  if (typeof plugin == "undefined") {
    /* FIXME: This should never happen. But why does it do? */
    return false;
  }
  var obj = new plugin(this, args);
  if (obj) {
    var clone = {};
    var info = plugin._pluginInfo;
    for (var i in info)
      clone[i] = info[i];
    clone.instance = obj;
    clone.args = args;
    this.plugins[plugin._pluginInfo.name] = clone;
  } else
    alert("Can't register plugin " + plugin.toString() + ".");
};

// static function that loads the required plugin and lang file, based on the
// language loaded already for HTMLArea.  You better make sure that the plugin
// _has_ that language, otherwise shit might happen ;-)
HTMLArea.getPluginDir = function(pluginName) {
  return _editor_url + "plugins/" + pluginName;
};

HTMLArea.loadPlugin = function(pluginName) {
  var dir = this.getPluginDir(pluginName);
  var plugin = pluginName.replace(/([a-z])([A-Z])([a-z])/g,
          function (str, l1, l2, l3) {
            return l1 + "-" + l2.toLowerCase() + l3;
          }).toLowerCase() + ".js";
  var plugin_file = dir + "/" + plugin;
  var plugin_lang = dir + "/lang/" + _editor_lang + ".js";
  document.write("<script type='text/javascript' src='" + plugin_file + "'></script>");
  document.write("<script type='text/javascript' src='" + plugin_lang + "'></script>");
  //this.loadScript(plugin_file);
  //this.loadScript(plugin_lang);
};

HTMLArea.loadStyle = function(style, plugin) {
  var url = _editor_url || '';
  if (typeof plugin != "undefined") {
    url += "plugins/" + plugin + "/";
  }
  url += style;
  if (/^\//.test(style))
    url = style;
  var head = document.getElementsByTagName("head")[0];
  var link = document.createElement("link");
  link.rel = "stylesheet";
  link.href = url;
  head.appendChild(link);
  //document.write("<style type='text/css'>@import url(" + url + ");</style>");
};
HTMLArea.loadStyle(typeof _editor_css == "string" ? _editor_css : "htmlarea.css");

/***************************************************
 *  Category: EDITOR UTILITIES
 ***************************************************/

HTMLArea.prototype.debugTree = function() {
  var ta = document.createElement("textarea");
  ta.style.width = "100%";
  ta.style.height = "20em";
  ta.value = "";
  function debug(indent, str) {
    for (; --indent >= 0;)
      ta.value += " ";
    ta.value += str + "\n";
  };
  function _dt(root, level) {
    var tag = root.tagName.toLowerCase(), i;
    var ns = HTMLArea.is_ie ? root.scopeName : root.prefix;
    debug(level, "- " + tag + " [" + ns + "]");
    for (i = root.firstChild; i; i = i.nextSibling)
      if (i.nodeType == 1)
        _dt(i, level + 2);
  };
  _dt(this._doc.body, 0);
  document.body.appendChild(ta);
};

HTMLArea.getInnerText = function(el) {
  var txt = '', i;
  for (i = el.firstChild; i; i = i.nextSibling) {
    if (i.nodeType == 3)
      txt += i.data;
    else if (i.nodeType == 1)
      txt += HTMLArea.getInnerText(i);
  }
  return txt;
};

HTMLArea.prototype._wordClean = function() {
  var
    editor = this,
    stats = {
      empty_tags : 0,
      mso_class  : 0,
      mso_style  : 0,
      mso_xmlel  : 0,
      orig_len   : this._doc.body.innerHTML.length,
      T          : (new Date()).getTime()
    },
    stats_txt = {
      empty_tags : "Empty tags removed: ",
      mso_class  : "MSO class names removed: ",
      mso_style  : "MSO inline style removed: ",
      mso_xmlel  : "MSO XML elements stripped: "
    };
  function showStats() {
    var txt = "HTMLArea word cleaner stats: \n\n";
    for (var i in stats)
      if (stats_txt[i])
        txt += stats_txt[i] + stats[i] + "\n";
    txt += "\nInitial document length: " + stats.orig_len + "\n";
    txt += "Final document length: " + editor._doc.body.innerHTML.length + "\n";
    txt += "Clean-up took " + (((new Date()).getTime() - stats.T) / 1000) + " seconds";
    alert(txt);
  };
  function clearClass(node) {
    var newc = node.className.replace(/(^|\s)mso.*?(\s|$)/ig, ' ');
    if (newc != node.className) {
      node.className = newc;
      if (!/\S/.test(node.className)) {
        node.removeAttribute("className");
        ++stats.mso_class;
      }
    }
  };
  function clearStyle(node) {
    var declarations = node.style.cssText.split(/\s*;\s*/);
    for (var i = declarations.length; --i >= 0;)
      if (/^mso|^tab-stops/i.test(declarations[i]) ||
          /^margin\s*:\s*0..\s+0..\s+0../i.test(declarations[i])) {
        ++stats.mso_style;
        declarations.splice(i, 1);
      }
    node.style.cssText = declarations.join("; ");
  };
  function stripTag(el) {
    if (HTMLArea.is_ie)
      el.outerHTML = HTMLArea.htmlEncode(el.innerText);
    else {
      var txt = document.createTextNode(HTMLArea.getInnerText(el));
      el.parentNode.insertBefore(txt, el);
      el.parentNode.removeChild(el);
    }
    ++stats.mso_xmlel;
  };
  function checkEmpty(el) {
    if (/^(a|span|b|strong|i|em|font)$/i.test(el.tagName) &&
        !el.firstChild) {
      el.parentNode.removeChild(el);
      ++stats.empty_tags;
    }
  };
  function parseTree(root) {
    var tag = root.tagName.toLowerCase(), i, next;
    if ((HTMLArea.is_ie && root.scopeName != 'HTML') || (!HTMLArea.is_ie && /:/.test(tag))) {
      stripTag(root);
      return false;
    } else {
      clearClass(root);
      clearStyle(root);
      for (i = root.firstChild; i; i = next) {
        next = i.nextSibling;
        if (i.nodeType == 1 && parseTree(i))
          checkEmpty(i);
      }
    }
    return true;
  };
  parseTree(this._doc.body);
  // showStats();
  // this.debugTree();
  // this.setHTML(this.getHTML());
  // this.setHTML(this.getInnerHTML());
  // this.forceRedraw();
  this.updateToolbar();
};

HTMLArea.prototype._clearFonts = function() {
  var D = this.getInnerHTML();

  if(confirm('Would you like to clear font typefaces?'))
  {
    D = D.replace(/face="[^"]*"/gi, '');
    D = D.replace(/font-family:[^;}"']+;?/gi, '');
  }

  if(confirm('Would you like to clear font sizes?'))
  {
    D = D.replace(/size="[^"]*"/gi, '');
    D = D.replace(/font-size:[^;}"']+;?/gi, '');
  }

  if(confirm('Would you like to clear font colours?'))
  {
    D = D.replace(/color="[^"]*"/gi, '');
    D = D.replace(/([^-])color:[^;}"']+;?/gi, '$1');
  }

  D = D.replace(/(style|class)="\s*"/gi, '');
  D = D.replace(/<(font|span)\s*>/gi, '');
  this.setHTML(D);
  this.updateToolbar();
}

HTMLArea.prototype._splitBlock = function()
{
  this._doc.execCommand('formatblock', false, '<div>');
}

HTMLArea.prototype.forceRedraw = function() {
  this._doc.body.style.visibility = "hidden";
  this._doc.body.style.visibility = "visible";
  // this._doc.body.innerHTML = this.getInnerHTML();
};

// focuses the iframe window.  returns a reference to the editor document.
HTMLArea.prototype.focusEditor = function() {
  switch (this._editMode) {
      // notice the try { ... } catch block to avoid some rare exceptions in FireFox
      // (perhaps also in other Gecko browsers). Manual focus by user is required in
        // case of an error. Somebody has an idea?
      case "wysiwyg" :
      try
      {
        // We don't want to focus the field unless at least one field has been activated.
        if(HTMLArea.last_on)
        {
          this.activateEditor();
          this._iframe.contentWindow.focus();
        }

      } catch (e) {} break;
      case "textmode": try { this._textArea.focus() } catch (e) {} break;
      default	   : alert("ERROR: mode " + this._editMode + " is not defined");
  }
  return this._doc;
};

// takes a snapshot of the current text (for undo)
HTMLArea.prototype._undoTakeSnapshot = function() {
  ++this._undoPos;
  if (this._undoPos >= this.config.undoSteps) {
    // remove the first element
    this._undoQueue.shift();
    --this._undoPos;
  }
  // use the fasted method (getInnerHTML);
  var take = true;
  var txt = this.getInnerHTML();
  if (this._undoPos > 0)
    take = (this._undoQueue[this._undoPos - 1] != txt);
  if (take) {
    this._undoQueue[this._undoPos] = txt;
  } else {
    this._undoPos--;
  }
};

HTMLArea.prototype.undo = function() {
  if (this._undoPos > 0) {
    var txt = this._undoQueue[--this._undoPos];
    if (txt) this.setHTML(txt);
    else ++this._undoPos;
  }
};

HTMLArea.prototype.redo = function() {
  if (this._undoPos < this._undoQueue.length - 1) {
    var txt = this._undoQueue[++this._undoPos];
    if (txt) this.setHTML(txt);
    else --this._undoPos;
  }
};

HTMLArea.prototype.disableToolbar = function(except)
{
  if(typeof except == 'undefined')
  {
    except = [ ];
  }
  else if(typeof except != 'object')
  {
    except = [except];
  }

  for (var i in this._toolbarObjects)
  {
    var btn = this._toolbarObjects[i];
    if(except.contains(i))
    {
      continue;
    }
    btn.state("enabled", false);
  }
}

HTMLArea.prototype.enableToolbar = function()
{
  this.updateToolbar();
}

if(!Array.prototype.contains)
{
  Array.prototype.contains = function(needle)
  {
   var haystack = this;
   for(var i = 0; i < haystack.length; i++)
    {
      if(needle == haystack[i]) return true;
    }

    return false;
  }

}


// updates enabled/disable/active state of the toolbar elements
HTMLArea.prototype.updateToolbar = function(noStatus) {
  var doc = this._doc;
  var text = (this._editMode == "textmode");
  var ancestors = null;
  if (!text) {
    ancestors = this.getAllAncestors();
    if (this.config.statusBar && !noStatus) {
      this._statusBarTree.innerHTML = HTMLArea.I18N.msg["Path"] + ": "; // clear
      for (var i = ancestors.length; --i >= 0;) {
        var el = ancestors[i];
        if (!el) {
          // hell knows why we get here; this
          // could be a classic example of why
          // it's good to check for conditions
          // that are impossible to happen ;-)
          continue;
        }
        var a = document.createElement("a");
        a.href = "javascript:void(0)";
        a.el = el;
        a.editor = this;
        a.onclick = function() {
          this.blur();
          this.editor.selectNodeContents(this.el);
          this.editor.updateToolbar(true);
          return false;
        };
        a.oncontextmenu = function() {
          // TODO: add context menu here
          this.blur();
          var info = "Inline style:\n\n";
          info += this.el.style.cssText.split(/;\s*/).join(";\n");
          alert(info);
          return false;
        };
        var txt = el.tagName.toLowerCase();
        a.title = el.style.cssText;
        if (el.id) {
          txt += "#" + el.id;
        }
        if (el.className) {
          txt += "." + el.className;
        }
        a.appendChild(document.createTextNode(txt));
        this._statusBarTree.appendChild(a);
        if (i != 0) {
          this._statusBarTree.appendChild(document.createTextNode(String.fromCharCode(0xbb)));
        }
      }
    }
  }

  for (var i in this._toolbarObjects) {
    var btn = this._toolbarObjects[i];
    var cmd = i;
    var inContext = true;
    if (btn.context && !text) {
      inContext = false;
      var context = btn.context;
      var attrs = [];
      if (/(.*)\[(.*?)\]/.test(context)) {
        context = RegExp.$1;
        attrs = RegExp.$2.split(",");
      }
      context = context.toLowerCase();
      var match = (context == "*");
      for (var k = 0; k < ancestors.length; ++k) {
        if (!ancestors[k]) {
          // the impossible really happens.
          continue;
        }
        if (match || (ancestors[k].tagName.toLowerCase() == context)) {
          inContext = true;
          for (var ka = 0; ka < attrs.length; ++ka) {
            if (!eval("ancestors[k]." + attrs[ka])) {
              inContext = false;
              break;
            }
          }
          if (inContext) {
            break;
          }
        }
      }
    }
    btn.state("enabled", (!text || btn.text) && inContext);
    if (typeof cmd == "function") {
      continue;
    }
    // look-it-up in the custom dropdown boxes
    var dropdown = this.config.customSelects[cmd];
    if ((!text || btn.text) && (typeof dropdown != "undefined")) {
      dropdown.refresh(this);
      continue;
    }
    switch (cmd) {
        case "fontname":
        case "fontsize":
      if (!text) try {
        var value = ("" + doc.queryCommandValue(cmd)).toLowerCase();
        if (!value) {
          btn.element.selectedIndex = 0;
          break;
        }
        // HACK -- retrieve the config option for this
        // combo box.  We rely on the fact that the
        // variable in config has the same name as
        // button name in the toolbar.
        var options = this.config[cmd];
        var k = 0;
        for (var j in options) {
          // FIXME: the following line is scary.
          if ((j.toLowerCase() == value) ||
              (options[j].substr(0, value.length).toLowerCase() == value)) {
            btn.element.selectedIndex = k;
            throw "ok";
          }
          ++k;
        }
        btn.element.selectedIndex = 0;
      } catch(e) {};

      // It's better to search for the format block by tag name from the
      //  current selection upwards, because IE has a tendancy to return
      //  things like 'heading 1' for 'h1', which breaks things if you want
      //  to call your heading blocks 'header 1'.  Stupid MS.
      case "formatblock"  :
        var blocks = [ ];
        for(var i in this.config['formatblock'])
        {
          blocks[blocks.length] = this.config['formatblock'][i];
        }

        var deepestAncestor = this._getFirstAncestor(this._getSelection(), blocks);
        if(deepestAncestor)
        {
          for(var x= 0; x < blocks.length; x++)
          {
            if(blocks[x].toLowerCase() == deepestAncestor.tagName.toLowerCase())
            {
              btn.element.selectedIndex = x;
            }
          }
        }
        else
        {
          btn.element.selectedIndex = 0;
        }
        break;

      break;
        case "textindicator":
      if (!text) {
        try {with (btn.element.style) {
          backgroundColor = HTMLArea._makeColor(
            doc.queryCommandValue(HTMLArea.is_ie ? "backcolor" : "hilitecolor"));
          if (/transparent/i.test(backgroundColor)) {
            // Mozilla
            backgroundColor = HTMLArea._makeColor(doc.queryCommandValue("backcolor"));
          }
          color = HTMLArea._makeColor(doc.queryCommandValue("forecolor"));
          fontFamily = doc.queryCommandValue("fontname");
          fontWeight = doc.queryCommandState("bold") ? "bold" : "normal";
          fontStyle = doc.queryCommandState("italic") ? "italic" : "normal";
        }} catch (e) {
          // alert(e + "\n\n" + cmd);
        }
      }
      break;
        case "htmlmode": btn.state("active", text); break;
        case "lefttoright":
        case "righttoleft":
      var el = this.getParentElement();
      while (el && !HTMLArea.isBlockElement(el))
        el = el.parentNode;
      if (el)
        btn.state("active", (el.style.direction == ((cmd == "righttoleft") ? "rtl" : "ltr")));
      break;
        default:
      cmd = cmd.replace(/(un)?orderedlist/i, "insert$1orderedlist");
      try {
        btn.state("active", (!text && doc.queryCommandState(cmd)));
      } catch (e) {}
    }
  }
  // take undo snapshots
  if (this._customUndo && !this._timerUndo) {
    this._undoTakeSnapshot();
    var editor = this;
    this._timerUndo = setTimeout(function() {
      editor._timerUndo = null;
    }, this.config.undoTimeout);
  }

  // Insert a space in certain locations, this is just to make editing a little
  // easier (to "get out of" tags), it's not essential.
  if(HTMLArea.is_gecko)
  {
    var s = this._getSelection();
    if(s && s.isCollapsed && s.anchorNode && s.anchorNode.nodeType == 3)
    {
      if(s.anchorOffset == s.anchorNode.length)
      {
        if(HTMLArea.isBlockElement(s.anchorNode.parentNode))
        {
          if(s.anchorNode.data != '\xA0' && !s.anchorNode.nextSibling)
          {
            s.anchorNode.parentNode.insertBefore(this._doc.createTextNode('\xA0'), s.anchorNode.nextSibling);
          }
        }
        else
        {
          if(!s.anchorNode.parentNode.nextSibling)
          {
            s.anchorNode.parentNode.parentNode.insertBefore(this._doc.createTextNode('\xA0'), s.anchorNode.parentNode.nextSibling);
          }
        }
      }
    }
  }

  // check if any plugins have registered refresh handlers
  for (var i in this.plugins) {
    var plugin = this.plugins[i].instance;
    if (typeof plugin.onUpdateToolbar == "function")
      plugin.onUpdateToolbar();
  }

};

/** Returns a node after which we can insert other nodes, in the current
 * selection.  The selection is removed.  It splits a text node, if needed.
 */
HTMLArea.prototype.insertNodeAtSelection = function(toBeInserted) {
  if (!HTMLArea.is_ie) {
    var sel = this._getSelection();
    var range = this._createRange(sel);
    // remove the current selection
    sel.removeAllRanges();
    range.deleteContents();
    var node = range.startContainer;
    var pos = range.startOffset;
    switch (node.nodeType) {
        case 3: // Node.TEXT_NODE
      // we have to split it at the caret position.
      if (toBeInserted.nodeType == 3) {
        // do optimized insertion
        node.insertData(pos, toBeInserted.data);
        range = this._createRange();
        range.setEnd(node, pos + toBeInserted.length);
        range.setStart(node, pos + toBeInserted.length);
        sel.addRange(range);
      } else {
        node = node.splitText(pos);
        var selnode = toBeInserted;
        if (toBeInserted.nodeType == 11 /* Node.DOCUMENT_FRAGMENT_NODE */) {
          selnode = selnode.firstChild;
        }
        node.parentNode.insertBefore(toBeInserted, node);
        this.selectNodeContents(selnode);
        this.updateToolbar();
      }
      break;
        case 1: // Node.ELEMENT_NODE
      var selnode = toBeInserted;
      if (toBeInserted.nodeType == 11 /* Node.DOCUMENT_FRAGMENT_NODE */) {
        selnode = selnode.firstChild;
      }
      node.insertBefore(toBeInserted, node.childNodes[pos]);
      this.selectNodeContents(selnode);
      this.updateToolbar();
      break;
    }
  } else {
    return null;	// this function not yet used for IE <FIXME>
  }
};

// Returns the deepest node that contains both endpoints of the selection.
HTMLArea.prototype.getParentElement = function(sel) {
  if(typeof sel == 'undefined')
  {
    sel = this._getSelection();
  }
  var range = this._createRange(sel);
  if (HTMLArea.is_ie) {
    switch (sel.type) {
        case "Text":
        case "None":
      // It seems that even for selection of type "None",
      // there _is_ a parent element and it's value is not
      // only correct, but very important to us.  MSIE is
      // certainly the buggiest browser in the world and I
      // wonder, God, how can Earth stand it?
      return range.parentElement();
        case "Control":
      return range.item(0);
        default:
      return this._doc.body;
    }
  } else try {
    var p = range.commonAncestorContainer;
    if (!range.collapsed && range.startContainer == range.endContainer &&
        range.startOffset - range.endOffset <= 1 && range.startContainer.hasChildNodes())
      p = range.startContainer.childNodes[range.startOffset];
    /*
    alert(range.startContainer + ":" + range.startOffset + "\n" +
          range.endContainer + ":" + range.endOffset);
    */
    while (p.nodeType == 3) {
      p = p.parentNode;
    }
    return p;
  } catch (e) {
    return null;
  }
};

// Returns an array with all the ancestor nodes of the selection.
HTMLArea.prototype.getAllAncestors = function() {
  var p = this.getParentElement();
  var a = [];
  while (p && (p.nodeType == 1) && (p.tagName.toLowerCase() != 'body')) {
    a.push(p);
    p = p.parentNode;
  }
  a.push(this._doc.body);
  return a;
};

// Returns the deepest ancestor of the selection that is of the current type
HTMLArea.prototype._getFirstAncestor = function(sel, types)
{
  var prnt = this._activeElement(sel);
  if(prnt == null)
  {
    try
    {
      prnt = (HTMLArea.is_ie ? this._createRange(sel).parentElement() : this._createRange(sel).commonAncestorContainer);
    }
    catch(e)
    {
      return null;
    }
  }

  if(typeof types == 'string')
  {
    types = [types];
  }

  while(prnt)
  {
    if(prnt.nodeType == 1)
    {
      if(types == null) return prnt;
      if(types.contains(prnt.tagName.toLowerCase()))
      {

        return prnt;
      }
      if(prnt.tagName.toLowerCase() == 'body') break;
      if(prnt.tagName.toLowerCase() == 'table') break;
    }
    prnt = prnt.parentNode;
  }

  return null;
}

/**
 * Returns the selected element, if any.  That is,
 * the element that you have last selected in the "path"
 * at the bottom of the editor, or a "control" (eg image)
 *
 * @returns null | element
 */
HTMLArea.prototype._activeElement = function(sel)
{
  if(sel == null) return null;
  if(this._selectionEmpty(sel)) return null;

  if(HTMLArea.is_ie)
  {
    if(sel.type.toLowerCase() == "control")
    {
      return sel.createRange().item(0);
    }
    else
    {

      // If it's not a control, then we need to see if
      // the selection is the _entire_ text of a parent node
      // (this happens when a node is clicked in the tree)
      var range = sel.createRange();
      var p_elm = this.getParentElement(sel);
      if(p_elm.innerHTML == range.htmlText)
      {
        return p_elm;
      }
      /*
      if(p_elm)
      {
        var p_rng = this._doc.body.createTextRange();
        p_rng.moveToElementText(p_elm);
        if(p_rng.isEqual(range))
        {
          return p_elm;
        }
      }

      if(range.parentElement())
      {
        var prnt_range = this._doc.body.createTextRange();
        prnt_range.moveToElementText(range.parentElement());
        if(prnt_range.isEqual(range))
        {
          return range.parentElement();
        }
      }
      */
      return null;
    }
  }
  else
  {
    // For Mozilla we just see if the selection is not collapsed (something is selected)
    // and that the anchor (start of selection) is an element.  This might not be totally
    // correct, we possibly should do a simlar check to IE?
    if(! sel.isCollapsed)
    {
      if(sel.anchorNode.nodeType == 1)
      {
        return sel.anchorNode;
      }
    }
    return null;
  }
}


HTMLArea.prototype._selectionEmpty = function(sel)
{
  if(!sel) return true;

  if(HTMLArea.is_ie)
  {
    return this._createRange(sel).htmlText == '';
  }
  else if(typeof sel.isCollapsed != 'undefined')
  {
    return sel.isCollapsed;
  }

  return true;
}

HTMLArea.prototype._getAncestorBlock = function(sel)
{
  // Scan upwards to find a block level element that we can change or apply to
  var prnt = (HTMLArea.is_ie ? this._createRange(sel).parentElement : this._createRange(sel).commonAncestorContainer);

  while(prnt && (prnt.nodeType == 1))
  {
    switch(prnt.tagName.toLowerCase())
    {
      case 'div' :
      case 'p'   :
      case 'address'    :
      case 'blockquote' :
      case 'center'  :
      case 'del'     :
      case 'ins'     :
      case 'pre'     :
      case 'h1'      :
      case 'h2'      :
      case 'h3'      :
      case 'h4'      :
      case 'h5'      :
      case 'h6'      :
      case 'h7'      :
        // Block Element
        return prnt;

      case 'body'     :
      case 'noframes' :
      case 'dd'  :
      case 'li'  :
      case 'th'  :
      case 'td'  :
      case 'noscript' :
        // Halting element (stop searching)
        return null;

      default :
        // Keep lookin
        break;
    }
  }

  return null;
}

HTMLArea.prototype._createImplicitBlock = function(type)
{
  // expand it until we reach a block element in either direction
  // then wrap the selection in a block and return
  var sel = this._getSelection();
  if(HTMLArea.is_ie)
  {
    sel.empty();
  }
  else
  {
    sel.collapseToStart();
  }

  var rng = this._createRange(sel);

  // Expand UP

  // Expand DN
}

HTMLArea.prototype._formatBlock = function(block_format)
{
  var ancestors = this.getAllAncestors();
  var apply_to = null;

  // Block format can be a tag followed with class defs
  //  eg div.blue.left
  var target_tag = null;
  var target_classNames = [ ];

  if(block_format.indexOf('.') >= 0)
  {
    target_tag = block_format.substr(0, block_format.indexOf('.')).toLowerCase();;

    target_classNames = block_format.substr(block_format.indexOf('.'), block_format.length - block_format.indexOf('.')).replace(/\./g, '').replace(/^\s*/, '').replace(/\s*$/, '').split(' ');
  }
  else
  {
    target_tag = block_format.toLowerCase();
  }

  var sel = this._getSelection();
  var rng = this._createRange(sel);
  var apply_to = null;

  if(HTMLArea.is_gecko)
  {
    if(sel.isCollapsed)
    {
      // With no selection we want to apply to the whole contents of the ancestor block
      apply_to = this._getAncestorBlock(sel);
      if(apply_to == null)
      {
        // If there wasn't an ancestor, make one.
        apply_to = this._createImplicitBlock(sel, target_tag);
      }
    }
    else
    {
      // With a selection it's more tricky
      switch(target_tag)
      {

        case 'h1'      :
        case 'h2'      :
        case 'h3'      :
        case 'h4'      :
        case 'h5'      :
        case 'h6'      :
        case 'h7'      :
          apply_to = [ ];
          var search_tags = ['h1', 'h2', 'h3', 'h4', 'h5', 'h6', 'h7'];
          for(var y = 0; y < search_tags.length; y++)
          {
            var headers = this._doc.getElementsByTagName(search_tag[y]);
            for(var x = 0; x < headers.length; x++)
            {
              if(sel.containsNode(headers[x]))
              {
                apply_to[apply_to.length] = headers[x];
              }
            }
          }
          if(apply_to.length > 0) break;
          // If there wern't any in the selection drop through
        case 'div' :
          apply_to = this._doc.createElement(target_tag);
          apply_to.appendChild(rng.extractContents());
          rng.insertNode(apply_to);
          break;

        case 'p'   :
        case 'center'  :
        case 'pre' :
        case 'ins' :
        case 'del' :
        case 'blockquote' :
        case 'address'    :
          apply_to = [ ];
          var paras = this._doc.getElementsByTagName(target_tag);
          for(var x = 0; x < paras.length; x++)
          {
            if(sel.containsNode(paras[x]))
            {
              apply_to[apply_to.length] = paras[x];
            }
          }

          if(apply_to.length == 0)
          {
            sel.collapseToStart();
            return this._formatBlock(block_format);
          }
          break;
      }
    }
  }

}

// Selects the contents inside the given node
HTMLArea.prototype.selectNodeContents = function(node, pos) {
  this.focusEditor();
  this.forceRedraw();
  var range;
  var collapsed = (typeof pos != "undefined");
  if (HTMLArea.is_ie) {
    // Tables and Images get selected as "objects" rather than the text contents
    if(!collapsed && node.tagName && node.tagName.toLowerCase().match(/table|img/))
    {
      range = this._doc.body.createControlRange();
      range.add(node);
    }
    else
    {
      range = this._doc.body.createTextRange();
      range.moveToElementText(node);
      (collapsed) && range.collapse(pos);
    }
    range.select();
  } else {
    var sel = this._getSelection();
    range = this._doc.createRange();
    // Tables and Images get selected as "objects" rather than the text contents
    if(!collapsed && node.tagName && node.tagName.toLowerCase().match(/table|img/))
    {
      range.selectNode(node);
      (collapsed) && range.collapse(pos);
    }
    else
    {
      range.selectNodeContents(node);
      (collapsed) && range.collapse(pos);
    }
    sel.removeAllRanges();
    sel.addRange(range);
  }
};

/** Call this function to insert HTML code at the current position.  It deletes
 * the selection, if any.
 */
HTMLArea.prototype.insertHTML = function(html) {
  var sel = this._getSelection();
  var range = this._createRange(sel);
  if (HTMLArea.is_ie) {
    range.pasteHTML(html);
  } else {
    // construct a new document fragment with the given HTML
    var fragment = this._doc.createDocumentFragment();
    var div = this._doc.createElement("div");
    div.innerHTML = html;
    while (div.firstChild) {
      // the following call also removes the node from div
      fragment.appendChild(div.firstChild);
    }
    // this also removes the selection
    var node = this.insertNodeAtSelection(fragment);
  }
};

/**
 *  Call this function to surround the existing HTML code in the selection with
 *  your tags.  FIXME: buggy!  This function will be deprecated "soon".
 */
HTMLArea.prototype.surroundHTML = function(startTag, endTag) {
  var html = this.getSelectedHTML();
  // the following also deletes the selection
  this.insertHTML(startTag + html + endTag);
};

/// Retrieve the selected block
HTMLArea.prototype.getSelectedHTML = function() {
  var sel = this._getSelection();
  var range = this._createRange(sel);
  var existing = null;
  if (HTMLArea.is_ie) {
    existing = range.htmlText;
  } else {
    existing = HTMLArea.getHTML(range.cloneContents(), false, this);
  }
  return existing;
};

/// Return true if we have some selection
HTMLArea.prototype.hasSelectedText = function() {
  // FIXME: come _on_ mishoo, you can do better than this ;-)
  return this.getSelectedHTML() != '';
};

HTMLArea.prototype._createLink = function(link) {
  var editor = this;
  var outparam = null;
  if (typeof link == "undefined") {
    link = this.getParentElement();
    if (link) {
      if (/^img$/i.test(link.tagName))
        link = link.parentNode;
      if (!/^a$/i.test(link.tagName))
        link = null;
    }
  }
  if (!link) {
    var sel = editor._getSelection();
    var range = editor._createRange(sel);
    var compare = 0;
    if (HTMLArea.is_ie) {
      compare = range.compareEndPoints("StartToEnd", range);
    } else {
      compare = range.compareBoundaryPoints(range.START_TO_END, range);
    }
    if (compare == 0) {
      alert("You need to select some text before creating a link");
      return;
    }
    outparam = {
      f_href : '',
      f_title : '',
      f_target : '',
      f_usetarget : editor.config.makeLinkShowsTarget
    };
  } else
    outparam = {
      f_href   : HTMLArea.is_ie ? editor.stripBaseURL(link.href) : link.getAttribute("href"),
      f_title  : link.title,
      f_target : link.target,
      f_usetarget : editor.config.makeLinkShowsTarget
    };
  this._popupDialog("link.html", function(param) {
    if (!param)
      return false;
    var a = link;
    if (!a) try {
      editor._doc.execCommand("createlink", false, param.f_href);
      a = editor.getParentElement();
      var sel = editor._getSelection();
      var range = editor._createRange(sel);
      if (!HTMLArea.is_ie) {
        a = range.startContainer;
        if (!/^a$/i.test(a.tagName)) {
          a = a.nextSibling;
          if (a == null)
            a = range.startContainer.parentNode;
        }
      }
    } catch(e) {}
    else {
      var href = param.f_href.trim();
      editor.selectNodeContents(a);
      if (href == "") {
        editor._doc.execCommand("unlink", false, null);
        editor.updateToolbar();
        return false;
      }
      else {
        a.href = href;
      }
    }
    if (!(a && /^a$/i.test(a.tagName)))
      return false;
    a.target = param.f_target.trim();
    a.title = param.f_title.trim();
    editor.selectNodeContents(a);
    editor.updateToolbar();
  }, outparam);
};

// Called when the user clicks on "InsertImage" button.  If an image is already
// there, it will just modify it's properties.
HTMLArea.prototype._insertImage = function(image) {
  var editor = this;	// for nested functions
  var outparam = null;
  if (typeof image == "undefined") {
    image = this.getParentElement();
    if (image && !/^img$/i.test(image.tagName))
      image = null;
  }
  if (image) outparam = {
    f_base   : editor.config.baseURL,
    f_url    : HTMLArea.is_ie ? editor.stripBaseURL(image.src) : image.getAttribute("src"),
    f_alt    : image.alt,
    f_border : image.border,
    f_align  : image.align,
    f_vert   : image.vspace,
    f_horiz  : image.hspace
  };
  this._popupDialog("insert_image.html", function(param) {
    if (!param) {	// user must have pressed Cancel
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

    for (var field in param) {
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

// Called when the user clicks the Insert Table button
HTMLArea.prototype._insertTable = function() {
  var sel = this._getSelection();
  var range = this._createRange(sel);
  var editor = this;	// for nested functions
  this._popupDialog("insert_table.html", function(param) {
    if (!param) {	// user must have pressed Cancel
      return false;
    }
    var doc = editor._doc;
    // create the table element
    var table = doc.createElement("table");
    // assign the given arguments

    for (var field in param) {
      var value = param[field];
      if (!value) {
        continue;
      }
      switch (field) {
          case "f_width"   : table.style.width = value + param["f_unit"]; break;
          case "f_align"   : table.align	 = value; break;
          case "f_border"  : table.border	 = parseInt(value); break;
          case "f_spacing" : table.cellSpacing = parseInt(value); break;
          case "f_padding" : table.cellPadding = parseInt(value); break;
      }
    }
    var cellwidth = 0;
    if (param.f_fixed)
      cellwidth = Math.floor(100 / parseInt(param.f_cols));
    var tbody = doc.createElement("tbody");
    table.appendChild(tbody);
    for (var i = 0; i < param["f_rows"]; ++i) {
      var tr = doc.createElement("tr");
      tbody.appendChild(tr);
      for (var j = 0; j < param["f_cols"]; ++j) {
        var td = doc.createElement("td");
        if (cellwidth)
          td.style.width = cellwidth + "%";
        tr.appendChild(td);
        // Mozilla likes to see something inside the cell.
        (HTMLArea.is_gecko) && td.appendChild(doc.createElement("br"));
      }
    }
    if (HTMLArea.is_ie) {
      range.pasteHTML(table.outerHTML);
    } else {
      // insert the table
      editor.insertNodeAtSelection(table);
    }
    return true;
  }, null);
};

/***************************************************
 *  Category: EVENT HANDLERS
 ***************************************************/

// el is reference to the SELECT object
// txt is the name of the select field, as in config.toolbar
HTMLArea.prototype._comboSelected = function(el, txt) {
  this.focusEditor();
  var value = el.options[el.selectedIndex].value;
  switch (txt) {
      case "fontname":
      case "fontsize": this.execCommand(txt, false, value); break;
      case "formatblock":
    // (HTMLArea.is_ie) && (value = "<" + value + ">");
    value = "<" + value + ">"
    this.execCommand(txt, false, value);
    break;
      default:
    // try to look it up in the registered dropdowns
    var dropdown = this.config.customSelects[txt];
    if (typeof dropdown != "undefined") {
      dropdown.action(this);
    } else {
      alert("FIXME: combo box " + txt + " not implemented");
    }
  }
};

// the execCommand function (intercepts some commands and replaces them with
// our own implementation)
HTMLArea.prototype.execCommand = function(cmdID, UI, param) {
  var editor = this;	// for nested functions
  this.focusEditor();
  cmdID = cmdID.toLowerCase();
  if (HTMLArea.is_gecko) try { this._doc.execCommand('useCSS', false, true); } catch (e) {};
  switch (cmdID) {
      case "htmlmode" : this.setMode(); break;
      case "hilitecolor":
    (HTMLArea.is_ie) && (cmdID = "backcolor");
      case "forecolor":
    this._popupDialog("select_color.html", function(color) {
      if (color) { // selection not canceled
        editor._doc.execCommand(cmdID, false, "#" + color);
      }
    }, HTMLArea._colorToRgb(this._doc.queryCommandValue(cmdID)));
    break;
      case "createlink":
    this._createLink();
    break;
      case "popupeditor":
    // this object will be passed to the newly opened window
    HTMLArea._object = this;
    if (HTMLArea.is_ie) {
      //if (confirm(HTMLArea.I18N.msg["IE-sucks-full-screen"]))
      {
        window.open(this.popupURL("fullscreen.html"), "ha_fullscreen",
              "toolbar=no,location=no,directories=no,status=no,menubar=no," +
              "scrollbars=no,resizable=yes,width=640,height=480");
      }
    } else {
      window.open(this.popupURL("fullscreen.html"), "ha_fullscreen",
            "toolbar=no,menubar=no,personalbar=no,width=640,height=480," +
            "scrollbars=no,resizable=yes");
    }
    break;
      case "undo":
      case "redo":
    if (this._customUndo)
      this[cmdID]();
    else
      this._doc.execCommand(cmdID, UI, param);
    break;
      case "inserttable": this._insertTable(); break;
      case "insertimage": this._insertImage(); break;
      case "about"    : this._popupDialog("about.html", null, this); break;
      case "showhelp" : window.open(_editor_url + "reference.html", "ha_help"); break;

      case "killword": this._wordClean(); break;

      case "cut":
      case "copy":
      case "paste":
    try {
      this._doc.execCommand(cmdID, UI, param);
      if (this.config.killWordOnPaste)
        this._wordClean();
    } catch (e) {
      if (HTMLArea.is_gecko) {
        if (typeof HTMLArea.I18N.msg["Moz-Clipboard"] == "undefined") {
          HTMLArea.I18N.msg["Moz-Clipboard"] =
            "Unprivileged scripts cannot access Cut/Copy/Paste programatically " +
            "for security reasons.  Click OK to see a technical note at mozilla.org " +
            "which shows you how to allow a script to access the clipboard.\n\n" +
            "[FIXME: please translate this message in your language definition file.]";
        }
        if (confirm(HTMLArea.I18N.msg["Moz-Clipboard"]))
          window.open("http://mozilla.org/editor/midasdemo/securityprefs.html");
      }
    }
    break;
      case "lefttoright":
      case "righttoleft":
    var dir = (cmdID == "righttoleft") ? "rtl" : "ltr";
    var el = this.getParentElement();
    while (el && !HTMLArea.isBlockElement(el))
      el = el.parentNode;
    if (el) {
      if (el.style.direction == dir)
        el.style.direction = "";
      else
        el.style.direction = dir;
    }
    break;
      default: try { this._doc.execCommand(cmdID, UI, param); }
    catch(e) { if (this.config.debug) { alert(e + "\n\nby execCommand(" + cmdID + ");"); } }
  }
  this.updateToolbar();
  return false;
};

/** A generic event handler for things that happen in the IFRAME's document.
 * This function also handles key bindings. */
HTMLArea.prototype._editorEvent = function(ev) {
  var editor = this;
  var keyEvent = (HTMLArea.is_ie && ev.type == "keydown") || (!HTMLArea.is_ie && ev.type == "keypress");

  if (keyEvent)
    for (var i in editor.plugins) {
      var plugin = editor.plugins[i].instance;
      if (typeof plugin.onKeyPress == "function")
        if (plugin.onKeyPress(ev))
          return false;
    }
  if (keyEvent && ev.ctrlKey && !ev.altKey) {
    var sel = null;
    var range = null;
    var key = String.fromCharCode(HTMLArea.is_ie ? ev.keyCode : ev.charCode).toLowerCase();
    var cmd = null;
    var value = null;
    switch (key) {
        case 'a':
      if (!HTMLArea.is_ie) {
        // KEY select all
        sel = this._getSelection();
        sel.removeAllRanges();
        range = this._createRange();
        range.selectNodeContents(this._doc.body);
        sel.addRange(range);
        HTMLArea._stopEvent(ev);
      }
      break;

      // simple key commands follow

        case 'b': cmd = "bold"; break;
        case 'i': cmd = "italic"; break;
        case 'u': cmd = "underline"; break;
        case 's': cmd = "strikethrough"; break;
        case 'l': cmd = "justifyleft"; break;
        case 'e': cmd = "justifycenter"; break;
        case 'r': cmd = "justifyright"; break;
        case 'j': cmd = "justifyfull"; break;
        case 'z': cmd = "undo"; break;
        case 'y': cmd = "redo"; break;
        case 'v': if (HTMLArea.is_ie || editor.config.htmlareaPaste) { cmd = "paste"; } break;
        case 'n': cmd = "formatblock"; value = HTMLArea.is_ie ? "<p>" : "p"; break;

        case '0': cmd = "killword"; break;

      // headings
        case '1':
        case '2':
        case '3':
        case '4':
        case '5':
        case '6':
      cmd = "formatblock";
      value = "h" + key;
      if (HTMLArea.is_ie)
        value = "<" + value + ">";
      break;
    }
    if (cmd) {
      // execute simple command
      this.execCommand(cmd, false, value);
      HTMLArea._stopEvent(ev);
    }
  }
  else if (keyEvent) {
    // other keys here
    switch (ev.keyCode) {
        case 13: // KEY enter
      if (HTMLArea.is_gecko && !ev.shiftKey) {
        this.dom_checkInsertP();
        HTMLArea._stopEvent(ev);
      }
      break;
        case 8: // KEY backspace
        case 46: // KEY delete
      if (HTMLArea.is_gecko && !ev.shiftKey) {
        if (this.dom_checkBackspace())
          HTMLArea._stopEvent(ev);
      } else if (HTMLArea.is_ie) {
        if (this.ie_checkBackspace())
          HTMLArea._stopEvent(ev);
      }
      break;
    }
  }

  // update the toolbar state after some time
  if (editor._timerToolbar) {
    clearTimeout(editor._timerToolbar);
  }
  editor._timerToolbar = setTimeout(function() {
    editor.updateToolbar();
    editor._timerToolbar = null;
  }, 100);
};

HTMLArea.prototype.convertNode = function(el, newTagName) {
  var newel = this._doc.createElement(newTagName);
  while (el.firstChild)
    newel.appendChild(el.firstChild);
  return newel;
};

HTMLArea.prototype.ie_checkBackspace = function() {
  var sel = this._getSelection();
  var range = this._createRange(sel);
  var r2 = range.duplicate();
  r2.moveStart("character", -1);
  var a = r2.parentElement();
  if (a != range.parentElement() &&
      /^a$/i.test(a.tagName)) {
    r2.collapse(true);
    r2.moveEnd("character", 1);
    r2.pasteHTML('');
    r2.select();
    return true;
  }
};

HTMLArea.prototype.dom_checkBackspace = function() {
  var self = this;
  setTimeout(function() {
    var sel = self._getSelection();
    var range = self._createRange(sel);
    var SC = range.startContainer;
    var SO = range.startOffset;
    var EC = range.endContainer;
    var EO = range.endOffset;
    var newr = SC.nextSibling;
    if (SC.nodeType == 3)
      SC = SC.parentNode;
    if (!/\S/.test(SC.tagName)) {
      var p = document.createElement("p");
      while (SC.firstChild)
        p.appendChild(SC.firstChild);
      SC.parentNode.insertBefore(p, SC);
      SC.parentNode.removeChild(SC);
      var r = range.cloneRange();
      r.setStartBefore(newr);
      r.setEndAfter(newr);
      r.extractContents();
      sel.removeAllRanges();
      sel.addRange(r);
    }
  }, 10);
};

/** The idea here is
 * 1. See if we are in a block element
 * 2. If we are not, then wrap the current "block" of text into a paragraph
 * 3. Now that we have a block element, select all the text between the insertion point
 *    and just AFTER the end of the block
 *    eg <p>The quick |brown fox jumped over the lazy dog.</p>|
 *                     ---------------------------------------
 * 4. Extract that from the document, making
 *       <p>The quick </p>
 *    and a document fragment with
 *       <p>brown fox jumped over the lazy dog.</p>
 * 5. Reinsert it just after the block element
 *       <p>The quick </p><p>brown fox jumped over the lazy dog.</p>
 *
 * Along the way, allow inserting blank paragraphs, which will look like <p><br/></p>
 */

HTMLArea.prototype.dom_checkInsertP = function() {

  // Get the insertion point, we'll scrub any highlighted text the user wants rid of while we are there.
  var sel = this._getSelection();
  var range = this._createRange(sel);
  if (!range.collapsed)
  {
    range.deleteContents();
  }
  this.deactivateEditor();
  //sel.removeAllRanges();
  //sel.addRange(range);

  var SC = range.startContainer;
  var SO = range.startOffset;
  var EC = range.endContainer;
  var EO = range.endOffset;

  // If the insertion point is character 0 of the
  // document, then insert a space character that we will wrap into a paragraph
  // in a bit.
  if (SC == EC && SC == body && !SO && !EO)
  {
    p = this._doc.createTextNode(" ");
    body.insertBefore(p, body.firstChild);
    range.selectNodeContents(p);
    SC = range.startContainer;
    SO = range.startOffset;
    EC = range.endContainer;
    EO = range.endOffset;
  }

  // See if we are in a block element, if so, great.
  var p     = this.getAllAncestors();

  var block = null;
  var body = this._doc.body;
  for (var i = 0; i < p.length; ++i)
  {
    if(HTMLArea.isParaContainer(p[i]))
    {
      break;
    }
    else if (HTMLArea.isBlockElement(p[i]) && !/body|html/i.test(p[i].tagName))
    {
      block = p[i];
      break;
    }
  }

  // If not in a block element, we'll have to turn some stuff into a paragraph
  if (!block)
  {
    // We want to wrap as much stuff as possible into the paragraph in both directions
    // from the insertion point.  We start with the start container and walk back up to the
    // node just before any of the paragraph containers.
    var wrap = range.startContainer;
    while(wrap.parentNode && !HTMLArea.isParaContainer(wrap.parentNode))
    {
      wrap = wrap.parentNode;
    }
    var start = wrap;
    var end   = wrap;

    // Now we walk up the sibling list until we hit the top of the document
    // or an element that we shouldn't put in a p (eg other p, div, ul, ol, table)
    while(start.previousSibling)
    {
      if(start.previousSibling.tagName)
      {
        if(!HTMLArea.isBlockElement(start.previousSibling))
        {
          start = start.previousSibling;
        }
        else
        {
          break;
        }
      }
      else
      {
        start = start.previousSibling;
      }
    }

    // Same down the list
    while(end.nextSibling)
    {
      if(end.nextSibling.tagName)
      {
        if(!HTMLArea.isBlockElement(end.nextSibling))
        {
          end = end.nextSibling;
        }
        else
        {
          break;
        }
      }
      else
      {
        end = end.nextSibling;
      }
    }

    // Select the entire block
    range.setStartBefore(start);
    range.setEndAfter(end);

    // Make it a paragraph
    range.surroundContents(this._doc.createElement('p'));

    // Which becomes the block element
    block = range.startContainer.firstChild;

    // And finally reset the insertion point to where it was originally
    range.setStart(SC, SO);
  }

  // The start point is the insertion point, so just move the end point to immediatly
  // after the block
  range.setEndAfter(block);

  // Extract the range, to split the block
  // If we just did range.extractContents() then Mozilla does wierd stuff
  // with selections, but if we clone, then remove the original range and extract
  // the clone, it's quite happy.
  var r2 = range.cloneRange();
  sel.removeRange(range);
  var df = r2.extractContents();

  if(df.childNodes.length == 0)
  {
    df.appendChild(this._doc.createElement('p'));
    df.firstChild.appendChild(this._doc.createElement('br'));
  }

  if(df.childNodes.length > 1)
  {
    var nb = this._doc.createElement('p');
    while(df.firstChild)
    {
      var s = df.firstChild;
      df.removeChild(s);
      nb.appendChild(s);
    }
    df.appendChild(nb);
  }

  // If the original block is empty, put a nsbp in it.
  if (!/\S/.test(block.innerHTML))
    block.innerHTML = "&nbsp;";

  p = df.firstChild;
  if (!/\S/.test(p.innerHTML))
    p.innerHTML = "<br />";

  // If the new block is empty and it's a heading, make it a paragraph
  // note, the new block is empty when you are hitting enter at the end of the existing block
  if (/^\s*<br\s*\/?>\s*$/.test(p.innerHTML) && /^h[1-6]$/i.test(p.tagName))
  {
    df.appendChild(this.convertNode(p, "p"));
    df.removeChild(p);
  }

  var newblock = block.parentNode.insertBefore(df.firstChild, block.nextSibling);

  /*
  if(block.nextSibling)
  {
    block.parentNode.insertBefore(df, block.nextSibling);
  }
  else
  {
    block.parentNode.appendChild(df);
  }
  */

  // Select the range (to set the insertion)
  // collapse to the start of the new block
  //  (remember the block might be <p><br/></p>, so if we collapsed to the end the <br/> would be noticable)

  //range.selectNode(newblock.firstChild);
  //range.collapse(true);

  this.activateEditor();

  var sel = this._getSelection();
  sel.removeAllRanges();
  sel.collapse(newblock,0);

  // scroll into view
  this.scrollToElement(newblock);

  //this.forceRedraw();

};

HTMLArea.prototype.scrollToElement = function(e)
{
  if(HTMLArea.is_gecko)
  {
    var top  = 0;
    var left = 0;
    while(e)
    {
      top  += e.offsetTop;
      left += e.offsetLeft;
      if(e.offsetParent && e.offsetParent.tagName.toLowerCase() != 'body')
      {
        e = e.offsetParent;
      }
      else
      {
        e = null;
      }
    }
    this._iframe.contentWindow.scrollTo(left, top);
  }
}

// retrieve the HTML
HTMLArea.prototype.getHTML = function() {
  var html = '';
  switch (this._editMode) {
      case "wysiwyg"  :
        {
          if (!this.config.fullPage)
            html = HTMLArea.getHTML(this._doc.body, false, this);
          else
            html = this.doctype + "\n" + HTMLArea.getHTML(this._doc.documentElement, true, this);
          break;
        }
      case "textmode" :
        {
          html = this._textArea.value;
          break;
        }
      default	        :
        {
          alert("Mode <" + mode + "> not defined!");
          return false;
        }
  }
  return html;
};

HTMLArea.prototype.outwardHtml = function(html)
{
  html = html.replace(/<(\/?)b(\s|>|\/)/ig, "<$1strong$2");
  html = html.replace(/<(\/?)i(\s|>|\/)/ig, "<$1em$2");

  // Figure out what our server name is, and how it's referenced
  var serverBase = location.href.replace(/(https?:\/\/[^\/]*)\/.*/, '$1') + '/';

  // IE puts this in can't figure out why
  html = html.replace(/https?:\/\/null\//g, serverBase);

  // Make semi-absolute links to be truely absolute
  //  we do this just to standardize so that special replacements knows what
  //  to expect
  html = html.replace(/((href|src|background)=[\'\"])\/+/ig, '$1' + serverBase);

  html = this.outwardSpecialReplacements(html);

  html = this.fixRelativeLinks(html);
  return html;
}

HTMLArea.prototype.inwardHtml = function(html)
{
  // Midas uses b and i instead of strong and em, um, hello,
  // mozilla, this is the 21st century calling!
  if (HTMLArea.is_gecko) {
    html = html.replace(/<(\/?)strong(\s|>|\/)/ig, "<$1b$2");
    html = html.replace(/<(\/?)em(\s|>|\/)/ig, "<$1i$2");
  }
  html = this.inwardSpecialReplacements(html);

  // For IE's sake, make any URLs that are semi-absolute (="/....") to be
  // truely absolute
  var nullRE = new RegExp('((href|src|background)=[\'"])/+', 'gi');
  html = html.replace(nullRE, '$1' + location.href.replace(/(https?:\/\/[^\/]*)\/.*/, '$1') + '/');

  html = this.fixRelativeLinks(html);
  return html;
}

HTMLArea.prototype.outwardSpecialReplacements = function(html)
{
  for(var i in this.config.specialReplacements)
  {
    var from = this.config.specialReplacements[i];
    var to   = i;
    // alert('out : ' + from + '=>' + to);
    var reg = new RegExp(from.replace(HTMLArea.RE_Specials, '\\$1'), 'g');
    html = html.replace(reg, to.replace(/\$/g, '$$$$'));
    //html = html.replace(from, to);
  }
  return html;
}

HTMLArea.prototype.inwardSpecialReplacements = function(html)
{
  // alert("inward");
  for(var i in this.config.specialReplacements)
  {
    var from = i;
    var to   = this.config.specialReplacements[i];
    // alert('in : ' + from + '=>' + to);
    //
    // html = html.replace(reg, to);
    // html = html.replace(from, to);
    var reg = new RegExp(from.replace(HTMLArea.RE_Specials, '\\$1'), 'g');
    html = html.replace(reg, to.replace(/\$/g, '$$$$')); // IE uses doubled dollar signs to escape backrefs, also beware that IE also implements $& $_ and $' like perl.
  }
  return html;
}


HTMLArea.prototype.fixRelativeLinks = function(html)
{

  if(typeof this.config.stripSelfNamedAnchors != 'undefined' && this.config.stripSelfNamedAnchors)
  {
    var stripRe = new RegExp(document.location.href.replace(HTMLArea.RE_Specials, '\\$1') + '(#.*)', 'g');
    html = html.replace(stripRe, '$1');
  }


  if(typeof this.config.stripBaseHref != 'undefined' && this.config.stipBaseHref)
  {
    var baseRe = null
    if(typeof this.config.baseHref != 'undefined' && this.config.baseHref != null)
    {
      baseRe = new RegExp(this.config.baseHref.replace(HTMLArea.RE_Specials, '\\$1'), 'g');
    }
    else
    {
      baseRe = new RegExp(document.location.href.replace(/([^\/]*\/?)$/, '').replace(HTMLArea.RE_Specials, '\\$1'), 'g');
    }

    html = html.replace(baseRe, '');
  }

  if(HTMLArea.is_ie)
  {
    // This is now done in inward & outward
    // Don't know why but IE is doing this (putting http://null/ on links?!
    // alert(html);
    // var nullRE = new RegExp('https?:\/\/null\/', 'g');
    // html = html.replace(nullRE, location.href.replace(/(https?:\/\/[^\/]*\/).*/, '$1'));
    // alert(html);
  }

  return html;
}

// retrieve the HTML (fastest version, but uses innerHTML)
HTMLArea.prototype.getInnerHTML = function() {
  if(!this._doc.body) return '';
  switch (this._editMode) {
      case "wysiwyg"  :
        if (!this.config.fullPage)
          // return this._doc.body.innerHTML;
          html = this._doc.body.innerHTML;
        else
          html = this.doctype + "\n" + this._doc.documentElement.innerHTML;
        break;
      case "textmode" :
        html = this._textArea.value;
        break;
      default	    :
        alert("Mode <" + mode + "> not defined!");
        return false;
  }

  return html;
};

// completely change the HTML inside
HTMLArea.prototype.setHTML = function(html) {
  switch (this._editMode) {
      case "wysiwyg"  :
    if (!this.config.fullPage)
      this._doc.body.innerHTML = html;
    else
      // this._doc.documentElement.innerHTML = html;
      this._doc.body.innerHTML = html;
    break;
      case "textmode" : this._textArea.value = html; break;
      default	    : alert("Mode <" + mode + "> not defined!");
  }
  return false;
};

// sets the given doctype (useful when config.fullPage is true)
HTMLArea.prototype.setDoctype = function(doctype) {
  this.doctype = doctype;
};

/***************************************************
 *  Category: UTILITY FUNCTIONS
 ***************************************************/

// variable used to pass the object to the popup editor window.
HTMLArea._object = null;

// function that returns a clone of the given object
HTMLArea.cloneObject = function(obj) {
  if (!obj) return null;
  var newObj = new Object;

  // check for array objects
  if (obj.constructor.toString().indexOf("function Array(") == 1) {
    newObj = obj.constructor();
  }

  // check for function objects (as usual, IE is fucked up)
  if (obj.constructor.toString().indexOf("function Function(") == 1) {
    newObj = obj; // just copy reference to it
  } else for (var n in obj) {
    var node = obj[n];
    if (typeof node == 'object') { newObj[n] = HTMLArea.cloneObject(node); }
    else                         { newObj[n] = node; }
  }

  return newObj;
};

// FIXME!!! this should return false for IE < 5.5
HTMLArea.checkSupportedBrowser = function() {
  if (HTMLArea.is_gecko) {
    if (navigator.productSub < 20021201) {
      alert("You need at least Mozilla-1.3 Alpha.\n" +
            "Sorry, your Gecko is not supported.");
      return false;
    }
    if (navigator.productSub < 20030210) {
      alert("Mozilla < 1.3 Beta is not supported!\n" +
            "I'll try, though, but it might not work.");
    }
  }
  return HTMLArea.is_gecko || HTMLArea.is_ie;
};

// selection & ranges

// returns the current selection object
HTMLArea.prototype._getSelection = function() {
  if (HTMLArea.is_ie) {
    return this._doc.selection;
  } else {
    return this._iframe.contentWindow.getSelection();
  }
};

// returns a range for the current selection
HTMLArea.prototype._createRange = function(sel) {
  if (HTMLArea.is_ie) {
    return sel.createRange();
  } else {
    this.focusEditor();
    if (typeof sel != "undefined") {
      try {
        return sel.getRangeAt(0);
      } catch(e) {
        return this._doc.createRange();
      }
    } else {
      return this._doc.createRange();
    }
  }
};

// event handling

HTMLArea._addEvent = function(el, evname, func) {
  if (HTMLArea.is_ie) {
    el.attachEvent("on" + evname, func);
  } else {
    el.addEventListener(evname, func, true);
  }
};

HTMLArea._addEvents = function(el, evs, func) {
  for (var i = evs.length; --i >= 0;) {
    HTMLArea._addEvent(el, evs[i], func);
  }
};

HTMLArea._removeEvent = function(el, evname, func) {
  if (HTMLArea.is_ie) {
    el.detachEvent("on" + evname, func);
  } else {
    el.removeEventListener(evname, func, true);
  }
};

HTMLArea._removeEvents = function(el, evs, func) {
  for (var i = evs.length; --i >= 0;) {
    HTMLArea._removeEvent(el, evs[i], func);
  }
};

HTMLArea._stopEvent = function(ev) {
  if (HTMLArea.is_ie) {
    ev.cancelBubble = true;
    ev.returnValue = false;
  } else {
    ev.preventDefault();
    ev.stopPropagation();
  }
};


HTMLArea.prototype.notifyOn = function(ev, fn)
{
  if(typeof this._notifyListeners[ev] == 'undefined')
  {
    this._notifyListeners[ev] = [ ];
  }

  this._notifyListeners[ev].push(fn);
}

HTMLArea.prototype.notifyOf = function(ev, args)
{

  if(this._notifyListeners[ev])
  {

    for(var i = 0; i < this._notifyListeners[ev].length; i++)
    {
      this._notifyListeners[ev][i](ev, args);
    }
  }
}


HTMLArea._removeClass = function(el, className) {
  if (!(el && el.className)) {
    return;
  }
  var cls = el.className.split(" ");
  var ar = new Array();
  for (var i = cls.length; i > 0;) {
    if (cls[--i] != className) {
      ar[ar.length] = cls[i];
    }
  }
  el.className = ar.join(" ");
};

HTMLArea._addClass = function(el, className) {
  // remove the class first, if already there
  HTMLArea._removeClass(el, className);
  el.className += " " + className;
};

HTMLArea._hasClass = function(el, className) {
  if (!(el && el.className)) {
    return false;
  }
  var cls = el.className.split(" ");
  for (var i = cls.length; i > 0;) {
    if (cls[--i] == className) {
      return true;
    }
  }
  return false;
};

HTMLArea._blockTags = " body form textarea fieldset ul ol dl li div " +
"p h1 h2 h3 h4 h5 h6 quote pre table thead " +
"tbody tfoot tr td th iframe address blockquote";
HTMLArea.isBlockElement = function(el) {
  return el && el.nodeType == 1 && (HTMLArea._blockTags.indexOf(" " + el.tagName.toLowerCase() + " ") != -1);
};

HTMLArea._paraContainerTags = " body td th caption fieldset div";
HTMLArea.isParaContainer = function(el)
{
  return el && el.nodeType == 1 && (HTMLArea._paraContainerTags.indexOf(" " + el.tagName.toLowerCase() + " ") != -1);
}

HTMLArea._closingTags = " head script style div span tr td tbody table em strong b i code cite dfn abbr acronym font a title ";
HTMLArea.needsClosingTag = function(el) {
  return el && el.nodeType == 1 && (HTMLArea._closingTags.indexOf(" " + el.tagName.toLowerCase() + " ") != -1);
};

// performs HTML encoding of some given string
HTMLArea.htmlEncode = function(str) {
  if(typeof str.replace == 'undefined') str = str.toString();
  // we don't need regexp for that, but.. so be it for now.
  str = str.replace(/&/ig, "&amp;");
  str = str.replace(/</ig, "&lt;");
  str = str.replace(/>/ig, "&gt;");
  str = str.replace(/\xA0/g, "&nbsp;"); // Decimal 160, non-breaking-space
  str = str.replace(/\x22/g, "&quot;");
  // \x22 means '"' -- we use hex reprezentation so that we don't disturb
  // JS compressors (well, at least mine fails.. ;)
  return str;
};

// Retrieves the HTML code from the given node.	 This is a replacement for
// getting innerHTML, using standard DOM calls.
// Wrapper catch a Mozilla-Exception with non well formed html source code
HTMLArea.getHTML = function(root, outputRoot, editor){
    try{
        return HTMLArea.getHTMLWrapper(root,outputRoot,editor);
    }
    catch(e){
        alert('Your Document is not well formed. Check JavaScript console for details.');
        return editor._iframe.contentWindow.document.body.innerHTML;
    }
}

HTMLArea.getHTMLWrapper = function(root, outputRoot, editor) {
  var html = "";
  switch (root.nodeType) {
    case 10:// Node.DOCUMENT_TYPE_NODE
    case 6: // Node.ENTITY_NODE
    case 12:// Node.NOTATION_NODE
      // this all are for the document type, probably not necessary
      break;

    case 2: // Node.ATTRIBUTE_NODE
      // Never get here, this has to be handled in the ELEMENT case because
      // of IE crapness requring that some attributes are grabbed directly from
      // the attribute (nodeValue doesn't return correct values), see
      //http://groups.google.com/groups?hl=en&lr=&ie=UTF-8&oe=UTF-8&safe=off&selm=3porgu4mc4ofcoa1uqkf7u8kvv064kjjb4%404ax.com
      // for information
      break;

    case 4: // Node.CDATA_SECTION_NODE
      // Mozilla seems to convert CDATA into a comment when going into wysiwyg mode,
      //  don't know about IE
      html += '<![CDATA[' + root.data + ']]>';
      break;

    case 5: // Node.ENTITY_REFERENCE_NODE
      html += '&' + root.nodeValue + ';';
      break;

    case 7: // Node.PROCESSING_INSTRUCTION_NODE
      // PI's don't seem to survive going into the wysiwyg mode, (at least in moz)
      // so this is purely academic
      html += '<?' + root.target + ' ' + root.data + ' ?>';
      break;


      case 1: // Node.ELEMENT_NODE
      case 11: // Node.DOCUMENT_FRAGMENT_NODE
      case 9: // Node.DOCUMENT_NODE
      {
    var closed;
    var i;
    var root_tag = (root.nodeType == 1) ? root.tagName.toLowerCase() : '';
    if (root_tag == 'br' && !root.nextSibling)
      break;
    if (outputRoot)
      outputRoot = !(editor.config.htmlRemoveTags && editor.config.htmlRemoveTags.test(root_tag));
    if (HTMLArea.is_ie && root_tag == "head") {
      if (outputRoot)
        html += "<head>";
      // lowercasize
      var save_multiline = RegExp.multiline;
      RegExp.multiline = true;
      var txt = root.innerHTML.replace(HTMLArea.RE_tagName, function(str, p1, p2) {
        return p1 + p2.toLowerCase();
      });
      RegExp.multiline = save_multiline;
      html += txt;
      if (outputRoot)
        html += "</head>";
      break;
    } else if (outputRoot) {
      closed = (!(root.hasChildNodes() || HTMLArea.needsClosingTag(root)));
      html = "<" + root.tagName.toLowerCase();
      var attrs = root.attributes;
      for (i = 0; i < attrs.length; ++i) {
        var a = attrs.item(i);
        if (!a.specified) {
          continue;
        }
        var name = a.nodeName.toLowerCase();
        if (/_moz_editor_bogus_node/.test(name)) {
          html = "";
          break;
        }
        if (/(_moz)|(contenteditable)|(_msh)/.test(name)) {
          // avoid certain attributes
          continue;
        }
        var value;
        if (name != "style") {
          // IE5.5 reports 25 when cellSpacing is
          // 1; other values might be doomed too.
          // For this reason we extract the
          // values directly from the root node.
          // I'm starting to HATE JavaScript
          // development.  Browser differences
          // suck.
          //
          // Using Gecko the values of href and src are converted to absolute links
          // unless we get them using nodeValue()
          if (typeof root[a.nodeName] != "undefined" && name != "href" && name != "src" && !/^on/.test(name)) {
            value = root[a.nodeName];
          } else {
            value = a.nodeValue;
            // IE seems not willing to return the original values - it converts to absolute
            // links using a.nodeValue, a.value, a.stringValue, root.getAttribute("href")
            // So we have to strip the baseurl manually :-/
            if (HTMLArea.is_ie && (name == "href" || name == "src")) {
              value = editor.stripBaseURL(value);
            }
          }
        } else { // IE fails to put style in attributes list
          // FIXME: cssText reported by IE is UPPERCASE
          value = root.style.cssText;
        }
        if (/^(_moz)?$/.test(value)) {
          // Mozilla reports some special tags
          // here; we don't need them.
          continue;
        }
        html += " " + name + '="' + HTMLArea.htmlEncode(value) + '"';
      }
      if (html != "") {
        html += closed ? " />" : ">";
      }
    }
    for (i = root.firstChild; i; i = i.nextSibling) {
      html += HTMLArea.getHTMLWrapper(i, true, editor);
    }
    if (outputRoot && !closed) {
      html += "</" + root.tagName.toLowerCase() + ">";
    }
    break;
      }
      case 3: // Node.TEXT_NODE

    // If a text node is alone in an element and all spaces, replace it with an non breaking one
    // This partially undoes the damage done by moz, which translates '&nbsp;'s into spaces in the data element

    html = /^script|style$/i.test(root.parentNode.tagName) ? root.data : HTMLArea.htmlEncode(root.data);
    break;

      case 8: // Node.COMMENT_NODE
    html = "<!--" + root.data + "-->";
    break;		// skip comments, for now.
  }
  return html;
};

HTMLArea.prototype.stripBaseURL = function(string) {
  var baseurl = this.config.baseURL;

  // strip to last directory in case baseurl points to a file
  baseurl = baseurl.replace(/[^\/]+$/, '');
  var basere = new RegExp(baseurl);
  string = string.replace(basere, "");

  // strip host-part of URL which is added by MSIE to links relative to server root
  baseurl = baseurl.replace(/^(https?:\/\/[^\/]+)(.*)$/, '$1');
  basere = new RegExp(baseurl);
  return string.replace(basere, "");
};

String.prototype.trim = function() {
  return this.replace(/^\s+/, '').replace(/\s+$/, '');
};

// creates a rgb-style color from a number
HTMLArea._makeColor = function(v) {
  if (typeof v != "number") {
    // already in rgb (hopefully); IE doesn't get here.
    return v;
  }
  // IE sends number; convert to rgb.
  var r = v & 0xFF;
  var g = (v >> 8) & 0xFF;
  var b = (v >> 16) & 0xFF;
  return "rgb(" + r + "," + g + "," + b + ")";
};

// returns hexadecimal color representation from a number or a rgb-style color.
HTMLArea._colorToRgb = function(v) {
  if (!v)
    return '';

  // returns the hex representation of one byte (2 digits)
  function hex(d) {
    return (d < 16) ? ("0" + d.toString(16)) : d.toString(16);
  };

  if (typeof v == "number") {
    // we're talking to IE here
    var r = v & 0xFF;
    var g = (v >> 8) & 0xFF;
    var b = (v >> 16) & 0xFF;
    return "#" + hex(r) + hex(g) + hex(b);
  }

  if (v.substr(0, 3) == "rgb") {
    // in rgb(...) form -- Mozilla
    var re = /rgb\s*\(\s*([0-9]+)\s*,\s*([0-9]+)\s*,\s*([0-9]+)\s*\)/;
    if (v.match(re)) {
      var r = parseInt(RegExp.$1);
      var g = parseInt(RegExp.$2);
      var b = parseInt(RegExp.$3);
      return "#" + hex(r) + hex(g) + hex(b);
    }
    // doesn't match RE?!  maybe uses percentages or float numbers
    // -- FIXME: not yet implemented.
    return null;
  }

  if (v.substr(0, 1) == "#") {
    // already hex rgb (hopefully :D )
    return v;
  }

  // if everything else fails ;)
  return null;
};

// modal dialogs for Mozilla (for IE we're using the showModalDialog() call).

// receives an URL to the popup dialog and a function that receives one value;
// this function will get called after the dialog is closed, with the return
// value of the dialog.
HTMLArea.prototype._popupDialog = function(url, action, init) {
  Dialog(this.popupURL(url), action, init);
};

// paths

HTMLArea.prototype.imgURL = function(file, plugin) {
  if (typeof plugin == "undefined")
    return _editor_url + file;
  else
    return _editor_url + "plugins/" + plugin + "/img/" + file;
};

HTMLArea.prototype.popupURL = function(file) {
  var url = "";
  if (file.match(/^plugin:\/\/(.*?)\/(.*)/)) {
    var plugin = RegExp.$1;
    var popup = RegExp.$2;
    if (!/\.html$/.test(popup))
      popup += ".html";
    url = _editor_url + "plugins/" + plugin + "/popups/" + popup;
  } else
    url = _editor_url + this.config.popupURL + file;
  return url;
};

/**
 * FIX: Internet Explorer returns an item having the _name_ equal to the given
 * id, even if it's not having any id.  This way it can return a different form
 * field even if it's not a textarea.  This workarounds the problem by
 * specifically looking to search only elements having a certain tag name.
 */
HTMLArea.getElementById = function(tag, id) {
  var el, i, objs = document.getElementsByTagName(tag);
  for (i = objs.length; --i >= 0 && (el = objs[i]);)
    if (el.id == id)
      return el;
  return null;
};


/** Use some CSS trickery to toggle borders on tables */

HTMLArea.prototype._toggleBorders = function()
{
  tables = this._doc.getElementsByTagName('TABLE');
  if(tables.length != 0)
  {
   if(!this.borders)
   {
    name = "bordered";
    this.borders = true;
   }
   else
   {
     name = "";
     this.borders = false;
   }

   for (var ix=0;ix < tables.length;ix++)
   {
     if(this.borders)
     {
       HTMLArea._addClass(tables[ix], 'htmtableborders');
     }
     else
     {
       HTMLArea._removeClass(tables[ix], 'htmtableborders');
     }
   }
  }
  return true;
}


HTMLArea.addClasses = function(el, classes)
  {
    if(el != null)
    {
      var thiers = el.className.trim().split(' ');
      var ours   = classes.split(' ');
      for(var x = 0; x < ours.length; x++)
      {
        var exists = false;
        for(var i = 0; exists == false && i < thiers.length; i++)
        {
          if(thiers[i] == ours[x])
          {
            exists = true;
          }
        }
        if(exists == false)
        {
          thiers[thiers.length] = ours[x];
        }
      }
      el.className = thiers.join(' ').trim();
    }
  }

HTMLArea.removeClasses = function(el, classes)
{
  var existing    = el.className.trim().split();
  var new_classes = [ ];
  var remove      = classes.trim().split();

  for(var i = 0; i < existing.length; i++)
  {
    var found = false;
    for(var x = 0; x < remove.length && !found; x++)
    {
      if(existing[i] == remove[x])
      {
        found = true;
      }
    }
    if(!found)
    {
      new_classes[new_classes.length] = existing[i];
    }
  }
  return new_classes.join(' ');
}

/** Alias these for convenience */
HTMLArea.addClass       = HTMLArea._addClass;
HTMLArea.removeClass    = HTMLArea._removeClass;
HTMLArea._addClasses    = HTMLArea.addClasses;
HTMLArea._removeClasses = HTMLArea.removeClasses;

/** Use XML HTTPRequest to post some data back to the server and do something
 * with the response (asyncronously!), this is used by such things as the tidy functions
 */
HTMLArea._postback = function(url, data, handler)
{
  var req = null;
  if(HTMLArea.is_ie)
  {
   req = new ActiveXObject("Microsoft.XMLHTTP");
  }
  else
  {
   req = new XMLHttpRequest();
  }

  var content = '';
  for(var i in data)
  {
    content += (content.length ? '&' : '') + i + '=' + escape(data[i]);
  }

  function callBack()
  {
    if(req.readyState == 4)
    {
      if(req.status == 200)
      {
        handler(req.responseText, req);
      }
      else
      {
        alert('An error has occurred: ' + req.statusText);
      }
    }
  }

  req.onreadystatechange = callBack;

  req.open('POST', url, true);
  req.setRequestHeader
  (
      'Content-Type',
      'application/x-www-form-urlencoded; charset=UTF-8'
  );
  //alert(content);
  req.send(content);
}

HTMLArea._getback = function(url, handler)
{
  var req = null;
  if(HTMLArea.is_ie)
  {
   req = new ActiveXObject("Microsoft.XMLHTTP");
  }
  else
  {
   req = new XMLHttpRequest();
  }

  function callBack()
  {
    if(req.readyState == 4)
    {
      if(req.status == 200)
      {
        handler(req.responseText, req);
      }
      else
      {
        alert('An error has occurred: ' + req.statusText);
      }
    }
  }

  req.onreadystatechange = callBack;
  req.open('GET', url, true);
  req.send(null);
}

HTMLArea._geturlcontent = function(url)
{
  var req = null;
  if(HTMLArea.is_ie)
  {
   req = new ActiveXObject("Microsoft.XMLHTTP");
  }
  else
  {
   req = new XMLHttpRequest();
  }

  // Synchronous!
  req.open('GET', url, false);
  req.send(null);
  if(req.status == 200)
  {
    return req.responseText;
  }
  else
  {
    return '';
  }

}

/**
 * Unless somebody already has, make a little function to debug things
 */
if(typeof dump == 'undefined')
{
  function dump(o) {
    var s = '';
    for (var prop in o) {
      s += prop + ' = ' + o[prop] + '\n';
    }

    x = window.open("", "debugger");
    x.document.write('<pre>' + s + '</pre>');
  }
}


HTMLArea.arrayContainsArray = function(a1, a2)
{
  var all_found = true;
  for(var x = 0; x < a2.length; x++)
  {
    var found = false;
    for(var i = 0; i < a1.length; i++)
    {
      if(a1[i] == a2[x])
      {
        found = true;
        break;
      }
    }
    if(!found)
    {
      all_found = false;
      break;
    }
  }
  return all_found;
}

HTMLArea.arrayFilter = function(a1, filterfn)
{
  var new_a = [ ];
  for(var x = 0; x < a1.length; x++)
  {
    if(filterfn(a1[x]))
      new_a[new_a.length] = a1[x];
  }

  return new_a;
}

HTMLArea.uniq_count = 0;
HTMLArea.uniq = function(prefix)
{
  return prefix + HTMLArea.uniq_count++;
}

/** New language handling functions **/


/** Load a language file.
 *  This function should not be used directly, HTMLArea._lc will use it when necessary.
 * @param context Case sensitive context name, eg 'HTMLArea', 'TableOperations', ...
 * @TODO  Make this useful.
 */
HTMLArea._loadlang = function(context)
{
  return { };
}

/** Return a localised string.
 * @param string    English language string
 * @param context   Case sensitive context name, eg 'HTMLArea' (default), 'TableOperations'...
 */
HTMLArea._lc = function(string, context)
{
  if(typeof HTMLArea._lc_catalog == 'undefined')
  {
    HTMLArea._lc_catalog = [ ];
  }

  if(typeof context == 'undefined')
  {
    context = 'HTMLArea';
  }

  if(typeof HTMLArea._lc_catalog[context] == 'undefined')
  {
    HTMLArea._lc_catalog[context] = HTMLArea._loadlang(context);
  }

  if(typeof HTMLArea._lc_catalog[context][string] == 'undefined')
  {
    return string; // Indicate it's untranslated
  }
  else
  {
    return HTMLArea._lc_catalog[context][string];
  }
}

HTMLArea.hasDisplayedChildren = function(el)
{
  var children = el.childNodes;
  for(var i =0; i < children.length;i++)
  {
    if(children[i].tagName)
    {
      if(children[i].style.display != 'none')
      {
        return true;
      }
    }
  }
  return false;
}


HTMLArea._loadback = function(src, callback)
{
  var head = document.getElementsByTagName("head")[0];
  var evt = HTMLArea.is_ie ? "onreadystatechange" : "onload";

  var script = document.createElement("script");
  script.type = "text/javascript";
  script.src = src;
  script[evt] = function()
  {
    if(HTMLArea.is_ie && !/loaded|complete/.test(window.event.srcElement.readyState))  return;
    callback();
  }
  head.appendChild(script);
};

HTMLArea.collectionToArray = function(collection)
{
  var array = [ ];
  for(var i = 0; i < collection.length; i++)
  {
    array.push(collection.item(i));
  }
  return array;
}

if(!Array.prototype.append)
{
  Array.prototype.append  = function(a)
  {
    for(var i = 0; i<a.length;i++)
    {
      this.push(a[i]);
    }
    return this;
  }
}



HTMLArea.init();
// EOF
// Local variables: //
// c-basic-offset:8 //
// indent-tabs-mode:t //
// End: //
