<!-- $Id$ -->

<!-- BEGIN cat_list -->

	<center>
		<table border="0" cellspacing="2" cellpadding="2">
			<tr>
				<td colspan="5" align="center" bgcolor="#c9c9c9"><b>{title_categories}<b/></td>
			</tr>
			<tr>
				<td colspan="5" align="left">
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
				<td colspan="5" align="right">
					<form method="post" action="{actionurl}">
					<input type="text" name="query">&nbsp;<input type="submit" name="search" value="{lang_search}"></form></td>
			</tr>
			<tr class="th">
				<td width=20% class="th">{sort_name}</td>
				<td width=32% class="th">{sort_description}</td>
				<td width=8% class="th" align="center">{lang_sub}</td>
				<td width=8% class="th" align="center">{lang_edit}</td>
				<td width=8% class="th" align="center">{lang_delete}</td>
			</tr>

			{rows}

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

		</table>
	</center>

<!-- END cat_list -->

<!-- BEGIN cat_row -->

			<tr class="{tr_color}">
				<td>{name}</td>
				<td>{descr}</td>
				<td align="center"><a href="{add_sub}">{lang_sub_entry}</a></td>
				<td align="center"><a href="{edit}">{lang_edit_entry}</a></td>
				<td align="center"><a href="{delete}">{lang_delete_entry}</a></td>
			</tr>

<!-- END cat_row -->
