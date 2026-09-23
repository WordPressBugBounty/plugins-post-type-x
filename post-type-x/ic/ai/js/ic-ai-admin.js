(function ($) {
	'use strict';

	function targetKey(value) {
		return String(value || icAIAdmin.targetKey || '');
	}

	function escapeHtml(value) {
		return $('<div>').text(value == null ? '' : String(value)).html();
	}

	function buildSettingsUrl(planSlug, billingMode, usePublicBilling) {
		var url = new URL(icAIAdmin.settingsUrl, window.location.origin);

		if (planSlug) {
			url.searchParams.set('ic_ai_open_checkout', planSlug);
			url.searchParams.set('ic_ai_source', 'editor');
		}
		if (billingMode) {
			url.searchParams.set('ic_ai_open_billing', billingMode);
			url.searchParams.set('ic_ai_source', 'editor');
			if (usePublicBilling) {
				url.searchParams.set('ic_ai_billing_public', '1');
			}
		}

		return url.toString();
	}

	function safeNavigationUrl(value) {
		try {
			var url = new URL(String(value || ''), window.location.origin);

			return (url.protocol === 'http:' || url.protocol === 'https:') ? url.toString() : '';
		} catch (e) {
			return '';
		}
	}

	function loadingWords(includeIntro) {
		var words = Array.isArray(icAIAdmin.loadingWords) && icAIAdmin.loadingWords.length ? icAIAdmin.loadingWords.slice() : ['Drafting', 'Editing', 'Revising', 'Polishing', 'Structuring', 'Refining', 'Clarifying', 'Composing'];

		if (false !== includeIntro) {
			words.unshift(icAIAdmin.messages.loading);
		}

		return words;
	}

	function isValidLoadingPaletteColor(color) {
		return /^#(?:[0-9a-f]{3}|[0-9a-f]{6})$/i.test(String(color == null ? '' : color));
	}

	function loadingPalette() {
		var palette = Array.isArray(icAIAdmin.loadingPalette) ? icAIAdmin.loadingPalette.filter(isValidLoadingPaletteColor) : [];

		if (!palette.length) {
			palette = ['#1e1e1e', '#3858e9', '#7b90ff'];
		}

		return palette;
	}

	function loadingPaletteStops(palette) {
		var weights = [9, 17, 11, 19, 13, 16, 15];
		var stopCount = palette.length > 1 ? palette.length : 2;
		var totalWeight = 0;
		var stops = [{ position: 0, color: palette[0] }];
		var cursor = 0;
		var index;
		var segment;
		var holdStart;

		for (index = 0; index < stopCount; index += 1) {
			totalWeight += weights[index % weights.length];
		}

		for (index = 0; index < stopCount; index += 1) {
			segment = weights[index % weights.length] / totalWeight * 100;
			holdStart = Math.min(99.5, cursor + Math.max(segment * 0.45, 3.5));

			if (index > 0) {
				stops.push({
					position: Math.round(cursor * 1000) / 1000,
					color: palette[index % palette.length]
				});
			}

			stops.push({
				position: Math.round(holdStart * 1000) / 1000,
				color: palette[index % palette.length]
			});

			cursor += segment;
		}

		stops.push({ position: 100, color: palette[0] });

		return stops;
	}

	function ensureLoadingPaletteAnimation() {
		var palette = loadingPalette();
		var styleId = 'ic-ai-loading-palette-animation';
		var animationName = 'ic-ai-loading-palette-shift-' + palette.join('-').replace(/[^a-z0-9]+/ig, '-').replace(/^-+|-+$/g, '').toLowerCase();
		var stops = loadingPaletteStops(palette);
		var styleTag = document.getElementById(styleId);
		var keyframes = [];
		var index;

		for (index = 0; index < stops.length; index += 1) {
			keyframes.push(stops[index].position + '% { color: ' + stops[index].color + '; }');
		}

		if (!styleTag) {
			styleTag = document.createElement('style');
			styleTag.id = styleId;
			document.head.appendChild(styleTag);
		}

		if (styleTag.getAttribute('data-animation-name') !== animationName) {
			styleTag.textContent = '@keyframes ' + animationName + ' { ' + keyframes.join(' ') + ' }';
			styleTag.setAttribute('data-animation-name', animationName);
		}

		return animationName;
	}

	function loadingDotsMarkup() {
		return '<span class="ic-ai-loading-dots" aria-hidden="true">...</span>';
	}

	function normalizedLoadingWord(word) {
		return String(word == null ? '' : word).replace(/\.+\s*$/, '');
	}

	function loadingWordMarkup(word) {
		return '<span class="ic-ai-loading-word">' + escapeHtml(normalizedLoadingWord(word)) + '</span>' + loadingDotsMarkup();
	}

	function shuffleLoadingWords(words) {
		var shuffled = Array.isArray(words) ? words.slice() : [];
		var firstWord = shuffled.shift();
		var index;
		var swapIndex;
		var swapValue;

		for (index = shuffled.length - 1; index > 0; index -= 1) {
			swapIndex = Math.floor(Math.random() * (index + 1));
			swapValue = shuffled[index];
			shuffled[index] = shuffled[swapIndex];
			shuffled[swapIndex] = swapValue;
		}

		if (typeof firstWord !== 'undefined') {
			shuffled.unshift(firstWord);
		}

		return shuffled;
	}

	function formatElapsedTime(seconds) {
		var minutes = Math.floor(seconds / 60);
		var remainder = seconds % 60;

		return minutes + ':' + (remainder < 10 ? '0' : '') + remainder;
	}

	function randomWordDelay() {
		return (3 + Math.floor(Math.random() * 5)) * 1000;
	}

	function scheduleNextWordRotation(state) {
		state.wordTimeoutId = window.setTimeout(state.rotateWord, randomWordDelay());
	}

	function clearLoadingState($results) {
		var state = $results.data('icAiLoadingState');

		if (!state) {
			return;
		}

		if (state.intervalId) {
			window.clearInterval(state.intervalId);
		}
		if (state.wordTimeoutId) {
			window.clearTimeout(state.wordTimeoutId);
		}

		$results.removeData('icAiLoadingState');
	}

	function startLoadingState($results, options) {
		var config = $.extend({
			compact: false
		}, options || {});
		var words = shuffleLoadingWords(loadingWords(!config.compact));
		var stateClassName = config.compact ? ' ic-ai-loading-state-compact' : '';
		var state = {
			seconds: 0,
			wordIndex: 0,
			words: words,
			intervalId: 0,
			wordTimeoutId: 0,
			rotateWord: null
		};

		clearLoadingState($results);
		$results.html([
			'<div class="ic-ai-loading-state' + stateClassName + '">',
			'<p class="ic-ai-loading-wording" aria-hidden="true">', loadingWordMarkup(words[0]), '</p>',
			'<p class="ic-ai-loading-elapsed" aria-hidden="true"><span class="ic-ai-loading-elapsed-value">0:00</span></p>',
			'</div>'
		].join(''));

		state.$wording = $results.find('.ic-ai-loading-wording').first();
		state.$wording.css('animation-name', ensureLoadingPaletteAnimation());
		state.$elapsed = $results.find('.ic-ai-loading-elapsed-value').first();
		state.intervalId = window.setInterval(function () {
			state.seconds += 1;
			state.$elapsed.text(formatElapsedTime(state.seconds));
		}, 1000);
		state.rotateWord = function () {
			state.wordIndex = (state.wordIndex + 1) % state.words.length;
			state.$wording.html(loadingWordMarkup(state.words[state.wordIndex]));
			scheduleNextWordRotation(state);
		};
		scheduleNextWordRotation(state);

		$results.data('icAiLoadingState', state);
	}

	function updateEditorField(selector, value) {
		var $field = $(selector).first();
		var editor;

		if (!$field.length) {
			return;
		}

		$field.val(value).trigger('change');

		if ('content' === $field.attr('id') || 'excerpt' === $field.attr('id')) {
			if (window.tinymce) {
				editor = tinymce.get($field.attr('id'));
				if (editor) {
					editor.setContent(value);
					if ('classic' === icAIAdmin.editorType && typeof editor.setDirty === 'function') {
						editor.setDirty(true);
					}
				}
			}
		}
	}

	/**
	 * Synchronize a WordPress post field with Gutenberg's unsaved editor state.
	 *
	 * The classic editor update remains the fallback when Gutenberg is unavailable
	 * or its editor store rejects the update.
	 *
	 * @param {Object} config Field configuration.
	 * @param {*}      value  Value to apply.
	 * @return {boolean} Whether Gutenberg handled the value.
	 */
	function applyGutenbergPostFieldAdapter(config, value) {
		var fieldMap = {
			post_title: 'title',
			post_excerpt: 'excerpt',
			post_content: 'content'
		};
		var editorField = fieldMap[config.post_field];
		var data = window.wp && window.wp.data;
		var dispatch;
		var update;

		if (!editorField || !data || typeof data.dispatch !== 'function') {
			return false;
		}

		try {
			dispatch = data.dispatch('core/editor');
			if (!dispatch || typeof dispatch.editPost !== 'function') {
				return false;
			}
			update = {};
			update[editorField] = value;
			if ('post_content' === config.post_field) {
				update.blocks = [];
				if (window.wp.blocks && typeof window.wp.blocks.parse === 'function') {
					try {
						update.blocks = window.wp.blocks.parse(String(value));
					} catch (error) {
						update.blocks = [];
					}
				}
			}
			dispatch.editPost(update);
		} catch (error) {
			return false;
		}

		return true;
	}

	var coreBlockRegistrationAttempted = false;

	/**
	 * Ensure the native raw handler has the core block definitions it needs.
	 *
	 * @param {Object} blocks WordPress blocks package.
	 * @return {boolean} Whether the required core block types are registered.
	 */
	function coreBlockRegistryReady(blocks) {
		var requiredTypes = ['core/paragraph', 'core/list', 'core/list-item'];
		var registeredTypes;
		var hasCoreType;

		if (!blocks || typeof blocks.rawHandler !== 'function' || typeof blocks.serialize !== 'function' ||
			typeof blocks.getBlockTypes !== 'function' || typeof blocks.getBlockType !== 'function' ||
			!window.wp.blockLibrary || typeof window.wp.blockLibrary.registerCoreBlocks !== 'function') {
			return false;
		}

		try {
			registeredTypes = blocks.getBlockTypes();
			if (!Array.isArray(registeredTypes)) {
				return false;
			}
			if (requiredTypes.every(function (blockName) { return !!blocks.getBlockType(blockName); })) {
				return true;
			}

			hasCoreType = registeredTypes.some(function (blockType) {
				return blockType && typeof blockType.name === 'string' && blockType.name.indexOf('core/') === 0;
			});
			if (hasCoreType || coreBlockRegistrationAttempted) {
				return false;
			}

			coreBlockRegistrationAttempted = true;
			window.wp.blockLibrary.registerCoreBlocks();

			return requiredTypes.every(function (blockName) { return !!blocks.getBlockType(blockName); });
		} catch (error) {
			return false;
		}
	}

	/**
	 * Convert raw post content through WordPress's native HTML-to-block pipeline.
	 *
	 * @param {Object} config Field configuration.
	 * @param {*}      value  Candidate field value.
	 * @return {*} Serialized block content, or the unchanged value on fallback.
	 */
	function normalizePostContentBlocks(config, value) {
		var blocks = window.wp && window.wp.blocks;
		var html;
		var parsed;
		var serialized;

		if (!config || 'post_field' !== config.type || 'post_content' !== config.post_field) {
			return value;
		}
		if ('classic' === icAIAdmin.editorType) {
			return value;
		}

		html = String(value);
		if (/<!--\s*\/?wp:/i.test(html) || !coreBlockRegistryReady(blocks)) {
			return value;
		}

		try {
			parsed = blocks.rawHandler({ HTML: html });
			if (!Array.isArray(parsed) || !parsed.length) {
				return value;
			}
			serialized = blocks.serialize(parsed);
			return serialized ? serialized : value;
		} catch (error) {
			return value;
		}
	}

	function replaceEditorPayload(payload, value) {
		if ('__value__' === payload) {
			return value;
		}
		if (Array.isArray(payload)) {
			return payload.map(function (item) {
				return replaceEditorPayload(item, value);
			});
		}
		if (payload && typeof payload === 'object') {
			var mapped = {};
			$.each(payload, function (key, item) {
				mapped[key] = replaceEditorPayload(item, value);
			});
			return mapped;
		}
		return payload;
	}

	/**
	 * Runs an extension-provided editor adapter.
	 *
	 * @param {Object} config Field configuration.
	 * @param {*}      value  Value to apply.
	 * @return {boolean} Whether the adapter handled the value.
	 */
	function applyEditorAdapter(config, value) {
		var editor = config.editor || {};
		var adapters = window.icAIEditorAdapters || {};
		var adapter = adapters[editor.adapter];

		return typeof adapter === 'function' ? !!adapter(config, value) : false;
	}

	function updateRepeatedEditorFields(config, values) {
		var normalizedValues = Array.isArray(values) ? values : [values];
		var $inputs = $(config.input_selector);
		var $row = $inputs.first().closest('tr');
		var $addButton = config.repeater_add_selector && $row.length ? $row.find(config.repeater_add_selector).first() : $();

		while ($addButton.length && $inputs.length < normalizedValues.length) {
			$addButton.trigger('click');
			$inputs = $(config.input_selector);
		}

		$inputs.each(function (index) {
			var nextValue = Object.prototype.hasOwnProperty.call(normalizedValues, index) ? normalizedValues[index] : '';

			$(this).val(nextValue).trigger('change');
		});
	}

	function applyEditorValue(config, value) {
		if (Array.isArray(value)) {
			updateRepeatedEditorFields(config, value);
			return;
		}

		if (config.input_selector) {
			updateEditorField(config.input_selector, value);
		}
	}

	function renderFieldPreview(fieldKey, config, value) {
		var preview = typeof value === 'object' ? JSON.stringify(value, null, 2) : String(value);

		return [
			'<div class="ic-ai-preview-field" data-field-key="', escapeHtml(fieldKey), '">',
			'<label><input type="checkbox" class="ic-ai-apply-toggle" checked="checked" /> ',
			escapeHtml(config.label || fieldKey),
			'</label>',
			'<textarea class="widefat ic-ai-preview-value" rows="4" readonly="readonly">', escapeHtml(preview), '</textarea>',
			'</div>'
		].join('');
	}

	function normalizedPreviewPayload(payload) {
		var normalized = {
			fields: {},
			meta: {}
		};

		if (!payload || typeof payload !== 'object') {
			return normalized;
		}
		if (payload.fields && typeof payload.fields === 'object') {
			normalized.fields = payload.fields;
		}
		if (payload.meta && typeof payload.meta === 'object') {
			normalized.meta = payload.meta;
		}

		return normalized;
	}

	function renderPreviewResults($results, payload) {
		var previewPayload = normalizedPreviewPayload(payload);
		var fields = previewPayload.fields;
		var questions = normalizedQuestions(payload);
		var fieldHtml = '';
		var html = questions.length ? '<div class="ic-ai-question-box" aria-live="polite" aria-label="' + escapeHtml(icAIAdmin.messages.questionsButton) + '"></div>' : '';

		$.each(fields, function (fieldKey, value) {
			if (!icAIAdmin.fields[fieldKey]) {
				return;
			}

			fieldHtml += renderFieldPreview(fieldKey, icAIAdmin.fields[fieldKey], value);
		});

		if (!fieldHtml && !questions.length) {
			html = '<p class="description">' + escapeHtml(icAIAdmin.messages.noSuggestions) + '</p>';
		} else {
			html += fieldHtml;
			if (fieldHtml) {
				html += '<p><button type="button" class="button button-secondary ic-ai-apply-selected">' + escapeHtml(icAIAdmin.messages.apply) + '</button></p>';
			}
		}

		$results.data('aiFields', fields).data('aiMeta', previewPayload.meta).data('aiQuestions', questions).html(html);

		if (questions.length) {
			var $questionBox = $results.children('.ic-ai-question-box').first();
			var $reviewCard = $results.closest('.ic-ai-review-card');
			var answers = payload && payload.qa_answers && typeof payload.qa_answers === 'object' ? payload.qa_answers : {};

			renderQuestionBox($questionBox, questions, {
				answers: answers,
				formData: $('#post').length ? $('#post').serialize() : ($('#edittag').length ? $('#edittag').serialize() : ''),
				postId: $reviewCard.length ? Number($reviewCard.data('post-id') || 0) : icAIAdmin.postId,
				context: $reviewCard.length ? 'review' : '',
				$results: $results
			});
		}
	}

	function normalizedQuestions(payload) {
		if (!payload || typeof payload !== 'object' || !payload.questions || !payload.questions.length) {
			return [];
		}

		var questions = [];
		$.each(payload.questions, function (index, entry) {
			if (!entry || typeof entry !== 'object' || !entry.question) {
				return;
			}
			questions.push({
				id: entry.id ? String(entry.id) : 'q' + (questions.length + 1),
				question: String(entry.question),
				suggestions: entry.suggestions && entry.suggestions.length ? entry.suggestions.slice(0, 2) : []
			});
		});

		return questions;
	}

	function renderQuestionBox($container, questions, opts) {
		if (!$container || !$container.length || !questions || !questions.length) {
			return;
		}

		var options = opts || {};
		var state = {
			questions: questions,
			answers: options.answers && typeof options.answers === 'object' ? options.answers : {},
			index: 0,
			formData: options.formData || '',
			context: options.context || '',
			postId: options.postId || icAIAdmin.postId || 0,
			$results: options.$results && options.$results.length ? options.$results : null
		};

		$container.data('icAiQuestionState', state);
		renderCurrentQuestion($container);
	}

	function reviewQuestionsSaveLocally(state) {
		var review = reviewScreen();
		return !!state && 'review' === (state.context || '') && !!review && !review.singleMode;
	}

	function renderCurrentQuestion($container) {
		var state = $container.data('icAiQuestionState');
		if (!state) {
			return;
		}

		var question = state.questions[state.index];
		if (!question) {
			return;
		}

		var progress = icAIAdmin.messages.questionProgress
			.replace('%1$d', state.index + 1)
			.replace('%2$d', state.questions.length);
		var savedAnswer = state.answers[question.id] ? String(state.answers[question.id]) : '';
		var html = '<div class="ic-ai-question">';
		html += '<p class="ic-ai-question-progress description">' + escapeHtml(progress) + '</p>';
		html += '<p class="ic-ai-question-text"><strong>' + escapeHtml(question.question) + '</strong></p>';

		if (question.suggestions && question.suggestions.length) {
			html += '<p class="ic-ai-question-suggestions">';
			$.each(question.suggestions, function (index, suggestion) {
				html += '<button type="button" class="button ic-ai-question-suggestion">' + escapeHtml(String(suggestion)) + '</button> ';
			});
			html += '</p>';
		}

		html += '<p><label class="ic-ai-question-custom-label">' + escapeHtml(icAIAdmin.messages.questionCustomLabel);
		html += '<textarea class="ic-ai-question-custom" rows="2">' + escapeHtml(savedAnswer) + '</textarea></label></p>';
		var isFinal = state.index >= state.questions.length - 1;
		var submitLabel = icAIAdmin.messages.questionSave;

		html += '<p class="ic-ai-question-actions">';
		html += '<button type="button" class="button button-primary ic-ai-question-next">' + escapeHtml( submitLabel ) + '</button> ';
		html += '<button type="button" class="button ic-ai-question-skip">' + escapeHtml(icAIAdmin.messages.questionSkip) + '</button>';
		html += '</p></div>';

		$container.html(html);
	}

	function advanceQuestion($container, answerText) {
		var state = $container.data('icAiQuestionState');
		if (!state) {
			return;
		}

		var question = state.questions[state.index];
		if (question) {
			if (answerText) {
				state.answers[question.id] = answerText;
			} else {
				// Skipping or clearing a question must retract any previously stored
				// answer (reopened-after-fail or qa_answers-prefilled review flows),
				// otherwise submitQuestionAnswers() would resend the stale value.
				delete state.answers[question.id];
			}
		}
		state.index += 1;

		if (state.index >= state.questions.length) {
			finishQuestionFlow( $container, state );
			return;
		}

		renderCurrentQuestion($container);
	}

	// Builds the non-interactive AI Credit usage indicator from a complete,
	// server-localized label; plural forms are never assembled here.
	function creditUsageContentHtml(text) {
		return (icAIAdmin.creditIcon ? String(icAIAdmin.creditIcon) : '') + escapeHtml(String(text));
	}

	function creditUsageHtml(text) {
		if (!text) {
			return '';
		}

		return '<span class="ic-ai-credit-usage">' + creditUsageContentHtml(text) + '</span>';
	}

	function renderQuestionConfirmation($container, state) {
		var answers = Object.keys(state.answers || {}).length;
		var refineLabel = icAIAdmin.messages.questionSubmit;
		var disabled = answers ? '' : ' disabled="disabled"';
		var usage = creditUsageHtml(icAIAdmin.messages.questionSubmitUsage);
		$container.html('<div class="ic-ai-question-confirmation"><p class="description">' + escapeHtml(answers ? icAIAdmin.messages.answersReady : icAIAdmin.messages.error) + '</p><p class="ic-ai-question-actions"><button type="button" class="button button-primary ic-ai-question-refine-confirm"' + disabled + '>' + escapeHtml(refineLabel) + '</button> ' + (usage ? usage + ' ' : '') + '<button type="button" class="button ic-ai-question-reopen">' + escapeHtml(icAIAdmin.messages.questionsReopen) + '</button></p></div>');
	}

	function finishQuestionFlow($container, state) {
		if ( reviewQuestionsSaveLocally( state ) ) {
			submitQuestionAnswers($container);
			return;
		}
		renderQuestionConfirmation($container, state);
	}

	// Renders an in-box reopen affordance so skip-all or a submit failure never
	// leaves the automatically opened questionnaire at a dead end.
	function renderQuestionsReopen($container, state) {
		if (!state) {
			return;
		}

		$container.html('<p class="ic-ai-question-actions"><button type="button" class="button ic-ai-question-reopen">' + escapeHtml(icAIAdmin.messages.questionsReopen) + '</button></p>');
	}

	function setQuestionRefineBusy($container, state, busy) {
		var $scope;

		if (busy) {
			if (state.refineButtons) {
				return;
			}

			$scope = $container.closest('.ic-ai-metabox, .ic-ai-review-card').first();
			if (!$scope.length) {
				$scope = $container;
			}

			state.refineButtons = [];
			$scope.find(':button').each(function () {
				state.refineButtons.push({
					element: this,
					disabled: !!this.disabled
				});
				$(this).prop('disabled', true);
			});
			return;
		}

		if (!state.refineButtons) {
			return;
		}

		$.each(state.refineButtons, function (_, buttonState) {
			$(buttonState.element).prop('disabled', buttonState.disabled);
		});
		delete state.refineButtons;
	}

	function submitQuestionAnswers($container, jobId) {
		var state = $container.data('icAiQuestionState');
		if (!state) {
			return;
		}

		var answers = [];
		$.each(state.questions, function (index, question) {
			if (state.answers[question.id]) {
				answers.push({
					id: question.id,
					question: question.question,
					answer: String(state.answers[question.id])
				});
			}
		});

		if (!answers.length) {
			renderQuestionsReopen($container, state);
			return;
		}

		setQuestionRefineBusy($container, state, true);
		startLoadingState($container);
		$container.append('<p class="screen-reader-text ic-ai-question-loading-status">' + escapeHtml(icAIAdmin.messages.loading) + '</p>');

		// Edit screens: re-serialize #post FRESH at submit time (also on each queued
		// retry, which re-enters this function) so title/content/custom-field edits made
		// while the question box was open are refined against, instead of the stale
		// snapshot captured when the box first opened. Review context keeps its own
		// review_fields snapshot mechanism below (do not re-serialize #post there).
		var formData = state.formData;
		if ('review' !== (state.context || '') && ( $('#post').length || $('#edittag').length )) {
			if (window.tinymce) {
				tinymce.triggerSave();
			}
			formData = $('#post').length ? $('#post').serialize() : $('#edittag').serialize();
		}

		var saveOnly = reviewQuestionsSaveLocally( state );
		var postData = {
			action: saveOnly ? 'ic_ai_save_review_answers' : 'ic_ai_submit_answers',
			nonce: icAIAdmin.nonce,
			target_key: targetKey(),
			object_id: state.postId,
			form_data: formData,
			context: state.context || '',
			job_id: jobId || '',
			answers: answers
		};
		if ('review' !== (state.context || '') && ('classic' === icAIAdmin.editorType || 'block' === icAIAdmin.editorType)) {
			postData.editor_type = icAIAdmin.editorType;
		}
		if ('review' === (state.context || '')) {
			postData.review_context = 1;
		}

		// On review screens submit the CURRENT review draft field values (saved draft
		// plus any unsaved edits) as the payload base so the refine does not drop the
		// reviewer's in-progress changes. Cached on the state so queued-retry/reopen
		// re-submits carry the same snapshot taken when the flow was completed.
		if ('review' === (state.context || '')) {
			if ( saveOnly || ! state.reviewFields ) {
				state.reviewFields = collectReviewDraftFields($container);
			}
			if (state.reviewFields) {
				postData.review_fields = state.reviewFields;
			}
		}

		$.post(icAIAdmin.ajaxUrl, postData).done(function (response) {
			clearLoadingState($container);

			if (!response || !response.success || !response.data) {
				renderAnswerSubmitError($container, state, null);
				return;
			}
			if (saveOnly) {
				$container.html( '<p class="description">' + escapeHtml( icAIAdmin.messages.answersSaved ) + '</p>' );
				setQuestionRefineBusy( $container, state, false );
				window.location = response.data.next_url;
				return;
			}

			var $results = state.$results;
			if (!$results || !$results.length) {
				$results = $container.closest('.ic-ai-review-card, .ic-ai-metabox').find('.ic-ai-preview-results').first();
			}
			if ($results && $results.length) {
				renderPreviewResults($results, response.data);
				$results.prepend('<div class="notice notice-success inline ic-ai-question-feedback"><p>' + escapeHtml(icAIAdmin.messages.questionsDone) + '</p></div>');
				setQuestionRefineBusy($container, state, false);
				return;
			}

			// Review screen renders its refined draft server-side; reload to repaint it.
			$container.html('<p class="description">' + escapeHtml(icAIAdmin.messages.questionsDone) + '</p>');
			setQuestionRefineBusy($container, state, false);
			window.location.reload();
		}).fail(function (xhr) {
			var data = xhr && xhr.responseJSON && xhr.responseJSON.data ? xhr.responseJSON.data : null;
			renderAnswerSubmitError($container, state, data);
		}).always(function () {
			clearLoadingState($container);
		});
	}

	// Renders the same structured recovery UI the preview/regenerate flows use for
	// 409/429 payloads (queueable retry, upgrade/buy, license flow, plans) instead
	// of a dead-end generic error, and keeps the clarifying-questions CTA reopenable.
	function renderAnswerSubmitError($container, state, data) {
		var message = icAIAdmin.messages.error;

		clearLoadingState($container);
		if (data && data.code === 'ic_ai_review_locked' && data.list_url) {
			window.location = data.list_url;
			return;
		}

		// Accepted-but-pending refine: drive it through the same queued poll/retry helper the
		// single-preview and review-regenerate flows use, re-posting the answers with the job_id.
		if (data && data.queueable && data.job_id) {
			renderSinglePreviewQueuedRetry($container, $container, data, function (nextJobId) {
				submitQuestionAnswers($container, nextJobId);
			});
			return;
		}

		if (data && data.queueable) {
			$container.html(renderQueueableFeedback(normalizeSingleItemQueueablePayload(data, message), message));
		} else if (data && (data.license_flow || data.upgrade_url || data.buy_enhancements_url || data.plans)) {
			$container.html(renderRecoveryActions(data));
		} else {
			if (data && data.message) {
				message = data.message;
			}
			$container.html('<p class="notice notice-error inline">' + escapeHtml(message) + '</p>');
		}

		setQuestionRefineBusy($container, state, false);
		$container.append('<p class="ic-ai-question-actions"><button type="button" class="button ic-ai-question-reopen">' + escapeHtml(icAIAdmin.messages.questionsReopen) + '</button></p>');
	}

	function clearQueuedRetry($box, enableButton) {
		var state = $box.data('icAiQueuedRetry');
		var shouldEnable = enableButton !== false;

		if (state) {
			if (state.timeoutId) {
				window.clearTimeout(state.timeoutId);
			}
			if (state.intervalId) {
				window.clearInterval(state.intervalId);
			}

			$box.removeData('icAiQueuedRetry');
		}

		if (shouldEnable) {
			$box.find('.ic-ai-preview-button').prop('disabled', false);
		}
	}

	function clearSinglePreviewRetry($container) {
		var state = $container.data('icAiSinglePreviewRetry');

		if (state) {
			if (state.timeoutId) {
				window.clearTimeout(state.timeoutId);
			}
			if (state.intervalId) {
				window.clearInterval(state.intervalId);
			}

			$container.removeData('icAiSinglePreviewRetry');
		}
	}

	function queueReasonMessage(payload) {
		if (!payload || typeof payload !== 'object') {
			return '';
		}
		if (payload.limit_kind === 'window_rate') {
			return icAIAdmin.messages.queueWindow;
		}
		if (payload.limit_scope === 'global') {
			return icAIAdmin.messages.queueGlobal;
		}

		return icAIAdmin.messages.queueClient;
	}

	function queueableUpgradeAction(payload) {
		if (!payload || typeof payload !== 'object' || payload.limit_kind !== 'window_rate' || !payload.upgrade_url) {
			return null;
		}

		return {
			url: String(payload.upgrade_url),
			label: payload.upgrade_label ? String(payload.upgrade_label) : icAIAdmin.messages.upgradePlan
		};
	}

	function renderQueuedRetry($box, $results, payload, formData) {
		var delayMs = Math.max(1000, Number(payload && payload.retry_after_ms ? payload.retry_after_ms : 0) || (Math.max(1, Number(payload && payload.retry_after ? payload.retry_after : 1)) * 1000));
		var state = {
			remainingMs: delayMs,
			formData: formData,
			jobId: payload && payload.job_id ? String(payload.job_id) : '',
			timeoutId: 0,
			intervalId: 0
		};
		var upgradeAction = queueableUpgradeAction(payload);
		var retryMessage = queueReasonMessage(payload);

		clearQueuedRetry($box, false);

		function render() {
			var seconds = Math.max(1, Math.ceil(state.remainingMs / 1000));
			var html = '<div class="notice notice-warning inline ic-ai-preview-queued">';

			html += '<p><strong>' + escapeHtml(icAIAdmin.messages.queueTitle) + '</strong></p>';
			html += '<p>' + escapeHtml(payload && payload.message ? payload.message : icAIAdmin.messages.error) + '</p>';
			if (retryMessage) {
				html += '<p class="description">' + escapeHtml(retryMessage) + '</p>';
			}
			html += '<p class="description">' + escapeHtml(icAIAdmin.messages.queueRetryingIn) + ' <strong>' + escapeHtml(seconds) + 's</strong>.</p>';
			if (upgradeAction) {
				html += '<p><a class="button button-secondary" href="' + escapeHtml(upgradeAction.url) + '">' + escapeHtml(upgradeAction.label) + '</a></p>';
			}
			html += '<p><button type="button" class="button button-secondary ic-ai-preview-retry-now">' + escapeHtml(icAIAdmin.messages.queueRetryNow) + '</button></p>';
			html += '</div>';

			$results.html(html);
		}

		state.timeoutId = window.setTimeout(function () {
			clearQueuedRetry($box, false);
			submitPreviewRequest($box, state.formData, state.jobId);
		}, delayMs);
		state.intervalId = window.setInterval(function () {
			state.remainingMs = Math.max(0, state.remainingMs - 1000);
			render();
		}, 1000);

		$box.data('icAiQueuedRetry', state);
		render();
	}

	function renderRecoveryActions(payload) {
		var html = '<div class="ic-ai-onboarding-inline">';
		var plans = payload && payload.plans ? payload.plans : [];
		var settingsUrl = payload && payload.settings_url ? payload.settings_url : icAIAdmin.settingsUrl;
		var actionUrl = payload && payload.action_settings_url ? payload.action_settings_url : '';
		var actionLabel = payload && payload.action_label ? payload.action_label : icAIAdmin.messages.upgrade;
		var upgradeUrl = payload && payload.upgrade_url ? payload.upgrade_url : '';
		var buyEnhancementsUrl = payload && payload.buy_enhancements_url ? payload.buy_enhancements_url : '';
		var upgradeLabel = payload && payload.upgrade_label ? payload.upgrade_label : icAIAdmin.messages.upgradePlan;
		var buyEnhancementsLabel = payload && payload.buy_enhancements_label ? payload.buy_enhancements_label : icAIAdmin.messages.buyEnhancements;

		html += '<p class="description">' + escapeHtml(payload && payload.message ? payload.message : icAIAdmin.messages.error) + '</p>';
		if (payload && payload.recovery_type === 'enable_ai') {
			html += '<p><a class="button button-primary" href="' + escapeHtml(settingsUrl) + '">' + escapeHtml(icAIAdmin.messages.openSettings) + '</a></p>';
			html += '</div>';

			return html;
		}
		if (payload && payload.recovery_type === 'license_key') {
			if (actionUrl) {
				html += '<p><a class="button button-secondary" href="' + escapeHtml(actionUrl) + '">' + escapeHtml(actionLabel) + '</a></p>';
			}
			html += '<p><a class="button-link" href="' + escapeHtml(settingsUrl) + '">' + escapeHtml(icAIAdmin.messages.openSettings) + '</a></p>';
			html += '</div>';

			return html;
		}
		if (upgradeUrl || buyEnhancementsUrl) {
			if (upgradeUrl) {
				html += '<p><a class="button button-secondary" href="' + escapeHtml(upgradeUrl) + '">' + escapeHtml(upgradeLabel) + '</a></p>';
			}
			if (buyEnhancementsUrl) {
				html += '<p><a class="button button-secondary" href="' + escapeHtml(buyEnhancementsUrl) + '">' + escapeHtml(buyEnhancementsLabel) + '</a></p>';
			}
			html += '<p><a class="button-link" href="' + escapeHtml(settingsUrl) + '">' + escapeHtml(icAIAdmin.messages.openSettings) + '</a></p>';
			html += '</div>';

			return html;
		}
		if (plans.length) {
			html += '<div class="ic-ai-inline-plans">';
			$.each(plans, function (_, plan) {
				if (!plan || !plan.slug) {
					return;
				}

				html += '<div class="ic-ai-inline-plan">';
				html += '<strong>' + escapeHtml(plan.name || plan.slug) + '</strong>';
				if (plan.price != null) {
					html += '<div>' + escapeHtml('$' + Number(plan.price).toFixed(2)) + '</div>';
				}
				html += '<p><a class="button button-primary" href="' + escapeHtml(buildSettingsUrl(plan.slug)) + '">' + escapeHtml(plan.name || plan.slug) + '</a></p>';
				html += '</div>';
			});
			html += '</div>';
		}
		html += '<p><a class="button button-secondary" href="' + escapeHtml(settingsUrl) + '">' + escapeHtml(icAIAdmin.messages.haveKey) + '</a></p>';
		html += '<p><a class="button-link" href="' + escapeHtml(settingsUrl) + '">' + escapeHtml(icAIAdmin.messages.openSettings) + '</a></p>';
		html += '</div>';

		return html;
	}

	function renderQueueableFeedback(payload, fallbackMessage) {
		var upgradeAction = queueableUpgradeAction(payload);
		var message = payload && payload.message ? payload.message : fallbackMessage;

		if (upgradeAction) {
			return renderRecoveryActions(payload);
		}

		return '<p class="notice notice-warning inline">' + escapeHtml(message) + '</p>';
	}

	function normalizeSingleItemQueueablePayload(payload, fallbackMessage) {
		var normalized;
		var message;

		if (!payload || typeof payload !== 'object') {
			return payload;
		}

		normalized = $.extend({}, payload);
		message = normalized.message ? String(normalized.message) : String(fallbackMessage || '');
		normalized.message = message.replace(/\s*The request will retry automatically\.?\s*$/i, '').trim();

		return normalized;
	}

	function renderSinglePreviewQueuedRetry($container, $results, payload, retryCallback) {
		var delayMs = Math.max(1000, Number(payload && payload.retry_after_ms ? payload.retry_after_ms : 0) || (Math.max(1, Number(payload && payload.retry_after ? payload.retry_after : 1)) * 1000));
		var state = {
			remainingMs: delayMs,
			jobId: payload && payload.job_id ? String(payload.job_id) : '',
			retryCallback: retryCallback,
			timeoutId: 0,
			intervalId: 0
		};
		var upgradeAction = queueableUpgradeAction(payload);
		var retryMessage = queueReasonMessage(payload);

		clearSinglePreviewRetry($container);

		function render() {
			var seconds = Math.max(1, Math.ceil(state.remainingMs / 1000));
			var html = '<div class="notice notice-warning inline ic-ai-preview-queued">';

			html += '<p><strong>' + escapeHtml(icAIAdmin.messages.queueTitle) + '</strong></p>';
			html += '<p>' + escapeHtml(payload && payload.message ? payload.message : icAIAdmin.messages.error) + '</p>';
			if (retryMessage) {
				html += '<p class="description">' + escapeHtml(retryMessage) + '</p>';
			}
			html += '<p class="description">' + escapeHtml(icAIAdmin.messages.queueRetryingIn) + ' <strong>' + escapeHtml(seconds) + 's</strong>.</p>';
			if (upgradeAction) {
				html += '<p><a class="button button-secondary" href="' + escapeHtml(upgradeAction.url) + '">' + escapeHtml(upgradeAction.label) + '</a></p>';
			}
			html += '<p><button type="button" class="button button-secondary ic-ai-single-preview-retry-now">' + escapeHtml(icAIAdmin.messages.queueRetryNow) + '</button></p>';
			html += '</div>';

			$results.html(html);
		}

		state.timeoutId = window.setTimeout(function () {
			clearSinglePreviewRetry($container);
			state.retryCallback(state.jobId);
		}, delayMs);
		state.intervalId = window.setInterval(function () {
			state.remainingMs = Math.max(0, state.remainingMs - 1000);
			render();
		}, 1000);

		$container.data('icAiSinglePreviewRetry', state);
		render();
	}

	function applyTaxonomy(fieldKey, config, value) {
		return $.post(icAIAdmin.ajaxUrl, {
			action: 'ic_ai_apply_taxonomy_terms',
			nonce: icAIAdmin.nonce,
			target_key: targetKey(),
			object_id: icAIAdmin.postId,
			field_key: fieldKey,
			paths: value,
			review_context: reviewScreen() ? 1 : 0
		}).done(function (response) {
			if (!response || !response.success || !response.data || !response.data.terms) {
				return;
			}

			$.each(response.data.terms, function (_, term) {
				var checkboxId = '#in-' + config.taxonomy + '-' + term.term_id;
				var $checkbox = $(checkboxId);
				var listSelector = '#' + config.taxonomy + 'checklist, #' + config.taxonomy + 'checklist-pop';

				if ($checkbox.length) {
					$checkbox.prop('checked', true).trigger('change');
					return;
				}

				var $list = $(listSelector).first();

				if ($list.length) {
					$list.append(
						'<li><label class="selectit"><input type="checkbox" checked="checked" id="in-' +
							escapeHtml(config.taxonomy) + '-' + escapeHtml(term.term_id) +
							'" name="tax_input[' + escapeHtml(config.taxonomy) + '][]" value="' +
							escapeHtml(term.term_id) + '" /> ' + escapeHtml(term.name) + '</label></li>'
					);
				}
			});
		});
	}

	function applyMeta(fieldKey, config, value) {
		var handled = applyEditorAdapter(config, value);
		if (!handled) {
			applyEditorValue(config, value);
		}
		if (window.CustomEvent && document.dispatchEvent) {
			document.dispatchEvent(new CustomEvent('ic_ai_custom_meta_applied', {
				detail: { fieldKey: fieldKey, value: value }
			}));
		}

		return $.Deferred().resolve();
	}

	function applyField(fieldKey, config, value) {
		var requests = [];

		if ('taxonomy' === config.type) {
			return applyTaxonomy(fieldKey, config, value);
		}

		if (config.editor && config.editor.adapter || 'custom_meta' === config.type) {
			return applyMeta(fieldKey, config, value);
		}

		if ('group' === config.type && config.group_fields) {
			$.each(config.group_fields, function (groupKey, groupField) {
				if (value && Object.prototype.hasOwnProperty.call(value, groupKey)) {
					requests.push(applyField(groupKey, groupField, value[groupKey]));
				}
			});

			return requests.length ? $.when.apply($, requests) : $.Deferred().resolve();
		}

		if ('post_field' === config.type) {
			value = normalizePostContentBlocks(config, value);
			applyGutenbergPostFieldAdapter(config, value);
			applyEditorValue(config, value);
			return $.Deferred().resolve();
		}

		applyEditorValue(config, value);

		return $.Deferred().resolve();
	}

	function submitPreviewRequest($box, formData, jobId) {
		var $button = $box.find('.ic-ai-preview-button');
		var $results = $box.find('.ic-ai-preview-results');
		var requestData = {
			action: 'ic_ai_preview_post',
			nonce: icAIAdmin.nonce,
			target_key: targetKey(),
			object_id: icAIAdmin.postId,
			form_data: formData
		};
		if ('classic' === icAIAdmin.editorType || 'block' === icAIAdmin.editorType) {
			requestData.editor_type = icAIAdmin.editorType;
		}

		clearQueuedRetry($box, false);
		$button.prop('disabled', true);
		startLoadingState($results);
		if (jobId) {
			requestData.job_id = jobId;
		}

		$.post(icAIAdmin.ajaxUrl, requestData).done(function (response) {
			var payload = response && response.success && response.data ? response.data : null;

			clearLoadingState($results);

			if (!payload || typeof payload !== 'object') {
				$results.html('<p class="notice notice-error inline">' + escapeHtml(icAIAdmin.messages.error) + '</p>');
				return;
			}

			renderPreviewResults($results, payload);
		}).fail(function (xhr) {
			var message = icAIAdmin.messages.error;
			var data = xhr && xhr.responseJSON && xhr.responseJSON.data ? xhr.responseJSON.data : null;

			clearLoadingState($results);

			if (data && data.queueable) {
				renderQueuedRetry($box, $results, data, formData);
				return;
			}

			if (data && (data.license_flow || data.upgrade_url || data.buy_enhancements_url || data.plans)) {
				$results.html(renderRecoveryActions(data));
				return;
			}

			if (data && (data.code === 'ic_ai_field_too_large' || data.code === 'ic_ai_payload_too_large')) {
				$results.html('<p class="notice notice-error inline ic-ai-oversize-error">' + escapeHtml(data.message || icAIAdmin.messages.error) + '</p>');
				return;
			}

			if (data && data.message) {
				message = data.message;
			}

			$results.html('<p class="notice notice-error inline">' + escapeHtml(message) + '</p>');
		}).always(function () {
			clearLoadingState($results);
			if (!$box.data('icAiQueuedRetry')) {
				$button.prop('disabled', false);
			}
		});
	}

	function listScreen() {
		return icAIAdmin.listScreen && typeof icAIAdmin.listScreen === 'object' ? icAIAdmin.listScreen : null;
	}

	function reviewScreen() {
		return icAIAdmin.reviewScreen && typeof icAIAdmin.reviewScreen === 'object' ? icAIAdmin.reviewScreen : null;
	}

	function listActionsContainer() {
		var $actions = $( '.ic-ai-list-actions' ).first();

		return $actions.length ? $actions : $( '.ic-ai-refine-actions' ).first();
	}

	function listPanelContainer() {
		return $('.ic-ai-list-panel').first();
	}

	function relocateListPanel() {
		var $panel = listPanelContainer();
		var $table = $('#posts-filter .wp-list-table').first();

		if (!$panel.length) {
			return;
		}
		if (!$table.length) {
			$table = $('form .wp-list-table').first();
		}
		if (!$table.length || $panel.next()[0] === $table[0]) {
			return;
		}

		$panel.insertBefore($table);
	}

	function selectedPostIds() {
		var ids = [];
		var config = listScreen();
		var selector = config && config.selectionSelector ? config.selectionSelector : 'post[]';

		$('tbody .check-column input[type="checkbox"][name="' + selector + '"]:checked').each(function () {
			var value = Number($(this).val());

			if (value > 0) {
				ids.push(value);
			}
		});

		return ids;
	}

	function buildPrimaryActionLabel(count, label, selectedMode) {
		count = Math.max(0, Number(count) || 0);
		label = String(label || '');

		if (selectedMode) {
			return icAIAdmin.messages.enhanceLabel + ' ' + count + ' ' + icAIAdmin.messages.selected + ' ' + label;
		}

		return icAIAdmin.messages.enhanceLabel + ' ' + count + ' ' + label;
	}

	// Returns the complete server-localized primary action label (registered
	// singular/plural item label, translated word order) for the current scope.
	function listActionLabel(config, selectedCount) {
		var labels = config && config.actionLabels && typeof config.actionLabels === 'object' ? config.actionLabels : {};

		if (selectedCount > 0) {
			if (Object.prototype.hasOwnProperty.call(labels, String(selectedCount))) {
				return String(labels[String(selectedCount)]);
			}

			return buildPrimaryActionLabel(selectedCount, config.scopeLabel, true);
		}

		return config.scopeActionLabel ? String(config.scopeActionLabel) : buildPrimaryActionLabel(config.scopeCount, config.scopeLabel, false);
	}

	// Returns the exact server-localized AI Credit usage label for the items the
	// primary list action would submit.
	function listUsageLabel(config, selectedCount) {
		var labels = config && config.usageLabels && typeof config.usageLabels === 'object' ? config.usageLabels : {};

		if (selectedCount > 0) {
			return Object.prototype.hasOwnProperty.call(labels, String(selectedCount)) ? String(labels[String(selectedCount)]) : '';
		}

		return config && config.scopeUsageLabel ? String(config.scopeUsageLabel) : '';
	}

	function updatePrimaryListButton() {
		var config = listScreen();
		var $actions = listActionsContainer();
		var $button = $actions.find('.ic-ai-list-start').first();
		var $usage = $actions.find('.ic-ai-credit-usage').first();
		var usageLabel;
		var ids;

		if (!config || !$button.length) {
			return;
		}

		ids = selectedPostIds();
		$button.text(listActionLabel(config, ids.length));
		if ($usage.length) {
			usageLabel = listUsageLabel(config, ids.length);
			$usage.html(creditUsageContentHtml(usageLabel));
			$usage.toggleClass('ic-ai-hidden', '' === usageLabel);
		}
	}

	function updateReviewLink(count, label) {
		var config = listScreen();
		var $link = listActionsContainer().find('.ic-ai-review-link').first();

		if (!config || !$link.length) {
			return;
		}

		count = Math.max(0, Number(count) || 0);
		config.reviewCount = count;
		// The server is the only producer of this label: it owns the formatted
		// count and the singular/plural form. Without a label the link keeps its
		// server-rendered text; this script never formats a number itself. The
		// count-driven disabled state below always runs.
		if (typeof label === 'string' && '' !== label) {
			$link.text(label);
		}
		var locked = !!(config.activeTask && config.activeTask.status && ['completed', 'failed', 'stopped'].indexOf(config.activeTask.status) === -1);
		$link.toggleClass('disabled', count < 1 || locked).attr('aria-disabled', locked ? 'true' : null).attr('tabindex', locked ? '-1' : null);
	}

	function singleReviewUrl(postId) {
		var config = listScreen();

		if (!config || !config.reviewUrl) {
			return '';
		}

		var parameter = 'taxonomy' === config.targetKind ? 'object_id' : 'post_id';
		return config.reviewUrl + (config.reviewUrl.indexOf('?') === -1 ? '?' : '&') + parameter + '=' + encodeURIComponent(postId);
	}

	function setListCellReviewLink($cell, postId) {
		var $action = $cell.find('.ic-ai-list-action').first();
		var $link = $('<a class="button button-secondary ic-ai-list-action" data-action="review"></a>')
			.attr('href', singleReviewUrl(postId) || '#')
			.text(icAIAdmin.messages.reviewDraftLabel);

		// Reviewing an existing suggestion uses no AI Credit.
		$cell.find('.ic-ai-credit-usage').remove();

		if ($action.length) {
			$action.replaceWith($link);
			return;
		}

		$cell.find('.ic-ai-list-cell-actions').first().append($link);
	}

	function listCellFeedback($cell) {
		var $feedback = $cell.find('.ic-ai-list-feedback').first();

		if (!$feedback.length) {
			$feedback = $('<div class="ic-ai-list-feedback" aria-live="polite"></div>').appendTo($cell);
		}

		return $feedback;
	}

	var taskWordState = null;

	function taskWords(task) {
		var words = Array.isArray(icAIAdmin.taskWords) && icAIAdmin.taskWords.length ? icAIAdmin.taskWords.slice() : [];

		words.unshift( task && task.operation === 'refine' ? icAIAdmin.messages.refineTaskRunning : icAIAdmin.messages.taskRunning );

		return words;
	}

	function currentTaskWord() {
		if (taskWordState && taskWordState.words.length) {
			return taskWordState.words[taskWordState.wordIndex % taskWordState.words.length];
		}

		return icAIAdmin.messages.taskRunning;
	}

	function taskCountText(task) {
		var processed = Number(task && task.processed ? task.processed : 0);
		var total = Number(task && task.total ? task.total : 0);

		return processed + '/' + total;
	}
	function isTaskTerminal(task) {
		return !!task && (task.status === 'completed' || task.status === 'failed' || task.status === 'stopped');
	}

	function taskRunningMarkup(task) {
		var seconds = taskWordState ? taskWordState.seconds : 0;
		var wording = taskWaitsForWindowReset( task ) ? icAIAdmin.messages.windowWait : loadingWordMarkup( currentTaskWord() );

		return [
			'<span class="ic-ai-loading-state">',
			'<span class="ic-ai-loading-wording" aria-hidden="true">', taskWaitsForWindowReset( task ) ? escapeHtml( wording ) : wording, '</span>',
			'<span class="ic-ai-loading-elapsed" aria-hidden="true">',
			'<span class="ic-ai-loading-elapsed-value">', escapeHtml(formatElapsedTime(seconds)), '</span> ',
			'<span class="ic-ai-loading-count">', escapeHtml(taskCountText(task)), '</span>',
			'</span>',
			'</span>'
		].join('');
	}

	function taskWaitsForWindowReset( task ) {
		return !!task && 'window_rate' === task.noticeCode;
	}

	function clearTaskWordTimeout() {
		if ( taskWordState && taskWordState.timeoutId ) {
			window.clearTimeout( taskWordState.timeoutId );
			taskWordState.timeoutId = 0;
		}
	}

	function taskProgressMarkup(task) {
		var html = '';
		var actionLabel = task && task.lastErrorActionLabel ? String(task.lastErrorActionLabel) : icAIAdmin.messages.upgradePlan;
		var refine      = task && task.operation === 'refine';
		if (task && task.notice) {
			html += '<span class="ic-ai-task-notice">' + escapeHtml(String(task.notice)) + '</span>';
			if (task.noticeActionUrl) { html += ' <a class="button-link" href="' + escapeHtml(String(task.noticeActionUrl)) + '">' + escapeHtml(String(task.noticeActionLabel || icAIAdmin.messages.upgradePlan)) + '</a> ' + escapeHtml( icAIAdmin.messages.windowRateSuffix ); }
			html += ' ';
		}

		if (task && task.lastError) {
			html += escapeHtml(String(task.lastError));
			if (task.lastErrorActionUrl) {
				html += ' <a class="button-link" href="' + escapeHtml(String(task.lastErrorActionUrl)) + '">' + escapeHtml(actionLabel) + '</a>';
			}
			html += ' ';
		}

		if (task && task.status === 'completed') {
			html += escapeHtml( refine ? icAIAdmin.messages.refineTaskComplete : icAIAdmin.messages.taskComplete );
			return html;
		}
		if (task && task.status === 'failed') {
			html += escapeHtml( refine ? icAIAdmin.messages.refineTaskFailed : icAIAdmin.messages.taskFailed );
			if (task.terminalCode === 'ic_ai_quota_blocked' && Number(task.pending || 0) > 0) {
				html += ' <button type="button" class="button-link ic-ai-continue-accepted" data-task-id="' + escapeHtml(String(task.id || '')) + '">' + escapeHtml(icAIAdmin.messages.continueAccepted) + '</button>';
			}
			return html;
		}
		if (task && task.status === 'stopped') {
			return escapeHtml(icAIAdmin.messages.taskStopped) + (Number(task.pending || 0) > 0 ? ' <button type="button" class="button-link ic-ai-continue-accepted" data-task-id="' + escapeHtml(String(task.id || '')) + '">' + escapeHtml(icAIAdmin.messages.continueAccepted) + '</button>' : '');
		}
		if (task && task.status === 'stopping') {
			html += escapeHtml(icAIAdmin.messages.taskStopping);
			return html;
		}

		html += taskRunningMarkup(task);
		if (task && task.canStop) {
			html += ' <button type="button" class="button-link ic-ai-stop-task" data-task-id="' + escapeHtml(String(task.id || '')) + '">' + escapeHtml(icAIAdmin.messages.stopTask) + '</button>';
		}

		return html;
	}

	function taskProgressInPlaceReady($progress) {
		return taskWordState && taskWordState.built &&
			taskWordState.$wording && taskWordState.$wording.length &&
			taskWordState.$wording.closest('body').length &&
			taskWordState.$progress && taskWordState.$progress[0] === $progress[0];
	}

	function writeTaskProgress(task) {
		var $progress = listActionsContainer().find('.ic-ai-list-progress').first();
		var isRunning = !task || !isTaskTerminal(task);
		var hasError = !!(task && task.lastError);
		var $wording;
		var fingerprint = JSON.stringify({id: task && task.id || '', status: task && task.status || '', operation: task && task.operation || '', canStop: !!(task && task.canStop), notice: task && task.notice || '', noticeCode: task && task.noticeCode || '', noticeActionUrl: task && task.noticeActionUrl || '', noticeActionLabel: task && task.noticeActionLabel || '', lastError: task && task.lastError || '', terminalCode: task && task.terminalCode || '', pending: Number(task && task.pending || 0)});

		if (!$progress.length) {
			return;
		}

		// In-place path: steady running updates mutate text/inner content only,
		// so `.ic-ai-loading-wording` is never rebuilt and its palette animation keeps running.
		if (isRunning && !hasError && (!(task && task.notice) || taskWaitsForWindowReset( task )) && taskProgressInPlaceReady($progress) && taskWordState.structureFingerprint === fingerprint) {
			taskWordState.$elapsedValue.text(formatElapsedTime(taskWordState.seconds));
			taskWordState.$count.text(taskCountText(task));
			if (taskWordState.wordIndex !== taskWordState.lastWordIndex) {
				taskWordState.$wording.html(loadingWordMarkup(currentTaskWord()));
				taskWordState.lastWordIndex = taskWordState.wordIndex;
			}
			return;
		}

		// Full-build path: not running, error present, or the running structure isn't built yet.
		$progress.html(taskProgressMarkup(task));

		if (!isRunning) {
			if (taskWordState) {
				taskWordState.built = false;
			}
			return;
		}

		$wording = $progress.find('.ic-ai-loading-wording').first();
		$wording.css('animation-name', ensureLoadingPaletteAnimation());

		if (taskWordState) {
			taskWordState.$progress = $progress;
			taskWordState.$wording = $wording;
			taskWordState.$elapsedValue = $progress.find('.ic-ai-loading-elapsed-value').first();
			taskWordState.$count = $progress.find('.ic-ai-loading-count').first();
			taskWordState.lastWordIndex = taskWordState.wordIndex;
			taskWordState.built = true;
			taskWordState.structureFingerprint = fingerprint;
		}
	}

	function setTaskButtonsDisabled(disabled) {
		var $actions = listActionsContainer();

		$actions.find( '.ic-ai-list-start, .ic-ai-start-refine' ).toggleClass( 'disabled', ! ! disabled ).prop( 'disabled', ! ! disabled );
		$( '.ic-ai-edit-answers' ).toggleClass( 'ic-ai-hidden', ! ! disabled );
		$( '.ic-ai-review-card' ).each(
			function () {
				setReviewActionsDisabled( $( this ), disabled );
			}
		);
	}

	function renderTaskProgress() {
		if (!taskWordState || !taskWordState.lastTask) {
			return;
		}

		writeTaskProgress(taskWordState.lastTask);
	}

	function scheduleNextTaskWord() {
		if (!taskWordState || taskWaitsForWindowReset( taskWordState.lastTask ) || taskWordState.timeoutId) {
			return;
		}

		taskWordState.timeoutId = window.setTimeout(function () {
			if ( taskWordState ) {
				taskWordState.timeoutId = 0;
			}
			if (!taskWordState || !taskWordState.words.length) {
				return;
			}

			taskWordState.wordIndex = (taskWordState.wordIndex + 1) % taskWordState.words.length;
			renderTaskProgress();
			scheduleNextTaskWord();
		}, randomWordDelay());
	}

	function stopTaskWordRotation() {
		clearTaskWordTimeout();
		if (taskWordState && taskWordState.intervalId) {
			window.clearInterval(taskWordState.intervalId);
		}

		taskWordState = null;
	}

	function startTaskWordRotation(task) {
		stopTaskWordRotation();
		taskWordState = {
			words: shuffleLoadingWords( taskWords( task ) ),
			wordIndex: 0,
			lastWordIndex: 0,
			seconds: 0,
			timeoutId: 0,
			intervalId: 0,
			lastTask: task || null,
			built: false,
			structureFingerprint: '',
			$progress: null,
			$wording: null,
			$elapsedValue: null,
			$count: null
		};
		taskWordState.intervalId = window.setInterval(function () {
			if (!taskWordState) {
				return;
			}

			taskWordState.seconds += 1;
			renderTaskProgress();
		}, 1000);
		if ( ! taskWaitsForWindowReset( task ) ) {
			scheduleNextTaskWord();
		}
	}

	function syncTaskWordRotation( task ) {
		if ( ! taskWordState ) {
			return;
		}
		taskWordState.lastTask = task;
		if ( taskWaitsForWindowReset( task ) ) {
			clearTaskWordTimeout();
			return;
		}
		if ( ! taskWordState.timeoutId ) {
			scheduleNextTaskWord();
		}
	}

	function stopTaskPolling() {
		if (window.icAiTaskPollId) {
			window.clearTimeout(window.icAiTaskPollId);
			window.icAiTaskPollId = 0;
		}
		stopTaskWordRotation();
	}

	function handleTaskUpdate(task) {
		var $progress = listActionsContainer().find('.ic-ai-list-progress').first();
		var reloadDelayMs = Number(task && task.reloadDelayMs ? task.reloadDelayMs : 0);
		var shouldAutoReload = reloadDelayMs > 0;
		updateReviewControls(task);

		if (!$progress.length) {
			return;
		}

		if (taskWordState) {
			syncTaskWordRotation( task );
		}

		writeTaskProgress(task);
		var config = listScreen();
		if ( config && task && task.reviewCount !== undefined ) {
			config.reviewCount = Number( task.reviewCount ) || 0;
			config.activeTask = task;
			updateReviewLink( config.reviewCount, task.reviewLabel );
		}
		if (!task || !task.status) {
			return;
		}

		if (isTaskTerminal(task)) {
			stopTaskWordRotation();
			setTaskButtonsDisabled(false);
			if ((task.status === 'completed' || task.status === 'stopped') && shouldAutoReload) {
				window.setTimeout(function () {
					window.location.reload();
				}, reloadDelayMs);
			}
			return;
		}

		setTaskButtonsDisabled(true);
	}

	function updateReviewControls(task) {
		if (!task || !task.targetKey) {
			return;
		}
		var terminalUnlocked = ['completed', 'failed', 'stopped'].indexOf(String(task.status || '')) !== -1 && Number(task.pending || 0) === 0 && Number(task.reviewCount || 0) > 0;
		var targetKeyValue = String(task.targetKey).replace(/"/g, '\\"');
		$( '[data-target-key="' + targetKeyValue + '"]' ).filter('.ic-ai-review-link, .ic-ai-list-action[data-action="review"]').add( $( '[data-target-key="' + targetKeyValue + '"]' ).find('.ic-ai-review-link, .ic-ai-list-action[data-action="review"]') ).each(function () {
			var $control = $(this);
			$control.toggleClass('disabled', !terminalUnlocked);
			if (terminalUnlocked) {
				$control.removeAttr('disabled aria-disabled tabindex');
			} else {
				$control.attr('aria-disabled', 'true').attr('tabindex', '-1');
				if ($control.is('button')) {
					$control.attr('disabled', 'disabled');
				}
			}
		});
	}

	function pollTask(taskId) {
		if (!taskId) {
			return;
		}

		// Clear only the pending poll timeout; the word-rotation timer keeps running between polls.
		if (window.icAiTaskPollId) {
			window.clearTimeout(window.icAiTaskPollId);
			window.icAiTaskPollId = 0;
		}
		$.post(icAIAdmin.ajaxUrl, {
			action: 'ic_ai_list_task_status',
			nonce: icAIAdmin.nonce,
			task_id: taskId
		}).done(function (response) {
			if (!response || !response.success || !response.data || !response.data.task) {
				return;
			}

			handleTaskUpdate(response.data.task);
			if (!isTaskTerminal(response.data.task)) {
				window.icAiTaskPollId = window.setTimeout(function () {
					pollTask(taskId);
				}, 3000);
			}
		}).fail(function () {
			window.icAiTaskPollId = window.setTimeout(function () {
				pollTask(taskId);
			}, 5000);
		});
	}

	$(document).on('click', '.ic-ai-continue-accepted', function () {
		var taskId = String($(this).data('task-id') || '');
		if (taskId) {
			$.post(icAIAdmin.ajaxUrl, { action: 'ic_ai_resume_accepted_jobs', nonce: icAIAdmin.nonce, task_id: taskId }).done(function () { pollTask(taskId); });
		}
	});
	$(document).on('click', '.ic-ai-stop-task', function () {
		var taskId = String($(this).data('task-id') || '');
			if (!taskId || !window.confirm(icAIAdmin.messages.confirmStopTask)) { return; }
		$.post(icAIAdmin.ajaxUrl, { action: 'ic_ai_stop_list_task', nonce: icAIAdmin.nonce, task_id: taskId }).done(function (response) {
			if (response && response.success) { if (response.data && response.data.task) { handleTaskUpdate(response.data.task); } pollTask(taskId); }
		});
	});

	function collectSavedPreviewPayload($form) {
		var payload = {
			selected_fields: [],
			field_values: {}
		};

		$form.find('.ic-ai-preview-field').each(function () {
			var $field = $(this);
			var fieldKey = String($field.data('field-key') || '');

			if (!fieldKey || !$field.find('.ic-ai-apply-toggle').is(':checked')) {
				return;
			}

			payload.selected_fields.push(fieldKey);
			payload.field_values[fieldKey] = savedPreviewFieldValue($field, fieldKey);
		});

		return payload;
	}

	// Gathers the CURRENT values shown in the review draft form (saved draft plus any
	// in-progress edits the reviewer made before refining) keyed by field key, so the
	// refine bases its payload on exactly what the reviewer currently sees rather than
	// on the last-saved draft alone. Returns null when no review draft form is present.
	function collectReviewDraftFields($container) {
		var $form = $container.closest('.ic-ai-review-card, .ic-ai-metabox').find('.ic-ai-saved-preview-form').first();

		if (!$form.length) {
			return null;
		}

		var fields = {};
		var found = false;

		$form.find('.ic-ai-preview-field').each(function () {
			var $field = $(this);
			var fieldKey = String($field.data('field-key') || '');

			if (!fieldKey || !$field.find('.ic-ai-preview-value').length) {
				return;
			}

			fields[fieldKey] = savedPreviewFieldValue($field, fieldKey);
			found = true;
		});

		return found ? fields : null;
	}

	function savedPreviewFieldValue($field, fieldKey) {
		var config = icAIAdmin.fields && icAIAdmin.fields[fieldKey] ? icAIAdmin.fields[fieldKey] : null;
		var value = $field.find('.ic-ai-preview-value').val();

		if (!config || config.type !== 'group') {
			return normalizePostContentBlocks(config, value);
		}

		try {
			value = JSON.parse(value);
		} catch (error) {
			// Leave invalid JSON as text so the server can decide how to handle it.
		}

		return value;
	}

	function previewTextareaDisplayHtml(value) {
		return escapeHtml(value == null ? '' : String(value)).replace(/\n/g, '<br>');
	}

	function setSavedPreviewApplyState($form, applying) {
		var $button = $form.find('.ic-ai-apply-saved-preview').first();

		if (!$button.length) {
			return;
		}

		if (!$button.data('defaultLabel')) {
			$button.data('defaultLabel', $button.text());
		}

		$button.prop('disabled', !!applying);
		$button.text(applying ? icAIAdmin.messages.applyingSaved : $button.data('defaultLabel'));
	}

	function applySavedPreviewForm($form) {
		var payload = collectSavedPreviewPayload($form);
		var postId = Number($form.data('object-id') || $form.data('post-id') || 0);
		var postType = String($form.data('post-type') || icAIAdmin.postType || '');
		var $feedback = $form.find('.ic-ai-preview-feedback').first();

		if (!payload.selected_fields.length) {
			$feedback.html('<p class="notice notice-error inline">' + escapeHtml(icAIAdmin.messages.noneSelected) + '</p>');
			return;
		}

		$feedback.empty();
		setSavedPreviewApplyState($form, true);
		$.post(icAIAdmin.ajaxUrl, $.extend({
			action: 'ic_ai_apply_saved_preview',
			nonce: icAIAdmin.nonce,
			target_key: targetKey($form.data('target-key')),
			object_id: postId
		}, payload)).done(function (response) {
			var data = response && response.success && response.data ? response.data : null;
			var review = reviewScreen();

			if (!data) {
				$feedback.html('<p class="notice notice-error inline">' + escapeHtml(icAIAdmin.messages.error) + '</p>');
				return;
			}

			updateReviewLink(data.review_count, data.review_label);
			if (data.next_url && review && !review.singleMode) { window.location = data.next_url; return; }
			if (review && review.listUrl && (review.singleMode || Number(data.review_count) === 0)) {
				window.location = review.listUrl;
				return;
			}

			window.location.reload();
		}).fail(function (xhr) {
			var message = icAIAdmin.messages.error;
			var data = xhr && xhr.responseJSON && xhr.responseJSON.data ? xhr.responseJSON.data : null;

			if (data && data.message) {
				message = data.message;
			}
			$feedback.html('<p class="notice notice-error inline">' + escapeHtml(message) + '</p>');
		}).always(function () {
			setSavedPreviewApplyState($form, false);
		});
	}

	function generateListPreview($cell, jobId) {
		var postId = Number($cell.data('post-id') || 0);
		var $feedback = listCellFeedback($cell);
		var $button = $cell.find('.ic-ai-list-action').first();
		var requestData = {
			action: 'ic_ai_generate_list_preview',
			nonce: icAIAdmin.nonce,
			target_key: targetKey($cell.data('target-key')),
			object_id: postId
		};
		if ($cell.closest('.ic-ai-review-card').length) {
			requestData.review_regenerate = 1;
		}

		clearSinglePreviewRetry($cell);
		$button.prop('disabled', true);
		startLoadingState($feedback, { compact: true });
		if (jobId) {
			requestData.job_id = jobId;
		}

		$.post(icAIAdmin.ajaxUrl, requestData).done(function (response) {
			var data = response && response.success && response.data ? response.data : null;

			clearLoadingState($feedback);
			if (!data) {
				$feedback.html('<p class="notice notice-error inline">' + escapeHtml(icAIAdmin.messages.error) + '</p>');
				return;
			}

			setListCellReviewLink($cell, postId);
			updateReviewLink(data.review_count, data.review_label);
			$feedback.empty();
		}).fail(function (xhr) {
			var data = xhr && xhr.responseJSON && xhr.responseJSON.data ? xhr.responseJSON.data : null;
			if (data && data.code === 'ic_ai_review_locked' && data.list_url) {
				window.location = data.list_url;
				return;
			}
			var message = icAIAdmin.messages.error;

			clearLoadingState($feedback);
			if (data && data.queueable && data.job_id) {
				renderSinglePreviewQueuedRetry($cell, $feedback, data, function (nextJobId) {
					generateListPreview($cell, nextJobId);
				});
				return;
			}
			if (data && data.queueable) {
				$feedback.html(renderQueueableFeedback(normalizeSingleItemQueueablePayload(data, message), message));
				return;
			}
			if (data && (data.license_flow || data.upgrade_url || data.buy_enhancements_url || data.plans)) {
				$feedback.html(renderRecoveryActions(data));
				return;
			}
			if (data && data.message) {
				message = data.message;
			}
			$feedback.html('<p class="notice notice-error inline">' + escapeHtml(message) + '</p>');
		}).always(function () {
			clearLoadingState($feedback);
			if (!$cell.data('icAiSinglePreviewRetry')) {
				$button.prop('disabled', false);
			}
		});
	}

	function renderListTaskStartError($progress, data) {
		var message = data && data.message ? String(data.message) : icAIAdmin.messages.taskFailed;
		var url;

		$progress.text(message);
		if (data && data.license_flow) {
			url = safeNavigationUrl(data.settings_url || icAIAdmin.settingsUrl);
			if (url) {
				$progress.append(' ', $('<a class="button-link ic-ai-open-settings"></a>').attr('href', url).text(icAIAdmin.messages.openSettings));
			}
		}
	}

	function startListTask(scope, ids) {
		var config = listScreen();
		var $progress = listActionsContainer().find('.ic-ai-list-progress').first();

		if (!config) {
			return;
		}

		setTaskButtonsDisabled(true);
		startTaskWordRotation({ status: 'running', processed: 0, total: Number(config.scopeCount || 0) });
		renderTaskProgress();
		$.post(icAIAdmin.ajaxUrl, {
			action: 'ic_ai_start_list_task',
			nonce: icAIAdmin.nonce,
			target_key: targetKey(config.targetKey),
			scope: scope,
			post_ids: Array.isArray(ids) ? ids : [],
			query: config.queryString || window.location.search
		}).done(function (response) {
			var task = response && response.success && response.data ? response.data.task : null;

			if (!task || !task.id) {
				stopTaskWordRotation();
				setTaskButtonsDisabled(false);
				$progress.text(icAIAdmin.messages.taskFailed);
				return;
			}

			handleTaskUpdate(task);
			pollTask(task.id);
		}).fail(function (xhr) {
			var data = xhr && xhr.responseJSON && xhr.responseJSON.data ? xhr.responseJSON.data : null;

			stopTaskWordRotation();
			setTaskButtonsDisabled(false);
			renderListTaskStartError($progress, data);
		});
	}

	function startRefineTask() {
		var config    = reviewScreen();
		var $progress = listActionsContainer().find( '.ic-ai-list-progress' ).first();

		if ( ! config ) {
			return;
		}

		setTaskButtonsDisabled( true );
		startTaskWordRotation(
			{
				status: 'running',
				operation: 'refine',
				processed: 0,
				total: Number( config.awaitingCount || 0 )
			}
		);
		renderTaskProgress();
		$.post(
			icAIAdmin.ajaxUrl,
			{
				action: 'ic_ai_start_refine_task',
				nonce: icAIAdmin.nonce,
				target_key: targetKey(config.targetKey),
				review_context: 1
			}
		).done(
			function ( response ) {
				var task = response && response.success && response.data ? response.data.task : null;

				if ( ! task || ! task.id ) {
					stopTaskWordRotation();
					setTaskButtonsDisabled( false );
					$progress.text( icAIAdmin.messages.refineTaskFailed );
					return;
				}

				handleTaskUpdate( task );
				pollTask( task.id );
			}
		).fail(
			function ( xhr ) {
				var data = xhr && xhr.responseJSON && xhr.responseJSON.data ? xhr.responseJSON.data : null;

				stopTaskWordRotation();
				setTaskButtonsDisabled( false );
				$progress.text( data && data.message ? data.message : icAIAdmin.messages.refineTaskFailed );
			}
		);
	}

	function bindListInteractions() {
		var config = listScreen();
		var activeTask = config && config.activeTask ? config.activeTask : null;

		if (!config) {
			return;
		}

		updatePrimaryListButton();
		if (activeTask && activeTask.id && !isTaskTerminal(activeTask)) {
			startTaskWordRotation(activeTask);
			handleTaskUpdate(activeTask);
			pollTask(activeTask.id);
		}

		$(document).on('change', 'tbody .check-column input[type="checkbox"], #cb-select-all-1, #cb-select-all-2', function () {
			window.setTimeout(updatePrimaryListButton, 0);
		});

		$(document).on('click', '.ic-ai-list-start', function (event) {
			var ids = selectedPostIds();

			event.preventDefault();
			if ($(this).hasClass('disabled')) {
				return;
			}
			startListTask(ids.length ? 'selected' : 'query', ids);
		});

		$(document).on('click', '.ic-ai-review-link.disabled', function (event) {
			event.preventDefault();
		});
		$(document).on('keydown', '.ic-ai-review-link.disabled, .ic-ai-list-action.disabled', function (event) {
			if (event.key === 'Enter' || event.key === ' ') {
				event.preventDefault();
			}
		});

		$(document).on('click', '.ic-ai-list-action', function () {
			if ($(this).hasClass('disabled') || $(this).attr('aria-disabled') === 'true') {
				return;
			}
			var $cell = $(this).closest('.ic-ai-list-cell');
			var action = String($(this).attr('data-action') || 'preview');

			if (action === 'review') {
				var reviewUrl = $(this).attr('data-review-url');
				if (reviewUrl) {
					window.location.href = reviewUrl;
				}
				return;
			}

			generateListPreview($cell);
		});

		$(document).on('click', '#doaction, #doaction2', function (event) {
			var $button = $(this);
			var selectorId = $button.attr('id') === 'doaction2' ? '#bulk-action-selector-bottom' : '#bulk-action-selector-top';
			var action = $(selectorId).val();
			var ids;

			if (action !== config.bulkAction) {
				return;
			}

			event.preventDefault();
			ids = selectedPostIds();
			if (!ids.length) {
				window.alert(icAIAdmin.messages.noneSelected);
				return;
			}
			startListTask('selected', ids);
		});
	}

	function reviewCardFeedback($card) {
		var $feedback = $card.find('.ic-ai-preview-feedback').first();

		if (!$feedback.length) {
			$feedback = $('<div class="ic-ai-preview-feedback" aria-live="polite"></div>').appendTo($card);
		}

		return $feedback;
	}

	function setReviewActionsDisabled($card, disabled) {
		$card.find( '.ic-ai-run-again, .ic-ai-skip-review, .ic-ai-apply-saved-preview, .ic-ai-field-edit, .ic-ai-field-done, .ic-ai-apply-toggle, .ic-ai-question-box :input' ).prop( 'disabled', ! ! disabled );
		$card.find('.ic-ai-cancel-review').toggleClass('disabled', !!disabled);
	}

	function regenerateReviewDraft($card, jobId) {
		var postId = Number($card.data('object-id') || $card.data('post-id') || 0);
		var $feedback = reviewCardFeedback($card);
		var requestData = {
			action: 'ic_ai_generate_list_preview',
			nonce: icAIAdmin.nonce,
			target_key: targetKey($card.data('target-key')),
			object_id: postId
			,review_context: 1
		};
		requestData.review_regenerate = 1;
		requestData.review_context = 1;

		if (!postId) {
			return;
		}

		clearSinglePreviewRetry($card);
		setReviewActionsDisabled($card, true);
		startLoadingState($feedback);
		if (jobId) {
			requestData.job_id = jobId;
		}

		$.post(icAIAdmin.ajaxUrl, requestData).done(function (response) {
			if (response && response.success) {
				window.location.reload();
				return;
			}

			clearLoadingState($feedback);
			setReviewActionsDisabled($card, false);
			$feedback.html('<p class="notice notice-error inline">' + escapeHtml(icAIAdmin.messages.error) + '</p>');
		}).fail(function (xhr) {
			var data = xhr && xhr.responseJSON && xhr.responseJSON.data ? xhr.responseJSON.data : null;
			var message = icAIAdmin.messages.error;
			if (data && data.code === 'ic_ai_review_locked' && data.list_url) {
				window.location = data.list_url;
				return;
			}
			if (data && data.code === 'ic_ai_review_locked' && data.list_url) {
				window.location = data.list_url;
				return;
			}

			clearLoadingState($feedback);
			if (data && data.queueable && data.job_id) {
				renderSinglePreviewQueuedRetry($card, $feedback, data, function (nextJobId) {
					regenerateReviewDraft($card, nextJobId);
				});
				return;
			}
			if (data && data.queueable) {
				setReviewActionsDisabled($card, false);
				$feedback.html(renderQueueableFeedback(normalizeSingleItemQueueablePayload(data, message), message));
				return;
			}
			if (data && (data.license_flow || data.upgrade_url || data.buy_enhancements_url || data.plans)) {
				setReviewActionsDisabled($card, false);
				$feedback.html(renderRecoveryActions(data));
				return;
			}
			if (data && data.message) {
				message = data.message;
			}
			setReviewActionsDisabled($card, false);
			$feedback.html('<p class="notice notice-error inline">' + escapeHtml(message) + '</p>');
		}).always(function () {
			clearLoadingState($feedback);
			if (!$card.data('icAiSinglePreviewRetry')) {
				setReviewActionsDisabled($card, false);
			}
		});
	}

	function skipReviewPost($card) {
		var postId = Number($card.data('object-id') || $card.data('post-id') || 0);
		var $feedback = reviewCardFeedback($card);

		if (!postId) {
			return;
		}

		setReviewActionsDisabled($card, true);
		$.post(icAIAdmin.ajaxUrl, {
			action: 'ic_ai_skip_review_post',
			nonce: icAIAdmin.nonce,
			target_key: targetKey($card.data('target-key')),
			object_id: postId
		}).done(function (response) {
			var data = response && response.success && response.data ? response.data : null;
			var review = reviewScreen();
			if (review && review.singleMode && review.listUrl) { window.location = review.listUrl; return; }
			if (data && data.next_url) { window.location = data.next_url; return; }
			window.location.reload();
		}).fail(function (xhr) {
			var data = xhr && xhr.responseJSON && xhr.responseJSON.data ? xhr.responseJSON.data : null;
			var message = data && data.message ? data.message : icAIAdmin.messages.error;
			if (data && data.code === 'ic_ai_review_locked' && data.list_url) {
				window.location = data.list_url;
				return;
			}

			setReviewActionsDisabled($card, false);
			$feedback.html('<p class="notice notice-error inline">' + escapeHtml(message) + '</p>');
		});
	}

	function bindReviewInteractions() {
		var review     = reviewScreen();
		var activeTask = review && review.activeTask ? review.activeTask : null;

		if ( ! review ) {
			return;
		}

		if ( activeTask && activeTask.id && !isTaskTerminal(activeTask) ) {
			startTaskWordRotation( activeTask );
			handleTaskUpdate( activeTask );
			pollTask( activeTask.id );
		}

		$( document ).on(
			'click',
			'.ic-ai-start-refine',
			function ( event ) {
				event.preventDefault();
				if ( $( this ).hasClass( 'disabled' ) ) {
					return;
				}
				startRefineTask();
			}
		);

		$(document).on('click', '.ic-ai-apply-saved-preview', function () {
			applySavedPreviewForm($(this).closest('.ic-ai-saved-preview-form'));
		});

		$(document).on('click', '.ic-ai-run-again', function () {
			regenerateReviewDraft($(this).closest('.ic-ai-review-card'));
		});

		$(document).on('click', '.ic-ai-skip-review', function () {
			skipReviewPost($(this).closest('.ic-ai-review-card'));
		});

		$(document).on('click', '.ic-ai-saved-preview-form .ic-ai-field-edit', function (event) {
			var $field = $(this).closest('.ic-ai-preview-field');

			event.preventDefault();
			$field.find('.ic-ai-preview-display').addClass('ic-ai-hidden');
			$(this).addClass('ic-ai-hidden');
			$field.find('.ic-ai-field-done').removeClass('ic-ai-hidden');
			$field.find('.ic-ai-preview-value').removeClass('ic-ai-hidden').trigger('focus');
		});

		$(document).on('click', '.ic-ai-saved-preview-form .ic-ai-field-done', function (event) {
			var $field = $(this).closest('.ic-ai-preview-field');
			var $display = $field.find('.ic-ai-preview-display').first();
			var $textarea = $field.find('.ic-ai-preview-value').first();

			event.preventDefault();
			$textarea.addClass('ic-ai-hidden');
			$(this).addClass('ic-ai-hidden');
			$display.removeClass('ic-ai-hidden');
			$field.find('.ic-ai-field-edit').removeClass('ic-ai-hidden');
			$display.html(previewTextareaDisplayHtml($textarea.val()));
		});

		$(document).on('change', '.ic-ai-saved-preview-form .ic-ai-apply-toggle', function () {
			var $field = $(this).closest('.ic-ai-preview-field');
			var $form = $(this).closest('.ic-ai-saved-preview-form');
			var fieldKey = String($field.data('field-key') || '');

			if (!fieldKey) {
				return;
			}

			$.post(icAIAdmin.ajaxUrl, {
				action: 'ic_ai_save_apply_field_preference',
				nonce: icAIAdmin.nonce,
				target_key: targetKey($form.data('target-key')),
				field_key: fieldKey,
				enabled: $(this).is(':checked') ? 1 : 0
			});
		});
	}

	$(function () {
		if (icAIAdmin.savedPreview && typeof icAIAdmin.savedPreview === 'object') {
			$('.ic-ai-metabox').each(function () {
				renderPreviewResults($(this).find('.ic-ai-preview-results'), icAIAdmin.savedPreview);
			});
		}

		initReviewQuestionBox();

		$(document).on('click', '.ic-ai-preview-button', function () {
			var $box = $(this).closest('.ic-ai-metabox');
			var formData;

			if (window.tinymce) {
				tinymce.triggerSave();
			}

			formData = $('#post').length ? $('#post').serialize() : $('#edittag').serialize();
			clearQueuedRetry($box, false);
			submitPreviewRequest($box, formData);
		});

		$(document).on('click', '.ic-ai-preview-retry-now', function () {
			var $box = $(this).closest('.ic-ai-metabox');
			var state = $box.data('icAiQueuedRetry');

			if (!state || !state.formData) {
				return;
			}

			clearQueuedRetry($box, false);
			submitPreviewRequest($box, state.formData, state.jobId);
		});

		$(document).on('click', '.ic-ai-single-preview-retry-now', function () {
			var $container = $(this).closest('.ic-ai-question-box, .ic-ai-list-cell, .ic-ai-review-card');
			var state = $container.data('icAiSinglePreviewRetry');

			if (!state || typeof state.retryCallback !== 'function') {
				return;
			}

			clearSinglePreviewRetry($container);
			state.retryCallback(state.jobId);
		});

		$(document).on('click', '.ic-ai-question-reopen', function () {
			var $box = $(this).closest('.ic-ai-question-box');
			var state = $box.data('icAiQuestionState');

			if (!state) {
				return;
			}

			renderQuestionBox($box, state.questions, {
				answers: state.answers,
				postId: state.postId,
				formData: state.formData,
				context: state.context,
				$results: state.$results
			});
		});

		$(document).on('click', '.ic-ai-apply-selected', function () {
			var $results = $(this).closest('.ic-ai-preview-results');
			var fields = $results.data('aiFields') || {};
			var requests = [];

			$results.find('.ic-ai-preview-field').each(function () {
				var $field = $(this);
				var fieldKey = $field.data('field-key');
				var config = icAIAdmin.fields[fieldKey];

				if (!config || !$field.find('.ic-ai-apply-toggle').is(':checked') || !Object.prototype.hasOwnProperty.call(fields, fieldKey)) {
					return;
				}

				requests.push(applyField(fieldKey, config, fields[fieldKey]));
			});

			$results.find('.ic-ai-apply-feedback').remove();

			$.when.apply($, requests).done(function () {
				$results.append('<p class="description ic-ai-apply-feedback">' + escapeHtml(icAIAdmin.messages.applied) + '</p>');
			}).fail(function () {
				$results.append('<p class="notice notice-error inline ic-ai-apply-feedback">' + escapeHtml(icAIAdmin.messages.error) + '</p>');
			});
		});

		$(document).on('click', '.ic-ai-question-suggestion', function () {
			$(this).closest('.ic-ai-question').find('.ic-ai-question-custom').first().val($(this).text());
		});

		$(document).on('click', '.ic-ai-question-next', function () {
			var $container = $(this).closest('.ic-ai-question-box');
			var answer = $.trim($(this).closest('.ic-ai-question').find('.ic-ai-question-custom').first().val() || '');

			advanceQuestion($container, answer);
		});

		$(document).on('click', '.ic-ai-question-refine-confirm', function () {
			var $container = $(this).closest('.ic-ai-question-box');
			if (!$(this).prop('disabled')) {
				submitQuestionAnswers($container);
			}
		});

		$(document).on('click', '.ic-ai-question-skip', function () {
			var $container = $(this).closest('.ic-ai-question-box');

			advanceQuestion($container, '');
		});

		relocateListPanel();
		bindListInteractions();
		bindReviewInteractions();
	});

	function initReviewQuestionBox() {
		var review = reviewScreen();

		if (!review) {
			return;
		}

		var saved = icAIAdmin.savedPreview;
		var questions = saved && typeof saved === 'object' ? normalizedQuestions(saved) : [];

		if (!questions.length) {
			return;
		}

		var postId = Number(review.currentPostId || 0);
		var $box = $('.ic-ai-review-card[data-object-id="' + postId + '"], .ic-ai-review-card[data-post-id="' + postId + '"]').find('.ic-ai-question-box').first();
		if (!$box.length) {
			$box = $('.ic-ai-question-box').first();
		}
		if (!$box.length) {
			return;
		}

		renderQuestionBox($box, questions, {
			answers: saved.qa_answers && typeof saved.qa_answers === 'object' ? saved.qa_answers : {},
			postId: postId,
			// Signals ajax_submit_answers() to refine the review DRAFT (not the live
			// post). The review flow inits with an empty formData; submitQuestionAnswers()
			// then gathers the CURRENT review draft field values (saved draft plus the
			// reviewer's in-progress edits) and posts them as review_fields so the refine
			// uses exactly what the reviewer currently sees rather than stale content.
			context: 'review',
			formData: ''
		});
	}
}(jQuery));
