<!-- $Id$ -->

	<xsl:template name="help">
		<xsl:apply-templates select="help_data"/>
	</xsl:template>

	<xsl:template match="help_data">
		<table cellpadding="0" cellspacing="0" width="100%">
 			<tr class="th">
  				<td class="th_text">
					<xsl:value-of disable-output-escaping="yes" select="space"/>
					<xsl:value-of select="title"/>
				</td>
				<td valign="middle" align="right">
					<xsl:apply-templates select="control_link"/>
				</td>
			</tr>
 			<tr>
  				<td colspan="2">
					<table cellpadding="3" cellspacing="0" class="portal">
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
							<xsl:when test="xextrabox != ''">
								<tr>
									<td>
										<xsl:call-template name="extrabox"/>
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
