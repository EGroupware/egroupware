  {error}
  <table border="0" width="50%" align="center">
   <form action="{form_action}" method="POST">
    {hidden_vars}
    <tr>
     <td>{lang_group_name}</td>
     <td><input name="n_group" value="{group_name_value}"></td>
    </tr>

    <tr>
     <td>{lang_include_user}</td>
     <td><select name="n_users[]" multiple size="{select_size}">
          {user_list}
         </select>
     </td>
    </tr>

    <tr>
     <td>{lang_permissions}</td>
     <td><select name="n_group_permissions[]" multiple size="5">
          {permissions_list}
         </select>
     </td>
    </tr>

    <tr>
     <td colspan="2" align="center">
      <input type="submit" name="submit" value="{lang_submit_button}">
     </td>
    </tr>
   </form>
  </table>
