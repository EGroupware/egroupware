<!-- $Id$ -->
<br>
<center>
<table border="0" cellspacing="2" cellpadding="2" width="80%">
		<tr>
			<td colspan="3" align=left>
				<table border="0" width="100%">
					<tr>
					{left}
						<td align="center">{lang_showing}</td>
					{right}
					</tr>
				</table>
			</td>
		</tr>
		<tr>
			<td>&nbsp;</td>
			<td colspan="3" align=right>
				<form method="post" action="{actionurl}">
				<input type="text" name="query">&nbsp;<input type="submit" name="search" value="{lang_search}">
				</form></td>
		</tr>
</table>
<table border="0" cellspacing="2" cellpadding="2" width="80%">
	<tr bgcolor="{th_bg}">
		<td bgcolor="{th_bg}">{sort_name}</td>
		<td bgcolor="{th_bg}">{sort_description}</td>
		{sort_data}
		<td bgcolor="{th_bg}" align="center">{lang_app}</td>
		<td bgcolor="{th_bg}" align=center>{lang_sub}</td>
		<td bgcolor="{th_bg}" align=center>{lang_edit}</td>
		<td bgcolor="{th_bg}" align=center>{lang_delete}</td>
	</tr>

<!-- BEGIN cat_list -->

	<tr bgcolor="{tr_color}">
		<td>{name}</td>
		<td>{descr}</td>
		{td_data}
		<td align="center"><a href="{app_url}">{lang_app}</a></td>
		<td align="center"><a href="{add_sub}">{lang_sub_entry}</a></td>
		<td align="center"><a href="{edit}">{lang_edit_entry}</a></td>
		<td align="center"><a href="{delete}">{lang_delete_entry}</a></td>
	</tr>

<!-- END cat_list -->  

<!-- BEGINN add   -->

</table>
<table border="0" cellspacing="2" cellpadding="2" width="80%">
	<tr valign="bottom" height="50">
		<td>
			<form method="POST" action="{add_action}">
				<input type="submit" value="{lang_add}">
			</form>
		</td>
		<td align="right">
			<form method="POST" action="{doneurl}">
				<input type="submit" name="done" value="{lang_done}">
			</form>
		</td>
	</tr>
</table>

<!-- END add -->

</center>
