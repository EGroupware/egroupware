<!DOCTYPE HTML PUBLIC "-//W3C//DTD HTML 4.01 Transitional//EN">
<html>
<head>
<meta http-equiv="Content-Type" content="text/html; charset=iso-8859-1">
<meta name="AUTHOR" content="phpGroupWare http://www.phpgroupware.org">
<meta name="description" content="phpGroupWare">
<META NAME="ROBOTS" CONTENT="NOINDEX, NOFOLLOW">
<meta name="keywords" content="phpGroupWare login screen">
<style type="text/css">
  a { text-decoration:none; }
  A:link{ text-decoration:none; color: #336699; }
  A:visted{ text-decoration:none; color: #336699; }
  A:active{ text-decoration:none; color: #ff0000; }
  A:hover{ text-decoration:none; color: #cc0000; }
  td {text-decoration:none; color: #ffffff; }
  body { margin-top: 0px; margin-right: 0px; margin-left: 0px; font-family: "Arial, Helvetica, san-serif" }
  .tablink { color: #000000; }
</style>
<title>{website_title} - Login</title>
</head>
<!-- idsociety body tags continue into navbar.tpl, so the closing bracket here is there END Head -->
<body bgcolor="#cccccc" alink="#ff0000" link="#336699" vlink="#336699">

<a href="http://www.phpgroupware.org"><img src="phpgwapi/templates/{template_set}/images/logo.gif" alt="phpGroupWare" border="0"></a>
<p>&nbsp;</p>
<center>{lang_message}</center>
<p>&nbsp;</p>

<table border="0" align="center" width="40%" cellspacing="0" cellpadding="0">
<tr>
  <td>
   <table border="0" width="100%" cellpadding="2" cellspacing="1">
 <tr bgcolor="#525252">
  <td align="left" valign="middle">&nbsp;phpGroupWare</td>
 </tr>
 <tr>
  <td valign="baseline">
  <form name="login" method="post" action="{login_url}">
  <input type="hidden" name="passwd_type" value="text">
  <input type="hidden" name="account_type" value="u">
   <table bgcolor="#adadad" border="0" align="center" width="100%" cellpadding="0" cellspacing="0">
    <tr>
     <td colspan="2" align="center">{cd}</td>
    </tr>
    <tr>
     <td align="right">{lang_username}:&nbsp;</td>
     <td><input name="login" value="{cookie}"></td>
    </tr>
    <tr>
     <td align="right">{lang_password}:&nbsp;</td>
     <td><input name="passwd" type="password"></td>
    </tr>
    <tr>
     <td colspan="2" align="center"><input type="submit" value="{lang_login}" name="submitit"></td>
    </tr>
    <tr>
     <td colspan="2" align="right">{version}</td>
    </tr>
   </table>
  </FORM>
  </td>
 </tr>
   </table>
  </td>
 </tr>
</table>

<!-- END login_form -->
</html>
