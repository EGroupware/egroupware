{* Savant2_Compiler_basic : compile.tpl.php *}

{tpl 'header.tpl.php'}

<p>{$this->variable1}</p>
<p>{$this->variable2}</p>
<p>{$this->variable3}</p>
<p>{$this->key0}</p>
<p>{$this->key1}</p>
<p>{$this->key2}</p>
<p>{$this->reference1}</p>
<p>{$this->reference2}</p>
<p>{$this->reference3}</p>

<p>{$this->variable1}</p>

<p>Extended printing: {: $this->variable1 . ' ' . $this->variable2}</p>

<ul>
{foreach ($this->set as $key => $val):}
	<li>{$key} = {$val} ({$this->set[$key]})</li>
{endforeach}
</ul>

{['form', 'start']}
{['form', 'text', 'example', 'default value', 'label:']}
{['form', 'end']}

<p style="clear: both;"><?php echo "PHP Tags" ?>

{tpl 'footer.tpl.php'}