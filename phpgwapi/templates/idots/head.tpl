<!-- BEGIN head -->
<!DOCTYPE html PUBLIC "-//W3C//DTD XHTML 1.1//EN"
"http://www.w3.org/TR/xhtml11/DTD/xhtml11.dtd">
<html xml:lang="nl" xmlns="http://www.w3.org/1999/xhtml">
	<head>
		<!--
		HTML Coding Standards;

		1. use lowercase is possible, because of xhtml validation
		2. make your template validate either html 4.01 or xhtml 1
		3. make your application validat both if possible
		4. always use "" when possible (please help me I don't know the English word)
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
		<SCRIPT src="{pngfix}" type=text/javascript>
		</SCRIPT>
		<![endif]-->

	</head>
	<!-- we don't need body tags anymore, do we?) -->
	<!--<body {body_tags}>-->
	<body>
<!-- END Head -->
