{errors}
{title}
<table border="0" align="center" width="70%">
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
 <table border="0" align="center" width="50%">
{row}
 </table>
{common_hidden_vars_form}
 <input type="hidden" name="processed" value="{processed}">
 <center><input type="submit" name="submit" value="{submit_lang}"></center>
</form>


