<!-- begin setup_main.tpl -->
<!-- begin the db section -->
<table border="0" width="90%" cellspacing="0" cellpadding="2">
<tr class="th">
	<td align="left" colspan="2">{db_step_text}</td>
</tr>

{V_db_filled_block}
<!-- end the db section -->

<!-- begin the config section -->
<tr class="th">
	<td align="left" colspan="2">{config_step_text}</td>
</tr>
<tr>
	<td align="center" width="30%">
		<img src="{config_status_img}" alt="{config_status_alt}" border="0" />
	</td>
	<td>
		{config_table_data}
	</td>
</tr>
<!-- end the config section -->
<!-- begin the admin section -->
<tr class="th">
	<td align="left" colspan="2">{admin_step_text}</td>
</tr>
<tr>
	<td align="center" width="30%">
		<img src="{admin_status_img}" alt="{admin_status_alt}" border="0" />
	</td>
	<td>
		{admin_table_data}
	</td>
</tr>
<!-- end the admin section -->
<!-- begin the lang section -->
<tr class="th">
	<td align="left" colspan="2">{lang_step_text}</td>
</tr>
<tr>
	<td align="center">
		<img src="{lang_status_img}" alt="{lang_status_alt}" border="0" />
	</td>
	<td>
		{lang_table_data}
	</td>
</tr>
<!-- end the lang section -->
<!-- begin the apps section -->
<tr class="th">
	<td align="left" colspan="2">{apps_step_text}</td>
</tr>
<tr>
	<td align="center">
		<img src="{apps_status_img}" alt="{apps_status_alt}" border="0" />
	</td>
	<td>
		{apps_table_data}
	</td>
</tr>
<!-- end the apps section -->
<!-- begin the backup section -->
<tr class="th">
	<td align="left" colspan="2">{backup_step_text}</td>
</tr>
<tr>
	<td align="center">
		<img src="{backup_status_img}" alt="{backup_status_alt}" border="0" />
	</td>
	<td>
		{backup_table_data}
	</td>
</tr>
<!-- end the apps section -->
<tr class="banner">
	<td colspan="2">&nbsp;</td>
</tr>
</table>
<!-- end setup_main.tpl -->
