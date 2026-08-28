## Linking system widgets

The linking system (`api/js/etemplate/Et2Link/`) is EGroupware's cross-app entry-to-entry linking
mechanism - attaching a contact to an infolog entry, a file to a project, and so on. These widgets
cover searching for something to link, displaying existing links, and managing them.

See also [Implementing the linking system](https://github.com/EGroupware/example/tree/step5?tab=readme-ov-file)
under Tutorials for a worked example of wiring this into a new app.

### Widgets in this group

{% for c in components %}
{% if c.belongsTo == "Linking system" %}
- [{{ c.name | classNameToComponentName }}](/components/{{ c.tagName }}/)
{% endif %}
{% endfor %}
