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

// phpcs:disable Squiz.Commenting.FunctionComment.ParamCommentFullStop
// phpcs:disable Squiz.Commenting.InlineComment.InvalidEndChar
// phpcs:disable Squiz.PHP.CommentedOutCode.Found

// Load our FPDF extension.
require_once plugin_dir_path( dirname( __FILE__ ) ) . 'fpdf/scripts/PDF_HTML.php';

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
class Heat_Loss_Calculator_To_Pdf {

	/**
	 * Construct
	 */
	public function __construct() {

	}

	/**
	 * Download
	 *
	 * @param array $results Results.
	 *
	 * @return void
	 */
	public function download( array $results ) {
		$document = array(
			'width'       => 210,
			'height'      => 297,
			'units'       => 'mm',
			'date_string' => gmdate( 'd/m/Y' ),
			'name'        => 'heat-loss-guide',
			'results'     => $results,
		);

		$filename = gmdate( 'YmdHis' ) . '_' . $document['name'] . '.pdf';

		$pdf = $this->create_fpdf( $document );
		$pdf->Output( 'D', $filename );
	}

	/**
	 * Create Pdf
	 *
	 * @param array $document Document config and data.
	 *
	 * @return FPDF
	 */
	private function create_fpdf( array $document ) {
		$font_path = plugin_dir_path( dirname( __FILE__ ) ) . 'fonts';
		define( 'FPDF_FONTPATH', $font_path );

		// Is it portrait or landscape?
		if ( $document['height'] >= $document['width'] ) {
			$orientation = 'P';
		} else {
			$orientation = 'L';
		}

		// Units can be pt, mm, cm, in.
		$units = $document['units'];

		// Size, width then height. Can also pass a string eg A4.
		$size = array(
			$document['width'],
			$document['height'],
		);

		$pdf = new PDF_HTML( $orientation, $units, $size );
		$pdf->AddFont( 'Helvetica', '', 'helvetica.php' );
		$pdf->AddFont( 'Helvetica', 'B', 'helveticab.php' );

		$pdf->SetMargins( 0, 0, 0 );
		$pdf->SetAutoPageBreak( 0 );

		// Set line width (float of unit).
		$pdf->SetLineWidth( .1 );

		// Count the tables, we can fit 4 on 1 page.
		$page_count = 0;
		$chunks     = array();

		$rows = count( $document['results'] );

		if ( $rows > 0 ) {
			$page_count = ceil( $rows / 4 );
			$chunks     = array_chunk( $document['results'], 4 );
		}

		$atts = array(
			'page_count' => $page_count,
			'page'       => 1,
		);

		// First page is a cover.
		$this->add_fpdf_cover( $pdf, $document, $atts );

		for ( $i = 0; $i < $page_count; $i++ ) {
			$atts['page'] = $atts['page'] + 1;
			$this->add_page( $pdf, $chunks[ $i ], $atts );
		}

		return $pdf;
	}

	/**
	 * Add page
	 *
	 * @param PDF_HTML $pdf      Pdf
	 * @param array    $document Document
	 * @param array    $atts     Attributes
	 *
	 * @return PDF_HTML
	 */
	private function add_fpdf_cover( PDF_HTML $pdf, array $document, array $atts ) {
		$pdf->AddPage();

		$logo = plugin_dir_path( dirname( __FILE__ ) ) . 'public/assets/company-logo.png';

		// Width only - FPDF scales the height to match the image's aspect ratio.
		// The logo is roughly square, so 38mm wide is ~39mm tall.
		$pdf->Image( $logo, 20, 20, 38 );

		$pdf->SetY( 227, false );
		$pdf->SetX( 48 );
		$pdf->SetFont( 'Helvetica', 'B', 22 );
		$pdf->WriteHTML( '<b>Heat loss calculation guide</b>' );

		$pdf->SetY( 238, false );
		$pdf->SetX( 48 );
		$pdf->SetFont( 'Helvetica', '', 12 );
		$pdf->WriteHTML( 'Calculated ' . $document['date_string'] );

		$pdf->SetY( 248, false );
		$pdf->SetX( 48 );
		$pdf->WriteHTML( 'Page 1 of ' . ( $atts['page_count'] + 1 ) );

		$pdf->SetY( 276, false );
		$pdf->SetX( 48 );
		$pdf->SetFont( 'Helvetica', 'B', 14 );
		$pdf->WriteHTML( HLC_COMPANY_WEBSITE );

		$pdf->SetY( 284, false );
		$pdf->SetX( 48 );
		$pdf->SetFont( 'Helvetica', '', 7 );
		$pdf->WriteHTML( '<b>' . HLC_COMPANY_NAME . '</b>, ' . HLC_COMPANY_ADDRESS );

		$pdf->SetY( 288, false );
		$pdf->SetX( 48 );
		$pdf->WriteHTML( 'T: ' . HLC_COMPANY_PHONE . '  |  E: ' . HLC_COMPANY_EMAIL );

		return $pdf;
	}

	/**
	 * Add page
	 *
	 * @param PDF_HTML $pdf  Pdf
	 * @param array    $rows Page data
	 * @param array    $atts Page data
	 *
	 * @return PDF_HTML
	 */
	private function add_page( PDF_HTML $pdf, array $rows, array $atts ) {
		$pdf->AddPage();

		$logo = plugin_dir_path( dirname( __FILE__ ) ) . 'public/assets/company-logo.png';

		// The header band is 15mm tall (the divider lines run y=10 to y=25).
		// The logo is roughly square, so 14.5mm wide is ~15mm tall and fits the
		// band exactly. Adjust if the logo artwork is replaced.
		$pdf->Image( $logo, 10, 10, 14.5 );

		$pdf->Line( 76, 10, 76, 25 );

		$pdf->SetY( 18.5, false );

		$pdf->SetX( 77 );
		$pdf->SetFont( 'Helvetica', '', 18 );
		$pdf->WriteHTML( '<b>Heat loss guide</b>' );

		$pdf->Line( 130, 10, 130, 25 );
		$pdf->SetY( 19.4, false );
		$pdf->SetX( 131 );
		$pdf->SetFont( 'Helvetica', '', 10 );
		$pdf->WriteHTML( '<b>Calculated</b> - ' . gmdate( 'd/m/Y' ) );

		$pdf->Line( 180, 10, 180, 25 );
		$pdf->SetY( 19.4, false );
		$pdf->SetX( 181 );
		$pdf->SetFont( 'Helvetica', '', 10 );
		$pdf->WriteHTML( 'Page ' . $atts['page'] . ' of ' . ( $atts['page_count'] + 1 ) );

		$y = 35;

		foreach ( $rows as $row ) {
			$puc    = new Heat_Loss_Calculator_U_Calculator();
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

			$last_y = $this->add_table( $pdf, $puc, $y );

			$y = $last_y + 10;
		}

		$pdf->SetFont( 'Helvetica', '', 6 );
		$pdf->SetX( 10 );
		$pdf->SetY( 285, false );
		$pdf->WriteHtmlCell( 190, '<font color="#ff0000">PLEASE NOTE:</font> These calculations are made from the details that have been input. The year of construction that was selected on our calculator uses building regulations data that was in force' );

		$pdf->SetX( 10 );
		$pdf->SetY( 288, false );
		$pdf->SetLeftMargin( 13 );
		// $pdf->SetLeftMargin( 10 );
		$pdf->WriteHtmlCell( 190, 'at that time. The calculations should be treated as a <b>guide</b> to the potential heating requirements and are not intended to replace a professionally prepared, detailed heat loss calculation.' );

		$pdf->SetX( 10 );
		$pdf->SetY( 291, false );
		$pdf->SetLeftMargin( 53 );
		$pdf->WriteHtmlCell( 190, '<font color="#ff0000">' . HLC_COMPANY_LEGAL_NAME . ' cannot take responsibility for inappropriate installation of heating equipment.</font>' );

		return $pdf;
	}

	/**
	 * Meters Square symbol
	 *
	 * @return string
	 */
	public function m2() {
		return 'm' . iconv( 'UTF-8', 'CP1252//TRANSLIT', '²' );
	}

	/**
	 * Degrees Celcius
	 *
	 * @return string
	 */
	public function degc() {
		return iconv( 'UTF-8', 'CP1252//TRANSLIT', '°c' );
	}

	/**
	 * Add table
	 *
	 * @param PDF_HTML             $pdf Pdf
	 * @param Heat_Loss_Calculator_U_Calculator $puc Calculator
	 * @param int                  $y starting y
	 * @return int
	 */
	private function add_table( PDF_HTML $pdf, Heat_Loss_Calculator_U_Calculator $puc, int $y ) {
		$hlcp = new Heat_Loss_Calculator_Public( '', '' );

		$pdf->SetFont( 'Helvetica', '', 7 );

		$y_txt_start = 0.8;

		$col_x = array(
			12,
			36,
			72,
			104,
			128,
			156,
			180,
		);

		// Start top bar
		$pdf->SetX( 10 );
		$pdf->SetY( $y, false );
		$pdf->SetFillColor( 170, 165, 197 );
		$pdf->Cell( 190, 8, '', 0, 0, 0, true, '' );

		$pdf->SetY( $y + 1.5, false );

		$store_y = $y + 1.5;

		$pdf->SetX( $col_x[0] );
		$pdf->WriteHTML( 'Site name: <b>' . $puc->site_name . '</b>' );

		$pdf->SetX( $col_x[2] );
		$pdf->WriteHTML( 'Room: <b>' . $puc->room . '</b>' );

		$pdf->SetX( $col_x[3] );
		$pdf->WriteHTML( 'Output requirement: <b>' . number_format( $puc->total_load / 1000, 2, '.', '' ) . ' kW</b>' );

		$pdf->SetX( $col_x[5] );
		$msg = $hlcp->heater( number_format( $puc->total_load / 1000, 1, '.', '' ) );
		if ( strpos( $msg, 'contact' ) === false ) {
			$pdf->WriteHTML( $hlcp->heater( number_format( $puc->total_load / 1000, 1, '.', '' ) ) );
		} else {
			$pdf->SetLeftMargin( $col_x[5] );
			$pdf->SetRightMargin( 12 );
			$pdf->SetY( $y + .25, false );
			$pdf->WriteHTML( 'Please contact ' . HLC_COMPANY_NAME );
			$pdf->SetX( $col_x[5] );
			$pdf->SetY( $y + 2.75, false );
			$pdf->WriteHTML( 'for advice on heating this space.' );
			$pdf->SetRightMargin( 10 );
		}
		// End top bar 58mm

		// Start first row
		// ----------------
		$y = $y + 8;
		$pdf->SetY( $y, false );

		$pdf->SetY( $y + $y_txt_start, false );
		$pdf->SetX( $col_x[0] );
		$pdf->WriteHTML( '<b>Temperatures</b>' );

		$pdf->SetX( $col_x[1] );
		$pdf->WriteHTML( 'Inside' );

		$pdf->SetX( $col_x[2] );
		$pdf->WriteHTML( 'Outside' );

		$pdf->SetY( $y + 5, false );

		$pdf->SetX( $col_x[1] );
		$pdf->WriteHTML( '<b>' . $puc->inside_temp . ' ' . $this->degc() . '</b>' );

		$pdf->SetX( $col_x[2] );
		$pdf->WriteHTML( '<b>' . $puc->outside_temp . ' ' . $this->degc() . '</b>' );

		$pdf->Line( 10, $y + 10, 200, $y + 10 );

		// ----------------
		$y = $y + 10;
		$pdf->SetY( $y + $y_txt_start, false );

		$pdf->SetX( $col_x[0] );
		$pdf->WriteHTML( '<b>Dimesions</b>' );

		$pdf->SetX( $col_x[1] );
		$pdf->WriteHTML( 'Floor' );

		$pdf->SetX( $col_x[2] );
		$pdf->WriteHTML( 'Ceiling Height' );

		$pdf->SetY( $y + 5, false );

		$pdf->SetX( $col_x[1] );
		$pdf->WriteHTML( '<b>' . $puc->floor_area . ' ' . $this->m2() . '</b>' );

		$pdf->SetX( $col_x[2] );
		$pdf->WriteHTML( '<b>' . $puc->ceiling_height . ' m</b>' );

		$pdf->Line( 10, $y + 10, 200, $y + 10 );

		// ----------------
		$y = $y + 10;
		$pdf->SetY( $y + $y_txt_start, false );

		$pdf->SetX( $col_x[0] );
		$pdf->WriteHTML( '<b>Areas</b>' );

		$pdf->SetX( $col_x[1] );
		$pdf->WriteHTML( 'Exposed external walls:' );

		$pdf->SetX( $col_x[2] );
		$pdf->WriteHTML( 'Exposed ceiling/roof:' );

		$pdf->SetX( $col_x[3] );
		$pdf->WriteHTML( 'Exposed floor:' );

		$pdf->SetX( $col_x[4] );
		$pdf->WriteHTML( 'Window and door:' );

		$pdf->SetX( $col_x[5] );
		$pdf->WriteHTML( 'Roof light:' );

		$pdf->SetY( $y + 5, false );

		$pdf->SetX( $col_x[1] );
		$pdf->WriteHTML( '<b>' . $puc->exposed_external_area . ' ' . $this->m2() . '</b>' );

		$pdf->SetX( $col_x[2] );
		$pdf->WriteHTML( '<b>' . $puc->exposed_ceiling_area . ' ' . $this->m2() . '</b>' );

		$pdf->SetX( $col_x[3] );
		$pdf->WriteHTML( '<b>' . $puc->exposed_floor_area . ' ' . $this->m2() . '</b>' );

		$pdf->SetX( $col_x[4] );
		$pdf->WriteHTML( '<b>' . $puc->window_and_door_area . ' ' . $this->m2() . '</b>' );

		$pdf->SetX( $col_x[5] );
		$pdf->WriteHTML( '<b>' . $puc->roof_light_area . ' ' . $this->m2() . '</b>' );

		$pdf->Line( 10, $y + 10, 200, $y + 10 );

		// ----------------
		$y = $y + 10;
		$pdf->SetY( $y + $y_txt_start, false );

		$pdf->SetX( $col_x[0] );
		$pdf->WriteHTML( '<b>Heat Loss</b>' );

		$pdf->SetX( $col_x[1] );
		$pdf->WriteHTML( 'Infiltration:' );

		$pdf->SetX( $col_x[2] );
		$pdf->WriteHTML( 'Exposed external wall:' );

		$pdf->SetX( $col_x[3] );
		$pdf->WriteHTML( 'Window/door:' );

		$pdf->SetX( $col_x[4] );
		$pdf->WriteHTML( 'Ceiling/roof:' );

		$pdf->SetX( $col_x[5] );
		$pdf->WriteHTML( 'Roof light:' );

		$pdf->SetX( $col_x[6] );
		$pdf->WriteHTML( 'Exposed floor:' );

		$pdf->SetY( $y + 5, false );

		$pdf->SetX( $col_x[1] );
		$pdf->WriteHTML( '<b>' . ceil( $puc->infiltration_heat_loss ) . ' W</b>' );

		$pdf->SetX( $col_x[2] );
		$pdf->WriteHTML( '<b>' . ceil( $puc->exposed_external_wall_heat_loss ) . ' W</b>' );

		$pdf->SetX( $col_x[3] );
		$pdf->WriteHTML( '<b>' . ceil( $puc->window_door_heat_loss ) . ' W</b>' );

		$pdf->SetX( $col_x[4] );
		$pdf->WriteHTML( '<b>' . ceil( $puc->ceiling_roof_heat_loss ) . ' W</b>' );

		$pdf->SetX( $col_x[5] );
		$pdf->WriteHTML( '<b>' . ceil( $puc->roof_light_heat_loss ) . ' W</b>' );

		$pdf->SetX( $col_x[6] );
		$pdf->WriteHTML( '<b>' . ceil( $puc->exposed_floor_heat_loss ) . ' W</b>' );

		$pdf->Line( 10, $y + 10, 200, $y + 10 );

		// ----------------
		$y = $y + 10;
		$pdf->SetY( $y + $y_txt_start, false );

		$pdf->SetX( $col_x[0] );
		$pdf->WriteHTML( '<b>Totals</b>' );

		$pdf->SetX( $col_x[1] );
		$pdf->WriteHTML( 'Total:' );

		$pdf->SetX( $col_x[2] );
		$pdf->WriteHTML( 'Typical 10% margin:' );

		$pdf->SetX( $col_x[3] );
		$pdf->WriteHTML( 'Total Load:' );

		$pdf->SetX( $col_x[4] );
		$pdf->WriteHTML( 'Watts per ' . $this->m2() . ':' );

		$pdf->SetY( $y + 5, false );

		$pdf->SetX( $col_x[1] );
		$pdf->WriteHTML( '<b>' . ceil( $puc->total ) . ' W</b>' );

		$pdf->SetX( $col_x[2] );
		$pdf->WriteHTML( '<b>' . ceil( $puc->total_with_margin ) . ' W</b>' );

		$pdf->SetX( $col_x[3] );
		$pdf->WriteHTML( '<b>' . ceil( $puc->total_load ) . ' W</b>' );

		$pdf->SetX( $col_x[4] );
		$pdf->WriteHTML( '<b>' . ceil( $puc->total_ref ) . ' W</b>' );

		$pdf->Line( 10, $y + 10, 200, $y + 10 );

		return $y + 5;
	}

}
