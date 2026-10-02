<?php
/**
 * AI edit-screen UI and AJAX handlers.
 *
 * @package impleCode\IC
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

/**
 * Renders the AI metabox and handles preview/apply requests.
 */
class IC_AI_Admin {
	/**
	 * Memoized taxonomy review contexts for this request.
	 *
	 * @var array
	 */
	private $taxonomy_review_context_cache = array();

	/**
	 * Returns the memoized taxonomy review context.
	 *
	 * @param IC_AI_Target $target Taxonomy target.
	 *
	 * @return array
	 */
	private function taxonomy_review_context( $target ) {
		$key = $target instanceof IC_AI_Target ? $target->key() : '';
		if ( isset( $this->taxonomy_review_context_cache[ $key ] ) ) {
			return $this->taxonomy_review_context_cache[ $key ]; }
		$explicit     = $this->requested_review_term_id();
		$edit_answers = $explicit ? 0 : $this->requested_edit_answers_term_id( $target );
		$skipped      = $this->review_skipped_ids( $key );
		$ids          = get_terms(
			array(
				'taxonomy'   => $target->taxonomy(),
				'hide_empty' => false,
				'fields'     => 'ids',
			)
		);
		$ids          = is_wp_error( $ids ) ? array() : array_map( 'absint', (array) $ids );
		$selected     = $explicit ? $explicit : $edit_answers;
		if ( ! $selected ) {
			foreach ( $ids as $id ) {
				$saved = $this->target_saved_preview( $target, $id );
				if ( ! in_array( $id, $skipped, true ) && empty( $saved['qa_answers'] ) && ( ! empty( $saved['fields'] ) || ! empty( $saved['questions'] ) ) && $this->can_edit_target( $target, $id ) ) {
					$selected = $id;
					break; }
			}
		}
		$saved                                       = $selected ? $this->target_saved_preview( $target, $selected ) : array();
		$context                                     = array(
			'target'            => $target,
			'single_mode'       => $explicit > 0,
			'edit_answers'      => $edit_answers > 0,
			'current_object_id' => $selected,
			'saved_preview'     => $saved,
			'review_count'      => $this->saved_preview_count( $target ),
			'pending_count'     => count( $this->queued_taxonomy_review_ids( $target, $skipped ) ),
			'awaiting_ids'      => $this->awaiting_taxonomy_review_ids( $target ),
			'skipped_ids'       => $skipped,
			'list_url'          => $this->target_list_url( $target->integration(), $target ),
			'review_url'        => $this->review_page_url( $target->integration(), $target ),
			'active_task'       => $this->active_task_summary( $target->integration(), $target ),
		);
		$this->taxonomy_review_context_cache[ $key ] = $context;
		return $context;
	}

	/**
	 * Returns queued taxonomy review term IDs.
	 *
	 * @param IC_AI_Target $target  Taxonomy target.
	 * @param array        $skipped Skipped term IDs.
	 *
	 * @return array
	 */
	private function queued_taxonomy_review_ids( $target, $skipped = array() ) {
		$out = array();
		foreach ( $this->reviewable_saved_preview_post_ids( $target, $skipped ) as $id ) {
			$saved = $this->target_saved_preview( $target, $id );
			if ( empty( $saved['qa_answers'] ) ) {
				$out[] = $id;
			}
		} return $out;
	}
	/**
	 * Returns taxonomy review term IDs awaiting answers.
	 *
	 * @param IC_AI_Target $target Taxonomy target.
	 *
	 * @return array
	 */
	private function awaiting_taxonomy_review_ids( $target ) {
		$out = array();
		foreach ( $this->reviewable_saved_preview_post_ids( $target ) as $id ) {
			$saved = $this->target_saved_preview( $target, $id );
			if ( ! empty( $saved['qa_answers'] ) ) {
				$out[] = $id;
			}
		} return $out;
	}
	/**
	 * Returns the current taxonomy term ID from the admin screen.
	 *
	 * @param WP_Screen|null $screen Current screen.
	 *
	 * @return int
	 */
	private function current_admin_term_id( $screen = null ) {
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only screen routing.
		return isset( $_GET['tag_ID'] ) ? absint( wp_unslash( $_GET['tag_ID'] ) ) : 0;
	}

	/**
	 * Determines whether the current user can configure a target's integration.
	 *
	 * @param IC_AI_Target $target Target object.
	 *
	 * @return bool
	 */
	private function can_manage_target_settings( $target ) {
		if ( ! $target instanceof IC_AI_Target || ! $target->integration() ) {
			return false;
		}

		return current_user_can( $target->integration()->settings_capability() );
	}

	/**
	 * Determines whether a target's admin UI is available to the current user.
	 *
	 * @param IC_AI_Target $target Target object.
	 *
	 * @return bool
	 */
	private function target_ui_available( $target ) {
		return $target instanceof IC_AI_Target
			&& $target->is_valid()
			&& ( $this->manager->client()->is_enabled( $target ) || $this->can_manage_target_settings( $target ) );
	}

	/**
	 * Determines whether a target has list enhancement enabled.
	 *
	 * @param IC_AI_Integration $integration Integration.
	 * @param string            $kind        Target kind.
	 * @param string            $taxonomy    Taxonomy slug.
	 *
	 * @return bool
	 */
	private function target_list_enabled( $integration, $kind = 'post', $taxonomy = '' ) {
		if ( ! is_object( $integration ) || ! method_exists( $integration, 'target' ) ) {
			return false;
		}
		$target = 'taxonomy' === $kind ? $integration->target( 'taxonomy', $taxonomy ) : $integration->target( 'post' );
		return $this->target_ui_available( $target ) && $target->list_enhance_enabled();
	}

	/**
	 * Normalizes a review target argument.
	 *
	 * @param IC_AI_Target|string $target_or_post_type Target or legacy slug.
	 *
	 * @return IC_AI_Target|false
	 */
	private function normalize_review_target( $target_or_post_type ) {
		if ( $target_or_post_type instanceof IC_AI_Target ) {
			return $target_or_post_type;
		}
		$value  = sanitize_text_field( (string) $target_or_post_type );
		$target = $value ? $this->manager->target( $value ) : false;
		if ( $target instanceof IC_AI_Target ) {
			return $target;
		}
		$integration = $value ? $this->manager->integration( $value ) : false;
		return $integration ? $integration->target( 'post' ) : false;
	}
	/**
	 * Resolves the target submitted with the current request.
	 *
	 * @return IC_AI_Target|false
	 */
	private function request_target() {
		// phpcs:ignore WordPress.Security.NonceVerification.Missing -- AJAX callers verify the shared nonce in each public handler.
		$key = isset( $_POST['target_key'] ) ? sanitize_text_field( wp_unslash( $_POST['target_key'] ) ) : '';
		return $key ? $this->manager->target( $key ) : false;
	}

	/**
	 * Returns the allowlisted editor type submitted by an authenticated AJAX request.
	 *
	 * @return string
	 */
	private function request_editor_type() {
		// phpcs:ignore WordPress.Security.NonceVerification.Missing -- AJAX callers verify the shared nonce before this helper is used.
		$editor_type = isset( $_POST['editor_type'] ) ? sanitize_key( wp_unslash( $_POST['editor_type'] ) ) : '';

		return in_array( $editor_type, array( 'classic', 'block' ), true ) ? $editor_type : '';
	}

	/**
	 * Rejects review mutations while a target-wide bulk task is active.
	 *
	 * @param IC_AI_Target|false $target Target object.
	 */
	private function reject_locked_review_action( $target ) {
		if ( ! $target instanceof IC_AI_Target ) {
			return;
		}
		if ( ! current_user_can( $target->edit_capability() ) ) {
			return;
		}
		$state = $this->resolve_target_active_task( $target );
		if ( empty( $state ) || ( in_array( $state['status'], array( 'completed', 'failed', 'stopped' ), true ) && empty( $state['pending_jobs'] ) ) ) {
			return;
		}
		$list_url = $this->target_list_url( $target->integration(), $target );
		wp_send_json_error(
			array(
				'code'     => 'ic_ai_review_locked',
				'message'  => __( 'AI bulk processing is active for this target.', 'post-type-x' ),
				'list_url' => $list_url,
				'task'     => $this->task_summary( $state ),
			),
			409
		);
	}

	/**
	 * Resolves and validates the object identified by a target-key request.
	 *
	 * @param IC_AI_Target $target    Target object.
	 * @param int          $object_id Object ID.
	 *
	 * @return WP_Post|WP_Term|false
	 */
	private function request_target_object( $target, $object_id ) {
		if ( ! $target instanceof IC_AI_Target || ! $target->is_valid() || ! $object_id ) {
			return false;
		}
		if ( 'post' === $target->kind() ) {
			$post = get_post( $object_id );
			return $post instanceof WP_Post && $post->post_type === $target->post_type() ? $post : false;
		}
		$term = get_term( $object_id, $target->taxonomy() );
		return $term && ! is_wp_error( $term ) ? $term : false;
	}

	/**
	 * Checks the target-specific edit capability.
	 *
	 * @param IC_AI_Target $target    Target object.
	 * @param int          $object_id Object ID.
	 *
	 * @return bool
	 */
	private function can_edit_target( $target, $object_id ) {
		$object = $this->request_target_object( $target, $object_id );
		if ( ! $object ) {
			return false;
		}
		if ( 'post' === $target->kind() ) {
			return current_user_can( 'edit_post', $object_id );
		}
		$taxonomy   = get_taxonomy( $target->taxonomy() );
		$capability = $taxonomy && ! empty( $taxonomy->cap->edit_terms ) ? $taxonomy->cap->edit_terms : 'manage_categories';
		return current_user_can( $capability );
	}

	/**
	 * Builds a target field map, including registered extension fields.
	 *
	 * @param IC_AI_Target $target Target object.
	 *
	 * @return array
	 */
	private function target_field_map( $target ) {
		if ( ! $target instanceof IC_AI_Target ) {
			return array();
		}
		return $target->field_map();
	}

	/**
	 * Reads one target object's saved preview from its native meta table.
	 *
	 * @param IC_AI_Target $target    Target object.
	 * @param int          $object_id Object ID.
	 *
	 * @return array
	 */
	private function target_saved_preview( $target, $object_id ) {
		$key = 'taxonomy' === $target->kind() ? get_term_meta( absint( $object_id ), self::SAVED_PREVIEW_META_KEY, true ) : get_post_meta( absint( $object_id ), self::SAVED_PREVIEW_META_KEY, true );
		$key = is_array( $key ) ? $key : array();
		if ( ! empty( $key['target_key'] ) && $key['target_key'] !== $target->key() ) {
			return array(); }
		$key['target_key'] = $target->key();
		$key['fields']     = ! empty( $key['fields'] ) && is_array( $key['fields'] ) ? $key['fields'] : array();
		$key['questions']  = ! empty( $key['questions'] ) && is_array( $key['questions'] ) ? $key['questions'] : array();
		return $key;
	}

	/**
	 * Persists one target object's saved preview in its native meta table.
	 *
	 * @param IC_AI_Target $target    Target object.
	 * @param int          $object_id Object ID.
	 * @param array        $response  Preview response.
	 *
	 * @return array
	 */
	private function save_target_preview( $target, $object_id, $response ) {
		$response               = is_array( $response ) ? $response : array();
		$response['target_key'] = $target->key();
		$fields                 = isset( $response['fields'] ) && is_array( $response['fields'] ) ? $response['fields'] : array();
		foreach ( $fields as $field_key => $value ) {
			$field = $this->target_field_map( $target )[ $field_key ] ?? array();
			if ( 'html' === ( $field['value_type'] ?? $field['content_format'] ?? '' ) ) {
				$fields[ $field_key ] = wp_kses_post( is_scalar( $value ) ? (string) $value : wp_json_encode( $value ) );
			}
		}
		$response['fields'] = $fields;
		if ( 'taxonomy' === $target->kind() ) {
			update_term_meta( $object_id, self::SAVED_PREVIEW_META_KEY, $response );
		} else {
			update_post_meta( $object_id, self::SAVED_PREVIEW_META_KEY, $response );
		}
		return $response;
	}
	/**
	 * Post meta key used for one saved AI preview response.
	 */
	const SAVED_PREVIEW_META_KEY = '_ic_ai_saved_preview';

	/**
	 * Post meta key used to mark one item as AI enhanced.
	 */
	const ENHANCED_META_KEY = '_ic_ai_already_enhanced';

	/**
	 * Post meta key storing the latest enhancement signature.
	 */
	const ENHANCED_SIGNATURE_META_KEY = '_ic_ai_enhanced_signature';

	/**
	 * Cron hook processing shared AI list tasks.
	 */
	const LIST_TASK_HOOK = 'ic_ai_process_list_task';

	/**
	 * Shared list-table bulk action value.
	 */
	const BULK_ACTION = 'ic_ai_bulk_enhance';

	/**
	 * Manager.
	 *
	 * @var IC_AI_Manager
	 */
	private $manager;

	/**
	 * Post IDs currently being written by AI apply logic.
	 *
	 * @var array
	 */
	private $managed_write_post_ids = array();

	/**
	 * Pre-save enhancement signatures captured for the current request.
	 *
	 * @var array
	 */
	private $pre_save_signatures = array();

	/**
	 * Display-only list scope counts memoized for the current request.
	 *
	 * Keyed by target, user, content version and query string. List tasks never
	 * read this cache; start_list_task() always resolves its scope fresh.
	 *
	 * @var array<string, int>
	 */
	private $display_scope_counts = array();

	/**
	 * Constructor.
	 *
	 * @param IC_AI_Manager $manager Manager.
	 */
	public function __construct( $manager ) {
		$this->manager = $manager;
		add_action( 'init', array( $this, 'register_list_hooks' ), 100 );
		add_action( 'add_meta_boxes', array( $this, 'register_metaboxes' ) );
		add_action( 'admin_menu', array( $this, 'register_review_pages' ), 99 );
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_assets' ) );
		add_action( 'pre_post_update', array( $this, 'capture_pre_save_signature' ), 100, 2 );
		add_action( 'wp_after_insert_post', array( $this, 'clear_saved_preview_on_save' ), 100, 4 );
		add_action( 'wp_ajax_ic_ai_preview_post', array( $this, 'ajax_preview' ) );
		add_action( 'wp_ajax_ic_ai_submit_answers', array( $this, 'ajax_submit_answers' ) );
		add_action( 'wp_ajax_ic_ai_save_review_answers', array( $this, 'ajax_save_review_answers' ) );
		add_action( 'wp_ajax_ic_ai_apply_taxonomy_terms', array( $this, 'ajax_apply_taxonomy_terms' ) );
		add_action( 'wp_ajax_ic_ai_generate_list_preview', array( $this, 'ajax_generate_list_preview' ) );
		add_action( 'wp_ajax_ic_ai_skip_review_post', array( $this, 'ajax_skip_review_post' ) );
		add_action( 'wp_ajax_ic_ai_save_apply_field_preference', array( $this, 'ajax_save_apply_field_preference' ) );
		add_action( 'wp_ajax_ic_ai_apply_saved_preview', array( $this, 'ajax_apply_saved_preview' ) );
		add_action( 'wp_ajax_ic_ai_start_list_task', array( $this, 'ajax_start_list_task' ) );
		add_action( 'wp_ajax_ic_ai_start_refine_task', array( $this, 'ajax_start_refine_task' ) );
		add_action( 'wp_ajax_ic_ai_list_task_status', array( $this, 'ajax_list_task_status' ) );
		add_action( 'wp_ajax_ic_ai_stop_list_task', array( $this, 'ajax_stop_list_task' ) );
		add_action( 'wp_ajax_ic_ai_resume_accepted_jobs', array( $this, 'ajax_resume_accepted_jobs' ) );
		add_action( self::LIST_TASK_HOOK, array( $this, 'process_list_task' ), 10, 1 );
	}

	/** Requests a durable local stop for one owned list task. */
	public function ajax_stop_list_task() {
		check_ajax_referer( 'ic-ai-admin', 'nonce' );
		$task_id = isset( $_POST['task_id'] ) ? sanitize_key( wp_unslash( $_POST['task_id'] ) ) : '';
		$state   = $task_id ? $this->normalize_task_state( $this->get_task_state( $task_id ) ) : array();
		$target  = ! empty( $state['target_key'] ) ? $this->manager->target( $state['target_key'] ) : false;
		if ( empty( $state ) || absint( $state['user_id'] ) !== get_current_user_id() || ! $target instanceof IC_AI_Target || ! $target->list_enhance_enabled() || ! current_user_can( $target->edit_capability() ) ) {
			wp_send_json_error( array( 'message' => __( 'This task is not available.', 'post-type-x' ) ), 404 );
		}
		if ( in_array( $state['status'], array( 'completed', 'failed', 'stopped' ), true ) || 'quota' === $state['stop_reason'] ) {
			wp_send_json_success( array( 'task' => $this->task_summary( $state ) ) );
		}
		$marker_value = array(
			'requested_at' => gmdate( 'c' ),
			'requested_by' => get_current_user_id(),
		);
		$marker       = get_option( 'ic_ai_list_task_stop_' . $task_id, false );
		if ( false === $marker && ! add_option( 'ic_ai_list_task_stop_' . $task_id, $marker_value, '', false ) ) {
			wp_send_json_error( array( 'message' => __( 'The task could not be stopped.', 'post-type-x' ) ), 500 );
		}
		if ( is_array( $marker ) && ! empty( $marker['requested_at'] ) ) {
			$marker_value = $marker; }
		$state['status']            = 'stopping';
		$state['stop_reason']       = 'user';
		$state['poll_only']         = true;
		$state['submit_not_before'] = 0;
		$state['stop_requested_at'] = $marker_value['requested_at'];
		$state['stop_requested_by'] = absint( $marker_value['requested_by'] ?? get_current_user_id() );
		if ( empty( $state['drain_deadline'] ) ) {
			$state['drain_deadline'] = time() + absint( apply_filters( 'ic_ai_bulk_drain_timeout', HOUR_IN_SECONDS, $target, $state ) ); }
		$state['notice']      = '';
		$state['notice_code'] = '';
		$this->save_task_state( $state );
		$this->schedule_task_event( $task_id, 1 );
		if ( function_exists( 'spawn_cron' ) ) {
			spawn_cron( time() ); }
		wp_send_json_success( array( 'task' => $this->task_summary( $state ) ) );
	}

	/**
	 * Registers shared list-table hooks for enabled integrations.
	 *
	 * @return void
	 */
	public function register_list_hooks() {
		$has_enabled_integrations      = false;
		$has_enabled_post_integrations = false;

		foreach ( $this->manager->integrations() as $integration ) {
			$post_target = $integration->target( 'post' );
			if ( $this->target_ui_available( $post_target ) && $post_target->list_enhance_enabled() ) {
				$has_enabled_integrations      = true;
				$has_enabled_post_integrations = true;
				add_filter( 'manage_edit-' . $integration->post_type() . '_columns', array( $this, 'register_list_column' ) );
				add_action( 'manage_' . $integration->post_type() . '_posts_custom_column', array( $this, 'render_list_column' ), 10, 2 );
				add_filter( 'bulk_actions-edit-' . $integration->post_type(), array( $this, 'register_bulk_action' ) );
				add_filter( 'handle_bulk_actions-edit-' . $integration->post_type(), array( $this, 'handle_bulk_action_fallback' ), 10, 3 );
			}
			foreach ( (array) $integration->taxonomy_targets() as $taxonomy_target ) {
				if ( ! $this->target_ui_available( $taxonomy_target ) || ! $taxonomy_target->list_enhance_enabled() ) {
					continue;
				}
				$has_enabled_integrations = true;
				$taxonomy_name            = $taxonomy_target->taxonomy();
				add_filter( 'manage_edit-' . $taxonomy_name . '_columns', array( $this, 'register_taxonomy_list_column' ) );
				add_filter( 'manage_' . $taxonomy_name . '_custom_column', array( $this, 'render_taxonomy_list_column' ), 10, 3 );
				add_action( $taxonomy_name . '_edit_form_fields', array( $this, 'render_taxonomy_edit_control' ), 10, 1 );
				add_filter( 'bulk_actions-edit-' . $taxonomy_name, array( $this, 'register_bulk_action' ) );
				add_filter( 'handle_bulk_actions-edit-' . $taxonomy_name, array( $this, 'handle_taxonomy_bulk_action' ), 10, 3 );
				add_action( 'after-' . $taxonomy_name . '-table', array( $this, 'render_taxonomy_list_actions_panel' ) );
			}
		}

		if ( $has_enabled_post_integrations ) {
			add_action( 'manage_posts_extra_tablenav', array( $this, 'render_list_actions_tablenav' ) );
			add_action( 'restrict_manage_posts', array( $this, 'render_enhance_status_filter' ) );
			add_action( 'pre_get_posts', array( $this, 'filter_by_enhance_status' ) );
		}
	}

	/**
	 * Registers hidden AI review pages for enabled list integrations.
	 *
	 * @return void
	 */
	public function register_review_pages() {
		foreach ( $this->manager->integrations() as $integration ) {
			$taxonomy_targets = array_filter(
				(array) $integration->taxonomy_targets(),
				function ( $target ) {
					return $this->target_ui_available( $target ) && $target->list_enhance_enabled();
				}
			);
			if ( ! $this->target_list_enabled( $integration ) && empty( $taxonomy_targets ) ) {
				continue;
			}
			$hook_suffix = add_submenu_page(
				$integration->menu_parent(),
				__( 'AI Review', 'post-type-x' ),
				__( 'AI Review', 'post-type-x' ),
				'read',
				$this->review_page_slug( $integration ),
				array( $this, 'render_review_page' )
			);
			if ( $hook_suffix ) {
				add_action( 'load-' . $hook_suffix, array( $this, 'handle_review_page_load' ) );
			}
			remove_submenu_page( $integration->menu_parent(), $this->review_page_slug( $integration ) );
		}
	}

	/**
	 * Registers AI metaboxes on enabled edit screens.
	 *
	 * @return void
	 */
	public function register_metaboxes() {
		$screen = function_exists( 'get_current_screen' ) ? get_current_screen() : null;
		if ( $this->is_new_post_screen( $screen ) ) {
			return;
		}
		foreach ( $this->manager->integrations() as $integration ) {
			$target = $integration->target( 'post' );
			if ( ! $this->target_ui_available( $target ) ) {
				continue;
			}
			add_meta_box(
				'ic-ai-' . $integration->post_type(),
				__( 'impleCode AI', 'post-type-x' ),
				array( $this, 'render_metabox' ),
				$integration->post_type(),
				'side',
				'high',
				array( 'post_type' => $integration->post_type() )
			);
		}
	}

	/**
	 * Renders the AI metabox.
	 *
	 * @param WP_Post $post Post object.
	 * @param array   $box  Meta box args.
	 *
	 * @return void
	 */
	public function render_metabox( $post, $box ) {
		$post_type   = ! empty( $box['args']['post_type'] ) ? sanitize_key( $box['args']['post_type'] ) : $post->post_type;
		$integration = $this->manager->integration( $post_type );
		if ( ! $integration ) {
			return;
		}
		$target = $integration->target( 'post' );
		if ( ! $this->target_ui_available( $target ) ) {
			return;
		}
		wp_nonce_field( 'ic_ai_preview_' . ( $target ? $target->key() : $post_type ), 'ic_ai_preview_nonce' );
		?>
		<div class="ic-ai-metabox" data-target-key="<?php echo esc_attr( $target ? $target->key() : '' ); ?>" data-post-type="<?php echo esc_attr( $post_type ); ?>">
			<p><?php esc_html_e( 'Improve this product using its current data as context. Review every suggested change before applying it.', 'post-type-x' ); ?></p>
			<p class="ic-ai-action-row"><button type="button" class="button button-primary ic-ai-preview-button"><?php esc_html_e( 'Enhance', 'post-type-x' ); ?></button> <?php echo $this->credit_usage_indicator( 1 ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Indicator markup is escaped by credit_usage_indicator(). ?></p>
			<div class="ic-ai-preview-results" aria-live="polite"></div>
		</div>
		<?php
	}

	/**
	 * Enqueues AI edit-screen assets.
	 *
	 * @return void
	 */
	public function enqueue_assets() {
		$screen      = function_exists( 'get_current_screen' ) ? get_current_screen() : null;
		$context     = $this->screen_context( $screen );
		$mode        = isset( $context['mode'] ) ? $context['mode'] : '';
		$integration = ! empty( $context['integration'] ) ? $context['integration'] : false;
		if ( ! $integration || '' === $mode ) {
			return;
		}
		$post_id       = 'editor' === $mode ? $this->current_admin_post_id( $screen ) : 0;
		$editor_type   = '';
		if ( $post_id && function_exists( 'use_block_editor_for_post' ) ) {
			$editor_post = get_post( $post_id );
			if ( $editor_post instanceof WP_Post ) {
				$editor_type = use_block_editor_for_post( $editor_post ) ? 'block' : 'classic';
			}
		}
		$term_id       = 'taxonomy' === $mode ? $this->current_admin_term_id( $screen ) : 0;
		$saved_preview = $post_id ? $this->saved_preview_response( $post_id, $integration ) : ( $term_id && ! empty( $context['target'] ) ? $this->target_saved_preview( $context['target'], $term_id ) : array() );
		$field_config  = $this->integration_field_config( $integration, 'taxonomy' === $mode && ! empty( $context['target'] ) ? $context['target'] : null );
		$list_config   = null;
		$review_config = null;
		$asset_target  = ! empty( $context['target'] ) && $context['target'] instanceof IC_AI_Target ? $context['target'] : $integration->target( 'post' );
		$settings_url  = $this->manager->settings()->settings_url( $integration, $asset_target );

		if ( 'list' === $mode ) {
			$list_target        = ! empty( $context['target'] ) && $context['target'] instanceof IC_AI_Target ? $context['target'] : $integration->target( 'post' );
			$list_is_taxonomy   = $list_target && 'taxonomy' === $list_target->kind();
			$list_column_hidden = $this->is_ai_list_ui_hidden( $screen );
			$list_scope_count   = $this->display_scope_count( $integration, $list_is_taxonomy ? $list_target : null );
			$list_scope_label   = $list_is_taxonomy ? $list_target->label() : $this->plural_label( $integration );
			$list_item_labels   = $this->item_labels( $integration, $list_is_taxonomy ? $list_target : null );
			$list_row_limit     = $this->selectable_row_limit( $list_scope_count, $list_is_taxonomy ? $list_target : null );
			$list_review_count  = $this->saved_preview_count( $list_target );
			$list_config        = array(
				'postType'          => $integration->post_type(),
				'targetKey'         => $list_target ? $list_target->key() : '',
				'targetKind'        => $list_target ? $list_target->kind() : 'post',
				'selectionSelector' => $list_is_taxonomy ? 'delete_tags[]' : 'post[]',
				'scopeCount'        => $list_scope_count,
				'scopeLabel'        => $list_scope_label,
				'scopeActionLabel'  => $this->build_primary_action_label( $list_scope_count, $list_item_labels ),
				'actionLabels'      => $this->primary_action_label_map( $list_row_limit, $list_item_labels ),
				'scopeUsageLabel'   => $this->credit_usage_text( $list_scope_count ),
				'usageLabels'       => $this->credit_usage_label_map( $list_row_limit ),
				'reviewCount'       => $list_review_count,
				'reviewLabel'       => $this->build_review_action_label( $list_review_count ),
				'reviewUrl'         => $this->review_page_url( $integration, $list_target ),
				'queryString'       => $this->current_list_query_string(),
				'bulkAction'        => self::BULK_ACTION,
				'bulkActionLabel'   => __( 'Enhance', 'post-type-x' ),
				'columnVisible'     => ! $list_column_hidden,
				'activeTask'        => $this->active_task_summary( $integration, $list_target ),
			);
		} elseif ( 'review' === $mode ) {
			if ( ! empty( $context['target'] ) && $context['target'] instanceof IC_AI_Target && 'taxonomy' === $context['target']->kind() ) {
				$review_config = array(
					'targetKey'     => $context['target']->key(),
					'targetKind'    => 'taxonomy',
					'reviewCount'   => $this->saved_preview_count( $context['target'] ),
					'listUrl'       => $this->target_list_url( $integration, $context['target'] ),
					'currentPostId' => $this->requested_review_term_id(),
					'singleMode'    => true,
				);
				$saved_preview = $this->requested_review_term_id() ? $this->target_saved_preview( $context['target'], $this->requested_review_term_id() ) : array();
			} else {
				$single_post_id = $this->requested_review_post_id();
				$edit_post_id   = $single_post_id ? 0 : $this->requested_edit_answers_post_id( $integration );
				$review_post    = ( $single_post_id || $edit_post_id ) ? null : $this->next_review_post( $integration->post_type() );
				$review_post_id = $single_post_id ? $single_post_id : ( $edit_post_id ? $edit_post_id : ( $review_post instanceof WP_Post ? (int) $review_post->ID : 0 ) );
				$review_config  = array(
					'postType'      => $integration->post_type(),
					'reviewCount'   => $this->saved_preview_count( $integration->post_type() ),
					'listUrl'       => $this->target_list_url( $integration, $context['target'] ?? null ),
					'currentPostId' => $review_post_id,
					'singleMode'    => $single_post_id > 0,
					'editAnswers'   => $edit_post_id > 0,
					'awaitingCount' => count( $this->awaiting_refine_post_ids( $integration->post_type() ) ),
					'activeTask'    => $this->active_task_summary( $integration ),
				);
				if ( $review_post_id && current_user_can( 'edit_post', $review_post_id ) ) {
					$saved_preview = $this->saved_preview_response( $review_post_id, $integration );
				}
			}
		}
		if ( 'review' === $mode && ! empty( $context['target'] ) && $context['target'] instanceof IC_AI_Target && 'taxonomy' === $context['target']->kind() ) {
			$review_context = $this->taxonomy_review_context( $context['target'] );
			if ( 'review' === $mode ) {
				$saved_preview = $review_context['saved_preview'];
				$review_config = array(
					'targetKey'     => $context['target']->key(),
					'targetKind'    => 'taxonomy',
					'reviewCount'   => $review_context['review_count'],
					'pendingCount'  => $review_context['pending_count'],
					'awaitingCount' => count( $review_context['awaiting_ids'] ),
					'listUrl'       => $review_context['list_url'],
					'reviewUrl'     => $review_context['review_url'],
					'currentPostId' => $review_context['current_object_id'],
					'singleMode'    => $review_context['single_mode'],
					'editAnswers'   => ! empty( $review_context['edit_answers'] ),
					'activeTask'    => $review_context['active_task'],
				);
			}
			wp_localize_script(
				'ic-ai-admin',
				'icAIAdmin',
				array(
					'targetKey'     => $context['target']->key(),
					'objectId'      => ! empty( $review_context ) ? $review_context['current_object_id'] : $this->current_admin_term_id( $screen ),
					'fieldConfig'   => $field_config,
					'reviewContext' => $review_context,
				)
			);
		}

		wp_enqueue_style( 'ic-ai-admin' );
		if ( 'editor' === $mode || ( 'review' === $mode && $asset_target instanceof IC_AI_Target && 'post' === $asset_target->kind() ) ) {
			wp_enqueue_script( 'wp-block-library' );
		}
		wp_enqueue_script( 'ic-ai-admin' );
		$localized_target    = ( ! empty( $context['target'] ) && $context['target'] instanceof IC_AI_Target && 'taxonomy' === $context['target']->kind() ) ? $context['target'] : $integration->target( 'post' );
		$localized_object_id = ( 'review' === $mode && ! empty( $review_context ) ? $review_context['current_object_id'] : ( 'taxonomy' === $mode ? $this->current_admin_term_id( $screen ) : $post_id ) );
		wp_localize_script(
			'ic-ai-admin',
			'icAIAdmin',
			array(
				'ajaxUrl'        => admin_url( 'admin-ajax.php' ),
				'nonce'          => wp_create_nonce( 'ic-ai-admin' ),
				'screenMode'     => $mode,
				'editorType'     => $editor_type,
				'postType'       => $integration->post_type(),
				'targetKey'      => $localized_target ? $localized_target->key() : '',
				'postId'         => $localized_object_id,
				'fields'         => $field_config,
				'savedPreview'   => ! empty( $saved_preview ) ? $saved_preview : null,
				'listScreen'     => $list_config,
				'reviewScreen'   => $review_config,
				'settingsUrl'    => $settings_url,
				'creditIcon'     => $this->credit_usage_icon(),
				'loadingPalette' => $this->loading_palette(),
				'loadingWords'   => array(
					__( 'Drafting', 'post-type-x' ),
					__( 'Editing', 'post-type-x' ),
					__( 'Revising', 'post-type-x' ),
					__( 'Polishing', 'post-type-x' ),
					__( 'Structuring', 'post-type-x' ),
					__( 'Refining', 'post-type-x' ),
					__( 'Clarifying', 'post-type-x' ),
					__( 'Composing', 'post-type-x' ),
				),
				'taskWords'      => array(
					__( "Assembling the writers' room", 'post-type-x' ),
					__( 'Briefing the copywriters', 'post-type-x' ),
					__( 'Drafting in parallel', 'post-type-x' ),
					__( 'Weaving product stories', 'post-type-x' ),
					__( 'Curating the catalog voice', 'post-type-x' ),
					__( 'Harmonizing the brand tone', 'post-type-x' ),
					__( 'Refining every listing', 'post-type-x' ),
					__( 'Polishing the collection', 'post-type-x' ),
				),
				'messages'       => array(
					'loading'             => __( 'Generating AI suggestions...', 'post-type-x' ),
					'apply'               => __( 'Apply selected changes', 'post-type-x' ),
					'error'               => __( 'The AI service request failed.', 'post-type-x' ),
					'queueTitle'          => __( 'AI request queued in this tab.', 'post-type-x' ),
					'queueClient'         => __( 'Another impleCode AI request from this site is still running.', 'post-type-x' ),
					'queueGlobal'         => __( 'The shared impleCode AI service is busy handling other requests.', 'post-type-x' ),
					'queueWindow'         => __( 'This site reached its AI request limit for the current time window.', 'post-type-x' ),
					'queueRetryingIn'     => __( 'Retrying automatically in', 'post-type-x' ),
					'queueRetryNow'       => __( 'Retry Now', 'post-type-x' ),
					'upgrade'             => __( 'Upgrade now', 'post-type-x' ),
					'upgradePlan'         => __( 'Upgrade Plan', 'post-type-x' ),
					'buyEnhancements'     => __( 'Buy AI Credits', 'post-type-x' ),
					'openSettings'        => __( 'Open AI Settings', 'post-type-x' ),
					'haveKey'             => __( 'I already have AI License Key', 'post-type-x' ),
					'noSuggestions'       => __( 'No suggestions were returned.', 'post-type-x' ),
					'applied'             => __( 'Applied to the editor form. Save the post to persist the changes.', 'post-type-x' ),
					'applySaved'          => __( 'Apply selected changes', 'post-type-x' ),
					'applyingSaved'       => __( 'Applying...', 'post-type-x' ),
					'selected'            => __( 'Selected', 'post-type-x' ),
					'enhanceLabel'        => __( 'Enhance', 'post-type-x' ),
					'enhanceAgainLabel'   => __( 'Enhance Again', 'post-type-x' ),
					'reviewDraftLabel'    => __( 'Review Suggestion', 'post-type-x' ),
					'reviewDraftsLabel'   => __( 'Review Suggestions', 'post-type-x' ),
					'regenerateLabel'     => __( 'Enhance Again', 'post-type-x' ),
					'noneSelected'        => __( 'Select at least one item first.', 'post-type-x' ),
					'taskRunning'         => __( 'AI enhance task running...', 'post-type-x' ),
					'taskQueuedSuffix'    => __( 'queued on AI service', 'post-type-x' ),
					'taskComplete'        => __( 'AI enhance task completed.', 'post-type-x' ),
					'taskFailed'          => __( 'AI enhance task failed.', 'post-type-x' ),
					'taskStopping'        => __( 'Stopping new submissions; accepted jobs are finishing...', 'post-type-x' ),
					'taskStopped'         => __( 'AI enhance task stopped.', 'post-type-x' ),
					'stopTask'            => __( 'Stop', 'post-type-x' ),
					'confirmStopTask'     => __( 'Stop new submissions? Accepted jobs will continue to finish.', 'post-type-x' ),
					'questionsButton'     => __( 'Answer Questions to Refine', 'post-type-x' ),
					'questionSubmit'      => __( 'Enhance with answers', 'post-type-x' ),
					'questionSubmitUsage' => $this->credit_usage_text( 1 ),
					'questionSave'        => __( 'Save answer', 'post-type-x' ),
					'windowWait'          => __( 'Waiting for limit reset...', 'post-type-x' ),
					'windowRateSuffix'    => __( 'to increase this limit and speed up processing.', 'post-type-x' ),
					'questionCustomLabel' => __( 'Your answer', 'post-type-x' ),
					'answersReady'        => __( 'Your answers are ready. Select Enhance with answers to update the suggestion.', 'post-type-x' ),
					'questionsDone'       => __( 'Suggestion updated using your answers.', 'post-type-x' ),
					'answersSaved'        => __( 'Answers saved.', 'post-type-x' ),
					'questionsReopen'     => __( 'Show clarifying questions again', 'post-type-x' ),
					'refineTaskRunning'   => __( 'AI refine task running...', 'post-type-x' ),
					'refineTaskComplete'  => __( 'AI refine task completed.', 'post-type-x' ),
					'refineTaskFailed'    => __( 'AI refine task failed.', 'post-type-x' ),
					/* translators: 1: current question number, 2: total number of questions. */
					'questionProgress'    => __( 'Question %1$d of %2$d', 'post-type-x' ),
					'questionSkip'        => __( 'Skip question', 'post-type-x' ),
					'continueAccepted'    => __( 'Continue checking accepted jobs', 'post-type-x' ),
				),
			)
		);
	}

	/**
	 * Returns the exact admin list URL for a target.
	 *
	 * @param IC_AI_Integration $integration Integration.
	 * @param IC_AI_Target|null $target      Optional target.
	 *
	 * @return string
	 */
	private function target_list_url( $integration, $target = null ) {
		if ( $target instanceof IC_AI_Target && 'taxonomy' === $target->kind() ) {
			return add_query_arg(
				array(
					'taxonomy'  => $target->taxonomy(),
					'post_type' => $target->post_type(),
				),
				admin_url( 'edit-tags.php' )
			);
		}
		return admin_url( 'edit.php?post_type=' . $integration->post_type() );
	}

	/**
	 * Returns the current user's admin color palette for the AI loading state.
	 *
	 * @return array
	 */
	private function loading_palette() {
		global $_wp_admin_css_colors;

		if ( ( ! isset( $_wp_admin_css_colors ) || ! is_array( $_wp_admin_css_colors ) || empty( $_wp_admin_css_colors ) ) && function_exists( 'register_admin_color_schemes' ) ) {
			register_admin_color_schemes();
		}

		$color_scheme = get_user_option( 'admin_color' );
		if ( empty( $color_scheme ) || empty( $_wp_admin_css_colors[ $color_scheme ]->colors ) || ! is_array( $_wp_admin_css_colors[ $color_scheme ]->colors ) ) {
			$color_scheme = 'modern';
		}

		$palette = ! empty( $_wp_admin_css_colors[ $color_scheme ]->colors ) && is_array( $_wp_admin_css_colors[ $color_scheme ]->colors ) ? $_wp_admin_css_colors[ $color_scheme ]->colors : array(
			'#1e1e1e',
			'#3858e9',
			'#7b90ff',
		);

		$loading_palette = array();
		foreach ( $palette as $color ) {
			$sanitized_color = sanitize_hex_color( $color );
			if ( ! empty( $sanitized_color ) ) {
				$loading_palette[] = $sanitized_color;
			}
		}

		if ( empty( $loading_palette ) ) {
			$loading_palette = array(
				'#1e1e1e',
				'#3858e9',
				'#7b90ff',
			);
		}

		return array_values( array_unique( $loading_palette ) );
	}

	/**
	 * Checks whether the screen is a new post screen (post-new.php).
	 *
	 * @param mixed $screen Current screen.
	 *
	 * @return bool
	 */
	private function is_new_post_screen( $screen ) {
		return is_object( $screen ) && isset( $screen->base, $screen->action ) && 'post' === $screen->base && 'add' === $screen->action;
	}

	/**
	 * Returns the current shared AI admin screen context.
	 *
	 * @param mixed $screen Current screen.
	 *
	 * @return array
	 */
	private function screen_context( $screen ) {
		if ( ! is_object( $screen ) ) {
			return array(
				'mode'        => '',
				'integration' => false,
			);
		}
		if ( $this->is_new_post_screen( $screen ) ) {
			return array(
				'mode'        => '',
				'integration' => false,
			);
		}

		$post_type   = ! empty( $screen->post_type ) ? sanitize_key( (string) $screen->post_type ) : '';
		$integration = $post_type ? $this->manager->integration( $post_type ) : false;
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only admin screen routing.
		$page     = isset( $_GET['page'] ) ? sanitize_key( wp_unslash( $_GET['page'] ) ) : '';
		$post_id  = $this->current_admin_post_id( $screen );
		$taxonomy = ! empty( $screen->taxonomy ) ? sanitize_key( (string) $screen->taxonomy ) : '';
		if ( $taxonomy ) {
			foreach ( $this->manager->integrations() as $candidate ) {
				$target = $candidate->target( 'taxonomy', $taxonomy );
				if ( $this->target_ui_available( $target ) && $target->list_enhance_enabled() ) {
					return array(
						'mode'        => $this->current_admin_term_id( $screen ) ? 'taxonomy' : 'list',
						'integration' => $candidate,
						'target'      => $target,
					);
				}
			}
		}

		$post_target = $integration ? $integration->target( 'post' ) : false;
		if ( $integration && $this->target_ui_available( $post_target ) && $post_id && get_post_type( $post_id ) === $post_type ) {
			return array(
				'mode'        => 'editor',
				'integration' => $integration,
			);
		}
		if ( $integration && $this->target_ui_available( $post_target ) && 'post' === $screen->base ) {
			return array(
				'mode'        => 'editor',
				'integration' => $integration,
			);
		}
		if ( $integration && $this->target_list_enabled( $integration ) && 'edit' === $screen->base ) {
			return array(
				'mode'        => 'list',
				'integration' => $integration,
			);
		}

		foreach ( $this->manager->integrations() as $registered_integration ) {
			if ( $page === $this->review_page_slug( $registered_integration ) ) {
				// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only review routing.
				$review_key    = isset( $_GET['target_key'] ) ? sanitize_text_field( wp_unslash( $_GET['target_key'] ) ) : '';
				$review_target = $review_key ? $this->manager->target( $review_key ) : $registered_integration->target( 'post' );
				if ( ! $review_target instanceof IC_AI_Target || $review_target->integration() !== $registered_integration || ! $this->target_ui_available( $review_target ) || ! $review_target->list_enhance_enabled() ) {
					continue;
				}
				return array(
					'mode'        => 'review',
					'integration' => $registered_integration,
					'target'      => $review_target,
				);
			}
		}

		return array(
			'mode'        => '',
			'integration' => false,
		);
	}

	/**
	 * Checks whether the current list screen hides the shared AI column.
	 *
	 * Missing screen or core column helpers fail open so direct calls retain the
	 * existing visible behavior.
	 *
	 * @param mixed $screen Current screen.
	 *
	 * @return bool
	 */
	private function is_ai_list_ui_hidden( $screen = null ) {
		if ( null === $screen && function_exists( 'get_current_screen' ) ) {
			$screen = get_current_screen();
		}
		if ( ! is_object( $screen ) || ! function_exists( 'get_hidden_columns' ) ) {
			return false;
		}
		$context = $this->screen_context( $screen );
		if ( 'list' !== ( $context['mode'] ?? '' ) ) {
			return false;
		}

		return in_array( 'ic_ai_enhance', (array) get_hidden_columns( $screen ), true );
	}

	/**
	 * Returns the list-aware field JS config for one integration.
	 *
	 * @param IC_AI_Integration $integration Integration.
	 * @param IC_AI_Target|null $target      Optional target.
	 *
	 * @return array
	 */
	private function integration_field_config( $integration, $target = null ) {
		if ( $target instanceof IC_AI_Target ) {
			$field_config = array();
			foreach ( $target->field_map() as $field_key => $field ) {
				if ( 'term_field' === ( $field['type'] ?? '' ) && empty( $field['input_selector'] ) ) {
					$field['input_selector'] = 'description' === ( $field['term_field'] ?? '' ) ? '#description' : '#tag-name';
				}
				$field_config[ $field_key ] = $this->js_field_config( $field, $field_key ); }
			return $field_config;
		}
		$enabled_fields = array_merge(
			$this->manager->client()->enabled_field_map( $integration ),
			$this->manager->client()->custom_meta_field_map( $integration, 'enhance' )
		);
		$field_config   = array();

		foreach ( $enabled_fields as $field_key => $field ) {
			$field_config[ $field_key ] = $this->js_field_config( $field, $field_key );
		}

		return $field_config;
	}

	/**
	 * Registers the shared AI list-table column.
	 *
	 * @param array $columns Existing columns.
	 *
	 * @return array
	 */
	public function register_list_column( $columns ) {
		$columns = is_array( $columns ) ? $columns : array();
		unset( $columns['ic_ai_enhance'] );
		$columns['ic_ai_enhance'] = __( 'impleCode AI', 'post-type-x' );

		return $columns;
	}

	/**
	 * Adds the AI column to registered taxonomy list tables.
	 *
	 * @param array $columns Existing columns.
	 *
	 * @return array
	 */
	public function register_taxonomy_list_column( $columns ) {
		$columns                  = is_array( $columns ) ? $columns : array();
		$columns['ic_ai_enhance'] = __( 'impleCode AI', 'post-type-x' );
		return $columns;
	}

	/**
	 * Renders taxonomy target status and row action.
	 *
	 * @param string $output      Existing output.
	 * @param string $column_name Column name.
	 * @param int    $term_id     Term ID.
	 *
	 * @return string
	 */
	public function render_taxonomy_list_column( $output, $column_name, $term_id ) {
		if ( 'ic_ai_enhance' !== $column_name ) {
			return $output;
		}
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- This is read-only admin routing; every related mutation verifies its action nonce and required edit capability.
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Core verifies the taxonomy bulk-action nonce before invoking this callback; this value only resolves the edited taxonomy.
		$taxonomy = isset( $_REQUEST['taxonomy'] ) ? sanitize_key( wp_unslash( $_REQUEST['taxonomy'] ) ) : '';
		$target   = $this->find_taxonomy_target( $taxonomy );
		if ( ! $this->target_ui_available( $target ) || ! $this->can_edit_target( $target, $term_id ) ) {
			return '';
		}
		$preview   = $this->target_saved_preview( $target, $term_id );
		$has_draft = ! empty( $preview['fields'] ) || ! empty( $preview['questions'] );
		$locked    = $this->task_blocks_target_review( $target );
		$state     = $this->enhancement_state( $term_id, $target );
		$label     = $has_draft ? __( 'Review Suggestion', 'post-type-x' ) : ( 'none' === $state ? __( 'Enhance', 'post-type-x' ) : __( 'Enhance Again', 'post-type-x' ) );
		$note      = 'stale' === $state ? ' <span class="ic-ai-enhance-state">' . esc_html__( 'Edited since last enhancement', 'post-type-x' ) . '</span>' : '';
		$action    = $has_draft ? 'review' : 'preview';
		$url       = '#';
		if ( $has_draft ) {
			$url = add_query_arg(
				array(
					'target_key' => $target->key(),
					'object_id'  => absint( $term_id ),
				),
				$this->review_page_url( $target->integration() )
			);
		}
		$disabled = $has_draft && $locked ? ' disabled aria-disabled="true" tabindex="-1"' : '';
		return '<div class="ic-ai-list-cell" data-post-id="' . esc_attr( (string) $term_id ) . '" data-object-id="' . esc_attr( (string) $term_id ) . '" data-target-key="' . esc_attr( $target->key() ) . '"><button type="button" class="button button-secondary ic-ai-list-action' . ( $disabled ? ' disabled' : '' ) . '" data-action="' . esc_attr( $action ) . '" data-target-key="' . esc_attr( $target->key() ) . '" data-object-id="' . esc_attr( (string) $term_id ) . '"' . ( '#' !== $url ? ' data-review-url="' . esc_attr( $url ) . '"' : '' ) . $disabled . '>' . esc_html( $label ) . '</button>' . ( $has_draft ? '' : ' ' . $this->credit_usage_indicator( 1 ) ) . $note . '<div class="ic-ai-list-feedback" aria-live="polite"></div></div>';
	}

	/**
	 * Handles taxonomy bulk enhancement fallback.
	 *
	 * @param string $redirect_url Redirect URL.
	 * @param string $action       Bulk action.
	 * @param array  $term_ids     Selected term IDs.
	 * @return string
	 */
	public function handle_taxonomy_bulk_action( $redirect_url, $action, $term_ids ) {
		if ( self::BULK_ACTION !== $action ) {
			return $redirect_url;
		}
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Core verifies the taxonomy bulk-action nonce before invoking this callback; this value only resolves the edited taxonomy.
		$taxonomy = isset( $_REQUEST['taxonomy'] ) ? sanitize_key( wp_unslash( $_REQUEST['taxonomy'] ) ) : '';
		$target   = $this->find_taxonomy_target( $taxonomy );
		$ids      = is_array( $term_ids ) ? array_values( array_filter( array_map( 'absint', $term_ids ) ) ) : array();
		if ( ! $target || empty( $ids ) || ! current_user_can( $target->edit_capability() ) ) {
			return $redirect_url;
		}
		$task = $this->start_list_task( $target->integration(), 'selected', $ids, true, '', 'preview', $target );
		return is_wp_error( $task ) ? add_query_arg( 'ic_ai_error', rawurlencode( $task->get_error_message() ), $redirect_url ) : add_query_arg( 'ic_ai_task', rawurlencode( $task['id'] ), $redirect_url );
	}

	/**
	 * Renders taxonomy list actions after the terms table.
	 *
	 * @return void
	 */
	public function render_taxonomy_list_actions_panel() {
		$screen   = function_exists( 'get_current_screen' ) ? get_current_screen() : null;
		$taxonomy = $screen && ! empty( $screen->taxonomy ) ? sanitize_key( $screen->taxonomy ) : '';
		$target   = $this->find_taxonomy_target( $taxonomy );
		if ( ! $this->target_ui_available( $target ) || ! $target->list_enhance_enabled() ) {
			return;
		}
		$count        = $this->display_scope_count( $target->integration(), $target );
		$review_count = $this->saved_preview_count( $target );
		$review_url   = $this->review_page_url( $target->integration(), $target );
		$locked       = $this->task_blocks_target_review( $target );
		$review_attrs = $locked ? ' disabled aria-disabled="true" tabindex="-1"' : '';
		$review_class = ( $review_count ? '' : ' disabled' ) . ( $locked ? ' disabled' : '' );
		$hidden_attr  = $this->is_ai_list_ui_hidden( $screen ) ? ' hidden' : '';
		$html         = sprintf( '<div class="ic-ai-list-panel"%13$s data-target-key="%1$s" data-selection-selector="delete_tags[]"><span class="ic-ai-list-panel-label">%2$s</span><span class="ic-ai-list-actions" data-post-type="%3$s" data-target-key="%1$s" data-scope-count="%4$d" data-scope-label="%5$s" data-review-count="%6$d"><a href="#" class="button button-primary ic-ai-list-start">%7$s</a> %12$s <a href="%8$s" class="button button-secondary ic-ai-review-link%9$s"%10$s>%11$s</a><span class="ic-ai-list-progress" aria-live="polite"></span></span></div>', esc_attr( $target->key() ), esc_html__( 'impleCode AI', 'post-type-x' ), esc_attr( $target->post_type() ), $count, esc_attr( $target->label() ), $review_count, esc_html( $this->build_primary_action_label( $count, $this->item_labels( $target->integration(), $target ) ) ), esc_url( $review_url ), $review_class, $review_attrs, esc_html( $this->build_review_action_label( $review_count ) ), $this->credit_usage_indicator( $count ), $hidden_attr );
		// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Markup is escaped while built.
		echo $html;
	}

	/**
	 * Renders controls only on saved taxonomy edit screens, never Add New.
	 *
	 * @param WP_Term $term Term object.
	 */
	public function render_taxonomy_edit_control( $term ) {
		if ( ! $term instanceof WP_Term ) {
			return;
		}
		$target = $this->find_taxonomy_target( $term->taxonomy );
		if ( ! $this->target_ui_available( $target ) || ! $this->can_edit_target( $target, $term->term_id ) ) {
			return;
		}
		wp_nonce_field( 'ic_ai_target_' . $target->key(), 'ic_ai_target_nonce' );
		// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Indicator markup is escaped by credit_usage_indicator().
		printf( '<tr class="form-field"><th scope="row"><label>%s</label></th><td><div class="ic-ai-metabox" data-target-key="%s" data-object-id="%d"><p class="ic-ai-action-row"><button type="button" class="button button-secondary ic-ai-preview-button" data-target-key="%s" data-object-id="%d">%s</button> %s</p><div class="ic-ai-preview-results" aria-live="polite"></div><p class="description">%s</p></div></td></tr>', esc_html__( 'impleCode AI', 'post-type-x' ), esc_attr( $target->key() ), absint( $term->term_id ), esc_attr( $target->key() ), absint( $term->term_id ), esc_html__( 'Enhance', 'post-type-x' ), $this->credit_usage_indicator( 1 ), esc_html__( 'Preview AI-generated category changes before applying them.', 'post-type-x' ) );
	}

	/**
	 * Resolves a taxonomy target by its registered taxonomy identity.
	 *
	 * @param string $taxonomy Taxonomy slug.
	 *
	 * @return IC_AI_Target|false
	 */
	private function find_taxonomy_target( $taxonomy ) {
		$taxonomy = sanitize_key( (string) $taxonomy );
		foreach ( $this->manager->targets() as $target ) {
			if ( $target instanceof IC_AI_Target && 'taxonomy' === $target->kind() && $taxonomy === $target->taxonomy() ) {
				return $target;
			}
		}
		return false;
	}

	/**
	 * Renders the shared AI list-table column.
	 *
	 * @param string $column_name Column name.
	 * @param int    $post_id     Post ID.
	 *
	 * @return void
	 */
	public function render_list_column( $column_name, $post_id ) {
		if ( 'ic_ai_enhance' !== $column_name ) {
			return;
		}

		$post_type   = get_post_type( $post_id );
		$integration = $post_type ? $this->manager->integration( $post_type ) : false;
		if ( ! $integration || ! $this->target_list_enabled( $integration ) || ! current_user_can( 'edit_post', $post_id ) ) {
			return;
		}

		$saved_preview = $this->saved_preview_response( $post_id, $integration );
		$has_preview   = ! empty( $saved_preview['fields'] ) || ! empty( $saved_preview['questions'] );
		$locked        = $this->task_blocks_target_review( $integration->target( 'post' ) );
		$state         = $this->enhancement_state( $post_id, $integration );
		$button_label  = 'none' === $state ? __( 'Enhance', 'post-type-x' ) : __( 'Enhance Again', 'post-type-x' );
		?>
		<div class="ic-ai-list-cell" data-post-id="<?php echo esc_attr( (string) $post_id ); ?>" data-post-type="<?php echo esc_attr( $integration->post_type() ); ?>" data-target-key="<?php echo esc_attr( $integration->target( 'post' ) ? $integration->target( 'post' )->key() : '' ); ?>">
			<p class="ic-ai-list-cell-actions">
				<?php if ( $has_preview ) : ?>
					<a class="button button-secondary ic-ai-list-action<?php echo $locked ? ' disabled' : ''; ?>" data-action="review"<?php echo $locked ? ' aria-disabled="true" tabindex="-1"' : ''; ?> data-review-url="<?php echo esc_url( $this->single_review_url( $integration, $post_id ) ); ?>" href="<?php echo esc_url( $this->single_review_url( $integration, $post_id ) ); ?>">
						<?php esc_html_e( 'Review Suggestion', 'post-type-x' ); ?>
					</a>
				<?php else : ?>
					<span class="ic-ai-list-credit-action">
						<button type="button" class="button button-secondary ic-ai-list-action" data-action="preview">
							<?php echo esc_html( $button_label ); ?>
						</button>
						<?php echo $this->credit_usage_indicator( 1 ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Indicator markup is escaped by credit_usage_indicator(). ?>
					</span>
				<?php endif; ?>
				<?php if ( 'stale' === $state ) : ?>
					<span class="ic-ai-enhance-state"><?php esc_html_e( 'Edited since last enhancement', 'post-type-x' ); ?></span>
				<?php endif; ?>
			</p>
			<div class="ic-ai-list-feedback" aria-live="polite"></div>
		</div>
		<?php
	}

	/**
	 * Renders shared AI list-screen actions inside the top posts tablenav.
	 *
	 * @param string $which Tablenav position.
	 *
	 * @return void
	 */
	public function render_list_actions_tablenav( $which ) {
		$screen      = function_exists( 'get_current_screen' ) ? get_current_screen() : null;
		$context     = $this->screen_context( $screen );
		$integration = ! empty( $context['integration'] ) ? $context['integration'] : false;

		if ( 'top' !== $which || ! $integration || 'list' !== $context['mode'] ) {
			return;
		}

		// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Shared AI list actions are escaped while building the HTML string.
		echo $this->render_list_actions_box( $integration );
	}

	/**
	 * Returns shared AI list-screen action markup for one integration.
	 *
	 * @param IC_AI_Integration $integration Integration.
	 *
	 * @return string
	 */
	private function render_list_actions_box( $integration ) {
		$target = $integration->target( 'post' );
		if ( ! $this->target_ui_available( $target ) || ! $target->list_enhance_enabled() ) {
			return '';
		}

		$scope_count  = $this->display_scope_count( $integration );
		$scope_label  = $this->plural_label( $integration );
		$review_count = $this->saved_preview_count( $integration->post_type() );
		$locked       = $target instanceof IC_AI_Target && $this->task_blocks_target_review( $target );
		$review_attrs = $locked ? ' disabled aria-disabled="true" tabindex="-1"' : '';
		$review_class = ( $review_count ? '' : ' disabled' ) . ( $locked ? ' disabled' : '' );
		$hidden_attr  = $this->is_ai_list_ui_hidden() ? ' hidden' : '';

		return sprintf(
			'<div class="ic-ai-list-panel"%13$s data-target-key="%2$s"><span class="ic-ai-list-panel-label">%1$s</span><span class="ic-ai-list-actions" data-post-type="%3$s" data-target-key="%2$s" data-scope-count="%4$d" data-scope-label="%5$s" data-review-count="%6$d"><a href="#" class="button button-primary ic-ai-list-start">%7$s</a> %12$s <a href="%8$s" class="button button-secondary ic-ai-review-link%9$s"%10$s>%11$s</a> <span class="ic-ai-list-progress" aria-live="polite"></span></span></div>',
			esc_html__( 'impleCode AI', 'post-type-x' ),
			esc_attr( $target instanceof IC_AI_Target ? $target->key() : '' ),
			esc_attr( $integration->post_type() ),
			(int) $scope_count,
			esc_attr( $scope_label ),
			(int) $review_count,
			esc_html( $this->build_primary_action_label( $scope_count, $this->item_labels( $integration ) ) ),
			esc_url( $this->review_page_url( $integration ) ),
			$review_class,
			$review_attrs,
			esc_html( $this->build_review_action_label( $review_count ) ),
			$this->credit_usage_indicator( $scope_count ),
			$hidden_attr
		);
	}

	/**
	 * Renders the AI-enhance status filter dropdown in the list-table tablenav.
	 *
	 * @return void
	 */
	public function render_enhance_status_filter() {
		$screen      = function_exists( 'get_current_screen' ) ? get_current_screen() : null;
		$context     = $this->screen_context( $screen );
		$integration = ! empty( $context['integration'] ) ? $context['integration'] : false;

		if ( ! $integration || 'list' !== $context['mode'] ) {
			return;
		}

		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only list-table filter selection.
		$current = isset( $_GET['ic_ai_status'] ) ? sanitize_key( wp_unslash( $_GET['ic_ai_status'] ) ) : '';
		$options = array(
			''         => __( '&mdash; All impleCode AI statuses &mdash;', 'post-type-x' ),
			'enhanced' => __( 'Suggestions applied', 'post-type-x' ),
			'draft'    => __( 'Suggestions pending review', 'post-type-x' ),
			'none'     => __( 'Not processed', 'post-type-x' ),
		);
		$hidden_attr = $this->is_ai_list_ui_hidden( $screen ) ? ' hidden' : '';
		?>
		<span class="ic-ai-status-filter"<?php echo $hidden_attr; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Static boolean attribute. ?>>
			<select name="ic_ai_status">
				<?php foreach ( $options as $value => $label ) : ?>
					<option value="<?php echo esc_attr( $value ); ?>" <?php selected( $current, $value ); ?>><?php echo esc_html( $label ); ?></option>
				<?php endforeach; ?>
			</select>
		</span>
		<?php
	}

	/**
	 * Filters the list-table main query by AI-enhance status.
	 *
	 * @param WP_Query $query Query.
	 *
	 * @return void
	 */
	public function filter_by_enhance_status( $query ) {
		if ( ! is_admin() || ! $query instanceof WP_Query || ! $query->is_main_query() ) {
			return;
		}

		$post_type = $query->get( 'post_type' );
		if ( ! is_scalar( $post_type ) ) {
			return;
		}
		$post_type   = (string) $post_type;
		$integration = $post_type ? $this->manager->integration( $post_type ) : false;
		if ( ! $integration || ! $this->target_list_enabled( $integration ) ) {
			return;
		}

		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only list-table filter selection.
		$status     = isset( $_GET['ic_ai_status'] ) ? sanitize_key( wp_unslash( $_GET['ic_ai_status'] ) ) : '';
		$meta_query = $this->enhance_status_meta_query( $status );
		if ( empty( $meta_query ) ) {
			return;
		}

		$query->set( 'meta_query', $meta_query );
	}

	/**
	 * Returns the list-query meta clause for one AI status filter value.
	 *
	 * @param string $status AI status value.
	 *
	 * @return array
	 */
	private function enhance_status_meta_query( $status ) {
		$status = sanitize_key( (string) $status );

		if ( 'enhanced' === $status ) {
			return array(
				array(
					'key'     => self::ENHANCED_META_KEY,
					'compare' => 'EXISTS',
				),
			);
		}
		if ( 'draft' === $status ) {
			return array(
				array(
					'key'     => self::SAVED_PREVIEW_META_KEY,
					'compare' => 'EXISTS',
				),
			);
		}
		if ( 'none' === $status ) {
			return array(
				'relation' => 'AND',
				array(
					'key'     => self::ENHANCED_META_KEY,
					'compare' => 'NOT EXISTS',
				),
				array(
					'key'     => self::SAVED_PREVIEW_META_KEY,
					'compare' => 'NOT EXISTS',
				),
			);
		}

		return array();
	}

	/**
	 * Registers the shared AI list-table bulk action.
	 *
	 * @param array $actions Bulk actions.
	 *
	 * @return array
	 */
	public function register_bulk_action( $actions ) {
		if ( $this->is_ai_list_ui_hidden() ) {
			return $actions;
		}

		$actions[ self::BULK_ACTION ] = __( 'Enhance', 'post-type-x' );

		return $actions;
	}

	/**
	 * Provides a non-JS fallback for the shared AI bulk action.
	 *
	 * @param string $redirect_url Redirect URL.
	 * @param string $action       Bulk action.
	 * @param array  $post_ids     Selected post IDs.
	 *
	 * @return string
	 */
	public function handle_bulk_action_fallback( $redirect_url, $action, $post_ids ) {
		if ( self::BULK_ACTION !== $action ) {
			return $redirect_url;
		}

		$post_ids = is_array( $post_ids ) ? array_map( 'absint', $post_ids ) : array();
		$post_ids = array_values( array_filter( $post_ids ) );
		if ( empty( $post_ids ) ) {
			return $redirect_url;
		}

		$post_type   = get_post_type( $post_ids[0] );
		$integration = $post_type ? $this->manager->integration( $post_type ) : false;
		if ( ! $integration || ! $this->target_list_enabled( $integration ) ) {
			return $redirect_url;
		}

		$task = $this->start_list_task( $integration, 'selected', $post_ids, true, '' );
		if ( is_wp_error( $task ) ) {
			return add_query_arg( 'ic_ai_error', rawurlencode( $task->get_error_message() ), $redirect_url );
		}

		return add_query_arg( 'ic_ai_task', rawurlencode( $task['id'] ), $redirect_url );
	}

	/**
	 * Processes nonce-protected review-page actions before assets or markup render.
	 *
	 * @return void
	 */
	public function handle_review_page_load() {
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only routing values are validated before the reset action is processed.
		$post_type = isset( $_GET['post_type'] ) ? sanitize_key( wp_unslash( $_GET['post_type'] ) ) : '';
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only routing values are validated before the reset action is processed.
		$page        = isset( $_GET['page'] ) ? sanitize_key( wp_unslash( $_GET['page'] ) ) : '';
		$integration = $post_type ? $this->manager->integration( $post_type ) : false;
		if ( ! $integration || $page !== $this->review_page_slug( $integration ) ) {
			return;
		}
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Presence is checked before check_admin_referer() validates the action.
		$target_key = isset( $_GET['target_key'] ) ? sanitize_text_field( wp_unslash( $_GET['target_key'] ) ) : '';
		$target     = $target_key ? $this->manager->target( $target_key ) : $integration->target( 'post' );
		if ( ! $target || $target->integration() !== $integration || ! $this->target_ui_available( $target ) || ! $target->list_enhance_enabled() || ! current_user_can( $target->edit_capability() ) ) {
			wp_die( esc_html__( 'You are not allowed to access this page.', 'post-type-x' ) );
		}
		$active = $this->resolve_target_active_task( $target );
		if ( ! empty( $active ) ) {
			wp_safe_redirect( add_query_arg( 'ic_ai_locked', 1, $this->target_list_url( $integration, $target ) ) );
			exit;
		}
		// register_review_pages() hides this page with remove_submenu_page(), which deletes the entry
		// get_admin_page_title() reads, so core cannot resolve a title. Supplying it here keeps
		// wp-admin/admin-header.php from rendering an empty browser title.
		// phpcs:ignore WordPress.WP.GlobalVariablesOverride.Prohibited -- Setting the admin page title on load- is the core idiom for a screen removed from the menu.
		$GLOBALS['title'] = __( 'AI Review', 'post-type-x' );
		if ( empty( $_GET['ic_ai_reset_skips'] ) ) {
			return;
		}

		check_admin_referer( 'ic_ai_reset_skips_' . ( 'taxonomy' === $target->kind() ? $target->key() : $post_type ) );
		$this->clear_review_skips( 'taxonomy' === $target->kind() ? $target->key() : $target->post_type() );
		wp_safe_redirect( $this->review_page_url( $integration, $target ) );
		exit;
	}

	/**
	 * Renders the hidden shared AI review page.
	 *
	 * @return void
	 */
	public function render_review_page() {
		$screen      = function_exists( 'get_current_screen' ) ? get_current_screen() : null;
		$context     = $this->screen_context( $screen );
		$integration = ! empty( $context['integration'] ) ? $context['integration'] : false;

		if ( ! $integration ) {
			wp_die( esc_html__( 'You are not allowed to access this page.', 'post-type-x' ) );
		}
		// Taxonomy review pages use the same hidden page but route by exact target key.
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only review routing.
		$requested_target_key = isset( $_GET['target_key'] ) ? sanitize_text_field( wp_unslash( $_GET['target_key'] ) ) : '';
		$requested_target     = $requested_target_key ? $this->manager->target( $requested_target_key ) : $integration->target( 'post' );
		if ( ! $requested_target instanceof IC_AI_Target || $requested_target->integration() !== $integration || ! $this->target_ui_available( $requested_target ) || ! $requested_target->list_enhance_enabled() || ! current_user_can( $requested_target->edit_capability() ) ) {
			wp_die( esc_html__( 'You are not allowed to access this page.', 'post-type-x' ) );
		}
		if ( ! empty( $this->resolve_target_active_task( $requested_target ) ) ) {
			wp_safe_redirect( add_query_arg( 'ic_ai_locked', 1, $this->target_list_url( $integration, $requested_target ) ) );
			exit;
		}
		if ( $requested_target instanceof IC_AI_Target && 'taxonomy' === $requested_target->kind() ) {
			$this->render_taxonomy_review_page( $requested_target );
			return;
		}

		$post_type = $integration->post_type();

		$single_post_id = $this->requested_review_post_id();
		if ( $single_post_id ) {
			$this->render_single_review_screen( $integration, $single_post_id );

			return;
		}

		$edit_post_id      = $this->requested_edit_answers_post_id( $integration );
		$list_url          = admin_url( 'edit.php?post_type=' . $post_type );
		$item_count        = $this->ordinary_review_count( $post_type );
		$awaiting_ids      = $this->awaiting_refine_post_ids( $post_type );
		$awaiting_count    = count( $awaiting_ids );
		$all_preview_count = $this->saved_preview_count( $post_type );
		$active_task       = $this->active_task_summary( $integration );
		$refine_active     = $this->is_active_refine_task( $active_task );
		if ( 0 === $all_preview_count ) {
			$this->clear_review_skips( $post_type );
		}
		$post           = $edit_post_id ? get_post( $edit_post_id ) : $this->next_review_post( $post_type );
		$pending_count  = $this->pending_review_count( $post_type );
		$skipped_count  = max( 0, $item_count - $pending_count );
		$remaining_text = $this->review_remaining_text( $pending_count, $skipped_count );
		?>
		<div class="wrap ic-ai-review-screen">
			<h1><?php echo esc_html( $this->build_review_action_label( $all_preview_count ) ); ?></h1>
			<p><a href="<?php echo esc_url( $list_url ); ?>"><?php esc_html_e( 'Back to list', 'post-type-x' ); ?></a></p>
			<?php if ( 0 === $all_preview_count ) : ?>
				<div class="notice notice-info inline"><p><?php esc_html_e( 'There are no suggestions pending review right now.', 'post-type-x' ); ?></p></div>
			<?php elseif ( ! $post instanceof WP_Post ) : ?>
				<?php if ( $skipped_count > 0 ) : ?>
					<div class="notice notice-info inline">
						<p>
							<?php
							echo esc_html(
								sprintf(
									/* translators: %s: number of skipped suggestions. */
									_n( 'You skipped %s suggestion.', 'You skipped %s suggestions.', (int) $skipped_count, 'post-type-x' ),
									number_format_i18n( (int) $skipped_count )
								)
							);
							?>
						</p>
						<p><a href="<?php echo esc_url( $this->reset_review_skips_url( $integration ) ); ?>"><?php esc_html_e( 'Show skipped suggestions', 'post-type-x' ); ?></a></p>
					</div>
				<?php endif; ?>
			<?php else : ?>
				<div class="ic-ai-review-card" data-post-id="<?php echo esc_attr( (string) $post->ID ); ?>" data-target-key="<?php echo esc_attr( $integration->target( 'post' ) ? $integration->target( 'post' )->key() : '' ); ?>">
					<h2>
						<a href="<?php echo esc_url( get_edit_post_link( $post->ID, 'raw' ) ); ?>"><?php echo esc_html( get_the_title( $post ) ); ?></a>
					</h2>
						<div class="ic-ai-question-box" aria-live="polite"></div>
						<p class="description"><?php echo esc_html( $remaining_text ); ?></p>
					<?php echo $this->render_saved_preview_form( $post->ID, $integration, 'review' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Shared AI preview form is escaped by helper methods. ?>
					<p class="ic-ai-review-actions">
						<button type="button" class="button button-secondary ic-ai-run-again"><?php esc_html_e( 'Enhance Again', 'post-type-x' ); ?></button>
						<?php echo $this->credit_usage_indicator( 1 ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Indicator markup is escaped by credit_usage_indicator(). ?>
						<button type="button" class="button ic-ai-skip-review"><?php esc_html_e( 'Skip', 'post-type-x' ); ?></button>
					</p>
				</div>
			<?php endif; ?>
			<?php if ( ( 0 === $pending_count && $awaiting_count > 0 ) || ! empty( $active_task ) ) : ?>
				<div class="ic-ai-refine-actions" data-post-type="<?php echo esc_attr( $post_type ); ?>">
					<p>
						<?php if ( 0 === $pending_count && $awaiting_count > 0 && ! $refine_active ) : ?>
							<button type="button" class="button button-primary ic-ai-start-refine">
								<?php
								$awaiting_labels = $this->item_labels( $integration );
								/* translators: 1: formatted item count, 2: registered item label (singular for one item, plural otherwise). */
								echo esc_html( sprintf( __( 'Enhance %1$s %2$s with answers', 'post-type-x' ), number_format_i18n( (int) $awaiting_count ), 1 === (int) $awaiting_count ? $awaiting_labels['singular'] : $awaiting_labels['plural'] ) );
								?>
							</button>
							<?php echo $this->credit_usage_indicator( $awaiting_count ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Indicator markup is escaped by credit_usage_indicator(). ?>
						<?php endif; ?>
						<span class="ic-ai-list-progress" aria-live="polite"></span>
					</p>
					<?php if ( 0 === $pending_count && $awaiting_count > 0 && ! $refine_active ) : ?>
						<details class="ic-ai-edit-answers">
							<summary><?php esc_html_e( 'Edit answers', 'post-type-x' ); ?></summary>
							<ul>
								<?php foreach ( $awaiting_ids as $awaiting_post_id ) : ?>
									<li><a href="<?php echo esc_url( $this->edit_answers_url( $integration, $awaiting_post_id ) ); ?>"><?php echo esc_html( get_the_title( $awaiting_post_id ) ); ?></a></li>
								<?php endforeach; ?>
							</ul>
						</details>
					<?php endif; ?>
				</div>
			<?php endif; ?>
		</div>
		<?php
	}

	/**
	 * Renders the single-item shared AI review screen.
	 *
	 * @param IC_AI_Integration $integration Integration.
	 * @param int               $post_id     Requested post ID.
	 *
	 * @return void
	 */
	private function render_single_review_screen( $integration, $post_id ) {
		$post_type = $integration->post_type();
		$list_url  = admin_url( 'edit.php?post_type=' . $post_type );
		$post      = get_post( $post_id );

		if ( ! $post instanceof WP_Post || get_post_type( $post ) !== $post_type || ! current_user_can( 'edit_post', $post_id ) ) {
			wp_die( esc_html__( 'You are not allowed to access this page.', 'post-type-x' ) );
		}

		$saved         = $this->saved_preview_response( $post_id, $integration );
		$has_preview   = ! empty( $saved['fields'] );
		$has_questions = ! empty( $saved['questions'] );
		?>
		<div class="wrap ic-ai-review-screen">
			<h1><?php esc_html_e( 'Review Suggestion', 'post-type-x' ); ?></h1>
			<p><a href="<?php echo esc_url( $list_url ); ?>"><?php esc_html_e( 'Back to list', 'post-type-x' ); ?></a></p>
			<div class="ic-ai-review-card" data-post-id="<?php echo esc_attr( (string) $post_id ); ?>" data-target-key="<?php echo esc_attr( $integration->target( 'post' ) ? $integration->target( 'post' )->key() : '' ); ?>">
				<h2>
					<a href="<?php echo esc_url( get_edit_post_link( $post_id, 'raw' ) ); ?>"><?php echo esc_html( get_the_title( $post ) ); ?></a>
				</h2>
				<?php if ( ! $has_preview ) : ?>
					<?php if ( $has_questions ) : ?>
						<?php // Question-only draft: the model asked for clarification without proposing fields. Render the box so it can be answered from the single-review URL. ?>
						<div class="ic-ai-question-box" aria-live="polite"></div>
					<?php else : ?>
						<div class="notice notice-info inline"><p><?php esc_html_e( 'There is no suggestion pending review for this item anymore.', 'post-type-x' ); ?></p></div>
					<?php endif; ?>
					<p class="ic-ai-review-actions">
						<button type="button" class="button button-secondary ic-ai-run-again"><?php esc_html_e( 'Enhance Again', 'post-type-x' ); ?></button>
						<?php echo $this->credit_usage_indicator( 1 ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Indicator markup is escaped by credit_usage_indicator(). ?>
						<a class="button ic-ai-cancel-review" href="<?php echo esc_url( $list_url ); ?>"><?php esc_html_e( 'Cancel', 'post-type-x' ); ?></a>
					</p>
					<div class="ic-ai-preview-feedback" aria-live="polite"></div>
				<?php else : ?>
					<div class="ic-ai-question-box" aria-live="polite"></div>
					<?php echo $this->render_saved_preview_form( $post_id, $integration, 'review' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Shared AI preview form is escaped by helper methods. ?>
					<p class="ic-ai-review-actions">
						<button type="button" class="button button-secondary ic-ai-run-again"><?php esc_html_e( 'Enhance Again', 'post-type-x' ); ?></button>
						<?php echo $this->credit_usage_indicator( 1 ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Indicator markup is escaped by credit_usage_indicator(). ?>
						<a class="button ic-ai-cancel-review" href="<?php echo esc_url( $list_url ); ?>"><?php esc_html_e( 'Cancel', 'post-type-x' ); ?></a>
					</p>
				<?php endif; ?>
			</div>
		</div>
		<?php
	}

	/**
	 * Renders the taxonomy review page.
	 *
	 * @param IC_AI_Target $target Taxonomy target.
	 */
	private function render_taxonomy_review_page( $target ) {
		$review_context = $this->taxonomy_review_context( $target );
		$explicit_id    = isset( $_GET['object_id'] ) ? absint( wp_unslash( $_GET['object_id'] ) ) : 0; // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only review routing; no state is changed.
		$term_id        = $explicit_id ? $explicit_id : absint( $review_context['current_object_id'] );
		if ( $explicit_id && ( ! $this->request_target_object( $target, $explicit_id ) || ! $this->can_edit_target( $target, $explicit_id ) ) ) {
			wp_die( esc_html__( 'You are not allowed to access this page.', 'post-type-x' ) ); }
		$skipped = $this->review_skipped_ids( $target->key() );
		if ( $term_id && in_array( $term_id, $skipped, true ) ) {
			$term_id = 0;
		}
		if ( ! $term_id ) {
			$term_ids = get_terms(
				array(
					'taxonomy'   => $target->taxonomy(),
					'hide_empty' => false,
					'fields'     => 'ids',
				)
			);
			foreach ( is_wp_error( $term_ids ) ? array() : $term_ids as $candidate ) {
				$saved_candidate = $this->target_saved_preview( $target, $candidate );
				if ( ! in_array( absint( $candidate ), $skipped, true ) && empty( $saved_candidate['qa_answers'] ) && ( ! empty( $saved_candidate['fields'] ) || ! empty( $saved_candidate['questions'] ) ) ) {
					$term_id = absint( $candidate );
					break;
				}
			}
		}
		$term = $term_id ? get_term( $term_id, $target->taxonomy() ) : false;
		if ( ! $term_id ) {
			$reset       = $this->reset_review_skips_url( $target->integration(), $target );
			$awaiting    = $this->awaiting_taxonomy_review_ids( $target );
			$active_task = $review_context['active_task'];
			echo '<div class="wrap ic-ai-review-screen"><h1>' . esc_html__( 'Suggestion Review Complete', 'post-type-x' ) . '</h1><p>' . esc_html__( 'There are no more suggestions to review.', 'post-type-x' ) . '</p>' . ( ! empty( $skipped ) ? '<p><a href="' . esc_url( $reset ) . '">' . esc_html__( 'Show skipped suggestions', 'post-type-x' ) . '</a></p>' : '' );
			// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- This is read-only admin routing; every related mutation verifies its action nonce and required edit capability.
			if ( ( $awaiting || ! empty( $active_task ) ) && empty( $_GET['object_id'] ) ) {
				echo '<div class="ic-ai-refine-actions" data-target-key="' . esc_attr( $target->key() ) . '"><button type="button" class="button button-primary ic-ai-start-refine"' . ( ! empty( $active_task ) ? ' disabled="disabled"' : '' ) . '>' . esc_html( $this->taxonomy_refine_action_label( count( $awaiting ) ) ) . '</button> ' . $this->credit_usage_indicator( count( $awaiting ) ) . '<span class="ic-ai-list-progress" aria-live="polite"></span></div>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Every dynamic fragment is escaped while building the markup.
			}
			echo '</div>';
			return;
		}
		if ( ! $term || is_wp_error( $term ) || ! $this->can_edit_target( $target, $term_id ) ) {
			wp_die( esc_html__( 'You are not allowed to access this page.', 'post-type-x' ) );
		}
		$integration = $target->integration();
		$list_url    = $this->target_list_url( $integration, $target );
		$saved       = $this->target_saved_preview( $target, $term_id );
		?>
		<div class="wrap ic-ai-review-screen" data-target-key="<?php echo esc_attr( $target->key() ); ?>">
			<h1><?php esc_html_e( 'Review Suggestion', 'post-type-x' ); ?></h1>
			<p><a href="<?php echo esc_url( $list_url ); ?>"><?php esc_html_e( 'Back to list', 'post-type-x' ); ?></a></p>
			<div class="ic-ai-review-card" data-object-id="<?php echo esc_attr( (string) $term_id ); ?>" data-target-key="<?php echo esc_attr( $target->key() ); ?>">
				<h2><a href="<?php echo esc_url( get_edit_term_link( $term_id, $target->taxonomy() ) ); ?>"><?php echo esc_html( $term->name ); ?></a></h2>
				<div class="ic-ai-question-box" aria-live="polite"></div>
				<?php
				if ( ! empty( $saved['fields'] ) ) {
					// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- HTML is escaped at this point.
					echo $this->render_target_saved_preview_form( $target, $term_id, 'review' ); }
				?>
				<p class="ic-ai-review-actions"><button type="button" class="button ic-ai-skip-review"><?php esc_html_e( 'Skip', 'post-type-x' ); ?></button></p>
			</div>
			<?php
			$awaiting    = $this->awaiting_taxonomy_review_ids( $target );
			$active_task = $review_context['active_task']; if ( ( $awaiting || ! empty( $active_task ) ) && ! $review_context['single_mode'] ) :
				?>
				<div class="ic-ai-refine-actions" data-target-key="<?php echo esc_attr( $target->key() ); ?>"><button type="button" class="button button-primary ic-ai-start-refine" <?php disabled( ! empty( $active_task ) ); ?>><?php echo esc_html( $this->taxonomy_refine_action_label( count( $awaiting ) ) ); ?></button> <?php echo $this->credit_usage_indicator( count( $awaiting ) ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Indicator markup is escaped by credit_usage_indicator(). ?><span class="ic-ai-list-progress" aria-live="polite"></span></div><?php endif; ?>
		</div>
		<?php
	}

	/**
	 * Renders a saved preview form for any target kind.
	 *
	 * @param IC_AI_Target $target    Target object.
	 * @param int          $object_id Object ID.
	 * @param string       $context   Form context.
	 *
	 * @return string
	 */
	private function render_target_saved_preview_form( $target, $object_id, $context ) {
		$saved = $this->target_saved_preview( $target, $object_id );
		if ( empty( $saved['fields'] ) ) {
			return '<p class="description">' . esc_html__( 'No suggestion is available for this item.', 'post-type-x' ) . '</p>';
		}
		$html        = '<form class="ic-ai-saved-preview-form" data-object-id="' . esc_attr( (string) $object_id ) . '" data-target-key="' . esc_attr( $target->key() ) . '" data-context="' . esc_attr( $context ) . '">';
		$preferences = $this->apply_field_preferences( $target->key() );
		foreach ( $saved['fields'] as $field_key => $value ) {
			$field = isset( $target->field_map()[ $field_key ] ) ? $target->field_map()[ $field_key ] : array();
			if ( empty( $field ) ) {
				continue; }
			$checked = ! isset( $preferences[ $field_key ] ) || ! empty( $preferences[ $field_key ] );
			$html   .= '<div class="ic-ai-preview-field" data-field-key="' . esc_attr( $field_key ) . '"><label><input type="checkbox" class="ic-ai-apply-toggle"' . ( $checked ? ' checked="checked"' : '' ) . ' /> ' . esc_html( $field['label'] ?? $field_key ) . '</label><textarea class="widefat ic-ai-preview-value" rows="4">' . esc_textarea( is_scalar( $value ) ? (string) $value : wp_json_encode( $value ) ) . '</textarea></div>';
		}
		$html .= '<p><button type="button" class="button button-primary ic-ai-apply-saved-preview">' . esc_html__( 'Apply selected changes', 'post-type-x' ) . '</button></p></form>';
		return $html;
	}

	/**
	 * Returns the requested single-mode review post ID.
	 *
	 * @return int
	 */
	private function requested_review_post_id() {
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only admin screen routing.
		return isset( $_GET['post_id'] ) ? absint( wp_unslash( $_GET['post_id'] ) ) : 0;
	}

	/**
	 * Returns the requested single-mode review term ID.
	 *
	 * @return int
	 */
	private function requested_review_term_id() {
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only admin screen routing.
		return isset( $_GET['object_id'] ) ? absint( wp_unslash( $_GET['object_id'] ) ) : 0;
	}

	/**
	 * Returns the requested taxonomy draft whose answers are being edited.
	 *
	 * @param IC_AI_Target $target Taxonomy target.
	 * @return int
	 */
	private function requested_edit_answers_term_id( $target ) {
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only review screen routing.
		$term_id = isset( $_GET['ic_ai_edit_answers'] ) ? absint( wp_unslash( $_GET['ic_ai_edit_answers'] ) ) : 0;
		if ( ! $term_id || ! $target instanceof IC_AI_Target || 'taxonomy' !== $target->kind() || ! $this->request_target_object( $target, $term_id ) ) {
			return 0;
		}
		if ( ! $this->can_edit_target( $target, $term_id ) ) {
			wp_die( esc_html__( 'You are not allowed to access this page.', 'post-type-x' ) );
		}
		$saved = $this->target_saved_preview( $target, $term_id );
		return ! empty( $saved['questions'] ) && ! empty( $saved['qa_answers'] ) ? $term_id : 0;
	}

	/**
	 * Returns an eligible answered draft selected for bulk-mode answer editing.
	 *
	 * @param IC_AI_Integration $integration Integration.
	 *
	 * @return int
	 */
	private function requested_edit_answers_post_id( $integration ) {
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only admin screen routing.
		$post_id = isset( $_GET['ic_ai_edit_answers'] ) ? absint( wp_unslash( $_GET['ic_ai_edit_answers'] ) ) : 0;
		if ( ! $post_id ) {
			return 0;
		}
		if ( $this->is_active_refine_task( $this->active_task_summary( $integration ) ) ) {
			return 0;
		}

		$post = get_post( $post_id );
		if ( ! $post instanceof WP_Post || get_post_type( $post ) !== $integration->post_type() ) {
			return 0;
		}
		if ( ! current_user_can( 'edit_post', $post_id ) ) {
			wp_die( esc_html__( 'You are not allowed to access this page.', 'post-type-x' ) );
		}

		return $this->is_awaiting_refine( $post_id, $integration ) ? $post_id : 0;
	}

	/**
	 * Returns the nonce-protected URL that resets the review skip list.
	 *
	 * @param IC_AI_Integration $integration Integration.
	 * @param IC_AI_Target      $target      Optional target.
	 *
	 * @return string
	 */
	private function reset_review_skips_url( $integration, $target = null ) {
		$target = $target instanceof IC_AI_Target ? $target : $integration->target( 'post' );
		return wp_nonce_url(
			add_query_arg( 'ic_ai_reset_skips', 1, $this->review_page_url( $integration, $target ) ),
			'ic_ai_reset_skips_' . ( 'taxonomy' === $target->kind() ? $target->key() : $integration->post_type() )
		);
	}

	/**
	 * Returns the next saved-preview post for one post type.
	 *
	 * @param string $post_type Post type.
	 *
	 * @return WP_Post|null
	 */
	private function next_review_post( $post_type ) {
		$post_ids = $this->queued_review_post_ids( $post_type, $this->review_skipped_ids( $post_type ) );

		return ! empty( $post_ids[0] ) ? get_post( $post_ids[0] ) : null;
	}

	/**
	 * Returns the shared AI review-page slug.
	 *
	 * @param IC_AI_Integration $integration Integration.
	 *
	 * @return string
	 */
	private function review_page_slug( $integration ) {
		return 'ic-ai-review-' . sanitize_key( $integration->post_type() );
	}

	/**
	 * Returns the shared AI review page URL.
	 *
	 * @param IC_AI_Integration $integration Integration.
	 * @param IC_AI_Target|null $target Optional target.
	 *
	 * @return string
	 */
	private function review_page_url( $integration, $target = null ) {
		$url = admin_url(
			add_query_arg(
				array(
					'post_type' => $integration->post_type(),
					'page'      => $this->review_page_slug( $integration ),
				),
				'edit.php'
			)
		);
		if ( $target instanceof IC_AI_Target ) {
			$url = add_query_arg( 'target_key', $target->key(), $url );
		}
		return $url;
	}

	/**
	 * Returns the single-mode shared AI review page URL for one post.
	 *
	 * @param IC_AI_Integration $integration Integration.
	 * @param int               $post_id     Post ID.
	 *
	 * @return string
	 */
	private function single_review_url( $integration, $post_id ) {
		return add_query_arg( 'post_id', absint( $post_id ), $this->review_page_url( $integration ) );
	}

	/**
	 * Returns the bulk-mode answer-editing URL for one answered draft.
	 *
	 * @param IC_AI_Integration $integration Integration.
	 * @param int               $post_id     Post ID.
	 *
	 * @return string
	 */
	private function edit_answers_url( $integration, $post_id ) {
		return add_query_arg( 'ic_ai_edit_answers', absint( $post_id ), $this->review_page_url( $integration ) );
	}

	/**
	 * Returns the plural label for one integration.
	 *
	 * @param IC_AI_Integration $integration Integration.
	 *
	 * @return string
	 */
	private function plural_label( $integration ) {
		$label  = $integration->label();
		$object = get_post_type_object( $integration->post_type() );
		if ( $object && ! empty( $object->labels->name ) ) {
			$label = $object->labels->name;
		}

		return (string) $label;
	}

	/**
	 * Returns the number of items a query-scope list task would process, for display.
	 *
	 * Uses the same capability-filtered resolvers as start_list_task() so the
	 * displayed count matches the items (and AI Credits) the task would use. The
	 * count is memoized per request because the list panel and the localized list
	 * config both need it; the key includes the user, the post/term cache version
	 * and the query string, so content or filter changes resolve again.
	 *
	 * @param IC_AI_Integration $integration Integration.
	 * @param IC_AI_Target|null $taxonomy    Taxonomy target for term lists, null for post lists.
	 *
	 * @return int
	 */
	private function display_scope_count( $integration, $taxonomy = null ) {
		$query       = $this->current_list_query_string();
		$is_taxonomy = $taxonomy instanceof IC_AI_Target && 'taxonomy' === $taxonomy->kind();
		$key         = implode(
			'|',
			array(
				$is_taxonomy ? $taxonomy->key() : 'post:' . $integration->post_type(),
				get_current_user_id(),
				wp_cache_get_last_changed( $is_taxonomy ? 'terms' : 'posts' ),
				$query,
			)
		);
		if ( ! isset( $this->display_scope_counts[ $key ] ) ) {
			$this->display_scope_counts[ $key ] = $is_taxonomy ? count( $this->query_scope_term_ids( $taxonomy, $query ) ) : count( $this->query_scope_post_ids( $integration, $query ) );
		}

		return $this->display_scope_counts[ $key ];
	}

	/**
	 * Returns the raw current admin list query string.
	 *
	 * @return string
	 */
	private function current_list_query_string() {
		// phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- The raw query string is preserved and sanitized per parameter by the scope resolvers after parse_str().
		return isset( $_SERVER['QUERY_STRING'] ) ? (string) wp_unslash( $_SERVER['QUERY_STRING'] ) : '';
	}

	/**
	 * Returns the largest checkbox selection size the current list page can produce.
	 *
	 * @param int               $scope_count Query-scope item count.
	 * @param IC_AI_Target|null $taxonomy    Taxonomy target for term lists, null for post lists.
	 *
	 * @return int
	 */
	private function selectable_row_limit( $scope_count, $taxonomy = null ) {
		global $wp_query;

		$scope_count = absint( $scope_count );
		if ( $taxonomy instanceof IC_AI_Target ) {
			$per_page = absint( get_user_option( 'edit_' . $taxonomy->taxonomy() . '_per_page' ) );

			return min( $scope_count, $per_page > 0 ? $per_page : 20 );
		}
		$rows = isset( $wp_query->post_count ) ? absint( $wp_query->post_count ) : 0;

		return $rows > 0 ? $rows : min( $scope_count, 100 );
	}

	/**
	 * Returns the current taxonomy term count.
	 *
	 * @param IC_AI_Target $target Taxonomy target.
	 * @return int
	 */
	private function taxonomy_scope_count( $target ) {
		if ( ! $target instanceof IC_AI_Target || 'taxonomy' !== $target->kind() ) {
			return 0;
		}
		$count = wp_count_terms(
			array(
				'taxonomy'   => $target->taxonomy(),
				'hide_empty' => false,
			)
		);
		return is_wp_error( $count ) ? 0 : absint( $count );
	}

	/**
	 * Returns the saved-preview count for one post type.
	 *
	 * @param string $post_type Post type.
	 *
	 * @return int
	 */
	private function saved_preview_count( $post_type ) {
		if ( $post_type instanceof IC_AI_Target && 'taxonomy' === $post_type->kind() ) {
			$count    = 0;
			$term_ids = get_terms(
				array(
					'taxonomy'   => $post_type->taxonomy(),
					'hide_empty' => false,
					'fields'     => 'ids',
				)
			);
			foreach ( is_wp_error( $term_ids ) ? array() : (array) $term_ids as $term_id ) {
				$saved = $this->target_saved_preview( $post_type, $term_id );
				if ( ! empty( $saved['fields'] ) || ! empty( $saved['questions'] ) ) {
					++$count; }
			}
			return $count;
		}
		if ( $post_type instanceof IC_AI_Target ) {
			$post_type = $post_type->post_type();
		}
		return count( $this->reviewable_saved_preview_post_ids( $post_type ) );
	}

	/**
	 * Returns the ordinary review count, excluding answered drafts.
	 *
	 * @param string $post_type Post type.
	 *
	 * @return int
	 */
	private function ordinary_review_count( $post_type ) {
		return count( $this->queued_review_post_ids( $post_type ) );
	}

	/**
	 * Returns the saved-preview count excluding session-skipped posts.
	 *
	 * @param string $post_type Post type.
	 *
	 * @return int
	 */
	private function pending_review_count( $post_type ) {
		return count( $this->queued_review_post_ids( $post_type, $this->review_skipped_ids( $post_type ) ) );
	}

	/**
	 * Returns ordinary review-queue IDs, excluding drafts with saved answers.
	 *
	 * @param string $post_type   Post type.
	 * @param int[]  $skipped_ids Session-skipped IDs to exclude.
	 *
	 * @return int[]
	 */
	private function queued_review_post_ids( $post_type, $skipped_ids = array() ) {
		$integration = $this->manager->integration( $post_type );
		if ( ! $integration ) {
			return array();
		}

		return array_values(
			array_filter(
				$this->reviewable_saved_preview_post_ids( $post_type, $skipped_ids ),
				function ( $post_id ) use ( $integration ) {
					return ! $this->is_awaiting_refine( $post_id, $integration );
				}
			)
		);
	}

	/**
	 * Returns accessible answered-draft IDs eligible for bulk refinement.
	 *
	 * @param string $post_type Post type.
	 *
	 * @return int[]
	 */
	private function awaiting_refine_post_ids( $post_type ) {
		$integration = $this->manager->integration( $post_type );
		if ( ! $integration ) {
			return array();
		}

		return array_values(
			array_filter(
				$this->reviewable_saved_preview_post_ids( $post_type ),
				function ( $post_id ) use ( $integration ) {
					return $this->is_awaiting_refine( $post_id, $integration );
				}
			)
		);
	}

	/**
	 * Returns the saved-preview post IDs the current user can review.
	 *
	 * @param string $post_type   Post type.
	 * @param int[]  $skipped_ids Session-skipped IDs to exclude.
	 *
	 * @return int[]
	 */
	private function reviewable_saved_preview_post_ids( $post_type, $skipped_ids = array() ) {
		$target = $this->normalize_review_target( $post_type );
		if ( $target instanceof IC_AI_Target && 'taxonomy' === $target->kind() ) {
			$terms       = get_terms(
				array(
					'taxonomy'   => $target->taxonomy(),
					'hide_empty' => false,
					'fields'     => 'ids',
					'number'     => 0,
					// phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_query -- This established query shape is required for the bounded lookup; changing storage, indexes, caching, or result semantics is outside this advisory boundary.
					'meta_query' => array(
						array(
							'key'     => self::SAVED_PREVIEW_META_KEY,
							'compare' => 'EXISTS',
						),
					),
				)
			);
			$ids         = is_wp_error( $terms ) ? array() : array_values( array_map( 'absint', (array) $terms ) );
			$skipped_ids = array_map( 'absint', (array) $skipped_ids );
			return array_values(
				array_filter(
					array_diff( $ids, $skipped_ids ),
					function ( $term_id ) use ( $target ) {
						$saved = $this->target_saved_preview( $target, $term_id );
						return $this->can_edit_target( $target, $term_id ) && ( ! empty( $saved['fields'] ) || ! empty( $saved['questions'] ) );
					}
				)
			);
		}
		$args = array(
			'post_type'           => sanitize_key( (string) $post_type ),
			'post_status'         => array_values( get_post_stati( array( 'show_in_admin_all_list' => true ), 'names' ) ),
			'posts_per_page'      => -1,
			'orderby'             => 'ID',
			'order'               => 'ASC',
			'fields'              => 'ids',
			'ignore_sticky_posts' => true,
			'no_found_rows'       => true,
			// phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_query -- Review queue is keyed by the saved-preview meta; EXISTS lookup is required.
			'meta_query'          => array(
				array(
					'key'     => self::SAVED_PREVIEW_META_KEY,
					'compare' => 'EXISTS',
				),
			),
		);
		if ( ! empty( $skipped_ids ) ) {
			// phpcs:ignore WordPressVIPMinimum.Performance.WPQueryParams.PostNotIn_post__not_in -- This established query shape is required for the bounded lookup; changing storage, indexes, caching, or result semantics is outside this advisory boundary.
			$args['post__not_in'] = $skipped_ids;
		}

		return array_values(
			array_filter(
				array_map( 'absint', get_posts( $args ) ),
				function ( $post_id ) use ( $target ) {
					$saved = $this->saved_preview_response( $post_id, $target->integration() );
					return $post_id && current_user_can( 'edit_post', $post_id ) && ( ! empty( $saved['fields'] ) || ! empty( $saved['questions'] ) );
				}
			)
		);
	}

	/**
	 * Returns the transient name storing the current user's review skip list.
	 *
	 * @param string $post_type Post type.
	 *
	 * @return string
	 */
	private function review_skips_transient_name( $post_type ) {
		return 'ic_ai_review_skips_' . sanitize_key( (string) $post_type ) . '_' . get_current_user_id();
	}

	/**
	 * Returns the current user's session-skipped review post IDs.
	 *
	 * @param string $post_type Post type.
	 *
	 * @return int[]
	 */
	private function review_skipped_ids( $post_type ) {
		$skips = get_transient( $this->review_skips_transient_name( $post_type ) );
		if ( ! is_array( $skips ) ) {
			return array();
		}

		return array_values( array_unique( array_filter( array_map( 'absint', $skips ) ) ) );
	}

	/**
	 * Adds one post to the current user's review skip list.
	 *
	 * @param string $post_type Post type.
	 * @param int    $post_id   Post ID.
	 *
	 * @return void
	 */
	private function add_review_skip( $post_type, $post_id ) {
		$skips   = $this->review_skipped_ids( $post_type );
		$skips[] = absint( $post_id );

		set_transient( $this->review_skips_transient_name( $post_type ), array_values( array_unique( array_filter( $skips ) ) ), HOUR_IN_SECONDS );
	}

	/**
	 * Clears the current user's review skip list.
	 *
	 * @param string $post_type Post type.
	 *
	 * @return void
	 */
	private function clear_review_skips( $post_type ) {
		delete_transient( $this->review_skips_transient_name( $post_type ) );
	}

	/**
	 * Returns one active task summary for the current user and integration.
	 *
	 * @param IC_AI_Integration $integration Integration.
	 * @param IC_AI_Target|null $target      Optional target.
	 *
	 * @return array|null
	 */
	private function active_task_summary( $integration, $target = null ) {
		$post_target = $target instanceof IC_AI_Target ? $target : $integration->target( 'post' );
		$target_key  = $post_target ? $post_target->key() : $integration->post_type();
		$state       = $this->resolve_target_active_task( $target_key );
		return ! empty( $state ) ? $this->task_summary( $state ) : null;
	}

	/**
	 * Returns the target-wide active task option name.
	 *
	 * @param string $target_key Target key.
	 *
	 * @return string
	 */
	private function target_active_task_option_name( $target_key ) {
		return 'ic_ai_list_active_target_' . sanitize_key( (string) $target_key );
	}

	/**
	 * Resolves and self-heals the target-wide active task pointer.
	 *
	 * @param IC_AI_Target|string $target_or_key Target object or key.
	 *
	 * @return array
	 */
	private function resolve_target_active_task( $target_or_key ) {
		$target = $this->normalize_review_target( $target_or_key );
		$key    = $target instanceof IC_AI_Target ? $target->key() : sanitize_text_field( (string) $target_or_key );
		if ( '' === $key ) {
			return array();
		}
		$name     = $this->target_active_task_option_name( $key );
		$task_id  = get_option( $name, '' );
		$state    = $task_id ? $this->normalize_task_state( $this->get_task_state( $task_id ) ) : array();
		$terminal = ! empty( $state ) && in_array( (string) ( $state['status'] ?? '' ), array( 'completed', 'failed', 'stopped' ), true );
		if ( empty( $state ) || empty( $state['id'] ) || empty( $state['target_key'] ) || $state['target_key'] !== $key || empty( $state['status'] ) || ( $terminal && empty( $state['pending_jobs'] ) ) ) {
			if ( $task_id && get_option( $name, '' ) === $task_id ) {
				delete_option( $name );
			}
			return array();
		}
		return $state;
	}

	/**
	 * Releases a terminal target pointer using compare-and-delete semantics.
	 *
	 * @param array $state Task state.
	 *
	 * @return bool Whether the pointer was deleted.
	 */
	private function maybe_release_target_active_task( $state ) {
		$state = $this->normalize_task_state( $state );
		if ( empty( $state['target_key'] ) || ! in_array( $state['status'], array( 'completed', 'failed', 'stopped' ), true ) || ! empty( $state['pending_jobs'] ) ) {
			return false;
		}
		$name = $this->target_active_task_option_name( $state['target_key'] );
		if ( get_option( $name, '' ) === $state['id'] ) {
			global $wpdb;
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Atomic compare-and-delete of this private pointer.
			$deleted = $wpdb->query( $wpdb->prepare( "DELETE FROM {$wpdb->options} WHERE option_name = %s AND option_value = %s", $name, $state['id'] ) );
			if ( ! empty( $state['post_type'] ) && ! empty( $state['user_id'] ) ) {
				delete_option( $this->active_task_option_name( $state['post_type'], (int) $state['user_id'] ) );
			}
			if ( false !== $deleted && $deleted > 0 ) {
				wp_cache_delete( $name, 'options' );
			}
			return false !== $deleted && $deleted > 0;
		}
		return false;
	}

	/**
	 * Determines whether a target has a review-blocking task.
	 *
	 * @param IC_AI_Target|false $target Target object.
	 *
	 * @return bool
	 */
	private function task_blocks_target_review( $target ) {
		$state = $this->resolve_target_active_task( $target );
		return ! empty( $state ) && ( ! in_array( (string) ( $state['status'] ?? '' ), array( 'completed', 'failed', 'stopped' ), true ) || ! empty( $state['pending_jobs'] ) );
	}

	/**
	 * Returns true when a public task summary describes an active refine task.
	 *
	 * @param array|null $task Task summary.
	 *
	 * @return bool
	 */
	private function is_active_refine_task( $task ) {
		if ( empty( $task ) || ! is_array( $task ) || empty( $task['operation'] ) || 'refine' !== $task['operation'] ) {
			return false;
		}

		return empty( $task['status'] ) || ! in_array( $task['status'], array( 'completed', 'failed' ), true );
	}

	/**
	 * Builds the primary top-button label.
	 *
	 * @param int   $count    Number of items the action would process.
	 * @param array $labels   Registered item labels with 'singular' and 'plural' keys.
	 * @param bool  $selected Whether the count comes from a checkbox selection.
	 *
	 * @return string
	 */
	private function build_primary_action_label( $count, $labels, $selected = false ) {
		$count = absint( $count );
		$label = 1 === $count ? $labels['singular'] : $labels['plural'];
		if ( $selected ) {
			/* translators: 1: formatted number of selected items, 2: registered item label (singular for one item, plural otherwise). */
			return sprintf( __( 'Enhance %1$s selected %2$s', 'post-type-x' ), number_format_i18n( $count ), $label );
		}

		/* translators: 1: formatted item count, 2: registered item label (singular for one item, plural otherwise). */
		return sprintf( __( 'Enhance %1$s %2$s', 'post-type-x' ), number_format_i18n( $count ), $label );
	}

	/**
	 * Returns complete selected-mode primary action labels keyed by selection count.
	 *
	 * @param int   $max    Largest selection count to prepare.
	 * @param array $labels Registered item labels with 'singular' and 'plural' keys.
	 *
	 * @return array<string, string>
	 */
	private function primary_action_label_map( $max, $labels ) {
		$map = array();
		$max = absint( $max );
		for ( $count = 1; $count <= $max; $count++ ) {
			$map[ (string) $count ] = $this->build_primary_action_label( $count, $labels, true );
		}

		return $map;
	}

	/**
	 * Returns the registered singular and plural item labels for a list target.
	 *
	 * Picks between registered labels; never derives a plural by appending "s".
	 *
	 * @param IC_AI_Integration $integration Integration.
	 * @param IC_AI_Target|null $taxonomy    Taxonomy target for term lists, null for post lists.
	 *
	 * @return array{singular: string, plural: string}
	 */
	private function item_labels( $integration, $taxonomy = null ) {
		if ( $taxonomy instanceof IC_AI_Target && 'taxonomy' === $taxonomy->kind() ) {
			$plural   = (string) $taxonomy->label();
			$singular = (string) $taxonomy->singular_label();
		} else {
			$plural   = $this->plural_label( $integration );
			$object   = get_post_type_object( $integration->post_type() );
			$singular = $object && ! empty( $object->labels->singular_name ) ? (string) $object->labels->singular_name : (string) $integration->label();
		}

		return array(
			'singular' => '' !== $singular ? $singular : $plural,
			'plural'   => $plural,
		);
	}

	/**
	 * Builds the review-button label.
	 *
	 * @param int $count Saved-draft count.
	 *
	 * @return string
	 */
	private function build_review_action_label( $count ) {
		$count = absint( $count );

		return sprintf(
			/* translators: %s: formatted number of suggestions awaiting review. */
			_n( 'Review Suggestion (%s)', 'Review Suggestions (%s)', $count, 'post-type-x' ),
			number_format_i18n( $count )
		);
	}

	/**
	 * Builds the answered taxonomy bulk action label.
	 *
	 * @param int $count Number of terms with saved answers.
	 *
	 * @return string
	 */
	private function taxonomy_refine_action_label( $count ) {
		$count = absint( $count );

		return sprintf(
			/* translators: %s: formatted number of categories with saved answers. */
			_n( 'Enhance %s category with answers', 'Enhance %s categories with answers', $count, 'post-type-x' ),
			number_format_i18n( $count )
		);
	}

	/**
	 * Builds the bulk review progress text.
	 *
	 * @param int $pending_count Products still waiting for review.
	 * @param int $skipped_count Products skipped in this review session.
	 *
	 * @return string
	 */
	private function review_remaining_text( $pending_count, $skipped_count ) {
		$pending_count = absint( $pending_count );
		$skipped_count = absint( $skipped_count );
		$text          = sprintf(
			/* translators: %s: formatted number of products whose suggestions still wait for review. */
			_n( '%s product remaining.', '%s products remaining.', $pending_count, 'post-type-x' ),
			number_format_i18n( $pending_count )
		);
		if ( $skipped_count > 0 ) {
			$text .= ' ' . sprintf(
				/* translators: %s: formatted number of products skipped during review. */
				_n( '%s product skipped.', '%s products skipped.', $skipped_count, 'post-type-x' ),
				number_format_i18n( $skipped_count )
			);
		}

		return $text;
	}

	/**
	 * Returns the localized AI Credit usage text for one processing action.
	 *
	 * One AI Credit is used for every processed item, so the count equals the
	 * number of items the action submits.
	 *
	 * @param int $count Number of items the action processes.
	 *
	 * @return string
	 */
	private function credit_usage_text( $count ) {
		$count = absint( $count );

		return sprintf(
			/* translators: %s: formatted number of AI Credits used; one AI Credit is used for each processed item. */
			_n( 'Uses %s AI Credit', 'Uses %s AI Credits', $count, 'post-type-x' ),
			number_format_i18n( $count )
		);
	}

	/**
	 * Returns localized AI Credit usage labels keyed by selection count.
	 *
	 * Lets the list script show an exact, locale-correct label for any checkbox
	 * selection without building plural forms in JavaScript.
	 *
	 * @param int $max Largest selection count to prepare.
	 *
	 * @return array<string, string>
	 */
	private function credit_usage_label_map( $max ) {
		$labels = array();
		$max    = absint( $max );
		for ( $count = 1; $count <= $max; $count++ ) {
			$labels[ (string) $count ] = $this->credit_usage_text( $count );
		}

		return $labels;
	}

	/**
	 * Returns the decorative single-token AI Credit icon.
	 *
	 * @return string
	 */
	private function credit_usage_icon() {
		return '<span class="dashicons dashicons-tickets-alt" aria-hidden="true"></span>';
	}

	/**
	 * Returns the non-interactive AI Credit usage indicator shown beside an action.
	 *
	 * @param int $count Number of items the action processes.
	 *
	 * @return string
	 */
	private function credit_usage_indicator( $count ) {
		return '<span class="ic-ai-credit-usage">' . $this->credit_usage_icon() . esc_html( $this->credit_usage_text( $count ) ) . '</span>';
	}

	/**
	 * Renders the saved-preview form HTML for list or review screens.
	 *
	 * @param int               $post_id      Post ID.
	 * @param IC_AI_Integration $integration  Integration.
	 * @param string            $context      Render context.
	 *
	 * @return string
	 */
	private function render_saved_preview_form( $post_id, $integration, $context ) {
		$target = $integration->target( 'post' );
		$saved  = $target instanceof IC_AI_Target ? $this->target_saved_preview( $target, $post_id ) : array();
		if ( empty( $saved['fields'] ) ) {
			return '<p class="description">' . esc_html__( 'No suggestion is available for this item.', 'post-type-x' ) . '</p>';
		}

		$field_preferences = $target instanceof IC_AI_Target ? $this->apply_field_preferences( $target->key() ) : array();

		$post_target = $integration->target( 'post' );
		$html        = '<form class="ic-ai-saved-preview-form" data-post-id="' . esc_attr( (string) $post_id ) . '" data-post-type="' . esc_attr( $integration->post_type() ) . '" data-target-key="' . esc_attr( $post_target ? $post_target->key() : '' ) . '" data-context="' . esc_attr( $context ) . '">';
		foreach ( $saved['fields'] as $field_key => $value ) {
			$field = $this->preview_field_definition( $integration, $field_key );
			if ( empty( $field ) ) {
				continue;
			}

			$checked = ! isset( $field_preferences[ $field_key ] ) || ! empty( $field_preferences[ $field_key ] );

			$is_rich = $this->is_rich_text_signature_field( $field );

			$html .= '<div class="ic-ai-preview-field" data-field-key="' . esc_attr( $field_key ) . '"' . ( $is_rich ? ' data-rich="1"' : '' ) . '>';
			$html .= '<label><input type="checkbox" class="ic-ai-apply-toggle"' . ( $checked ? ' checked="checked"' : '' ) . ' /> ' . esc_html( isset( $field['label'] ) ? $field['label'] : $field_key );
			$html .= ' <button type="button" class="button-link ic-ai-field-edit" aria-label="' . esc_attr__( 'Edit', 'post-type-x' ) . '"><span class="dashicons dashicons-edit"></span></button>';
			$html .= ' <button type="button" class="button-link ic-ai-field-done ic-ai-hidden" aria-label="' . esc_attr__( 'Done editing', 'post-type-x' ) . '"><span class="dashicons dashicons-yes"></span></button></label>';
			$html .= '<div class="ic-ai-preview-display">' . $this->render_preview_field_display( $value, $field ) . '</div>';
			$html .= '<textarea class="widefat ic-ai-preview-value ic-ai-hidden" rows="4">' . esc_textarea( $this->preview_field_text( $value, $field ) ) . '</textarea>';
			$html .= '</div>';
		}
		$html .= '<p><button type="button" class="button button-primary ic-ai-apply-saved-preview">' . esc_html__( 'Apply selected changes', 'post-type-x' ) . '</button></p>';
		$html .= '<div class="ic-ai-preview-feedback" aria-live="polite"></div>';
		$html .= '</form>';

		return $html;
	}

	/**
	 * Renders the formatted read-only preview block for one saved field.
	 *
	 * @param mixed $value Preview value.
	 * @param array $field Field definition.
	 *
	 * @return string
	 */
	private function render_preview_field_display( $value, $field ) {
		if ( $this->is_rich_text_signature_field( $field ) ) {
			return wp_kses_post( (string) $value );
		}
		if ( ! empty( $field['type'] ) && 'taxonomy' === $field['type'] ) {
			$lines = array_filter( array_map( 'trim', explode( "\n", $this->preview_field_text( $value, $field ) ) ) );
			if ( empty( $lines ) ) {
				return '';
			}
			$items = '';
			foreach ( $lines as $line ) {
				$items .= '<li>' . esc_html( $line ) . '</li>';
			}

			return '<ul>' . $items . '</ul>';
		}

		return nl2br( esc_html( $this->preview_field_text( $value, $field ) ) );
	}

	/**
	 * Returns the current user's saved apply-field checkbox preferences.
	 *
	 * Absent field keys default to checked.
	 *
	 * @param string $target_key Target key.
	 *
	 * @return array
	 */
	private function apply_field_preferences( $target_key ) {
		$all = get_user_meta( get_current_user_id(), 'ic_ai_apply_fields', true );
		if ( is_array( $all ) && isset( $all[ $target_key ] ) && is_array( $all[ $target_key ] ) ) {
			return $all[ $target_key ];
		}
		return array();
	}

	/**
	 * Returns the text shown in one preview textarea.
	 *
	 * @param mixed $value Preview value.
	 * @param array $field Field definition.
	 *
	 * @return string
	 */
	private function preview_field_text( $value, $field ) {
		if ( ! empty( $field['type'] ) && 'taxonomy' === $field['type'] ) {
			$lines = array();
			$paths = is_array( $value ) ? $value : array();
			foreach ( $paths as $path ) {
				$lines[] = is_array( $path ) ? implode( ' > ', array_map( 'sanitize_text_field', $path ) ) : sanitize_text_field( (string) $path );
			}

			return implode( "\n", array_filter( $lines ) );
		}
		if ( is_array( $value ) ) {
			return wp_json_encode( $value, JSON_PRETTY_PRINT );
		}

		return (string) $value;
	}

	/**
	 * Records one AI statistics event through the shared safe recorder.
	 *
	 * @param string $event   Allowlisted event key.
	 * @param array  $context Scalar-only, content-free context.
	 *
	 * @return void
	 */
	private function record_stats_event( $event, $context = array() ) {
		if ( function_exists( 'ic_ai_stats_record_event' ) ) {
			ic_ai_stats_record_event( $event, $context ); }
	}

	/**
	 * Handles preview AJAX requests.
	 *
	 * @return void
	 */
	public function ajax_preview() {
		check_ajax_referer( 'ic-ai-admin', 'nonce' );
		$target      = $this->request_target();
		$object_id   = isset( $_POST['object_id'] ) ? absint( wp_unslash( $_POST['object_id'] ) ) : 0;
		$integration = $target instanceof IC_AI_Target ? $target->integration() : false;
		if ( ! $target || ! $integration || ! $this->request_target_object( $target, $object_id ) ) {
			wp_send_json_error( array( 'message' => __( 'Unknown AI integration.', 'post-type-x' ) ), 400 );
		}
		if ( ! $this->can_edit_target( $target, $object_id ) ) {
			wp_send_json_error( array( 'message' => __( 'You cannot edit this post.', 'post-type-x' ) ), 403 );
		}
		$site_settings = $this->manager->client()->site_settings();
		$readiness     = $this->manager->client()->enhancement_readiness( $target );
		if ( is_wp_error( $readiness ) ) {
			wp_send_json_error( $this->enhancement_error_payload( $integration, $readiness, $site_settings, $target ), 409 );
		}
		// phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- Query-string payload is parsed and normalized before use.
		$form_data = isset( $_POST['form_data'] ) ? (string) wp_unslash( $_POST['form_data'] ) : '';
		$job_id    = isset( $_POST['job_id'] ) ? sanitize_text_field( wp_unslash( $_POST['job_id'] ) ) : '';
		$parsed    = array();
		wp_parse_str( $form_data, $parsed );
		$payload = 'taxonomy' === $target->kind() ? $this->collect_target_term_payload( $target, $object_id, $parsed ) : $this->collect_payload( $integration, $object_id, $parsed );
		$context = 'taxonomy' === $target->kind() ? array_merge( $this->collect_context_payload( $target, $object_id, $parsed ), is_callable( $target->context_callback() ) ? (array) call_user_func( $target->context_callback(), $object_id, $target ) : array() ) : $this->collect_context_payload( $integration, $object_id, $parsed );
		$editor_type = 'post' === $target->kind() ? $this->request_editor_type() : '';
		$result      = $this->manager->client()->request_enhancement( $target, $payload, $context, $job_id, 1, false, '', array(), false, $editor_type );
		if ( is_wp_error( $result ) ) {
			$error_payload = $this->enhancement_error_payload( $integration, $result, $site_settings, $target );
			$status        = ! empty( $error_payload['license_flow'] ) ? 409 : 400;
			if ( ! empty( $error_payload['queueable'] ) ) {
				$status = 429;
			}
			wp_send_json_error( $error_payload, $status );
		}
		$this->save_target_preview( $target, $object_id, $result );
		$this->record_stats_event(
			'ai_preview',
			array(
				'integration' => (string) $target->plugin_slug(),
				'target'      => (string) $target->key(),
				'object'      => 'taxonomy' === $target->kind() ? 'term' : 'post',
				'object_id'   => $object_id,
			)
		);

		wp_send_json_success(
			array(
				'fields'               => isset( $result['fields'] ) && is_array( $result['fields'] ) ? $result['fields'] : array(),
				'meta'                 => isset( $result['meta'] ) && is_array( $result['meta'] ) ? $result['meta'] : array(),
				'questions'            => isset( $result['questions'] ) && is_array( $result['questions'] ) ? $this->normalize_preview_questions( $result['questions'] ) : array(),
				'plans'                => isset( $result['plans'] ) ? $result['plans'] : array(),
				'quota'                => isset( $result['quota'] ) ? $result['quota'] : array(),
				'active_plan'          => isset( $result['active_plan'] ) ? $result['active_plan'] : array(),
				'upgrade_url'          => isset( $result['upgrade_url'] ) ? $result['upgrade_url'] : '',
				'buy_enhancements_url' => isset( $result['buy_enhancements_url'] ) ? $result['buy_enhancements_url'] : '',
			)
		);
	}

	/**
	 * Handles the clarifying-question answers submission and runs one refine call.
	 *
	 * @return void
	 */
	public function ajax_submit_answers() {
		check_ajax_referer( 'ic-ai-admin', 'nonce' );
		$target         = $this->request_target();
		$context_screen = isset( $_POST['context'] ) ? sanitize_key( wp_unslash( $_POST['context'] ) ) : '';
		if ( 'review' === $context_screen ) {
			$this->reject_locked_review_action( $target ); }
		$post_id     = isset( $_POST['object_id'] ) ? absint( wp_unslash( $_POST['object_id'] ) ) : 0;
		$integration = $target instanceof IC_AI_Target ? $target->integration() : false;
		if ( ! $target || ! $integration || ! $this->request_target_object( $target, $post_id ) ) {
			wp_send_json_error( array( 'message' => __( 'Unknown AI integration.', 'post-type-x' ) ), 400 );
		}
		if ( ! $this->can_edit_target( $target, $post_id ) ) {
			wp_send_json_error( array( 'message' => __( 'You cannot edit this post.', 'post-type-x' ) ), 403 );
		}
		$site_settings = $this->manager->client()->site_settings();
		$readiness     = $this->manager->client()->enhancement_readiness( $target );
		if ( is_wp_error( $readiness ) ) {
			wp_send_json_error( $this->enhancement_error_payload( $integration, $readiness, $site_settings, $target ), 409 );
		}
		// phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- Query-string payload is parsed and normalized before use.
		$form_data = isset( $_POST['form_data'] ) ? (string) wp_unslash( $_POST['form_data'] ) : '';
		$job_id    = isset( $_POST['job_id'] ) ? sanitize_text_field( wp_unslash( $_POST['job_id'] ) ) : '';
		$parsed    = array();
		wp_parse_str( $form_data, $parsed );
		$payload = 'taxonomy' === $target->kind() ? $this->collect_target_term_payload( $target, $post_id, $parsed ) : $this->collect_payload( $integration, $post_id, $parsed );
		if ( 'review' === $context_screen && $post_id > 0 ) {
			// On review screens the refine base must be exactly what the reviewer is
			// currently looking at: the saved AI draft PLUS any in-progress edits they
			// made in the review form before refining. The client submits those current
			// field values as review_fields, so prefer them. Fall back to the last saved
			// draft when they are absent, and to the live-post payload only when neither
			// exists (the review flow inits with an empty form_data, so collect_payload()
			// would otherwise regenerate from get_post()/post meta and wipe the draft).
			$review_fields = $this->collect_review_field_values( $target );
			if ( ! empty( $review_fields ) ) {
				$payload = $review_fields;
			} else {
				$saved_draft = 'taxonomy' === $target->kind() ? $this->target_saved_preview( $target, $post_id ) : $this->saved_preview_response( $post_id, $integration );
				if ( ! empty( $saved_draft['fields'] ) && is_array( $saved_draft['fields'] ) ) {
					$payload = $saved_draft['fields'];
				}
			}
		}
		$context    = 'taxonomy' === $target->kind() ? array_merge( $this->collect_context_payload( $target, $post_id, $parsed ), is_callable( $target->context_callback() ) ? (array) call_user_func( $target->context_callback(), $post_id, $target ) : array() ) : $this->collect_context_payload( $integration, $post_id, $parsed );
		$qa_context = $this->collect_submitted_answers();
		if ( empty( $qa_context ) ) {
			wp_send_json_error( array( 'message' => __( 'No answers were provided.', 'post-type-x' ) ), 400 );
		}
		// A queued/202 refine returns a job_id; polling with it resumes the same job the way
		// ajax_preview() does, so a rate-limited refine finishes instead of stalling.
		$editor_type = 'review' !== $context_screen && 'post' === $target->kind() ? $this->request_editor_type() : '';
		$result      = $this->manager->client()->request_enhancement( $target, $payload, $context, $job_id, 1, false, '', $qa_context, true, $editor_type );
		if ( is_wp_error( $result ) ) {
			$error_payload = $this->enhancement_error_payload( $integration, $result, $site_settings, $target );
			$status        = ! empty( $error_payload['license_flow'] ) ? 409 : 400;
			if ( ! empty( $error_payload['queueable'] ) ) {
				$status = 429;
			}
			wp_send_json_error( $error_payload, $status );
		}
		if ( 'taxonomy' === $target->kind() ) {
			$saved                = $this->target_saved_preview( $target, $post_id );
			$result['qa_answers'] = array();
			$this->save_target_preview( $target, $post_id, $result );
		} elseif ( $post_id ) {
			$this->save_preview_response( $post_id, $integration, $result );
		}

		wp_send_json_success(
			array(
				'fields'               => isset( $result['fields'] ) && is_array( $result['fields'] ) ? $result['fields'] : array(),
				'meta'                 => isset( $result['meta'] ) && is_array( $result['meta'] ) ? $result['meta'] : array(),
				'questions'            => isset( $result['questions'] ) && is_array( $result['questions'] ) ? $this->normalize_preview_questions( $result['questions'] ) : array(),
				'plans'                => isset( $result['plans'] ) ? $result['plans'] : array(),
				'quota'                => isset( $result['quota'] ) ? $result['quota'] : array(),
				'active_plan'          => isset( $result['active_plan'] ) ? $result['active_plan'] : array(),
				'upgrade_url'          => isset( $result['upgrade_url'] ) ? $result['upgrade_url'] : '',
				'buy_enhancements_url' => isset( $result['buy_enhancements_url'] ) ? $result['buy_enhancements_url'] : '',
			)
		);
	}

	/**
	 * Saves review answers for later bulk refinement without contacting AI.
	 *
	 * @return void
	 */
	public function ajax_save_review_answers() {
		check_ajax_referer( 'ic-ai-admin', 'nonce' );
		$target = $this->request_target();
		$this->reject_locked_review_action( $target );
		$post_id     = isset( $_POST['object_id'] ) ? absint( wp_unslash( $_POST['object_id'] ) ) : 0;
		$integration = $target instanceof IC_AI_Target ? $target->integration() : false;
		if ( ! $target || ! $integration || ! $target->list_enhance_enabled() || ! $this->request_target_object( $target, $post_id ) ) {
			wp_send_json_error( array( 'message' => __( 'Unknown AI integration.', 'post-type-x' ) ), 400 );
		}
		if ( ! $this->manager->client()->is_enabled( $target ) ) {
			wp_send_json_error(
				array(
					'code'    => 'ic_ai_not_ready',
					'message' => __( 'AI is not enabled for this target.', 'post-type-x' ),
				),
				409
			);
		}
		if ( ! $this->can_edit_target( $target, $post_id ) ) {
			wp_send_json_error( array( 'message' => __( 'You cannot edit this post.', 'post-type-x' ) ), 403 );
		}

		$saved = 'taxonomy' === $target->kind() ? $this->target_saved_preview( $target, $post_id ) : $this->saved_preview_response( $post_id, $integration );
		if ( empty( $saved['questions'] ) || ! is_array( $saved['questions'] ) ) {
			wp_send_json_error( array( 'message' => __( 'This suggestion no longer has questions to answer.', 'post-type-x' ) ), 400 );
		}

		$answers = $this->submitted_answer_map( $saved['questions'] );
		if ( empty( $answers ) ) {
			wp_send_json_error( array( 'message' => __( 'No answers were provided.', 'post-type-x' ) ), 400 );
		}

		$review_fields = $this->collect_review_field_values( $target );
		if ( ! empty( $review_fields ) ) {
			$saved['fields'] = $review_fields;
		}
		$saved['qa_answers'] = $answers;
		if ( 'taxonomy' === $target->kind() ) {
			$this->save_target_preview( $target, $post_id, $saved );
		} else {
			$this->save_preview_response( $post_id, $integration, $saved );
		}
		$post_type = 'taxonomy' === $target->kind() ? $target : $integration->post_type();

		wp_send_json_success(
			array(
				'post_id'        => $post_id,
				'review_count'   => $this->saved_preview_count( $post_type ),
				'pending_count'  => 'taxonomy' === $target->kind() ? count( $this->queued_taxonomy_review_ids( $target ) ) : $this->pending_review_count( $post_type ),
				'awaiting_count' => 'taxonomy' === $target->kind() ? count( $this->awaiting_taxonomy_review_ids( $target ) ) : count( $this->awaiting_refine_post_ids( $post_type ) ),
				'next_url'       => $this->review_page_url( $integration, $target ),
				'message'        => __( 'Answers saved.', 'post-type-x' ),
			)
		);
	}

	/**
	 * Returns valid submitted answers keyed by current saved question ID.
	 *
	 * @param array $questions Current normalized saved questions.
	 *
	 * @return array
	 */
	private function submitted_answer_map( $questions ) {
		$allowed = array();
		foreach ( (array) $questions as $question ) {
			if ( ! empty( $question['id'] ) ) {
				$allowed[ (string) $question['id'] ] = true;
			}
		}

		// phpcs:ignore WordPress.Security.NonceVerification.Missing -- Nonce is verified by the calling AJAX handler.
		if ( empty( $_POST['answers'] ) || ! is_array( $_POST['answers'] ) ) {
			return array();
		}

		// phpcs:ignore WordPress.Security.NonceVerification.Missing, WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- Each answer is validated and sanitized below.
		$raw_answers = wp_unslash( $_POST['answers'] );
		$answers     = array();
		foreach ( $raw_answers as $entry ) {
			if ( ! is_array( $entry ) ) {
				continue;
			}
			$id     = isset( $entry['id'] ) ? sanitize_text_field( (string) $entry['id'] ) : '';
			$answer = isset( $entry['answer'] ) ? sanitize_text_field( (string) $entry['answer'] ) : '';
			if ( '' === $id || '' === $answer || empty( $allowed[ $id ] ) ) {
				continue;
			}
			$answers[ $id ] = $answer;
		}

		return $answers;
	}

	/**
	 * Reads the submitted clarifying-question answers into refine qa_context entries.
	 *
	 * @return array
	 */
	private function collect_submitted_answers() {
		// phpcs:ignore WordPress.Security.NonceVerification.Missing -- Nonce is verified by the calling AJAX handler.
		if ( empty( $_POST['answers'] ) || ! is_array( $_POST['answers'] ) ) {
			return array();
		}

		// phpcs:ignore WordPress.Security.NonceVerification.Missing, WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- Nonce is verified by the calling AJAX handler; each element is sanitized with sanitize_text_field() in the loop below.
		$raw_answers = wp_unslash( $_POST['answers'] );
		$qa_context  = array();
		foreach ( $raw_answers as $entry ) {
			if ( ! is_array( $entry ) ) {
				continue;
			}
			$answer = isset( $entry['answer'] ) ? sanitize_text_field( (string) $entry['answer'] ) : '';
			if ( '' === $answer ) {
				continue;
			}

			$qa_context[] = array(
				'id'       => isset( $entry['id'] ) ? sanitize_text_field( (string) $entry['id'] ) : '',
				'question' => isset( $entry['question'] ) ? sanitize_text_field( (string) $entry['question'] ) : '',
				'answer'   => $answer,
			);
		}

		return $qa_context;
	}

	/**
	 * Reads the CURRENT review-draft field values submitted with a refine request.
	 *
	 * On review screens the client posts the field values currently shown in the
	 * review draft form (the saved draft plus any unsaved reviewer edits) keyed by
	 * field key. Only keys in the enhance field map are kept, and every value is
	 * sanitized the same way saved-draft field values are, so the refine bases its
	 * payload on exactly what the reviewer sees without trusting raw input.
	 *
	 * @param IC_AI_Target|IC_AI_Integration $target_or_integration Target or integration.
	 *
	 * @return array
	 */
	private function collect_review_field_values( $target_or_integration ) {
		// phpcs:ignore WordPress.Security.NonceVerification.Missing -- Nonce is verified by the calling AJAX handler.
		if ( empty( $_POST['review_fields'] ) || ! is_array( $_POST['review_fields'] ) ) {
			return array();
		}

		// phpcs:ignore WordPress.Security.NonceVerification.Missing, WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- Nonce is verified by the calling AJAX handler; each element is sanitized via sanitize_review_field_value()/normalize_preview_value() in the loop below.
		$raw_fields = wp_unslash( $_POST['review_fields'] );
		$target     = $target_or_integration instanceof IC_AI_Target ? $target_or_integration : $target_or_integration->target( 'post' );
		$allowed    = array_merge(
			$this->manager->client()->enabled_field_map( $target ),
			$this->manager->client()->custom_meta_field_map( $target, 'enhance' )
		);
		$fields     = array();
		foreach ( $raw_fields as $field_key => $value ) {
			if ( ! array_key_exists( $field_key, $allowed ) ) {
				continue;
			}

			$fields[ $field_key ] = $this->normalize_preview_value( $this->sanitize_review_field_value( $value ) );
		}

		return $fields;
	}

	/**
	 * Recursively sanitizes one submitted review-draft field value.
	 *
	 * Editor fields may contain post HTML, so scalars pass through wp_kses_post
	 * rather than a flat text sanitizer; group fields arrive as nested arrays.
	 *
	 * @param mixed $value Raw submitted value.
	 *
	 * @return mixed
	 */
	private function sanitize_review_field_value( $value ) {
		if ( is_array( $value ) ) {
			$sanitized = array();
			foreach ( $value as $item_key => $item_value ) {
				$sanitized[ $item_key ] = $this->sanitize_review_field_value( $item_value );
			}

			return $sanitized;
		}

		return wp_kses_post( (string) $value );
	}

	/**
	 * Captures one enhancement signature before a manual post update.
	 *
	 * @param int   $post_id Post ID.
	 * @param array $data    Raw post update data.
	 *
	 * @return void
	 */
	public function capture_pre_save_signature( $post_id, $data = array() ) {
		unset( $data );

		$post_id = absint( $post_id );
		if ( ! $post_id || wp_is_post_revision( $post_id ) || wp_is_post_autosave( $post_id ) || $this->is_managed_write( $post_id ) ) {
			return;
		}

		$post_type   = get_post_type( $post_id );
		$integration = $post_type ? $this->manager->integration( $post_type ) : false;
		if ( ! $integration || ! get_post_meta( $post_id, self::ENHANCED_META_KEY, true ) ) {
			return;
		}

		$this->pre_save_signatures[ $post_id ] = $this->current_enhancement_signature( $post_id, $integration );
	}

	/**
	 * Clears one saved AI preview payload after the post is persisted.
	 *
	 * @param int          $post_id     Post ID.
	 * @param WP_Post      $post        Post object.
	 * @param bool         $update      Whether the post was updated.
	 * @param WP_Post|null $post_before Post object before update.
	 *
	 * @return void
	 */
	public function clear_saved_preview_on_save( $post_id, $post, $update = true, $post_before = null ) {
		unset( $post_before );

		if ( ! $post instanceof WP_Post || empty( $post->post_type ) ) {
			return;
		}
		if ( wp_is_post_revision( $post_id ) || wp_is_post_autosave( $post_id ) ) {
			return;
		}
		if ( ! $this->manager->integration( $post->post_type ) ) {
			return;
		}
		if ( $this->is_managed_write( $post_id ) ) {
			return;
		}

		$integration = $this->manager->integration( $post->post_type );
		$this->delete_saved_preview_response( $post_id );
		$this->maybe_invalidate_enhanced_state( $post_id, $integration, $update );
	}

	/**
	 * Builds one editor-facing error payload for failed AI enhancement requests.
	 *
	 * @param IC_AI_Integration $integration   Integration.
	 * @param WP_Error          $error         Request error.
	 * @param array             $site_settings Saved site settings.
	 * @param IC_AI_Target|null $target        Optional target.
	 *
	 * @return array
	 */
	private function enhancement_error_payload( $integration, $error, $site_settings, $target = null ) {
		$target = $target instanceof IC_AI_Target ? $target : ( is_object( $integration ) && method_exists( $integration, 'target' ) ? $integration->target( 'post' ) : false );
		if ( ! $this->can_manage_target_settings( $target ) ) {
			return array(
				'message' => $error->get_error_message(),
				'code'    => $error->get_error_code(),
				'data'    => $error->get_error_data(),
			);
		}

		$field_error = $this->manager->client()->enhancement_license_key_field_error( $error, $site_settings );

		if ( ! empty( $field_error['field'] ) && ! empty( $field_error['message'] ) ) {
			return array(
				'message'             => $field_error['message'],
				'code'                => ! empty( $field_error['code'] ) ? $field_error['code'] : $error->get_error_code(),
				'license_flow'        => true,
				'recovery_type'       => 'license_key',
				'settings_url'        => $this->manager->settings()->settings_url( $integration, $target ),
				'action_settings_url' => $this->recovery_settings_url( $integration, $field_error, $target ),
				'action_label'        => ! empty( $field_error['action_label'] ) ? $field_error['action_label'] : '',
			);
		}
		$billing_actions = $this->billing_recovery_actions( $integration, $error, $site_settings );
		if ( ! empty( $billing_actions ) ) {
			return array_merge(
				array(
					'message'                => $error->get_error_message(),
					'code'                   => $error->get_error_code(),
					'license_flow'           => true,
					'settings_url'           => $this->manager->settings()->settings_url( $integration, $target ),
					'upgrade_label'          => __( 'Upgrade Plan', 'post-type-x' ),
					'buy_enhancements_label' => __( 'Buy AI Credits', 'post-type-x' ),
				),
				$billing_actions
			);
		}

		$error_data = $error->get_error_data();
		if ( ! empty( $error_data['license_flow'] ) ) {
			return array(
				'message'       => $error->get_error_message(),
				'code'          => $error->get_error_code(),
				'license_flow'  => true,
				'recovery_type' => ! empty( $error_data['recovery_type'] ) ? sanitize_key( $error_data['recovery_type'] ) : '',
				'settings_url'  => $this->manager->settings()->settings_url( $integration, $target ),
				'plans'         => ! empty( $error_data['plans'] ) && is_array( $error_data['plans'] ) ? array_values( $error_data['plans'] ) : array(),
			);
		}
		if ( ! empty( $error_data['status'] ) && 429 === absint( $error_data['status'] ) && ! empty( $error_data['body']['queueable'] ) ) {
			$body             = $error_data['body'];
			$queueable_action = $this->queueable_window_rate_actions( $integration, $body, $site_settings );

			return array(
				'message'              => $error->get_error_message(),
				'code'                 => $error->get_error_code(),
				'queueable'            => true,
				'job_id'               => ! empty( $body['job_id'] ) ? sanitize_text_field( $body['job_id'] ) : '',
				'job_status'           => ! empty( $body['job_status'] ) ? sanitize_key( $body['job_status'] ) : '',
				'retry_after'          => ! empty( $body['retry_after'] ) ? absint( $body['retry_after'] ) : 1,
				'retry_after_ms'       => ! empty( $body['retry_after_ms'] ) ? absint( $body['retry_after_ms'] ) : 1000,
				'limit_kind'           => ! empty( $body['limit_kind'] ) ? sanitize_key( $body['limit_kind'] ) : '',
				'limit_scope'          => ! empty( $body['limit_scope'] ) ? sanitize_key( $body['limit_scope'] ) : '',
				'settings_url'         => $this->manager->settings()->settings_url( $integration ),
				'active_plan'          => ! empty( $body['active_plan'] ) && is_array( $body['active_plan'] ) ? $body['active_plan'] : array(),
				'quota'                => ! empty( $body['quota'] ) && is_array( $body['quota'] ) ? $body['quota'] : array(),
				'plans'                => ! empty( $body['plans'] ) && is_array( $body['plans'] ) ? $body['plans'] : array(),
				'enhancement_packs'    => ! empty( $body['enhancement_packs'] ) && is_array( $body['enhancement_packs'] ) ? $body['enhancement_packs'] : array(),
				'upgrade_url'          => ! empty( $queueable_action['upgrade_url'] ) ? esc_url_raw( $queueable_action['upgrade_url'] ) : '',
				'upgrade_label'        => ! empty( $queueable_action['upgrade_label'] ) ? sanitize_text_field( $queueable_action['upgrade_label'] ) : '',
				'buy_enhancements_url' => '',
			);
		}

		return array(
			'message' => $error->get_error_message(),
			'code'    => $error->get_error_code(),
			'data'    => $error->get_error_data(),
		);
	}

	/**
	 * Builds one recovery AI settings URL for the editor metabox.
	 *
	 * @param IC_AI_Integration $integration Integration.
	 * @param array             $field_error Field-error payload.
	 * @param IC_AI_Target|null $target      Optional target.
	 *
	 * @return string
	 */
	private function recovery_settings_url( $integration, $field_error, $target = null ) {
		if ( empty( $field_error['action_url'] ) ) {
			return '';
		}

		$mode = 'upgrade';
		$url  = wp_parse_url( $field_error['action_url'] );
		if ( ! empty( $url['query'] ) ) {
			parse_str( $url['query'], $query );
			if ( ! empty( $query['ic-ai-billing'] ) ) {
				$requested_mode = sanitize_key( $query['ic-ai-billing'] );
				if ( in_array( $requested_mode, array( 'upgrade', 'enhancements' ), true ) ) {
					$mode = $requested_mode;
				}
			}
		}

		return add_query_arg(
			array(
				'ic_ai_open_billing'   => $mode,
				'ic_ai_billing_public' => 1,
				'ic_ai_source'         => 'editor',
			),
			$this->manager->settings()->settings_url( $integration, $target )
		);
	}

	/**
	 * Returns editor billing recovery actions for over-quota enhancement errors.
	 *
	 * @param IC_AI_Integration $integration   Integration.
	 * @param WP_Error          $error         Enhancement error.
	 * @param array             $site_settings Saved site settings.
	 *
	 * @return array
	 */
	private function billing_recovery_actions( $integration, $error, $site_settings ) {
		if ( ! is_wp_error( $error ) ) {
			return array();
		}

		$error_data = $error->get_error_data();
		$status     = ! empty( $error_data['status'] ) ? absint( $error_data['status'] ) : 0;
		if ( 402 !== $status ) {
			return array();
		}

		$body        = ! empty( $error_data['body'] ) && is_array( $error_data['body'] ) ? $error_data['body'] : array();
		$upgrade_url = ! empty( $body['upgrade_url'] ) ? esc_url_raw( $body['upgrade_url'] ) : '';
		if ( empty( $upgrade_url ) && ! empty( $site_settings['upgrade_url'] ) ) {
			$upgrade_url = esc_url_raw( $site_settings['upgrade_url'] );
		}
		$buy_enhancements_url = ! empty( $body['buy_enhancements_url'] ) ? esc_url_raw( $body['buy_enhancements_url'] ) : '';
		if ( empty( $buy_enhancements_url ) && ! empty( $site_settings['buy_enhancements_url'] ) ) {
			$buy_enhancements_url = esc_url_raw( $site_settings['buy_enhancements_url'] );
		}

		$actions = array();
		if ( ! empty( $upgrade_url ) ) {
			$actions['upgrade_url'] = $this->billing_settings_url( $integration, 'upgrade' );
		}
		if ( ! empty( $buy_enhancements_url ) ) {
			$actions['buy_enhancements_url'] = $this->billing_settings_url( $integration, 'enhancements' );
		}

		return $actions;
	}

	/**
	 * Returns one local upgrade action for request-window queueable errors.
	 *
	 * @param IC_AI_Integration $integration   Integration.
	 * @param array             $body          Queueable error body.
	 * @param array             $site_settings Saved site settings.
	 *
	 * @return array
	 */
	private function queueable_window_rate_actions( $integration, $body, $site_settings = array() ) {
		$body = is_array( $body ) ? $body : array();
		if ( 'window_rate' !== sanitize_key( $body['limit_kind'] ?? '' ) ) {
			return array();
		}

		$upgrade_url = ! empty( $body['upgrade_url'] ) ? esc_url_raw( $body['upgrade_url'] ) : '';
		if ( empty( $upgrade_url ) && ! empty( $site_settings['upgrade_url'] ) ) {
			$upgrade_url = esc_url_raw( $site_settings['upgrade_url'] );
		}
		if ( empty( $upgrade_url ) ) {
			return array();
		}

		return array(
			'upgrade_url'   => $this->billing_settings_url( $integration, 'upgrade' ),
			'upgrade_label' => __( 'Upgrade Plan', 'post-type-x' ),
		);
	}

	/**
	 * Builds one EPC-hosted billing settings URL for the editor metabox.
	 *
	 * @param IC_AI_Integration $integration Integration.
	 * @param string            $mode        Billing mode.
	 *
	 * @return string
	 */
	private function billing_settings_url( $integration, $mode ) {
		$mode = 'enhancements' === sanitize_key( $mode ) ? 'enhancements' : 'upgrade';

		return add_query_arg(
			array(
				'ic_ai_open_billing'   => $mode,
				'ic_ai_billing_public' => 1,
				'ic_ai_source'         => 'editor',
			),
			$this->manager->settings()->settings_url( $integration )
		);
	}

	/**
	 * Applies taxonomy suggestions by creating or resolving terms.
	 *
	 * @return void
	 */
	public function ajax_apply_taxonomy_terms() {
		check_ajax_referer( 'ic-ai-admin', 'nonce' );
		$target         = $this->request_target();
		$review_context = ! empty( $_POST['review_context'] );
		if ( $review_context ) {
			$this->reject_locked_review_action( $target ); }
		$post_id = isset( $_POST['object_id'] ) ? absint( wp_unslash( $_POST['object_id'] ) ) : 0;
		if ( ! $target || ! $post_id || ! $this->can_edit_target( $target, $post_id ) ) {
			wp_send_json_error( array( 'message' => __( 'You are not allowed to edit this item.', 'post-type-x' ) ), 403 );
		}
		$field_key   = isset( $_POST['field_key'] ) ? sanitize_key( wp_unslash( $_POST['field_key'] ) ) : '';
		$integration = $target->integration();
		if ( ! $integration ) {
			wp_send_json_error( array( 'message' => __( 'Unknown AI integration.', 'post-type-x' ) ), 400 );
		}
		$field = $target instanceof IC_AI_Target ? ( $target->field_map()[ $field_key ] ?? array() ) : array();
		if ( empty( $field['type'] ) || 'taxonomy' !== $field['type'] || empty( $field['taxonomy'] ) ) {
			wp_send_json_error( array( 'message' => __( 'This field is not a taxonomy field.', 'post-type-x' ) ), 400 );
		}
		$taxonomy_access = $this->validate_taxonomy_assignment_capability( $field['taxonomy'] );
		if ( is_wp_error( $taxonomy_access ) ) {
			wp_send_json_error( array( 'message' => __( 'You are not allowed to assign terms in this taxonomy.', 'post-type-x' ) ), 403 );
		}
		// phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- Each submitted path segment is sanitized before term resolution.
		$paths = isset( $_POST['paths'] ) && is_array( $_POST['paths'] ) ? wp_unslash( $_POST['paths'] ) : array();
		$terms = array();
		foreach ( $paths as $path ) {
			if ( is_string( $path ) ) {
				$path = array_map( 'trim', explode( '>', $path ) );
			}
			if ( ! is_array( $path ) ) {
				continue;
			}
			$parent = 0;
			$term   = null;
			foreach ( $path as $name ) {
				$name = sanitize_text_field( $name );
				if ( '' === $name ) {
					continue;
				}
				$existing = term_exists( $name, $field['taxonomy'], $parent );
				if ( ! $existing ) {
					$taxonomy_object = get_taxonomy( $field['taxonomy'] );
					if ( ! $taxonomy_object || empty( $taxonomy_object->cap->manage_terms ) || ! current_user_can( $taxonomy_object->cap->manage_terms ) ) {
						continue 2;
					}
					$existing = wp_insert_term( $name, $field['taxonomy'], array( 'parent' => $parent ) );
				}
				if ( is_wp_error( $existing ) ) {
					continue 2;
				}
				$term_id = is_array( $existing ) ? intval( $existing['term_id'] ) : intval( $existing );
				$term    = get_term( $term_id, $field['taxonomy'] );
				$parent  = $term_id;
			}
			if ( ! empty( $term->term_id ) ) {
				$terms[] = array(
					'term_id' => (int) $term->term_id,
					'name'    => $term->name,
				);
			}
		}

		wp_send_json_success( array( 'terms' => $terms ) );
	}

	/**
	 * Validates one taxonomy assignment capability.
	 *
	 * @param string $taxonomy Taxonomy name.
	 *
	 * @return true|WP_Error
	 */
	private function validate_taxonomy_assignment_capability( $taxonomy ) {
		$taxonomy_object = get_taxonomy( $taxonomy );
		if ( ! $taxonomy_object || empty( $taxonomy_object->cap->assign_terms ) || ! current_user_can( $taxonomy_object->cap->assign_terms ) ) {
			return new WP_Error( 'ic_ai_forbidden_taxonomy_assign', __( 'You are not allowed to assign terms in this taxonomy.', 'post-type-x' ) );
		}

		return true;
	}

	/**
	 * Generates one saved preview for a list-table row.
	 *
	 * @return void
	 */
	public function ajax_generate_list_preview() {
		check_ajax_referer( 'ic-ai-admin', 'nonce' );
		$target = $this->request_target();
		if ( ! empty( $_POST['review_regenerate'] ) ) {
			$this->reject_locked_review_action( $target ); }
		$post_id     = isset( $_POST['object_id'] ) ? absint( wp_unslash( $_POST['object_id'] ) ) : 0;
		$integration = $target instanceof IC_AI_Target ? $target->integration() : false;
		if ( ! $target || ! $integration || ! $target->list_enhance_enabled() || ! $this->request_target_object( $target, $post_id ) ) {
			wp_send_json_error( array( 'message' => __( 'Unknown AI integration.', 'post-type-x' ) ), 400 );
		}
		if ( ! $this->can_edit_target( $target, $post_id ) ) {
			wp_send_json_error( array( 'message' => __( 'You cannot edit this post.', 'post-type-x' ) ), 403 );
		}
		$this->record_stats_event(
			'ai_preview',
			array(
				'integration' => (string) $target->plugin_slug(),
				'target'      => (string) $target->key(),
				'object'      => 'taxonomy' === $target->kind() ? 'term' : 'post',
				'object_id'   => $post_id,
				'surface'     => 'list',
			)
		);

		$job_id = isset( $_POST['job_id'] ) ? sanitize_text_field( wp_unslash( $_POST['job_id'] ) ) : '';
		$ready  = $this->ensure_preview_request_ready( $target );
		if ( is_wp_error( $ready ) ) {
			$error_payload = $this->enhancement_error_payload( $integration, $ready, $this->manager->client()->site_settings(), $target );
			wp_send_json_error( $error_payload, 409 );
		}
		$term_context = 'taxonomy' === $target->kind() ? $this->collect_context_payload( $target, $post_id, array() ) : array();
		if ( 'taxonomy' === $target->kind() && is_callable( $target->context_callback() ) ) {
			$term_context = array_merge( $term_context, (array) call_user_func( $target->context_callback(), $post_id, $target ) );
		}
		$result = 'taxonomy' === $target->kind() ? $this->manager->client()->request_enhancement( $target, $this->collect_target_term_payload( $target, $post_id ), $term_context, $job_id ) : $this->request_preview_for_post( $post_id, $integration, $job_id );
		if ( 'taxonomy' === $target->kind() && ! is_wp_error( $result ) ) {
			$this->save_target_preview( $target, $post_id, $result );
		}
		if ( is_wp_error( $result ) ) {
			$error_payload = $this->enhancement_error_payload( $integration, $result, $this->manager->client()->site_settings(), $target );
			$status        = ! empty( $error_payload['queueable'] ) ? 429 : ( ! empty( $error_payload['license_flow'] ) ? 409 : 400 );
			wp_send_json_error( $error_payload, $status );
		}

		$review_count = $this->saved_preview_count( $target );

		wp_send_json_success(
			array(
				'post_id'      => $post_id,
				'review_count' => $review_count,
				'review_label' => $this->build_review_action_label( $review_count ),
				'status'       => __( 'Suggestion ready', 'post-type-x' ),
			)
		);
	}

	/**
	 * Skips one saved draft for the current review session.
	 *
	 * @return void
	 */
	public function ajax_skip_review_post() {
		check_ajax_referer( 'ic-ai-admin', 'nonce' );
		$target = $this->request_target();
		$this->reject_locked_review_action( $target );
		$post_id     = isset( $_POST['object_id'] ) ? absint( wp_unslash( $_POST['object_id'] ) ) : 0;
		$integration = $target instanceof IC_AI_Target ? $target->integration() : false;
		if ( ! $target || ! $integration || ! $target->list_enhance_enabled() || ! $this->request_target_object( $target, $post_id ) ) {
			wp_send_json_error( array( 'message' => __( 'Unknown AI integration.', 'post-type-x' ) ), 400 );
		}
		if ( ! $this->can_edit_target( $target, $post_id ) ) {
			wp_send_json_error( array( 'message' => __( 'You cannot edit this post.', 'post-type-x' ) ), 403 );
		}

		$this->add_review_skip( 'taxonomy' === $target->kind() ? $target->key() : $target->post_type(), $post_id );
		$pending_count = 'taxonomy' === $target->kind()
			? count( $this->queued_taxonomy_review_ids( $target, $this->review_skipped_ids( $target->key() ) ) )
			: $this->pending_review_count( $integration->post_type() );

		wp_send_json_success(
			array(
				'post_id'       => $post_id,
				'review_count'  => $this->saved_preview_count( $target ),
				'pending_count' => $pending_count,
				'next_url'      => $this->review_page_url( $integration, $target ),
			)
		);
	}

	/**
	 * Persists one per-user apply-field checkbox preference.
	 *
	 * @return void
	 */
	public function ajax_save_apply_field_preference() {
		check_ajax_referer( 'ic-ai-admin', 'nonce' );
		$target      = $this->request_target();
		$field_key   = isset( $_POST['field_key'] ) ? sanitize_text_field( wp_unslash( $_POST['field_key'] ) ) : '';
		$enabled     = ! empty( $_POST['enabled'] ) ? 1 : 0;
		$integration = $target instanceof IC_AI_Target ? $target->integration() : false;
		if ( ! $integration ) {
			wp_send_json_error( array( 'message' => __( 'Unknown AI integration.', 'post-type-x' ) ), 400 );
		}
		if ( ! $target || ! current_user_can( $target->edit_capability() ) ) {
			wp_send_json_error( array( 'message' => __( 'You are not allowed to save AI review preferences.', 'post-type-x' ) ), 403 );
		}
		if ( '' === $field_key || empty( $this->preview_field_definition( $target, $field_key ) ) ) {
			wp_send_json_error( array( 'message' => __( 'Unknown suggestion field.', 'post-type-x' ) ), 400 );
		}

		$preferences                       = $this->apply_field_preferences( $target->key() );
		$preferences[ $field_key ]         = $enabled;
		$all_preferences                   = get_user_meta( get_current_user_id(), 'ic_ai_apply_fields', true );
		$all_preferences                   = is_array( $all_preferences ) ? $all_preferences : array();
		$all_preferences[ $target->key() ] = $preferences;
		update_user_meta( get_current_user_id(), 'ic_ai_apply_fields', $all_preferences );

		wp_send_json_success(
			array(
				'field_key' => $field_key,
				'enabled'   => $enabled,
			)
		);
	}

	/**
	 * Applies one saved preview to the product record.
	 *
	 * @return void
	 */
	public function ajax_apply_saved_preview() {
		check_ajax_referer( 'ic-ai-admin', 'nonce' );
		$target = $this->request_target();
		$this->reject_locked_review_action( $target );
		$post_id         = isset( $_POST['object_id'] ) ? absint( wp_unslash( $_POST['object_id'] ) ) : 0;
		$post_type       = $post_id ? get_post_type( $post_id ) : '';
		$integration     = $target instanceof IC_AI_Target ? $target->integration() : false;
		$selected_fields = isset( $_POST['selected_fields'] ) && is_array( $_POST['selected_fields'] ) ? array_map( 'sanitize_text_field', wp_unslash( $_POST['selected_fields'] ) ) : array();
		// phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- Raw values are normalized per field type before persistence.
		$field_values = isset( $_POST['field_values'] ) && is_array( $_POST['field_values'] ) ? wp_unslash( $_POST['field_values'] ) : array();
		if ( ! $target || ! $integration || ! $this->request_target_object( $target, $post_id ) ) {
			wp_send_json_error( array( 'message' => __( 'Unknown AI integration.', 'post-type-x' ) ), 400 );
		}
		if ( ! $this->can_edit_target( $target, $post_id ) ) {
			wp_send_json_error( array( 'message' => __( 'You cannot edit this post.', 'post-type-x' ) ), 403 );
		}
		if ( 'taxonomy' === $target->kind() ) {
			$result = $this->apply_saved_preview_to_term( $target, $post_id, $selected_fields, $field_values );
			if ( is_wp_error( $result ) ) {
				wp_send_json_error( array( 'message' => $result->get_error_message() ), 400 );
			}
			$pending_ids  = $this->queued_taxonomy_review_ids( $target, $this->review_skipped_ids( $target->key() ) );
			$review_count = $this->saved_preview_count( $target );
			wp_send_json_success(
				array(
					'object_id'      => $post_id,
					'target_key'     => $target->key(),
					'enhanced'       => ! empty( $result['enhanced'] ),
					'review_count'   => $review_count,
					'review_label'   => $this->build_review_action_label( $review_count ),
					'pending_count'  => count( $pending_ids ),
					'awaiting_count' => count( $this->awaiting_taxonomy_review_ids( $target ) ),
					'next_url'       => $this->review_page_url( $integration, $target ),
					'message'        => __( 'Suggestions applied.', 'post-type-x' ),
				)
			);
		}

		$result = $this->apply_saved_preview_payload( $post_id, $integration, $selected_fields, $field_values );
		if ( is_wp_error( $result ) ) {
			wp_send_json_error( array( 'message' => $result->get_error_message() ), 400 );
		}

		$review_count = $this->saved_preview_count( $target );

		wp_send_json_success(
			array(
				'post_id'      => $post_id,
				'enhanced'     => ! empty( $result['enhanced'] ),
				'review_count' => $review_count,
				'review_label' => $this->build_review_action_label( $review_count ),
				'message'      => __( 'Suggestions applied.', 'post-type-x' ),
			)
		);
	}

	/**
	 * Starts one shared list enhance task.
	 *
	 * @return void
	 */
	public function ajax_start_list_task() {
		check_ajax_referer( 'ic-ai-admin', 'nonce' );
		$target_key = isset( $_POST['target_key'] ) ? sanitize_text_field( wp_unslash( $_POST['target_key'] ) ) : '';
		$target     = $target_key ? $this->manager->target( $target_key ) : false;
		$scope      = isset( $_POST['scope'] ) ? sanitize_key( wp_unslash( $_POST['scope'] ) ) : 'query';
		$post_ids   = isset( $_POST['post_ids'] ) && is_array( $_POST['post_ids'] ) ? array_map( 'absint', wp_unslash( $_POST['post_ids'] ) ) : array();
		// phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- The raw query string is preserved here and sanitized per parameter after parse_str() when building query scope.
		$query       = isset( $_POST['query'] ) ? (string) wp_unslash( $_POST['query'] ) : '';
		$integration = $target instanceof IC_AI_Target ? $target->integration() : false;
		// Bulk enhance always overrides existing AI drafts regardless of scope.
		$force = true;
		if ( ! $target || ! $integration || ! $target->list_enhance_enabled() ) {
			wp_send_json_error( array( 'message' => __( 'Unknown AI integration.', 'post-type-x' ) ), 400 );
		}
		if ( ( ! $target || ! $this->can_edit_target( $target, $post_ids ? $post_ids[0] : 0 ) ) && 'selected' === $scope ) {
			wp_send_json_error( array( 'message' => __( 'You are not allowed to run AI enhance here.', 'post-type-x' ) ), 403 );
		}
		if ( ! current_user_can( $target->edit_capability() ) ) {
			wp_send_json_error( array( 'message' => __( 'You are not allowed to run AI enhance here.', 'post-type-x' ) ), 403 );
		}
		$ready = $this->manager->client()->bulk_readiness( $target );
		if ( is_wp_error( $ready ) ) {
			$payload         = $this->enhancement_error_payload( $integration, $ready, $this->manager->client()->site_settings(), $target );
			$payload['code'] = $ready->get_error_code();
			wp_send_json_error( $payload, 409 );
		}

		$task = $this->start_list_task( $integration, $scope, $post_ids, $force, $query, 'preview', $target );
		if ( is_wp_error( $task ) ) {
			wp_send_json_error( array( 'message' => $task->get_error_message() ), 400 );
		}

		wp_send_json_success(
			array(
				'task' => $this->task_summary( $task ),
			)
		);
	}

	/**
	 * Starts one server-selected bulk refinement task for saved answers.
	 *
	 * @return void
	 */
	public function ajax_start_refine_task() {
		check_ajax_referer( 'ic-ai-admin', 'nonce' );
		$target      = $this->request_target();
		$integration = $target instanceof IC_AI_Target ? $target->integration() : false;
		if ( ! $target || ! $integration || ! $target->list_enhance_enabled() ) {
			wp_send_json_error( array( 'message' => __( 'Unknown AI integration.', 'post-type-x' ) ), 400 );
		}
		if ( ! current_user_can( $target->edit_capability() ) ) {
			wp_send_json_error( array( 'message' => __( 'You are not allowed to run AI enhance here.', 'post-type-x' ) ), 403 );
		}
		if ( ! empty( $_POST['review_context'] ) ) {
			$this->reject_locked_review_action( $target ); }

		$task = $this->start_list_task( $integration, 'answered', array(), true, '', 'refine', $target );
		if ( is_wp_error( $task ) ) {
			wp_send_json_error( array( 'message' => $task->get_error_message() ), 400 );
		}

		wp_send_json_success(
			array(
				'task' => $this->task_summary( $task ),
			)
		);
	}

	/**
	 * Returns the current status for one shared list enhance task.
	 *
	 * @return void
	 */
	public function ajax_resume_accepted_jobs() {
		check_ajax_referer( 'ic-ai-admin', 'nonce' );
		$task_id = isset( $_POST['task_id'] ) ? sanitize_text_field( wp_unslash( $_POST['task_id'] ) ) : '';
		$option  = $this->task_option_name( $task_id );
		$state   = get_option( $option, array() );
		if ( empty( $state ) || ! in_array( (string) ( $state['stop_reason'] ?? '' ), array( 'quota', 'user' ), true ) || empty( $state['pending_jobs'] ) || ( ! empty( $state['user_id'] ) && absint( $state['user_id'] ) !== get_current_user_id() ) ) {
			wp_send_json_error( array( 'message' => __( 'This task cannot be resumed.', 'post-type-x' ) ), 400 ); }
		$target = $this->normalize_review_target( $state['target_key'] ?? '' );
		if ( ! $target || ! current_user_can( $target->edit_capability() ) ) {
			wp_send_json_error( array( 'message' => __( 'You cannot resume this task.', 'post-type-x' ) ), 403 ); }
		$state['status']         = 'stopping';
		$state['poll_only']      = true;
		$state['drain_deadline'] = 0;
		delete_option( 'ic_ai_list_task_stop_' . $task_id );
		update_option( $option, $state, false );
		wp_schedule_single_event( time(), self::LIST_TASK_HOOK, array( $task_id ) );
		wp_send_json_success( array( 'task_id' => $task_id ) );
	}

	/**
	 * Returns the current list task state.
	 *
	 * @return void
	 */
	public function ajax_list_task_status() {
		check_ajax_referer( 'ic-ai-admin', 'nonce' );
		$task_id = isset( $_POST['task_id'] ) ? sanitize_key( wp_unslash( $_POST['task_id'] ) ) : '';
		$state   = $task_id ? $this->get_task_state( $task_id ) : array();
		if ( empty( $state ) ) {
			wp_send_json_error( array( 'message' => __( 'AI task not found.', 'post-type-x' ) ), 404 );
		}
		$marker = get_option( 'ic_ai_list_task_stop_' . $task_id, array() );
		if ( is_array( $marker ) && ! empty( $marker['requested_at'] ) && ! in_array( $state['status'], array( 'stopped', 'completed', 'failed' ), true ) && 'quota' !== $state['stop_reason'] ) {
			$state['status']            = 'stopping';
			$state['stop_reason']       = 'user';
			$state['poll_only']         = true;
			$state['submit_not_before'] = 0;
			$state['stop_requested_at'] = sanitize_text_field( $marker['requested_at'] );
			$state['stop_requested_by'] = absint( $marker['requested_by'] ?? 0 );
			if ( empty( $state['drain_deadline'] ) ) {
				$state['drain_deadline'] = time() + HOUR_IN_SECONDS; }
			$this->save_task_state( $state );
			$this->schedule_task_event( $task_id, 1 );
		}
		$target = $this->normalize_review_target( $state['target_key'] ?? '' );
		if ( ! $target instanceof IC_AI_Target || ! current_user_can( $target->edit_capability() ) ) {
			wp_send_json_error( array( 'message' => __( 'You are not allowed to view this AI task.', 'post-type-x' ) ), 403 );
		}
		if ( ! in_array( $state['status'], array( 'completed', 'failed', 'stopped' ), true ) || ! empty( $state['pending_jobs'] ) ) {
			$delay = ! empty( $state['pending_jobs'] ) ? 5 : max( 1, ! empty( $state['submit_not_before'] ) ? absint( $state['submit_not_before'] ) - time() : 1 );
			$this->schedule_task_event( $task_id, $delay );
		} else {
			$this->clear_task_events( $task_id );
			$this->maybe_release_target_active_task( $state );
		}

		wp_send_json_success(
			array(
				'task' => $this->task_summary( $state ),
			)
		);
	}

	/**
	 * Processes one shared list enhance task batch.
	 *
	 * @param string $task_id Task ID.
	 *
	 * @return void
	 */
	public function process_list_task( $task_id ) {
		$task_id = sanitize_key( (string) $task_id );
		if ( '' === $task_id ) {
			return;
		}
		if ( function_exists( 'ic_ai_stats_scope' ) ) {
			ic_ai_stats_scope( 'bulk' ); }

		$state = $this->get_task_state( $task_id );
		if ( empty( $state ) ) {
			return;
		}
		$state = $this->normalize_task_state( $state );
		if ( empty( $state['poll_only'] ) && 'quota' === $state['stop_reason'] && ! empty( $state['drain_deadline'] ) && time() >= absint( $state['drain_deadline'] ) ) {
			if ( ! empty( $state['pending_jobs'] ) ) {
				$state['poll_only'] = true;
				$state['status']    = 'stopping';
				$this->save_task_state( $state );
				$this->schedule_task_event( $task_id, 5 );
				return;
			}
			$state['status']                  = 'failed';
			$state['terminal_code']           = 'ic_ai_quota_blocked';
			$state['last_error_action_label'] = __( 'Continue checking accepted jobs', 'post-type-x' );
			$state['updated_at']              = gmdate( 'c' );
			$this->save_task_state( $state );
			$this->clear_task_events( $task_id );
			$this->maybe_release_target_active_task( $state );
			return;
		}

		if ( get_transient( $this->task_lock_name( $task_id ) ) ) {
			$this->schedule_task_retry( $task_id, $state );

			return;
		}

		set_transient( $this->task_lock_name( $task_id ), 1, MINUTE_IN_SECONDS * 5 );
		$target      = ! empty( $state['target_key'] ) ? $this->manager->target( $state['target_key'] ) : false;
		$integration = $target instanceof IC_AI_Target ? $target->integration() : false;
		$stop_marker = get_option( 'ic_ai_list_task_stop_' . $task_id, array() );
		if ( is_array( $stop_marker ) && ! empty( $stop_marker['requested_at'] ) && 'quota' !== $state['stop_reason'] ) {
			$state['stop_reason']       = 'user';
			$state['stop_requested_at'] = sanitize_text_field( $stop_marker['requested_at'] );
			$state['stop_requested_by'] = absint( $stop_marker['requested_by'] ?? 0 );
			$state['poll_only']         = true;
			$state['status']            = 'stopping';
			if ( empty( $state['drain_deadline'] ) ) {
				$state['drain_deadline'] = time() + absint( apply_filters( 'ic_ai_bulk_drain_timeout', HOUR_IN_SECONDS, $target, $state ) );
			}
			$this->save_task_state( $state );
		}
		if ( 'user' === $state['stop_reason'] && ! empty( $state['drain_deadline'] ) && time() >= absint( $state['drain_deadline'] ) ) {
			if ( ! empty( $state['pending_jobs'] ) ) {
				$this->schedule_task_event( $task_id, 5 );
				delete_transient( $this->task_lock_name( $task_id ) );
				return;
			}
			$state['status']                  = 'stopped';
			$state['poll_only']               = true;
			$state['last_error_action_label'] = __( 'Continue checking accepted jobs', 'post-type-x' );
			$this->save_task_state( $state );
			$this->clear_task_events( $task_id );
			$this->maybe_release_target_active_task( $state );
			delete_transient( $this->task_lock_name( $task_id ) );
			return;
		}

		if ( ! $target || ! $integration || ! $target->list_enhance_enabled() ) {
			$state['status']     = 'failed';
			$state['last_error'] = __( 'AI target is no longer available.', 'post-type-x' );
			$this->save_task_state( $state );
			$this->clear_task_events( $task_id );
			delete_transient( $this->task_lock_name( $task_id ) );
			return;
		}
		if ( ! $integration ) {
			$state['status']     = 'failed';
			$state['last_error'] = __( 'AI integration is no longer available.', 'post-type-x' );
			$this->save_task_state( $state );
			$this->clear_task_events( $task_id );
			delete_transient( $this->task_lock_name( $task_id ) );

			return;
		}

		$batch_total = $this->list_task_batch_total( $state, $integration );
		if ( absint( $state['submittable_batch_total'] ) !== $batch_total ) {
			$state['submittable_batch_total'] = $batch_total;
		}
		$started_at         = time();
		$time_budget        = $this->task_time_budget();
		$pending_cap        = $this->task_pending_cap();
		$retry_delay        = 0;
		$pending_poll_delay = 0;
		$refused            = false;
		$calls_made         = 0;

		// Phase 1: poll previously submitted jobs, oldest first (submission order).
		foreach ( $state['pending_jobs'] as $object_id => $job_id ) {
			$object_id = absint( $object_id );
			if ( 'refine' === $state['operation'] && ! $this->refine_task_snapshot_is_current( $object_id, 'taxonomy' === $target->kind() ? $target : $integration, $state ) ) {
				$state['skipped']                 = absint( $state['skipped'] ) + 1;
				$state['processed']               = absint( $state['processed'] ) + 1;
				$state['last_error']              = '';
				$state['last_error_action_url']   = '';
				$state['last_error_action_label'] = '';
				unset( $state['pending_jobs'][ $object_id ] );
				continue;
			}
			if ( $calls_made > 0 && ( time() - $started_at ) >= $time_budget ) {
				break;
			}
			if ( ! empty( $state['poll_only'] ) && '' === $job_id ) {
				continue; }
			$result = 'taxonomy' === $target->kind() ? $this->request_task_term( $object_id, $target, $state, $job_id, $batch_total ) : $this->request_task_post( $object_id, $integration, $state, $job_id, $batch_total );
			++$calls_made;
			if ( $this->is_refine_changed_error( $result ) ) {
				$state['skipped']                 = absint( $state['skipped'] ) + 1;
				$state['processed']               = absint( $state['processed'] ) + 1;
				$state['last_error']              = '';
				$state['last_error_action_url']   = '';
				$state['last_error_action_label'] = '';
				unset( $state['pending_jobs'][ $object_id ] );
				continue;
			}
			if ( is_wp_error( $result ) && $this->is_queueable_error( $result ) ) {
				$job_id = $this->queueable_job_id( $result );
				if ( '' !== $job_id ) {
					$state['pending_jobs'][ $object_id ] = $job_id;
					$state['last_error']                 = '';
					$state['last_error_action_url']      = '';
					$state['last_error_action_label']    = '';
				}
				$job_retry_delay    = $this->queueable_poll_after( $result );
				$pending_poll_delay = $pending_poll_delay ? min( $pending_poll_delay, $job_retry_delay ) : $job_retry_delay;
				continue;
			}
			if ( is_wp_error( $result ) ) {
				$state['failed']                  = absint( $state['failed'] ) + 1;
				$state['processed']               = absint( $state['processed'] ) + 1;
				$state['last_error']              = $result->get_error_message();
				$state['last_error_action_url']   = '';
				$state['last_error_action_label'] = '';
			} else {
				if ( 'refine' === $state['operation'] ) {
					$state['refined'] = absint( $state['refined'] ) + 1;
				} else {
					$state['previewed'] = absint( $state['previewed'] ) + 1;
				}
				$state['processed']               = absint( $state['processed'] ) + 1;
				$state['last_error']              = '';
				$state['last_error_action_url']   = '';
				$state['last_error_action_label'] = '';
			}
			unset( $state['pending_jobs'][ $object_id ] );
		}

		// Phase 2: submit new posts one by one until the service refuses to queue more.
		$ids = ! empty( $state['ids'] ) && is_array( $state['ids'] ) ? array_map( 'absint', $state['ids'] ) : array();
		if ( ! empty( $state['submit_not_before'] ) && time() < absint( $state['submit_not_before'] ) ) {
			$retry_delay = max( 1, absint( $state['submit_not_before'] ) - time() );
		} elseif ( ! empty( $state['submit_not_before'] ) ) {
			$state['submit_not_before'] = 0;
		}
		if ( ! empty( $state['submit_not_before'] ) && time() < absint( $state['submit_not_before'] ) ) {
			$refused = true;
		}
		while ( ! $refused && ! $state['poll_only'] && 'quota' !== $state['stop_reason'] && absint( $state['index'] ) < absint( $state['total'] ) ) {
			if ( $pending_cap && count( $state['pending_jobs'] ) >= $pending_cap ) {
				break;
			}
			$index   = absint( $state['index'] );
			$post_id = ! empty( $ids[ $index ] ) ? absint( $ids[ $index ] ) : 0;

			if ( ! $post_id || ( 'taxonomy' === $target->kind() ? ! $this->request_target_object( $target, $post_id ) : get_post_type( $post_id ) !== $integration->post_type() ) ) {
				$state['skipped']   = absint( $state['skipped'] ) + 1;
				$state['processed'] = absint( $state['processed'] ) + 1;
				$state['index']     = $index + 1;
				continue;
			}
			if ( 'refine' === $state['operation'] && ! $this->refine_task_snapshot_is_current( $post_id, 'taxonomy' === $target->kind() ? $target : $integration, $state ) ) {
				$state['skipped']                 = absint( $state['skipped'] ) + 1;
				$state['processed']               = absint( $state['processed'] ) + 1;
				$state['index']                   = $index + 1;
				$state['last_error']              = '';
				$state['last_error_action_url']   = '';
				$state['last_error_action_label'] = '';
				continue;
			}
			if ( 'preview' === $state['operation'] && empty( $state['force'] ) && $this->is_currently_enhanced( $post_id, $integration ) ) {
				$state['skipped']   = absint( $state['skipped'] ) + 1;
				$state['processed'] = absint( $state['processed'] ) + 1;
				$state['index']     = $index + 1;
				continue;
			}
			if ( $calls_made > 0 && ( time() - $started_at ) >= $time_budget ) {
				break;
			}

			$stop_marker = get_option( 'ic_ai_list_task_stop_' . $task_id, array() );
			if ( is_array( $stop_marker ) && ! empty( $stop_marker['requested_at'] ) ) {
				$state['stop_reason'] = 'user';
				$state['poll_only']   = true;
				$state['status']      = 'stopping';
				break;
			}
			$result = 'taxonomy' === $target->kind() ? $this->request_task_term( $post_id, $target, $state, '', $batch_total, true, $task_id ) : $this->request_task_post( $post_id, $integration, $state, '', $batch_total, true, $task_id );
			++$calls_made;
			$stop_marker = get_option( 'ic_ai_list_task_stop_' . $task_id, array() );
			if ( is_array( $stop_marker ) && ! empty( $stop_marker['requested_at'] ) && 'quota' !== $state['stop_reason'] ) {
				$state['stop_reason'] = 'user';
				$state['poll_only']   = true;
				$state['status']      = 'stopping';
			}
			if ( $this->is_refine_changed_error( $result ) ) {
				$state['skipped']                 = absint( $state['skipped'] ) + 1;
				$state['processed']               = absint( $state['processed'] ) + 1;
				$state['index']                   = $index + 1;
				$state['last_error']              = '';
				$state['last_error_action_url']   = '';
				$state['last_error_action_label'] = '';
				continue;
			}
			if ( is_wp_error( $result ) && $this->is_queueable_error( $result ) ) {
				$job_id = $this->queueable_job_id( $result );
				if ( '' !== $job_id ) {
					// Accepted and queued remotely: keep filling the service queue.
					$state['pending_jobs'][ $post_id ] = $job_id;
					$state['index']                    = $index + 1;
					$state['last_error']               = '';
					$state['last_error_action_url']    = '';
					$state['last_error_action_label']  = '';
					$state['submit_not_before']        = 0;
					$state['notice']                   = '';
					$state['notice_code']              = '';
					$state['notice_action_url']        = '';
					$state['notice_action_label']      = '';
					$job_poll                          = $this->queueable_poll_after( $result );
					$pending_poll_delay                = $pending_poll_delay ? min( $pending_poll_delay, $job_poll ) : $job_poll;
					continue;
				}
				// Refused (queue full, request window or scheduler busy): retry this post later.
				$refused                          = true;
				$retry_delay                      = $this->queueable_retry_after( $result );
				$body                             = $this->queueable_error_body( $result );
				$state['submit_not_before']       = time() + $retry_delay;
				$state['last_error']              = '';
				$state['last_error_action_url']   = '';
				$state['last_error_action_label'] = '';
				$state['notice']                  = '';
				$state['notice_code']             = '';
				if ( 'window_rate' === sanitize_key( $body['limit_kind'] ?? '' ) ) {
					$limit                        = absint( $body['request_limit'] ?? 0 );
					$window                       = absint( $body['request_window_minutes'] ?? 0 );
					$state['notice_code']         = 'window_rate';
					$queueable_action             = $this->queueable_window_rate_actions( $integration, $body, $this->manager->client()->site_settings() );
					$state['notice_action_url']   = $queueable_action['upgrade_url'] ?? '';
					$state['notice_action_label'] = $queueable_action['upgrade_label'] ?? '';
					if ( $state['notice_action_url'] && $limit > 0 && $window > 0 ) {
						/* translators: %d: request count. */
						$request_text = sprintf( _n( '%d request', '%d requests', $limit, 'post-type-x' ), $limit );
						/* translators: %d: window length in minutes. */
						$window_text = sprintf( _n( '%d minute', '%d minutes', $window, 'post-type-x' ), $window );
						/* translators: %d: window length in minutes. */
						$window_duration = sprintf( _n( '%d-minute', '%d-minute', $window, 'post-type-x' ), $window );
						/* translators: 1: request count, 2: window length, 3: window duration. */
						$state['notice'] = sprintf( __( 'Current AI request window: %1$s every %2$s. Accepted items continue processing; new submissions resume automatically when the %3$s window resets.', 'post-type-x' ), $request_text, $window_text, $window_duration );
					} else {
						$state['notice_action_url']   = '';
						$state['notice_action_label'] = '';
					}
				}
				break;
			}
			if ( is_wp_error( $result ) && $this->is_quota_blocked_error( $result ) ) {
				// Out of quota: stop submitting and drain the already queued jobs.
				$state['stop_reason'] = 'quota';
				if ( empty( $state['drain_deadline'] ) ) {
					$drain_target            = $this->normalize_review_target( $state['target_key'] );
					$timeout                 = absint( apply_filters( 'ic_ai_bulk_drain_timeout', HOUR_IN_SECONDS, $drain_target, $state ) );
					$state['drain_deadline'] = time() + max( 1, $timeout );
				}
				$state['terminal_code']           = 'ic_ai_quota_blocked';
				$state['last_error']              = $result->get_error_message();
				$state['last_error_action_url']   = '';
				$state['last_error_action_label'] = '';
				break;
			}
			if ( is_wp_error( $result ) ) {
				$state['failed']                  = absint( $state['failed'] ) + 1;
				$state['processed']               = absint( $state['processed'] ) + 1;
				$state['index']                   = $index + 1;
				$state['last_error']              = $result->get_error_message();
				$state['last_error_action_url']   = '';
				$state['last_error_action_label'] = '';
			} else {
				if ( 'refine' === $state['operation'] ) {
					$state['refined'] = absint( $state['refined'] ) + 1;
				} else {
					$state['previewed'] = absint( $state['previewed'] ) + 1;
				}
				$state['processed']               = absint( $state['processed'] ) + 1;
				$state['index']                   = $index + 1;
				$state['last_error']              = '';
				$state['last_error_action_url']   = '';
				$state['last_error_action_label'] = '';
			}
		}

		// Phase 3: completion or reschedule.
		$submissions_done = absint( $state['index'] ) >= absint( $state['total'] ) || in_array( $state['stop_reason'], array( 'quota', 'user' ), true );
		if ( $submissions_done && empty( $state['pending_jobs'] ) ) {
			$state['status']        = 'quota' === $state['stop_reason'] ? 'failed' : ( 'user' === $state['stop_reason'] ? 'stopped' : 'completed' );
			$state['terminal_code'] = 'quota' === $state['stop_reason'] ? 'ic_ai_quota_blocked' : '';
			$this->maybe_release_target_active_task( $state );
			$this->clear_task_events( $task_id );
			if ( 'user' === $state['stop_reason'] ) {
				delete_option( 'ic_ai_list_task_stop_' . $task_id );
			}
		} else {
			$state['status'] = 'user' === $state['stop_reason'] ? 'stopping' : 'running';
			$this->schedule_task_retry( $task_id, $state, $pending_poll_delay ? $pending_poll_delay : $retry_delay, $refused );
		}

		$state['updated_at'] = gmdate( 'c' );
		$this->save_task_state( $state );
		delete_transient( $this->task_lock_name( $task_id ) );
	}

	/**
	 * Starts one shared list enhance task.
	 *
	 * @param IC_AI_Integration $integration Integration.
	 * @param string            $scope       Task scope.
	 * @param array             $post_ids    Selected post IDs.
	 * @param bool              $force       Whether to force enhancement.
	 * @param string            $query       Current list query.
	 * @param string            $operation   Task operation: preview or refine.
	 * @param IC_AI_Target|null $target      Optional target.
	 *
	 * @return array|WP_Error
	 */
	private function start_list_task( $integration, $scope, $post_ids, $force, $query, $operation = 'preview', $target = null ) {
		$target      = $target instanceof IC_AI_Target ? $target : $integration->target( 'post' );
		$user_id     = get_current_user_id();
		$target_key  = $target ? $target->key() : $integration->post_type();
		$active_name = $this->target_active_task_option_name( $target_key );
		$active_task = $this->resolve_target_active_task( $target_key );
		if ( ! empty( $active_task ) ) {
			return $active_task;
		}

		$operation = 'refine' === sanitize_key( (string) $operation ) ? 'refine' : 'preview';
		if ( 'refine' === $operation ) {
			$scope_ids = ( $target instanceof IC_AI_Target && 'taxonomy' === $target->kind() ) ? $this->awaiting_taxonomy_review_ids( $target ) : $this->awaiting_refine_post_ids( $integration->post_type() );
		} elseif ( $target && 'taxonomy' === $target->kind() ) {
			$scope_ids = 'selected' === $scope ? $this->selected_scope_term_ids( $target, $post_ids ) : $this->query_scope_term_ids( $target, $query );
		} else {
			$scope_ids = 'selected' === $scope ? $this->selected_scope_post_ids( $integration, $post_ids ) : $this->query_scope_post_ids( $integration, $query );
		}
		if ( empty( $scope_ids ) ) {
			$message = 'refine' === $operation ? __( 'No saved answers are ready to refine.', 'post-type-x' ) : __( 'No items matched the AI enhance task.', 'post-type-x' );

			return new WP_Error( 'ic_ai_no_items', $message );
		}
		$refine_snapshots = array();
		if ( 'refine' === $operation ) {
			foreach ( $scope_ids as $scope_post_id ) {
				$signature = $this->refine_saved_signature( $scope_post_id, $target );
				if ( '' !== $signature ) {
					$refine_snapshots[ absint( $scope_post_id ) ] = $signature;
				}
			}
			$scope_ids = array_values( array_map( 'absint', array_keys( $refine_snapshots ) ) );
			if ( empty( $scope_ids ) ) {
				return new WP_Error( 'ic_ai_no_items', __( 'No saved answers are ready to refine.', 'post-type-x' ) );
			}
		}

		$task_id = 'icai' . strtolower( wp_generate_password( 12, false, false ) );
		$state   = array(
			'id'                      => $task_id,
			'target_key'              => $target ? $target->key() : 'post:' . $integration->post_type(),
			'post_type'               => $integration->post_type(),
			'user_id'                 => $user_id,
			'operation'               => $operation,
			'scope'                   => 'refine' === $operation ? 'answered' : ( 'selected' === $scope ? 'selected' : 'query' ),
			'force'                   => $force ? 1 : 0,
			'status'                  => 'queued',
			'ids'                     => array_values( array_map( 'absint', $scope_ids ) ),
			'index'                   => 0,
			'total'                   => count( $scope_ids ),
			'processed'               => 0,
			'previewed'               => 0,
			'refined'                 => 0,
			'skipped'                 => 0,
			'failed'                  => 0,
			'last_error'              => '',
			'last_error_action_url'   => '',
			'last_error_action_label' => '',
			'pending_jobs'            => array(),
			'drain_deadline'          => 0,
			'terminal_code'           => '',
			'refine_snapshots'        => $refine_snapshots,
			'submittable_batch_total' => 0,
			'poll_only'               => false,
			'submit_not_before'       => 0,
			'notice'                  => '',
			'notice_code'             => '',
			'notice_action_url'       => '',
			'notice_action_label'     => '',
			'stop_reason'             => '',
			'created_at'              => gmdate( 'c' ),
			'updated_at'              => gmdate( 'c' ),
		);

		$this->save_task_state( $state );
		if ( ! add_option( $active_name, $task_id, '', false ) ) {
			$incumbent = $this->resolve_target_active_task( $target_key );
			if ( empty( $incumbent ) && add_option( $active_name, $task_id, '', false ) ) {
				$incumbent = array();
			} elseif ( ! empty( $incumbent ) ) {
				delete_option( $this->task_option_name( $task_id ) );
				return $incumbent;
			}
			if ( empty( $incumbent ) && get_option( $active_name, '' ) === $task_id ) {
				$this->schedule_task_event( $task_id, 1 );
				return $state;
			}
			delete_option( $this->task_option_name( $task_id ) );
			return new WP_Error( 'ic_ai_task_busy', __( 'Another task is already active for this target.', 'post-type-x' ) );
		}
		$this->schedule_task_event( $task_id, 1 );
		if ( function_exists( 'spawn_cron' ) ) {
			spawn_cron();
		}

		return $state;
	}

	/**
	 * Returns the selected-scope post IDs for one integration.
	 *
	 * @param IC_AI_Integration $integration Integration.
	 * @param array             $post_ids    Submitted post IDs.
	 *
	 * @return int[]
	 */
	private function selected_scope_post_ids( $integration, $post_ids ) {
		$ids = array();

		foreach ( (array) $post_ids as $post_id ) {
			$post_id = absint( $post_id );
			if ( ! $post_id || get_post_type( $post_id ) !== $integration->post_type() || ! current_user_can( 'edit_post', $post_id ) ) {
				continue;
			}
			$ids[] = $post_id;
		}

		return array_values( array_unique( $ids ) );
	}

	/**
	 * Normalizes selected taxonomy term IDs for a target.
	 *
	 * @param IC_AI_Target $target   Taxonomy target.
	 * @param array        $term_ids Selected term IDs.
	 *
	 * @return int[]
	 */
	private function selected_scope_term_ids( $target, $term_ids ) {
		$ids = array();
		foreach ( (array) $term_ids as $term_id ) {
			$term_id = absint( $term_id );
			if ( $term_id && $this->can_edit_target( $target, $term_id ) ) {
				$ids[] = $term_id; }
		}
		return array_values( array_unique( $ids ) );
	}

	/**
	 * Resolves taxonomy term IDs from a list query.
	 *
	 * @param IC_AI_Target $target Taxonomy target.
	 * @param string       $query  Query string.
	 *
	 * @return int[]
	 */
	private function query_scope_term_ids( $target, $query ) {
		$args   = array(
			'taxonomy'   => $target->taxonomy(),
			'hide_empty' => false,
			'fields'     => 'ids',
			'number'     => 0,
		);
		$parsed = array();
		parse_str( (string) $query, $parsed );
		if ( ! empty( $parsed['s'] ) ) {
			$args['search'] = sanitize_text_field( $parsed['s'] ); }
		$terms = get_terms( $args );
		return is_wp_error( $terms ) ? array() : $this->selected_scope_term_ids( $target, $terms );
	}

	/**
	 * Returns the current filtered scope post IDs for one integration.
	 *
	 * @param IC_AI_Integration $integration Integration.
	 * @param string            $query       Raw query string.
	 *
	 * @return int[]
	 */
	private function query_scope_post_ids( $integration, $query ) {
		$args        = array(
			'post_type'           => $integration->post_type(),
			'post_status'         => array_values( get_post_stati( array( 'show_in_admin_all_list' => true ), 'names' ) ),
			'posts_per_page'      => -1,
			'orderby'             => 'ID',
			'order'               => 'ASC',
			'fields'              => 'ids',
			'no_found_rows'       => true,
			'ignore_sticky_posts' => true,
		);
		$raw_args    = array();
		$tax_queries = array();
		$query       = ltrim( (string) $query, '?' );

		if ( '' !== $query ) {
			parse_str( $query, $raw_args );
		}
		if ( ! empty( $raw_args['post_status'] ) ) {
			$args['post_status'] = sanitize_key( (string) $raw_args['post_status'] );
		}
		if ( ! empty( $raw_args['s'] ) && ! is_array( $raw_args['s'] ) ) {
			$args['s'] = sanitize_text_field( wp_unslash( $raw_args['s'] ) );
		}
		if ( ! empty( $raw_args['author'] ) ) {
			$args['author'] = absint( $raw_args['author'] );
		}
		if ( ! empty( $raw_args['m'] ) ) {
			$args['m'] = preg_replace( '/[^0-9]/', '', (string) $raw_args['m'] );
		}
		if ( ! empty( $raw_args['ic_ai_status'] ) ) {
			$meta_query = $this->enhance_status_meta_query( sanitize_key( (string) $raw_args['ic_ai_status'] ) );
			if ( ! empty( $meta_query ) ) {
				// phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_query -- Admin scope tasks must mirror the visible list-table status filter.
				$args['meta_query'] = $meta_query;
			}
		}

		$post_target = $integration->target( 'post' );
		foreach ( $post_target ? $post_target->field_map() : array() as $field ) {
			if ( empty( $field['type'] ) || 'taxonomy' !== $field['type'] || empty( $field['taxonomy'] ) ) {
				continue;
			}
			$taxonomy = (string) $field['taxonomy'];
			if ( empty( $raw_args[ $taxonomy ] ) || is_array( $raw_args[ $taxonomy ] ) ) {
				continue;
			}

			$tax_query = $this->query_scope_tax_query( $taxonomy, $raw_args[ $taxonomy ] );
			if ( ! empty( $tax_query ) ) {
				$tax_queries[] = $tax_query;
			}
		}

		if ( ! empty( $tax_queries ) ) {
			// phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_tax_query -- Admin scope tasks must mirror the visible list-table taxonomy filters.
			$args['tax_query'] = $tax_queries;
		}

		$post_ids = array_values( array_map( 'absint', get_posts( $args ) ) );
		// Load post rows in batches so the per-item capability check below does not query each post separately.
		foreach ( array_chunk( $post_ids, 500 ) as $post_id_chunk ) {
			_prime_post_caches( $post_id_chunk, false, false );
		}

		return array_values(
			array_filter(
				$post_ids,
				static function ( $post_id ) {
					return $post_id && current_user_can( 'edit_post', $post_id );
				}
			)
		);
	}

	/**
	 * Returns one query-scope taxonomy filter clause from a raw admin query value.
	 *
	 * EPC product-category filters submit slugs, while stock hierarchical list-table
	 * dropdowns for other post types submit term IDs.
	 *
	 * @param string $taxonomy  Taxonomy name.
	 * @param string $raw_value Raw submitted value.
	 *
	 * @return array
	 */
	private function query_scope_tax_query( $taxonomy, $raw_value ) {
		$taxonomy  = sanitize_key( (string) $taxonomy );
		$raw_value = trim( wp_unslash( (string) $raw_value ) );
		if ( '' === $taxonomy || '' === $raw_value ) {
			return array();
		}

		if ( 0 === strpos( $taxonomy, 'al_product-cat' ) ) {
			return array(
				'taxonomy' => $taxonomy,
				'field'    => 'slug',
				'terms'    => sanitize_title( $raw_value ),
			);
		}
		if ( is_numeric( $raw_value ) ) {
			return array(
				'taxonomy' => $taxonomy,
				'field'    => 'term_id',
				'terms'    => absint( $raw_value ),
			);
		}

		return array(
			'taxonomy' => $taxonomy,
			'field'    => 'slug',
			'terms'    => sanitize_title( $raw_value ),
		);
	}

	/**
	 * Returns the option name storing one task state.
	 *
	 * @param string $task_id Task ID.
	 *
	 * @return string
	 */
	private function task_option_name( $task_id ) {
		return 'ic_ai_list_task_' . sanitize_key( (string) $task_id );
	}

	/**
	 * Returns the option name storing the active task pointer.
	 *
	 * @param string $post_type Post type.
	 * @param int    $user_id   User ID.
	 *
	 * @return string
	 */
	private function active_task_option_name( $post_type, $user_id ) {
		return 'ic_ai_list_active_' . sanitize_key( (string) $post_type ) . '_' . absint( $user_id );
	}

	/**
	 * Returns the transient name used to lock one task run.
	 *
	 * @param string $task_id Task ID.
	 *
	 * @return string
	 */
	private function task_lock_name( $task_id ) {
		return 'ic_ai_list_task_lock_' . sanitize_key( (string) $task_id );
	}

	/**
	 * Returns one saved task state.
	 *
	 * @param string $task_id Task ID.
	 *
	 * @return array
	 */
	private function get_task_state( $task_id ) {
		$state = get_option( $this->task_option_name( $task_id ), array() );

		return is_array( $state ) ? $state : array();
	}

	/**
	 * Persists one task state.
	 *
	 * @param array $state Task state.
	 *
	 * @return void
	 */
	private function save_task_state( $state ) {
		if ( empty( $state['id'] ) ) {
			return;
		}

		update_option( $this->task_option_name( $state['id'] ), $state, false );
	}

	/**
	 * Clears all scheduled cron events for one task.
	 *
	 * @param string $task_id Task ID.
	 *
	 * @return void
	 */
	private function clear_task_events( $task_id ) {
		$task_id = sanitize_key( (string) $task_id );
		if ( '' === $task_id ) {
			return;
		}

		wp_clear_scheduled_hook( self::LIST_TASK_HOOK, array( $task_id ) );
	}

	/**
	 * Returns the next retry delay for one task, or zero when no retry is needed.
	 *
	 * @param array $state       Task state.
	 * @param int   $retry_delay Suggested retry delay.
	 * @param bool  $refused     Whether the remote service refused new work.
	 *
	 * @return int
	 */
	private function task_retry_delay( $state, $retry_delay = 0, $refused = false ) {
		$state            = $this->normalize_task_state( $state );
		$submissions_done = absint( $state['index'] ) >= absint( $state['total'] ) || 'quota' === $state['stop_reason'];
		if ( in_array( $state['status'], array( 'completed', 'failed' ), true ) || ( $submissions_done && empty( $state['pending_jobs'] ) ) ) {
			return 0;
		}
		if ( $refused ) {
			$deadline_delay = ! empty( $state['submit_not_before'] ) ? max( 1, absint( $state['submit_not_before'] ) - time() ) : 0;
			$poll_delay     = ! empty( $state['pending_jobs'] ) ? max( 1, absint( $retry_delay ) ) : 0;
			if ( $poll_delay && $deadline_delay ) {
				return min( $poll_delay, $deadline_delay ); }
			return max( 1, $deadline_delay ? $deadline_delay : $poll_delay );
		}
		if ( ! empty( $state['pending_jobs'] ) ) {
			return max( 1, $retry_delay ? absint( $retry_delay ) : 5 );
		}

		return 1;
	}

	/**
	 * Schedules or clears the next cron event for one task based on task state.
	 *
	 * @param string $task_id     Task ID.
	 * @param array  $state       Task state.
	 * @param int    $retry_delay Suggested retry delay.
	 * @param bool   $refused     Whether the remote service refused new work.
	 *
	 * @return void
	 */
	private function schedule_task_retry( $task_id, $state, $retry_delay = 0, $refused = false ) {
		$delay = $this->task_retry_delay( $state, $retry_delay, $refused );
		if ( $delay < 1 ) {
			$this->clear_task_events( $task_id );

			return;
		}

		$this->schedule_task_event( $task_id, $delay );
	}

	/**
	 * Schedules the next cron event for one task.
	 *
	 * @param string $task_id     Task ID.
	 * @param int    $delay       Delay in seconds.
	 *
	 * @return void
	 */
	private function schedule_task_event( $task_id, $delay ) {
		$delay      = max( 1, absint( $delay ) );
		$task_id    = sanitize_key( (string) $task_id );
		$target     = time() + $delay;
		$next_event = wp_next_scheduled( self::LIST_TASK_HOOK, array( $task_id ) );
		if ( $next_event ) {
			if ( $next_event <= $target ) {
				return;
			}

			wp_unschedule_event( $next_event, self::LIST_TASK_HOOK, array( $task_id ) );
		}

		wp_schedule_single_event( $target, self::LIST_TASK_HOOK, array( $task_id ) );
	}

	/**
	 * Returns the public task summary used by JS.
	 *
	 * @param array $state Task state.
	 *
	 * @return array
	 */
	private function task_summary( $state ) {
		$state        = $this->normalize_task_state( $state );
		$failed_count = isset( $state['failed'] ) ? absint( $state['failed'] ) : 0;
		$last_error   = ! empty( $state['last_error'] ) ? (string) $state['last_error'] : '';
		$status       = ! empty( $state['status'] ) ? sanitize_key( $state['status'] ) : 'queued';
		if ( get_option( 'ic_ai_list_task_stop_' . ( $state['id'] ?? '' ), false ) && ! in_array( $status, array( 'stopped', 'failed', 'completed' ), true ) ) {
			$status = 'stopping';
		}
		$reload_delay  = $this->task_summary_reload_delay( $status, $failed_count, $last_error );
		$review_target = ! empty( $state['target_key'] ) ? $this->normalize_review_target( $state['target_key'] ) : false;
		$review_count  = $review_target instanceof IC_AI_Target ? $this->saved_preview_count( $review_target ) : 0;

		return array(
			'id'                   => isset( $state['id'] ) ? sanitize_key( $state['id'] ) : '',
			'targetKey'            => ! empty( $state['target_key'] ) ? sanitize_text_field( $state['target_key'] ) : '',
			'postType'             => ! empty( $state['post_type'] ) ? sanitize_key( $state['post_type'] ) : '',
			'operation'            => $state['operation'],
			'status'               => $status,
			'total'                => isset( $state['total'] ) ? absint( $state['total'] ) : 0,
			'processed'            => isset( $state['processed'] ) ? absint( $state['processed'] ) : 0,
			'previewed'            => isset( $state['previewed'] ) ? absint( $state['previewed'] ) : 0,
			'refined'              => isset( $state['refined'] ) ? absint( $state['refined'] ) : 0,
			'skipped'              => isset( $state['skipped'] ) ? absint( $state['skipped'] ) : 0,
			'failed'               => $failed_count,
			'pending'              => ! empty( $state['pending_jobs'] ) && is_array( $state['pending_jobs'] ) ? count( $state['pending_jobs'] ) : 0,
			'drainDeadline'        => ! empty( $state['drain_deadline'] ) ? absint( $state['drain_deadline'] ) : 0,
			'terminalCode'         => ! empty( $state['terminal_code'] ) ? sanitize_key( $state['terminal_code'] ) : '',
			'lastError'            => $last_error,
			'lastErrorActionUrl'   => ! empty( $state['last_error_action_url'] ) ? esc_url_raw( $state['last_error_action_url'] ) : '',
			'lastErrorActionLabel' => ! empty( $state['last_error_action_label'] ) ? sanitize_text_field( (string) $state['last_error_action_label'] ) : '',
			'notice'               => ! empty( $state['notice'] ) ? sanitize_text_field( (string) $state['notice'] ) : '',
			'noticeCode'           => ! empty( $state['notice_code'] ) ? sanitize_key( $state['notice_code'] ) : '',
			'noticeActionUrl'      => ! empty( $state['notice_action_url'] ) ? esc_url_raw( $state['notice_action_url'] ) : '',
			'noticeActionLabel'    => ! empty( $state['notice_action_label'] ) ? sanitize_text_field( (string) $state['notice_action_label'] ) : '',
			'autoReload'           => $reload_delay > 0,
			'reloadDelayMs'        => $reload_delay,
			'stopReason'           => ! empty( $state['stop_reason'] ) ? sanitize_key( $state['stop_reason'] ) : '',
			'remaining'            => max( 0, (int) $state['total'] - (int) $state['processed'] ),
			'reviewCount'          => absint( $review_count ),
			'reviewLabel'          => $this->build_review_action_label( $review_count ),
			'canStop'              => absint( $state['user_id'] ?? 0 ) === get_current_user_id() && ! in_array( $status, array( 'stopped', 'stopping', 'completed', 'failed' ), true ),
			'owner'                => absint( $state['user_id'] ?? 0 ) === get_current_user_id(),
		);
	}

	/**
	 * Returns the stored or saved surrounding batch total for one list task.
	 *
	 * Queue-only list tasks already persist the original scope size in state.
	 * Reuse the cached submit total when present and otherwise fall back to that
	 * saved scope size instead of rescanning posts before the first submit tick.
	 *
	 * @param array             $state       Task state.
	 * @param IC_AI_Integration $integration Integration.
	 *
	 * @return int
	 */
	private function list_task_batch_total( $state, $integration ) {
		$persisted_total = ! empty( $state['submittable_batch_total'] ) ? absint( $state['submittable_batch_total'] ) : 0;
		if ( $persisted_total > 0 ) {
			return $persisted_total;
		}

		$ids               = ! empty( $state['ids'] ) && is_array( $state['ids'] ) ? array_map( 'absint', $state['ids'] ) : array();
		$submittable_count = count( $ids );
		if ( ! empty( $state['operation'] ) && 'refine' === $state['operation'] ) {
			return max( 1, $submittable_count );
		}
		foreach ( $ids as $id ) {
			$post_id = absint( $id );
			if ( empty( $state['force'] ) && $this->is_currently_enhanced( $post_id, $integration ) ) {
				--$submittable_count;
			}
		}

		return max( 1, $submittable_count );
	}

	/**
	 * Returns the client-side reload delay for one task summary.
	 *
	 * @param string $status       Task status.
	 * @param int    $failed_count Failed item count.
	 * @param string $last_error   Last task error text.
	 *
	 * @return int
	 */
	private function task_summary_reload_delay( $status, $failed_count, $last_error ) {
		if ( 'completed' !== $status ) {
			return 0;
		}

		return ( 0 === absint( $failed_count ) && '' === $last_error ) ? 1200 : 6000;
	}

	/**
	 * Returns true when one error is queueable.
	 *
	 * @param WP_Error $error Error object.
	 *
	 * @return bool
	 */
	private function is_queueable_error( $error ) {
		$data = is_wp_error( $error ) ? $error->get_error_data() : array();

		return ! empty( $data['status'] ) && 429 === absint( $data['status'] ) && ! empty( $data['body']['queueable'] );
	}

	/**
	 * Returns true when refinement was discarded because its snapshot changed.
	 *
	 * @param mixed $result Request result.
	 *
	 * @return bool
	 */
	private function is_refine_changed_error( $result ) {
		return is_wp_error( $result ) && 'ic_ai_refine_changed' === $result->get_error_code();
	}

	/**
	 * Returns the queueable error body for one request error.
	 *
	 * @param WP_Error $error Error object.
	 *
	 * @return array
	 */
	private function queueable_error_body( $error ) {
		$data = is_wp_error( $error ) ? $error->get_error_data() : array();

		return ! empty( $data['body'] ) && is_array( $data['body'] ) ? $data['body'] : array();
	}

	/**
	 * Normalizes one saved task state for processing.
	 *
	 * Ensures the target-keyed pending_jobs map and terminal state exist.
	 *
	 * @param array $state Task state.
	 *
	 * @return array
	 */
	private function normalize_task_state( $state ) {
		$pending_jobs = array();
		if ( ! empty( $state['pending_jobs'] ) && is_array( $state['pending_jobs'] ) ) {
			foreach ( $state['pending_jobs'] as $object_id => $job_id ) {
				$object_id = absint( $object_id );
				if ( $object_id ) {
					$pending_jobs[ $object_id ] = sanitize_text_field( (string) $job_id );
				}
			}
		}
		$refine_snapshots = array();
		if ( ! empty( $state['refine_snapshots'] ) && is_array( $state['refine_snapshots'] ) ) {
			foreach ( $state['refine_snapshots'] as $snapshot_post_id => $snapshot_signature ) {
				$snapshot_post_id   = absint( $snapshot_post_id );
				$snapshot_signature = sanitize_text_field( (string) $snapshot_signature );
				if ( $snapshot_post_id && '' !== $snapshot_signature ) {
					$refine_snapshots[ $snapshot_post_id ] = $snapshot_signature;
				}
			}
		}
		$state['pending_jobs']            = $pending_jobs;
		$state['target_key']              = ! empty( $state['target_key'] ) ? sanitize_text_field( (string) $state['target_key'] ) : ( ! empty( $state['post_type'] ) ? 'post:' . sanitize_key( $state['post_type'] ) : '' );
		$state['refine_snapshots']        = $refine_snapshots;
		$state['operation']               = ! empty( $state['operation'] ) && 'refine' === sanitize_key( $state['operation'] ) ? 'refine' : 'preview';
		$state['refined']                 = ! empty( $state['refined'] ) ? absint( $state['refined'] ) : 0;
		$state['submittable_batch_total'] = ! empty( $state['submittable_batch_total'] ) ? max( 1, absint( $state['submittable_batch_total'] ) ) : 0;
		$state['stop_reason']             = ! empty( $state['stop_reason'] ) ? sanitize_key( $state['stop_reason'] ) : '';
		$state['stop_requested_at']       = ! empty( $state['stop_requested_at'] ) ? sanitize_text_field( $state['stop_requested_at'] ) : '';
		$state['stop_requested_by']       = ! empty( $state['stop_requested_by'] ) ? absint( $state['stop_requested_by'] ) : 0;
		$state['drain_deadline']          = ! empty( $state['drain_deadline'] ) ? absint( $state['drain_deadline'] ) : 0;
		$state['terminal_code']           = ! empty( $state['terminal_code'] ) ? sanitize_key( $state['terminal_code'] ) : '';
		$state['last_error_action_url']   = ! empty( $state['last_error_action_url'] ) ? esc_url_raw( (string) $state['last_error_action_url'] ) : '';
		$state['last_error_action_label'] = ! empty( $state['last_error_action_label'] ) ? sanitize_text_field( (string) $state['last_error_action_label'] ) : '';
		$state['poll_only']               = ! empty( $state['poll_only'] );
		$state['submit_not_before']       = ! empty( $state['submit_not_before'] ) ? absint( $state['submit_not_before'] ) : 0;
		$state['notice']                  = ! empty( $state['notice'] ) ? sanitize_text_field( (string) $state['notice'] ) : '';
		$state['notice_code']             = ! empty( $state['notice_code'] ) ? sanitize_key( (string) $state['notice_code'] ) : '';
		$state['notice_action_url']       = ! empty( $state['notice_action_url'] ) ? esc_url_raw( (string) $state['notice_action_url'] ) : '';
		$state['notice_action_label']     = ! empty( $state['notice_action_label'] ) ? sanitize_text_field( (string) $state['notice_action_label'] ) : '';

		return $state;
	}

	/**
	 * Returns the remote job ID carried by one queueable error.
	 *
	 * An accepted-but-pending response carries a job_id; a queue refusal
	 * (queue full, request window or scheduler busy) does not.
	 *
	 * @param WP_Error $error Error object.
	 *
	 * @return string
	 */
	private function queueable_job_id( $error ) {
		$body = $this->queueable_error_body( $error );

		return ! empty( $body['job_id'] ) ? sanitize_text_field( $body['job_id'] ) : '';
	}

	/**
	 * Returns the retry delay suggested by one queueable error.
	 *
	 * @param WP_Error $error    Error object.
	 * @param int      $fallback Fallback delay in seconds.
	 *
	 * @return int
	 */
	private function queueable_retry_after( $error, $fallback = 5 ) {
		$body = $this->queueable_error_body( $error );

		return ! empty( $body['retry_after'] ) ? absint( $body['retry_after'] ) : absint( $fallback );
	}

	/**
	 * Returns the short poll cadence for one accepted-but-pending remote job.
	 *
	 * @param WP_Error $error    Error object.
	 * @param int      $fallback Fallback delay in seconds.
	 *
	 * @return int
	 */
	private function queueable_poll_after( $error, $fallback = 5 ) {
		$body = $this->queueable_error_body( $error );
		if ( empty( $body['job_id'] ) ) {
			return $this->queueable_retry_after( $error, $fallback );
		}

		return ! empty( $body['poll_after'] ) ? absint( $body['poll_after'] ) : absint( $fallback );
	}

	/**
	 * Returns true when one error reports an exhausted AI quota.
	 *
	 * @param WP_Error $error Error object.
	 *
	 * @return bool
	 */
	private function is_quota_blocked_error( $error ) {
		if ( ! is_wp_error( $error ) ) {
			return false;
		}

		$error_data = $error->get_error_data();

		return 'ic_ai_quota_blocked' === $error->get_error_code() && ( empty( $error_data['status'] ) || in_array( absint( $error_data['status'] ), array( 402, 429 ), true ) );
	}

	/**
	 * Returns the optional cap on simultaneously pending remote list-task jobs.
	 *
	 * @return int Cap on pending jobs; 0 means uncapped (purely reactive fill).
	 */
	private function task_pending_cap() {
		/**
		 * Filters the maximum number of pending remote jobs one list task keeps queued.
		 *
		 * @param int $cap Pending-jobs cap; 0 disables the cap.
		 */
		return max( 0, absint( apply_filters( 'ic_ai_list_task_pending_cap', 0 ) ) );
	}

	/**
	 * Returns the per-tick time budget for list-task remote calls.
	 *
	 * After the budget elapses the tick stops starting new remote calls; at
	 * least one remote call is always allowed per tick.
	 *
	 * @return int Time budget in seconds.
	 */
	private function task_time_budget() {
		/**
		 * Filters the per-tick time budget for list-task remote calls.
		 *
		 * @param int $budget Time budget in seconds.
		 */
		return max( 5, absint( apply_filters( 'ic_ai_list_task_time_budget', 40 ) ) );
	}

	/**
	 * Requests and stores one saved preview for a post.
	 *
	 * @param int               $post_id      Post ID.
	 * @param IC_AI_Integration $integration  Integration.
	 * @param string            $job_id       Optional remote job ID.
	 * @param int               $batch_total    Total number of items in the surrounding batch.
	 * @param bool              $queue_only     Whether the fresh submit should only enqueue remotely.
	 * @param string            $batch_group_id Optional same-task batch group identifier for worker-side merging.
	 *
	 * @return array|WP_Error
	 */
	private function request_preview_for_post( $post_id, $integration, $job_id = '', $batch_total = 1, $queue_only = false, $batch_group_id = '' ) {
		$target      = $integration instanceof IC_AI_Target ? $integration : $integration->target( 'post' );
		$integration = $target->integration();
		$ready       = $this->ensure_preview_request_ready( $target );
		if ( is_wp_error( $ready ) ) {
			return $ready;
		}

		$job_id  = sanitize_text_field( (string) $job_id );
		$payload = '' === $job_id ? $this->collect_payload( $integration, $post_id, array() ) : array();
		$context = '' === $job_id ? $this->collect_context_payload( $integration, $post_id, array() ) : array();
		$result  = $this->manager->client()->request_enhancement( $target, $payload, $context, $job_id, $batch_total, $queue_only, $batch_group_id );
		if ( is_wp_error( $result ) ) {
			return $result;
		}

		$this->save_preview_response( $post_id, $integration, $result );

		return $result;
	}

	/**
	 * Runs the request matching one taxonomy task operation.
	 *
	 * @param int          $term_id        Term ID.
	 * @param IC_AI_Target $target         Taxonomy target.
	 * @param array        $state          Normalized task state.
	 * @param string       $job_id         Optional remote job ID.
	 * @param int          $batch_total    Total number of items in the batch.
	 * @param bool         $queue_only     Whether a fresh request should only enqueue.
	 * @param string       $batch_group_id Same-task batch group identifier.
	 *
	 * @return array|WP_Error
	 */
	private function request_task_term( $term_id, $target, $state, $job_id = '', $batch_total = 1, $queue_only = false, $batch_group_id = '' ) {
		if ( 'refine' === ( $state['operation'] ?? '' ) ) {
			$expected = ! empty( $state['refine_snapshots'][ $term_id ] ) ? (string) $state['refine_snapshots'][ $term_id ] : '';
			return $this->request_refine_for_target( $term_id, $target, $job_id, $batch_total, $queue_only, $batch_group_id, $expected );
		}
		$readiness = $this->manager->client()->enhancement_readiness( $target );
		if ( is_wp_error( $readiness ) ) {
			return $readiness;
		}
		$payload = $this->collect_target_term_payload( $target, $term_id );
		$context = array_merge( $this->collect_context_payload( $target, $term_id, array() ), is_callable( $target->context_callback() ) ? (array) call_user_func( $target->context_callback(), $term_id, $target ) : array() );
		$result  = $this->manager->client()->request_enhancement( $target, $payload, $context, $job_id, $batch_total, $queue_only, $batch_group_id );
		if ( ! is_wp_error( $result ) ) {
			$this->save_target_preview( $target, $term_id, $result ); }
		return $result;
	}

	/**
	 * Requests a refinement for one target object.
	 *
	 * @param int          $object_id         Object ID.
	 * @param IC_AI_Target $target             Target object.
	 * @param string       $job_id             Optional remote job ID.
	 * @param int          $batch_total        Total batch size.
	 * @param bool         $queue_only         Whether to queue only.
	 * @param string       $batch_group_id     Batch group identifier.
	 * @param string       $expected_signature Expected saved-answer signature.
	 *
	 * @return array|WP_Error
	 */
	private function request_refine_for_target( $object_id, $target, $job_id = '', $batch_total = 1, $queue_only = false, $batch_group_id = '', $expected_signature = '' ) {
		$saved      = $this->target_saved_preview( $target, $object_id );
		$qa_context = $this->saved_qa_context( $saved );
		$signature  = ! empty( $qa_context ) ? hash( 'sha256', (string) wp_json_encode( $saved ) ) : '';
		if ( '' === $expected_signature || '' === $signature || ! hash_equals( $expected_signature, $signature ) ) {
			return new WP_Error( 'ic_ai_refine_changed', __( 'The saved answers changed after this refine task started.', 'post-type-x' ) ); }
		$payload = ! empty( $saved['fields'] ) && is_array( $saved['fields'] ) ? $saved['fields'] : $this->collect_target_term_payload( $target, $object_id );
		$context = array_merge( $this->collect_context_payload( $target, $object_id, array() ), is_callable( $target->context_callback() ) ? (array) call_user_func( $target->context_callback(), $object_id, $target ) : array() );
		$result  = $this->manager->client()->request_enhancement( $target, $payload, $context, $job_id, $batch_total, $queue_only, $batch_group_id, $qa_context, true );
		if ( is_wp_error( $result ) ) {
			return $result; }
		$current_raw = get_term_meta( absint( $object_id ), self::SAVED_PREVIEW_META_KEY, true );
		$current     = is_array( $current_raw ) ? $current_raw : array();
		$current_sig = ! empty( $this->saved_qa_context( $current ) ) ? hash( 'sha256', (string) wp_json_encode( $current ) ) : '';
		if ( '' === $current_sig || ! hash_equals( $expected_signature, $current_sig ) ) {
			return new WP_Error( 'ic_ai_refine_changed', __( 'The saved answers changed while this suggestion was being updated.', 'post-type-x' ) ); }
		$result['qa_answers']     = array();
		$normalized               = $this->normalize_preview_response( $target, $result );
		$normalized['target_key'] = $target->key();
		if ( false === update_term_meta( absint( $object_id ), self::SAVED_PREVIEW_META_KEY, $normalized, $current_raw ) ) {
			return new WP_Error( 'ic_ai_refine_changed', __( 'The saved answers changed before the updated suggestion could be stored.', 'post-type-x' ) ); }
		return $result;
	}

	/**
	 * Submits one post task request.
	 *
	 * @param int               $post_id       Post ID.
	 * @param IC_AI_Integration $integration   Integration.
	 * @param array             $state         Task state.
	 * @param string            $job_id        Existing job ID.
	 * @param int               $batch_total   Batch total.
	 * @param bool              $queue_only    Whether to queue only.
	 * @param string            $batch_group_id Batch group ID.
	 *
	 * @return array|WP_Error
	 */
	private function request_task_post( $post_id, $integration, $state, $job_id = '', $batch_total = 1, $queue_only = false, $batch_group_id = '' ) {
		if ( 'refine' === $state['operation'] ) {
			$expected_signature = ! empty( $state['refine_snapshots'][ $post_id ] ) ? (string) $state['refine_snapshots'][ $post_id ] : '';

			return $this->request_refine_for_post( $post_id, $integration, $job_id, $batch_total, $queue_only, $batch_group_id, $expected_signature );
		}

		return $this->request_preview_for_post( $post_id, $integration, $job_id, $batch_total, $queue_only, $batch_group_id );
	}

	/**
	 * Refines one saved answered draft and clears answers only after success.
	 *
	 * @param int               $post_id       Post ID.
	 * @param IC_AI_Integration $integration   Integration.
	 * @param string            $job_id        Optional remote job ID.
	 * @param int               $batch_total   Total number of items in the batch.
	 * @param bool              $queue_only    Whether a fresh request should only enqueue.
	 * @param string            $batch_group_id Same-task batch group identifier.
	 * @param string            $expected_signature Saved-draft signature captured at task start.
	 *
	 * @return array|WP_Error
	 */
	private function request_refine_for_post( $post_id, $integration, $job_id = '', $batch_total = 1, $queue_only = false, $batch_group_id = '', $expected_signature = '' ) {
		$target      = $integration instanceof IC_AI_Target ? $integration : $integration->target( 'post' );
		$integration = $target->integration();
		$ready       = $this->ensure_preview_request_ready( $target );
		if ( is_wp_error( $ready ) ) {
			return $ready;
		}

		$saved      = $this->saved_preview_response( $post_id, $integration );
		$qa_context = $this->saved_qa_context( $saved );
		$signature  = ! empty( $qa_context ) ? hash( 'sha256', (string) wp_json_encode( $saved ) ) : '';
		if ( '' === $expected_signature || '' === $signature || ! hash_equals( $expected_signature, $signature ) ) {
			return new WP_Error( 'ic_ai_refine_changed', __( 'The saved answers changed after this refine task started.', 'post-type-x' ) );
		}

		$job_id  = sanitize_text_field( (string) $job_id );
		$payload = ! empty( $saved['fields'] ) && is_array( $saved['fields'] )
			? $saved['fields']
			: $this->collect_payload( $integration, $post_id, array() );
		$context = $this->collect_context_payload( $integration, $post_id, array() );
		$result  = $this->manager->client()->request_enhancement( $target, $payload, $context, $job_id, $batch_total, $queue_only, $batch_group_id, $qa_context, true );
		if ( is_wp_error( $result ) ) {
			return $result;
		}
		$current_raw = get_post_meta( $post_id, self::SAVED_PREVIEW_META_KEY, true );
		if ( ! is_array( $current_raw ) ) {
			return new WP_Error( 'ic_ai_refine_changed', __( 'The saved answers changed while this suggestion was being updated.', 'post-type-x' ) );
		}
		$current_saved     = $this->normalize_preview_response( $integration, $current_raw );
		$current_signature = ! empty( $this->saved_qa_context( $current_saved ) ) ? hash( 'sha256', (string) wp_json_encode( $current_saved ) ) : '';
		if ( '' === $current_signature || ! hash_equals( $expected_signature, $current_signature ) ) {
			return new WP_Error( 'ic_ai_refine_changed', __( 'The saved answers changed while this suggestion was being updated.', 'post-type-x' ) );
		}

		$result['qa_answers'] = array();
		$normalized_result    = $this->normalize_preview_response( $integration, $result );
		if ( false === update_post_meta( $post_id, self::SAVED_PREVIEW_META_KEY, $normalized_result, $current_raw ) ) {
			return new WP_Error( 'ic_ai_refine_changed', __( 'The saved answers changed before the updated suggestion could be stored.', 'post-type-x' ) );
		}

		return $result;
	}

	/**
	 * Ensures one integration is ready for AI preview requests.
	 *
	 * @param IC_AI_Integration $integration Integration.
	 *
	 * @return true|WP_Error
	 */
	private function ensure_preview_request_ready( $integration ) {
		$target = $integration instanceof IC_AI_Target ? $integration : $integration->target( 'post' );

		return $this->manager->client()->enhancement_readiness( $target );
	}

	/**
	 * Applies one saved preview payload to the post.
	 *
	 * @param int               $post_id         Post ID.
	 * @param IC_AI_Integration $integration     Integration.
	 * @param array             $selected_fields Selected field keys.
	 * @param array             $field_values    Submitted field values.
	 *
	 * @return array|WP_Error
	 */
	private function apply_saved_preview_payload( $post_id, $integration, $selected_fields, $field_values ) {
		$saved           = $this->saved_preview_response( $post_id, $integration );
		$available_keys  = ! empty( $saved['fields'] ) && is_array( $saved['fields'] ) ? array_keys( $saved['fields'] ) : array();
		$selected_fields = array_values( array_intersect( array_map( 'sanitize_text_field', (array) $selected_fields ), $available_keys ) );
		$post_update     = array( 'ID' => $post_id );
		$post_dirty      = false;
		$enhanced        = false;

		if ( empty( $selected_fields ) ) {
			return new WP_Error( 'ic_ai_no_fields', __( 'Select at least one suggested change to apply.', 'post-type-x' ) );
		}

		$this->start_managed_write( $post_id );

		foreach ( $selected_fields as $field_key ) {
			$field = $this->preview_field_definition( $integration, $field_key );
			if ( empty( $field ) ) {
				continue;
			}

			$value  = array_key_exists( $field_key, $field_values ) ? $field_values[ $field_key ] : $saved['fields'][ $field_key ];
			$result = $this->apply_saved_preview_field_to_post( $post_id, $field_key, $field, $value, $post_update, $post_dirty, $enhanced );
			if ( is_wp_error( $result ) ) {
				$this->finish_managed_write( $post_id );
				return $result;
			}
		}

		if ( $post_dirty ) {
			wp_update_post( $post_update );
		}

		$this->refresh_post_read_caches( $post_id );
		$this->delete_saved_preview_response( $post_id );
		if ( $enhanced ) {
			$this->mark_enhanced_state( $post_id, $integration );
		}
		$this->refresh_post_read_caches( $post_id );
		$this->finish_managed_write( $post_id );
		$this->record_stats_event(
			'ai_apply',
			array(
				'integration' => $integration instanceof IC_AI_Integration ? (string) $integration->plugin_slug() : '',
				'object'      => 'post',
				'object_id'   => absint( $post_id ),
				'field_count' => count( $selected_fields ),
				'enhanced'    => $enhanced ? 1 : 0,
			)
		);

		return array(
			'enhanced' => $enhanced,
		);
	}

	/**
	 * Applies a saved preview to a taxonomy term using term fields/meta.
	 *
	 * @param IC_AI_Target $target          Taxonomy target.
	 * @param int          $term_id         Term ID.
	 * @param array        $selected_fields Selected field keys.
	 * @param array        $field_values    Submitted field values.
	 *
	 * @return array|WP_Error
	 */
	private function apply_saved_preview_to_term( $target, $term_id, $selected_fields, $field_values ) {
		$saved           = $this->target_saved_preview( $target, $term_id );
		$available       = isset( $saved['fields'] ) && is_array( $saved['fields'] ) ? array_keys( $saved['fields'] ) : array();
		$selected_fields = array_values( array_intersect( array_map( 'sanitize_key', (array) $selected_fields ), $available ) );
		if ( empty( $selected_fields ) ) {
			return new WP_Error( 'ic_ai_no_fields', __( 'Select at least one suggested change to apply.', 'post-type-x' ) );
		}
		$field_map = $this->target_field_map( $target );
		foreach ( $selected_fields as $field_key ) {
			$field = isset( $field_map[ $field_key ] ) && is_array( $field_map[ $field_key ] ) ? $field_map[ $field_key ] : array();
			$value = array_key_exists( $field_key, $field_values ) ? $field_values[ $field_key ] : $saved['fields'][ $field_key ];
			if ( 'html' === ( $field['value_type'] ?? $field['content_format'] ?? '' ) ) {
				$value = wp_kses_post( (string) $value );
			} else {
				$value = sanitize_text_field( (string) $value );
			}
			if ( ! $this->manager->client()->update_target_field_value( $target, $term_id, $field_key, $value ) ) {
				return new WP_Error( 'ic_ai_term_write_failed', __( 'A taxonomy field could not be saved; the suggestion was retained.', 'post-type-x' ) );
			}
		}
		delete_term_meta( $term_id, self::SAVED_PREVIEW_META_KEY );
		update_term_meta( $term_id, self::ENHANCED_META_KEY, 1 );
		update_term_meta( $term_id, self::ENHANCED_SIGNATURE_META_KEY, $this->target_term_signature( $target, $term_id ) );
		$this->record_stats_event(
			'ai_apply',
			array(
				'integration' => (string) $target->plugin_slug(),
				'target'      => (string) $target->key(),
				'object'      => 'term',
				'object_id'   => absint( $term_id ),
				'field_count' => count( $selected_fields ),
				'enhanced'    => 1,
			)
		);
		return array( 'enhanced' => true );
	}

	/**
	 * Builds a target-keyed taxonomy enhancement signature.
	 *
	 * @param IC_AI_Target $target  Taxonomy target.
	 * @param int          $term_id Term ID.
	 *
	 * @return string
	 */
	private function target_term_signature( $target, $term_id ) {
		$values = array( 'target_key' => $target->key() );
		foreach ( $this->target_field_map( $target ) as $key => $field ) {
			if ( 'term_field' === ( $field['type'] ?? '' ) ) {
				$term           = get_term( $term_id, $target->taxonomy() );
				$values[ $key ] = $term && isset( $field['term_field'] ) ? (string) $term->{ $field['term_field'] } : '';
			} elseif ( 'term_meta' === ( $field['type'] ?? '' ) ) {
				$values[ $key ] = (string) $this->manager->client()->target_field_value( $target, $term_id, $key );
			}
		}
		return hash( 'sha256', (string) wp_json_encode( $values ) );
	}

	/**
	 * Applies one saved preview field to the post record.
	 *
	 * @param int    $post_id     Post ID.
	 * @param string $field_key   Field key.
	 * @param array  $field       Field definition.
	 * @param mixed  $value       Preview value.
	 * @param array  $post_update Post update payload.
	 * @param bool   $post_dirty  Whether the post update payload changed.
	 * @param bool   $enhanced    Whether any field was applied.
	 *
	 * @return WP_Error|null
	 */
	private function apply_saved_preview_field_to_post( $post_id, $field_key, $field, $value, &$post_update, &$post_dirty, &$enhanced ) {
		$type = $this->field_type( $field );

		if ( 'group' === $type ) {
			if ( is_string( $value ) ) {
				$decoded = json_decode( $value, true );
				if ( JSON_ERROR_NONE === json_last_error() && is_array( $decoded ) ) {
					$value = $decoded;
				}
			}
			if ( ! is_array( $value ) || empty( $field['group_fields'] ) || ! is_array( $field['group_fields'] ) ) {
				return null;
			}

			foreach ( $field['group_fields'] as $group_key => $group_field ) {
				if ( ! array_key_exists( $group_key, $value ) ) {
					continue;
				}

				$result = $this->apply_saved_preview_field_to_post( $post_id, $group_key, $group_field, $value[ $group_key ], $post_update, $post_dirty, $enhanced );
				if ( is_wp_error( $result ) ) {
					return $result;
				}
			}

			return null;
		}
		if ( 'taxonomy' === $type && ! empty( $field['taxonomy'] ) ) {
			$taxonomy_access = $this->validate_taxonomy_assignment_capability( $field['taxonomy'] );
			if ( is_wp_error( $taxonomy_access ) ) {
				return $taxonomy_access;
			}

			$this->apply_taxonomy_paths_to_post( $post_id, $field['taxonomy'], $value );
			$enhanced = true;

			return null;
		}
		if ( 'custom_meta' === $type || 'meta' === $type ) {
			$meta_key = ! empty( $field['meta_key'] ) ? (string) $field['meta_key'] : $field_key;
			update_post_meta( $post_id, $meta_key, sanitize_textarea_field( is_array( $value ) ? wp_json_encode( $value ) : (string) $value ) );
			$enhanced = true;

			return null;
		}
		if ( 'post_field' === $type && ! empty( $field['post_field'] ) ) {
			if ( 'post_title' === $field['post_field'] ) {
				$post_update['post_title'] = sanitize_text_field( (string) $value );
			} elseif ( 'post_content' === $field['post_field'] ) {
				$post_update['post_content'] = wp_kses_post( (string) $value );
			} else {
				$post_update[ $field['post_field'] ] = sanitize_textarea_field( (string) $value );
			}
			$post_dirty = true;
			$enhanced   = true;
		}

		return null;
	}

	/**
	 * Applies taxonomy paths to the post record.
	 *
	 * @param int          $post_id  Post ID.
	 * @param string       $taxonomy Taxonomy.
	 * @param string|array $value    Submitted value.
	 *
	 * @return void
	 */
	private function apply_taxonomy_paths_to_post( $post_id, $taxonomy, $value ) {
		$paths    = array();
		$term_ids = array();

		if ( is_string( $value ) ) {
			$lines = preg_split( '/\r\n|\r|\n/', $value );
			foreach ( (array) $lines as $line ) {
				$line = trim( (string) $line );
				if ( '' === $line ) {
					continue;
				}
				$paths[] = array_map( 'trim', explode( '>', $line ) );
			}
		} elseif ( is_array( $value ) ) {
			foreach ( $value as $path ) {
				$paths[] = is_array( $path ) ? $path : array_map( 'trim', explode( '>', (string) $path ) );
			}
		}

		$term_ids = $this->resolved_term_ids_from_paths( $taxonomy, $paths );
		wp_set_object_terms( $post_id, $term_ids, $taxonomy, false );
	}

	/**
	 * Resolves term IDs for hierarchical term paths.
	 *
	 * @param string $taxonomy Taxonomy.
	 * @param array  $paths    Term paths.
	 *
	 * @return int[]
	 */
	private function resolved_term_ids_from_paths( $taxonomy, $paths ) {
		$term_ids = array();

		foreach ( $paths as $path ) {
			$path    = is_array( $path ) ? $path : array();
			$parent  = 0;
			$term_id = 0;
			foreach ( $path as $name ) {
				$name = sanitize_text_field( (string) $name );
				if ( '' === $name ) {
					continue;
				}
				$existing = term_exists( $name, $taxonomy, $parent );
				if ( ! $existing ) {
					$taxonomy_object = get_taxonomy( $taxonomy );
					if ( ! $taxonomy_object || empty( $taxonomy_object->cap->manage_terms ) || ! current_user_can( $taxonomy_object->cap->manage_terms ) ) {
						$term_id = 0;
						break;
					}
					$existing = wp_insert_term( $name, $taxonomy, array( 'parent' => $parent ) );
				}
				if ( is_wp_error( $existing ) ) {
					$term_id = 0;
					break;
				}
				$term_id = is_array( $existing ) ? absint( $existing['term_id'] ) : absint( $existing );
				$parent  = $term_id;
			}
			if ( $term_id ) {
				$term_ids[] = $term_id;
			}
		}

		return array_values( array_unique( array_filter( array_map( 'absint', $term_ids ) ) ) );
	}

	/**
	 * Returns one preview-enabled field definition.
	 *
	 * @param IC_AI_Integration $integration Integration.
	 * @param string            $field_key   Field key.
	 *
	 * @return array
	 */
	private function preview_field_definition( $integration, $field_key ) {
		if ( $integration instanceof IC_AI_Target ) {
			$field = $this->target_field_map( $integration )[ $field_key ] ?? array();
			return is_array( $field ) ? $field : array();
		}
		$field_map = array_merge(
			$this->manager->client()->enabled_field_map( $integration ),
			$this->manager->client()->custom_meta_field_map( $integration, 'enhance' )
		);

		return ! empty( $field_map[ $field_key ] ) && is_array( $field_map[ $field_key ] ) ? $field_map[ $field_key ] : array();
	}

	/**
	 * Returns the enhancement state of one object.
	 *
	 * @param int                            $object_id Post ID or term ID.
	 * @param IC_AI_Integration|IC_AI_Target $context   Owning integration, or a taxonomy target for term objects.
	 *
	 * @return string 'none', 'current' or 'stale'.
	 */
	public function enhancement_state( $object_id, $context ) {
		$object_id = absint( $object_id );
		if ( ! $object_id ) {
			return 'none';
		}

		if ( $context instanceof IC_AI_Target && 'taxonomy' === $context->kind() ) {
			$term_flag      = get_term_meta( $object_id, self::ENHANCED_META_KEY, true );
			$term_signature = (string) get_term_meta( $object_id, self::ENHANCED_SIGNATURE_META_KEY, true );
			if ( empty( $term_flag ) ) {
				return 'none';
			}
			if ( '' === $term_signature ) {
				return 'stale';
			}

			return hash_equals( $term_signature, $this->target_term_signature( $context, $object_id ) ) ? 'current' : 'stale';
		}

		$integration = $context instanceof IC_AI_Target ? $context->integration() : $context;

		$flag      = get_post_meta( $object_id, self::ENHANCED_META_KEY, true );
		$signature = get_post_meta( $object_id, self::ENHANCED_SIGNATURE_META_KEY, true );
		if ( empty( $flag ) ) {
			return 'none';
		}
		if ( '' === $signature ) {
			return 'stale';
		}

		$current_signature = $this->current_enhancement_signature( $object_id, $integration );
		$signature         = (string) $signature;
		if ( hash_equals( $signature, $current_signature ) ) {
			return 'current';
		}
		if ( hash_equals( $signature, $this->compatible_enhancement_signature( $object_id, $integration ) ) ) {
			return 'current';
		}

		return hash_equals( $signature, $this->legacy_enhancement_signature( $object_id, $integration ) ) ? 'current' : 'stale';
	}

	/**
	 * Returns true when the post is still considered enhanced.
	 *
	 * @param int               $post_id      Post ID.
	 * @param IC_AI_Integration $integration  Integration.
	 *
	 * @return bool
	 */
	private function is_currently_enhanced( $post_id, $integration ) {
		return 'current' === $this->enhancement_state( $post_id, $integration );
	}

	/**
	 * Invalidates stale enhancement state after manual saves.
	 *
	 * @param int               $post_id      Post ID.
	 * @param IC_AI_Integration $integration  Integration.
	 * @param bool              $update       Whether the post was updated.
	 *
	 * @return void
	 */
	private function maybe_invalidate_enhanced_state( $post_id, $integration, $update ) {
		$post_id = absint( $post_id );
		if ( ! get_post_meta( $post_id, self::ENHANCED_META_KEY, true ) ) {
			unset( $this->pre_save_signatures[ $post_id ] );
			return;
		}
		if ( ! $update ) {
			unset( $this->pre_save_signatures[ $post_id ] );
			return;
		}

		$before_signature = isset( $this->pre_save_signatures[ $post_id ] ) ? (string) $this->pre_save_signatures[ $post_id ] : '';
		unset( $this->pre_save_signatures[ $post_id ] );
		if ( '' === $before_signature ) {
			return;
		}

		$current_signature = $this->current_enhancement_signature( $post_id, $integration );
		if ( ! hash_equals( $before_signature, $current_signature ) ) {
			delete_post_meta( $post_id, self::ENHANCED_META_KEY );
			delete_post_meta( $post_id, self::ENHANCED_SIGNATURE_META_KEY );
		}
	}

	/**
	 * Marks one post as AI enhanced.
	 *
	 * @param int               $post_id      Post ID.
	 * @param IC_AI_Integration $integration  Integration.
	 *
	 * @return void
	 */
	private function mark_enhanced_state( $post_id, $integration ) {
		$signature = $this->current_enhancement_signature( $post_id, $integration );
		if ( '' === $signature ) {
			return;
		}

		update_post_meta( $post_id, self::ENHANCED_META_KEY, 1 );
		update_post_meta( $post_id, self::ENHANCED_SIGNATURE_META_KEY, $signature );
	}

	/**
	 * Returns the current enhancement signature for one post.
	 *
	 * @param int               $post_id      Post ID.
	 * @param IC_AI_Integration $integration  Integration.
	 *
	 * @return string
	 */
	private function current_enhancement_signature( $post_id, $integration ) {
		return $this->enhancement_signature( $post_id, $integration, 'canonical' );
	}

	/**
	 * Returns the compatibility enhancement signature for one post.
	 *
	 * @param int               $post_id      Post ID.
	 * @param IC_AI_Integration $integration  Integration.
	 *
	 * @return string
	 */
	private function compatible_enhancement_signature( $post_id, $integration ) {
		return $this->enhancement_signature( $post_id, $integration, 'compatible' );
	}

	/**
	 * Returns the legacy enhancement signature for one post.
	 *
	 * @param int               $post_id      Post ID.
	 * @param IC_AI_Integration $integration  Integration.
	 *
	 * @return string
	 */
	private function legacy_enhancement_signature( $post_id, $integration ) {
		return $this->enhancement_signature( $post_id, $integration, 'legacy' );
	}

	/**
	 * Returns one enhancement signature for a post.
	 *
	 * @param int               $post_id      Post ID.
	 * @param IC_AI_Integration $integration  Integration.
	 * @param string            $mode         Signature normalization mode.
	 *
	 * @return string
	 */
	private function enhancement_signature( $post_id, $integration, $mode ) {
		$field_map          = array_merge(
			$this->manager->client()->enabled_field_map( $integration ),
			$this->manager->client()->custom_meta_field_map( $integration, 'enhance' )
		);
		$payload            = array();
		$include_taxonomies = 'canonical' === $mode;

		foreach ( $field_map as $field_key => $field ) {
			$type = $this->field_type( $field );
			if ( 'taxonomy' === $type && ! $include_taxonomies ) {
				continue;
			}

			$value = $this->collect_field_value( $field, $post_id, array() );
			if ( 'canonical' === $mode ) {
				$value = $this->normalize_signature_value( $field, $value );
			} elseif ( 'compatible' === $mode ) {
				$value = $this->compatible_signature_value( $value );
			}
			$payload[ $field_key ] = $value;
		}

		if ( empty( $payload ) ) {
			if ( empty( $field_map ) ) {
				return '';
			}

			$field_keys = array_map( 'sanitize_key', array_keys( $field_map ) );
			sort( $field_keys );

			return md5( wp_json_encode( array( 'taxonomy_only' => $field_keys ) ) );
		}

		ksort( $payload );

		return md5( wp_json_encode( array( 'fields' => $payload ) ) );
	}

	/**
	 * Normalizes one signature value for stable change detection.
	 *
	 * @param array $field Field definition.
	 * @param mixed $value Signature value.
	 *
	 * @return mixed
	 */
	private function normalize_signature_value( $field, $value ) {
		if ( is_array( $value ) ) {
			$normalized = array();
			foreach ( $value as $key => $item ) {
				$item_field = array();
				if ( ! empty( $field['group_fields'][ $key ] ) && is_array( $field['group_fields'][ $key ] ) ) {
					$item_field = $field['group_fields'][ $key ];
				}
				$normalized[ $key ] = $this->normalize_signature_value( $item_field, $item );
			}
			if ( $this->is_associative_array( $normalized ) ) {
				ksort( $normalized );
			}

			return $normalized;
		}
		if ( is_string( $value ) ) {
			return $this->normalize_string_signature_value( $field, $value );
		}
		if ( is_scalar( $value ) ) {
			return $value;
		}

		return null;
	}

	/**
	 * Normalizes one signature value using the pre-canonical compatibility rules.
	 *
	 * @param mixed $value Signature value.
	 *
	 * @return mixed
	 */
	private function compatible_signature_value( $value ) {
		if ( is_array( $value ) ) {
			$normalized = array();
			foreach ( $value as $key => $item ) {
				$normalized[ $key ] = $this->compatible_signature_value( $item );
			}
			if ( $this->is_associative_array( $normalized ) ) {
				ksort( $normalized );
			}

			return $normalized;
		}
		if ( is_string( $value ) ) {
			$value = str_replace( array( "\r\n", "\r" ), "\n", $value );

			return trim( $value );
		}
		if ( is_scalar( $value ) ) {
			return $value;
		}

		return null;
	}

	/**
	 * Normalizes one string signature value according to the field type.
	 *
	 * @param array  $field Field definition.
	 * @param string $value Field value.
	 *
	 * @return string
	 */
	private function normalize_string_signature_value( $field, $value ) {
		$value = str_replace( array( "\r\n", "\r" ), "\n", $value );

		if ( $this->is_rich_text_signature_field( $field ) ) {
			$value = wp_kses_post( $value );
			$value = preg_replace( '/>\s+</', '><', $value );
			$value = preg_replace( "/[ \t]+\n/", "\n", $value );
			$value = preg_replace( "/\n[ \t]+/", "\n", $value );
		}

		return trim( $value );
	}

	/**
	 * Returns true when the field stores rich-text content that WordPress may
	 * reformat without any user-visible change.
	 *
	 * @param array $field Field definition.
	 *
	 * @return bool
	 */
	private function is_rich_text_signature_field( $field ) {
		if ( empty( $field['type'] ) || 'post_field' !== $field['type'] || empty( $field['post_field'] ) ) {
			return false;
		}

		return in_array( $field['post_field'], array( 'post_content', 'post_excerpt' ), true );
	}

	/**
	 * Returns true when one array uses associative keys.
	 *
	 * @param array $items Array to inspect.
	 *
	 * @return bool
	 */
	private function is_associative_array( $items ) {
		if ( ! is_array( $items ) ) {
			return false;
		}
		if ( array() === $items ) {
			return false;
		}

		return array_keys( $items ) !== range( 0, count( $items ) - 1 );
	}

	/**
	 * Marks a post as being updated by AI apply logic.
	 *
	 * @param int $post_id Post ID.
	 *
	 * @return void
	 */
	private function start_managed_write( $post_id ) {
		$this->managed_write_post_ids[ absint( $post_id ) ] = true;
	}

	/**
	 * Removes the managed-write marker for one post.
	 *
	 * @param int $post_id Post ID.
	 *
	 * @return void
	 */
	private function finish_managed_write( $post_id ) {
		unset( $this->managed_write_post_ids[ absint( $post_id ) ] );
	}

	/**
	 * Clears one post's read caches after managed writes.
	 *
	 * @param int $post_id Post ID.
	 *
	 * @return void
	 */
	private function refresh_post_read_caches( $post_id ) {
		$post_id = absint( $post_id );
		if ( ! $post_id ) {
			return;
		}

		clean_post_cache( $post_id );
		update_meta_cache( 'post', array( $post_id ) );
	}

	/**
	 * Returns true when the post is being updated by AI apply logic.
	 *
	 * @param int $post_id Post ID.
	 *
	 * @return bool
	 */
	private function is_managed_write( $post_id ) {
		return ! empty( $this->managed_write_post_ids[ absint( $post_id ) ] );
	}

	/**
	 * Builds the JS config for one field.
	 *
	 * @param array  $field     Field definition.
	 * @param string $field_key Field key.
	 *
	 * @return array
	 */
	private function js_field_config( $field, $field_key ) {
		$config = array(
			'key'   => sanitize_key( $field_key ),
			'type'  => isset( $field['type'] ) ? sanitize_key( $field['type'] ) : 'meta',
			'label' => isset( $field['label'] ) ? $field['label'] : $field_key,
		);
		if ( ! empty( $field['input_selector'] ) ) {
			$config['input_selector'] = $field['input_selector'];
		}
		if ( 'post_field' === $config['type'] && ! empty( $field['post_field'] ) ) {
			$config['post_field'] = sanitize_key( $field['post_field'] );
		}
		if ( ! empty( $field['editor'] ) && is_array( $field['editor'] ) ) {
			$config['editor'] = array_intersect_key(
				$field['editor'],
				array_flip( array( 'adapter', 'store', 'action', 'payload', 'meta_key' ) )
			);
		}
		if ( ! empty( $field['taxonomy'] ) ) {
			$config['taxonomy'] = $field['taxonomy'];
		}
		if ( 'group' === $config['type'] && ! empty( $field['group_fields'] ) && is_array( $field['group_fields'] ) ) {
			$config['group_fields'] = $field['group_fields'];
		}

		return $config;
	}

	/**
	 * Returns the current admin-screen post ID, if any.
	 *
	 * @param object|null $screen Current admin screen object.
	 *
	 * @return int
	 */
	private function current_admin_post_id( $screen = null ) {
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only admin screen detection.
		if ( ! empty( $_GET['post'] ) ) {
			// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only admin screen detection.
			return absint( wp_unslash( $_GET['post'] ) );
		}
		// phpcs:ignore WordPress.Security.NonceVerification.Missing -- Read-only admin screen detection during save requests.
		if ( ! empty( $_POST['post_ID'] ) ) {
			// phpcs:ignore WordPress.Security.NonceVerification.Missing -- Read-only admin screen detection during save requests.
			return absint( wp_unslash( $_POST['post_ID'] ) );
		}
		if ( is_object( $screen ) && ( empty( $screen->base ) || 'post' !== $screen->base ) ) {
			return 0;
		}

		$post = get_post();

		return $post instanceof WP_Post ? (int) $post->ID : 0;
	}

	/**
	 * Persists one normalized saved AI preview response for a post.
	 *
	 * @param int               $post_id      Post ID.
	 * @param IC_AI_Integration $integration  Integration.
	 * @param array             $raw_response Raw preview response.
	 *
	 * @return array
	 */
	private function save_preview_response( $post_id, $integration, $raw_response ) {
		$normalized = $this->normalize_preview_response( $integration, $raw_response );
		update_post_meta( $post_id, self::SAVED_PREVIEW_META_KEY, $normalized );

		return $normalized;
	}

	/**
	 * Returns one normalized saved AI preview response for a post.
	 *
	 * @param int               $post_id     Post ID.
	 * @param IC_AI_Integration $integration Integration.
	 *
	 * @return array
	 */
	private function saved_preview_response( $post_id, $integration ) {
		$saved = get_post_meta( $post_id, self::SAVED_PREVIEW_META_KEY, true );
		if ( ! is_array( $saved ) ) {
			return array();
		}

		return $this->normalize_preview_response( $integration, $saved );
	}

	/**
	 * Returns true when a saved draft has an answer matching a current question.
	 *
	 * @param int               $post_id     Post ID.
	 * @param IC_AI_Integration $integration Integration.
	 *
	 * @return bool
	 */
	private function is_awaiting_refine( $post_id, $integration ) {
		$target = $integration instanceof IC_AI_Target ? $integration : ( is_object( $integration ) ? $integration->target( 'post' ) : false );
		$saved  = $target instanceof IC_AI_Target && 'taxonomy' === $target->kind() ? $this->target_saved_preview( $target, $post_id ) : $this->saved_preview_response( $post_id, $integration );
		return ! empty( $this->saved_qa_context( $saved ) );
	}

	/**
	 * Builds refine context from answers matched to current saved questions.
	 *
	 * @param array $saved Normalized saved preview.
	 *
	 * @return array
	 */
	private function saved_qa_context( $saved ) {
		if ( empty( $saved['questions'] ) || ! is_array( $saved['questions'] ) || empty( $saved['qa_answers'] ) || ! is_array( $saved['qa_answers'] ) ) {
			return array();
		}

		$qa_context = array();
		foreach ( $saved['questions'] as $question ) {
			$id     = ! empty( $question['id'] ) ? (string) $question['id'] : '';
			$answer = $id && isset( $saved['qa_answers'][ $id ] ) ? sanitize_text_field( (string) $saved['qa_answers'][ $id ] ) : '';
			if ( '' === $id || '' === $answer ) {
				continue;
			}
			$qa_context[] = array(
				'id'       => $id,
				'question' => isset( $question['question'] ) ? sanitize_text_field( (string) $question['question'] ) : '',
				'answer'   => $answer,
			);
		}

		return $qa_context;
	}

	/**
	 * Returns a deterministic signature for one currently eligible saved draft.
	 *
	 * @param int               $post_id     Post ID.
	 * @param IC_AI_Integration $integration Integration.
	 *
	 * @return string
	 */
	private function refine_saved_signature( $post_id, $integration ) {
		$target = $integration instanceof IC_AI_Target ? $integration : $integration->target( 'post' );
		$saved  = 'taxonomy' === $target->kind() ? $this->target_saved_preview( $target, $post_id ) : $this->saved_preview_response( $post_id, $integration );
		if ( empty( $this->saved_qa_context( $saved ) ) ) {
			return '';
		}

		return hash( 'sha256', (string) wp_json_encode( $saved ) );
	}

	/**
	 * Returns true when a refine task still owns the saved draft snapshot.
	 *
	 * @param int               $post_id     Post ID.
	 * @param IC_AI_Integration $integration Integration.
	 * @param array             $state       Normalized task state.
	 *
	 * @return bool
	 */
	private function refine_task_snapshot_is_current( $post_id, $integration, $state ) {
		$expected = ! empty( $state['refine_snapshots'][ $post_id ] ) ? (string) $state['refine_snapshots'][ $post_id ] : '';
		$current  = $this->refine_saved_signature( $post_id, $integration );

		return '' !== $expected && '' !== $current && hash_equals( $expected, $current );
	}

	/**
	 * Deletes one saved AI preview response from a post.
	 *
	 * @param int $post_id Post ID.
	 *
	 * @return void
	 */
	private function delete_saved_preview_response( $post_id ) {
		delete_post_meta( $post_id, self::SAVED_PREVIEW_META_KEY );
	}

	/**
	 * Normalizes one preview response to the editor-safe saved shape.
	 *
	 * @param IC_AI_Integration $integration Integration.
	 * @param array             $response    Raw response.
	 *
	 * @return array
	 */
	private function normalize_preview_response( $integration, $response ) {
		$response   = is_array( $response ) ? $response : array();
		$target     = $integration instanceof IC_AI_Target ? $integration : $integration->target( 'post' );
		$allowed    = array_merge(
			$this->manager->client()->enabled_field_map( $target ),
			$this->manager->client()->custom_meta_field_map( $target, 'enhance' )
		);
		$normalized = array(
			'fields'     => array(),
			'meta'       => array(),
			'questions'  => array(),
			'qa_answers' => array(),
		);
		if ( ! empty( $response['fields'] ) && is_array( $response['fields'] ) ) {
			foreach ( $response['fields'] as $field_key => $value ) {
				if ( ! array_key_exists( $field_key, $allowed ) ) {
					continue;
				}

				$normalized['fields'][ $field_key ] = $this->normalize_preview_value( $value );
			}
		}
		if ( ! empty( $response['meta'] ) && is_array( $response['meta'] ) ) {
			$normalized['meta'] = $this->normalize_preview_value( $response['meta'] );
		}
		if ( ! empty( $response['questions'] ) && is_array( $response['questions'] ) ) {
			$normalized['questions'] = $this->normalize_preview_questions( $response['questions'] );
		}
		if ( ! empty( $response['qa_answers'] ) && is_array( $response['qa_answers'] ) ) {
			foreach ( $response['qa_answers'] as $question_id => $answer ) {
				$question_id = sanitize_text_field( (string) $question_id );
				if ( '' === $question_id ) {
					continue;
				}
				$normalized['qa_answers'][ $question_id ] = sanitize_text_field( (string) $answer );
			}
		}

		return $normalized;
	}

	/**
	 * Sanitizes clarifying questions for the saved preview shape.
	 *
	 * @param array $questions Raw questions payload.
	 *
	 * @return array
	 */
	private function normalize_preview_questions( $questions ) {
		if ( ! is_array( $questions ) ) {
			return array();
		}

		$normalized = array();
		foreach ( $questions as $entry ) {
			if ( ! is_array( $entry ) ) {
				continue;
			}
			$question = isset( $entry['question'] ) ? sanitize_text_field( (string) $entry['question'] ) : '';
			if ( '' === $question ) {
				continue;
			}
			$id = isset( $entry['id'] ) ? sanitize_text_field( (string) $entry['id'] ) : '';
			if ( '' === $id ) {
				$id = 'q' . ( count( $normalized ) + 1 );
			}
			$suggestions = array();
			if ( ! empty( $entry['suggestions'] ) && is_array( $entry['suggestions'] ) ) {
				foreach ( $entry['suggestions'] as $suggestion ) {
					if ( count( $suggestions ) >= 2 ) {
						break;
					}
					$suggestion = sanitize_text_field( (string) $suggestion );
					if ( '' !== $suggestion ) {
						$suggestions[] = $suggestion;
					}
				}
			}

			$normalized[] = array(
				'id'          => $id,
				'question'    => $question,
				'suggestions' => $suggestions,
			);
		}

		return $normalized;
	}

	/**
	 * Normalizes one preview value recursively without stripping editor HTML.
	 *
	 * @param mixed $value Raw preview value.
	 *
	 * @return array|string
	 */
	private function normalize_preview_value( $value ) {
		if ( is_object( $value ) ) {
			$value = (array) $value;
		}
		if ( is_array( $value ) ) {
			$normalized = array();
			foreach ( $value as $item_key => $item_value ) {
				$normalized[ $item_key ] = $this->normalize_preview_value( $item_value );
			}

			return $normalized;
		}
		if ( is_bool( $value ) ) {
			return $value ? '1' : '';
		}
		if ( null === $value ) {
			return '';
		}

		return (string) $value;
	}

	/**
	 * Collects the outgoing AI payload from the submitted form.
	 *
	 * @param IC_AI_Integration $integration Integration.
	 * @param int               $post_id     Current post ID.
	 * @param array             $parsed      Parsed form fields.
	 *
	 * @return array
	 */
	private function collect_payload( $integration, $post_id, $parsed ) {
		$target    = $integration instanceof IC_AI_Target ? $integration : $integration->target( 'post' );
		$payload   = array();
		$field_map = array_merge(
			$this->manager->client()->enabled_field_map( $integration ),
			$this->manager->client()->custom_meta_field_map( $integration, 'enhance' )
		);
		foreach ( $field_map as $field_key => $field ) {
			$field['target']    = $target;
			$field['field_key'] = $field_key;
			$value              = $this->collect_field_value( $field, $post_id, $parsed );
			if ( $this->should_include_payload_value( $field, $value ) ) {
				$payload[ $field_key ] = $value;
			}
		}

		return $payload;
	}

	/**
	 * Collects taxonomy target fields from a term and submitted editor values.
	 *
	 * @param IC_AI_Target $target   Taxonomy target.
	 * @param int          $term_id  Term ID.
	 * @param array        $parsed   Parsed form fields.
	 *
	 * @return array
	 */
	private function collect_target_term_payload( $target, $term_id, $parsed = array() ) {
		$term    = $this->request_target_object( $target, $term_id );
		$payload = array();
		if ( ! $term ) {
			return $payload;
		}
		$settings      = $this->manager->client()->target_settings( $target );
		$has_selection = array_key_exists( 'fields', $settings ) || array_key_exists( 'custom_enhance_meta', $settings );
		$selected      = array_unique( array_merge( (array) ( $settings['fields'] ?? array() ), (array) ( $settings['custom_enhance_meta'] ?? array() ) ) );
		$field_map     = array_merge( $this->target_field_map( $target ), (array) $this->manager->client()->custom_meta_field_map( $target, 'enhance' ) );
		foreach ( $field_map as $field_key => $field ) {
			if ( $has_selection && ! in_array( $field_key, $selected, true ) ) {
				continue; }
			$type  = $field['type'] ?? '';
			$value = '';
			if ( 'term_field' === $type ) {
				$field_name = (string) ( $field['term_field'] ?? $field_key );
				$value      = isset( $parsed[ $field_name ] ) ? sanitize_text_field( $parsed[ $field_name ] ) : ( isset( $term->{ $field_name } ) ? $term->{ $field_name } : '' );
				if ( 'description' === $field_name && isset( $parsed['description'] ) ) {
					$value = wp_kses_post( $parsed['description'] );
				}
			} elseif ( in_array( $type, array( 'term_meta', 'extension', 'post_meta' ), true ) ) {
				$value = $this->manager->client()->target_field_value( $target, $term_id, $field_key );
			}
			if ( 'html' === ( $field['value_type'] ?? $field['content_format'] ?? '' ) ) {
				$value = wp_kses_post( (string) $value );
			}
			if ( ( '' !== $value && array() !== $value ) || ( $has_selection && in_array( $field_key, $selected, true ) ) ) {
				$payload[ $field_key ] = $value;
			}
		}
		return $payload;
	}

	/**
	 * Collects the reference-only context payload from preserve-mode fields.
	 *
	 * Only non-empty values are included; context data is never generated.
	 *
	 * @param IC_AI_Integration $integration Integration.
	 * @param int               $post_id     Current post ID.
	 * @param array             $parsed      Parsed form fields.
	 *
	 * @return array
	 */
	private function collect_context_payload( $integration, $post_id, $parsed ) {
		$target    = $integration instanceof IC_AI_Target ? $integration : $integration->target( 'post' );
		$context   = array();
		$field_map = array_merge(
			$this->manager->client()->context_field_map( $integration ),
			$this->manager->client()->custom_meta_field_map( $integration, 'context' )
		);
		foreach ( $field_map as $field_key => $field ) {
			$field['target']    = $target;
			$field['field_key'] = $field_key;
			$value              = $this->collect_field_value( $field, $post_id, $parsed );
			if ( null === $value || '' === $value || array() === $value ) {
				continue;
			}

			$context[ $field_key ] = $value;
		}

		return $context;
	}

	/**
	 * Collects a single field value.
	 *
	 * @param array $field   Field definition.
	 * @param int   $post_id Post ID.
	 * @param array $parsed  Parsed form data.
	 *
	 * @return mixed
	 */
	private function collect_field_value( $field, $post_id, $parsed ) {
		$type = isset( $field['type'] ) ? $field['type'] : 'meta';
		if ( 'post_field' === $type ) {
			$form_key = ! empty( $field['form_field'] ) ? $field['form_field'] : '';
			if ( $form_key && isset( $parsed[ $form_key ] ) ) {
				return is_array( $parsed[ $form_key ] ) ? $parsed[ $form_key ] : wp_kses_post( $parsed[ $form_key ] );
			}
			if ( $post_id ) {
				$post       = get_post( $post_id );
				$post_field = ! empty( $field['post_field'] ) ? (string) $field['post_field'] : '';
				if ( $post instanceof WP_Post && '' !== $post_field && property_exists( $post, $post_field ) ) {
					return $post->{ $post_field };
				}
			}
		} elseif ( in_array( $type, array( 'meta', 'custom_meta', 'post_meta', 'term_meta', 'extension' ), true ) ) {
			$form_key = ! empty( $field['form_field'] ) ? $field['form_field'] : ( ! empty( $field['meta_key'] ) ? $field['meta_key'] : '' );
			if ( $form_key && isset( $parsed[ $form_key ] ) ) {
				return is_array( $parsed[ $form_key ] ) ? $parsed[ $form_key ] : sanitize_text_field( $parsed[ $form_key ] );
			}
			if ( $post_id && ! empty( $field['target'] ) && $field['target'] instanceof IC_AI_Target ) {
				return $this->manager->client()->target_field_value( $field['target'], $post_id, $field['field_key'] ?? '' );
			}
			if ( $post_id && ! empty( $field['meta_key'] ) ) {
				return get_post_meta( $post_id, $field['meta_key'], true );
			}
		} elseif ( 'group' === $type && ! empty( $field['group_fields'] ) && is_array( $field['group_fields'] ) ) {
			$group = array();
			foreach ( $field['group_fields'] as $group_key => $group_field ) {
				$group_value = $this->collect_field_value( $group_field, $post_id, $parsed );
				if ( $this->should_include_payload_value( $group_field, $group_value ) ) {
					$group[ $group_key ] = $group_value;
				}
			}

			return $group;
		} elseif ( 'taxonomy' === $type && ! empty( $field['taxonomy'] ) ) {
			$paths     = array();
			$tax_input = isset( $parsed['tax_input'][ $field['taxonomy'] ] ) ? $parsed['tax_input'][ $field['taxonomy'] ] : array();
			if ( ! is_array( $tax_input ) && is_string( $tax_input ) ) {
				$tax_input = array_map( 'trim', explode( ',', $tax_input ) );
			}
			if ( ! empty( $tax_input ) ) {
				foreach ( $tax_input as $term_id ) {
					$term = get_term( absint( $term_id ), $field['taxonomy'] );
					if ( $term && ! is_wp_error( $term ) ) {
						$paths[] = $this->taxonomy_path( $term );
					}
				}
			} elseif ( $post_id ) {
				$terms = wp_get_object_terms( $post_id, $field['taxonomy'] );
				if ( ! is_wp_error( $terms ) ) {
					foreach ( $terms as $term ) {
						$paths[] = $this->taxonomy_path( $term );
					}
				}
			}

			return array_values( array_filter( $paths ) );
		}

		return null;
	}

	/**
	 * Returns whether one collected field value should be sent to the AI request.
	 *
	 * Empty rewriteable fields and taxonomy suggestions are preserved so AI can
	 * generate missing content. Preserve-only factual fields still skip empty values.
	 *
	 * @param array $field Field definition.
	 * @param mixed $value Collected value.
	 *
	 * @return bool
	 */
	private function should_include_payload_value( $field, $value ) {
		if ( null === $value ) {
			return false;
		}
		if ( '' !== $value && array() !== $value ) {
			return true;
		}

		return $this->allows_empty_generation( $field );
	}

	/**
	 * Returns whether one field should be sent even when its current value is empty.
	 *
	 * @param array $field Field definition.
	 *
	 * @return bool
	 */
	private function allows_empty_generation( $field ) {
		if ( ! is_array( $field ) ) {
			return false;
		}

		$mode = $this->field_enhancement_mode( $field );
		if ( in_array( $mode, array( 'rewrite', 'suggest_taxonomy' ), true ) ) {
			return true;
		}

		if ( 'group' !== $this->field_type( $field ) || empty( $field['group_fields'] ) || ! is_array( $field['group_fields'] ) ) {
			return false;
		}

		foreach ( $field['group_fields'] as $group_field ) {
			if ( $this->allows_empty_generation( $group_field ) ) {
				return true;
			}
		}

		return false;
	}

	/**
	 * Resolves the normalized enhancement mode for one field.
	 *
	 * Thin wrapper around the shared client-side derivation.
	 *
	 * @param array $field Field definition.
	 *
	 * @return string
	 */
	private function field_enhancement_mode( $field ) {
		return $this->manager->client()->field_enhancement_mode( $field );
	}

	/**
	 * Returns the normalized field type.
	 *
	 * @param array $field Field definition.
	 *
	 * @return string
	 */
	private function field_type( $field ) {
		return ! empty( $field['type'] ) ? sanitize_key( (string) $field['type'] ) : 'meta';
	}

	/**
	 * Returns a hierarchical taxonomy path.
	 *
	 * @param WP_Term $term Term.
	 *
	 * @return array
	 */
	private function taxonomy_path( $term ) {
		$path = array( $term->name );
		while ( ! empty( $term->parent ) ) {
			$term = get_term( $term->parent, $term->taxonomy );
			if ( ! $term || is_wp_error( $term ) ) {
				break;
			}
			array_unshift( $path, $term->name );
		}

		return $path;
	}
}
