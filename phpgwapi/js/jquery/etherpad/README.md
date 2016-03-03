# Description

The Etherpad jQuery Plugin easily allows you to embed and access a pad from Etherpad in a web page.  The plugin injects the pad contents into a div using iframes.  It can also read the contents of a Pad and write it to a div.

# Usage & Examples
<p>Include jQuery.js, include etherpad.js, assign a pad to a div.  If you get confused look at the examples in index.html</p>

### Sets the pad id and puts the pad in the div
`$('#examplePadBasic').pad({'padId':'test'});`
<div id="examplePadBasic"></div>

### Sets the pad id, some more parameters and puts the pad in the div
`$('#examplePadBasic').pad({'padId':'test','showChat':true});`
<div id="examplePadIntense"></div>

### Sets the pad id, some plugin parameters and puts the pad in the div
`$('#examplePadPlugins').pad({'padId':'test','plugins':{'pageview':'true'}});`
<div id="examplePadPlugins"></div>

### Gets the padContents from Example #2 and writes it to the target div "exampleGetContents"
`$('#examplePadBasic').pad({'getContents':'exampleGetContents'});`

# Available options and parameters
<pre>
'host'             : 'http://beta.etherpad.org', // the host and port of the Etherpad instance, by default the foundation will host your pads for you
'baseUrl'          : '/p/', // The base URL of the pads
'showControls'     : false, // If you want to show controls IE bold, italic, etc.
'showChat'         : false, // If you want to show the chat button or not
'showLineNumbers'  : false, // If you want to show the line numbers or not
'userName'         : 'unnamed', // The username you want to pass to the pad
'useMonospaceFont' : false, // Use monospaced fonts
'noColors'         : false, // Disable background colors on author text
'userColor'        : false, // The background color of this authors text in hex format IE #000
'hideQRCode'       : false, // Hide QR code
'alwaysShowChat'   : false, // Always show the chat on the UI
'width'            : 100, // The width of the embedded IFrame
'height'           : 100,  // The height of the embedded IFrame
'border'           : 0,    // The width of the border (make sure to append px to a numerical value)
'borderStyle'      : 'solid', // The CSS style of the border	[none, dotted, dashed, solid, double, groove, ridge, inset, outset]
'plugins'          : {}, // The options related to the plugins, not to the basic Etherpad configuration
'rtl'              : false // Show text from right to left
</pre>

# Copyright
jQuery Etherpad plugin written by John McLear (c) Primary Technology 2011<br/>
Development funded by the Etherpad Foundation.
Feel free to re-use, distribute, butcher, edit and whatever else you want.
It's under the Apache licence.
