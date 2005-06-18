/*
	DynAPI Distribution
	FlashSound Class - for sonifying web pages with the flash player
	Based on the FlashSound API (1.8) Copyright 2001 Hayden Porter, hayden@aviarts.com

	For more information please see the FlashSound Quick Reference

	The DynAPI Distribution is distributed under the terms of the GNU LGPL license.
	
	Requires: DynLayer
	
*/

function FlashSound(swfURL,loop,autostart){

	this.DynLayer = DynLayer;
	this.DynLayer(null,-10,-10,1,1);
	
	// add layer to document
	dynapi.document.addChild(this);
	
	// instance properties
	this.playerID = this.id + "SWF";
	FlashSound.players[FlashSound.players.length] = this;
	
	// instance embed properties
	this.autostart = true;
	this.base = null;
	this.bgcolor = null;
	this.loop = (loop)? loop:false;
	this.src = null;
	
	// setup flash plugin
	this.setSWF(swfURL);	
	
};


/* ============== FlashSound Instance methods =============== */

/*
javascript embed ---------------------------------
embeds swf if user has a supported browser and minimum player.
script sets swf bgcolor attribute to document.bgcolor if no custom color specified.
*/
var p = dynapi.setPrototype('FlashSound','DynLayer');

p._recognizeMethod = function (objstr){
	// check for player readiness ----------------------
	// check for javascript DOM object first then check to see if any frames are loaded in maintimeline
	if(typeof(this.doc[this.playerID][objstr]) == "undefined") return false;
	else return true;
};

p._checkForInstance = function() {
	if(!FlashSound.supportedBrowser || !FlashSound.checkForMinPlayer()) return false;
	if (this.doc[this.playerID] == null) return false;
	return true;
};

// Public Methods -------------------

p.isPlayerReady = function(){
	if(!FlashSound.engage) return false;
	if(!this._checkForInstance()) return false;
	// block browsers that do not recognize Flash javascript methods
	if(!this._recognizeMethod("PercentLoaded")) return false;
	if(this.percentLoaded() > 0) return true;
	return false;
};

p.getFramesLoaded = function(target){
	if(!this._checkForInstance()) {return 0;}
	if(target == null) target = "/";
	var framesloaded = this.doc[this.playerID].TGetProperty(target,12);
	return parseInt(framesloaded);
};

p.getTotalFrames = function(target){
	if(!this.isPlayerReady()) {return 0;}
	if(target == null) target = "/";
	var totalframes = this.doc[this.playerID].TGetProperty(target,5);
	return parseInt(totalframes);
};

// check to see if all frames are loaded for a given timeline.
// check before moving playhead to a frame/label incase the frame/label is not yet loaded.
p.isLoaded = function(target){
	if(!this.isPlayerReady()) return false;
	if(target == null) target = "/";
	if (this.getFramesLoaded(target) == this.getTotalFrames(target)) return true;
	return false;
};

/* flash javascript api functions ------------------------ */

p.gotoAndPlay = function(target,frame){
	if(!this.isPlayerReady()) return;
	if(typeof(frame) == "number") {
		this.doc[this.playerID].TGotoFrame(target,frame - 1);
		this.doc[this.playerID].TPlay(target);
	}
	if(typeof(frame) == "string") {
		this.doc[this.playerID].TGotoLabel(target,frame);
		this.doc[this.playerID].TPlay(target);
	}
};

p.gotoAndStop = function(target,frame){
	if(!this.isPlayerReady()) return;
	if(typeof(frame) == "number") {
		this.doc[this.playerID].TGotoFrame(target,frame - 1);
	}
	if(typeof(frame) == "string") {
		this.doc[this.playerID].TGotoLabel(target,frame);
	}
};

// Is Playing (IsPlaying)
p.isPlaying = function(){
	if(!this.isPlayerReady()) return false;
	return this.doc[this.playerID].IsPlaying();
};

// Load Movie
p.loadMovie = function(layerNumber,url){					
	if(!this.isPlayerReady()) return;
	this.doc[this.playerID].LoadMovie(layerNumber,url);
};

p.percentLoaded = function(){
	if(!this._checkForInstance()) return 0;
	var percentLoaded = this.doc[this.playerID].PercentLoaded();
	return parseInt(percentLoaded);
};

p.play  = function(target){
	if(!this.isPlayerReady()) return;
	if(target == null) target = "/";
	this.doc[this.playerID].TPlay(target);
};

// Set SWF
p.setSWF = function(swfURL){
	if (!FlashSound.supportedBrowser || !FlashSound.checkForMinPlayer()) return;
	
	var defaultColor = (document.bgColor != null) ? document.bgColor : "#ffffff";
	var defaultBase = ".";
	swfURL=(swfURL)? swfURL:'';
	this.bgcolor = (this.bgcolor == null) ? defaultColor : this.bgcolor;
	this.base = (this.base == null) ? defaultBase : this.base; 
	this.src = (swfURL.charAt(0) == "/") ? "http://" + location.host+swfURL : swfURL;
	this.setHTML(
		'<OBJECT ' +
			'CLASSID="clsid:D27CDB6E-AE6D-11cf-96B8-444553540000" ' +
 			'CODEBASE="http://download.macromedia.com/pub/shockwave/cabs/flash/swflash.cab" ' +
 			'WIDTH="1" ' +
			'HEIGHT="1" ' +
			'ID="' + this.playerID + '">\n' +
    		'<PARAM NAME="movie" VALUE="' + this.src + '">\n' +
			'<PARAM NAME="play" VALUE="' + this.autostart + '">\n' +
			'<PARAM NAME="loop" VALUE="' + this.loop + '">\n' +
    		'<PARAM NAME="quality" VALUE="low">\n' +
    		'<PARAM NAME="wmode" VALUE="transparent">\n' +
    		'<PARAM NAME="bgcolor" VALUE="' + this.bgcolor + '">\n' +
			'<PARAM NAME="base" VALUE="' + this.base + '">\n' +
    		'<EMBED \n' +
				'name="' + this.playerID + '"\n' +
				'swLiveConnect="true"\n' +
				'src="' + this.src + '\"' + '\n' +
				'play="' + this.autostart + '\"' + '\n' +
				'loop="' + this.loop + '\"' + '\n' +
				'quality="low"\n' +
				'wmode="transparent"\n' +
				'base="' + this.base + '"\n' +
				'bgcolor="' + this.bgcolor + '"\n' +
				'WIDTH="1"\n' +
				'HEIGHT="2"\n' +
				'TYPE="application/x-shockwave-flash"\n' +			
				'PLUGINSPAGE="http://www.macromedia.com/shockwave/download/index.cgi?P1_Prod_Version=ShockwaveFlash">\n' +
    		'</EMBED>\n' +
  		'</OBJECT>'
  	);
};

// Stop Play (TStopPlay)
p.stopPlay = function(target){
	if(!this.isPlayerReady()) return;
	if(target == null) target = "/";
	this.doc[this.playerID].TStopPlay(target);
};


/* Static Functions & Properties --------------------------- */

var fs = FlashSound;
fs.engage = true;			// engage
fs.playerCount = 0;			// player count
fs.players = new Array();	// players[]
fs.playerVersion = 0; 		// set playerVersion to 0 for unsupported browsers

										
// check for LiveConnect - Opera version 6.x with Java Enabled and Netscape versions 4.x but not greater		
fs.LiveConnect = ((navigator.javaEnabled() && 
				 (dynapi.ua.ns4 && dynapi.ua.v<5)) || dynapi.ua.opera6);
						
// browser compatibility check -----------------
fs.supportedBrowser = ((dynapi.ua.ie && dynapi.ua.win32) || 
						dynapi.ua.ns6 || FlashSound.LiveConnect) ? true : false;

// player compatibility  ------------------

// checkForMinPlayer sets playerVersion for supported browsers
fs.checkForMinPlayer = function(){
	if(!this.supportedBrowser) return false;
	if(dynapi.ua.ns6) {
		// xpconnect works with version 6 r40 or greater
		this.playerVersion = this.getPlugInVers(); // get version
		releaseVers = this.getPlugInReleaseVers(); // get release version	
		//check release vers only for vers 6
		if(this.playerVersion == 6 && releaseVers >=40) return true; 
	}
	
	if(this.LiveConnect) this.playerVersion = this.getPlugInVers();
	if(dynapi.ua.ie) this.playerVersion = (Flash_getActiveXVersion());
	
	if(this.playerVersion >= this.minPlayer) return true;
	else return false;
};

// check for flash plug-in in netscape
fs.checkForPlugIn = function(){
	var flashmimeType = "application/x-shockwave-flash";
	var hasplugin = (navigator.mimeTypes && navigator.mimeTypes[flashmimeType]) ? navigator.mimeTypes[flashmimeType].enabledPlugin : 0;
	return hasplugin;
};

// Get Plugin Version
fs.getPlugInVers = function(){
	if(this.checkForPlugIn()){
		var plugin = navigator.mimeTypes["application/x-shockwave-flash"].enabledPlugin;
		var pluginversion = parseInt(plugin.description.substring(plugin.description.indexOf(".")-1));
		return pluginversion;
	}else{
		return 0;
	}
};

// Get Plugin Release Version
fs.getPlugInReleaseVers = function(){
	if(this.checkForPlugIn()){
		var plugin = navigator.mimeTypes["application/x-shockwave-flash"].enabledPlugin;
		var pluginversion = parseInt(plugin.description.substring(plugin.description.indexOf("r")+1, plugin.description.length));
		return pluginversion;
	}else{
		return 0;
	} 
};

// vers is integer
fs.setMinPlayer = function(vers){
	if(!this.supportedBrowser) return;
	this.minPlayer = (vers != null && vers >= 4) ? vers : 4;
	if(dynapi.ua.ns6) { 
		this.minPlayer = 6; // set min player to 6 for XPConnect
	}
	this.checkForMinPlayer();
};

// vbscript get Flash ActiveX control version for windows IE
if(dynapi.ua.ie && dynapi.ua.win32){
	var h='<scr' + 'ipt language="VBScript">' + '\n' +
	'Function Flash_getActiveXVersion()' + '\n' +
		'On Error Resume Next' + '\n' +
		'Dim hasPlayer, playerversion' + '\n' +
		'hasPlayer = false' + '\n' +
		'playerversion = 15' + '\n' +
		'Do While playerversion > 0' + '\n' +
			'hasPlayer = (IsObject(CreateObject(\"ShockwaveFlash.ShockwaveFlash.\" & playerversion)))' + '\n' +
			'If hasPlayer Then Exit Do' + '\n' +
			'playerversion = playerversion - 1' + '\n' +
		'Loop' + '\n' +
		'Flash_getActiveXVersion = playerversion' + '\n' +
	'End Function' + '\n' +
	'<\/scr' + 'ipt>';
	
	if(!dynapi.loaded) document.write(h);
	else {
		dynapi.document.addChild(new DynLayer({w:0,h:0,visible:false,html:'<iframe name="FSVBS"></iframe'}));
		var elm=document.frames['FSVBS'];
		var doc = elm.document;
		doc.open();doc.write(h);doc.close();
		dynapi.frame.Flash_getActiveXVersion = function() {
			return elm.Flash_getActiveXVersion();
		};
	}
};

// set minimum player version - default is 4
fs.setMinPlayer();
