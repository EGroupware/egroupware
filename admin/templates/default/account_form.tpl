<p><b>{lang_action}</b><hr><br>
{error_messages}

 <form method="POST" action="{form_action}">
  <center>
   <table border=0 width=65%>
    <tr>
     <td>{lang_loginid}</td>
     <td><input name="n_loginid" value="{n_loginid_value}"></td>
    </tr>

    <tr>
     <td>{lang_password}</td>
     <td><input type="password" name="n_passwd" value="{n_passwd_value}"></td>
    </tr>

    <tr>
     <td>{lang_reenter_password}</td>
     <td><input type="password" name="n_passwd_2" value="{n_passwd_2_value}"></td>
    </tr>

    <tr>
     <td>{lang_firstname}</td>
     <td><input name="n_firstname" value="{n_firstname_value}"></td>
    </tr>

    <tr>
     <td>{lang_lastname}</td>
     <td><input name="n_lastname" value="{n_lastname_value}"></td>
    </tr>
 
    <tr>
     <td>{lang_groups}</td>
     <td>{groups_select}</td>
    </tr>

    {permissions_list}

     <tr>
      <td colspan="2" align="center"><input type="submit" name="submit" value="{lang_button}"></td>
     </tr>
   </table>
  </center>
 </form>
