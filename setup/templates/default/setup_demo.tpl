<!-- BEGIN setup_demo -->
<table border="1" width="100%" cellspacing="0" cellpadding="2">
  <tr>
    <td>{description}</td>
  </tr>
  <tr>
    <td align="left" bgcolor="486591"><font color="fefefe">{detailadmin}</td><td align="right" bgcolor="486591">&nbsp;
    </td>
  </tr>
  <tr>
    <td>
      <form method="POST" action="{action_url}">
        <table border="0">
          <tr>
            <td>{adminusername}</td>
            <td><input type="text" name="username"></td>
          </tr>
          <tr>
            <td>{adminfirstname}</td>
            <td><input type="text" name="fname"></td>
          </tr>
          <tr>
            <td>{adminlastname}</td>
            <td><input type="text" name="lname"></td>
          </tr>
          <tr>
            <td>{adminpassword}</td>
            <td><input type="password" name="passwd"></td>
          </tr>
          <tr>
            <td>{adminpassword2}</td>
            <td><input type="password" name="passwd2"></td>
          </tr>
          <tr>
            <td>{create_demo_accounts}</td>
            <td><input type="checkbox" name="create_demo" checked></td>
          </tr>
          <tr>
            <td colspan="2"><input type="submit" name="submit" value="{lang_submit}"> </td>
          </tr>
        </table>
      </form>
    </td>
  </tr>
</table>
<!-- END setup_demo -->
