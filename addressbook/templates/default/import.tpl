
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
            <LI>In Netscape, open the Addressbook and select <b>Export</b> from the <b>File</b> menu.
                The file exported will be in LDIF format.
              <P>Or, in Outlook, select your Contacts folder, select <b>Import 
                and Export...</b> from the <b>File</b> 
                menu and export your contacts into a comma separated text (CSV) file.
              <P>Or, in Palm Desktop 4.0 or greater, visit your addressbook and select <b>Export</b> from the <b>File</b> menu.
                The file exported will be in VCard format.<P>
            </LI>
            <LI>Enter the path to the exported file here:
              <INPUT NAME="tsvfile" SIZE="48" TYPE="file" VALUE="{tsvfilename}"><P></LI>
            <LI>Select the type of conversion:
            <SELECT NAME="conv_type">
            <OPTION VALUE="none">&lt;none&gt;</OPTION>
     {conv}
            </SELECT><P></LI>
   <LI>{lang_cat}:{cat_link}</LI>
   <LI><INPUT NAME="private" TYPE="checkbox" VALUE="private" CHECKED>Mark records as private</LI>
            <LI><INPUT NAME="download" TYPE="checkbox" VALUE="{debug}" CHECKED>Debug output in browser</LI>
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
