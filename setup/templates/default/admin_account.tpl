<!-- BEGIN admin_account -->
<form method="post" action="{action_url}">
<table border="0" width="90%" cellspacing="0" cellpadding="2">
  <tr>
    <td>
     <p><b>{description}</b></p>
	 <p><input type="checkbox" name="delete_all" />{lang_deleteall}</p>
	 <font color="red">{error}</font>
    </td>
  </tr>
  <tr>
    <td align="left" bgcolor="#cccccc">{detailadmin}</td>
  </tr>
  <tr>
    <td>
	<table border="0">
          <tr>
            <td>{adminusername}</td>
            <td colspan="2"><input type="text" name="username" value="{username}" /></td>
          </tr>
          <tr>
            <td>{adminfirstname}</td>
            <td colspan="2"><input type="text" name="fname" value="{fname}" /></td>
          </tr>
          <tr>
            <td>{adminlastname}</td>
            <td colspan="2"><input type="text" name="lname" value="{lname}" /></td>
          </tr>
          <tr>
            <td>{adminemail}</td>
            <td colspan="2"><input type="text" name="email" value="{email}" /></td>
          </tr>
          <tr>
            <td>{adminpassword}</td>
            <td colspan="2"><input type="password" name="passwd" /></td>
          </tr>
          <tr>
            <td>{adminpassword2}</td>
            <td colspan="2"><input type="password" name="passwd2" /></td>
          </tr>
          <tr>
            <td>{admin_all_apps}</td>
            <td><input type="checkbox" name="admin_all_aps" /></td>
            <td >{all_apps_desc}</td>
          </tr>
          <tr>
            <td>{create_demo_accounts}</td>
            <td><input type="checkbox" name="create_demo" /></td>
            <td >{demo_desc}</td>
         </tr>
 	      <tr>
          </tr>
          <tr>
            <td><input type="submit" name="submit" value="{lang_submit}" /></td>
            <td colspan="2"><input type="submit" name="cancel" value="{lang_cancel}" /></td>
          </tr>
        </table>
    </td>
  </tr>
</table>
</form>
<!-- END admin_account -->
