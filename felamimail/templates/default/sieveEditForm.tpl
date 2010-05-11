<!-- BEGIN main -->
<script language="JavaScript" type="text/javascript">

function SubmitForm(a)
{
    if (a == 'delete'){
	if (!confirm("Are you sure you want to delete this rule?")){
                return true;
        }
    }
    document.thisRule.submit();
}

</script>
<div style="color:red;">{message}</div>
<form ACTION="{action_url}" METHOD="post" NAME="thisRule">

	<fieldset style="width:680px;" class="row_on"><legend>{lang_condition}</legend>
			<table WIDTH="100%" CELLPADDING="2" CELLSPACING="1" style="border: 1px solid silver;">
				<tr CLASS="th">
					<td style="width:30%;">
						{lang_match}:
					</td>
					<td style="width:70%; text-align:left;">
						<select class="input_text" NAME="anyof">
							<option VALUE="0" {anyof_selected0}> {lang_all_of}
							<option VALUE="1" {anyof_selected4}> {lang_any_of}
						</select>
					</td>
				</tr>
				<tr CLASS="sieveRowActive">
					<td NOWRAP="nowrap">
						{lang_if_from_contains}: 
					</td>
					<td>
						<input class="input_text" TYPE="text" NAME="from" SIZE="50" value="{value_from}">
					</td>
				</tr>
				<tr CLASS="sieveRowActive">
					<td>
						{lang_if_to_contains}: 
					</td>
					<td>
						<input class="input_text" TYPE="text" NAME="to" SIZE="50" value="{value_to}">
					</td>
				</tr>
				<tr CLASS="sieveRowActive">
					<td>
						{lang_if_subject_contains}: 
					</td>
					<td>
						<input class="input_text" TYPE="text" NAME="subject" SIZE="50" value="{value_subject}">
					</td>
				</tr>
				<tr CLASS="sieveRowActive">
					<td>
						{lang_if_message_size}
					</td>
					<td>
						<select class="input_text" NAME="gthan">
							<option VALUE="0" {gthan_selected0}> {lang_less_than}
							<option VALUE="1" {gthan_selected2}> {lang_greater_than}
						</select>
						<input class="input_text" TYPE="text" NAME="size" SIZE="5" value="{value_size}"> {lang_kilobytes}
					</td>
				</tr>
				<tr CLASS="sieveRowActive">
					<td>
						{lang_if_mail_header}:
					</td>
					<td>
						<input class="input_text" TYPE="text" NAME="field" SIZE="20" value="{value_field}"> 
						{lang_contains}:
						<input class="input_text" TYPE="text" NAME="field_val" SIZE="30" value="{value_field_val}">
					</td>
				</tr>
			</table>
	</fieldset>
	<fieldset style="width:680px;" class="row_on"><legend>{lang_action}</legend>
		<table WIDTH="100%" CELLPADDING="2" CELLSPACING="1" style="border: 1px solid silver;">
			<tr CLASS="sieveRowActive">
				<td style="width:30%;">
					<input TYPE="radio" NAME="action" VALUE="folder" id="action_folder" {checked_action_folder}> <label for="action_folder">{lang_file_into}:</label>
				</td>
				<td style="width:70%;">
					<input type="text" value="{folderName}" id="folderName" name="folder" style="width:250px;" onchange="document.getElementById('action_folder').checked = true;">
					<a href="#" onclick="javascript:window.open('{folder_select_url}', 'windowName', 'width=400,height=500,toolbar=no,resizable=yes'); return false;">{lang_select_folder}...</a>
				</td>
			</tr>
			<tr CLASS="sieveRowActive">
				<td>
					<input TYPE="radio" NAME="action" VALUE="address" id="action_address" {checked_action_address}> <label for="action_address">{lang_forward_to_address}:</label>
				</td>
				<td>
					<input class="input_text" TYPE="text" NAME="address" style="width:250px;" onchange="document.getElementById('action_address').checked = true;" SIZE="40" value="{value_address}">
				</td>
			</tr>
			<tr CLASS="sieveRowActive">
				<td>
					<input TYPE="radio" NAME="action" VALUE="reject" id="action_reject" {checked_action_reject}> <label for="action_reject">{lang_send_reject_message}:</label>
				</td>
				<td>
					<textarea class="input_text" NAME="reject" style="width:400px;" onchange="document.getElementById('action_reject').checked = true;" ROWS="3" COLS="40" WRAP="hard" TABINDEX="14">{value_reject}</textarea>
				</td>
			</tr>
			<tr CLASS="sieveRowActive">
				<td>
					<input TYPE="radio" NAME="action" VALUE="discard" id="action_discard" {checked_action_discard}> <label for="action_discard">{lang_discard_message}</label>
				</td>
				<td>&nbsp;</td>
			</tr>
		</table>
	</fieldset>
	<fieldset style="width:680px;" class="row_on"><legend>{lang_extended}</legend>
		<table WIDTH="100%" CELLPADDING="2" CELLSPACING="1" style="border: 1px solid silver;">
			<tr CLASS="sieveRowActive">
				<td>
					<input TYPE="checkbox" NAME="continue" id="continue" VALUE="continue" {continue_checked}><label for="continue">{lang_check_message_against_next_rule_also}</label><br>
					<input TYPE="checkbox" NAME="keep" id="keep" VALUE="keep" {keep_checked}><label for="keep">{lang_keep_a_copy_of_the_message_in_your_inbox}</label><br>
					<input TYPE="checkbox" NAME="regexp" id="regexp" VALUE="regexp" {regexp_checked}><label for="regexp">{lang_use_regular_expressions}</label>
				</td>
			</tr>
		</table>
	</fieldset>
	<table style="width:680px; border: 0px solid silver;">
		<tr height="30" valign="bottom">
			<td align="left">
				<input name="save" value="{lang_save}" type="submit" onclick="delete window.onunload;"> &nbsp;
				<input name="apply" value="{lang_apply}" type="submit" onclick="delete window.onunload;"> &nbsp;
				<input name="cancel" value="{lang_cancel}" type="submit" onclick="opener.fm_sieve_cancelReload(); window.close()">
			</td>
		</tr>
	</table>
	<input type="hidden" name="ruleID" value="{value_ruleID}">
</form>
<!-- END main -->
