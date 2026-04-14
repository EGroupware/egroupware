// Shim to provide a stable default export for DOMPurify
// Import the UMD build so rollup (which handles commonjs) can bundle it.
import * as DOMPurify from 'dompurify/dist/purify.js';

// Export default to satisfy code that expects a default export in browser ESM tests
export default (DOMPurify as any);
