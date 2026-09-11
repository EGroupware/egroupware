import {assert} from "@open-wc/testing";
import * as sinon from "sinon";
import "./MailAppImportStub";
// breaks the et2_core_widget <-> Et2Widget import cycle (ClassWithAttributes TDZ) the same
// way the other mail tests do, before compose.ts pulls it in transitively
import "../../../api/js/etemplate/Et2Widget/Et2Widget";
import {MailCompose} from "../compose";

/**
 * MailCompose.setToggle(): compose.xet has its own separate, hidden `<et2-checkbox id="to_infolog">`
 * (and to_tracker/to_calendar/disposition) preceding the composeToolbar - sharing the SAME id as the
 * real, visible toggle the toolbar creates for that action. An unscoped `this.et2.getWidgetById(id)`
 * resolves to that hidden duplicate instead of the real toolbar widget (found live 2026-09-11,
 * alongside the Et2SwitchIcon click-persistence bug - see that fix's own commit/test). setToggle()
 * must update the REAL toolbar widget, scoped the same way integrateSentMessage()/integrateSubmit()
 * already correctly do.
 */
describe('MailCompose.setToggle()', () =>
{
	let compose : MailCompose;
	let hiddenDuplicate : any;
	let realToolbarWidget : any;
	let toolbar : any;

	beforeEach(() =>
	{
		hiddenDuplicate = {set_value: sinon.spy()};
		realToolbarWidget = {set_value: sinon.spy()};
		toolbar = {getWidgetById: (id : string) => id === 'to_infolog' ? realToolbarWidget : undefined};
		const et2 : any = {
			// unscoped lookup would find the HIDDEN duplicate first (document order in compose.xet) -
			// only 'composeToolbar' itself should ever be looked up directly on this.et2
			getWidgetById: (id : string) => id === 'composeToolbar' ? toolbar : id === 'to_infolog' ? hiddenDuplicate : undefined,
		};
		compose = new MailCompose({egw: {}} as any);
		(compose as any).et2 = et2;
	});

	it('updates the real toolbar widget, not the hidden duplicate with the same id', () =>
	{
		compose.setToggle({id: 'to_infolog', checkbox: true, checked: false});

		assert.isTrue(realToolbarWidget.set_value.calledOnceWith('off'));
		assert.isFalse(hiddenDuplicate.set_value.called);
	});

	it('passes "on" when the action is checked', () =>
	{
		compose.setToggle({id: 'to_infolog', checkbox: true, checked: true});

		assert.isTrue(realToolbarWidget.set_value.calledOnceWith('on'));
	});

	it('does nothing for a non-checkbox action', () =>
	{
		compose.setToggle({id: 'to_infolog', checkbox: false, checked: true});

		assert.isFalse(realToolbarWidget.set_value.called);
	});

	it('does nothing when the toolbar has no matching widget', () =>
	{
		compose.setToggle({id: 'unknown', checkbox: true, checked: true});

		assert.isFalse(realToolbarWidget.set_value.called);
		assert.isFalse(hiddenDuplicate.set_value.called);
	});
});
