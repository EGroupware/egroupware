<!-- BEGIN header -->
<script language="JavaScript1.2">

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
<i>Scripts available for this account.</i><br>
<br>
<form method='post' action='{action_add_script}' name='addScript'>
<table border="0" width="100%" cellpadding="1" cellspacing="1" style="border: 1px solid white;">
	<tr class="th">
		<td colspan="3" style='text-align : right;'>
			<a href="javascript:createScript();">{lang_add_script}</a>
		</td>
	</tr>
	<tr class="th">
		<td style='text-align : center; width:60%'>
			{lang_script_name}
		</td>
		<td style='text-align : center; width:20%'>
			{lang_script_status}
		</td>
		<td style='text-align : center; width:20%'>
			{lang_delete_script}
		</td>
	</tr>
	{scriptrows}
</table>
<input type='hidden' name='newScriptName'>
</form>
</center>
<!-- END header -->

<!-- BEGIN scriptrow -->
<tr class="{ruleCSS}" onmouseover="javascript:style.backgroundColor='#F6F7F4'" onmouseout="javascript:style.backgroundColor='#FFFFFF'" style="background-color: rgb(255, 255, 255);">
	<td align="center">
		<a class="{ruleCSS}" href={link_editScript}>{scriptname} (Script {scriptnumber})</a>
	</td>
	<td align="center">
		<a class="{ruleCSS}" href={link_activateScript}>{lang_activate}</a>{active}
	</td>
	<td align="center">
		<a class="{ruleCSS}" href={link_deleteScript}>{lang_delete}</a>
	</td>
</tr>
<!-- END scriptrow -->

