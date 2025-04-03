`AvatarGroup` takes a `value` which is an array of `{id, label}` where `id` is a [contactId](../et2-avatar/#contact-id)

```html:preview
<et2-avatar-group id="group-example"></et2-avatar-group>
<script>
const group = document.getElementById("group-example");
group.value=[
    {id: "6", label: "Avatar #1"},
    {id: "12", label: "Avatar #2"}, 
    {id: "9", label: "Avatar #3"}
];
</script>
```