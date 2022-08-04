<!DOCTYPE html PUBLIC "-//W3C//DTD XHTML 1.0 Transitional//EN" "http://www.w3.org/TR/xhtml1/DTD/xhtml1-transitional.dtd">
<html xmlns="http://www.w3.org/1999/xhtml">
	<head>
		<title>EGroupware Setup - {lang_setup} {configdomain}</title>
		<meta http-equiv="content-type" content="text/html; charset={charset}" />
		<meta name="keywords" content="egroupware" />
		<meta name="description" content="egroupware" />
		<meta name="keywords" content="egroupware" />
		<meta name="copyright" content="egroupware http://www.egroupware.org (c) 2012" />
		<meta name="language" content="{lang_code}" />
		<meta name="author" content="egroupware http://www.egroupware.org" />
		<meta name="robots" content="none" />
		<link rel="icon" href="../api/templates/default/images/favicon.ico" type="image/x-ico" />
		<link rel="shortcut icon" href="../api/templates/default/images/favicon.ico" />
		<link href="../api/templates/default/default.css" type="text/css" rel="stylesheet" />
		<link href="../api/templates/default/css/default.css" type="text/css" rel="stylesheet" />

		<!--{java_script}-->
	</head>
	<body>

<div id="divLogo"><a href="http://www.egroupware.org" target="_blank">
	<img src="../api/templates/default/images/logo.svg" border="0" alt="egroupware" width="200px"/>
</a></div>

<div id="divMain">
	<div id="divAppIconBar">
		<table width="100%" border="0" cellspacing="0" cellpadding="0">
			<tr>
				<td width="180"></td>
				<td>
					<table width="100%" border="0" cellspacing="0" cellpadding="0">
						<tr>
							<td width="100%"><img src="templates/default/images/spacer.gif" width="1" height="68" alt="spacer" /></td>
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
									<td width="20" align="center" valign="middle" class="textSidebox"><img src="templates/default/images/bullet.png" alt="ball" /></td><td class="textSidebox"><a class="textsidebox" href="../index.php">{user_login}</a></td>
								</tr>
<!-- BEGIN loged_in -->
								<tr class="divSideboxEntry">
									<td width="20" align="center" valign="middle" class="textSidebox"><img src="templates/default/images/bullet.png" alt="ball" /></td><td class="textSidebox">{check_install}</td>
								</tr>

								<tr class="divSideboxEntry">
									<td width="20" align="center" valign="middle" class="textSidebox">{indeximg}</td><td class="textSidebox">{indexbutton}</td>
								</tr>

								<tr class="divSideboxEntry">
									<td width="20" align="center" valign="middle" class="textSidebox"><img src="templates/default/images/bullet.png" alt="ball" /></td><td class="textSidebox">{register_hooks}</td>
								</tr>
								<tr class="divSideboxEntry">
									<td width="20" align="center" valign="middle" class="textSidebox"><img src="templates/default/images/bullet.png" alt="ball" /></td><td class="textSidebox">{logoutbutton}</td>
								</tr>
<!-- END loged_in -->
								<tr class="divSideboxEntry">
									<td colspan="2" class="textSidebox">&nbsp;</td>
								</tr>
							</table>
						</div>
						<div class="divSideboxHeader"><span>{help_menu}</span></div>
						<div>
							<table width="100%" cellspacing="0" cellpadding="0">
								<tr class="divSideboxEntry">
									<td width="20" align="center" valign="middle" class="textSidebox"><img src="templates/default/images/bullet.png" alt="ball" /></td>
									<td class="textSidebox"><a href="https://github.com/EGroupware/egroupware/wiki" target="_blank">{documentation}</a></td>
								</tr>
								<tr class="divSideboxEntry">
									<td width="20" align="center" valign="middle" class="textSidebox"><img src="templates/default/images/bullet.png" alt="ball" /></td>
									<td class="textSidebox"><a href="https://www.egroupware.org/egroupware-support/" target="_blank">{commercial_support}</a></td>
								</tr>
								<tr class="divSideboxEntry">
									<td width="20" align="center" valign="middle" class="textSidebox"><img src="templates/default/images/bullet.png" alt="ball" /></td>
									<td class="textSidebox"><a href="https://help.egroupware.org/" target="_blank">{community_forum}</a></td>
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