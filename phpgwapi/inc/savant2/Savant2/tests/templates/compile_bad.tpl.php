{* Savant2_Compiler_basic *}

{tpl 'header.tpl.php'}

<p>{$varivari; $this->$varivari}</p>
<p>{$this->variable1; global $_SERVER;}</p>
<p>{$this->variable2; $obj = new StdClass;}</p>
<p>{$this->variable3; eval("echo 'bad guy!';")}</p>
<p>{$this->key0; print_r($this->_compiler);}</p>
<p>{$this->key1; File::read('/etc/passwd');}</p>
<p>{$this->key2; include "/etc/passwd";}</p>
<p>{$this->reference1; include $this->findTemplate('template.tpl.php') . '../../etc/passwd';}</p>
<p>{$this->reference2; $newvar = $this; $newvar =& $this; $newvar	=	&	$this; $newvar
=
&
$this;
$newvar = array(&$this); }</p>

<p>{$this->reference3; $thisIsOk; $thisIs_OK; $function(); }</p>

<p>{$this->variable1; echo parent::findTemplate('template.tpl.php')}</p>

<ul>
{foreach ($this->set as $key => $val): $this->$key; $this->$val(); }
	<li>{$key} = {$val} ({$this->set[$key]})</li>
{endforeach; echo htmlspecialchars(file_get_contents('/etc/httpd/php.ini')); }
</ul>

{['form', 'start']}
{['form', 'text', 'example', 'default value', 'My Text Field:']}
{['form', 'end']}

<p style="clear: both;"><?php echo "PHP Tags" ?>

{tpl 'footer.tpl.php'}