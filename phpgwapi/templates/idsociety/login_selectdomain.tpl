<!DOCTYPE HTML PUBLIC "-//W3C//DTD HTML 4.01 Transitional//EN">
<html>
<head>
<meta http-equiv="Content-Type" content="text/html; charset=iso-8859-1">
<meta name="AUTHOR" content="phpGroupWare http://www.phpgroupware.org">
<meta name="description" content="phpGroupWare">
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
   <form method="post" action="{login_url}">
    <table border="0" align="CENTER" bgcolor="#adadad" width="100%" cellpadding="0" cellspacing="0">
     <tr>
      <td colspan="3" align="CENTER">
       {cd}
      </td>
     </tr>
     <tr>
      <td align="RIGHT"><font color="#000000">{lang_username}:</font></td>
      <td align="RIGHT"><input name="login" value="{cookie}"></td>
      <td align="LEFT">&nbsp;&nbsp;<select name="logindomain">{select_domain}</select></td>
     </tr>
     <tr>
      <td align="RIGHT"><font color="#000000">{lang_password}:</font></td>
      <td align="RIGHT"><input name="passwd" type="password" onChange="this.form.submit()"></td>
      <td>&nbsp;</td>
     </tr>
     <tr>
      <td colspan="3" align="CENTER">
       <input type="submit" value="{lang_login}" name="submitit">
      </td>
     </tr>
     <tr>
      <td colspan="3" align="RIGHT">
       <font color="#000000" size="-1">{version}</font>
      </td>
     </tr>       
    </table>
    </form>
  </td>
 </tr>
   </table>
  </td>
 </tr>
</table>

<!-- END login_form -->
</html>
