<!-- BEGIN view_header -->
<p>&nbsp;<b>{lang_viewpref}</b><hr><p>
<table border="0" cellspacing="2" cellpadding="2" width="70%" align="center">
<!-- END view_header -->
<!-- BEGIN view_row -->
  <tr bgcolor="{th_bg}">
    <td align="right" width="30%"><b>{display_col}</b>:</td><td width="70%">{ref_data}</td>
  </tr>
<!-- END view_row -->
{cols}
<!-- BEGIN view_footer -->
  <tr>
    <td colspan="4">&nbsp;</td>
  </tr>
  <tr>
    <td><b>{lang_owner}</b></td>
    <td>{owner}</td>
  </tr>
  <tr>
    <td><b>{lang_access}</b></td>
    <td>{access}</b>
    </td>
  </tr>
  <tr>
    <td><b>{lang_category}</b></td>
    <td>{catname}</b></td>
  </tr>
  </td>
</td>
</table>
<!-- END view_footer -->
<!-- BEGIN view_buttons -->
<center>
 <TABLE border="0" cellpadding="1" cellspacing="1">
  <TR> 
   <TD align="left">
     {edit_link}
     <input type="hidden" name="ab_id" value="{ab_id}">
     {edit_button}
    </form>
   </TD>
   <TD align="left">
     {copy_link}
     <input type="hidden" name="sort" value="{sort}">
     <input type="hidden" name="order" value="{order}">
     <input type="hidden" name="filter" value="{filter}">
     <input type="hidden" name="start" value="{start}">
     <input type="hidden" name="fields" value="{copy_fields}">
     <input type="submit" name="submit" value="{lang_copy}">
    </form>
   </TD>
   <TD align="left">
     {vcard_link}
     <input type="hidden" name="ab_id" value="{ab_id}">
     <input type="hidden" name="sort" value="{sort}">
     <input type="hidden" name="order" value="{order}">
     <input type="hidden" name="filter" value="{filter}">
     <input type="hidden" name="start" value="{start}">
     <input type="submit" name="VCardform" value="{lang_vcard}">
    </form>
   </TD>
   <TD align="left">
     {done_link}
     <input type="hidden" name="sort" value="{sort}">
     <input type="hidden" name="order" value="{order}">
     <input type="hidden" name="filter" value="{filter}">
     <input type="hidden" name="start" value="{start}">
     <input type="submit" name="Doneform" value="{lang_done}">
    </form>
   </TD>
  </TR>
 </TABLE>
</center>
<!-- END view_buttons -->
