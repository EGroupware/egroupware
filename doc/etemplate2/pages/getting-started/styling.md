# Styling

Our overall styling is a combination of our site-wide style (pixelegg), etemplate2.css
and [Shoelace](https://shoelace.style/) styles

Some handy excerpts:

## Global CSS variables

```css
:root {
	--primary-background-color: #4177a2;
	--highlight-background-color: rgba(153, 204, 255, .4);

	--label-color: #000000;
	/* For fixed width labels - use class 'et2-label-fixed'*/
	--label-width: 8em;

	--input-border-color: #E6E6E6;
	--input-text-color: #26537C;

	--warning-color: rgba(255, 204, 0, .5);
	--error-color: rgba(204, 0, 51, .5);

}
```

## Useful CSS classes

### hide

Hides the element using css```display: none```

### hideme

Hides the element using css```display: none !important;```

### et2-label-fixed

Use on a widget to force its label to have a fixed width. This helps line up labels and widgets into columns without
having to use a grid, which allows them to reflow if needed. Set the CSS variable ```--label_width``` to change how much
space the labels get.

These widgets are in an et2-vbox:

|                ![fixed label example #1](/assets/images/styling_et2-label-fixed_2.png )                 |
|:-------------------------------------------------------------------------------------------------------:|  
|                                     *Without et2-label-fixed class*                                     |
|                 ![fixed label example #2](/assets/images/styling_et2-label-fixed_1.png)                 |
|                               *Fixed width labels using et2-label-fixed*                                |
|                 ![fixed label example #3](/assets/images/styling_et2-label-fixed_3.png)                 |
| *--label_width CSS variable changed for more space*            <br/>Note how 'Responsible' widget wraps |