<!-- BEGIN manageheader -->
{detected}
  <tr bgcolor="486591"><th colspan="2"><font color="fefefe">{lang_settings}</font></th></tr>
   <form action="manageheader.php" method="post">
    <input type="hidden" name="setting[write_config]" value="true">
    <tr>
    <td colspan="2"><b>{lang_serverroot}</b>
      <br><input type="text" name="setting[server_root]" size="80" value="{server_root}"></td>
  </tr>
  <tr>
    <td colspan="2"><b>{lang_includeroot}</b><br><input type="text" name="setting[include_root]" size="80" value="{include_root}"></td>
  </tr>
  <tr>
    <td colspan="2"><b>{lang_adminpass}</b><br><input type="text" name="setting[HEADER_ADMIN_PASSWORD]" size="80" value="{header_admin_password}"></td>
  </tr>
    <br><br>
  <tr>
    <td><b>{lang_dbhost}</b><br><input type="text" name="setting[db_host]" value="{db_host}"></td><td>Hostname/IP of database server</td>
  </tr>
  <tr>
    <td><b>{lang_dbname}</b><br><input type="text" name="setting[db_name]" value="{db_name}"></td><td>Name of database</td>
  </tr>
  <tr>
    <td><b>{lang_dbuser}</b><br><input type="text" name="setting[db_user]" value="{db_user}"></td><td>Name of db user phpGroupWare uses to connect</td>
  </tr>
  <tr>
    <td><b>{lang_dbpass}</b><br><input type="text" name="setting[db_pass]" value="{db_pass}"></td><td>Password of db user</td>
  </tr>
  <tr>
    <td><b>{lang_dbtype}</b><br>
      <select name="setting[db_type]">
{dbtype_options}
      </select>
    </td>
	<td>{lang_whichdb}</td>
  </tr>
  <tr>
    <td><b>{lang_configpass}</b><br><input type="text" name="setting[config_pass]" value="{config_passwd}"></td>
    <td>{lang_passforconfig}</td>
  </tr>
  <tr>
    <td><b>{lang_persist}</b><br>
      <select type="checkbox" name="setting[db_persistent]">
        <option value="True"{db_persistent_yes}>True</option>
        <option value="False"{db_persistent_no}>False</option>
      </select>
    </td>
    <td>{lang_persistdescr}</td>
  </tr>
  <tr>
    <td><b>{lang_sesstype}</b><br>
      <select name="setting[sessions_type]">
{session_options}
      </select>
    </td>
    <td>{lang_sesstypedescr}</td>
  </tr>
  <tr>
    <td colspan=2><b>{lang_enablemcrypt}</b><br>
      <select name="setting[enable_mcrypt]">
        <option value="True"{mcrypt_enabled}>True
        <option value="False"{mcrypt_disabled}>False
      </select>
    </td>
  </tr>
  <tr>
    <td><b>{lang_mcryptversion}</b><br><input type="text" name="setting[mcrypt_version]" value="{mcrypt"></td>
    <td>{lang_mcryptversiondescr}</td>
  </tr>
  <tr>
    <td><b>{lang_mcryptiv}</b><br><input type="text" name="setting[mcrypt_iv]" value="{mcrypt_iv}" size="30"></td>
    <td>{lang_mcryptivdescr}</td>
  </tr>
  <tr>
    <td><b>{lang_domselect}</b><br>
      <select name="setting[domain_selectbox]">
        <option value="True"{domain_selectbox_yes}>True</option>
        <option value="False"{domain_selectbox_no}>False</option>
      </select></td><td>&nbsp;
    </td>
  </tr>
</table>
{errors}
{formend}
 <form action="index.php" method="post">
  <br>{lang_finaldescr}<br>
  <input type="hidden" name="FormLogout"  value="header">
  <input type="hidden" name="FormLogout"  value="config">
  <input type="hidden" name="ConfigLogin" value="Login">
  <input type="hidden" name="FormPW"      value="{FormPW}">
  <input type="hidden" name="FormDomain"  value="{FormDomain}">
  <input type="submit" name="junk"        value="continue">
 </form>
</body>
</html>
