<p><b>{lang_action}</b><hr><br>
{error_messages}

 <form method="POST" action="{form_action}">
  <center>
   <table border=0 width=85%>
    <tr bgcolor="{th_bg}">
      <td colspan="4">&nbsp;</td>
    </tr>

    <tr bgcolor="{tr_color1}">
     <td>{lang_loginid}</td>
     <td><input name="account_lid" value="{account_lid}"></td>
     <td colspan="2" align="center">
       {lang_account_active}:&nbsp;<input type="checkbox" name="account_status" value="A"{account_status}>
     </td>
    </tr>

    <tr bgcolor="{tr_color2}">
     <td>{lang_firstname}</td>
     <td><input name="account_firstname" value="{account_firstname}"></td>
     <td>{lang_lastname}</td>
     <td><input name="account_lastname" value="{account_lastname}"></td>
    </tr>

    <tr bgcolor="{tr_color1}">
     <td>{lang_password}</td>
     <td><input type="password" name="account_passwd" value="{account_passwd}"></td>
     <td>{lang_reenter_password}</td>
     <td><input type="password" name="account_passwd_2" value="{account_passwd_2}"></td>
    </tr>

 
    <tr bgcolor="{tr_color2}">
     <td>{lang_groups}</td>
     <td>{groups_select}&nbsp;</td>
     <td colspan=2>&nbsp;</td>
    </tr>

    {permissions_list}
    
    {gui_hooks}

    <tr bgcolor="{tr_color2}">
     <td colspan="4" align="right"><input type="submit" name="submit" value="{lang_button}"></td>
    </tr>
   </table>
  </center>
 </form>
