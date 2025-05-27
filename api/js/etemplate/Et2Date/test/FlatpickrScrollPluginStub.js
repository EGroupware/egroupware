// Create a stub for scrollPlugin
import sinon from "sinon";

const scrollPlugin = sinon.stub().returns({
	onReady: sinon.stub(),
	onValueUpdate: sinon.stub(),
	onKeyDown: sinon.stub().callsFake((instance, e) =>
	{
		if (e.key === "ArrowUp" || e.key === "ArrowDown")
		{
			return false;
		}
	}),
	onClose: sinon.stub()
});

export default scrollPlugin;