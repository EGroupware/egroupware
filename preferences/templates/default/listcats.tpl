<!-- $Id$ -->

	<center>
		<table border="0" cellspacing="0" cellpadding="0" width="80%">
			<tr>
				<td colspan="3" width="80%" align="center" bgcolor="#c9c9c9"><font face="{font}"><b>{lang_app}&nbsp;{title_categories}:&nbsp;{user_name}<b/></font></td>
			</tr> 
			<tr>
				<td colspan="3" align=left>
					<table border="0" width="100%">
						<tr>
						{left}
							<td align="center"><font face="{font}">{lang_showing}</font></td>
						{right}
						</tr>
					</table>
				</td>
			</tr>
			<tr>
				<td>&nbsp;</td>
				<td colspan="3" align=right>
					<form method="post" action="{actionurl}">
					<font face="{font}"><input type="text" name="query">&nbsp;<input type="submit" name="search" value="{lang_search}"></font>
					</form></td>
			</tr>
		</table>
		<table border="0" cellspacing="2" cellpadding="2" width="80%">
			<tr bgcolor="{th_bg}">
				<td bgcolor="{th_bg}"><font face="{font}">{sort_name}</font></td>
				<td bgcolor="{th_bg}"><font face="{font}">{sort_description}</font></td>
				{sort_data}
				<td bgcolor="{th_bg}" align="center"><font face="{font}">{lang_app}</font></td>
				<td bgcolor="{th_bg}" align=center><font face="{font}">{lang_edit}</font></td>
				<td bgcolor="{th_bg}" align=center><font face="{font}">{lang_delete}</font></td>
			</tr>

<!-- BEGIN cat_list -->

		<tr bgcolor="{tr_color}">
			<td><font face="{font}">{name}</font></td>
			<td><font face="{font}">{descr}</font></td>
			{td_data}
			<td align="center"><font face="{font}"><a href="{app_url}">{lang_app}</a></font></td>
			<td align="center"><font face="{font}"><a href="{edit}">{lang_edit_entry}</a></font></td>
			<td align="center"><font face="{font}"><a href="{delete}">{lang_delete_entry}</font></td>
		</tr>

<!-- END cat_list -->  

<!-- BEGINN add   -->

	</table>
	<table border="0" cellspacing="2" cellpadding="2" width="80%">
		<tr valign="bottom">
			<td width="50%">
				<form method="POST" action="{add_action}">
				{hidden_vars}
				<font face="{font}"><input type="submit" value="{lang_add}"></font>
				</form>
			</td>
			<td width="50%" align="right">
				<form method="POST" action="{doneurl}">
				{hidden_vars}
				<font face="{font}"><input type="submit" name="done" value="{lang_done}"></font>
				</form>
			</td>
		</tr>

<!-- END add -->

	</table>
</center>