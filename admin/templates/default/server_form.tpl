<!-- BEGIN form -->
 <center>
  <table border="0" width="80%" cellspacing="2" cellpadding="2">
   <tr>
    <td colspan="1" align="center" bgcolor="#c9c9c9"><b>{title_servers}<b/></td>
   </tr>
  </table>
  {message}
  <table border="0" width="80%" cellspacing="2" cellpadding="2"> 
  <form name="form" action="{actionurl}" method="POST">
   <tr>
    <td>{lang_name}:</td>
    <td><input name="server_name" size="50" value="{server_name}"></td>
   </tr>
   <tr>
    <td>{lang_url}:</td>
    <td><input name="server_url" size="50" value="{server_url}"></td>
   </tr>
   <tr>
    <td>{lang_mode}:</td>
    <td>{server_mode}</td>
   </tr>
   <tr>
    <td>{lang_security}:</td>
    <td>{server_security}&nbsp;{ssl_note}</td>
   </tr>
   <tr>
    <td>{lang_trust}:</td>
    <td>{trust_level}</td>
   </tr>
   <tr>
    <td>{lang_relationship}:</td>
    <td>{trust_relationship}</td>
   </tr>
   <tr>
    <td>{lang_username}:</td>
    <td><input name="server_username" size="30" value="{server_username}"></td>
   </tr>
   <tr>
    <td>{lang_password}:</td>
    <td><input type="password" name="server_password" size="30" value="">&nbsp;{pass_note}</td>
   </tr>
   <tr>
    <td>{lang_admin_name}:</td>
    <td><input name="admin_name" size="50" value="{admin_name}"></td>
   </tr>
   <tr>
    <td>{lang_admin_email}:</td>
    <td><input name="admin_email" size="50" value="{admin_email}"></td>
   </tr>
  </table>

<!-- BEGIN add -->
  <table width="50%" border="0" cellspacing="2" cellpadding="2">
   <tr valign="bottom">
    <td height="50" align="center">
     <input type="submit" name="submit" value="{lang_add}"></td>
    <td height="50" align="center">
     <input type="reset" name="reset" value="{lang_reset}"></form></td>
    <td height="50" align="center">
     <form method="POST" action="{doneurl}">
     <input type="submit" name="done" value="{lang_done}"></form></td>
   </tr>
  </table>
 </center>
<!-- END add -->

<!-- BEGIN edit -->
  <table width="50%" border="0" cellspacing="2" cellpadding="2">
   <tr valign="bottom">
    <td height="50" align="center">
     <input type="submit" name="submit" value="{lang_edit}"></form></td>
    <td height="50" align="center">
     <form method="POST" action="{deleteurl}">
     <input type="submit" name="delete" value="{lang_delete}"></form></td>
    <td height="50" align="center">
     <form method="POST" action="{doneurl}">
     <input type="submit" name="done" value="{lang_done}"></form></td>
   </tr>
  </table>
 </center>
<!-- END edit -->

<!-- END form -->
