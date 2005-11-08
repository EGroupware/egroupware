{errors}
<table border="0" align="center" width="50%">
 <tr>
  {nml}
  <td width="40%">
   <div align="center">
    <form method="POST" action="{action_url}">
{common_hidden_vars}
     <input type="text" name="query" value="{search_value}">
     <input type="submit" name="search" value="{search}">
    </form>
   </div>
  </td>
  {nmr}
 </tr>
</table>
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
