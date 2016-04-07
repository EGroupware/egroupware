jQuery - Tap and Hold
=====================

	This jQuery plugin lets you detect a tap and hold event on touch interfaces.

How to use it?

	1) Add the jQuery Tap and Hold plugin into your HTML

	<script src="jquery.tapandhold.js" type="text/javascript"></script>

	2) Bind a tap and hold handler function to the tap and hold event of an element.

	$("#myDiv").bind("taphold", function(event){
		alert("This is a tap and hold!");
	});

You can check a working example in examples/example1.html

