<!-- $Id$ -->

	<center>
		<table border="0" cellspacing="2" cellpadding="2">
			<tr>
				<td colspan="4" align="center" bgcolor="#c9c9c9"><b>{title_categories}<b/></td>
			</tr>
			<tr>
				<td colspan="4" align="left">
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
				<td colspan="4" align="right">
					<form method="post" action="{actionurl}">
					<input type="text" name="query">&nbsp;<input type="submit" name="search" value="{lang_search}"></form></td>
			</tr>
			<tr bgcolor="{th_bg}">
				<td width=20% bgcolor="{th_bg}">{sort_name}</td>
				<td width=32% bgcolor="{th_bg}">{sort_description}</td>
				<td width=8% bgcolor="{th_bg}" align="center">{lang_edit}</td>
				<td width=8% bgcolor="{th_bg}" align="center">{lang_delete}</td>
			</tr>

<!-- BEGIN cat_list -->

			<tr bgcolor="{tr_color}">
				<td>{name}</td>
				<td>{descr}</td>
				<td align="center"><a href="{edit}">{lang_edit_entry}</a></td>
				<td align="center"><a href="{delete}">{lang_delete_entry}</td>  
			</tr>

<!-- END cat_list -->  

<!-- BEGINN add   -->

			<tr valign="bottom">
				<td>
					<form method="POST" action="{add_action}">
					<input type="submit" name="add" value="{lang_add}"></form>
				</td>
			</tr>
			<tr valign="bottom">
				<td>
					<form method="POST" action="{doneurl}">
					<input type="submit" name="done" value="{lang_done}"></form>
				</td>
			</tr>

<!-- END add -->

		</table>
	</center>
