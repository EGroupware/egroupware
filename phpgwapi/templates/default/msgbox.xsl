<!-- $Id$ -->

	<xsl:template match="msgbox">
		<xsl:apply-templates select="msgbox_data"/>
	</xsl:template>

	<xsl:template match="msgbox_data">
		<table cellpadding="2" cellspacing="2" align="center" bgcolor="#FFFFFF">
			<tr>
				<td align="center">
					<xsl:variable name="msgbox_img"><xsl:value-of select="msgbox_img"/></xsl:variable>
					<xsl:variable name="msgbox_img_alt"><xsl:value-of select="msgbox_img_alt"/></xsl:variable>
					<img src="{$msgbox_img}" alt="{$msgbox_img_alt}" onMouseout="window.status='';return true;">
						<xsl:attribute name="onMouseover">
							<xsl:text>window.status='</xsl:text>
								<xsl:value-of select="lang_msgbox_statustext"/>
							<xsl:text>'; return true;</xsl:text>
						</xsl:attribute>
					</img>
					<xsl:value-of select="msgbox_text"/>
				</td>
			</tr>
		</table>
	</xsl:template>
