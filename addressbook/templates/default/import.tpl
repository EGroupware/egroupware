
<!-- BEGIN import -->
<CENTER>
  <TABLE WIDTH=90%>
    <TR BGCOLOR="{navbar_bg}">
      <TD><B><FONT SIZE=+2 COLOR="{navbar_text}"><CENTER>{import_text}</CENTER></FONT></B>
      </TD>
    </TR>
    <TR>
      <TD>
        <TABLE WIDTH=85%>
    <TR>
     <TD><FORM ENCTYPE="multipart/form-data" action="{action_url}" method="post">
            <OL>
            <LI>{help_import}
            </LI>
            <LI>{export_path}:
              <INPUT NAME="tsvfile" SIZE="48" TYPE="file" VALUE="{tsvfilename}"><P></LI>
            <LI>{conversion}:
            <SELECT NAME="conv_type">
            <OPTION VALUE="none">&lt;{none}&gt;</OPTION>
     {conv}
            </SELECT><P></LI>
   <LI>{lang_cat}:{cat_link}</LI>
   <LI><INPUT NAME="private" TYPE="checkbox" VALUE="private" CHECKED>{mark_private}</LI>
            <LI><INPUT NAME="download" TYPE="checkbox" VALUE="{debug}" CHECKED>{debug_output}</LI>
            <LI><INPUT NAME="convert" TYPE="submit" VALUE="{download}"></LI>
            </OL>
              <input type="hidden" name="sort" value="{sort}">
              <input type="hidden" name="order" value="{order}">
              <input type="hidden" name="filter" value="{filter}">
              <input type="hidden" name="query" value="{query}">
              <input type="hidden" name="start" value="{start}">
            </FORM></TD>
          </TR>
        </TABLE>
      </TD>
    </TR>
   <tr>
     <td width="8%">
       <div align="left">
        <form action="{cancel_url}" method="post">
        <input type="hidden" name="sort" value="{sort}">
        <input type="hidden" name="order" value="{order}">
        <input type="hidden" name="filter" value="{filter}">
        <input type="hidden" name="query" value="{query}">
        <input type="hidden" name="start" value="{start}">
        <input type="submit" name="Cancel" value="{lang_cancel}">
     </form>
       </div>
     </td>
     <td width="64%">&nbsp;</td>
     <td width="32">&nbsp;</td>
   </tr>
  </TABLE>
</CENTER>
<!-- END import -->
