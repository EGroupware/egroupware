<!-- BEGIN form -->
<center>
 {message}
 <form name="form" action="{actionurl}" method="POST">
  <input type="hidden" name="server_id" value="{server_id}">
  <table border="0" width="80%" cellspacing="2" cellpadding="2"> 
   <tr class="row_on">
    <td>{lang_name}:</td>
    <td><input name="server_name" size="50" value="{server_name}"></td>
   </tr>
   <tr class="row_off">
    <td>{lang_url}:</td>
    <td><input name="server_url" size="50" value="{server_url}"></td>
   </tr>
   <tr class="row_on">
    <td>{lang_mode}:</td>
    <td>{server_mode}</td>
   </tr>
   <tr class="row_off">
    <td>{lang_security}:</td>
    <td>{server_security}&nbsp;{ssl_note}</td>
   </tr>
   <tr class="row_on">
    <td>{lang_trust}:</td>
    <td>{trust_level}</td>
   </tr>
   <tr class="row_off">
    <td>{lang_relationship}:</td>
    <td>{trust_relationship}</td>
   </tr>
   <tr class="row_on">
    <td>{lang_username}:</td>
    <td><input name="server_username" size="30" value="{server_username}"></td>
   </tr>
   <tr class="row_off">
    <td>{lang_password}:</td>
    <td><input type="password" name="server_password" size="30" value="">&nbsp;{pass_note}</td>
   </tr>
   <tr class="row_on">
    <td>{lang_admin_name}:</td>
    <td><input name="admin_name" size="50" value="{admin_name}"></td>
   </tr>
   <tr class="row_off">
    <td>{lang_admin_email}:</td>
    <td><input name="admin_email" size="50" value="{admin_email}"></td>
   </tr>

   <tr>
    <td nowrap>
     <input type="submit" name="save" value="{lang_save}"> &nbsp;
     <input type="submit" name="done" value="{lang_done}">
    </td>
<!-- BEGIN delete -->
    <td align="right">
     <input type="submit" name="delete" value="{lang_delete}">
    </td>
<!-- END delete -->
   </tr>
  
  </table>
 </form>
</center>
<!-- END form -->
