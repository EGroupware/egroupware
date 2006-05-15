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

<table border="0" width="600px;" id="tbl_manage_header"  style="border-spacing:5px;" cellspacing="0" cellpadding="0" align="center">
	<tbody>
		{lang_select}
		<tr><td colspan="2"><h3>{pagemsg}</h3></td></tr>
		<tr class="th"><th colspan="2">{lang_analysis}</th>
		</tr>
		<tr>
			<td colspan="2">
				{detected}
			</td>
		</tr>
		<!--		<tr>
			<td>-->
				<form name="domain_settings" action="manageheader.php" method="post">
					<input type="hidden" name="setting[write_config]" value="true" />
					<!--					<table style="border-spacing:5px;" border="0" width="100%" cellspacing="0" cellpadding="0">
						<tbody>-->
							<tr class="th">
								<th colspan="2">{lang_settings}</th>
							</tr>
							<tr>
								<td colspan="2"><strong>{lang_serverroot}</strong><br /><input type="text" name="setting[server_root]" size="80" value="{server_root}" /></td>
							</tr>
							<tr>
								<td colspan="2"><strong>{lang_includeroot}</strong><br /><input type="text" name="setting[include_root]" size="80" value="{include_root}" /></td>
							</tr>
							<tr>
								<td><strong>{lang_adminuser}</strong><br /><input type="text" name="setting[HEADER_ADMIN_USER]" size="30" value="{header_admin_user}" /></td>
								<td>{lang_adminuser_descr}</td>
							</tr>
							<tr>
								<td><strong>{lang_adminpass}</strong><br /><input type="password" name="setting[HEADER_ADMIN_PASSWORD]" size="30" value="{header_admin_password}" /><input type="hidden" name="setting[HEADER_ADMIN_PASS]" value="{header_admin_pass}" /></td>
								<td>{lang_adminpass_descr} {lang_leave_empty}</td>
							</tr>
							<tr>
								<td><strong>{lang_setup_acl}</strong><br /><input type="text" name="setting[setup_acl]" size="30" value="{setup_acl}" /></td>
								<td>{lang_setup_acl_descr}</td>
							</tr>
							<tr>
								<td><strong>{lang_persist}</strong><br />
									<select name="setting[db_persistent]">
										<option value="True"{db_persistent_yes}>{lang_Yes}</option>
										<option value="False"{db_persistent_no}>{lang_No}</option>
									</select>
								</td>
								<td>{lang_persistdescr}</td>
							</tr>
							<tr>
								<td><strong>{lang_sesstype}</strong><br />
									<select name="setting[sessions_type]">
										{session_options}
									</select>
								</td>
								<td>{lang_sesstypedescr}</td>
							</tr>
							<tr>
								<td><strong>{lang_enablemcrypt}</strong><br />
									<select name="setting[enable_mcrypt]">
										<option value="True"{mcrypt_enabled_yes}>{lang_Yes}</option>
										<option value="False"{mcrypt_enabled_no}>{lang_No}</option>
									</select>
								</td>
								<td>{lang_mcrypt_warning}</td>
							</tr>
							<tr>
								<td><strong>{lang_mcryptversion}</strong><br /><input type="text" name="setting[mcrypt_version]" value="{mcrypt}" /></td>
								<td>{lang_mcryptversiondescr}</td>
							</tr>
							<tr>
								<td><strong>{lang_mcryptiv}</strong><br /><input type="text" name="setting[mcrypt_iv]" value="{mcrypt_iv}" size="35" /></td>
								<td>{lang_mcryptivdescr}</td>
							</tr>
							<tr>
								<td><strong>{lang_domselect}</strong><br />
									<select name="setting[domain_selectbox]">
										<option value="True"{domain_selectbox_yes}>{lang_Yes}</option>
										<option value="False"{domain_selectbox_no}>{lang_No}</option>
								</select></td>
								<td>{lang_domselect_descr}</td>
							</tr>
							<tr>
								<td colspan="2">
									{domains}
								</td>
							</tr>
							{comment_l}
							<tr class="th">
								<td  style="padding:3px;" colspan="2"><input type="submit" name="adddomain" value="{lang_adddomain}" /></td>
							</tr>
							{comment_r}
							<tr>
								<td colspan="2">{errors}</td>
							</tr>
							<!--						</tbody>
					</table>-->
				</form>
				<!--			</td>
		</tr>-->

		<tr>
			<td colspan="2">
				<form action="index.php" method="post">
					<table border="0" width="100%" cellspacing="0" cellpadding="0">
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
			</td>
		</tr>
	</tbody>
</table>
<!-- END manageheader -->

<!-- BEGIN domain -->
<table class="table_domains">
	<tr class="th">
		<td colspan="2" style="text-align:center;padding:3px;">{lang_domain}:&nbsp;<input name="domains[{db_domain}]" value="{db_domain}" />&nbsp;&nbsp;<input type="checkbox" name="deletedomain[{db_domain}]" />&nbsp;{lang_delete}</td>
	</tr>
	<tr>
		<td><strong>{lang_dbtype}</strong><br />
			<select name="setting_{db_domain}[db_type]" onchange="setDefaultDBPort(this,this.form['setting_{db_domain}[db_port]']);">
				{dbtype_options}
			</select>
		</td>
		<td>{lang_whichdb}</td>
	</tr>
	<tr>
		<td><strong>{lang_dbhost}</strong><br /><input type="text" name="setting_{db_domain}[db_host]" value="{db_host}" /></td><td>{lang_dbhostdescr}</td>
	</tr>
	<tr>
		<td><strong>{lang_dbport}</strong><br /><input type="text" name="setting_{db_domain}[db_port]" value="{db_port}" /></td><td>{lang_dbportdescr}</td>
	</tr>
	<tr>
		<td><strong>{lang_dbname}</strong><br /><input type="text" name="setting_{db_domain}[db_name]" value="{db_name}" /></td><td>{lang_dbnamedescr}</td>
	</tr>
	<tr>
		<td><strong>{lang_dbuser}</strong><br /><input type="text" name="setting_{db_domain}[db_user]" value="{db_user}" /></td><td>{lang_dbuserdescr}</td>
	</tr>
	<tr>
		<td><strong>{lang_dbpass}</strong><br /><input type="password" name="setting_{db_domain}[db_pass]" value="{db_pass}" /></td><td>{lang_dbpassdescr}</td>
	</tr>
	<tr>
		<td><strong>{lang_configuser}</strong><br /><input type="text" name="setting_{db_domain}[config_user]" value="{config_user}" /></td>
		<td>{lang_configuser_descr}</td>
	</tr>
	<tr>
		<td><strong>{lang_configpass}</strong><br /><input type="password" name="setting_{db_domain}[config_pass]" value="{config_pass}" /><input type="hidden" name="setting_{db_domain}[config_password]" value="{config_password}" /></td>
		<td>{lang_passforconfig} {lang_leave_empty}</td>
	</tr>
</table>
<!-- END domain -->

</td></tr>
</tbody>

</table>
