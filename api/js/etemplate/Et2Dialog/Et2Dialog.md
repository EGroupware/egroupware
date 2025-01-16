```html:preview
<et2-dialog title="Dialog" class="dialog-overview" buttons="0">
    This is the dialog
</et2-dialog>
<sl-button>Open Dialog</sl-button>
<script>
    const dialog = document.querySelector(".dialog-overview");
    const button = dialog.nextElementSibling;
    
    // Our dialogs always open on their own, not so good for docs
    dialog.open=false;
    
    button.addEventListener('click', () => dialog.show());
</script>
```

While most widgets are expected to be used via .XET files, Et2Dialog is primarily used via javascript, and usually with
`Et2Dialog.show_dialog()`.
Et2Dialog extends [SlDialog](https://shoelace.style/components/dialog).

```js
// All parameters are optional
const dialog = Et2Dialog.show_dialog(
	/* callback (button, value) => {} or null if you're using the promise*/ null,
	/* Message */           "Would you like to do the thing?",
	/* Title */             "Dialog title",
	/* Value */             {/* Passed to callback */},
	/* Buttons */           Et2Dialog.BUTTONS_OK_CANCEL
);

// Wait for user 
let [button, value] = await dialog.getComplete();
// Do stuff

// or
dialog.getComplete().then(([button, value]) =>
{
	// Do stuff
});
```

In your callback or after the `getComplete()` Promise resolves, you should check which button was pressed.

```js
let callback = function (button_id)
{
	if (button_id == Et2Dialog.YES_BUTTON)
	{
		// Do stuff
	}
	else if (button_id == Et2Dialog.NO_BUTTON)
	{
		// Other stuff
	}
	else if (button_id == Et2Dialog.CANCEL_BUTTON)
	{
		// Abort
	}
};
dialog = Et2Dialog.show_dialog(
	callback, "Erase the entire database?", "Break things", {}, // value
	Et2Dialog.BUTTONS_YES_NO_CANCEL, Et2Dialog.WARNING_MESSAGE
);
```

The parameters for the Et2Dialog.show_dialog() are all optional.

- callback - function called when the dialog closes, or false/null.
  The ID of the button will be passed. Button ID will be one of the Et2Dialog.*_BUTTON constants.
  The callback is _not_ called if the user closes the dialog with the X in the corner, or presses ESC.
- message - (plain) text to display
- title - Dialog title
- value (for prompt)
- buttons - Et2Dialog BUTTONS_* constant, or an array of button settings. Use DialogButton interface.
- dialog_type - Et2Dialog *_MESSAGE constant
- icon - name of icon

Note that these methods will _not_ block program flow while waiting for user input.

## Examples

### Pre-defined dialogs

We have several pre-defined dialogs that can be easily used from javascript for specific purposes.
`Et2Dialog.alert(message, title)`, `Et2Dialog.prompt(message, title)` and `Et2Dialog.confirm(message, title)`

```html:preview
<et2-hbox>
<et2-button class="alert">Alert</et2-button>
<et2-button class="prompt">Prompt</et2-button>
<et2-button class="confirm">Confirm</et2-button>
</et2-hbox>
<script>
    const alertButton = document.querySelector(".alert");
    alertButton.addEventListener("click", () => {
        Et2Dialog.alert("Alert dialog message", "Alert title");
    });
    
    const promptButton = document.querySelector(".prompt");
    promptButton.addEventListener("click", () => {
        Et2Dialog.show_prompt((button, value) => {
            Et2Dialog.alert("Button: " + button+ "  You entered " + value, "Prompt value");
        },
        "Please enter your name", "Prompt dialog"
    );});
    
    const confirmButton = document.querySelector(".confirm");
    confirmButton.addEventListener("click", () => {
        Et2Dialog.confirm(/* senders? */null, "Are you sure you want to delete this?", "Confirm title");
    });
</script>
```

### Template

You can define a dialog inside your template, and use it as needed in your app:

```xml

<template id="dialog_example">
    <!-- The rest of the application template goes here -->
    <!-- destroyOnClose="false" because we intend to re-use the dialog -->
    <et2-dialog id="change_owner" destroyOnClose="false" buttons="1">
        <et2-select-account id="new_owner" label="New owner"></et2-select-account>
        <!-- Anything can go here -->
    </et2-dialog>
</template>
```

```ts
async function changeOwner(entry : { owner : number })
{
	const dialog = document.querySelector("#change_owner");
	dialog.show();

	// Wait for answer
	let [button, value] = await dialog.getComplete();
	if(button)
	{
		entry.owner = value.new_owner;
	}
}
```

Or more commonly, load a template inside the dialog with the `template` attribute:

```xml

<template id="dialog_contents">
    <et2-select-account id="owner" label="Set owner"></et2-select-account>
</template>
```

```ts
async function changeOwner(entry : { owner : number })
{
	// Pass egw in the constructor
	let dialog = new Et2Dialog(this.egw);
	dialog.transformAttributes({
		template: "my_app/templates/default/dialog_contents.xet",
		value: {owner: entry.owner}
	});

	// Add to DOM, dialog will auto-open
	document.body.appendChild(dialog);

	// Wait for answer
	let [button, value] = await dialog.getComplete();
	if(button)
	{
		entry.owner = value.new_owner;
	}
}

```

### Buttons

The easiest way to put buttons on the dialog is to use one of the button constants: `Et2Dialog.BUTTONS_OK`,
`Et2Dialog.BUTTONS_OK_CANCEL`, `Et2Dialog.BUTTONS_YES_NO`, `Et2Dialog.BUTTONS_YES_NO_CANCEL`. This also ensures
consistancy across all dialogs.

```html:preview
<et2-hbox class="button-constants">
<et2-button class="OK">BUTTONS_OK</et2-button>
<et2-button class="OK_CANCEL">BUTTONS_OK_CANCEL</et2-button>
<et2-button class="YES_NO">BUTTONS_YES_NO</et2-button>
<et2-button class="YES_NO_CANCEL">BUTTONS_YES_NO_CANCEL</et2-button>
</et2-hbox>
<script>
    const buttonBox = document.querySelector(".button-constants");
    Array.from(buttonBox.children).forEach(button => {
        button.addEventListener("click", () => {
            Et2Dialog.show_dialog(null, button.textContent.trim() + " = " + Et2Dialog[button.textContent.trim()], "Button constant", null, Et2Dialog[button.textContent.trim()]);
        });
    });
</script>
```

### Custom buttons

Sometimes the pre-defined buttons are insufficient. You can provide your own list of buttons, following the
`DialogButton` interface.

```html:preview
<et2-button class="custom-buttons">Custom buttons</et2-button>
<script>
const button = document.querySelector(".custom-buttons");
const customButtons /* : DialogButton[] */ = [
    // These buttons will use the callback or getComplete() Promise, just like normal.
    {label: "OK", id: "OK", default: true},
    {label: "Yes", id: "Yes"},
    {label: "Sure", id: "Sure", disabled: true},
    {label: "Maybe", click: function() {
            // If you override the click handler, 'this' will be the dialog.
            // Things get more complicated, so doing this is not recommended
        }
    },
    {label: "Negative choice", id:"No", align: "right"}
];
button.addEventListener("click", () => {
    let dialog = Et2Dialog.show_dialog(null, "Custom buttons", "Custom buttons", null, customButtons);
});
</script>
```

```ts
// Pass egw in the constructor
let dialog = new Et2Dialog(my_egw_reference);

// Set attributes.  They can be set in any way, but this is convenient.
dialog.transformAttributes({
	// If you use a template, the second parameter will be the value of the template, as if it were submitted.
	callback: function(button_id, value) {...},	// return false to prevent dialog closing
	buttons: [
		// These ones will use the callback, just like normal.  Use DialogButton interface.
		{label: egw.lang("OK"), id: "OK", default: true},
		{label: egw.lang("Yes"), id: "Yes"},
		{label: egw.lang("Sure"), id: "Sure"},
		{
			label: egw.lang("Maybe"), click: function()
			{
				// If you override, 'this' will be the dialog DOMNode.
				// Things get more complicated.
				// Do what you like here
			}
		},

	],
	title: 'Why would you want to do this?',
	template: "/egroupware/addressbook/templates/default/edit.xet",
	value: {content: {...default values}, sel_options: {...}...}
});
// Add to DOM, dialog will auto-open
document.body.appendChild(dialog);
// If you want, wait for close
let result = await dialog.getComplete();
```
