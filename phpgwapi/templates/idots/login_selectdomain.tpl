<!DOCTYPE HTML PUBLIC "-//W3C//DTD HTML 4.01 Transitional//EN">
<html>
<head>
<meta http-equiv="Content-Type" content="text/html; charset={charset}">
<meta name="author" content="eGroupWare http://www.phpgroupware.org">
<meta name="description" content="eGroupWare login screen">
<meta name="keywords" content="eGroupWare login screen">
<link rel="stylesheet" href="phpgwapi/templates/{template_set}/css/idots.css" type="text/css">	
<link rel="icon" href="phpgwapi/templates/idots/images/favicon.ico" type="image/x-ico">
<link rel="shortcut icon" href="phpgwapi/templates/idots/images/favicon.ico">
<title>{website_title} - Login</title>
<style type="text/css">
#containerDiv
{
	position:absolute;
	width:100%;
	left:0px;
	top:40%;
	vertical-align:	bottom;
}

#centerBox
{
	position:relative;
	width:100%;
	top:-80px;
	height:134px;
	z-index:9;
}

</style>
</head>
<body bgcolor="#ffffff">
<br>
<a href="http://{logo_url}"><img src="{logo_file}" alt="{logo_title}" title="{logo_title}" border="0"></a>
<div id="containerDiv">
<div id="centerBox">
<center>{lang_message}</center>
<p>&nbsp;</p>
<form name="login_form" method="post" action="{login_url}">
<table class=sidebox cellspacing=1 cellpadding=0  border=0  align=center>
<tr> 
<td class="sideboxtitle" align="center"  height=28>{website_title}</td>
</tr>
<tr> 
<td class="sideboxcontent" bgcolor="#efefef">

<table class="sideboxtext" cellspacing=0 cellpadding=0 width="100%" border="0">
<tr bgcolor="#e6e6e6">
<td colspan="3" align="center">
{cd}
<br>
<img width="300" height="1" src="phpgwapi/templates/{template_set}/images/spacer.gif" alt="">
</td>
</tr>
<tr bgcolor="#e6e6e6">
<td colspan="3"> <input type="hidden" name="passwd_type" value="text"> </td>
</tr>
<tr bgcolor="#e6e6e6">
<td align="right"><font color="#000000">{lang_username}:&nbsp;</font></td>
<td align="left"><input name="login" value="{cookie}" style="width: 100px; border: 1px solid silver;"></td>
<td align="left">&nbsp;</select></td>
</tr>
<tr bgcolor="#e6e6e6">
<td align="right"><font color="#000000">{lang_password}:&nbsp;</font></td>
<td align="left"><input name="passwd" type="password" onChange="this.form.submit()" style="WIDTH: 100px; border: 1px solid silver;"></td>
<td>&nbsp;</td>
</tr>
<tr bgcolor="#e6e6e6">
<td colspan="3" align="center">
&nbsp;
</td>
</tr>
<tr bgcolor="#e6e6e6">
<td colspan="3" align="center">
<input type="submit" value="{lang_login}" name="submitit" style="border: 1px solid silver;">
</td>
</tr>
<tr bgcolor="#e6e6e6">
<td colspan="3" align="center">
&nbsp;
</td>
</tr>
</table>

</td>
</tr>
</table>
</form>
<script language="javascript1.2" type="text/javascript">
<!--
// position cursor in top form field
document.login_form.login.focus();
//-->
</script>


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
