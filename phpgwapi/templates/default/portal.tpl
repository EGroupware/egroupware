<!-- BEGIN portal_box -->
<p>
<table cellpadding="0" cellspacing="0" width="{outer_width}" class="portal">
 <tr nowrap align="center">
  <td align="center" background="{header_background_image}">{title}</td>{portal_controls}
 </tr>
 <tr>
  <td colspan="2">
   <table cellpadding="0" cellspacing="0" width="{inner_width}" class="portal">
    {row}
   </table>
  </td>
 </tr>
</table>
</p>
<!-- END portal_box -->
<!-- BEGIN portal_row -->
    <tr>
{output}
    </tr>
<!-- END portal_row -->
<!-- BEGIN portal_listbox_header -->
     <td>
      <ul>
<!-- END portal_listbox_header -->
<!-- BEGIN portal_listbox_link -->
<li><a href="{link}">{text}</a></li>
<!-- END portal_listbox_link -->
<!-- BEGIN portal_listbox_footer -->
      </ul>
     </td>
<!-- END portal_listbox_footer -->
<!-- BEGIN portal_control -->
  <td valign="middle" align="right" nowrap background="{header_background_image}">{control_link}
  </td>
<!-- END portal_control -->
<!-- BEGIN link_field -->
   {link_field_data}
<!-- END link_field -->
