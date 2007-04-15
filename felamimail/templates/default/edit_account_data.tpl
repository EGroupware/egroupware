<!-- BEGIN main -->
<center>
<form action="{form_action}" name="editAccountData" method="post">
<div style="width:650px; text-align:left;">
<input type="checkbox" id="active" name="active" value="1" onclick="onchange_active(this)" {checked_active}>{lang_use_costum_settings}
</div>
<fieldset style="width:650px;" class="row_on" id="identity"><legend style="font-weight: bold;">{lang_identity}</legend>
<table width="100%" border="0" cellpadding="0" cellspacing="1">
	<tr>
		<td style="width:300px; text-align:left;">
			{lang_name}
		</td>
		<td  style="text-align:left;">
			<input type="text" style="width: 99%;" name="identity[realName]" value="{identity[realName]}" maxlength="128">
		</td>
	</tr>
	<tr>
		<td style="width:300px; text-align:left;">
			{lang_organization}
		</td>
		<td  style="text-align:left;">
			<input type="text" style="width: 99%;" name="identity[organization]" value="{identity[organization]}" maxlength="128">
		</td>
	</tr>
	<tr>
		<td  style="text-align:left;">
			{lang_emailaddress}
		</td>
		<td  style="text-align:left;">
			<input type="text" style="width: 99%;" name="identity[emailAddress]" value="{identity[emailAddress]}" maxlength="128">
		</td>
	</tr>
</table>
</fieldset>

<fieldset style="width:650px;" class="row_on" id="incoming_server"><legend style="font-weight: bold;">{lang_incoming_server}</legend>
<table width="100%" border="0" cellpadding="0" cellspacing="1">
	<tr>
		<td style="width:300px; text-align:left;">
			{hostname_address}
		</td>
		<td  style="text-align:left;">
			<input type="text" style="width: 99%;" name="ic[host]" value="{ic[host]}" maxlength="128">
		</td>
	</tr>
	<tr>
		<td style="width:300px; text-align:left;">
			{lang_port}
		</td>
		<td  style="text-align:left;">
			<input type="text" style="width: 5em;" id="ic[port]" name="ic[port]" value="{ic[port]}" maxlength="5">
		</td>
	</tr>
	<tr>
		<td  style="text-align:left;">
			{lang_username}
		</td>
		<td  style="text-align:left;">
			<input type="text" style="width: 99%;" name="ic[username]" value="{ic[username]}" maxlength="128">
		</td>
	</tr>
	<tr>
		<td  style="text-align:left;">
			 {lang_password}
		</td>
		<td  style="text-align:left;">
			<input type="password" style="width: 99%;" name="ic[password]" value="{ic[password]}" maxlength="128">
		</td>
	</tr>
	<tr>
		<td  style="text-align:left;">
			 {lang_encrypted_connection}
		</td>
		<td  style="text-align:left;">
			<input type="radio" id="ic[encryption]_1" name="ic[encryption]" value="1" onchange="onchange_ic_encryption(this)" {checked_ic_encryption_1}> STARTTLS
			<input type="radio" id="ic[encryption]_2" name="ic[encryption]" value="2" onchange="onchange_ic_encryption(this)" {checked_ic_encryption_2}> TLS
			<input type="radio" id="ic[encryption]_3" name="ic[encryption]" value="3" onchange="onchange_ic_encryption(this)" {checked_ic_encryption_3}> SSL
			<input type="radio" id="ic[encryption]_0" name="ic[encryption]" value="0" onchange="onchange_ic_encryption(this)" {checked_ic_encryption_0}> {lang_no_encryption}
		</td>
	</tr>
	<tr>
		<td  style="text-align:left;">
			 {lang_do_not_validate_certificate}
		</td>
		<td  style="text-align:left;">
			<input type="checkbox" id="ic[validatecert]" name="ic[validatecert]" value="dontvalidate" {checked_ic_validatecert}>
		</td>
	</tr>
	<tr>
		<td  style="text-align:left;">
			 {lang_server_supports_sieve}
		</td>
		<td  style="text-align:left;">
			<input type="checkbox" id="ic[enableSieve]" name="ic[enableSieve]" onchange="onchange_ic_enableSieve(this)" value="enableSieve" {checked_ic_enableSieve}>
		</td>
	</tr>
	<tr>
		<td style="width:300px; text-align:left;">
			{lang_port}
		</td>
		<td  style="text-align:left;">
			<input type="text" style="width: 5em;" id="ic[sievePort]" name="ic[sievePort]" value="{ic[sievePort]}" maxlength="5">
		</td>
	</tr>
</table>
</fieldset>

<fieldset style="width:650px;" class="row_on" id="outgoing_server"><legend style="font-weight: bold;">{lang_outgoing_server}</legend>
<table width="100%" border="0" cellpadding="0" cellspacing="1">
	<tr>
		<td style="width:300px; text-align:left;">
			{hostname_address}
		</td>
		<td style="text-align:left;">
			<input type="text" style="width: 99%;" name="og[host]" value="{og[host]}" maxlength="128">
		</td>
	</tr>
	<tr>
		<td style="width:300px; text-align:left;">
			{lang_port}
		</td>
		<td style="text-align:left;">
			<input type="text" style="width: 5em;" id="og[port]" name="og[port]" value="{og[port]}" maxlength="5">
		</td>
	</tr>
	<tr>
		<td style="text-align:left;">
			 {auth_required}
		</td>
		<td style="text-align:left;">
			<input type="checkbox" id="og[smtpauth]" name="og[smtpAuth]" value="1" onchange="onchange_og_smtpauth(this)" {checked_og_smtpAuth}>
		</td>
	</tr>
	<tr>
		<td style="text-align:left;">
			{lang_username}
		</td>
		<td style="text-align:left;">
			<input type="text" style="width: 99%;" id="og[username]" name="og[username]" value="{og[username]}" maxlength="128">
		</td>
	</tr>
	<tr>
		<td style="text-align:left;">
			 {lang_password}
		</td>
		<td style="text-align:left;">
			<input type="password" style="width: 99%;" id="og[password]" name="og[password]" value="{og[password]}" maxlength="128">
		</td>
	</tr>
</table>
</fieldset>

<table width="650px" border="0" cellpadding="0" cellspacing="1">
	<tr>
		<td>
			<input type="submit" name="save" value="{lang_save}">
			<input type="submit" name="apply" value="{lang_apply}">
			<input type="submit" name="cancel" value="{lang_cancel}">
		</td>
	</tr>
</table>
<form>
</center>
<!-- END main -->
