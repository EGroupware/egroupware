<!DOCTYPE HTML PUBLIC "-//W3C//DTD HTML 4.01 Transitional//EN">
<HTML>
<HEAD>
<META http-equiv="Content-Type" content="text/html; charset={charset}">
<META name="AUTHOR" content="eGroupware http://www.phpgroupware.org">
<META NAME="description" CONTENT="eGroupware login screen">
<META NAME="ROBOTS" CONTENT="NOINDEX, NOFOLLOW">
<META NAME="keywords" CONTENT="eGroupware login screen">
<link rel="stylesheet" href="phpgwapi/templates/{template_set}/css/idots.css" type="text/css">	
<TITLE>{website_title} - Login</TITLE>
</HEAD>
<BODY bgcolor="#FFFFFF">
<br>
<a href="http://{logo_url}"><img src="{logo_file}" alt="{logo_title}" title="{logo_title}" border="0"></a>
<CENTER>{lang_message}</CENTER>
<p>&nbsp;</p>
<FORM name="login_form" method="post" action="{login_url}">
	<input type="hidden" name="passwd_type" value="text">
    <input type="hidden" name="account_type" value="u">
<TABLE class=sidebox cellSpacing=1 cellPadding=0  border=0  align=center>
<TR> 
<TD class="sideboxtitle" align="center"  height=28>{website_title}</TD>
</TR>
<TR> 
<TD class="sideboxcontent" bgColor="#efefef">

<TABLE class="sideboxtext" cellSpacing=0 cellPadding=0 width="100%" border="0">
<TR bgcolor="#e6e6e6">
<TD colspan="3" align="center">
{cd}
<br>
<img width="300" height="1" src="phpgwapi/templates/{template_set}/images/spacer.gif" alt="">
</TD>
</TR>
<TR bgcolor="#e6e6e6">
<TD colspan="3"> <input type="hidden" name="passwd_type" value="text"> </TD>
</TR>
<TR bgcolor="#e6e6e6">
<td align="right"><font color="#000000">{lang_username}:&nbsp;</font></TD>
<td align="left"><input name="login" value="{cookie}" style="width: 100px; border: 1px solid silver;"></TD>
<TD align="left">&nbsp;</TD>
</TR>
<TR bgcolor="#e6e6e6">
<TD align="RIGHT"><font color="#000000">{lang_password}:&nbsp;</font></TD>
<td align="left"><input name="passwd" type="password" onChange="this.form.submit()" style="WIDTH: 100px; border: 1px solid silver;"></TD>
<td>&nbsp;</td>
</TR>
<TR bgcolor="#e6e6e6">
<TD colspan="3" align="CENTER">
&nbsp;
</TD>
</TR>
<TR bgcolor="#e6e6e6">
<TD colspan="3" align="CENTER">
<input type="submit" value="{lang_login}" name="submitit" style="border: 1px solid silver;">
</TD>
</TR>
<TR bgcolor="#e6e6e6">
<TD colspan="3" align="CENTER">
&nbsp;
</TD>
</TR>
<!--
<TR bgcolor="#e6e6e6">
<TD colspan="3" align="RIGHT">
<font color="#000000" size="-1">eGroupWare {version}</font>
</TD>
</TR>
-->
</TABLE>

</TD>
</TR>
</TABLE>
</FORM>
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
