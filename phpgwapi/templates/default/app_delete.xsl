<!-- $Id$ -->

	<xsl:template name="app_delete">
		<xsl:apply-templates select="delete"/>
	</xsl:template>

	<xsl:template match="delete">
			<table cellpadding="2" cellspacing="2" align="center">
				<tr>
					<td align="center" colspan="2"><xsl:value-of select="lang_error_msg"/></td>
				</tr>
				<tr>
					<td align="center" colspan="2"><xsl:value-of select="lang_confirm_msg"/></td>
				</tr>
				<xsl:choose>
					<xsl:when test="subs = 'yes'">
						<tr>
							<td align="center" colspan="2">
								<table>
									<tr>
										<td><input type="radio" name="subs" value="move"/></td>
										<td><xsl:value-of select="lang_sub_select_move"/></td>
									</tr>
									<tr>
										<td><input type="radio" name="subs" value="drop"/></td>
										<td><xsl:value-of select="lang_sub_select_drop"/></td>
									</tr>
								</table>
							</td>
						</tr>
					</xsl:when>
				</xsl:choose>
				<tr>
					<td>
						<xsl:variable name="delete_url"><xsl:value-of select="delete_url"/></xsl:variable>
						<xsl:variable name="lang_yes"><xsl:value-of select="lang_yes"/></xsl:variable>
						<form method="POST" action="{$delete_url}">
							<input type="submit" class="forms" name="confirm" value="{$lang_yes}" onMouseout="window.status='';return true;">
								<xsl:attribute name="onMouseover">
									<xsl:text>window.status='</xsl:text>
										<xsl:value-of select="lang_yes_statustext"/>
									<xsl:text>'; return true;</xsl:text>
								</xsl:attribute>
							</input>
						</form>
					</td>
					<td align="right">
						<xsl:variable name="done_url"><xsl:value-of select="done_url"/></xsl:variable>
						<a href="{$done_url}" onMouseout="window.status='';return true;">
							<xsl:attribute name="onMouseover">
								<xsl:text>window.status='</xsl:text>
									<xsl:value-of select="lang_no_statustext"/>
								<xsl:text>'; return true;</xsl:text>
							</xsl:attribute>
							<xsl:value-of select="lang_no"/>
						</a>
					</td>
				</tr>
			</table>
	</xsl:template>
