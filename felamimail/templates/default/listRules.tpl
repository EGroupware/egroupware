<!-- BEGIN header -->
<script language="JavaScript1.2">

function submitRuleList(action)
{
	document.rulelist.rulelist_action.value = action;
	$j( "#sortable input[type=checkbox]").each(function(){
		this.value = $j(this).parent().parent().index();
	});
	document.rulelist.submit();
}
//egw.LAB.wait(function() {
	$j(document).ready(function() {
		$j( "#sortable tbody" ).sortable({
			start: function(e, ui) {
				ui.item.data('start-pos', ui.item.index());
			},
			update: function(e, ui) {
				var request = new egw_json_request('felamimail.uisieve.ajax_moveRule', [ui.item.data('start-pos'), ui.item.index()]);
				request.sendRequest(true);
			}
		});
		$j( "#sortable tbody" ).disableSelection();
		$j( "#sortable tbody a" ).on("click", function(e){
			fm_sieve_displayRuleEditWindow('{url_edit_rule}&ruleID='+$j(this).parent().parent().index()); 
			return false;
		});
	});
// });

var refreshURL='{refreshURL}';

</script>
<center>
<div style="color:red;">{message}</div>
<table border='0' width='100%'>
<tr class="text_small">
<td>
{lang_rule}: <a href="javascript:submitRuleList('enable');">{lang_enable}</a> 
<a href="javascript:submitRuleList('disable');">{lang_disable}</a> 
<a href="javascript:submitRuleList('delete');">{lang_delete}</a>
</td>
<td style='text-align : right;'>
<a href="#" onclick="fm_sieve_displayRuleEditWindow('{url_edit_rule}'); return false;">{lang_add_rule}</a>
</td>
</tr>
</table>
<form name='rulelist' method='post' action='{action_rulelist}'>
<input type='hidden' name='rulelist_action' value='unset'>
<table width="100%" border="0" cellpadding="2" cellspacing="1" id="sortable">
	<thead class="th">
		<tr>
			<th width="3%">&nbsp;</th>
			<th width="10%">Status</th>
			<th width="80%">{lang_rule}</th>
		</tr>
	</thead>
	<tbody>
		{filterrows}
	</tbody>
</table>
</form>

</center>
<!-- END header -->

<!-- BEGIN filterrow -->
<tr class="{ruleCSS}">
	<td style="text-align: center;">
		<input type="checkbox" name="ruleID[]" value="x">
	</td>
	<td style="text-align: center;">
		{filter_status}
	</td>
	<td>
		<a class="{ruleCSS}" href="#" ondblclick="fm_sieve_displayRuleEditWindow('{url_edit_rule}'); return false;" onmouseover="window.status='Edit This Rule'; return true;" onmouseout="window.status='';">{filter_text}</a>
	</td>
</tr>
<!-- END filterrow -->
