<!-- $Id$ -->

	<xsl:template name="portal">
		<xsl:variable name="outer_width"><xsl:value-of select="outer_width"/></xsl:variable>
		<xsl:variable name="header_background_image"><xsl:value-of select="header_background_image"/></xsl:variable>
		<xsl:variable name="inner_width"><xsl:value-of select="inner_width"/></xsl:variable>
		<p>
			<table cellpadding="0" cellspacing="0" width="{$outer_width}" class="portal">
 				<tr nowrap align="center">
  					<td align="center" background="{$header_background_image}">
						<xsl:value-of select="title"/> 
					</td>
					<td valign="middle" align="right" nowrap background="{$header_background_image}">
						<xsl:apply-templates select="control_link"/>
					</td>
				</tr>
 				<tr>
  					<td colspan="2">
   						<table cellpadding="0" cellspacing="0" width="{$inner_width}" class="portal">
							<xsl:apply-templates select="portal_row"/>
   						</table>
  					</td>
 				</tr>
			</table>
		</p>
	</xsl:template>

	<xsl:template match="control_link">
		<xsl:variable name="param_url"><xsl:value-of select="param_url"/></xsl:variable>
		<xsl:variable name="link_img"><xsl:value-of select="link_img"/></xsl:variable>
		<xsl:variable name="img_width"><xsl:value-of select="img_width"/></xsl:variable>
		<xsl:variable name="lang_param"><xsl:value-of select="lang_param"/></xsl:variable>
		<a href="{$param_url}">
			<img src="{$link_img}" border="0" width="{img_width}" height="15" alt="{$lang_param}">
		</a>
	</xsl:template>

	<xsl:template match="portal_row">
		<tr>

		</tr>
	</xsl:template>

		<xsl:variable name="select_action"><xsl:value-of select="select_action"/></xsl:variable>
		<xsl:variable name="lang_submit"><xsl:value-of select="lang_submit"/></xsl:variable>
		<form method="post" action="{$select_action}">
			<select name="cat_id" class="forms" onChange="this.form.submit();" onMouseout="window.status='';return true;">
				<xsl:attribute name="onMouseover">
					<xsl:text>window.status='</xsl:text>
						<xsl:value-of select="lang_cat_statustext"/>
					<xsl:text>'; return true;</xsl:text>
				</xsl:attribute>
				<option value=""><xsl:value-of select="lang_no_cat"/></option>
					<xsl:apply-templates select="cat_list"/>
			</select>
			<noscript>
				<xsl:text> </xsl:text>
				<input type="submit" class="forms" name="submit" value="{$lang_submit}"/> 
			</noscript>
		</form>
	</xsl:template>

	<xsl:template match="cat_list">
	<xsl:variable name="id"><xsl:value-of select="id"/></xsl:variable>
		<xsl:choose>
			<xsl:when test="selected">
				<option value="{$id}" selected="selected"><xsl:value-of select="name"/></option>
			</xsl:when>
			<xsl:otherwise>
				<option value="{$id}"><xsl:value-of select="name"/></option>
			</xsl:otherwise>
		</xsl:choose>
	</xsl:template>


<!-- END portal_box -->

<!-- BEGIN portal_row -->

<!-- END portal_row -->

<!-- BEGIN portal_listbox_header -->
<!--      <td> -->
<!--       <ul> -->
<!-- END portal_listbox_header -->

<!-- BEGIN portal_listbox_link -->

<!-- <li><a href="{link}">{text}</a></li> -->

<!-- END portal_listbox_link -->

<!-- BEGIN portal_listbox_footer -->
      </ul>
     </td>
<!-- END portal_listbox_footer -->

<!-- BEGIN portal_control -->

<!-- END portal_control -->

<!-- BEGIN link_field -->

<!-- END link_field -->
