<!-- begin setup_main.tpl -->
<!-- begin the db section -->
<table border="0" width="100%" cellspacing="0" cellpadding="2" style="{ border: 1px solid #000000; }">
<tr class="th">
	<td align="left">{db_step_text}</td>
	<td align="right">&nbsp;</td>
</tr>

{V_db_filled_block}
<!-- end the db section -->

<!-- begin the config section -->
<tr class="th">
	<td align="left">{config_step_text}</td>
	<td align="right">&nbsp;</td>
</tr>
<tr>
	<td align="center">
		<img src="{config_status_img}" alt="{config_status_alt}" border="0">
	</td>
	<td>
		{config_table_data}
	</td>
</tr>
<tr>
	<td>&nbsp;</td>
	<td>
		{ldap_table_data}
	</td>
</tr>
<!-- end the config section -->
<!-- begin the lang section -->
<tr class="th">
	<td align="left">{lang_step_text}</td>
	<td align="right">&nbsp;</td>
</tr>
<tr>
	<td align="center">
		<img src="{lang_status_img}" alt="{lang_status_alt}" border="0">
	</td>
	<td>
		{lang_table_data}
	</td>
</tr>
<!-- end the lang section -->
<!-- begin the apps section -->
<tr class="th">
	<td align="left">{apps_step_text}</td>
	<td align="right">&nbsp;</td>
</tr>
<tr>
	<td align="center">
		<img src="{apps_status_img}" alt="{apps_status_alt}" border="0">
	</td>
	<td>
		{apps_table_data}
	</td>
</tr>
<!-- end the apps section -->
<tr class="banner">
	<td colspan="2">&nbsp;</td>
</tr>
</table>
<!-- end setup_main.tpl -->
