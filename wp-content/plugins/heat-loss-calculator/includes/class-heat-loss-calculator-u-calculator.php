<?php
/**
 * Register all actions and filters for the plugin
 *
 * @link       https://wordpress-1657598-6759210.cloudwaysapps.com
 * @since      1.0.0
 *
 * @package    Heat_Loss_Calculator
 * @subpackage Heat_Loss_Calculator/includes
 */

/**
 * Register all actions and filters for the plugin.
 *
 * Maintain a list of all hooks that are registered throughout
 * the plugin, and register them with the WordPress API. Call the
 * run function to execute the list of actions and filters.
 *
 * @package    Heat_Loss_Calculator
 * @subpackage Heat_Loss_Calculator/includes
 * @author     Charlotte Oswald <web@charlotteoswald.com>
 */
class Heat_Loss_Calculator_U_Calculator {

	/**
	 * Primary key
	 *
	 * Generated
	 *
	 * @var float
	 */
	public $id;

	/**
	 * Uuid
	 *
	 * Unique record number
	 *
	 * @var float
	 */
	public $uid;

	/**
	 * User Reference
	 *
	 * Used to group data for a set of records.
	 * Stored as a user cookie or can be input.
	 *
	 * @var float
	 */
	public $user_ref;

	/**
	 * Site name
	 *
	 * User input.
	 *
	 * @var float
	 */
	public $site_name;

	/**
	 * Room
	 *
	 * User input.
	 *
	 * @var float
	 */
	public $room;

	/**
	 * Year built
	 *
	 * User input.
	 *
	 * @var float
	 */
	public $year_built;

	/**
	 * External Walls U value
	 *
	 * Set by set_heat_loss_values function.
	 *
	 * @var float
	 */
	public $external_walls_u;

	/**
	 * Floors U value
	 *
	 * Set by set_heat_loss_values function.
	 *
	 * @var float
	 */
	public $floors_u;

	/**
	 * Roof U value
	 *
	 * Set by set_heat_loss_values function.
	 *
	 * @var float
	 */
	public $roof_u;

	/**
	 * Windows & Doors U value
	 *
	 * Set by set_heat_loss_values function.
	 *
	 * @var float
	 */
	public $windows_doors_u;

	/**
	 * Air Change
	 *
	 * Set by set_heat_loss_values function.
	 *
	 * @var float
	 */
	public $air_change_u;

	/**
	 * Inside temperature
	 *
	 * User input.
	 *
	 * @var int
	 */
	public $inside_temp;

	/**
	 * Outside temperature
	 *
	 * User input.
	 *
	 * @var int
	 */
	public $outside_temp;

	/**
	 * Floor area
	 *
	 * User input.
	 *
	 * @var float
	 */
	public $floor_area;

	/**
	 * Ceiling height
	 *
	 * User input.
	 *
	 * @var float
	 */
	public $ceiling_height;

	/**
	 * Exposed external wall area including windows and doors
	 *
	 * User input.
	 *
	 * @var float
	 */
	public $exposed_external_area;

	/**
	 * Exposed ceiling/roof area including roof lights
	 *
	 * User input.
	 *
	 * @var float
	 */
	public $exposed_ceiling_area;

	/**
	 * Exposed floor area
	 *
	 * User input.
	 *
	 * @var float
	 */
	public $exposed_floor_area;

	/**
	 * Window and door area combined
	 *
	 * User input.
	 *
	 * @var float
	 */
	public $window_and_door_area;

	/**
	 * Roof light area
	 *
	 * User input.
	 *
	 * @var float
	 */
	public $roof_light_area;

	/**
	 * Infiltration heat loss
	 *
	 * Calculated.
	 *
	 * @var float
	 */
	public $infiltration_heat_loss;

	/**
	 * Exposed external wall heat loss
	 *
	 * Calculated.
	 *
	 * @var float
	 */
	public $exposed_external_wall_heat_loss;

	/**
	 * Window/door heat loss
	 *
	 * Calculated.
	 *
	 * @var float
	 */
	public $window_door_heat_loss;

	/**
	 * Ceiling/roof heat loss
	 *
	 * Calculated.
	 *
	 * @var float
	 */
	public $ceiling_roof_heat_loss;

	/**
	 * Roof light heat loss
	 *
	 * Calculated.
	 *
	 * @var float
	 */
	public $roof_light_heat_loss;

	/**
	 * Exposed floor heat loss
	 *
	 * Calculated.
	 *
	 * @var float
	 */
	public $exposed_floor_heat_loss;

	/**
	 * Total
	 *
	 * Calculated.
	 *
	 * @var float
	 */
	public $total;

	/**
	 * Total with margin
	 *
	 * Calculated.
	 *
	 * @var float
	 */
	public $total_with_margin;

	/**
	 * Total load
	 *
	 * Calculated.
	 *
	 * @var float
	 */
	public $total_load;

	/**
	 * Total ref
	 *
	 * Calculated.
	 *
	 * @var float
	 */
	public $total_ref;

	/**
	 * Initialize the collections used to maintain the actions and filters.
	 *
	 * @since    1.0.0
	 */
	public function __construct() {
	}

	/**
	 * Get year options
	 *
	 * @return array
	 */
	public function get_year_options() {
		$array['1'] = 'Before 1976';
		$array['2'] = '1976 to 1980';
		$array['3'] = '1981 to 1994';
		$array['4'] = '1995 to 2001';
		$array['5'] = '2002 to 2012';
		$array['6'] = '2013 to Present';

		return $array;
	}

	/**
	 * Get heat loss u values
	 *
	 * @return array
	 */
	public function get_heat_loss_u_values() {
		$array['1'] = array( 0, 0, 0, 0, 0 );
		$array['2'] = array( 1, 1, .6, 5.7, 1.5 );
		$array['3'] = array( .6, .6, .35, 5.7, 1.5 );
		$array['4'] = array( .45, .45, .25, 3.3, 1 );
		$array['5'] = array( .35, .25, .25, 2.2, 1 );
		$array['6'] = array( .18, .13, .13, 1.4, 1 );

		return $array;
	}

	/**
	 * Set heat loss u values
	 *
	 * Set the heat loss u values based on building regulations for a given year range.
	 * External Walls, Floors, Roof, Windows & Doors, Air Change.
	 * Note if the key is 1 'Before 1976' then a Bespoke assessment is recommended.
	 *
	 * @return void
	 */
	private function set_heat_loss_u_values() {
		$array = $this->get_heat_loss_u_values();

		$this->external_walls_u = $array[ $this->year_built ][0];
		$this->floors_u         = $array[ $this->year_built ][1];
		$this->roof_u           = $array[ $this->year_built ][2];
		$this->windows_doors_u  = $array[ $this->year_built ][3];
		$this->air_change_u     = $array[ $this->year_built ][4];
	}

	/**
	 * Calculate Watts
	 *
	 * @param array $values Values.
	 *
	 * @return void
	 */
	public function calculate_heating( $values ) {
		$this->id                    = $values['id'];
		$this->uid                   = $values['uid'];
		$this->user_ref              = $values['user_ref'];
		$this->site_name             = $values['site_name'];
		$this->room                  = $values['room'];
		$this->year_built            = $values['year_built'];
		$this->inside_temp           = $values['inside_temp'];
		$this->outside_temp          = $values['outside_temp'];
		$this->floor_area            = $values['floor_area'];
		$this->ceiling_height        = $values['ceiling_height'];
		$this->exposed_external_area = $values['exposed_external_area'];
		$this->exposed_ceiling_area  = $values['exposed_ceiling_area'];
		$this->exposed_floor_area    = $values['exposed_floor_area'];
		$this->window_and_door_area  = $values['window_and_door_area'];
		$this->roof_light_area       = $values['roof_light_area'];

		$this->set_heat_loss_u_values();

		$this->infiltration_heat_loss          = $this->calc_infiltration_heat_loss();
		$this->exposed_external_wall_heat_loss = $this->calc_exposed_external_wall_heat_loss();
		$this->window_door_heat_loss           = $this->calc_window_door_heat_loss();
		$this->ceiling_roof_heat_loss          = $this->calc_ceiling_roof_heat_loss();
		$this->roof_light_heat_loss            = $this->calc_roof_light_heat_loss();
		$this->exposed_floor_heat_loss         = $this->calc_exposed_floor_heat_loss();
		$this->total                           = $this->calc_total();
		$this->total_with_margin               = $this->calc_total_with_margin();
		$this->total_load                      = $this->calc_total_load();
		$this->total_ref                       = $this->calc_total_ref();
	}

	/**
	 * Calc infiltration heat loss
	 *
	 * (volume x air change x 0.33) x temp diff
	 *
	 * @return float
	 */
	private function calc_infiltration_heat_loss() {
		$volume    = $this->floor_area * $this->ceiling_height;
		$temp_diff = $this->inside_temp - $this->outside_temp;
		return $volume * $this->air_change_u * .33 * $temp_diff;
	}

	/**
	 * Calc exposed external wall heat loss
	 *
	 * (area - window/door) x U x temp diff
	 *
	 * @return float
	 */
	private function calc_exposed_external_wall_heat_loss() {
		$area      = $this->exposed_external_area - $this->window_and_door_area;
		$temp_diff = $this->inside_temp - $this->outside_temp;
		return $area * $this->external_walls_u * $temp_diff;
	}

	/**
	 * Calc window door heat loss
	 *
	 * (area) x U x temp diff
	 *
	 * @return float
	 */
	private function calc_window_door_heat_loss() {
		$temp_diff = $this->inside_temp - $this->outside_temp;
		return $this->window_and_door_area * $this->windows_doors_u * $temp_diff;
	}

	/**
	 * Calc ceiling roof heat loss
	 *
	 * (area - roof light) x U x temp diff
	 *
	 * @return float
	 */
	private function calc_ceiling_roof_heat_loss() {
		$area      = $this->exposed_ceiling_area - $this->roof_light_area;
		$temp_diff = $this->inside_temp - $this->outside_temp;
		return $area * $this->roof_u * $temp_diff;
	}

	/**
	 * Calc roof light heat loss
	 *
	 * (area) x U x temp diff
	 *
	 * @return float
	 */
	private function calc_roof_light_heat_loss() {
		$temp_diff = $this->inside_temp - $this->outside_temp;
		return $this->roof_light_area * $this->windows_doors_u * $temp_diff;
	}

	/**
	 * Calc exposed floor heat loss
	 *
	 * (area) x U x temp diff
	 *
	 * @return float
	 */
	private function calc_exposed_floor_heat_loss() {
		$temp_diff = $this->inside_temp - $this->outside_temp;
		return $this->exposed_floor_area * $this->floors_u * $temp_diff;
	}

	/**
	 * Calc total
	 *
	 * @return float
	 */
	private function calc_total() {
		return $this->infiltration_heat_loss +
		$this->exposed_external_wall_heat_loss +
		$this->window_door_heat_loss +
		$this->ceiling_roof_heat_loss +
		$this->roof_light_heat_loss +
		$this->exposed_floor_heat_loss;
	}

	/**
	 * Calc total
	 *
	 * @return float
	 */
	private function calc_total_with_margin() {
		return $this->total * 0.1;
	}

	/**
	 * Calc total
	 *
	 * @return float
	 */
	private function calc_total_load() {
		return $this->total + $this->total_with_margin;
	}

	/**
	 * Calc total ref
	 *
	 * @return float
	 */
	private function calc_total_ref() {
		if ( ! $this->floor_area > 0 ) {
			return 0;
		}
		return $this->total_load / $this->floor_area;
	}

}
