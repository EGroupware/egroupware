<!-- $Id$ -->

	<xsl:template name="help">
		<xsl:apply-templates select="help_values"/>
	</xsl:template>

	<xsl:template match="help_values">
		<xsl:variable name="img" select="img"/>
		<table cellpadding="0" cellspacing="0" width="100%">
 			<tr class="th">
  				<td class="th_text" valign="middle">
					<img src="{$img}" border="0"/>&nbsp;
					<xsl:value-of select="title"/>&nbsp;
					<xsl:choose>
						<xsl:when test="version != ''">
							<xsl:value-of select="lang_version"/>:&nbsp;<xsl:value-of select="version"/>
						</xsl:when>
					</xsl:choose>
				</td>
				<td valign="middle" align="right">
					<xsl:choose>
						<xsl:when test="control_link != ''">
							<xsl:apply-templates select="control_link"/>
						</xsl:when>
					</xsl:choose>
				</td>
			</tr>
 			<tr>
  				<td colspan="2">
					<table cellpadding="3" cellspacing="0" class="row_on" width="100%">
						<xsl:choose>
							<xsl:when test="listbox != ''">
								<tr>
									<td>
										<ul>
											<xsl:apply-templates select="listbox"/>
										</ul>
									</td>
								</tr>
							</xsl:when>
						</xsl:choose>
						<xsl:choose>
							<xsl:when test="extrabox != ''">
								<tr>
									<td>
										<xsl:value-of disable-output-escaping="yes" select="extrabox"/>
									</td>
								</tr>
							</xsl:when>
							<xsl:when test="xhelp != ''">
								<tr>
									<td>
										<xsl:call-template name="help_data"/>
									</td>
								</tr>
							</xsl:when>
						</xsl:choose>
   					</table>
  				</td>
 			</tr>
		</table>
	</xsl:template>

	<xsl:template match="control_link">
		<xsl:variable name="param_url"><xsl:value-of select="param_url"/></xsl:variable>
		<xsl:variable name="link_img"><xsl:value-of select="link_img"/></xsl:variable>
		<xsl:variable name="img_width"><xsl:value-of select="img_width"/></xsl:variable>
		<xsl:variable name="lang_param_title"><xsl:value-of select="lang_param_title"/></xsl:variable>
		<a href="{$param_url}" onMouseover="window.status='{$lang_param_title}';return true;" onMouseout="window.status='';return true;">
			<img src="{$link_img}" border="0" width="{img_width}" height="15" alt="{$lang_param_title}" title="{$lang_param_title}"/>
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
