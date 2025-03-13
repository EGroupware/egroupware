import {loadWebComponent} from "../../api/js/etemplate/Et2Widget/Et2Widget";
import {Et2Dialog} from "../../api/js/etemplate/Et2Dialog/Et2Dialog";
import {egw} from "../../api/js/jsapi/egw_global";

export const setPredefinedAddresses = function (action, _senders) {
    const pref_id = _senders[0].id.split('::')[0] + '_predefined_compose_addresses';
    const prefs = jQuery.extend(true, {}, egw.preference(pref_id, 'mail'));
    // @ts-ignore
    const dialog = loadWebComponent("et2-dialog",
        {
            callback: function (_button_id, _value) {
                switch (_button_id)
                {
                    case Et2Dialog.OK_BUTTON:
                        egw.set_preference('mail', pref_id, _value);
                        return;
                    case "cancel":
                }
            },
            title: this.egw.lang("Predefined addresses for compose"),
            buttons: Et2Dialog.BUTTONS_OK_CANCEL,
            value: {content: prefs || {}},
            minWidth: 410,
            template: egw.webserverUrl + '/mail/templates/default/predefinedAddressesDialog.xet?',
            resizable: false,
        });
    document.body.append(dialog);
}

export const preSetToggledOnActions = function () {
    let actions = egw.preference('toggledOnActions', 'mail');
    const toolbar = this.et2.getWidgetById('composeToolbar');
    if (actions)
    {
        if (typeof actions === 'string')
            actions = actions.split(',');
        // @ts-ignore
        for (var i = 0; i < actions.length; i++)
        {
            if (toolbar && toolbar.options.actions[actions[i]])
            {
                let d = document.getElementById('mail-compose_composeToolbar-' + actions[i]);
                if (d && toolbar._actionManager.getActionById(actions[i]).checkbox
                    && !toolbar._actionManager.getActionById(actions[i]).checked)
                {
                    d.click();
                }
            } else
            {
                var widget = this.et2.getWidgetById(actions[i]);
                if (widget)
                {
                    //	jQuery(widget.getDOMNode()).trigger('click');
                }
            }
        }
    }
}

export const addAttachmentPlaceholder = function () {
    if (this.et2.getArrayMgr("content").getEntry("is_html"))
    {
        // Add link placeholder box
        const email = this.et2.getWidgetById("mail_htmltext");
        const attach_type = this.et2.getWidgetById("filemode");
        const placeholder = '<fieldset class="attachments mceNonEditable"><legend>Download attachments</legend>' + this.egw.lang('Attachments') + '</fieldset>';

        if (email && !email.getValue().includes(placeholder) && attach_type.getValue() !== "attach")
        {
            email.editor.execCommand('mceInsertContent', false, placeholder);
        }
    }
}