<!-- $Id$ -->

	<xsl:template match="delete">
		<xsl:call-template name="app_header"/>
			<table cellpadding="2" cellspacing="2" align="center">
				<tr>
					<td align="center" colspan="2"><xsl:value-of select="lang_confirm_msg"/></td>
				</tr>
				<tr>
					<td>
						<xsl:variable name="delete_action"><xsl:value-of select="delete_action"/></xsl:variable>
						<xsl:variable name="lang_yes"><xsl:value-of select="lang_yes"/></xsl:variable>
						<form method="POST" action="{$delete_action}">
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
						<xsl:variable name="done_action"><xsl:value-of select="done_action"/></xsl:variable>
						<a href="{$done_action}" onMouseout="window.status='';return true;">
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
