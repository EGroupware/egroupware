	<xsl:template name="app_data">
		<form method="post">
			<xsl:attribute name="action"><xsl:value-of select="form_action"/></xsl:attribute>	
			<xsl:apply-templates select="filemanager_nav" />
			<xsl:apply-templates select="preview" />
			<xsl:apply-templates select="filemanager_edit" />
		</form>
	</xsl:template>
	
	<xsl:template match="filemanager_nav">
		<table class="app_header" width="100%">
			<tr>
				<td class="tr_text" align="left" width="33%" >
								<xsl:apply-templates select="img_up/widget" />
								<xsl:apply-templates select="img_home/widget" />
				</td>
				<td class="app_header" align="center" width="33%">
					<h3>Editing file: 
						<xsl:value-of select="/*/*/*/filename"/>
					</h3>
				</td>
				<td align="right" >
					<table>
						<tr>
							<xsl:for-each select="/*/*/nav_data/*">
								<td>
									<xsl:for-each select="./*">
										<xsl:apply-templates select="." />
										<br />
									</xsl:for-each>
								</td>
							</xsl:for-each>
						</tr>
					</table>
				</td>
			</tr>
		</table>
		<hr />
	</xsl:template>
	
	<xsl:template match="filemanager_edit">
		<xsl:value-of select="output"/>
		<xsl:value-of select="preview"/>
		<br />
		<xsl:apply-templates select="form_data/*" />
		<xsl:apply-templates select="file_content" /> 
	</xsl:template>
	
	<xsl:template match="preview">
		<xsl:copy-of select="."/>
	</xsl:template>
	
	<xsl:template match="file_content">
		<textarea name="edit_file_content" rows="50" cols="80" class="fileeditor"><xsl:value-of select="."/></textarea>
	</xsl:template>
	
	
