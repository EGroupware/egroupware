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
         <input type="hidden" name="cat_id" value="{cat_id}">
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
         <input type="hidden" name="cat_id" value="{cat_id}">
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
         <input type="hidden" name="cat_id" value="{cat_id}">
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
         <input type="hidden" name="cat_id" value="{cat_id}">
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
