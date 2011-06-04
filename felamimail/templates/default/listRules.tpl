<!-- BEGIN header -->
<script language="JavaScript1.2">

function submitRuleList(action)
{
	document.rulelist.rulelist_action.value = action;
	document.rulelist.submit();
}

var refreshURL='{refreshURL}';

</script>
<center>

<table border='0' width='100%'>
<tr class="text_small">
<td>
{lang_rule}: <a href="javascript:submitRuleList('enable');">{lang_enable}</a> 
<a href="javascript:submitRuleList('disable');">{lang_disable}</a> 
<a href="javascript:submitRuleList('delete');">{lang_delete}</a>
</td>
<td style='text-align : right;'>
<!-- <a href="{url_add_rule}">{lang_add_rule}</a> -->
<a href="#" onclick="fm_sieve_displayRuleEditWindow('{url_add_rule}'); return false;">{lang_add_rule}</a>
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
		<a class="{ruleCSS}" href="#" onclick="fm_sieve_displayRuleEditWindow('{url_edit_rule}'); return false;" onmouseover="window.status='Edit This Rule'; return true;" onmouseout="window.status='';">{filter_text}</a>
	</td>
	<td nowrap="nowrap" style="text-align: center;">
		<a href="{url_increase}"><img src="{url_up}" alt="Move rule up" border="0" onmouseover="window.status='Move rule up'; return true;" onmouseout="window.status='';"></a>
		<a href="{url_decrease}"><img src="{url_down}" alt="Move rule down" border="0" onmouseover="window.status='Move rule down'; return true;" onmouseout="window.status='';"></a>
	</td>
</tr>
<!-- END filterrow -->