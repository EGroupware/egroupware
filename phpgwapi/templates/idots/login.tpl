<!DOCTYPE HTML PUBLIC "-//W3C//DTD HTML 4.01 Transitional//EN">
<html>
<head>
<meta http-equiv="Content-Type" content="text/html; charset={charset}">
<meta name="author" content="eGroupWare http://www.phpgroupware.org">
<meta name="description" content="eGroupWare login screen">
<meta name="ROBOTS" CONTENT="NOINDEX, NOFOLLOW">
<meta name="keywords" content="eGroupWare login screen">
<link rel="stylesheet" href="phpgwapi/templates/{template_set}/css/idots.css" type="text/css">
<link rel="icon" href="phpgwapi/templates/idots/images/favicon.ico" type="image/x-ico">
<link rel="shortcut icon" href="phpgwapi/templates/idots/images/favicon.ico">
<title>{website_title} - Login</title>
<style type="text/css">

/*body 
{
	height:100%;
}
*/


#divMain
{
	height:85%;
}

</style>

		<!-- this solves the internet explorer png-transparency bug, but only for ie 5.5 and higher --> 
		<!--[if gte ie 5.5000]>
		<script src="./phpgwapi/templates/idots/js/pngfix.js" type=text/javascript>
		</script>
		<![endif]-->

</head>
<body bgcolor="#ffffff">
<div id="divLogo"><a href="http://{logo_url}" target="_blank"><img src="{logo_file}" border="0" alt="{logo_title}" title="{logo_title}"/></a></div>

<div id="divMain">
	<div id="divAppIconBar">
		<table width="100%" border="0" cellspacing="0" cellpadding="0">
			<tr>
				<td width="180" valign="top" align="left"><img src="./phpgwapi/templates/idots/images/grey-pixel.png" width="1" height="68" alt="spacer" /></td>
				<td>
					<table width="100%" border="0" cellspacing="0" cellpadding="0">
						<tr>
							<td width="100%"><img src="./phpgwapi/templates/idots/images/spacer.gif" width="1" height="68" alt="spacer" /></td>
						</tr>
						<tr>
							<td width="100%">&nbsp;</td>
						</tr>
					</table>

				</td>
				<td width="1" valign="top" align="right"><img src="./phpgwapi/templates/idots/images/grey-pixel.png" width="1" height="68" alt="spacer" /></td>
			</tr>
		</table>
	</div>
<br/>
<!--</div>-->
<div id="containerDiv">
<div id="centerBox">
<div align="center">{lang_message}</div>
<div align="center">{cd}</div>
<p>&nbsp;</p>
<form name="login_form" method="post" action="{login_url}">
<!-- <table class=sidebox cellspacing=1 cellpadding=0  border=1  align=center> -->


	<table class="divLoginbox" cellspacing="0" cellpadding="0" border="0" align="center">
	<tr> 
		<td colspan="2" class="divLoginboxHeader" style="border-bottom: #9c9c9c 1px solid;" align="center">{website_title}</td>
	</tr>
	<tr > 
		<td class="divSideboxEntry">

		<table  cellspacing=0 cellpadding=0 width="100%" border="0">
		<tr>
			<td colspan="3" align="center">
								<br>
				<img width="200" height="1" src="phpgwapi/templates/{template_set}/images/spacer.gif" alt="spacer" />
			</td>
		</tr>
		<tr>
			<td  colspan="3">
				<input type="hidden" name="passwd_type" value="text">
				<input type="hidden" name="account_type" value="u">
			</td>
		</tr>
		<tr>
			<td align="right">{lang_username}:&nbsp;</td>
			<td align="left"><input name="login" value="{cookie}" style="width: 100px; border: 1px solid silver;"></td>
			<td align="left">{select_domain}</td>
		</tr>
		<tr>
			<td align="right">{lang_password}:&nbsp;</td>
			<td align="left"><input name="passwd" type="password" onChange="this.form.submit()" style="width: 100px; border: 1px solid silver;"></td>
			<td>&nbsp;</td>
		</tr>
		<tr>
			<td colspan="3" align="center">
			&nbsp;
			</td>
		</tr>
		<tr>
			<td colspan="3" align="center">
				<input type="submit" value="{lang_login}" name="submitit" style="border: 1px solid silver;">
			</td>
		</tr>
		<tr>
			<td colspan="3" align="center">
			&nbsp;
			</td>
		</tr>
		</table>

	</td>
	<td class="divSideboxEntry">
	<img src="phpgwapi/templates/{template_set}/images/password.png" alt="keys" />
</td>
</tr>
</table>
<p>&nbsp;</p>
<p>&nbsp;</p>
<p>&nbsp;</p>
</form>
<script language="javascript1.2" type="text/javascript">
<!--
// position cursor in top form field
document.login_form.login.focus();
//-->
</script>


</div>
</div>
</div>
<div style="bottom:10px;left:10px;position:absolute;visibility:hidden;">
<img src="phpgwapi/templates/{template_set}/images/valid-html401.png" border="0" alt="Valid HTML 4.01">
<img src="phpgwapi/templates/{template_set}/images/vcss.png" border="0" alt="Valid CSS">
</div>
<div style="bottom:10px;right:10px;position:absolute;">
<a href="http://www.egroupware.org" target="_blank">eGroupWare</a> {version}</div>
</body>
</html>
