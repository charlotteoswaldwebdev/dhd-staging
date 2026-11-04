<?php
/**
 * The public-facing functionality of the plugin.
 *
 * @link       https://wordpress-1657598-6759210.cloudwaysapps.com
 * @since      1.0.0
 *
 * @package    Heat_Loss_Calculator
 * @subpackage Heat_Loss_Calculator/public
 */

/**
 * The public-facing functionality of the plugin.
 *
 * Defines the plugin name, version, and two examples hooks for how to
 * enqueue the public-facing stylesheet and JavaScript.
 *
 * @package    Heat_Loss_Calculator
 * @subpackage Heat_Loss_Calculator/public
 * @author     Charlotte Oswald <web@charlotteoswald.com>
 */
class Heat_Loss_Calculator_Public {

	/**
	 * The ID of this plugin.
	 *
	 * @since    1.0.0
	 * @access   private
	 * @var      string    $plugin_name    The ID of this plugin.
	 */
	private $plugin_name;

	/**
	 * The version of this plugin.
	 *
	 * @since    1.0.0
	 * @access   private
	 * @var      string    $version    The current version of this plugin.
	 */
	private $version;

	/**
	 * Form page
	 *
	 * @var string
	 */
	public $form_page = 'heat-loss-calculator';

	/**
	 * Results page
	 *
	 * @var string
	 */
	public $results_page = 'heat-loss-calculator-results';

	/**
	 * The current visitor reference.
	 *
	 * Resolved on `init`, which is early enough to still send the cookie.
	 *
	 * @var string
	 */
	public $user_ref = '';

	/**
	 * State carried over from a rejected submission.
	 *
	 * Populated by handle_post() on `template_redirect` and read back by the
	 * shortcode when it renders, so a failed save can redisplay the posted
	 * values together with its errors.
	 *
	 * @var array|null
	 */
	protected $submission = null;

	/**
	 * Rooms for the current visitor, resolved once per request.
	 *
	 * @var array|null
	 */
	protected $results_cache = null;

	/**
	 * Initialize the class and set its properties.
	 *
	 * @since    1.0.0
	 * @param      string $plugin_name       The name of the plugin.
	 * @param      string $version    The version of this plugin.
	 */
	public function __construct( $plugin_name, $version ) {
		require_once plugin_dir_path( __FILE__ ) . '../includes/class-heat-loss-calculator-cookie.php';
		require_once plugin_dir_path( __FILE__ ) . '../includes/class-heat-loss-calculator-model.php';
		require_once plugin_dir_path( __FILE__ ) . '../includes/class-heat-loss-calculator-to-pdf.php';
		require_once plugin_dir_path( __FILE__ ) . '../includes/class-heat-loss-calculator-u-calculator.php';

		$this->plugin_name = $plugin_name;
		$this->version     = $version;
	}

	/**
	 * Register the stylesheets for the public-facing side of the site.
	 *
	 * @since    1.0.0
	 */
	public function enqueue_styles() {

		/**
		 * This function is provided for demonstration purposes only.
		 *
		 * An instance of this class should be passed to the run() function
		 * defined in Heat_Loss_Calculator_Loader as all of the hooks are defined
		 * in that particular class.
		 *
		 * The Heat_Loss_Calculator_Loader will then create the relationship
		 * between the defined hooks and the functions defined in this
		 * class.
		 */

		wp_enqueue_style( $this->plugin_name, plugin_dir_url( __FILE__ ) . 'css/heat-loss-calculator-public.css', array(), $this->version, 'all' );

	}

	/**
	 * Register the JavaScript for the public-facing side of the site.
	 *
	 * @since    1.0.0
	 */
	public function enqueue_scripts() {

		/**
		 * This function is provided for demonstration purposes only.
		 *
		 * An instance of this class should be passed to the run() function
		 * defined in Heat_Loss_Calculator_Loader as all of the hooks are defined
		 * in that particular class.
		 *
		 * The Heat_Loss_Calculator_Loader will then create the relationship
		 * between the defined hooks and the functions defined in this
		 * class.
		 */

		wp_enqueue_script( $this->plugin_name, plugin_dir_url( __FILE__ ) . 'js/heat-loss-calculator-public.js', array( 'jquery' ), $this->version, false );
		wp_localize_script(
			$this->plugin_name,
			'ajax_object',
			array(
				'ajax_url' => admin_url( 'admin-ajax.php' ),
				'we_value' => 1234,
			)
		);

	}

	/**
	 * Resolve the visitor reference.
	 *
	 * Runs on `init`, before any output, so setcookie() can actually send its
	 * header. Previously the reference was minted from inside the shortcode,
	 * by which point headers were long gone and the cookie was never stored.
	 *
	 * @return void
	 */
	public function bootstrap_user_ref() {
		$cookies = new Heat_Loss_Calculator_Cookie();

		// Admin and admin-ajax requests read whatever the browser already sent
		// rather than minting a reference for crons, bots and REST callers.
		if ( is_admin() ) {
			$this->user_ref = $cookies->get_cookie();
			return;
		}

		$this->user_ref = $cookies->get_or_create();

		hlc_log( 'bootstrap_user_ref: user_ref=' . ( '' === $this->user_ref ? '(empty)' : $this->user_ref ) );
	}

	/**
	 * Keep the calculator pages out of every page cache.
	 *
	 * Both pages depend on a per visitor cookie, a nonce and POST data, so a
	 * shared cached copy hands one visitor another visitor's reference and a
	 * stale nonce. Breeze, WP Super Cache, W3TC and LiteSpeed all honour
	 * DONOTCACHEPAGE; the headers cover Cloudflare and any other edge cache.
	 *
	 * @return void
	 */
	public function prevent_caching() {
		if ( ! $this->is_calculator_page() ) {
			return;
		}

		if ( ! defined( 'DONOTCACHEPAGE' ) ) {
			define( 'DONOTCACHEPAGE', true );
		}
		if ( ! defined( 'DONOTCACHEOBJECT' ) ) {
			define( 'DONOTCACHEOBJECT', true );
		}
		if ( ! defined( 'DONOTCACHEDB' ) ) {
			define( 'DONOTCACHEDB', true );
		}

		if ( headers_sent() ) {
			return;
		}

		nocache_headers();
		header( 'Cache-Control: private, no-store, no-cache, must-revalidate, max-age=0' );
		// Tells Cloudflare not to serve this from its edge cache.
		header( 'CDN-Cache-Control: no-store' );
		header( 'Cloudflare-CDN-Cache-Control: no-store' );
	}

	/**
	 * Is this one of the calculator pages?
	 *
	 * @return boolean
	 */
	public function is_calculator_page() {
		if ( is_page( $this->form_page ) || is_page( $this->results_page ) ) {
			return true;
		}

		// A POST carrying an hlca action belongs to the calculator wherever it
		// lands, for instance when the pages are renamed.
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended
		return ! empty( $_REQUEST['hlca'] );
	}

	/**
	 * Handle a calculator submission.
	 *
	 * Runs on `template_redirect`, before the theme renders anything, which is
	 * what makes wp_safe_redirect() possible. The shortcode cannot redirect
	 * because it runs during the_content, after headers are sent.
	 *
	 * @return void
	 */
	public function handle_post() {
		$this->maybe_handle_delete();

		if ( empty( $_SERVER['REQUEST_METHOD'] ) || 'POST' !== $_SERVER['REQUEST_METHOD'] ) {
			return;
		}

		// phpcs:ignore WordPress.Security.NonceVerification.Missing
		$hlca = ( ! empty( $_POST['hlca'] ) ) ? sanitize_text_field( wp_unslash( $_POST['hlca'] ) ) : '';

		if ( ! in_array( $hlca, array( 'create_room', 'update_room' ), true ) ) {
			return;
		}

		hlc_log( 'handle_post: action=' . $hlca );

		// Guard 1: nonce. The field name and action must match the
		// wp_nonce_field() call in display_form() exactly.
		if ( empty( $_POST['calculator_verify'] )
			|| ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['calculator_verify'] ) ), 'heating_calculate' )
		) {
			hlc_log( 'handle_post: GUARD FAILED - nonce invalid or missing.' );
			$this->submission = array(
				'hlca'   => $hlca,
				'values' => wp_unslash( $_POST ), // phpcs:ignore WordPress.Security.NonceVerification.Missing
				'errors' => array(
					'_form' => 'This page had been open for too long, or was served from a cache. Please check your answers and submit again.',
				),
			);
			return;
		}

		// phpcs:ignore WordPress.Security.NonceVerification.Missing
		$data = wp_unslash( $_POST );

		// Guard 2: visitor reference. Always taken from the cookie resolved on
		// `init` rather than trusting the posted hidden field.
		$user_ref = $this->user_ref;
		if ( '' === $user_ref ) {
			$cookies  = new Heat_Loss_Calculator_Cookie();
			$user_ref = $cookies->get_cookie();
		}

		if ( '' === $user_ref ) {
			hlc_log( 'handle_post: GUARD FAILED - no user_ref available (cookies blocked?).' );
			$this->submission = array(
				'hlca'   => $hlca,
				'values' => $data,
				'errors' => array(
					'_form' => 'We could not identify your session. Please enable cookies for this site and try again.',
				),
			);
			return;
		}

		$data['user_ref'] = $user_ref;
		$model            = new Heat_Loss_Calculator_Model();

		if ( 'create_room' === $hlca ) {
			$result = $model->insert( $data );
		} else {
			// Guard 3: the row being updated must exist and belong to this visitor.
			$uid = ! empty( $data['uid'] ) ? sanitize_text_field( $data['uid'] ) : '';
			$old = '' !== $uid ? $model->find_one_by_uid( $uid ) : null;

			if ( ! $old ) {
				hlc_log( 'handle_post: GUARD FAILED - update_room with unknown uid "' . $uid . '".' );
				$this->submission = array(
					'hlca'   => $hlca,
					'values' => $data,
					'errors' => array( '_form' => 'That room could not be found. It may already have been deleted.' ),
				);
				return;
			}

			if ( $old->user_ref !== $user_ref ) {
				hlc_log( 'handle_post: GUARD FAILED - update_room uid "' . $uid . '" belongs to another user_ref.' );
				$this->submission = array(
					'hlca'   => $hlca,
					'values' => $data,
					'errors' => array( '_form' => 'That room could not be found. It may already have been deleted.' ),
				);
				return;
			}

			// The update rules require `id`, which the form does not post: it
			// identifies the row by uid. Supply it from the row we just loaded.
			$data['id'] = $old->id;

			$result = $model->update( $old->id, $data );
		}

		// Guard 4: validation and database errors, told apart so a silent
		// database failure can no longer look like a clean redisplay.
		if ( ! $result ) {
			$errors = $model->has_validation_errors() ? $model->validation_errors : array();

			if ( $model->has_db_error() ) {
				hlc_log( 'handle_post: GUARD FAILED - database error: ' . $model->get_db_error() );
				$errors['_form'] = 'Sorry, we could not save this room. Please try again, or call ' . HLC_COMPANY_PHONE . '.';
			} elseif ( empty( $errors ) ) {
				hlc_log( 'handle_post: GUARD FAILED - save returned false with no error detail.' );
				$errors['_form'] = 'Sorry, we could not save this room. Please try again, or call ' . HLC_COMPANY_PHONE . '.';
			} else {
				hlc_log( 'handle_post: GUARD FAILED - validation: ' . wp_json_encode( $errors ) );
			}

			$this->submission = array(
				'hlca'   => $hlca,
				'values' => $data,
				'errors' => $errors,
			);
			return;
		}

		hlc_log( 'handle_post: saved successfully, redirecting to results.' );

		// POST / redirect / GET so a refresh cannot resubmit the room.
		wp_safe_redirect( $this->results_url( 'view_results' ), 303 );
		exit;
	}

	/**
	 * Delete a saved room, then redirect.
	 *
	 * Runs from handle_post() on `template_redirect`. Deleting used to happen
	 * inside the shortcode, where wp_safe_redirect() could not work, so the
	 * delete URL stayed in the address bar and a refresh retried it.
	 *
	 * @return void
	 */
	public function maybe_handle_delete() {
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$hlca = ( ! empty( $_REQUEST['hlca'] ) ) ? sanitize_text_field( wp_unslash( $_REQUEST['hlca'] ) ) : '';

		if ( 'delete_result' !== $hlca ) {
			return;
		}

		$uid   = ( ! empty( $_REQUEST['uid'] ) ) ? sanitize_text_field( wp_unslash( $_REQUEST['uid'] ) ) : '';
		$nonce = ( ! empty( $_REQUEST['_hlcnonce'] ) ) ? sanitize_text_field( wp_unslash( $_REQUEST['_hlcnonce'] ) ) : '';

		if ( '' === $uid || ! wp_verify_nonce( $nonce, 'hlc_delete_' . $uid ) ) {
			hlc_log( 'maybe_handle_delete: GUARD FAILED - bad nonce or missing uid.' );
			wp_safe_redirect( $this->results_url( 'view_results' ), 303 );
			exit;
		}

		$model  = new Heat_Loss_Calculator_Model();
		$result = $model->find_one_by_uid( $uid );

		// Only ever delete a row belonging to the visitor asking for it.
		if ( ! $result || $result->user_ref !== $this->user_ref ) {
			hlc_log( 'maybe_handle_delete: GUARD FAILED - uid "' . $uid . '" missing or not this visitor.' );
			wp_safe_redirect( $this->results_url( 'view_results' ), 303 );
			exit;
		}

		$model->delete( $result->id );
		hlc_log( 'maybe_handle_delete: deleted uid "' . $uid . '".' );

		wp_safe_redirect( $this->results_url( 'view_results' ), 303 );
		exit;
	}

	/**
	 * URL of the results page.
	 *
	 * @param string $hlca Optional action to append.
	 *
	 * @return string
	 */
	public function results_url( $hlca = '' ) {
		$url = home_url( '/' . $this->results_page . '/' );

		return '' === $hlca ? $url : add_query_arg( 'hlca', $hlca, $url );
	}

	/**
	 * URL for deleting a saved room.
	 *
	 * @param string $uid Room uid.
	 *
	 * @return string
	 */
	public function delete_url( $uid ) {
		return add_query_arg(
			array(
				'hlca'      => 'delete_result',
				'uid'       => $uid,
				'_hlcnonce' => wp_create_nonce( 'hlc_delete_' . $uid ),
			),
			home_url( '/' . $this->results_page . '/' )
		);
	}

	/**
	 * URL of the form page.
	 *
	 * @param string $hlca Optional action to append.
	 * @param string $uid  Optional room uid to append.
	 *
	 * @return string
	 */
	public function form_url( $hlca = '', $uid = '' ) {
		$url  = home_url( '/' . $this->form_page . '/' );
		$args = array();

		if ( '' !== $hlca ) {
			$args['hlca'] = $hlca;
		}
		if ( '' !== $uid ) {
			$args['uid'] = $uid;
		}

		return empty( $args ) ? $url : add_query_arg( $args, $url );
	}

	/**
	 * Does this visitor have at least one saved room?
	 *
	 * @return boolean
	 */
	public function has_rooms() {
		if ( '' === $this->user_ref ) {
			return false;
		}

		return ! empty( $this->current_results() );
	}

	/**
	 * Form page controller
	 *
	 * @param array $atts Attributes.
	 * @return string
	 */
	public function form_page_controller( $atts ) {
		// A submission rejected by handle_post() is redisplayed here, with the
		// values the visitor typed and the reason it was rejected.
		if ( is_array( $this->submission ) ) {
			// Merge over the defaults so every field the form reads exists, even
			// if the request was truncated.
			$values = array_merge( $this->default_values(), $this->submission['values'] );

			return $this->display_form(
				$this->submission['hlca'],
				array_to_object( $values ),
				$this->submission['errors']
			);
		}

		// phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$hlca = ( ! empty( $_REQUEST['hlca'] ) ) ? sanitize_text_field( wp_unslash( $_REQUEST['hlca'] ) ) : null;

		switch ( $hlca ) {
			case 'edit_room':
				return $this->edit_room();
			case 'copy_edit_room':
				return $this->copy_edit_room();
			// create_room and update_room are handled on `template_redirect` by
			// handle_post(); reaching here means a bare GET, so show a fresh form.
			case 'add_room':
			default:
				return $this->add_room();
		}
	}

	/**
	 * Add room
	 *
	 * @return string
	 */
	public function add_room() {
		return $this->display_form( 'create_room', array_to_object( $this->default_values() ), null );
	}

	/**
	 * Default values for a blank form.
	 *
	 * @return array
	 */
	public function default_values() {
		return array(
			'id'                    => null,
			// uid is the saved room's own identifier, not a WordPress user id.
			// It is empty until the row is created, which is correct here.
			'uid'                   => '',
			'user_ref'              => $this->user_ref,
			'site_name'             => '',
			'room'                  => '',
			'year_built'            => 6,
			'inside_temp'           => 20,
			'outside_temp'          => -3,
			'floor_area'            => 0,
			'ceiling_height'        => 2.5,
			'exposed_external_area' => 0,
			'exposed_ceiling_area'  => 0,
			'exposed_floor_area'    => 0,
			'window_and_door_area'  => 0,
			'roof_light_area'       => 0,
		);
	}

	/**
	 * Edit room
	 *
	 * @return void
	 */
	public function edit_room() {
		// phpcs:disable WordPress.Security.NonceVerification.Recommended
		$uid = ( ! empty( $_REQUEST['uid'] ) ) ? sanitize_text_field( wp_unslash( $_REQUEST['uid'] ) ) : null;
		// phpcs:enable WordPress.Security.NonceVerification.Recommended

		if ( empty( $uid ) ) {
			return $this->add_room();
		}

		$model  = new Heat_Loss_Calculator_Model();
		$values = $model->find_one_by_uid( $uid );

		if ( ! $values || $values->user_ref !== $this->user_ref ) {
			hlc_log( 'edit_room: uid "' . $uid . '" not found for this user_ref.' );
			return $this->not_found_notice();
		}

		return $this->display_form( 'update_room', $values, null );
	}

	/**
	 * Copy and edit room
	 *
	 * @return void
	 */
	public function copy_edit_room() {
		// phpcs:disable WordPress.Security.NonceVerification.Recommended
		$uid = ( ! empty( $_REQUEST['uid'] ) ) ? sanitize_text_field( wp_unslash( $_REQUEST['uid'] ) ) : null;
		// phpcs:enable WordPress.Security.NonceVerification.Recommended

		if ( empty( $uid ) ) {
			return $this->add_room();
		}

		$model  = new Heat_Loss_Calculator_Model();
		$values = $model->find_one_by_uid( $uid );

		if ( ! $values || $values->user_ref !== $this->user_ref ) {
			hlc_log( 'copy_edit_room: uid "' . $uid . '" not found for this user_ref.' );
			return $this->not_found_notice();
		}

		$values->id   = null;
		$values->uid  = null;
		$values->room = null;

		return $this->display_form( 'create_room', $values, null );
	}

	/**
	 * Friendly notice for a room that cannot be found.
	 *
	 * @return string
	 */
	public function not_found_notice() {
		return '<div class="hlc-form-error">That room could not be found. It may already have been deleted.</div>'
			. '<p>' . $this->add_room_link() . '</p>';
	}

	/**
	 * Process form
	 *
	 * @deprecated 1.0.1 Submissions are handled by handle_post() on
	 *             `template_redirect`, which is early enough to redirect.
	 *
	 * @param string $hlca Form hlca.
	 *
	 * @return string|void
	 */
	public function process_form( $hlca ) {
		_deprecated_function( __METHOD__, '1.0.1', 'Heat_Loss_Calculator_Public::handle_post()' );

		$this->handle_post();

		return $this->form_page_controller( array() );
	}

	/**
	 * Results page controller
	 *
	 * @param array $atts Attributes.
	 * @return string
	 */
	public function results_page_controller( $atts ) {
		// phpcs:disable WordPress.Security.NonceVerification.Recommended
		$hlca = ( ! empty( $_REQUEST['hlca'] ) ) ? sanitize_text_field( wp_unslash( $_REQUEST['hlca'] ) ) : null;
		// phpcs:enable WordPress.Security.NonceVerification.Recommended

		switch ( $hlca ) {
			// Get actions.
			case 'view_results':
			default:
				return $this->view_results();
			case 'view_report':
				return $this->view_report();
			case 'download_report':
				return $this->download_report();
			case 'delete_result':
				return $this->delete_result();
		}
	}

	/**
	 * View results
	 *
	 * @return string|void
	 */
	public function view_results() {
		return $this->display_results( $this->current_results() );
	}

	/**
	 * Rooms saved by the current visitor.
	 *
	 * @return array
	 */
	public function current_results() {
		if ( is_array( $this->results_cache ) ) {
			return $this->results_cache;
		}

		if ( '' === $this->user_ref ) {
			hlc_log( 'current_results: no user_ref, returning no rooms.' );
			$this->results_cache = array();
			return $this->results_cache;
		}

		$model   = new Heat_Loss_Calculator_Model();
		$results = $model->find_all_by_user_ref( $this->user_ref );

		$this->results_cache = is_array( $results ) ? $results : array();

		return $this->results_cache;
	}

	/**
	 * View report
	 *
	 * @return string|void
	 */
	public function view_report() {
		return $this->display_report( $this->current_results() );
	}

	/**
	 * View report
	 *
	 * @return void
	 */
	public function download_report() {
		$cookies        = new Heat_Loss_Calculator_Cookie();
		$this->user_ref = '' !== $this->user_ref ? $this->user_ref : $cookies->get_cookie();

		$results = $this->current_results();

		if ( empty( $results ) ) {
			hlc_log( 'download_report: no rooms for this visitor, nothing to build.' );
			wp_die(
				esc_html__( 'There are no saved rooms to report on yet.', 'heat-loss-calculator' ),
				'',
				array( 'response' => 404 )
			);
		}

		$pdf = new Heat_Loss_Calculator_To_Pdf();
		$pdf->download( $results );
	}

	/**
	 * Delete result
	 *
	 * @return void
	 */
	public function delete_result() {
		// The delete itself is done by maybe_handle_delete() on
		// `template_redirect`, which then redirects. Reaching here means the
		// redirect did not happen, so just show what is left.
		return $this->display_results( $this->current_results() );
	}

	/**
	 * Form error
	 *
	 * @param string $key Key.
	 * @param array  $errors Errors.
	 *
	 * @return string
	 */
	public function form_error( $key, $errors ) {
		if ( isset( $errors ) && is_array( $errors ) && isset( $errors[ $key ] ) ) {
			return '<div class="hlc-form-error">' . $errors[ $key ] . '</div>';
		}
	}

	/**
	 * Form error input class
	 *
	 * @param string $key Key.
	 * @param array  $errors Errors.
	 *
	 * @return string
	 */
	public function form_error_input_class( $key, $errors ) {
		if ( isset( $errors ) && is_array( $errors ) && isset( $errors[ $key ] ) ) {
			return ' hlc-has-error';
		}
	}

	/**
	 * Display form nav
	 *
	 * @return string
	 */
	public function display_form_nav() {
		// Nothing to view until at least one room has been saved.
		if ( ! $this->has_rooms() ) {
			return '';
		}

		$html   = array();
		$html[] = '<div class="mb-1">';
		$html[] = $this->view_results_link();
		$html[] = $this->view_report_link();
		$html[] = '</div>';
		return join( '', $html );
	}

	/**
	 * Display a form level error
	 *
	 * Renders errors that have no field of their own, so a failure on
	 * `user_ref`, `uid` or the nonce can no longer be swallowed silently.
	 *
	 * @param array $errors Errors.
	 *
	 * @return string
	 */
	public function display_form_errors( $errors ) {
		if ( ! is_array( $errors ) || empty( $errors ) ) {
			return '';
		}

		// Fields that render their own inline message in the form below.
		$inline = array(
			'site_name',
			'room',
			'year_built',
			'inside_temp',
			'outside_temp',
			'floor_area',
			'ceiling_height',
			'exposed_external_area',
			'window_and_door_area',
			'exposed_ceiling_area',
			'roof_light_area',
			'exposed_floor_area',
		);

		$messages = array();
		foreach ( $errors as $key => $message ) {
			if ( ! in_array( $key, $inline, true ) ) {
				$messages[] = $message;
			}
		}

		if ( empty( $messages ) ) {
			// Everything is shown inline, but say so at the top of the form too.
			$messages[] = 'Please correct the highlighted fields below.';
		}

		$html   = array();
		$html[] = '<div class="hlc-form-error" role="alert">';
		foreach ( $messages as $message ) {
			$html[] = '<p>' . esc_html( $message ) . '</p>';
		}
		$html[] = '</div>';

		return join( '', $html );
	}

	/**
	 * Display form
	 *
	 * @param object $hlca hlca to submit.
	 * @param object $values Values.
	 * @param array  $errors Errors.
	 *
	 * @return string
	 */
	public function display_form( $hlca, $values, $errors ) {
		$html   = array();
		$html[] = $this->display_form_nav();

		$html[] = '<h1>Estimate how much heat your rooms require.</h1>';

		$html[] = $this->display_form_errors( $errors );

		$html[] = '<div>
			<ol style="list-style-position: outside;">
				<li>Fill in the form below and click ‘Calculate’. This will show the minimum output requirement of the
				heater needed for the space you have specified.</li>
				<li>Add other rooms to your list, for example ground floor or top floor rooms.</li>
				<li>Click ‘view report’ to see our recommendation for the specific heater required.</li>
				<li><strong>Adding subsequent rooms</strong><br>
				If you want to calculate for a number of rooms that are similar, but are perhaps on different floors
				for example – on the results page use the ‘COPY & AMEND’ feature to avoid having to
				input identical information again. If the room you wish to add is very different for
				example on a different site with different dimensions, then click the ADD ROOM button
				for a fresh start.</li>
			</ol>
			<p>
				This calculator provides a guide to the heating requirements, for the details you input,
				based on the building regulations in force at the time the building was constructed.
			</p>
			<p>
				The resulting calculations from this form should be treated as a guide and are not intended
				to replace a professionally prepared, detailed heat loss calculation.
			</p>
			<p>
				<strong>Required fields are marked with an asterisk <span class="hlc-required">*</span>.</strong>
			</p>
			<p>
				<a href="#general_notes"><strong>Please see general notes for instructions.</strong></a>
			</p>
		</div>';
		$html[] = '
		<form method="post" enctype="multipart/form-data" action="' . esc_url( $this->form_url() ) . '" class="hlc-form">
		' . wp_nonce_field( 'heating_calculate', 'calculator_verify', true, false ) . '
		<fieldset>
			<legend>Site details</legend>
			<div class="form-group">
				<label for="site_name">Site name <span class="hlc-required">*</span></label>
				<input name="site_name" id="site_name" type="text" value="' . esc_attr( (string) $values->site_name ) . '" class="' . $this->form_error_input_class( 'site_name', $errors ) . '">
				' . $this->form_error( 'site_name', $errors ) . '
			</div>
			<div class="form-group">
				<label for="room">Room ID / number <span class="hlc-required">*</span></label>
				<input name="room" id="room" type="text" value="' . esc_attr( (string) $values->room ) . '" class="' . $this->form_error_input_class( 'room', $errors ) . '">
				' . $this->form_error( 'room', $errors ) . '
			</div>
		</fieldset>
		<fieldset>
			<legend>Construction</legend>
			<p>
				<a href="#construction_notes" class="text-normal"><strong>Please see construction notes.</strong></a>
			</p>
			<div class="form-group">
				<label for="year_built">Year built <span class="hlc-required">*</span></label>
				<select name="year_built" id="year_built" class="' . $this->form_error_input_class( 'year_built', $errors ) . '">
					<option value=""' . is_selected( null, $values->year_built ) . '>Select...</option>
					<option value="1"' . is_selected( 1, $values->year_built ) . '>Pre 1976 - Call ' . HLC_COMPANY_PHONE . ' for advice.</option>
					<option value="2"' . is_selected( 2, $values->year_built ) . '>1976 to 1980</option>
					<option value="3"' . is_selected( 3, $values->year_built ) . '>1981 to 1994</option>
					<option value="4"' . is_selected( 4, $values->year_built ) . '>1995 to 2001</option>
					<option value="5"' . is_selected( 5, $values->year_built ) . '>2002 to 2012</option>
					<option value="6"' . is_selected( 6, $values->year_built ) . '>2013 to Present</option>
				</select>
				' . $this->form_error( 'year_built', $errors ) . '
			</div>
		</fieldset>
		<fieldset>
			<legend>Temperatures</legend>
			<div class="form-group">
				<label for="inside_temp"><strong>Inside</strong> temperature (°c) <span class="hlc-required">*</span></label>
				<select name="inside_temp" id="inside_temp" class="' . $this->form_error_input_class( 'inside_temp', $errors ) . '">
					<option value=""' . is_selected( null, $values->inside_temp ) . '>Select...</option>
					<option value="10"' . is_selected( 10, $values->inside_temp ) . '>10</option>
					<option value="11"' . is_selected( 11, $values->inside_temp ) . '>11</option>
					<option value="12"' . is_selected( 12, $values->inside_temp ) . '>12</option>
					<option value="13"' . is_selected( 13, $values->inside_temp ) . '>13</option>
					<option value="14"' . is_selected( 14, $values->inside_temp ) . '>14</option>
					<option value="15"' . is_selected( 15, $values->inside_temp ) . '>15</option>
					<option value="16"' . is_selected( 16, $values->inside_temp ) . '>16</option>
					<option value="17"' . is_selected( 17, $values->inside_temp ) . '>17</option>
					<option value="18"' . is_selected( 18, $values->inside_temp ) . '>18</option>
					<option value="19"' . is_selected( 19, $values->inside_temp ) . '>19</option>
					<option value="20"' . is_selected( 20, $values->inside_temp ) . '>20</option>
					<option value="21"' . is_selected( 21, $values->inside_temp ) . '>21</option>
					<option value="22"' . is_selected( 22, $values->inside_temp ) . '>22</option>
					<option value="23"' . is_selected( 23, $values->inside_temp ) . '>23</option>
					<option value="24"' . is_selected( 24, $values->inside_temp ) . '>24</option>
					<option value="25"' . is_selected( 25, $values->inside_temp ) . '>25</option>
					<option value="26"' . is_selected( 26, $values->inside_temp ) . '>26</option>
					<option value="27"' . is_selected( 27, $values->inside_temp ) . '>27</option>
					<option value="28"' . is_selected( 28, $values->inside_temp ) . '>28</option>
					<option value="29"' . is_selected( 29, $values->inside_temp ) . '>29</option>
					<option value="30"' . is_selected( 30, $values->inside_temp ) . '>30</option>
				</select>
				' . $this->form_error( 'inside_temp', $errors ) . '
			</div>
			<div class="form-group">
				<label for="outside_temp"><strong>Outside</strong> temperature (°c) <span class="hlc-required">*</span></label>
				<select name="outside_temp" id="outside_temp" class="' . $this->form_error_input_class( 'outside_temp', $errors ) . '">
					<option value=""' . is_selected( null, $values->outside_temp ) . '>Select...</option>
					<option value="-6"' . is_selected( -6, $values->outside_temp ) . '>-6</option>
					<option value="-5"' . is_selected( -5, $values->outside_temp ) . '>-5</option>
					<option value="-4"' . is_selected( -4, $values->outside_temp ) . '>-4</option>
					<option value="-3"' . is_selected( -3, $values->outside_temp ) . '>-3</option>
					<option value="-2"' . is_selected( -2, $values->outside_temp ) . '>-2</option>
					<option value="-1"' . is_selected( -1, $values->outside_temp ) . '>-1</option>
					<option value="0"' . is_selected( 0, $values->outside_temp ) . '>0</option>
					<option value="1"' . is_selected( 1, $values->outside_temp ) . '>1</option>
					<option value="2"' . is_selected( 2, $values->outside_temp ) . '>2</option>
					<option value="3"' . is_selected( 3, $values->outside_temp ) . '>3</option>
					<option value="4"' . is_selected( 4, $values->outside_temp ) . '>4</option>
					<option value="5"' . is_selected( 5, $values->outside_temp ) . '>5</option>
				</select>
				' . $this->form_error( 'outside_temp', $errors ) . '
			</div>
		</fieldset>
		<fieldset>
			<legend>Dimensions</legend>
			<div class="form-group">
				<label for="floor_area">Floor area  (&#13217;) <span class="hlc-required">*</span></label>
				<input name="floor_area" id="floor_area" type="text" value="' . esc_attr( (string) $values->floor_area ) . '" class="' . $this->form_error_input_class( 'floor_area', $errors ) . '">
				' . $this->form_error( 'floor_area', $errors ) . '
			</div>
			<div class="form-group">
				<label for="ceiling_height">Ceiling height (m) <span class="hlc-required">*</span></label>
				<input name="ceiling_height" id="ceiling_height" type="text" value="' . esc_attr( (string) $values->ceiling_height ) . '" class="' . $this->form_error_input_class( 'ceiling_height', $errors ) . '">
				' . $this->form_error( 'ceiling_height', $errors ) . '
			</div>
		</fieldset>
		<fieldset>
			<legend>Areas</legend>
			<p class="mb-1">
				<a href="#area_notes" class="text-normal"><strong>Please see area note.</strong></a>
			</p>
			<div class="form-group">
				<img class="hlc-room-info-img" src="/wp-content/plugins/heat-loss-calculator/public/assets/room-a.svg">
				<label class="hlc-img-lable" for="exposed_external_area"><strong>Outside wall</strong> area including windows and <br>doors (&#13217;) <span class="hlc-required">*</span></label>
				<input name="exposed_external_area" id="exposed_external_area" type="text" value="' . esc_attr( (string) $values->exposed_external_area ) . '" class="' . $this->form_error_input_class( 'exposed_external_area', $errors ) . '">
				' . $this->form_error( 'exposed_external_area', $errors ) . '
			</div>
			<div class="form-group">
				<img class="hlc-room-info-img" src="/wp-content/plugins/heat-loss-calculator/public/assets/room-e.svg">
				<label class="hlc-img-lable" for="window_and_door_area"><strong>Window and door</strong> area combined (&#13217;) <span class="hlc-required">*</span></label>
				<input name="window_and_door_area" id="window_and_door_area" type="text" value="' . esc_attr( (string) $values->window_and_door_area ) . '" class="' . $this->form_error_input_class( 'window_and_door_area', $errors ) . '">
				' . $this->form_error( 'window_and_door_area', $errors ) . '
			</div>
			<div class="form-group">
				<img class="hlc-room-info-img" src="/wp-content/plugins/heat-loss-calculator/public/assets/room-d.svg">
				<label class="hlc-img-lable" for="exposed_ceiling_area"><strong>For top floor rooms</strong> - Ceiling area including roof lights (leave as 0 if not a top floor room). (&#13217;) <span class="hlc-required">*</span></label>
				<input name="exposed_ceiling_area" id="exposed_ceiling_area" type="text" value="' . esc_attr( (string) $values->exposed_ceiling_area ) . '" class="' . $this->form_error_input_class( 'exposed_ceiling_area', $errors ) . '">
				' . $this->form_error( 'exposed_ceiling_area', $errors ) . '
			</div>
			<div class="form-group">
				<img class="hlc-room-info-img" src="/wp-content/plugins/heat-loss-calculator/public/assets/room-c.svg">
				<label class="hlc-img-lable" for="roof_light_area"><strong>Roof light</strong> area (&#13217;) <span class="hlc-required">*</span></label>
				<input name="roof_light_area" id="roof_light_area" type="text" value="' . esc_attr( (string) $values->roof_light_area ) . '" class="' . $this->form_error_input_class( 'roof_light_area', $errors ) . '">
				' . $this->form_error( 'roof_light_area', $errors ) . '
			</div>
			<div class="form-group">
				<img class="hlc-room-info-img" src="/wp-content/plugins/heat-loss-calculator/public/assets/room-b.svg">
				<label class="hlc-img-lable" for="exposed_floor_area"><strong>For ground floor rooms</strong> - Floor area (leave as 0 if not a ground floor room) (&#13217;) <span class="hlc-required">*</span></label>
				<input name="exposed_floor_area" id="exposed_floor_area" type="text" value="' . esc_attr( (string) $values->exposed_floor_area ) . '" class="' . $this->form_error_input_class( 'exposed_floor_area', $errors ) . '">
				' . $this->form_error( 'exposed_floor_area', $errors ) . '
			</div>
		</fieldset>
		<input name="hlca" id="hlca" type="hidden" value="' . esc_attr( (string) $hlca ) . '">
		<input name="uid" id="uid" type="hidden" value="' . esc_attr( (string) $values->uid ) . '">
		<input name="user_ref" id="user_ref" type="hidden" value="' . esc_attr( (string) ( '' !== $this->user_ref ? $this->user_ref : $values->user_ref ) ) . '">
		<input type="submit" value="Calculate" class="margin-label btn btn-primary btn-sm">
	</form>';

		$html[] = '<h3 id="general_notes">General notes</h3>
			<ul>
				<li>Internal heat transfer between spaces of different temperatures are not included.</li>
				<li>A 10% margin is included on the final room heat loss.</li>
				<li>Air changes rates have been assumed that will allow for a selected emitter to recover temperature should a window be opened for a short period.</li>
			</ul>

			<h3 id="construction_notes">Construction notes</h3>
			<ul>
				<li>Construction dates generally reflect Building Regulation U values however for simplicity some worst case allowances have been carrried forward.</li>
				<li>2013 to Present U values are representative for commercial buildings and have been based on the domestic building allowance, assuming a reasonable whole build carbon balance was taken.</li>
				<li>If an older building has been renovated please use the most recent date of renovation.</li>
			</ul>
			<h3 id="area_notes">Area note</h3>
			<ul>
				<li>Only input areas for surfaces which have an external surface. For example, do not enter a floor when there is a room below; or a ceiling when there is a room above.</li>
			</ul>';

		return join( '', $html );
	}

	/**
	 * Display results nav
	 *
	 * @return string
	 */
	public function display_results_nav() {
		$html   = array();
		$html[] = '<div class="mb-1">';
		$html[] = $this->add_room_link();
		if ( $this->has_rooms() ) {
			$html[] = $this->view_report_link();
		}
		$html[] = '</div>';
		return join( '', $html );
	}

	/**
	 * Display results
	 *
	 * @param array $results Results Array of Objects.
	 *
	 * @return string
	 */
	public function display_results( $results ) {
		$html   = array();
		$html[] = $this->display_results_nav();

		$html[] = '<p class="mb-1">Use the &lsquo;Copy and amend&rsquo; feature to add a room with similar values.</p>';

		$html[] = '<table class="hlc-room-details-table">
			<thead>
				<tr>
					<th>
						Room
					</th>
					<th>
						Site name
					</th>
					<th>
						<!-- total load -->
						kW Output required
					</th>
					<th>
						<!-- Edit -->
					</th>
					<th>
						<!-- Copy and amend -->
					</th>
					<th>
						<!-- Delete -->
					</th>
				</tr>
			</thead>
			<tbody>';
		if ( ! $results || count( $results ) === 0 ) {
			$html[] = '<tr>
				<td colspan="6">
					<strong>No results were found. Please add a room.</strong>
				</td>
			</tr>';
		}

		foreach ( $results as $row ) {
			$puc = new Heat_Loss_Calculator_U_Calculator();

			$values = array(
				'id'                    => $row->id,
				'uid'                   => $row->uid,
				'user_ref'              => $row->user_ref,
				'site_name'             => $row->site_name,
				'room'                  => $row->room,
				'year_built'            => $row->year_built,
				'inside_temp'           => $row->inside_temp,
				'outside_temp'          => $row->outside_temp,
				'floor_area'            => $row->floor_area,
				'ceiling_height'        => $row->ceiling_height,
				'exposed_external_area' => $row->exposed_external_area,
				'exposed_ceiling_area'  => $row->exposed_ceiling_area,
				'exposed_floor_area'    => $row->exposed_floor_area,
				'window_and_door_area'  => $row->window_and_door_area,
				'roof_light_area'       => $row->roof_light_area,
			);

			$puc->calculate_heating( $values );

			$html[] = '<tr>
				<td>
					' . esc_html( (string) $puc->room ) . '
				</td>
				<td>
					' . esc_html( (string) $puc->site_name ) . '
				</td>
				<td>
					' . number_format( $puc->total_load / 1000, 2, '.', '' ) . '
				</td>
				<td class="min-width text-nowrap">
					<a href="' . esc_url( $this->form_url( 'copy_edit_room', $puc->uid ) ) . '" class="btn btn-primary btn-sm"><strong>Copy and amend</strong></a>
				</td>
				<td class="min-width text-nowrap">
					<a href="' . esc_url( $this->form_url( 'edit_room', $puc->uid ) ) . '">Edit</a>
				</td>
				<td class="min-width text-nowrap">
					<a href="' . esc_url( $this->delete_url( $puc->uid ) ) . '">Delete</a>
				</td>
			</tr>';
		}

			$html[] = '</tbody>
		</table>';

		return join( '', $html );
	}

	/**
	 * Display report nav
	 *
	 * @return string
	 */
	public function display_report_nav() {
		$html   = array();
		$html[] = '<div class="mb-1">';
		$html[] = $this->add_room_link();
		if ( $this->has_rooms() ) {
			$html[] = $this->view_results_link();
			$html[] = $this->download_report_link();
		}
		$html[] = '</div>';
		return join( '', $html );
	}

	/**
	 * Display report
	 *
	 * @param array $results Results Array of Objects.
	 *
	 * @return string
	 */
	public function display_report( $results ) {
		$html   = array();
		$html[] = $this->display_report_nav();

		if ( ! $results || count( $results ) === 0 ) {
			$html[] = '<div><strong>No reports were found.  Please add a room.</strong></div>';
		}

		foreach ( $results as $row ) {
			$puc = new Heat_Loss_Calculator_U_Calculator();

			$values = array(
				'id'                    => $row->id,
				'uid'                   => $row->uid,
				'user_ref'              => $row->user_ref,
				'site_name'             => $row->site_name,
				'room'                  => $row->room,
				'year_built'            => $row->year_built,
				'inside_temp'           => $row->inside_temp,
				'outside_temp'          => $row->outside_temp,
				'floor_area'            => $row->floor_area,
				'ceiling_height'        => $row->ceiling_height,
				'exposed_external_area' => $row->exposed_external_area,
				'exposed_ceiling_area'  => $row->exposed_ceiling_area,
				'exposed_floor_area'    => $row->exposed_floor_area,
				'window_and_door_area'  => $row->window_and_door_area,
				'roof_light_area'       => $row->roof_light_area,
			);

			$puc->calculate_heating( $values );

			$html[] = '<table class="hlc-room-details-table">
			<thead>
				<tr>
					<th colspan="2">
						Site name: ' . esc_html( (string) $puc->site_name ) . '
					</th>
					<th>
						Room: ' . esc_html( (string) $puc->room ) . '
					</th>
					<th colspan="2">
						Output requirement: <strong>' . number_format( $puc->total_load / 1000, 2, '.', '' ) . ' kW</strong>.
						</th>
					<th colspan="2">
						 ' . $this->heater( number_format( $puc->total_load / 1000, 1, '.', '' ) ) . '
					</th>
				</tr>
			</thead>
			<tfoot>
				<tr>
					<td colspan="4">

					<td class="text-nowrap">
						<a href="' . esc_url( $this->form_url( 'copy_edit_room', $puc->uid ) ) . '" class="btn btn-primary btn-sm"><strong>Copy and amend</strong></a>
					</td>
					<td>
						<a href="' . esc_url( $this->form_url( 'edit_room', $puc->uid ) ) . '">Edit</a>
					</td>
					<td>
						<a href="' . esc_url( $this->delete_url( $puc->uid ) ) . '">Delete</a>
					</td>
				</tr>
			</tfoot>
			<tbody>
				<tr>
					<td>
						<span class="hlc-key">Temperatures</span>
					</td>
					<td>
						<span class="hlc-value-heading">Inside</span>
						<span class="hlc-value">' . $puc->inside_temp . ' °c</span>
					</td>
					<td>
						<span class="hlc-value-heading">Outside</span>
						<span class="hlc-value">' . $puc->outside_temp . ' °c</span>
					</td>
					<td colspan="5">
					</td>
				</tr>
				<tr>
					<td>
						<span class="hlc-key">Dimensions</span>
					</td>
					<td>
						<span class="hlc-value-heading">Floor:</span>
						<span class="hlc-value">' . $puc->floor_area . ' &#13217;</span>
					</td>
					<td>
						<span class="hlc-value-heading">Ceiling Height:</span>
						<span class="hlc-value">' . $puc->ceiling_height . ' m</span>
					</td>
					<td colspan="5">
					</td>
				</tr>
				<tr>
					<td>
						<span class="hlc-key">Areas</span>
					</td>
					<td>
						<!-- Exposed external wall area including windows and doors: -->
						<span class="hlc-value-heading">Exposed external walls:</span>
						<span class="hlc-value">' . $puc->exposed_external_area . ' &#13217;</span>
					</td>
					<td>
						<!-- Exposed ceiling/roof area including roof lights: -->
						<span class="hlc-value-heading">Exposed ceiling/roof:</span>
						<span class="hlc-value">' . $puc->exposed_ceiling_area . ' &#13217;</span>
					</td>
					<td>
						<span class="hlc-value-heading">Exposed floor:</span>
						<span class="hlc-value">' . $puc->exposed_floor_area . ' &#13217;</span>
					</td>
					<td>
						<!-- Window and door area combined -->
						<span class="hlc-value-heading">Window and door:</span>
						<span class="hlc-value">' . $puc->window_and_door_area . ' &#13217;</span>
					</td>
					<td>
						<span class="hlc-value-heading">Roof light:</span>
						<span class="hlc-value">' . $puc->roof_light_area . ' &#13217;</span>
					</td>
					<td>
					</td>
				</tr>
				<tr>
					<td>
						<span class="hlc-key">Heat Loss</span>
					</td>
					<td>
						<span class="hlc-value-heading">Infiltration:</span>
						<span class="hlc-value">' . ceil( $puc->infiltration_heat_loss ) . ' W</span>
					</td>
					<td>
						<span class="hlc-value-heading">Exposed external wall:</span>
						<span class="hlc-value">' . ceil( $puc->exposed_external_wall_heat_loss ) . ' W</span>
					</td>
					<td>
						<span class="hlc-value-heading">Window/door:</span>
						<span class="hlc-value">' . ceil( $puc->window_door_heat_loss ) . ' W</span>
					</td>
					<td>
						<span class="hlc-value-heading">Ceiling/roof:</span>
						<span class="hlc-value">' . ceil( $puc->ceiling_roof_heat_loss ) . ' W</span>
					</td>
					<td>
						<span class="hlc-value-heading">Roof light:</span>
						<span class="hlc-value">' . ceil( $puc->roof_light_heat_loss ) . ' W</span>
					</td>
					<td>
						<span class="hlc-value-heading">Exposed floor:</span>
						<span class="hlc-value">' . ceil( $puc->exposed_floor_heat_loss ) . ' W</span>
					</td>
				</tr>
				<tr>
					<td>
						<span class="hlc-key">Totals</span>
					</td>
					<td>
						<span class="hlc-value-heading">Total:</span>
						<span class="hlc-value">' . ceil( $puc->total ) . ' W</span>
					</td>
					<td>
						<span class="hlc-value-heading">Typical 10% margin:</span>
						<span class="hlc-value">' . ceil( $puc->total_with_margin ) . ' W</span>
					</td>
					<td>
						<span class="hlc-value-heading">Total Load:</span>
						<span class="hlc-value">' . ceil( $puc->total_load ) . ' W</span>
					</td>
					<td colspan="3">
						<span class="hlc-value-heading">Watts per &#13217;:</span>
						<span class="hlc-value">' . ceil( $puc->total_ref ) . ' W</span>
					</td>
				</tr>
			</tbody>

		</table>';
		}

		return join( '', $html );
	}

	/**
	 * Add room link
	 *
	 * @return string
	 */
	public function add_room_link() {
		return '<a href="' . esc_url( $this->form_url( 'add_room' ) ) . '" class="btn btn-primary btn-sm mr-2">Add room</a>';
	}

	/**
	 * View results link
	 *
	 * @return string
	 */
	public function view_results_link() {
		return '<a href="' . esc_url( $this->results_url( 'view_results' ) ) . '" class="btn btn-primary btn-sm mr-2">View results</a>';
	}

	/**
	 * View site results link
	 *
	 * @return string
	 */
	public function view_report_link() {
		return '<a href="' . esc_url( $this->results_url( 'view_report' ) ) . '" class="btn btn-primary btn-sm mr-2">View report</a>';
	}

	/**
	 * Download site results link
	 *
	 * @return string
	 */
	public function download_report_link() {
		return '<form method="post" id="as-fdpf-form" style="display:inline-block">
			<button class="btn btn-primary btn-sm mr-2 hlc-download-pdf" type="submit" name="generate_posts_pdf" value="generate">Download PDF</button>
		</form>';
	}

	/**
	 * Nearest heater
	 *
	 * @param float|integer $value Value in kW.
	 *
	 * @return float
	 */
	public function nearest_heater( $value ) {
		// kW.
		$array = array(
			0.5,
			0.75,
			1,
			1.5,
			2,
			2.5,
		);

		if ( $value > 2.5 ) {
			return 2.5;
		}

		foreach ( $array as $kw ) {
			if ( $value < $kw ) {
				return $kw;
			}
		}
	}

	/**
	 * Heater
	 *
	 * @param float|integer $value Value in kW.
	 *
	 * @return float
	 */
	public function heater( $value ) {
		if ( floatval( $value ) === 0.0 || $value > 5 ) {
			return 'Please contact ' . HLC_COMPANY_NAME . ' for advice on heating this space.';
		}

		if ( $value > 2.5 ) {
			$divided = ( $value / 2 );
			$heater  = $this->nearest_heater( $divided );
			return 'We recommend 2 x <strong>' . $heater . ' kW heater</strong>.';
		}

		if ( $value <= 2.5 ) {
			$heater = $this->nearest_heater( $value );
			return 'We recommend a <strong>' . $heater . ' kW heater</strong>.';
		}
	}

}
