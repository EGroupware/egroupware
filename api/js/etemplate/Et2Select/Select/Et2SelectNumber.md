## Examples

A range of numbers, generated in the browser from `min`, `max` and `interval`. Nothing comes from the
server.

Generation stops after 100 options, so a wide range needs a matching `interval` - `min="0"`
`max="1000"` on its own gives you 0 to 100, not 0 to 1000. Where the user should be able to type any
number at all, use [et2-number](/components/et2-number) instead.

### Basic

With nothing set you get a short default range, which is enough when the field is a small count.

```html:preview
<et2-select-number id="number-basic" label="Copies"></et2-select-number>
```

### Range

`min` and `max` are inclusive, and `interval` is the step between them.

```html:preview
<et2-select-number id="number-range" label="Quantity" min="0" max="50" interval="5"></et2-select-number>
<p>Value: <span id="number-range-output">?</span></p>
<script>
    const number = document.getElementById("number-range");
    const numberOutput = document.getElementById("number-range-output");
    const showNumber = () => {numberOutput.textContent = JSON.stringify(number.value);};

    number.addEventListener("change", showNumber);
    customElements.whenDefined("et2-select-number").then(() => number.updateComplete).then(showNumber);
</script>
```

### Leading zeros

`leading_zero` pads the labels with zeros so they all line up. The width comes from the number of
digits in `interval`, not from how many zeros you write, and only the label is padded - the value
stays the bare number.

```html:preview
<et2-select-number id="number-zero" label="Minute" min="0" max="45" interval="15" leading_zero="00"></et2-select-number>
```

### Suffix

`suffix` is appended to every label, and is translated. It is part of the label only - the value
stays the bare number.

```html:preview
<et2-select-number id="number-suffix" label="Remind me" min="5" max="60" interval="5" suffix=" minutes before"></et2-select-number>
```
