<!-- $Id$ -->
<!-- BEGIN form -->
<center>
<table border="0" width="80%" cellspacing="2" cellpadding="2">
 <tr><td colspan="1" align="center" bgcolor="#c9c9c9"><b>{title_holiday}<b/></td></tr>
</table>
{message}
<table border="0" width="80%" cellspacing="2" cellpadding="2"> 
 <form name="form" action="{actionurl}" method="POST">
  {hidden_vars}
{rows}
 <tr>
  <td colspan="2">
   <table width="100%" border="0" cellspacing="2" cellpadding="2">
    <tr valign="bottom">
     <td height="50" align="center">
      <input type="submit" name="submit" value="{lang_add}">
     </td>
     <td height="50" align="center">
      <input type="reset" name="reset" value="{lang_reset}">
     </td>
    </tr>
   </table>
  <td>
 </tr>
 </form>
</table>
{cancel_button}
</center>
<!-- END form -->
<!-- BEGIN list -->
 <tr>
  <td valign="top" width="35%"><b>{field}:</b></td>
  <td valign="top" width="65%">{data}</td>
 </tr>
<!-- END list -->
