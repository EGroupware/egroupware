  {error}
  <table border="0" width="50%" align="center">
   <form action="{form_action}" method="POST">
    {hidden_vars}
    <tr>
     <td>{lang_group_name}</td>
     <td><input name="account_name" value="{group_name_value}"></td>
    </tr>

    <tr>
     <td>{lang_include_user}</td>
     <td><select name="account_user[]" multiple size="{select_size}">
          {user_list}
         </select>
     </td>
    </tr>

    <tr>
     <td>{lang_file_space}</td>
     <td>
	{account_file_space}{account_file_space_select}
     </td>
    </td>

    <tr>
     <td>{lang_permissions}</td>
     <td><table width="100%" border="0" cols="6">
      {permissions_list}
     </table></td>
    </tr>

    <tr>
     <td colspan="2" align="center">
      <input type="submit" name="submit" value="{lang_submit_button}">
     </td>
    </tr>
   </form>
  </table>
