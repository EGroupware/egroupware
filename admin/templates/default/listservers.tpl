 <center>
  <table border="0" cellspacing="2" cellpadding="2">
   <tr>
    <td colspan="6" align="center" bgcolor="#c9c9c9"><b>{title_servers}<b/></td>
   </tr>
   <tr>
    <td colspan="6" align="left">
     <table border="0" width="100%">
      <tr>
      {left}
       <td align="center">{lang_showing}</td>
      {right}
      </tr>
     </table>
    </td>
   </tr>
   <tr>
    <td>&nbsp;</td>
    <td colspan="6" align="right">
     <form method="post" action="{actionurl}">
     <input type="text" name="query">&nbsp;<input type="submit" name="search" value="{lang_search}"></form></td>
   </tr>
   <tr class="th">
    <td class="th">{sort_name}</td>
    <td class="th">{sort_url}</td>
    <td class="th">{sort_mode}</td>
    <td class="th">{sort_security}</td>
    <td class="th" align="center">{lang_edit}</td>
    <td class="th" align="center">{lang_delete}</td>
   </tr>

<!-- BEGIN server_list -->
   <tr class="{tr_color}">
    <td>{server_name}</td>
    <td>{server_url}</td>
    <td>{server_mode}</td>
    <td>{server_security}&nbsp;</td>
    <td align="center"><a href="{edit}">{lang_edit_entry}</a></td>
    <td align="center"><a href="{delete}">{lang_delete_entry}</a></td>
   </tr>
<!-- END server_list -->

<!-- BEGIN add   -->
</form>
   <tr valign="bottom">
     <form method="POST" action="{add_action}">
     <td colspan="3"><input type="submit" name="add" value="{lang_add}"></form></td>
     </form>
     <form method="POST" action="{doneurl}">
     <td align="right" colspan="3"><input type="submit" name="done" value="{lang_done}"></form></td>
   </tr>
<!-- END add -->

  </table>
 </center>
