<!-- $Id$ -->

	<xsl:template match="portal">
		<xsl:variable name="outer_width"><xsl:value-of select="outer_width"/></xsl:variable>
		<xsl:variable name="header_background_image"><xsl:value-of select="header_background_image"/></xsl:variable>
		<xsl:variable name="inner_width"><xsl:value-of select="inner_width"/></xsl:variable>
			<table cellpadding="0" cellspacing="0" width="{$outer_width}" class="portal">
 				<tr align="center">
  					<td align="center" background="{$header_background_image}" class="portal_text">
						<xsl:value-of select="title"/> 
					</td>
					<td valign="middle" align="right" background="{$header_background_image}">
						<xsl:apply-templates select="control_link"/>
					</td>
				</tr>
 				<tr>
  					<td colspan="2">
   						<table cellpadding="0" cellspacing="0" width="{$inner_width}" class="portal">
							<tr>
								<td>
									<xsl:choose>
										<xsl:when test="listbox">
											<ul>
												<xsl:apply-templates select="listbox"/>
											</ul>
										</xsl:when>
										<xsl:otherwise>
											<xsl:call-template name="extrabox"/>
										</xsl:otherwise>
									</xsl:choose>
								</td>
							</tr>
   						</table>
  					</td>
 				</tr>
			</table>
	</xsl:template>

	<xsl:template match="control_link">
		<xsl:variable name="param_url"><xsl:value-of select="param_url"/></xsl:variable>
		<xsl:variable name="link_img"><xsl:value-of select="link_img"/></xsl:variable>
		<xsl:variable name="img_width"><xsl:value-of select="img_width"/></xsl:variable>
		<xsl:variable name="lang_param_statustext"><xsl:value-of select="lang_param_statustext"/></xsl:variable>
		<a href="{$param_url}" onMouseover="window.status='{$lang_param_statustext}';return true;" onMouseout="window.status='';return true;">
			<img src="{$link_img}" border="0" width="{img_width}" height="15" onMouseover="window.status='{$lang_param_statustext}';return true;" onMouseout="window.status='';return true;" alt="{$lang_param_statustext}"/>
		</a>
	</xsl:template>

	<xsl:template match="listbox">
		<xsl:variable name="link"><xsl:value-of select="link"/></xsl:variable>
		<xsl:variable name="lang_link_statustext"><xsl:value-of select="lang_link_statustext"/></xsl:variable>
			<li>
				<a href="{$link}" onMouseover="window.status='{$lang_link_statustext}';return true;" onMouseout="window.status='';return true;">
					<xsl:value-of select="text"/>
				</a>
			</li>
	</xsl:template>
