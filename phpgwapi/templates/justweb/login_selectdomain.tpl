<!-- BEGIN login_form -->
<html>
<head>
 <title>{website_title} - Login</title>
</head>

<body bgcolor="#FFFFFF">
 <a href="http://www.phpgroupware.org"><img src="phpGroupWare.jpg" alt="phpGroupWare" border="0"></a>
<p>&nbsp;</p>
<center>{lang_message}</center>
<p>&nbsp;</p>

<table bgcolor="#000000" border="0" cellpadding="0" cellspacing="0" width="60%" align="center">
 <tr>
  <td>
   <table border="0" width="100%" bgcolor="#486591" cellpadding="2" cellspacing="1">
    <tr bgcolor="#486591">
     <td align="left">
      <font color="#fefefe">&nbsp;{lang_phpgw_login}</font>
     </td>
    </tr>
    <tr bgcolor="#e6e6e6">
     <td valign="baselines">

      <form method="post" action="{login_url}">
       <input type="hidden" name="passwd_type" value="text">
       <input type="hidden" name="account_type" value="u">
       <table border="0" align="center" bgcolor="#486591" width="100%" cellpadding="0" cellspacing="0">
        <tr bgcolor="#e6e6e6">
         <td colspan="3" align="center">
          {cd}
         </td>
        </tr>
        <tr bgcolor="#e6e6e6">
         <td align="right"><font color="#000000">{lang_username}:</font></td>
         <td align="right"><input name="login" value="{cookie}"></td>
         <td align="left">&nbsp;@&nbsp;<select name="logindomain">{select_domain}</select></td>
        </tr>
        <tr bgcolor="#e6e6e6">
         <td align="right"><font color="#000000">{lang_password}:</font></td>
         <td align="right"><input name="passwd" type="password" onChange="this.form.submit()"></td>
         <td>&nbsp;</td>
        </tr>
        <tr bgcolor="#e6e6e6">
         <td colspan="3" align="center">
          <input type="submit" value="{lang_login}" name="submitit">
         </td>
        </tr>
        <tr bgcolor="#e6e6e6">
         <td colspan="3" align="right">
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

</html>
<!-- END login_form -->
