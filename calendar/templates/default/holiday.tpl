<!-- $Id$ -->
<!-- BEGIN form -->
<center>
{message}<br>
<table border="0" width="80%" cellspacing="2" cellpadding="2"> 
 <form name="form" action="{actionurl}" method="POST">
  {hidden_vars}
{rows}
 <tr>
  <td colspan="2">
   <table width="100%" border="0" cellspacing="5">
    <tr>
     <td>
      <input type="submit" name="submit" value="{lang_add}"></form>
     </td>
     <td>
      {cancel_button}
     </td>
     <td align="right" width="80%">
      {delete_button}
     </td>
    </tr>
   </table>
  <td>
 </tr>
</table>
</center>
<!-- END form -->
<!-- BEGIN list -->
 <tr bgcolor="{tr_color}">
  <td valign="top" width="35%"><b>{field}:</b></td>
  <td valign="top" width="65%">{data}</td>
 </tr>
<!-- END list -->
