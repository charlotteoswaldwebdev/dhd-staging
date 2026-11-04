<?php
/**
 * The file that defines the core plugin class
 *
 * A class definition that includes attributes and functions used across both the
 * public-facing side of the site and the admin area.
 *
 * @link       https://wordpress-1657598-6759210.cloudwaysapps.com
 * @since      1.0.0
 *
 * @package    Heat_Loss_Calculator
 * @subpackage Heat_Loss_Calculator/includes
 */

/**
 * The core plugin class.
 *
 * This is used to define internationalization, admin-specific hooks, and
 * public-facing site hooks.
 *
 * Also maintains the unique identifier of this plugin as well as the current
 * version of the plugin.
 *
 * @since      1.0.0
 * @package    Heat_Loss_Calculator
 * @subpackage Heat_Loss_Calculator/includes
 * @author     Charlotte Oswald <web@charlotteoswald.com>
 */
class Heat_Loss_Calculator {

	/**
	 * The loader that's responsible for maintaining and registering all hooks that power
	 * the plugin.
	 *
	 * @since    1.0.0
	 * @access   protected
	 * @var      Heat_Loss_Calculator_Loader    $loader    Maintains and registers all hooks for the plugin.
	 */
	protected $loader;

	/**
	 * The unique identifier of this plugin.
	 *
	 * @since    1.0.0
	 * @access   protected
	 * @var      string    $plugin_name    The string used to uniquely identify this plugin.
	 */
	protected $plugin_name;

	/**
	 * The current version of the plugin.
	 *
	 * @since    1.0.0
	 * @access   protected
	 * @var      string    $version    The current version of the plugin.
	 */
	protected $version;

	/**
	 * Define the core functionality of the plugin.
	 *
	 * Set the plugin name and the plugin version that can be used throughout the plugin.
	 * Load the dependencies, define the locale, and set the hooks for the admin area and
	 * the public-facing side of the site.
	 *
	 * @since    1.0.0
	 */
	public function __construct() {
		if ( defined( 'HEAT_LOSS_CALCULATOR_VERSION' ) ) {
			$this->version = HEAT_LOSS_CALCULATOR_VERSION;
		} else {
			$this->version = '1.0.1';
		}
		$this->plugin_name = 'heat-loss-calculator';

		$this->load_dependencies();
		$this->set_locale();
		$this->define_admin_hooks();
		$this->define_public_hooks();
	}

	/**
	 * Load the required dependencies for this plugin.
	 *
	 * Include the following files that make up the plugin:
	 *
	 * - Heat_Loss_Calculator_Loader. Orchestrates the hooks of the plugin.
	 * - Heat_Loss_Calculator_i18n. Defines internationalization functionality.
	 * - Heat_Loss_Calculator_Admin. Defines all hooks for the admin area.
	 * - Heat_Loss_Calculator_Public. Defines all hooks for the public side of the site.
	 *
	 * Create an instance of the loader which will be used to register the hooks
	 * with WordPress.
	 *
	 * @since    1.0.0
	 * @access   private
	 */
	private function load_dependencies() {
		/**
		 * The class responsible for orchestrating the actions and filters of the
		 * core plugin.
		 */
		require_once plugin_dir_path( dirname( __FILE__ ) ) . 'includes/class-heat-loss-calculator-loader.php';

		/**
		 * The class responsible for defining internationalization functionality
		 * of the plugin.
		 */
		require_once plugin_dir_path( dirname( __FILE__ ) ) . 'includes/class-heat-loss-calculator-i18n.php';

		/**
		 * The class responsible for defining all actions that occur in the admin area.
		 */
		require_once plugin_dir_path( dirname( __FILE__ ) ) . 'admin/class-heat-loss-calculator-admin.php';

		/**
		 * The class responsible for defining all actions that occur in the public-facing
		 * side of the site.
		 */
		require_once plugin_dir_path( dirname( __FILE__ ) ) . 'public/class-heat-loss-calculator-public.php';

		// Load composer.
		require_once plugin_dir_path( dirname( __FILE__ ) ) . 'vendor/autoload.php';

		// Load our FPDF extension.
		// require_once plugin_dir_path( dirname( __FILE__ ) ) . 'fpdf/scripts/PDF.php';

		$this->loader = new Heat_Loss_Calculator_Loader();
	}

	/**
	 * Define the locale for this plugin for internationalization.
	 *
	 * Uses the Heat_Loss_Calculator_i18n class in order to set the domain and to register the hook
	 * with WordPress.
	 *
	 * @since    1.0.0
	 * @access   private
	 */
	private function set_locale() {
		$plugin_i18n = new Heat_Loss_Calculator_i18n();

		$this->loader->add_action( 'plugins_loaded', $plugin_i18n, 'load_plugin_textdomain' );
	}

	/**
	 * Register all of the hooks related to the admin area functionality
	 * of the plugin.
	 *
	 * @since    1.0.0
	 * @access   private
	 */
	private function define_admin_hooks() {
		$plugin_admin = new Heat_Loss_Calculator_Admin( $this->get_plugin_name(), $this->get_version() );

		$this->loader->add_action( 'admin_enqueue_scripts', $plugin_admin, 'enqueue_styles' );
		$this->loader->add_action( 'admin_enqueue_scripts', $plugin_admin, 'enqueue_scripts' );
	}

	/**
	 * Register all of the hooks related to the public-facing functionality
	 * of the plugin.
	 *
	 * @since    1.0.0
	 * @access   private
	 */
	private function define_public_hooks() {
		$version = true === WP_DEBUG ? gmdate( 'YmdHis' ) : $this->get_version();

		$plugin_public = new Heat_Loss_Calculator_Public( $this->get_plugin_name(), $version );

		// Resolve the visitor reference before anything is output, so the cookie
		// header can still be sent.
		$this->loader->add_action( 'init', $plugin_public, 'bootstrap_user_ref' );

		// Keep both calculator pages out of every page cache: they depend on a
		// per visitor cookie, a nonce and POST data.
		$this->loader->add_action( 'template_redirect', $plugin_public, 'prevent_caching', 1 );

		// Handle submissions before the theme renders, so a successful save can
		// redirect (POST / redirect / GET).
		$this->loader->add_action( 'template_redirect', $plugin_public, 'handle_post', 5 );

		$this->loader->add_action( 'wp_enqueue_scripts', $plugin_public, 'enqueue_styles' );
		$this->loader->add_action( 'wp_enqueue_scripts', $plugin_public, 'enqueue_scripts' );
		$this->loader->add_shortcode( 'hlc_form_page', $plugin_public, 'form_page_controller' );
		$this->loader->add_shortcode( 'hlc_results_page', $plugin_public, 'results_page_controller' );

		$this->loader->add_action( 'wp_ajax_generate_posts_pdf', $plugin_public, 'download_report' );
		$this->loader->add_action( 'wp_ajax_nopriv_generate_posts_pdf', $plugin_public, 'download_report' );
	}

	/**
	 * Run the loader to execute all of the hooks with WordPress.
	 *
	 * @since    1.0.0
	 */
	public function run() {
		$this->loader->run();
	}

	/**
	 * The name of the plugin used to uniquely identify it within the context of
	 * WordPress and to define internationalization functionality.
	 *
	 * @since     1.0.0
	 * @return    string    The name of the plugin.
	 */
	public function get_plugin_name() {
		return $this->plugin_name;
	}

	/**
	 * The reference to the class that orchestrates the hooks with the plugin.
	 *
	 * @since     1.0.0
	 * @return    Heat_Loss_Calculator_Loader    Orchestrates the hooks of the plugin.
	 */
	public function get_loader() {
		return $this->loader;
	}

	/**
	 * Retrieve the version number of the plugin.
	 *
	 * @since     1.0.0
	 * @return    string    The version number of the plugin.
	 */
	public function get_version() {
		return $this->version;
	}

}
