<!-- BEGIN form -->
<br><br>
<center>
   {title}<br>
   {message}<br>
   <form action="{actionurl}" method="POST">
   {hidden_vars}
   <table border="0" width="60%" cellspacing="0" cellpadding="0">
     <tr>
       <td align="center" bgcolor="#c9c9c9"><font face="{font}"><b>{lang_action}:&nbsp;{name}</b></font></td>
     </tr>
     <tr>
       <td>&nbsp;</td>
     </tr>
     <tr>
       <td align="center"><font face="{font}" size="2"><input size="32" name="field" value="{field}"></font></td>
     </tr>
   </table><br><br>
<!-- BEGIN add -->
   <table width="60%" border="0" cellspacing="2" cellpadding="2">
     <tr>
       <td height="50" align="center">
       {hidden_vars}
       <font face="{font}"><input type="submit" name="addfield" value="{lang_add}"></font></td>
       <td height="50" align="center">
       <font face="{font}"><input type="reset" name="reset" value="{lang_reset}"></font></form></td>
       <td height="50" align="center"><font face="{font}"><a href="{listurl}">{lang_list}</a></font></td>
     </tr>
   </table>
</center>
<!-- END add -->

<!-- BEGIN edit -->
   <table width="60%" border="0" cellspacing="1" cellpadding="3">
     <tr>
       <td height="50" align="center">
       {hidden_vars}
       <font face="{font}"><input type="submit" name="editfield" value="{lang_edit}"></font>
       </form></td>
       <td height="50" align="center">
       <form method="POST" action="{deleteurl}">
       {hidden_vars}
       <font face="{font}"><input type="submit" name="delete" value="{lang_delete}"></font></form></td>
       <td height="50" align="center"><font face="{font}"><a href="{listurl}">{lang_list}</a></font></td>
     </tr>
   </table>
</center>
<!-- END edit -->

<!-- BEGIN delete -->
   <table width="60%" border="0" cellspacing="1" cellpadding="3">
     <tr>
       <td height="50" align="center">
       {hidden_vars}
       </form></td>
       <td height="50" align="center"><font face="{font}"><a href="{listurl}">{lang_list}</a></font></td>
     </tr>
   </table>
</center>
<!-- END delete -->

<!-- END form -->
