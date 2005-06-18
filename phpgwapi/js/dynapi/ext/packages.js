/*
	DynAPI Distribution
	Package File

	The DynAPI Distribution is distributed under the terms of the GNU LGPL license.
*/

var l = dynapi.library;
var p = dynapi.library.path;
l._pakLoaded=true;

l.addPackage('dynapi',p);
l.add('dynapi.library','ext/library.js');
l.add('dynapi.debug','ext/debug.js','dynapi.functions.Image');

// Functions
l.addPackage('dynapi.functions',p+'ext/');
l.add('dynapi.functions.Color','functions.color.js');
l.add('dynapi.functions.Math','functions.math.js');
l.add('dynapi.functions.Date','functions.date.js');
l.add('dynapi.functions.Numeric','functions.numeric.js');
l.add('dynapi.functions.String','functions.string.js');
l.add('dynapi.functions.System','functions.system.js');
if (dynapi.ua.ns4)
	l.add('dynapi.functions.Image','functions.image.js','MouseEvent'); // ns4 required MouseEvent for Image functions
else
	l.add('dynapi.functions.Image','functions.image.js'); // ns4 required MouseEvent for Image functions

// API - Core Events & DynDocument
l.addPackage('dynapi.api',p+'api/');
l.add(['dynapi.api.DynEvent','dynapi.api.EventObject','dynapi.api.DynElement'],'event.js');
l.add('dynapi.api.DynDocument','dyndocument.js','DynEvent');
	// DynLayer
l.add('dynapi.api.DynLayerBase','dynlayer_base.js','DynDocument');
if (dynapi.ua.ns4)
	l.add('dynapi.api.DynLayer','dynlayer_ns4.js','DynLayerBase');
else if (dynapi.ua.ie)
	l.add('dynapi.api.DynLayer','dynlayer_ie.js','DynLayerBase');
else if (dynapi.ua.opera)
	l.add('dynapi.api.DynLayer','dynlayer_opera.js','DynLayerBase');
else 
	l.add('dynapi.api.DynLayer','dynlayer_dom.js','DynLayerBase');
	// MouseEvent
if (dynapi.ua.ns4)
	l.add('dynapi.api.MouseEvent','mouse_ns4.js','DynLayer');
else if(dynapi.ua.ie|| (dynapi.ua.opera && dynapi.ua.v < 8))
	l.add('dynapi.api.MouseEvent','mouse_ie.js','DynLayer');
else
	l.add('dynapi.api.MouseEvent','mouse_dom.js','DynLayer');

// Extensions
l.addPackage('dynapi.api.ext',p+'api/ext/');
l.add('dynapi.api.ext.DragEvent','dragevent.js','DynDocument');
l.add(['dynapi.api.ext.DynKeyEvent','dynapi.api.ext.TabManager'],'dynkeyevent.js','DynLayer');
l.add('dynapi.api.ext.DynLayerInline','dynlayer.inline.js','DynLayer');

// FX
/*
l.addPackage('dynapi.fx',p+'fx/');
l.add('dynapi.fx.Thread','thread.js','DynLayer');
l.add('dynapi.fx.PathAnimation','pathanim.js','Thread');
l.add('dynapi.fx.SlideAnimation','slideanim.js','Thread');
l.add('dynapi.fx.GlideAnimation','glideanim.js',['Thread','dynapi.functions.Math']);
l.add('dynapi.fx.CircleAnimation','circleanim.js',['Thread','dynapi.functions.Math']);
l.add('dynapi.fx.HoverAnimation','hoveranim.js',['Thread','dynapi.functions.Math']);
l.add('dynapi.fx.Bezier','bezier.js','Thread');
l.add('dynapi.fx.TimerX','timerx.js','DynLayer');
l.add('dynapi.fx.MotionX','motionx.js','DynLayer');
l.add('dynapi.fx.SnapX','snapx.js','DynLayer');
l.add('dynapi.fx.FlashSound','fsound.js','DynLayer');
l.add('dynapi.fx.Fader','fader.js','DynLayer');
l.add('dynapi.fx.Swiper','swiper.js','DynLayer');
l.add('dynapi.fx.TextAnimation','textanim.js','DynLayer');
*/

// ThyAPI Packages
// ThyAPI Utils
l.addPackage('dynapi.thyutils', p+'thyutils/');
l.add('dynapi.thyutils.thyCollection','thycollection.js');
l.add('dynapi.thyutils.thyVisualCollection', 'thyvisualcollection.js', ['thyCollection']);
l.add('dynapi.thyutils.thyProtocol','thyprotocol.js');
l.add('dynapi.thyutils.thyXMLRPCProtocol','thyxmlrpcprotocol.js','thyProtocol');
l.add('dynapi.thyutils.thyConnector','thyconnector.js', 'thyXMLRPCProtocol');
l.add('dynapi.thyutils.thyDataSource','thydatasource.js', ['DynElement','thyConnector','thyCollection']);

//ThyAPI Widgets
l.addPackage('dynapi.thywidgets', p+'thywidgets/');
l.add('dynapi.thywidgets.thyPanelBase', 'thypanel.js', ['DynLayer', 'System', 'DynKeyEvent', 'thyCollection']);
if (dynapi.ua.ie) l.add('dynapi.thywidgets.thyPanel', 'thypanel_ie.js', 'thyPanelBase');
else if (dynapi.ua.ns4) l.add('dynapi.thywidgets.thyPanel', 'thypanel_ns4.js', 'thyPanelBase');
else if (dynapi.ua.opera) l.add('dynapi.thywidgets.thyPanel', 'thypanel_opera.js', 'thyPanelBase');
else l.add('dynapi.thywidgets.thyPanel', 'thypanel_dom.js', 'thyPanelBase');

l.add('dynapi.thywidgets.thyButton', 'thybutton.js', 'thyPanel');
l.add('dynapi.thywidgets.thyTabsManager', 'thytabsmanager.js', 'thyPanel');
l.add('dynapi.thywidgets.thyBorderPanel', 'thyborderpanel.js', 'thyPanel');
l.add('dynapi.thywidgets.thyLabelPanel', 'thylabelpanel.js', 'thyPanel');
l.add('dynapi.thywidgets.thyEditBox', 'thyeditbox.js', 'thyLabelPanel');
l.add('dynapi.thywidgets.thyCheckBox', 'thycheckbox.js', 'thyLabelPanel');
//l.add('dynapi.thywidgets.thyPopupCalendar', 'thypopupcalendar.js', ['thyEditBox','JSCalendarSetup','thyButton']);
l.add('dynapi.thywidgets.thyPopupCalendar', 'thypopupcalendar.js', ['thyEditBox','thyButton']);
l.add('dynapi.thywidgets.thyTextEdit', 'thytextedit.js', 'thyLabelPanel');
//l.add('dynapi.thywidgets.thyRichTextEdit', 'thyrichtextedit.js', ['thyLabelPanel', 'FCKeditor']);
l.add('dynapi.thywidgets.thyWindow', 'thywindow.js', ['thyBorderPanel','thyButton','DragEvent','dynapi.functions.String']);
l.add('dynapi.thywidgets.thyDialogWindow', 'thydialogwindow.js', ['thyWindow','thyButton']);
l.add('dynapi.thywidgets.thyGridCell', 'thygridcell.js', ['thyPanel','thyEditBox', 'thyCollection']);
l.add('dynapi.thywidgets.thyGridRow', 'thygridrow.js', ['thyGridCell', 'thyVisualCollection'])
l.add('dynapi.thywidgets.thyGridContents', 'thygridcontents.js', ['thyPanel']);
l.add('dynapi.thywidgets.thyGrid', 'thygrid.js', ['thyGridContents','thyLabelPanel','thyGridCell','thyGridRow','thyVisualCollection']);
l.add('dynapi.thywidgets.thyListBox', 'thylistbox.js', ['thyGrid']);
l.add('dynapi.thywidgets.thyDropDownBox', 'thydropdownbox.js', ['thyListBox','thyEditBox','thyButton']);

// ThyAPI External
//l.addPackage('dynapi.thywidgets.external', p+'thywidgets/external/');
//l.add('dynapi.thywidgets.external.FCKeditor', 'fckeditor/fckeditor.js');
//l.add('dynapi.thywidgets.external.JSCalendar', 'jscalendar/calendar.js');
//l.add('dynapi.thywidgets.external.JSCalendarLang', 'jscalendar/lang/calendar-en.js');
//l.add('dynapi.thywidgets.external.JSCalendarSetup', 'jscalendar/calendar-setup.js',['JSCalendar','JSCalendarLang']);

// Load buffered includes ---------
if(l._buffer){
	var i,ar=l._buffer;
	for(i=0;i<ar.length;i++) l.include(true,ar[i]); // pass arguments true and bufferedArguments 
	l._buffer=null;
}

