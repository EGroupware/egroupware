
<!-- BEGIN import -->
<CENTER>
  <TABLE WIDTH=90%>
    <TR BGCOLOR="{navbar_bg}">
      <TD><B><FONT SIZE=+2 COLOR="{navbar_text}"><CENTER>{export_text}</CENTER></FONT></B>
      </TD>
    </TR>
    <TR>
      <TD>
        <FORM ENCTYPE="multipart/form-data" action="{action_url}" method="POST">
        <OL>
        <LI>Select the type of conversion:
        <SELECT NAME="conv_type">
        <OPTION VALUE="none">&lt;none&gt;</OPTION>
{conv}        </SELECT><P></LI>
        <LI>{filename}:<INPUT NAME="tsvfilename" VALUE="export.txt"></LI>
        <LI>{lang_cat}:{cat_id}</LI>
        <LI><INPUT NAME="download" TYPE="checkbox" checked>Download export file (Uncheck to debug output in browser)</LI>
        <LI><INPUT NAME="convert" TYPE="submit" VALUE="{download}"></LI>
        </OL>
        </FORM>
      </TD>
    </TR>
    <TR>
      <TD>
        <FORM action="{cancel_url}" method="post">
        <INPUT type="submit" name="Cancel" value="{lang_cancel}">
        </FORM>
      </TD>
    </TR>
  </TABLE>
</CENTER>
<!-- END import -->
