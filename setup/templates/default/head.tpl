<!DOCTYPE html PUBLIC "-//W3C//DTD XHTML 1.0 Transitional//EN" "http://www.w3.org/TR/xhtml1/DTD/xhtml1-transitional.dtd">
<html xmlns="http://www.w3.org/1999/xhtml">
	<head>
		<title>eGroupWare Setup - {lang_setup} {configdomain}</title>
		<meta http-equiv="content-type" content="text/html; charset={charset}" />
		<meta name="keywords" content="egroupware" />
		<meta name="description" content="egroupware" />
		<meta name="keywords" content="egroupware" />
		<meta name="copyright" content="egroupware http://www.egroupware.org (c) 2003" />
		<meta name="language" content="{lang_code}" />
		<meta name="author" content="egroupware http://www.egroupware.org" />
		<meta name="robots" content="none" />
		<link rel="icon" href="../phpgwapi/templates/default/images/favicon.ico" type="image/x-ico" />
		<link rel="shortcut icon" href="../phpgwapi/templates/default/images/favicon.ico" />
		<link href="../phpgwapi/templates/idots/css/idots.css" type="text/css" rel="stylesheet" />

		<!--{java_script}-->

		<!-- this solves the internet explorer png-transparency bug, but only for ie 5.5 and 6.0 -->
		<!--[if lt IE 7.0]>
		<script src="../phpgwapi/templates/idots/js/pngfix.js" type=text/javascript>
		</script>
		<![endif]-->

	</head>
	<body>

<div id="divLogo"><a href="http://www.egroupware.org" target="_blank"><img src="../phpgwapi/templates/default/images/logo.png" border="0" alt="egroupware" /></a></div>

<div id="divMain">
	<div id="divAppIconBar">
		<table width="100%" border="0" cellspacing="0" cellpadding="0">
			<tr>
				<td width="180"></td>
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
			</tr>
		</table>
	</div>
	<br />
<!--	<div id="divstatusbar"><table width="100%" cellspacing="0" cellpadding="0" border="0"><tr><td align="left" id="user_info">{user_info}</td><td align="right" id="admin_info">{current_users}</td></tr></table></div>-->
	<div id="divSubContainer">
		<table width="100%" cellspacing="0" cellpadding="0">
			<tr>
				<!-- sidebox column -->
				<td id="tdSidebox" valign="top">
					<div id="thesideboxcolumn" style="width:203px">

					<div class="divSidebox">
						<div class="divSideboxHeader"><span>{main_menu}</span></div>
						<div>
							<table width="100%" cellspacing="0" cellpadding="0">

								<tr class="divSideboxEntry">
									<td width="20" align="center" valign="middle" class="textSidebox"><img src="../phpgwapi/templates/idots/images/orange-ball.png" alt="ball" /></td><td class="textSidebox"><a class="textsidebox" href="../home/index.php">{user_login}</a></td>
								</tr>
<!-- BEGIN loged_in -->
								<tr class="divSideboxEntry">
									<td width="20" align="center" valign="middle" class="textSidebox"><img src="../phpgwapi/templates/idots/images/orange-ball.png" alt="ball" /></td><td class="textSidebox">{check_install}</td>
								</tr>

								<tr class="divSideboxEntry">
									<td width="20" align="center" valign="middle" class="textSidebox">{indeximg}</td><td class="textSidebox">{indexbutton}</td>
								</tr>

								<tr class="divSideboxEntry">
									<td width="20" align="center" valign="middle" class="textSidebox"><img src="../phpgwapi/templates/idots/images/orange-ball.png" alt="ball" /></td><td class="textSidebox">{logoutbutton}</td>
								</tr>
<!-- END loged_in -->
								<tr class="divSideboxEntry">
									<td colspan="2" class="textSidebox">&nbsp;</td>
								</tr>
								<tr class="divSideboxEntry">
									<td width="20" align="center" valign="middle" class="textSidebox"><img src="../phpgwapi/templates/idots/images/orange-ball.png" alt="ball" /></td><td class="textSidebox">{manual}</td>
								</tr>
							</table>
						</div>
					</div>
					<div class="sideboxSpace"></div>

					</div>
				</td>
				<!-- end sidebox column -->

				<!-- applicationbox column -->
				<td id="tdAppbox" valign="top">
				<div id="divAppboxHeader">{lang_setup} {configdomain}</div>
				<div id="divAppbox">
