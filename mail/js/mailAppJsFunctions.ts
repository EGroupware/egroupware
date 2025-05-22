import {loadWebComponent} from "../../api/js/etemplate/Et2Widget/Et2Widget";
import {Et2Dialog} from "../../api/js/etemplate/Et2Dialog/Et2Dialog";
import {egw} from "../../api/js/jsapi/egw_global";

export const setPredefinedAddresses = function (action, _senders) {
    const pref_id = _senders[0].id.split('::')[0] + '_predefined_compose_addresses';
    const prefs = egw.deepExtend({}, egw.preference(pref_id, 'mail'));
    let selOptions = {}
    for (const predefined in prefs) {
        selOptions[predefined] = [];
        for (const predefinedElement of prefs[predefined]) {
            selOptions[predefined].push({label: predefinedElement, value: predefinedElement});
        }
    }
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
            value: {
                content: prefs || {},
                sel_options: selOptions
            },
            minWidth: 410,
            template: egw.webserverUrl + '/mail/templates/default/predefinedAddressesDialog.xet?',
            resizable: false,
        });
    document.body.append(dialog);
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