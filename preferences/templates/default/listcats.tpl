<!-- $Id$ -->

	<center>
		<table border="0" cellspacing="2" cellpadding="2" width="100%" class="egwGridView_grid">
			<tr>
				<td colspan="8" align="left">
					<table border="0" width="100%">
						<tr>
						{left}
							<td align="center">{lang_showing}</td>
						{right}
						</tr>
					</table>
				</td>
			</tr>
<!-- BEGIN search -->
			<tr>
				<td colspan="8" align="right">
					<form method="post" action="{action_nurl}">
					<input type="text" name="query">&nbsp;<input type="submit" name="search" value="{lang_search}"></form></td>
			</tr>
<!-- END search -->
			<tr class="th">

				<td width="20%">{sort_name}</td>
				<td width="6%">{lang_global}</td>
				<td width="32%">{sort_description}</td>
				{sort_data}
				<td width="8%">{lang_color}</td>
				<td width="14%" align="center">{lang_sub}</td>
				<td width="8%" align="center">{lang_edit}</td>
				<td width="8%" align="center">{lang_delete}</td>
			</tr>

<!-- BEGIN cat_list -->

			<tr bgcolor="{tr_color}" {color}>
				<td>{name}</td>
				<td>{lang_global_entry}</td>
				<td>{descr}</td>
				{td_data}
				<td bgcolor="{td_color}"></td>
				<td align="center"><a href="{add_sub}">{lang_sub_entry}</a></td>
				<td align="center"><a href="{edit}">{lang_edit_entry}</a></td>
				<td align="center"><a href="{delete}">{lang_delete_entry}</a></td>
			</tr>

<!-- END cat_list -->

			<tr valign="bottom" height="50">
<!-- BEGIN add -->
            		    <td>
                		<form method="POST" action="{add_action}">
                        	<input type="submit" value="{lang_add}">
                                </form>
            		    </td>

			    <td>
        			<form method="POST" action="{doneurl}">
                    		<input type="submit" name="done" value="{lang_cancel}">
                    		</form>
                    	    </td>

			    <td width="80%">&nbsp;</td>
<!-- END add -->
			</tr>
		</table>
	</center>
