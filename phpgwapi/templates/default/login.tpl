<!-- BEGIN login_form -->
<html>
<head>
 <title>{website_title} - Login</title>
</head>

<body bgcolor="FFFFFF">
<p>&nbsp;</p>
<center>{lang_message}</center>
<p>&nbsp;</p>

<table bgcolor="000000" border="0" cellpadding="0" cellspacing="0" width="40%" align="center">
 <tr>
  <td>
   <table border="0" width="100%" bgcolor="486591" cellpadding="2" cellspacing="1">
    <tr bgcolor="486591">
     <td align="left" valign="center">
      <a href="http://www.phpgroupware.org"><img src="phpgwapi/templates/{template_set}/images/logo.gif" alt="phpGroupWare"  border="0"></a>
     </td>
    </tr>
    <tr bgcolor="e6e6e6">
     <td valign="baselines">

      <form method="post" action="{login_url}">
       <table border="0" align="center" bgcolor="486591" width="100%" cellpadding="0" cellspacing="0">
        <tr bgcolor="e6e6e6">
         <td colspan="2" align="center">
          {cd}
         </td>
        </tr>
        <tr bgcolor="e6e6e6">
         <td align="right"><font color="000000">{lang_username}:&nbsp;</font></td>
         <td><input name="login" value="{cookie}"></td>
        </tr>
        <tr bgcolor="e6e6e6">
         <td align="right"><font color="000000">{lang_password}:&nbsp;</font></td>
         <td><input name="passwd" type="password"></td>
        </tr>
        <tr bgcolor="e6e6e6">
         <td colspan="2" align="center">
          <input type="submit" value="{lang_login}" name="submit">
         </td>
        </tr>
        <tr bgcolor="e6e6e6">
         <td colspan="2" align="right">
          <font color="000000" size="-1">{version}</font>
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

</html>
<!-- END login_form -->
