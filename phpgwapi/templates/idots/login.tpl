<!DOCTYPE HTML PUBLIC "-//W3C//DTD HTML 4.01 Transitional//EN">
<HTML>
<HEAD>
<META http-equiv="Content-Type" content="text/html; charset={charset}">
<META name="AUTHOR" content="eGroupware http://www.phpgroupware.org">
<META NAME="description" CONTENT="eGroupware login screen">
<META NAME="keywords" CONTENT="eGroupware login screen">
<link rel="stylesheet" href="phpgwapi/templates/{template_set}/css/idots.css" type="text/css">	
<TITLE>{website_title} - Login</TITLE>
</HEAD>
<BODY bgcolor="#FFFFFF">
<br>
<a href="http://www.lingewoud.nl"><img src="phpgwapi/templates/{template_set}/images/logo_egroupware.png" border="0" alt="eGroupWare"></a>
<CENTER>{lang_message}</CENTER>
<p>&nbsp;</p>
<TABLE class=sidebox cellSpacing=1 cellPadding=0  border=0  align=center>
<TR> 
<TD class="sideboxtitle" align="center"  height=28>{lang_title}</TD>
</TR>
<TR> 
<TD class="sideboxcontent" bgColor="#efefef">
<FORM method="post" action="{login_url}">

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
<td align="left"><input name="login" value="{cookie}" style="width: 100px;"></TD>
<TD align="left">&nbsp;</TD>
</TR>
<TR bgcolor="#e6e6e6">
<TD align="RIGHT"><font color="#000000">{lang_password}:&nbsp;</font></TD>
<td align="left"><input name="passwd" type="password" onChange="this.form.submit()" style="WIDTH: 100px;"></TD>
<td>&nbsp;</td>
</TR>
<TR bgcolor="#e6e6e6">
<TD colspan="3" align="CENTER">
<input type="submit" value="{lang_login}" name="submitit">
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
</FORM>

</TD>
</TR>
</TABLE>
<div style="bottom:10px;left:10px;position:absolute;">
<img src="phpgwapi/templates/{template_set}/images/valid-html401.png" border="0" alt="Valid HTML 4.01">
<img src="phpgwapi/templates/{template_set}/images/vcss.png" border="0" alt="Valid CSS">
</div>
<div style="bottom:10px;right:10px;position:absolute;">
<a href="http://www.egroupware.org" target="_blank">eGroupWare</a> {version}</div>
</body>
</html>
