	<xsl:template name="app_data">
		<center>
		<form enctype="multipart/form-data">
			<xsl:attribute name="action"> <xsl:value-of select="index/form_action"/> </xsl:attribute>
			<xsl:attribute name="method">post</xsl:attribute>
			<xsl:apply-templates select="index" />
			<xsl:apply-templates select="files" />
			<xsl:apply-templates select="body_data" />
			<xsl:apply-templates select="buttons" />
			<hr />
			<xsl:apply-templates select="uploads" />
		</form>
		</center>
	</xsl:template>

	<xsl:template match="index">
		<table class="app_header" width="100%">
			<tr>
				<td class="tr_text" align="left" width="33%" >
								<xsl:apply-templates select="img_up/widget" />
								<xsl:value-of select="help_up"/>
								<xsl:apply-templates select="img_home/widget" />
								<xsl:value-of select="help_home"/>
				</td>
				<td class="app_header" align="center" width="33%">
							<h3>	<xsl:apply-templates select="img_dir/widget" />
							<xsl:value-of select="dir"/> </h3>
				</td>
				<td align="right" >
					<xsl:choose>
						<xsl:when test="img_ok">
							<xsl:apply-templates select="img_cancel" />
							<xsl:apply-templates select="button_cancel" />							
							<xsl:apply-templates select="img_ok" />
							<xsl:apply-templates select="button_ok" />
							<xsl:apply-templates select="action" />
							<xsl:apply-templates select="fileman" />
						</xsl:when>
						<xsl:otherwise>
							<xsl:value-of select="/*/*/summary/file_count" /> files <br />
							<xsl:value-of select="/*/*/summary/usage" /> bytes
						</xsl:otherwise>
					</xsl:choose>
				</td>
			</tr>
		</table>
	
		<p><xsl:value-of select="errors" /></p>
		<hr />
	</xsl:template>
	
<!--These templates print out the file list-->
	<xsl:template match="files">
			<table class="table">
				<tr class="th">
				<xsl:for-each select="/*/*/file_attributes/*">
					<td class="th_text">
						<xsl:choose>
							<xsl:when test='name(./*)="widget"'>
								<xsl:apply-templates select="." />
							</xsl:when>
							<xsl:otherwise>
								<xsl:value-of select="." />
							</xsl:otherwise>
						</xsl:choose>
					</td>
				</xsl:for-each>
				</tr>
				<xsl:apply-templates select="file" />     
			</table>
			
	</xsl:template>
	
	<xsl:template match="file">
		  <tr>
		  		<xsl:choose>
					<xsl:when test="position() mod 2 = 0">
						<xsl:attribute name="class">row_on</xsl:attribute>
					</xsl:when>
					<xsl:otherwise>
						<xsl:attribute name="class">row_off</xsl:attribute>
					</xsl:otherwise>
				</xsl:choose>
				<xsl:for-each select="*">
					<td>
						<xsl:choose>
							<xsl:when test='name(./*)="widget" or name(./*/*)="widget"'>
								<xsl:apply-templates select="." />
							</xsl:when>
							<xsl:otherwise>
								<xsl:value-of select="." />
							</xsl:otherwise>
						</xsl:choose>
					</td>
				</xsl:for-each>
			</tr>
	</xsl:template>
	
<!--Prints out the buttons-->
	<xsl:template match="buttons">
		<xsl:for-each select="button">
			<xsl:apply-templates select="widget" />
		</xsl:for-each>
	</xsl:template>

<!--The widgets that handle uploads-->
	<xsl:template match="uploads" >
		<xsl:apply-templates/>
	</xsl:template>
	

