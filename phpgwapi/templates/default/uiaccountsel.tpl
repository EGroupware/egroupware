<!-- $Id$ -->

<script LANGUAGE="JavaScript">
	window.focus();
</script>

<div id="divMain" style="height: 520px">
<table border="0" width="100%">
	<tr>
		<td colspan="4">
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
		<td width="100%" colspan="4" align="right">
			<form method="POST" action="{search_action}">
			<input type="text" name="query">&nbsp;<input type="submit" name="search" value="{lang_search}">
			</form>
		</td>
	</tr>
</table>

<table border="0" width="100%" cellpadding="0" cellspacing="0">
	<tr>
		<td valign="top" width="20%">
			<table border="0" width="100%" cellpadding="2" cellspacing="2">
				<tr>
					<td class="th" colspan="2" align="center"><b>{lang_groups}</b></td>
				</tr>
<!-- BEGIN bla_intro -->
				<tr>
					<td class="th" colspan="2">{lang_perm}</td>
				</tr>

<!-- END bla_intro -->

<!-- BEGIN other_intro -->
				<tr>
					<td class="th" colspan="2">{lang_perm}</td>
				</tr>
<!-- END other_intro -->

<!-- BEGIN group_cal -->
				<tr bgcolor="{tr_color}">
					<td><a href="{link_user_group}" title="{lang_list_members}">{name_user_group}</a></td>
					<td align="center">
					<form>
						<input type="image" src="{img}" onclick="{onclick}; return false;" title="{lang_select_group}">
					</form>
					</td>
				</tr>
<!-- END group_cal -->

<!-- BEGIN group_other -->

				<tr bgcolor="{tr_color}">
					<td><a href="{link_user_group}" title="{lang_list_members}">{name_user_group}</a></td>
				</tr>

<!-- END group_other -->

<!-- BEGIN all_intro -->
				<tr height="5">
					<td>&nbsp;</td>
				</tr>
				<tr>
					<td class="th" colspan="2">{lang_nonperm}</td>
				</tr>

<!-- END all_intro -->

<!-- BEGIN group_all -->

				<tr bgcolor="{tr_color}">
					<td colspan="2"><a href="{link_all_group}" title="{lang_list_members}">{name_all_group}</a></td>
				</tr>

<!-- END group_all -->


			</table>
		</td>
		<td width="80%" valign="top">
			<table border="0" width="100%" cellpadding="2" cellspacing="2">
				<tr class="th">
					<td width="100%" class="th" align="center" colspan="4"><b>{lang_accounts}</b></td>
				</tr>
				<tr class="th">
					<td width="30%" class="th" align="center">{sort_lid}</td>
					<td width="30%" class="th" align="center">{sort_firstname}</td>
					<td width="30%" class="th" align="center">{sort_lastname}</td>
					<td width="10%" class="th">&nbsp;</td>
				</tr>

<!-- BEGIN accounts_list -->

	<tr bgcolor="{tr_color}">
		<td>{lid}</td>
		<td>{firstname}</td>
		<td>{lastname}</td>
		<td align="center">
			<form>
				<input type="image" src="{img}" onclick="{onclick}; return false;" title="{lang_select_user}">
			</form>
		</td>
	</tr>

<!-- END accounts_list -->

			</table>
		</td>
	</tr>
</table>
<p style="text-align: center">
<input type="button" name="Done" value="{lang_done}" onClick="window.close()">
</p>
</div>
