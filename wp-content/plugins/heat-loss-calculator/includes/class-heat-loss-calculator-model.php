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

// require_once plugin_dir_path( __FILE__ ) . '../framework/models/class-wpf-base-model.php';

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
class Heat_Loss_Calculator_Model {

	/**
	 * Table name
	 *
	 * @var string
	 */
	protected $table_name;

	/**
	 * Output
	 *
	 * OBJECT – result will be output as an object.
	 * ARRAY_A – result will be output as an associative array.
	 * ARRAY_N – result will be output as a numerically indexed array.
	 *
	 * @var int
	 */
	protected $output = OBJECT;

	/**
	 * Validation errors
	 *
	 * @var array
	 */
	public $validation_errors = array();

	/**
	 * Last database error
	 *
	 * Captured from $wpdb->last_error so a failed write can be told apart from
	 * a failed validation by the caller.
	 *
	 * @var string
	 */
	public $db_error = '';

	/**
	 * Skip validation
	 *
	 * Optionally skip the validation. Used in conjunction with
	 * skip_validation() to skip data validation for any future calls.
	 *
	 * @var boolean
	 */
	protected $skip_validation = FALSE;

	/**
	 * Insert validation rules
	 *
	 * @var array
	 */
	public $insert_validation_rules = array(
		array(
			'field' => 'uid',
			'label' => 'Uid',
			'rules' => 'trim|max_length[191]',
		),
		array(
			'field' => 'user_ref',
			'label' => 'User ref',
			'rules' => 'trim|max_length[191]|required',
		),
		array(
			'field' => 'site_name',
			'label' => 'Site name',
			'rules' => 'trim|max_length[191]|required',
		),
		array(
			'field' => 'room',
			'label' => 'Room ID / number',
			'rules' => 'trim|max_length[191]|required',
		),
		array(
			'field' => 'year_built',
			'label' => 'Year built',
			'rules' => 'trim|integer|greater_than_equal_to[1]|less_than_equal_to[6]|required',
		),
		array(
			'field' => 'inside_temp',
			'label' => 'Inside Temperature',
			'rules' => 'trim|integer|greater_than_equal_to[10]|less_than_equal_to[26]|required',
		),
		array(
			'field' => 'outside_temp',
			'label' => 'Outside Temperature',
			'rules' => 'trim|integer|greater_than_equal_to[-4]|less_than_equal_to[4]|required',
		),
		array(
			'field' => 'floor_area',
			'label' => 'Floor area',
			'rules' => 'trim|numeric|greater_than_equal_to[1]|required',
		),
		array(
			'field' => 'ceiling_height',
			'label' => 'Ceiling height',
			'rules' => 'trim|numeric|greater_than_equal_to[1]|required',
		),
		array(
			'field' => 'exposed_external_area',
			'label' => 'Exposed external area',
			'rules' => 'trim|numeric|greater_than_equal_to[0]|required',
		),
		array(
			'field' => 'exposed_ceiling_area',
			'label' => 'Exposed ceiling area',
			'rules' => 'trim|numeric|greater_than_equal_to[0]|required',
		),
		array(
			'field' => 'exposed_floor_area',
			'label' => 'Exposed floor area',
			'rules' => 'trim|numeric|greater_than_equal_to[0]|required',
		),
		array(
			'field' => 'window_and_door_area',
			'label' => 'Window and door area',
			'rules' => 'trim|numeric|greater_than_equal_to[0]|required',
		),
		array(
			'field' => 'roof_light_area',
			'label' => 'Roof light area',
			'rules' => 'trim|numeric|greater_than_equal_to[0]|required',
		),
	);

	/**
	 * Update validation rules
	 *
	 * @var array
	 */
	public $update_validation_rules = array(
		array(
			'field' => 'id',
			'label' => 'Id',
			'rules' => 'trim|integer|required',
		),
		array(
			'field' => 'uid',
			'label' => 'Uid',
			'rules' => 'trim|max_length[191]|required',
		),
		array(
			'field' => 'user_ref',
			'label' => 'User ref',
			'rules' => 'trim|max_length[191]|required',
		),
		array(
			'field' => 'site_name',
			'label' => 'Site name',
			'rules' => 'trim|max_length[191]|required',
		),
		array(
			'field' => 'room',
			'label' => 'Room ID / number',
			'rules' => 'trim|max_length[191]|required',
		),
		array(
			'field' => 'year_built',
			'label' => 'Year built',
			'rules' => 'trim|integer|greater_than_equal_to[1]|less_than_equal_to[6]|required',
		),
		array(
			'field' => 'inside_temp',
			'label' => 'Inside Temperature',
			'rules' => 'trim|integer|greater_than_equal_to[10]|less_than_equal_to[26]|required',
		),
		array(
			'field' => 'outside_temp',
			'label' => 'Outside Temperature',
			'rules' => 'trim|integer|greater_than_equal_to[-4]|less_than_equal_to[4]|required',
		),
		array(
			'field' => 'floor_area',
			'label' => 'Floor area',
			'rules' => 'trim|numeric|greater_than_equal_to[1]|required',
		),
		array(
			'field' => 'ceiling_height',
			'label' => 'Ceiling height',
			'rules' => 'trim|numeric|greater_than_equal_to[1]|required',
		),
		array(
			'field' => 'exposed_external_area',
			'label' => 'Exposed external area',
			'rules' => 'trim|numeric|greater_than_equal_to[0]|required',
		),
		array(
			'field' => 'exposed_ceiling_area',
			'label' => 'Exposed ceiling area',
			'rules' => 'trim|numeric|greater_than_equal_to[0]|required',
		),
		array(
			'field' => 'exposed_floor_area',
			'label' => 'Exposed floor area',
			'rules' => 'trim|numeric|greater_than_equal_to[0]|required',
		),
		array(
			'field' => 'window_and_door_area',
			'label' => 'Window and door area',
			'rules' => 'trim|numeric|greater_than_equal_to[0]|required',
		),
		array(
			'field' => 'roof_light_area',
			'label' => 'Roof light area',
			'rules' => 'trim|numeric|greater_than_equal_to[0]|required',
		),
	);

	/**
	 * Construct
	 */
	public function __construct() {
		require_once plugin_dir_path( __FILE__ ) . 'class-heat-loss-calculator-form-validation.php';
		global $wpdb;
		$this->wpdb       = $wpdb;
		$this->table_name = $this->wpdb->prefix . 'heat_loss_calculator';
	}

	/**
	 * Set output
	 *
	 * @param int $type Output type.
	 *
	 * @return void
	 */
	public function set_output( $type ) {
		$this->output = $type;
	}

	/**
	 * Insert
	 *
	 * @param array $values Values.
	 *
	 * @return int|boolean
	 */
	public function insert( $data ) {
		$this->validation_errors = array();
		$this->db_error          = '';

		$validate = $this->validate($data, 'insert');

		if ( ! $validate ) {
			hlc_log( 'insert: validation failed: ' . wp_json_encode( $this->validation_errors ) );
			return false;
		}

		$now = gmdate( 'Y-m-d H:i:s' );

		$data = array(
			'uid'                   => wp_generate_uuid4(),
			'user_ref'              => $data['user_ref'],
			'site_name'             => $data['site_name'],
			'room'                  => $data['room'],
			'year_built'            => $data['year_built'],
			'inside_temp'           => $data['inside_temp'],
			'outside_temp'          => $data['outside_temp'],
			'floor_area'            => $data['floor_area'],
			'ceiling_height'        => $data['ceiling_height'],
			'exposed_external_area' => $data['exposed_external_area'],
			'exposed_ceiling_area'  => $data['exposed_ceiling_area'],
			'exposed_floor_area'    => $data['exposed_floor_area'],
			'window_and_door_area'  => $data['window_and_door_area'],
			'roof_light_area'       => $data['roof_light_area'],
			'created_at'            => $now,
			'updated_at'            => $now,
			'deleted_at'            => null,
		);

		$types = array(
			'%s',
			'%s',
			'%s',
			'%s',
			'%d',
			'%d',
			'%d',
			'%f',
			'%f',
			'%f',
			'%f',
			'%f',
			'%f',
			'%f',
			'%s',
			'%s',
			'%s',
		);

		$result = $this->wpdb->insert( $this->table_name, $data, $types );

		if ( 1 === $result ) {
			return $this->wpdb->insert_id;
		}

		$this->db_error = (string) $this->wpdb->last_error;
		hlc_log(
			sprintf( 'insert: $wpdb->insert into %1$s failed. last_error=%2$s', $this->table_name, $this->db_error )
		);

		return false;
	}

	/**
	 * Update
	 *
	 * @param array $id Primary key.
	 * @param array $values Values.
	 *
	 * @return boolean|int
	 */
	public function update( $id, $values ) {
		$this->validation_errors = array();
		$this->db_error          = '';

		if ( ! $this->validate( $values, 'update' ) ) {
			hlc_log( 'update: validation failed: ' . wp_json_encode( $this->validation_errors ) );
			return false;
		}

		$now = gmdate( 'Y-m-d H:i:s' );

		// phpcs:disable Squiz.PHP.CommentedOutCode.Found
		$data = array(
			// 'uid'                   => $values['uid'],
			// 'user_ref'              => $values['user_ref'],
			'site_name'             => $values['site_name'],
			'room'                  => $values['room'],
			'year_built'            => $values['year_built'],
			'inside_temp'           => $values['inside_temp'],
			'outside_temp'          => $values['outside_temp'],
			'floor_area'            => $values['floor_area'],
			'ceiling_height'        => $values['ceiling_height'],
			'exposed_external_area' => $values['exposed_external_area'],
			'exposed_ceiling_area'  => $values['exposed_ceiling_area'],
			'exposed_floor_area'    => $values['exposed_floor_area'],
			'window_and_door_area'  => $values['window_and_door_area'],
			'roof_light_area'       => $values['roof_light_area'],
			// 'created_at'            => $now,
			'updated_at'            => $now,
			'deleted_at'            => null,
		);
		// phpcs:enable Squiz.PHP.CommentedOutCode.Found

		$where = array(
			'id' => $id,
		);

		$types = array(
			// '%s',
			// '%s',
			'%s',
			'%s',
			'%d',
			'%d',
			'%d',
			'%f',
			'%f',
			'%f',
			'%f',
			'%f',
			'%f',
			'%f',
			// '%s',
			'%s',
			'%s',
		);

		$result = $this->wpdb->update( $this->table_name, $data, $where, $types );

		if ( false === $result ) {
			$this->db_error = (string) $this->wpdb->last_error;
			hlc_log(
				sprintf( 'update: $wpdb->update on %1$s failed. last_error=%2$s', $this->table_name, $this->db_error )
			);
			return false;
		}

		// A row that was saved with identical values reports 0 affected rows.
		return true;
	}

	/**
	 * Delete
	 *
	 * @param array $id Primary key.
	 *
	 * @return void
	 */
	public function delete( $id ) {
		$where = array(
			'id' => $id,
		);

		$this->wpdb->delete( $this->table_name, $where );
	}

	/**
	 * Find one
	 *
	 * @param string $id Primary key.
	 *
	 * @return object|array
	 */
	public function find_one( $id ) {
		// phpcs:disable WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$sql = $this->wpdb->prepare(
			"SELECT * FROM $this->table_name WHERE id = %s",
			$id,
		);
		// phpcs:enable WordPress.DB.PreparedSQL.InterpolatedNotPrepared

		// phpcs:disable WordPress.DB.PreparedSQL.NotPrepared
		return $this->wpdb->get_row( $sql, $this->output );
		// phpcs:enable WordPress.DB.PreparedSQL.NotPrepared
	}

	/**
	 * Find one by uid
	 *
	 * @param string $uid Uuid.
	 *
	 * @return object|array
	 */
	public function find_one_by_uid( $uid ) {
		// phpcs:disable WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$sql = $this->wpdb->prepare(
			"SELECT * FROM $this->table_name WHERE uid = %s",
			$uid,
		);
		// phpcs:enable WordPress.DB.PreparedSQL.InterpolatedNotPrepared

		// phpcs:disable WordPress.DB.PreparedSQL.NotPrepared
		return $this->wpdb->get_row( $sql, $this->output );
		// phpcs:enable WordPress.DB.PreparedSQL.NotPrepared
	}

	/**
	 * Find all by user_ref
	 *
	 * @param string $user_ref User reference uuid.
	 *
	 * @return array
	 */
	public function find_all_by_user_ref( $user_ref ) {
		// phpcs:disable WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$sql = $this->wpdb->prepare(
			"SELECT * FROM $this->table_name WHERE user_ref = %s ORDER BY site_name, room",
			$user_ref,
		);
		// phpcs:enable WordPress.DB.PreparedSQL.InterpolatedNotPrepared

		// phpcs:disable WordPress.DB.PreparedSQL.NotPrepared
		return $this->wpdb->get_results( $sql, $this->output );
		// phpcs:enable WordPress.DB.PreparedSQL.NotPrepared
	}

	/**
	 * Validate
	 *
	 * Validates the data passed into it based upon the form_validation rules
	 * setup in the $this->validate property.
	 *
	 * @param array  $data An array of validation rules
	 * @param string $type Query type eg. 'insert' or 'update'
	 *
	 * @return array|boolean The original data or FALSE
	 */
	public function validate(array $data, $type)
	{
		if ($this->skip_validation === TRUE)
		{
			return $data;
		}

		if ('insert' === $type) {
			$validation_rules = $this->insert_validation_rules;
		}

		if ('update' === $type) {
			$validation_rules = $this->update_validation_rules;
		}

		$form_validation = new Heat_Loss_Calculator_Form_Validation();
		$valid = $form_validation->validate( $data, $validation_rules );

		if ( $valid ) {
			return true;
		}

		if ( ! $valid ) {
			$this->validation_errors = $form_validation->error_array();
			return false;
		}
	}

	/**
	 * Has validation errors
	 *
	 * @return boolean
	 */
	public function has_validation_errors() {
		return ! empty( $this->validation_errors );
	}

	/**
	 * Has a database error
	 *
	 * True when the last write failed at the database rather than at validation.
	 *
	 * @return boolean
	 */
	public function has_db_error() {
		return '' !== $this->db_error;
	}

	/**
	 * Get the last database error
	 *
	 * @return string
	 */
	public function get_db_error() {
		return $this->db_error;
	}



}
