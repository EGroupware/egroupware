## Custom fields widgets

Custom fields (`api/js/etemplate/Et2Customfields/`) let each installation add its own extra fields
to an app's entries without a code change. The widgets here render, edit, and filter on those
per-installation fields - what fields actually exist, and their types, is configuration, not
something fixed at this documentation's build time.

### Widgets in this group

{% for c in components %}
{% if c.belongsTo == "Custom fields" %}
- [{{ c.name | classNameToComponentName }}](/components/{{ c.tagName }}/)
{% endif %}
{% endfor %}
