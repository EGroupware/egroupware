<!DOCTYPE HTML PUBLIC "-//W3C//DTD HTML 4.01 Transitional//EN">
<HTML>
<!-- BEGIN login_form -->
<HEAD>

<META http-equiv="Content-Type" content="text/html; charset={charset}">
<META name="AUTHOR" content="phpGroupWare http://www.phpgroupware.org">
<META NAME="description" CONTENT="phpGroupWare login screen">
<META NAME="keywords" CONTENT="phpGroupWare login screen">

<TITLE>{website_title} - Login</TITLE>
</HEAD>

<BODY bgcolor="#FFFFFF">
<p>&nbsp;</p>
<CENTER>{lang_message}</CENTER>
<p>&nbsp;</p>

<TABLE bgcolor="#000000" border="0" cellpadding="0" cellspacing="0" width="60%" align="CENTER">
 <TR>
  <TD>
   <TABLE border="0" width="100%" bgcolor="#486591" cellpadding="2" cellspacing="1">
    <TR bgcolor="#486591">
     <TD align="LEFT" valign="MIDDLE">
      <A href="http://www.phpgroupware.org"><img src="phpgwapi/templates/{template_set}/images/logo.gif" alt="phpGroupWare"  border="0"></a>
     </TD>
    </TR>
    <TR bgcolor="#e6e6e6">
     <TD valign="BASELINE">

      <FORM method="post" action="{login_url}">
       <TABLE border="0" align="CENTER" bgcolor="#486591" width="100%" cellpadding="0" cellspacing="0">
        <TR bgcolor="#e6e6e6">
         <TD colspan="3" align="CENTER">
          {cd}
         </TD>
        </TR>
        <TR bgcolor="#e6e6e6">
         <TD align="RIGHT"><font color="#000000">{lang_username}:</font></TD>
         <TD align="RIGHT"><input name="login" value="{cookie}"></TD>
         <TD align="LEFT">&nbsp;@&nbsp;<select name="logindomain">{select_domain}</select></TD>
        </TR>
        <TR bgcolor="#e6e6e6">
         <TD align="RIGHT"><font color="#000000">{lang_password}:</font></TD>
         <TD align="RIGHT"><input name="passwd" type="password"></TD>
         <TD>&nbsp;</TD>
        </TR>
        <TR bgcolor="#e6e6e6">
         <TD colspan="3" align="CENTER">
          <input type="submit" value="{lang_login}" name="submit">
         </TD>
        </TR>
        <TR bgcolor="#e6e6e6">
         <TD colspan="3" align="RIGHT">
          <font color="000000" size="-1">{version}</font>
         </TD>
        </TR>       
       </TABLE>
      </FORM>
     
     </TD>
    </TR>
   </TABLE>
  </TD>
 </TR>
</TABLE>

<!-- END login_form -->
</HTML>
