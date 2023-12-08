/**
 * Waits for a specific event to be emitted from an element. Ignores events that bubble up from child elements.
 *
 * Copied from Shoelace
 * /src/internal/event.ts
 */
export function waitForEvent(el : HTMLElement, eventName : string)
{
	return new Promise<void>(resolve =>
	{
		function done(event : Event)
		{
			if(event.target === el)
			{
				el.removeEventListener(eventName, done);
				resolve();
			}
		}

		el.addEventListener(eventName, done);
	});
}
