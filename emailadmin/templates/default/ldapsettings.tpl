<!-- BEGIN main -->
<center>
<table border="0" cellspacing="1" cellpading="0" width="95%">
<tr>
	<td width="90%" valign="top">
		<form action="{form_action}" method="post">
		<table border="0" cellspacing="1" cellpading="0" width="100%">
		<tr bgcolor="{bg_01}">
			<td>
				{lang_server_name}
			</td>
			<td>
				<input type="text" size="50" name="qmail_servername" value="{qmail_servername}">
			</td>
		</tr>
		<tr bgcolor="{bg_02}">
			<td>
				{lang_server_description}
			</td>
			<td>
				<input type="text" size="50" name="description" value="{description}">
			</td>
		</tr>
		<tr bgcolor="{bg_01}">
			<td>
				{lang_ldap_server}
			</td>
			<td>
				<input type="text" size="50">
			</td>
		</tr>
		<tr bgcolor="{bg_02}">
			<td>
				{lang_ldap_basedn}
			</td>
			<td>
				<input type="text" size="50" name="ldap_basedn" value="{ldap_basedn}">
			</td>
		</tr>
		<tr bgcolor="{bg_01}">
			<td>
				{lang_ldap_server_admin}
			</td>
			<td>
				<input type="text" size="50">
			</td>
		</tr>
		<tr bgcolor="{bg_02}">
			<td>
				{lang_ldap_server_password}
			</td>
			<td>
				<input type="text" size="50">
			</td>
		</tr>
		<tr bgcolor="{bg_01}">
			<td align="left">
				<a href="{done_link}">{lang_back}</a>
			</td>
			<td align="right">
				<input type="submit" name="save_ldap" value="{lang_save}">
				<input type="hidden" name="bo_action" value="save_ldap">
			</td>
		</tr>
		</table>
		</form>
	</td>
</tr>
</table>
</center>
<!-- END main -->

<!-- BEGIN menu_row -->
<tr bgcolor="{menu_row_color}">
	<td>
		<nobr><a href="{menu_link}">{menu_description}</a><nobr>
	</td>
</tr>
<!-- END menu_row -->

<!-- BEGIN menu_row_bold -->
<tr bgcolor="{menu_row_color}">
	<td>
		<nobr><b><a href="{menu_link}">{menu_description}</a></b><nobr>
	</td>
</tr>
<!-- END menu_row_bold -->
