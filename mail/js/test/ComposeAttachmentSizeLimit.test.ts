import {assert} from "@open-wc/testing";
import {MailCompose} from "../compose";

describe("MailCompose.totalAttachmentSizeMb()", () =>
{
	it("returns 0 for no attachments", () =>
	{
		assert.equal(MailCompose.totalAttachmentSizeMb([]), 0);
	});

	it("sums multiple attachments' sizes, converted from bytes to MB", () =>
	{
		const oneMb = 1024 * 1024;
		const total = MailCompose.totalAttachmentSizeMb([{size: oneMb}, {size: 2 * oneMb}]);
		assert.equal(total, 3);
	});

	it("ignores a VFS-selected entry's size:0 placeholder (vfsUpload() never learns the real size)", () =>
	{
		const oneMb = 1024 * 1024;
		const total = MailCompose.totalAttachmentSizeMb([{size: oneMb}, {size: 0}]);
		assert.equal(total, 1);
	});

	it("treats a missing/undefined size as 0, not NaN", () =>
	{
		const total = MailCompose.totalAttachmentSizeMb([{size: 1024 * 1024}, {}]);
		assert.equal(total, 1);
	});
});
