<!-- BEGIN list -->
<style type="text/css">
	.letter_box,.letter_box_active {
		background-color: #D3DCE3;
		width: 25px;
		border: 1px solid #D3DCE3;
		text-align: center;
		cursor: pointer;
		cusror: hand;
	}
	.letter_box_active {
		font-weight: bold;
		background-color: #E8F0F0;
	}
	.letter_box_active,.letter_box:hover {
		border: 1px solid black;
		background-color: #E8F0F0;
	}
</style>
{errors}
<table border="0" align="center" width="50%">
<tr>
	<td colspan="5">
		<table width="100%"><tr>
<!-- BEGIN letter_search -->
			<td class="{class}" onclick="location.href='{link}';">{letter}</td>
<!-- END letter_search -->
		</tr></table>
	</td>
</tr>
<tr>
	<td colspan="5" align="center">{lang_groups}</td>
</tr>
<tr>
  {nml}
  <td width="40%">
   <div align="center">
    <form method="POST" action="{action_url}">
{common_hidden_vars_form}
{search_type}
     <input type="text" name="query" value="{search_value}">
     <input type="submit" name="search" value="{search}">
    </form>
   </div>
  </td>
  {nmr}
 </tr>
</table>
<!-- END list -->
<form method="POST" action="{action_url}">
{common_hidden_vars_form}
 <input type="hidden" name="processed" value="{processed}">
 <table border="0" align="center" width="50%">
{row}
  <tr><td colspan="3" nowrap>
 	<input type="submit" name="save" value="{lang_save}">
 	<input type="submit" name="apply" value="{lang_apply}">
 	<input type="submit" name="cancel" value="{lang_cancel}">
 </td></tr>
</table>
</form>