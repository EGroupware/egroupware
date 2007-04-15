<!-- BEGIN main -->
<center>

<form action="{action_url}" name="mailsettings" method="post">

<table width="90%" border="0" cellspacing="0" cellpading="1">
<tr class="th"> 
	<th width="40%" align="left" class="td_left">
		{lang_site_configuration}
	</th>
	<th width="60%" align="right" class="td_right">
		&nbsp;
	</th>
</tr>
<tr>
	<td width="40%" class="td_left">
		{lang_select_email_profile}:
	</td>
	<td width="60%" class="td_right">
		<select name="profileID">
			<option value="-1"></option>
			{select_options}
		</select>
		{lang_go_emailadmin}
	</td>
</tr>
</table>
<br><br>
<table width="90%" border="0" cellspacing="0" cellpading="1">
	<tr>
		<td width="90%" align="left"  class="td_left">
			<a href="{back_url}">{lang_back}</a>
		</td>
		<td width="10%" align="center" class="td_right">
			<a href="javascript:document.mailsettings.submit();">{lang_save}</a>
		</td>
	</tr>
</table>

</form>
<br>
</center>
<!-- END main -->

<!-- BEGIN select_option -->
			<option value="{profileID}" {selected}>{description}</option>
<!-- END select_option -->
