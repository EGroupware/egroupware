<!-- BEGIN main -->
<script language="JavaScript1.2">
var tab = new Tabs(2,'activetab','inactivetab','tab','tabcontent','','','tabpage');
var allowAccounts        = {allowAccounts};
function initTabs() {
tab.init();
}
</script>
<style>
th.activetab
			{
				color:#000000;
				background-color:#D3DCE3;
				border-top-width : 1px;
				border-top-style : solid;
				border-top-color : Black;
				border-left-width : 1px;
				border-left-style : solid;
				border-left-color : Black;
				border-right-width : 1px;
				border-right-style : solid;
				border-right-color : Black;
			}
			
th.inactivetab
			{
				color:#000000;
				background-color:#E8F0F0;
				border-bottom-width : 1px;
				border-bottom-style : solid;
				border-bottom-color : Black;
			}
			
.td_left { border-left : 1px solid Gray; border-top : 1px solid Gray; }
.td_right { border-right : 1px solid Gray; border-top : 1px solid Gray; }
			
div.activetab{ display:inline; }
div.inactivetab{ display:none; }
</style>
<center>
<div style="color:red;"> {message} </div>
<form action="{form_action}" name="editAccountData" method="post">
<INPUT TYPE=hidden NAME="identity[id]" value="{accountID}">
<fieldset style="width:650px;" class="row_on" id="identity"><legend style="font-weight: bold;">{lang_identity} {accountID}</legend>
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
    <tr>
        <td  style="text-align:left;">
            {lang_signature}
        </td>
        <td  style="text-align:left;">
            {identity_selectbox}
        </td>
    </tr>

</table>
</fieldset>

<div style="width:650px; text-align:left;">
<input type="checkbox" id="active" name="active" value="1" onclick="onchange_active(this)" {checked_active}>{lang_use_costum_settings}
</div>

<table width="665px" border="0" cellspacing="0" cellpading="0">
	<tr>
		<th width="50%" id="tab1" class="activetab" onclick="javascript:tab.display(1);"><a href="#" tabindex="1" accesskey="1" onfocus="tab.display(1);" onclick="tab.display(1); return(false);">{lang_incoming_server}</a></th>
		<th width="50%" id="tab2" class="activetab" onclick="javascript:tab.display(2);"><a href="#" tabindex="2" accesskey="2" onfocus="tab.display(2);" onclick="tab.display(2); return(false);">{lang_folder_settings}</a></th>
	</tr>
</table>
<div id="tabcontent1" class="activetab">
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
			<input type="text" style="width: 99%;" name="ic[username]" value="{ic[username]}" maxlength="128" autocomplete="off">
		</td>
	</tr>
	<tr>
		<td  style="text-align:left;">
			 {lang_password}
		</td>
		<td  style="text-align:left;">
			<input type="password" style="width: 99%;" name="ic[password]" value="{ic[password]}" maxlength="128" autocomplete="off">
		</td>
	</tr>
	<tr>
		<td  style="text-align:left;">
			 {lang_encrypted_connection}
		</td>
		<td  style="text-align:left;">
			<input type="radio" id="ic[encryption]_1" name="ic[encryption]" value="1" onclick="onchange_ic_encryption(this)" {checked_ic_encryption_1}> STARTTLS
			<input type="radio" id="ic[encryption]_2" name="ic[encryption]" value="2" onclick="onchange_ic_encryption(this)" {checked_ic_encryption_2}> TLS
			<input type="radio" id="ic[encryption]_3" name="ic[encryption]" value="3" onclick="onchange_ic_encryption(this)" {checked_ic_encryption_3}> SSL
			<input type="radio" id="ic[encryption]_0" name="ic[encryption]" value="0" onclick="onchange_ic_encryption(this)" {checked_ic_encryption_0}> {lang_no_encryption}
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
</div>

<div id="tabcontent2" class="inactivetab">
 <fieldset style="width:650px;" class="row_on" id="incoming_server_folders">
  <table width="100%" border="0" cellpadding="0" cellspacing="1">
    <tr>
        <td  style="text-align:left;">
            {lang_folder_to_appear_on_main_screen}
        </td>
        <td  style="text-align:left;">
            {folder_selectbox}
        </td>
    </tr>
    <tr>
        <td  style="text-align:left;">
            {lang_trash_folder}
        </td>
        <td  style="text-align:left;">
            {trash_selectbox}
        </td>
    </tr>
    <tr>
        <td  style="text-align:left;">
            {lang_sent_folder}
        </td>
        <td  style="text-align:left;">
            {sent_selectbox}
        </td>
    </tr>
    <tr>
        <td  style="text-align:left;">
            {lang_draft_folder}
        </td>
        <td  style="text-align:left;">
            {draft_selectbox}
        </td>
    </tr>
    <tr>
        <td  style="text-align:left;">
            {lang_template_folder}
        </td>
        <td  style="text-align:left;">
            {template_selectbox}
        </td>
    </tr>

  </table>
 </fieldset>
</div>

<fieldset style="width:650px;" class="row_on" id="outgoing_server"><legend style="font-weight: bold;">{lang_outgoing_server}</legend>
<table width="100%" border="0" cellpadding="0" cellspacing="1">
	<tr>
		<td style="width:300px; text-align:left;">
			{hostname_address}
		</td>
		<td style="text-align:left;">
			<input type="text" style="width: 99%;" id="og[host]" name="og[host]" value="{og[host]}" maxlength="128">
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
			<input type="text" style="width: 99%;" id="og[username]" name="og[username]" value="{og[username]}" maxlength="128" autocomplete="off">
		</td>
	</tr>
	<tr>
		<td style="text-align:left;">
			 {lang_password}
		</td>
		<td style="text-align:left;">
			<input type="password" style="width: 99%;" id="og[password]" name="og[password]" value="{og[password]}" maxlength="128" autocomplete="off">
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
