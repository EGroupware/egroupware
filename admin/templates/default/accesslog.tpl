<!-- BEGIN list -->
  <p>
  {link_return_to_view_account}
  <table border="0" align="center" width="75%">
   <tr>
    <td align="left">
      {lang_last_x_logins}
    </td>
    <td align="center" colspan="3">
      {showing}
    </td>
    <td align="right">

      <table border="0">
       <tr>
        {nextmatchs_left}
        &nbsp;
        {nextmatchs_right}
       </tr>
      </table>

    </td>
   </tr>
   <tr bgcolor="{th_bg}">
    <td>{lang_loginid}</td>
    <td>{lang_ip}</td>
    <td>{lang_login}</td>
    <td>{lang_logout}</td>
    <td>{lang_total}</td>
   </tr>

   {rows}

   <tr bgcolor="{bg_color}">
    <td colspan="5" align="left">{footer_total}</td>
   </tr>

   <tr bgcolor="{bg_color}">
    <td colspan="5" align="left">{lang_percent}</td>
   </tr>
  </table>
<!-- END list -->

<!-- BEGIN row -->
   <tr bgcolor="{tr_color}">
    <td>{row_loginid}</td>
    <td>{row_ip}</td>
    <td>{row_li}</td>
    <td>{row_lo}</td>
    <td>{row_total}</td>
   </tr>
<!-- END row -->

<!-- BEGIN row_empty -->
   <tr bgcolor="{tr_color}">
    <td align="center" colspan="5">{row_message}</td>
   </tr>
<!-- END row_empty -->
