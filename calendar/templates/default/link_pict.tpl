<!-- $Id$    link_pict -->
<!-- BEGIN link_pict -->
{picture}
<!-- END link_pict -->
<!-- BEGIN link_open -->
<a href="{link_link}" onMouseOver="window.status='{lang_view}'; return true;" onMouseOut="window.status=''; return true;" title="{desc} - {location}"><br>
<!-- END link_open -->
<!-- BEGIN pict -->
 <img src="{pic_image}" width="{width}" height="{height}" title="{title}" border="0" />
<!-- END pict -->
<!-- BEGIN link_text_old -->
<nobr>&nbsp;{time}&nbsp;</nobr> {title}&nbsp;{users_status}: <i>{desc}</i><!--({location})-->
<!-- END link_text_old -->
<!-- BEGIN link_text -->
<nobr>&nbsp;{time}&nbsp;</nobr>{users_status}&nbsp;{title}<!-- : <i>{desc}</i>--><!--({location})-->
<!-- END link_text -->
<!-- BEGIN link_close -->
</a>
<!-- END link_close -->
