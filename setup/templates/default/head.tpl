<!-- BEGIN head -->
<!DOCTYPE html PUBLIC "-//W3C//DTD XHTML 1.1//EN" "http://www.w3.org/TR/xhtml11/DTD/xhtml11.dtd">
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
		<title>eGroupWare Setup - {lang_setup} {configdomain}</title>
		<meta http-equiv="content-type" content="text/html; charset={charset}" />
		<meta name="keywords" content="egroupware" />
		<meta name="description" content="egroupware" />
		<meta name="keywords" content="egroupware" />
		<meta name="copyright" content="egroupware http://www.egroupware.org (c) 2003" />
		<meta name="language" content="en" />
		<meta name="author" content="egroupware http://www.egroupware.org" />
		<meta name="robots" content="none" />
		<link rel="icon" href="../phpgwapi/templates/default/images/favicon.ico" type="image/x-ico" />
		<link rel="shortcut icon" href="../phpgwapi/templates/default/images/favicon.ico" />
		<link href="../phpgwapi/templates/idots/css/idots.css" type="text/css" rel="stylesheet" />
		<!--
		{css}
		-->

		<style type="text/css">
			<!--
			.row_on { color: #000000; background-color: #eeeeee; }
			.row_off { color: #000000; background-color: #e8f0f0; }
			.th 
			{ 
			  color: #000000; 
			  background-color: #cccccc; 
			}

			-->	
		</style>
		
		<!--{java_script}-->
		
		<!-- this solves the internet explorer png-transparency bug, but only for ie 5.5 and higher --> 
		<!--[if gte ie 5.5000]>
		<script src="../phpgwapi/templates/idots/js/pngfix.js" type=text/javascript>
		</script>
		<![endif]-->

	</head>
	<body>

<div id="divLogo"><a href="http://www.egroupware.org" target="_blank"><img src="../phpgwapi/templates/idots/images/logo-setup.png" border="0" alt="egroupware"/></a></div>

<div id="divMain">
	<div id="divAppIconBar">
		<table width="100%" border="0" cellspacing="0" cellpadding="0">
			<tr>
				<td width="180" valign="top" align="left"><img src="../phpgwapi/templates/idots/images/grey-pixel.png" width="1" height="68" alt="spacer" /></td>
				<td>
					<table width="100%" border="0" cellspacing="0" cellpadding="0">
						<tr>
							<td width="100%"><img src="../phpgwapi/templates/idots/images/spacer.gif" width="1" height="68" alt="spacer" /></td>
						</tr>
						<tr>
							<td width="100%">&nbsp;</td>
						</tr>
					</table>

				</td>
				<td width="1" valign="top" align="right"><img src="../phpgwapi/templates/idots/images/grey-pixel.png" width="1" height="68" alt="spacer" /></td>
			</tr>
		</table>
	</div>
<!--	<div id="divstatusbar"><table width="100%" cellspacing="0" cellpadding="0" border="0"><tr><td align="left" id="user_info">{user_info}</td><td align="right" id="admin_info">{current_users}</td></tr></table></div>-->
	<div id="divSubContainer">
		<table width="100%" cellspacing="0" cellpadding="0">
			<tr>
				<!-- sidebox column -->
				<td id="tdSidebox" valign="top">
					
					
					<div class="divSidebox">
						<div class="divSideboxHeader"><span>setup main menu</span></div>
						<div>
							<table width="100%" cellspacing="0" cellpadding="0">
					
								<tr class="divSideboxEntry">
									<td width="20" align="center" valign="middle" class="textSidebox"><img src="../phpgwapi/templates/idots/images/orange-ball.png" alt="ball" /></td><td class="textSidebox"><a class="textsidebox" href="../home.php">back to user login</a></td>
								</tr>

								<tr class="divSideboxEntry">
					<td width="20" align="center" valign="middle" class="textSidebox"><img src="../phpgwapi/templates/idots/images/orange-ball.png" alt="ball" /></td><td class="textSidebox"><a class="textsidebox" href="check_install.php">check installation</a></td>
				</tr>

								<tr class="divSideboxEntry">
							<td width="20" align="center" valign="middle" class="textSidebox"><img src="../phpgwapi/templates/idots/images/orange-ball.png" alt="ball" /></td><td class="textSidebox"><!--<a class="textsidebox" href="check_install.php">check installation</a>-->{logoutbutton}</td>
						</tr>
									</table>	
						</div>
					</div>
					<div class="sideboxSpace"></div>

				</td>
				<!-- end sidebox column -->

				<!-- applicationbox column -->
				<td id="tdAppbox" valign="top">
				<div id="divAppboxHeader">{lang_setup} {configdomain}</div>
				<div id="divAppbox">


<!-- end head.tpl -->

