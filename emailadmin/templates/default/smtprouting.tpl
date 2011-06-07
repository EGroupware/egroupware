<!-- BEGIN main -->
<center>
<table border="0" cellspacing="1" cellpading="0" width="95%">
<tr>
	<td width="10%" valign="top">
		<table border="0" cellspacing="1" cellpading="0" width="100%">
		{menu_rows}
		<tr>
			<td>
				&nbsp;
			</td>
		</tr>
		{activation_rows}
		<tr bgcolor="{done_row_color}">
			<td>
				<a href="{done_link}">{lang_done}</a>
			</td>
		</tr>
		</table>
	</td>
	<td width="90%" valign="top">
		<table width="100%" cellspacing="1" cellpading="0" border="0">
		<tr>
			<td>
				<form action="{form_action}" method="post">
				<table width="100%" cellspacing="1" cellpading="0" border="0">
					<tr bgcolor="{th_bg}">
						<td align="center">
							{lang_domain_name}
						</td>
						<td  align="center">
							{lang_remote_server}
						</td>
						<td align="center">
							{lang_remote_port}
						</td>
						<td align="center">
							{lang_delete}
						</td>
					</tr>
					{smtproute_rows}
					<tr bgcolor="{last_row_color}">
						<td align="center">
							<input type="text" size="30" name="domain_name">
						</td>
						<td align="center">
							<input type="text" size="30" name="remote_server">
						</td>
						<td align="center">
							<input type="text" size="4" value="25" name="remote_port">
						</td>
						<td align="center">
							<input type="submit" name="add_smtp_route" value="{lang_add}">
							<input type="hidden" name="bo_action" value="add_smtproute">
						</td>
					</tr>
				</table>
				</form>
			</td>
		</tr>
		</table>
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

<!-- BEGIN activation_row -->
<tr bgcolor="{bg_01}">
	<td>
		<a href="{activation_link}">{lang_activate}</a>
	</td>
</tr>
<tr>
	<td>
		&nbsp;
	</td>
</tr>
<!-- END activation_row -->

<!-- BEGIN smtproute_row -->
<tr bgcolor="{row_color}">
	<td align="center">
		{domain_name}
	</td>
	<td align="center">
		{remote_server}
	</td>
	<td align="center">
		{remote_port}
	</td>
	<td align="center">
		<a href={delete_route_link}>{lang_delete}</a>
	</td>
</tr>
<!-- END smtproute_row -->
