  {error}
  <table border="0" width="60%" align="center">
   <tr>
    <td valign="top">
     {rows}
    </td>
    <td valign="top">
     <table border=0 width=100%>
      <form action="{form_action}" method="POST" name="app_form">
       {hidden_vars}
       <tr>
        <td>{lang_group_name}</td>
        <td><input name="account_name" value="{group_name_value}" style="width: 300px;"></td>
       </tr>

       <tr>
        <td>{lang_include_user}</td>

        <td>
         {accounts}
        </td>
       </tr>
       
       <tr>
        <td>{lang_email}</td>
        <td>{email}</td>
      </tr>

       <tr>
        <td>{lang_file_space}</td>
        <td>
           {account_file_space}{account_file_space_select}
        </td>
       </tr>

       <tr>
        <td>{lang_permissions}</td>
        <td><table width="100%" border="0" cols="6">
         {permissions_list}
        </table></td>
       </tr>

       <tr>
        <td colspan="2" align="center">
         <input type="submit" name="edit" value="{lang_submit_button}">
        </td>
       </tr>
      </form>
     </table>
    </td>
   </tr>
  </table>

<!-- BEGIN select -->

        <select name="account_user[]" multiple size="{select_size}">
             {user_list}
            </select>

<!-- END select -->

<!-- BEGIN popwin -->

		<table>
			<tr>
				<td>
					<select name="account_user[]" multiple size="{select_size}">{user_list}</select>
				</td>
				<td valign="top">
        			<input type="button" value="{lang_open_popup}" onClick="accounts_popup()">
					<input type="hidden" name="accountid" value="{accountid}">
				</td>
			</tr>
		</table>

<!-- END popwin -->
