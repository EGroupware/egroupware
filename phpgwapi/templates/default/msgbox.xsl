<!-- $Id$ -->

	<xsl:template name="msgbox">
		<xsl:apply-templates select="msgbox_data"/>
	</xsl:template>

	<xsl:template match="msgbox_data">
		<table cellpadding="2" cellspacing="0" align="center" class="msgbox">
			<tr>
				<td align="center" valign="middle">
					<xsl:choose>
						<xsl:when test="msgbox_img != ''">
						<xsl:variable name="msgbox_img"><xsl:value-of select="msgbox_img"/></xsl:variable>
						<xsl:variable name="msgbox_img_alt"><xsl:value-of select="msgbox_img_alt"/></xsl:variable>
							<img src="{$msgbox_img}" alt="{$msgbox_img_alt}" title="{$msgbox_img_alt}" onMouseout="window.status='';return true;">
								<xsl:attribute name="onMouseover">
									<xsl:text>window.status='</xsl:text>
										<xsl:value-of select="lang_msgbox_statustext"/>
									<xsl:text>'; return true;</xsl:text>
								</xsl:attribute>
							</img><xsl:text>&nbsp;</xsl:text>
						</xsl:when>
					</xsl:choose>
					<xsl:value-of disable-output-escaping="yes" select="msgbox_text"/>
				</td>
			</tr>
		</table>
	</xsl:template>
