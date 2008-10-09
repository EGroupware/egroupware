<!-- BEGIN manageheader -->

<script language="JavaScript" type="text/javascript">
	<!--
{js_default_db_ports}
function setDefaultDBPort(selectBox,portField)
{
	//alert("select: " + selectBox + "; portField: " + portField);
	if(selectBox.selectedIndex != -1 && selectBox.options[selectBox.selectedIndex].value)
	{
		//alert("value = " + selectBox.options[selectBox.selectedIndex].value);
		portField.value = default_db_ports[selectBox.options[selectBox.selectedIndex].value];
	}
	return false;
}
//-->
</script>

<form name="domain_settings" action="manageheader.php" method="post">
	<table border="0" width="90%" id="tbl_manage_header" style="border-spacing: 3px;" align="center">
		<tbody>
			{lang_select}
			<tr>
				<td colspan="2">
					<h3>{pagemsg}</h3>
					<h2>{lang_analysis}</h2>
					{detected}
				</td>
			</tr>
			<tr class="th">
				<th colspan="2">{lang_settings}</th>
			</tr>
			<tr class="row_on">
				<td colspan="2">
					<strong>{lang_serverroot}</strong> {lang_serverroot_descr}<br />
					<input type="text" name="setting[server_root]" size="80" value="{server_root}" />
				</td>
			</tr>
			<tr class="row_off">
				<td><strong>{lang_adminuser}</strong><br /><input type="text" name="setting[header_admin_user]" size="30" value="{header_admin_user}" /></td>
				<td>{lang_adminuser_descr}</td>
			</tr>
			<tr class="row_on">
				<td><strong>{lang_adminpass}</strong><br />
					<input type="hidden" name="setting[header_admin_password]" value="{header_admin_password}" />
					<input type="password" name="setting[new_admin_password]" size="30" value="" /></td>
				<td>{lang_adminpass_descr}<br />{lang_leave_empty}</td>
			</tr>
			<tr class="row_off">
				<td><strong>{lang_setup_acl}</strong><br /><input type="text" name="setting[setup_acl]" size="30" value="{setup_acl}" /></td>
				<td>{lang_setup_acl_descr}</td>
			</tr>
			<tr class="row_on">
				<td><strong>{lang_persist}</strong><br />
					<select name="setting[db_persistent]">
						<option value="True"{db_persistent_yes}>{lang_Yes}</option>
						<option value="False"{db_persistent_no}>{lang_No}</option>
					</select>
				</td>
				<td>{lang_persistdescr}</td>
			</tr>
			<tr class="row_off">
				<td><strong>{lang_session}</strong><br />
					<select name="setting[session_handler]">
						{session_options}
					</select>
				</td>
				<td>{lang_session_descr}</td>
			</tr>
			<tr class="row_on">
				<td><strong>{lang_enablemcrypt}</strong><br />
					<select name="setting[mcrypt_enabled]">
						<option value="True"{mcrypt_enabled_yes}>{lang_Yes}</option>
						<option value="False"{mcrypt_enabled_no}>{lang_No}</option>
					</select>
				</td>
				<td>{lang_mcrypt_warning}</td>
			</tr>
			<tr class="row_off">
				<td><strong>{lang_mcryptiv}</strong><br /><input type="text" name="setting[mcrypt_iv]" value="{mcrypt_iv}" size="35" /></td>
				<td>{lang_mcryptivdescr}</td>
			</tr>
			<tr class="row_on">
				<td><strong>{lang_domselect}</strong><br />
					<select name="setting[show_domain_selectbox]">
						<option value="True"{show_domain_selectbox_yes}>{lang_Yes}</option>
						<option value="False"{show_domain_selectbox_no}>{lang_No}</option>
				</select></td>
				<td>{lang_domselect_descr}</td>
			</tr>
{domains}
			{comment_l}
			<tr class="th">
				<td  style="padding:3px;" colspan="2"><input type="submit" name="adddomain" value="{lang_adddomain}" /></td>
			</tr>
			{comment_r}
			<tr>
				<td colspan="2">{actions}</td>
			</tr>
		</tbody>
	</table>
</form>

<form action="index.php" method="post">
	<table border="0" width="90%" align="center" cellspacing="0" cellpadding="0">
		<tbody>
			<tr>
				<td>
					<br />{lang_finaldescr}<br />
					<input type="hidden" name="FormLogout"  value="header" />
					<input type="hidden" name="ConfigLogin" value="Login" />
					<input type="hidden" name="FormUser"    value="{FormUser}" />
					<input type="hidden" name="FormPW"      value="{FormPW}" />
					<input type="hidden" name="FormDomain"  value="{FormDomain}" />
					<input type="submit" name="junk"        value="{lang_continue}" />
				</td>
			</tr>
		</tbody>
	</table>
</form>
<!-- END manageheader -->

<!-- BEGIN domain -->
				<tr class="th">
					<td>{lang_domain}:</td>
					<td><input name="domains[{db_domain}]" value="{db_domain}" />&nbsp;&nbsp;<input type="checkbox" name="deletedomain[{db_domain}]" />&nbsp;{lang_delete}</td>
				</tr>
				<tr class="row_on">
					<td><strong>{lang_dbtype}</strong><br />
						<select name="setting_{db_domain}[db_type]" onchange="setDefaultDBPort(this,this.form['setting_{db_domain}[db_port]']);">
							{dbtype_options}
						</select>
					</td>
					<td>{lang_whichdb}</td>
				</tr>
				<tr class="row_off">
					<td><strong>{lang_dbhost}</strong><br /><input type="text" name="setting_{db_domain}[db_host]" value="{db_host}" /></td><td>{lang_dbhostdescr}</td>
				</tr>
				<tr class="row_on">
					<td><strong>{lang_dbport}</strong><br /><input type="text" name="setting_{db_domain}[db_port]" value="{db_port}" /></td><td>{lang_dbportdescr}</td>
				</tr>
				<tr class="row_off">
					<td><strong>{lang_dbname}</strong><br /><input type="text" name="setting_{db_domain}[db_name]" value="{db_name}" /></td><td>{lang_dbnamedescr}</td>
				</tr>
				<tr class="row_on">
					<td><strong>{lang_dbuser}</strong><br /><input type="text" name="setting_{db_domain}[db_user]" value="{db_user}" /></td><td>{lang_dbuserdescr}</td>
				</tr>
				<tr class="row_off">
					<td><strong>{lang_dbpass}</strong><br /><input type="password" name="setting_{db_domain}[db_pass]" value="{db_pass}" /></td><td>{lang_dbpassdescr}</td>
				</tr>
				<tr class="row_on">
					<td><strong>{lang_configuser}</strong><br /><input type="text" name="setting_{db_domain}[config_user]" value="{config_user}" /></td>
					<td>{lang_configuser_descr}</td>
				</tr>
				<tr class="row_off">
					<td><strong>{lang_configpass}</strong><br />
						<input type="hidden" name="setting_{db_domain}[config_passwd]" value="{config_passwd}" />
						<input type="password" name="setting_{db_domain}[new_config_passwd]" value="" /></td>
					<td>{lang_passforconfig}<br />{lang_leave_empty}</td>
				</tr>
<!-- END domain -->
