 <center>
  <table border="0" cellspacing="2" cellpadding="2">
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
   
<!-- BEGIN search -->
   <tr>
    <td colspan="6" align="right">
     <form method="post" action="{actionurl}">
     <input type="text" name="query">&nbsp;<input type="submit" name="search" value="{lang_search}"></form></td>
   </tr>
<!-- END search -->

   <tr bgcolor="{th_bg}">
    <td bgcolor="{th_bg}">{sort_name}</td>
    <td bgcolor="{th_bg}">{sort_url}</td>
    <td bgcolor="{th_bg}">{sort_mode}</td>
    <td bgcolor="{th_bg}">{sort_security}</td>
    <td bgcolor="{th_bg}" align="center">{lang_edit}</td>
    <td bgcolor="{th_bg}" align="center">{lang_delete}</td>
   </tr>

<!-- BEGIN server_list -->
   <tr bgcolor="{tr_color}">
    <td>{server_name}</td>
    <td>{server_url}</td>
    <td>{server_mode}</td>
    <td>{server_security}&nbsp;</td>
    <td align="center">{edit}</td>
    <td align="center">{delete}</td>
   </tr>
<!-- END server_list -->

   <tr valign="bottom">
<!-- BEGIN add   -->
     <td><form method="POST" action="{add_action}">
       <input type="submit" name="add" value="{lang_add}"></form>
     </td>
<!-- END add -->
     <td><form method="POST" action="{doneurl}">
       <input type="submit" name="done" value="{lang_done}"></form>
     </td>
   </tr>

  </table>
 </center>
