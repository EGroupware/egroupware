<!DOCTYPE html PUBLIC "-//W3C//DTD XHTML 1.0 Transitional//EN" "http://www.w3.org/TR/xhtml1/DTD/xhtml1-transitional.dtd">
<html>
<head>
<meta http-equiv="Content-Type" content="text/html; charset={charset}" />
<meta name="AUTHOR" content="eGroupware http://www.egroupware.org" />
<meta name="description" content="eGroupware login screen" />
<meta name="ROBOTS" content="NOINDEX, NOFOLLOW" />
<meta name="keywords" content="eGroupware login screen">
<link rel="stylesheet" href="phpgwapi/templates/{template_set}/css/idots.css" type="text/css">	
<title>{website_title} - Login</title>
</head>
<body bgcolor="#FFFFFF">
<br />
<a href="{logo_url}"><img src="{logo_file}" alt="{logo_title}" title="{logo_title}" border="0"></a>
<center>{lang_message}</center>
<p>&nbsp;</p>
<form name="login_form" method="post" action="{login_url}">
	<input type="hidden" name="passwd_type" value="text">
    <input type="hidden" name="account_type" value="u">
<table class=sidebox cellSpacing=1 cellPadding=0  border=0  align=center>
<tr>
<td class="sideboxtitle" align="center"  height=28>{website_title}</td>
</tr>
<tr> 
<td class="sideboxcontent" bgColor="#efefef">

<table class="sideboxtext" cellSpacing=0 cellPadding=0 width="100%" border="0">
<tr bgcolor="#e6e6e6">
<td colspan="3" align="center">
{cd}
<br />
<img width="300" height="1" src="phpgwapi/templates/{template_set}/images/spacer.gif" alt="">
</td>
</tr>
<tr bgcolor="#e6e6e6">
<td colspan="3"> <input type="hidden" name="passwd_type" value="text"> </td>
</tr>
<tr bgcolor="#e6e6e6">
<td align="right"><font color="#000000">{lang_username}:&nbsp;</font></td>
<td align="left"><input name="login" value="{cookie}" style="width: 100px; border: 1px solid silver;"></td>
<td align="left">&nbsp;</td>
</tr>
<tr bgcolor="#e6e6e6">
<td align="RIGHT"><font color="#000000">{lang_password}:&nbsp;</font></td>
<td align="left"><input name="passwd" type="password" onChange="this.form.submit()" style="WIDTH: 100px; border: 1px solid silver;"></TD>
<td>&nbsp;</td>
</tr>
<tr bgcolor="#e6e6e6">
<td colspan="3" align="CENTER">
&nbsp;
</td>
</tr>
<tr bgcolor="#e6e6e6">
<td colspan="3" align="CENTER">
<input type="submit" value="{lang_login}" name="submitit" style="border: 1px solid silver;">
</td>
</tr>
<tr bgcolor="#e6e6e6">
<td colspan="3" align="CENTER">
&nbsp;
</td>
</tr>
<!--
<tr bgcolor="#e6e6e6">
<td colspan="3" align="RIGHT">
<font color="#000000" size="-1">eGroupWare {version}</font>
</td>
</tr>
-->
</table>

</td>
</tr>
</table>
</form>
<script language="javascript1.2">
<!--
// position cursor in top form field
document.login_form.login.focus();
//-->
</script>
<div style="bottom:10px;left:10px;position:absolute;visibility:hidden;">
<img src="phpgwapi/templates/{template_set}/images/valid-html401.png" border="0" alt="Valid HTML 4.01">
<img src="phpgwapi/templates/{template_set}/images/vcss.png" border="0" alt="Valid CSS">
</div>
<div style="bottom:10px;right:10px;position:absolute;">
<a href="http://www.egroupware.org" target="_blank">eGroupWare</a> {version}</div>
</body>
</html>
