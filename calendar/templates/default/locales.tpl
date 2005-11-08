<!-- BEGIN list -->
<p class="row_on" style="text-align: center; border: 1px dotted black; padding: 10px;">{help_msg}</p>

 <table border="0" width="45%" align="center">
  <tr>
   <td align="left">{left_next_matchs}</td>
   {center}
   <td align="right">{right_next_matchs}</td>
  </tr>
 </table>
 <p align="center">{total}</p>

 <table border="0" width="70%" align="center">
  <tr bgcolor="{th_bg}">
   <td>&nbsp;{sort_name}</td>
   {header_rule}
   <td>{header_edit}</td>
   <td>{header_delete}</td>
   <td>{header_extra}</td>
  </tr>

  {rows}

 </table>

 <table border="0" width="70%" cellspacing="5" align="center">
  <tr>
   <td align="left">
    <form method="POST" action="{new_action}">
     <input type="submit" value="{lang_add}">
    </form>
   </td>
   {back_button}
   <td width="80%" align="right">
    <form method="POST" action="{search_action}">
     {lang_search}&nbsp;<input name="query">
    </form>
   </td>
  </tr>
 </table>
<!-- END list -->
<!-- BEGIN row -->
 <tr bgcolor="{tr_color}">
  <td>&nbsp;{group_name}</td>
  {rule}
  <td width="5%">{edit_link}</td>
  <td width="5%">{delete_link}</td>
  <td align="center" {extra_width}>{extra_link}</td>
 </tr>
<!-- END row -->
<!-- BEGIN row_empty -->
   <tr>
    <td colspan="5" align="center">{message}</td>
   </tr>
<!-- END row_empty -->
<!-- BEGIN back_button_form -->
   <td align="center">
    <form method="POST" action="{back_action}">
     <input type="submit" value="{lang_back}">
    </form>
   </td>
<!-- END back_button_form -->
