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
		<tr bgcolor="{th_bg}">
			<td colspan="2">
				Domains we receive email for
			</td>
		</tr>
		<form action="{form_action}" method="post">
		<tr bgcolor="{bg_01}">
			<td width="50%" rowspan="5" align="center">
				{rcpt_selectbox}
			</td>
			<td width="50%" align="center">
				<input type="submit" value="{lang_remove} -->">
				<input type="hidden" name="bo_action" value="remove_rcpthosts">
			</td>
		</tr>
		</form>
		<tr bgcolor="{bg_02}">
			<td width="50%" align="center">
				&nbsp;
			</td>
		</tr>
		<form action="{form_action}" method="post">
		<tr bgcolor="{bg_01}">
			<td width="50%" align="center">
				<input type="text" size="30" name="new_rcpthost">
			</td>
		</tr>
		<tr bgcolor="{bg_02}">
			<td width="50%" align="center">
				<input type="checkbox" name="add_to_local">{lang_add_to_local}
			</td>
		</tr>
		<tr bgcolor="{bg_01}">
			<td width="50%" align="center">
				<input type="submit" value="<-- {lang_add}">
				<input type="hidden" name="bo_action" value="add_rcpthosts">
			</td>
		</tr>
		</form>
		<tr>
			<td colspan="2">
				&nbsp;
			</td>
		</tr>
		<tr bgcolor="{th_bg}">
			<td colspan="2">
				Domains which email we handle local
			</td>
		</tr>
		<form action="{form_action}" method="post">
		<tr bgcolor="{bg_01}">
			<td width="50%" rowspan="4" align="center">
				{locals_selectbox}
			</td>
			<td width="50%" align="center">
				<input type="submit" value="{lang_remove} -->">
				<input type="hidden" name="bo_action" value="remove_locals">
			</td>
		</tr>
		</form>
		<tr bgcolor="{bg_02}">
			<td width="50%" align="center">
				&nbsp;
			</td>
		</tr>
		<form action="{form_action}" method="post">
		<tr bgcolor="{bg_01}">
			<td width="50%" align="center">
				<input type="text" size="30" name="new_local">
			</td>
		</tr>
		<tr bgcolor="{bg_02}">
			<td width="50%" align="center">
				<input type="submit" value="<-- {lang_add}">
				<input type="hidden" name="bo_action" value="add_locals">
			</td>
		</tr>
		</form>
		</table>
	</td>
</tr>
</table>
</form>
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
