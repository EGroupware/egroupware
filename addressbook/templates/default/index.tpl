
<!-- BEGIN addressbook_header -->
<center>{lang_addressbook}
<br>{lang_showing}
<br>{searchreturn}
  <form action="{cats_url}" method="POST">
{cats}{cats_link}
    <input type="hidden" name="sort" value="{sort}">
    <input type="hidden" name="order" value="{order}">
    <input type="hidden" name="filter" value="{filter}">
    <input type="hidden" name="query" value="{query}">
    <input type="hidden" name="start" value="{start}">
    <noscript><input type="submit" name="cats" value="{lang_cats}"></noscript>
  </form>
{search_filter}

<table width=75% border=0 cellspacing=1 cellpadding=3>
<tr bgcolor="{th_bg}">
{cols}
  <td width="3%" height="21">
    <font face="Arial, Helvetica, sans-serif" size="-1">
    {lang_view}
    </font>
  </td>
  <td width="3%" height="21">
    <font face="Arial, Helvetica, sans-serif" size="-1">
    {lang_vcard}
    </font>
  </td>
  <td width="5%" height="21">
    <font face="Arial, Helvetica, sans-serif" size="-1">
    {lang_edit}
    </font>
  </td>
  <td width="5%" height="21">
    <font face="Arial, Helvetica, sans-serif" size="-1">
    {lang_owner}
    </font>
  </td>
</tr>
<!-- END addressbook_header -->
<!-- BEGIN column -->
  <td valign="top">
    <font face="Arial, Helvetica, san-serif" size="2">{col_data}&nbsp;</font>
  </td>
<!-- END column -->
<!-- BEGIN row -->
<tr bgcolor="{row_tr_color}">
{columns}
  <td valign="top" width="3%">
    <font face="{font}" size="2"><a href="{row_view_link}">{lang_view}</a></font>
  </td>
  <td valign="top" width=3%>
    <font face="{font}" size=2><a href="{row_vcard_link}">{lang_vcard}</a></font>
  </td>
  <td valign="top" width="5%">
    <font face="{font}" size="2">{row_edit}</font>
  </td>
  <td valign="top" width="5%">
    <font face="{font}" size="2">{row_owner}</font>
  </td>
</tr>
<!-- END row -->
<!-- BEGIN addressbook_footer -->
 </table>
 <table width="75%" border="0" cellspacing="0" cellpadding="4">
   <tr> 
     <td width="16%"> 
       <div align="left"> 
         <form action="{add_url}" method="post">
         <input type="hidden" name="sort" value="{sort}">
         <input type="hidden" name="order" value="{order}">
         <input type="hidden" name="filter" value="{filter}">
         <input type="hidden" name="start" value="{start}">
        <input type="submit" name="Add" value="{lang_add}">
       </div>
     </td>
     <td width="16%">
       <div align="left">
         <form action="{vcard_url}" method="post">
         <input type="hidden" name="sort" value="{sort}">
         <input type="hidden" name="order" value="{order}">
         <input type="hidden" name="filter" value="{filter}">
         <input type="hidden" name="start" value="{start}">
        <input type="submit" name="AddVcard" value="{lang_addvcard}">
       </div>
     </td>
     <td width="64%">&nbsp;</td>
     <td width="4%">&nbsp;</td>
   </tr>
   </form>
   <tr>
     <td width="16%">
       <div align="left">
         <form action="{import_url}" method="post">
         <input type="hidden" name="sort" value="{sort}">
         <input type="hidden" name="order" value="{order}">
         <input type="hidden" name="filter" value="{filter}">
         <input type="hidden" name="start" value="{start}">
         <input type="submit" name="Import" value="{lang_import}">
         </form>
       </div>
     </td>
     <td width="16%">
       <div align="left">
         <form action="{export_url}" method="post">
         <input type="hidden" name="sort" value="{sort}">
         <input type="hidden" name="order" value="{order}">
         <input type="hidden" name="filter" value="{filter}">
         <input type="hidden" name="start" value="{start}">
         <input type="submit" name="Export" value="{lang_export}">
         </form>
       </div>
     </td>
     <td width="64%">&nbsp;</td>
     <td width="4">&nbsp;</td>
   </tr>
 </table>
 </center>
<!-- END addressbook_footer -->
