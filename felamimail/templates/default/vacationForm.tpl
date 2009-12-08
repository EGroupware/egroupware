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
<form ACTION="{action_url}" METHOD="post" NAME="thisRule">

<table WIDTH="100%" CELLPADDING="2" CELLSPACING="1" style="border: 1px solid silver;">
	<tr CLASS="th">
		<td colspan="2">
						{lang_edit_vacation_settings}      
		</td>
	</tr>
	<tr CLASS="sieveRowActive">
		<td>
			{lang_vacation_notice}:
		</td>
		<td nowrap="nowrap">
			<textarea class="input_text" name="vacation_text" rows="3" cols="40" wrap="hard" tabindex="1"></textarea>
		</td>
	</tr>
	<tr CLASS="sieveRowActive">
		<td>
			{lang_send_vacation_notice_every}:
		</td>
		<td>
			<select name="days">
				<!-- <option value="0"></option> -->
				<option value="1">1</option>
				<option value="2">2</option>
				<option value="3">3</option>
				<option value="4">4</option>
				<option value="5">5</option>
				<option value="6">6</option>
				<option value="7">7</option>
				<option value="8">8</option>
				<option value="9">9</option>
				<option value="10">10</option>
				<option value="11">11</option>
				<option value="12">12</option>
				<option value="13">13</option>
				<option value="14">14</option>
				<option value="15">15</option>
				<option value="16">16</option>
				<option value="17">17</option>
				<option value="18">18</option>
				<option value="19">19</option>
				<option value="20">20</option>
				<option value="21">21</option>
				<option value="22">22</option>
				<option value="23">23</option>
				<option value="24">24</option>
				<option value="25">25</option>
				<option value="26">26</option>
				<option value="27">27</option>
				<option value="28">28</option>
				<option value="29">29</option>
				<option value="30">30</option>
			</select>
			{lang_days}
		</td>
	</tr>
	<tr CLASS="sieveRowActive">
		<td>
			{lang_local_email_address}:
		</td>
		<td nowrap="nowrap">
			{multiSelectBox}
		</td>
	</tr>
	<tr>
		<td>
			&nbsp;
		</td>
	</tr>
	<tr>
		<td colspan="2">
			<table WIDTH="100%" CELLPADDING="2" BORDER="0" CELLSPACING="0">
				<tr>
					<td>
						<a href="{url_back}">{lang_back}</a>
					</td>
					<td CLASS="options" style="text-align : right;">
						<a CLASS="option" HREF="javascript:SubmitForm('save');" onmouseover="window.status='Save Changes';" onmouseout="window.status='';">{lang_save_changes}</a>
					</td>
				</tr>
			</table>
		</td>
	</tr>
</table>
<input type="hidden" name="ruleID" value="{value_ruleID}">
</form>
<!-- END main -->

<!-- BEGIN folder -->
							<option VALUE="{folderName}">{folderDisplayName}</option>
<!-- END folder -->
	