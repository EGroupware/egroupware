
	<!--A widget is something like an input box, image etc, or a composite/virtual
	widget like a "seperator" or a "label".  These are used throughout the filemanager
	as a cunning way of avoiding putting any HTML in the app
	 
	NB:This means that someone clever could write an XSLT that converted to, say,
	Mozilla XUL, or QT's XML-UI, or a GTK glade interface etc (I dare you!)
	 -->
	<xsl:template match="widget">
		<xsl:variable name="type"><xsl:value-of select="type"/></xsl:variable>
		<xsl:choose>
			<xsl:when test='$type="select"'>
				<select>
					<xsl:attribute name="name"> <xsl:value-of select="name"/> </xsl:attribute>
					<xsl:attribute name="value"> <xsl:value-of select="value"/> </xsl:attribute> <xsl:value-of select="caption"/>
				<xsl:for-each select="options/option" >
					<option>
						<xsl:attribute name="value"><xsl:value-of select="value"/></xsl:attribute>
						<xsl:value-of select="caption"/>
					</option>
				</xsl:for-each>
				</select>
			</xsl:when>
			<xsl:when test='$type="seperator"'>
				<br />
			</xsl:when>
			<xsl:when test='$type="img"'>
				<xsl:variable name="link"><xsl:value-of select="link"/></xsl:variable>
				<xsl:choose> 
					<xsl:when test="link"> 
						<a class="none">
							<xsl:attribute name="href"><xsl:value-of select="link"/></xsl:attribute>
							<xsl:call-template name="img" />
						</a>
					</xsl:when>	
					<xsl:otherwise>
						<xsl:call-template name="img" />
					</xsl:otherwise>
				</xsl:choose>
			</xsl:when>
			<xsl:when test='$type="label"'>
				<xsl:value-of select="caption" />
			</xsl:when>
			<xsl:when test='$type="link"'>
				<a>
					<xsl:attribute name="href"><xsl:value-of select="href"/></xsl:attribute>
					<xsl:value-of select="caption"/>
				</a>
			</xsl:when>
			<xsl:otherwise>
				<input>
					<xsl:attribute name="type"><xsl:value-of select="type"/></xsl:attribute>
					<xsl:attribute name="name"> <xsl:value-of select="name"/></xsl:attribute>
					<xsl:attribute name="value"> <xsl:value-of select="value"/> </xsl:attribute>			
					<xsl:value-of select="caption"/>
				</input>
			</xsl:otherwise>
		</xsl:choose>
		<xsl:apply-templates select="widget" />
	</xsl:template>
	
	<xsl:template name="img">
		<xsl:element name="img">
			<xsl:attribute name="border">0</xsl:attribute>
			<xsl:attribute name="src"><xsl:value-of select="src"/></xsl:attribute>
			<xsl:attribute name="alt"><xsl:value-of select="alt"/></xsl:attribute>
			<xsl:attribute name="valign">center</xsl:attribute>
			<xsl:value-of select="caption" />
		</xsl:element>
	</xsl:template>

	<xsl:template match="form">
		<form>
			<xsl:attribute name="action"> <xsl:value-of select="action"/> </xsl:attribute>
			<xsl:attribute name="method"> <xsl:value-of select="method"/></xsl:attribute>
			<xsl:attribute name="enctype"> <xsl:value-of select="enctype"/></xsl:attribute>
			<table>
				<tr>
			<xsl:for-each select="members">
				<xsl:apply-templates />	
			</xsl:for-each>
				</tr>
			</table>
		</form>
	</xsl:template>
	
	<xsl:template match="table">
		<table>
			<xsl:attribute name="class"><xsl:value-of select="class"/></xsl:attribute>		
			<xsl:apply-templates select="table_head"/>
			<xsl:for-each select="table_row">
					<xsl:choose>
						<xsl:when test="position() mod 2 = 0">
							<xsl:call-template name="table_row">
								<xsl:with-param name="class">row_on</xsl:with-param>
							</xsl:call-template>
						</xsl:when>
						<xsl:otherwise>
							<xsl:call-template name="table_row">
								<xsl:with-param name="class">row_off</xsl:with-param>
							</xsl:call-template>
						</xsl:otherwise>
					</xsl:choose>
			</xsl:for-each>
		</table>
	</xsl:template>	
	<xsl:template match="table_head">
		<tr class="th">
			<xsl:apply-templates select="table_col"/>
		</tr>
	</xsl:template>
	
	<xsl:template name="table_row">
		<xsl:param name="class">tr</xsl:param>
		<tr class="{$class}">
			<xsl:apply-templates select="table_col" />
		</tr>
	</xsl:template>
	
	<xsl:template match="table_col">
		<td>
			<xsl:apply-templates />
		</td>
	</xsl:template>
