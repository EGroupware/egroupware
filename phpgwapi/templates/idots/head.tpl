<!-- BEGIN head -->
<!DOCTYPE html PUBLIC "-//W3C//DTD XHTML 1.1//EN" "http://www.w3.org/TR/xhtml11/DTD/xhtml11.dtd">
<html xml:lang="nl" xmlns="http://www.w3.org/1999/xhtml">
	<head>
		<!--
		HTML Coding Standards:
		1. use lowercase is possible, because of xhtml validation
		2. make your template validate either html 4.01 or xhtml 1
		3. make your application validat both if possible
		4. always use quotionmarks when possible e.g. <img src="/path/to/image" class="class" alt="this is an image :)" />
		5. use png-graphics if possible, but keep in ming IE has a transparency bug when it renders png's
		-->

		<!-- LAY-OUT BUGS 
		1. in IE no link cursor is displayd when for png's that link
		2. tabs are ugly in preferences
		3. spacers inside sidebox
		-->
		<title>{website_title}</title>
		<meta http-equiv="content-type" content="text/html; charset={charset}" />
		<meta name="keywords" content="eGroupWare" />
		<meta name="description" content="eGroupware" />
		<meta name="keywords" content="eGroupWare" />
		<meta name="copyright" content="eGroupWare http://www.egroupware.org (c) 2003" />
		<meta name="language" content="en" />
		<meta name="author" content="eGroupWare http://www.egroupware.org" />
		<meta name="robots" content="none" />
		<link rel="icon" href="{img_icon}" type="image/x-ico" />
		<link rel="shortcut icon" href="{img_shortcut}" />
		<link href="{theme_css}" type="text/css" rel="StyleSheet" />
		{css}
		{java_script}

		<!-- This solves the Internet Explorer PNG-transparency bug, but only for IE 5.5 and higher --> 
		<!--[if gte IE 5.5000]>
		<script src="{pngfix}" type=text/javascript>
		</script>
		<![endif]-->

		<script language="Javascript" type="text/javascript">
		/*
			javascript for showing and hiding layers starting with the one in the iconbar
		*/
		
		// Javascript Browser Sniff 1.0 Jim Cummins - http://www.conxiondesigns.com	
		
		var isIE = false;
		var isOther = false;
		var isNS4 = false;
		var isNS6 = false;
		if(document.getElementById)
		{
			if(!document.all)
			{
				isNS6=true;
			}
			if(document.all)
			{
				isIE=true;
			}
		}
		else
		{
			if(document.layers)
			{
				isNS4=true;
			}
			else
			{
				isOther=true;
			}
		}

		// End of Browser Sniff 1.0


		// Access Layer Style Properties Jim Cummins - http://www.conxiondesigns.com
		
		function aLs(layerID)
		{
		var returnLayer;
			if(isIE)
			{
				returnLayer = eval("document.all." + layerID + ".style");
			}
			if(isNS6)
			{
				returnLayer = eval("document.getElementById('" + layerID + "').style");
			}
			if(isNS4)
			{
				returnLayer = eval("document." + layerID);
			}
			if(isOther)
			{
				returnLayer = "null";
				alert("Error:\nDue to your browser you will probably not\nbe able to view all of the following page\nas it was designed to be viewed. We regret\nthis error sincerely.");
			}
		return returnLayer;
		}

		// HideShow 1.0	Jim Cummins - http://www.conxiondesigns.com
		function ShowL(ID)
		{
				aLs(ID).visibility = "visible";
		}
		
		function HideL(ID)
		{
				aLs(ID).visibility = "hidden";
		}

		function HideShow(ID)
		{
			if((aLs(ID).visibility == "visible") || (aLs(ID).visibility == ""))
			{
				aLs(ID).visibility = "hidden";
			}
			else if(aLs(ID).visibility == "hidden")
			{
				aLs(ID).visibility = "visible";
			}
		}
		</script>

	</head>
	<!-- we don't need body tags anymore, do we?) we do!!! onload!! LK -->
	<body {body_tags}>
<!-- END Head -->
