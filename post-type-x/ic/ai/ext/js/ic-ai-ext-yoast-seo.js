(function (window) {
	'use strict';
	window.icAIEditorAdapters = window.icAIEditorAdapters || {};
	window.icAIEditorAdapters.yoast = function (config, value) {
		var editor = config.editor || {}, data = window.wp && window.wp.data, dispatch, payload;
		if (!editor.action || !data || typeof data.dispatch !== 'function') { return false; }
		try { dispatch = data.dispatch(editor.store || 'editor'); } catch (error) { return false; }
		if (!dispatch || typeof dispatch[editor.action] !== 'function') { return false; }
		function replace(node) {
			if (node === '__value__') { return value; }
			if (Array.isArray(node)) { return node.map(replace); }
			if (node && typeof node === 'object') { var copy = {}; Object.keys(node).forEach(function (key) { copy[key] = replace(node[key]); }); return copy; }
			return node;
		}
		payload = editor.payload === undefined ? [value] : replace(editor.payload);
		if (!Array.isArray(payload)) { payload = [payload]; }
		dispatch[editor.action].apply(dispatch, payload);
		return true;
	};
}(window));
