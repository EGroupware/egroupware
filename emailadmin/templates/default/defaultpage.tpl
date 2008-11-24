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
		<form action="{form_action}" method="post">
		<table width="100%" cellspacing="1" cellpading="0" border="0">
		<tr>
			<td>
				Data
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
