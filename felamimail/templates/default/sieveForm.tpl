<!-- BEGIN header -->
<!-- TEMPLATE: sieveForm.tpl -->
<script language="JavaScript1.2">

function submitRuleList(action)
{
	document.rulelist.rulelist_action.value = action;
	document.rulelist.submit();
}

function submitVacationRule(action)
{
	document.vacationrule.vacationRule_action.value = action;
	document.vacationrule.submit();
}

function createScript()
{
	var newscript = prompt('Please supply a name for your new script','');
	if (newscript)
	{
		document.addScript.newScriptName.value = newscript;
		document.addScript.submit();
	}
}

</script>
<center>

<table width="100%" border="0" cellspacing="0" cellpading="0">
	<tr>
		<th width="50%" id="tab1" class="activetab" onclick="javascript:tab.display(1);"><a href="#" tabindex="0" accesskey="1" onfocus="tab.display(1);" onclick="tab.display(1); return(false);">{lang_filter_rules}</a></th>
		<th width="50%" id="tab2" class="activetab" onclick="javascript:tab.display(2);"><a href="#" tabindex="0" accesskey="2" onfocus="tab.display(2);" onclick="tab.display(2); return(false);">{lang_vacation_notice}({lang_vacation_status})</a></th>
	</tr>
</table>

<!-- beginn of the code for filter rules Tab -->

<div id="tabcontent1" class="inactivetab">

<br>
<table border='0' width='100%'>
<tr class="text_small">
<td>
{lang_rule}: <a href="javascript:submitRuleList('enable');">{lang_enable}</a> 
<a href="javascript:submitRuleList('disable');">{lang_disable}</a> 
<a href="javascript:submitRuleList('delete');">{lang_delete}</a>
</td>
<td style='text-align : right;'>
<a href="{url_add_rule}">{lang_add_rule}</a>
</td>
</tr>
</table>
<form name='rulelist' method='post' action='{action_rulelist}'>
<input type='hidden' name='rulelist_action' value='unset'>
<table width="100%" border="0" cellpadding="2" cellspacing="1">
	<thead class="th">
		<tr>
			<th width="3%">&nbsp;</th>
			<th width="10%">Status</th>
			<th width="80%">{lang_rule}</th>
			<th width="5%">Order</th>
		</tr>
	</thead>
		{filterrows}
	<tbody>
	</tbody>
</table>
</form>
</div>
<!-- end of the code for Global Tab -->

<!-- beginn of the code for the vacation tab -->

<div id="tabcontent2" class="inactivetab">
<br>
<table border='0' width='100%'>
<tr class="text_small">
<td>
{lang_rule}: <a class="{css_enabled}" href="javascript:submitVacationRule('enable');">{lang_enable}</a> 
<a class="{css_disabled}" href="javascript:submitVacationRule('disable');">{lang_disable}</a> 
<a href="javascript:submitVacationRule('delete');">{lang_delete}</a>
<a href="javascript:submitVacationRule('save');">{lang_save}</a>
</td>
</tr>
</table>
<form ACTION="{vacation_action_url}" METHOD="post" NAME="vacationrule">
<input type='hidden' name='vacationRule_action' value='unset'>
<table WIDTH="100%" CELLPADDING="2" CELLSPACING="1" style="border: 1px solid silver;">
	<tr CLASS="th">
		<td colspan="2">
						{lang_edit_vacation_settings}      
		</td>
	</tr>
	<tr CLASS="sieveRowActive">
		<td>
			{lang_respond_to_mail_sent_to}:
		</td>
		<td nowrap="nowrap">
			{multiSelectBox}
		</td>
	</tr>
	<tr CLASS="sieveRowActive">
		<td>
			{lang_every}:
		</td>
		<td>
			<select name="days">
				<option value="0"></option>
				<option value="1" {selected_1}>1</option>
				<option value="2" {selected_2}>2</option>
				<option value="3" {selected_3}>3</option>
				<option value="4" {selected_5}>4</option>
				<option value="5" {selected_5}>5</option>
				<option value="6" {selected_6}>6</option>
				<option value="7" {selected_7}>7</option>
				<option value="8" {selected_8}>8</option>
				<option value="9" {selected_0}>9</option>
				<option value="10" {selected_10}>10</option>
				<option value="11" {selected_11}>11</option>
				<option value="12" {selected_12}>12</option>
				<option value="13" {selected_13}>13</option>
				<option value="14" {selected_14}>14</option>
				<option value="15" {selected_15}>15</option>
				<option value="16" {selected_16}>16</option>
				<option value="17" {selected_17}>17</option>
				<option value="18" {selected_18}>18</option>
				<option value="19" {selected_19}>19</option>
				<option value="20" {selected_20}>20</option>
				<option value="21" {selected_21}>21</option>
				<option value="22" {selected_22}>22</option>
				<option value="23" {selected_23}>23</option>
				<option value="24" {selected_24}>24</option>
				<option value="25" {selected_25}>25</option>
				<option value="26" {selected_26}>26</option>
				<option value="27" {selected_27}>27</option>
				<option value="28" {selected_28}>28</option>
				<option value="29" {selected_29}>29</option>
				<option value="30" {selected_30}>30</option>
			</select>
			{lang_days}
		</td>
	</tr>
	<tr CLASS="sieveRowActive">
		<td>
			{lang_with_message}:
		</td>
		<td nowrap="nowrap">
			<textarea class="input_text" name="vacation_text" rows="5" cols="60" wrap="hard" tabindex="1">{vacation_text}</textarea>
		</td>
	</tr>
	<tr>
		<td>
			&nbsp;
		</td>
	</tr>
</table>
<input type="hidden" name="ruleID" value="{value_ruleID}">
</form>

</div>
<!-- end of the code for the vacation Tab -->

<table border='0' width='100%'>
	<tr class="text_small">
		<td>
			<a href="{url_back}">{lang_back}</a> 
		</td>
	</tr>
</table>
</center>
<!-- END header -->

<!-- BEGIN filterrow -->
<tr class="{ruleCSS}" onmouseover="javascript:style.backgroundColor='#F6F7F4'" onmouseout="javascript:style.backgroundColor='#FFFFFF'" style="background-color: rgb(255, 255, 255);">
	<td style="text-align: center;">
		<input type="checkbox" name="ruleID[]" value="{ruleID}">
	</td>
	<td style="text-align: center;">
		{filter_status}
	</td>
	<td>
		<a class="{ruleCSS}" href="javascript:alert('buh');" onmouseover="window.status='Edit This Rule'; return true;" onmouseout="window.status='';">{filter_text}</a>
	</td>
	<td nowrap="nowrap" style="text-align: center;">
		<a href="{url_increase}"><img src="{url_up}" alt="Move rule up" border="0" onmouseover="window.status='Move rule up'; return true;" onmouseout="window.status='';"></a>
		<a href="{url_decrease}"><img src="{url_down}" alt="Move rule down" border="0" onmouseover="window.status='Move rule down'; return true;" onmouseout="window.status='';"></a>
	</td>
</tr>
<!-- END filterrow -->

<!-- BEGIN vacation -->

<form ACTION="{vacation_action_url}" METHOD="post" NAME="editVacation">
<p style="color: red; font-style: italic; text-align: center;">{validation_errors}</p>
<table WIDTH="100%" CELLPADDING="2" CELLSPACING="1" style="border: 1px solid silver;">
	<tr CLASS="th">
		<td colspan="2">
						{lang_edit_vacation_settings}      
		</td>
	</tr>
	<tr CLASS="sieveRowActive">
		<td>
			{lang_status}:
		</td>
		<td>
			<input type="radio" name="vacationStatus" {checked_active} value="on" id="status_active"> <label for="status_active">{lang_active}</label>
			<input type="radio" name="vacationStatus" {checked_disabled} value="off" id="status_disabled"> <label for="status_disabled">{lang_disabled}</label>
			{by_date}
		</td>
	</tr>
<!-- END timed_vaction -->

		</td>
	</tr>
	<tr CLASS="sieveRowActive">
		<td>
			{lang_respond_to_mail_sent_to}:
		</td>
		<td nowrap="nowrap">
			{multiSelectBox}
		</td>
	</tr>
	<tr CLASS="sieveRowActive">
		<td>
			{lang_every}:
		</td>
		<td>
			<select name="days">
				<option value="0"></option>
				<option value="1" {selected_1}>1</option>
				<option value="2" {selected_2}>2</option>
				<option value="3" {selected_3}>3</option>
				<option value="4" {selected_4}>4</option>
				<option value="5" {selected_5}>5</option>
				<option value="6" {selected_6}>6</option>
				<option value="7" {selected_7}>7</option>
				<option value="8" {selected_8}>8</option>
				<option value="9" {selected_0}>9</option>
				<option value="10" {selected_10}>10</option>
				<option value="11" {selected_11}>11</option>
				<option value="12" {selected_12}>12</option>
				<option value="13" {selected_13}>13</option>
				<option value="14" {selected_14}>14</option>
				<option value="15" {selected_15}>15</option>
				<option value="16" {selected_16}>16</option>
				<option value="17" {selected_17}>17</option>
				<option value="18" {selected_18}>18</option>
				<option value="19" {selected_19}>19</option>
				<option value="20" {selected_20}>20</option>
				<option value="21" {selected_21}>21</option>
				<option value="22" {selected_22}>22</option>
				<option value="23" {selected_23}>23</option>
				<option value="24" {selected_24}>24</option>
				<option value="25" {selected_25}>25</option>
				<option value="26" {selected_26}>26</option>
				<option value="27" {selected_27}>27</option>
				<option value="28" {selected_28}>28</option>
				<option value="29" {selected_29}>29</option>
				<option value="30" {selected_30}>30</option>
			</select>
			{lang_days}
		</td>
	</tr>
	<tr CLASS="sieveRowActive">
		<td>
			{lang_with_message}:<br />{set_as_default}
		</td>
		<td nowrap="nowrap">
			<textarea class="input_text" name="vacation_text" rows="7" cols="75" wrap="hard" tabindex="1">{vacation_text}</textarea>
			{lang_help_start_end_replacement}
		</td>
	</tr>
	<tr CLASS="sieveRowActive">
		<td>
			{lang_vacation_forwards}:
		</td>
		<td nowrap="nowrap">
			<input class="input_text" name="vacation_forwards" size="80" value="{vacation_forwards}" />
		</td>
	</tr>
	<tr>
		<td>
			&nbsp;
		</td>
	</tr>
	<tr height="30px" valign="bottom">
		<td align="left" colspan="2">
			<table border="0" valign="bottom">
			<tr>
				<td>
					<input name="save" value="{lang_save}" type="submit">
				</td>
				<td>
					<input name="apply" value="{lang_apply}" type="submit">
				</td>
				<td width=100%"> &nbsp; </td>
				<td align="right">
					<input name="cancel" value="{lang_cancel}" type="submit">
				</td>
			</tr>
			</table>
		</td>
	</tr>
</table>
</form>
<!-- END vacation -->

</table>
<input type="hidden" name="ruleID" value="{value_ruleID}">
</form>
<!-- END vacation -->

<!-- BEGIN email_notification -->
<form ACTION="{email_notification_action_url}" METHOD="post" NAME="editVacation">
<table WIDTH="100%" CELLPADDING="2" CELLSPACING="1" style="border: 1px solid silver;">
  <tr CLASS="th">
    <td colspan="2">email notification settings</td>
  </tr>
  <tr CLASS="row_on">
    <td>Status:</td>
    <td>
      <input type="radio" name="emailNotificationStatus"{checked_active} value="active"> {lang_active}
      <input type="radio" name="emailNotificationStatus"{checked_disabled} value="disabled"> {lang_disabled}
    </td>
  </tr>
  <tr CLASS="row_off">
    <td>External email:</td>
    <td nowrap="nowrap"><input type="text" size="35" name="emailNotificationExternalEmail" value="{external_email}" /></td>
  </tr>
  <tr CLASS="row_on">
    <td>Display mail subject in notification:</td>
    <td>
      <input type="radio" name="emailNotificationDisplaySubject"{checked_yes} value="1"> {lang_yes}
      <input type="radio" name="emailNotificationDisplaySubject"{checked_no} value="0"> {lang_no}
    </td>
  </tr>
  <tr>
    <td>
      &nbsp;
    </td>
  </tr>
  <tr height="30px" valign="bottom">
    <td align="left" colspan="2">
      <table border="0" valign="bottom">
      <tr>
        <td>
          <input name="save" value="{lang_save}" type="submit">
        </td>
        <td>
          <input name="apply" value="{lang_apply}" type="submit">
        </td>
        <td width=100%"> &nbsp; </td>
        <td align="right">
          <input name="cancel" value="{lang_cancel}" type="submit">
        </td>
      </tr>
      </table>
    </td>
  </tr>
</table>
</form>
<!-- END email_notification -->
