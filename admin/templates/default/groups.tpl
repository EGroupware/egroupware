<!-- BEGIN list -->
<p>
 <table border="0" width="45%" align="center">
  <tr>
   <td align="left">{left_nextmatchs}</td>
   <td align="center">{lang_groups}</td>
   <td align="right">{right_nextmatchs}</td>
  </tr>
 </table>

 <table border="0" width="45%" align="center">
  <tr bgcolor="{th_bg}">
   <td>{sort_name}</td>
   <td>{header_edit}</td>
   <td>{header_delete}</td>
  </tr>

  {rows}

 </table>

 <table border="0" width="45%" align="center">
  <tr>
   <td align="left">
    <form method="POST" action="{new_action}">
     <input type="submit" value="{lang_add}">
    </form>
   </td>
   <td align="right">{lang_search}&nbsp;
    <form action="{search_action}">
     <input name="query">
    </form>
   </td>
  </tr>
 </table>
<!-- END list -->
