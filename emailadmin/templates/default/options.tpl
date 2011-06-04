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
		<tr bgcolor="{th_bg}">
			<td colspan="2">
				ldaplocaldelivery
			</td>
		</tr>
		<tr bgcolor="{bg_01}">
			<td>
				{desc_ldaplocaldelivery}
			</td>
		</tr>
		<tr bgcolor="{bg_02}">
			<td>
				<select name="ldaplocaldelivery">
					<option value="0" {ldaplocaldelivery_0}>{lang_disabled}</option>
					<option value="1" {ldaplocaldelivery_1}>{lang_enabled}</option>
				</select>
			</td>
		</tr>
		<tr>
			<td>
				&nbsp;
			</td>
		</tr>
		<tr bgcolor="{th_bg}">
			<td colspan="2">
				ldapdefaultdotmode
			</td>
		</tr>
		<tr bgcolor="{bg_01}">
			<td>
				{desc_ldapdefaultdotmode}
			</td>
		</tr>
		<tr bgcolor="{bg_02}">
			<td>
				<select name="ldapdefaultdotmode">
					<option value="both" {ldapdefaultdotmode_both}>both</option>
					<option value="dotonly" {ldapdefaultdotmode_dotonly}>dotonly</option>
					<option value="ldaponly" {ldapdefaultdotmode_ldaponly}>ldaponly</option>
					<option value="ldapwithprog" {ldapdefaultdotmode_ldapwithprog}>ldapwithprog</option>
					<option value="none" {ldapdefaultdotmode_none}>none</option>
				</select>
			</td>
		</tr>
		<tr>
			<td>
				&nbsp;
			</td>
		</tr>
		<tr bgcolor="{th_bg}">
			<td colspan="2">
				ldapbasedn
			</td>
		</tr>
		<tr bgcolor="{bg_01}">
			<td>
				{desc_ldapbasedn}
			</td>
		</tr>
		<tr bgcolor="{bg_02}">
			<td>
				<input size="50" name="ldapbasedn" value="{ldapbasedn}">
			</td>
		</tr>
		<tr>
			<td>
				&nbsp;
			</td>
		</tr>
		<tr bgcolor="{th_bg}">
			<td colspan="2">
				dirmaker
			</td>
		</tr>
		<tr bgcolor="{th_bg}">
			<td colspan="2">
				ldapcluster
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
