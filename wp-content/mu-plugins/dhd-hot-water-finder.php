<?php
/**
 * Plugin Name: DHD Hot Water Finder
 * Description: Questionnaire that recommends products based on hot water needs. Shortcode: [dhd_hot_water_finder]. Append ?hwf_review=1 to the page URL to show open client questions inline.
 *
 * Standalone by design — no shared state with user-group-discounts or the shipping restrictions plugin.
 * Product cards are rendered by WooCommerce's native [products] shortcode, so theme/Elementor
 * product styling, catalogue visibility and price display (incl. B2B pricing) all apply as usual.
 */

defined( 'ABSPATH' ) || exit;

/**
 * Phone number for "call our team" CTAs. Leave empty to link to the contact page instead.
 * TODO (client): confirm number.
 */
const DHD_HWF_PHONE = '';

/**
 * TODO (client): confirm contact page path.
 */
const DHD_HWF_CONTACT_PATH = '/contact/';

/**
 * Single source of truth for the questionnaire.
 *
 * Questions: each answer points to another question or a result via 'next'.
 * Results:   'skus'   — product SKUs to show, in order (1 = single card, 2+ = carousel).
 *                       Use parent SKUs for variable products; customers pick capacity on the product page.
 *            'browse' — product_cat slug for the "Browse" button (falls back to the shop page).
 *            'gap'    — open question for the client, shown only in review mode.
 */
function dhd_hwf_tree() {
	return [
		'start'     => 'needs',

		'questions' => [
			'needs'   => [
				'title'   => 'What are your hot water needs?',
				'answers' => [
					[ 'label' => 'Shower only', 'next' => 'shower' ],
					[ 'label' => 'Shower and bath', 'next' => 'heating' ],
				],
			],
			'shower'  => [
				'title'   => 'What kind of shower do you have?',
				'answers' => [
					[ 'label' => 'Small shower (up to 6 litres per minute)', 'next' => 'single_phase' ],
					[ 'label' => 'Large head or rainfall shower with a high flow rate', 'next' => 'three_phase_boiler' ],
				],
			],
			'heating' => [
				'title'   => 'How is your hot water heated at the moment?',
				'answers' => [
					[ 'label' => 'A boiler heats a cylinder', 'next' => 'indirect' ],
					[ 'label' => 'I heat my home with electric radiators', 'next' => 'direct' ],
					[
						'label' => 'I\'d like to consider a three-phase water heater',
						'next'  => 'three_phase_heater',
						'gap'   => 'The mind map lists "Have you considered a three-phase water heater such as our Kospel product" as an option here. Should it be an answer like this, or a tip shown on every "Shower and bath" result?',
					],
					[
						'label' => 'Something else / not sure',
						'next'  => 'advice',
						'gap'   => 'Not in the mind map. What should someone with a gas combi, heat pump or other setup see?',
					],
				],
			],
		],

		'results'   => [
			'single_phase'       => [
				'title'  => 'You need a single-phase water heater',
				'text'   => 'For a small shower with a flow rate of up to 6 litres per minute, a single-phase water heater such as our Volt Therm or Kospel products will do the job.',
				'skus'   => [],
				'browse' => [ 'label' => 'Browse water heaters', 'cat' => 'water-heaters' ],
				'gap'    => 'Which Volt Therm / Kospel products should appear here? Please send the SKUs (and confirm they are on the site).',
			],
			'three_phase_boiler' => [
				'title'  => 'You need a three-phase boiler with a direct cylinder',
				'text'   => 'A large head or rainfall shower needs a high flow rate, so we recommend a three-phase electric boiler paired with a direct cylinder.',
				'skus'   => [ 'DHD-CYL-DIRECT' ],
				'browse' => [ 'label' => 'Browse our cylinders', 'cat' => 'cylinders' ],
				'gap'    => 'Should an electric boiler be shown alongside the cylinder? The electric boilers (Nexa Heat) are not on the site yet.',
			],
			'indirect'           => [
				'title'  => 'You need an indirect cylinder with a coil',
				'text'   => 'Because your boiler already heats a cylinder, an indirect unvented cylinder with a heating coil is the right fit.',
				'skus'   => [ 'DHD-CYL-INDIRECT' ],
				'browse' => [ 'label' => 'Browse our cylinders', 'cat' => 'cylinders' ],
			],
			'direct'             => [
				'title'  => 'You need a direct cylinder',
				'text'   => 'With electric radiators for heating, a direct unvented cylinder heated by its own immersion elements is the right fit.',
				'skus'   => [ 'DHD-CYL-DIRECT' ],
				'browse' => [ 'label' => 'Browse our cylinders', 'cat' => 'cylinders' ],
			],
			'three_phase_heater' => [
				'title'  => 'A three-phase water heater could suit you',
				'text'   => 'A three-phase water heater such as our Kospel range can supply both a shower and a bath without a cylinder.',
				'skus'   => [],
				'browse' => [ 'label' => 'Browse water heaters', 'cat' => 'water-heaters' ],
				'gap'    => 'Please confirm this wording and which Kospel three-phase product(s) to show.',
			],
			'advice'             => [
				'title' => 'Let\'s talk it through',
				'text'  => 'Every home is different. Give our team a call and we\'ll recommend the right setup for you.',
				'skus'  => [],
				'gap'   => 'Please confirm this fallback wording.',
			],
		],

		'general_gaps' => [
			'For the cylinder results: should we help customers choose a capacity (e.g. number of bathrooms or people → litres), or leave that to the product page?',
			'Any changes to the wording of the questions and answers?',
		],
	];
}

/**
 * Whether open client questions should be shown. Triggered by ?hwf_review=1 so nothing
 * needs removing at launch.
 */
function dhd_hwf_is_review() {
	return isset( $_GET['hwf_review'] ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended
}

function dhd_hwf_contact_html() {
	if ( DHD_HWF_PHONE ) {
		$href  = 'tel:' . preg_replace( '/[^0-9+]/', '', DHD_HWF_PHONE );
		$label = 'Call our team on ' . DHD_HWF_PHONE;
	} else {
		$href  = home_url( DHD_HWF_CONTACT_PATH );
		$label = 'Contact our team';
	}

	return sprintf( '<a class="dhd-hwf__cta dhd-hwf__cta--secondary" href="%s">%s</a>', esc_url( $href ), esc_html( $label ) );
}

function dhd_hwf_browse_url( $cat_slug ) {
	$term = $cat_slug ? get_term_by( 'slug', $cat_slug, 'product_cat' ) : false;
	$link = $term ? get_term_link( $term ) : '';

	return ( $link && ! is_wp_error( $link ) ) ? $link : get_permalink( wc_get_page_id( 'shop' ) );
}

/**
 * Renders the products for a result via WooCommerce's [products] shortcode.
 * SKUs are resolved to IDs here so orderby="post__in" keeps the configured order.
 */
function dhd_hwf_products_html( array $skus ) {
	$ids = array_values( array_filter( array_map( 'wc_get_product_id_by_sku', $skus ) ) );
	if ( ! $ids ) {
		return '';
	}

	$single = 1 === count( $ids );
	$loop   = do_shortcode( sprintf(
		'[products ids="%s" orderby="post__in" columns="%d"]',
		implode( ',', $ids ),
		$single ? 1 : 4
	) );

	if ( ! trim( wp_strip_all_tags( $loop ) ) ) {
		return ''; // All products hidden or unpublished.
	}

	if ( $single ) {
		return '<div class="dhd-hwf__products dhd-hwf__products--single">' . $loop . '</div>';
	}

	return '<div class="dhd-hwf__products dhd-hwf__products--carousel">'
		. '<button type="button" class="dhd-hwf__nav dhd-hwf__nav--prev" data-scroll="prev" aria-label="Previous products">&lsaquo;</button>'
		. $loop
		. '<button type="button" class="dhd-hwf__nav dhd-hwf__nav--next" data-scroll="next" aria-label="Next products">&rsaquo;</button>'
		. '</div>';
}

function dhd_hwf_gap_html( $text ) {
	return '<p class="dhd-hwf__gap"><strong>Client question:</strong> ' . esc_html( $text ) . '</p>';
}

add_shortcode( 'dhd_hot_water_finder', 'dhd_hwf_render' );

function dhd_hwf_render() {
	if ( ! function_exists( 'wc_get_product_id_by_sku' ) ) {
		return '';
	}

	dhd_hwf_enqueue_assets();

	$tree   = dhd_hwf_tree();
	$review = dhd_hwf_is_review();
	$gaps   = $tree['general_gaps'];

	ob_start();
	?>
	<div class="dhd-hwf" data-start="<?php echo esc_attr( $tree['start'] ); ?>">

		<?php foreach ( $tree['questions'] as $id => $question ) : ?>
			<section class="dhd-hwf__step" data-step="<?php echo esc_attr( $id ); ?>"<?php echo $id === $tree['start'] ? '' : ' hidden'; ?>>
				<h3 class="dhd-hwf__question" tabindex="-1"><?php echo esc_html( $question['title'] ); ?></h3>
				<div class="dhd-hwf__answers">
					<?php foreach ( $question['answers'] as $answer ) : ?>
						<button type="button" class="dhd-hwf__answer" data-next="<?php echo esc_attr( $answer['next'] ); ?>">
							<?php echo esc_html( $answer['label'] ); ?>
						</button>
						<?php
						if ( ! empty( $answer['gap'] ) ) {
							$gaps[] = $answer['gap'];
							if ( $review ) {
								echo dhd_hwf_gap_html( $answer['gap'] ); // phpcs:ignore WordPress.Security.EscapeOutput
							}
						}
						?>
					<?php endforeach; ?>
				</div>
				<?php if ( $id !== $tree['start'] ) : ?>
					<button type="button" class="dhd-hwf__link" data-back>&larr; Back</button>
				<?php endif; ?>
			</section>
		<?php endforeach; ?>

		<?php foreach ( $tree['results'] as $id => $result ) : ?>
			<section class="dhd-hwf__step dhd-hwf__result" data-step="<?php echo esc_attr( $id ); ?>" hidden>
				<h3 class="dhd-hwf__question" tabindex="-1"><?php echo esc_html( $result['title'] ); ?></h3>
				<p class="dhd-hwf__text"><?php echo wp_kses_post( $result['text'] ); ?></p>

				<?php
				if ( ! empty( $result['gap'] ) ) {
					$gaps[] = $result['gap'];
					if ( $review ) {
						echo dhd_hwf_gap_html( $result['gap'] ); // phpcs:ignore WordPress.Security.EscapeOutput
					}
				}

				echo dhd_hwf_products_html( $result['skus'] ?? [] ); // phpcs:ignore WordPress.Security.EscapeOutput
				?>

				<div class="dhd-hwf__ctas">
					<?php if ( ! empty( $result['browse'] ) ) : ?>
						<a class="dhd-hwf__cta" href="<?php echo esc_url( dhd_hwf_browse_url( $result['browse']['cat'] ?? '' ) ); ?>">
							<?php echo esc_html( $result['browse']['label'] ); ?>
						</a>
					<?php endif; ?>
					<?php echo dhd_hwf_contact_html(); // phpcs:ignore WordPress.Security.EscapeOutput ?>
				</div>

				<button type="button" class="dhd-hwf__link" data-back>&larr; Back</button>
				<button type="button" class="dhd-hwf__link" data-restart>Start again</button>
			</section>
		<?php endforeach; ?>

		<?php if ( $review ) : ?>
			<div class="dhd-hwf__review">
				<h4>Open questions for review</h4>
				<ol>
					<?php foreach ( array_unique( $gaps ) as $gap ) : ?>
						<li><?php echo esc_html( $gap ); ?></li>
					<?php endforeach; ?>
				</ol>
			</div>
		<?php endif; ?>
	</div>
	<?php
	return ob_get_clean();
}

/**
 * Inline assets, enqueued only on pages that use the shortcode (printed in the footer).
 * Colours use the Elementor kit globals so the widget follows Site Settings.
 */
function dhd_hwf_enqueue_assets() {
	static $done = false;
	if ( $done ) {
		return;
	}
	$done = true;

	wp_register_style( 'dhd-hwf', false, [], '1.0.0' );
	wp_enqueue_style( 'dhd-hwf' );
	wp_add_inline_style( 'dhd-hwf', '
.dhd-hwf [hidden]{display:none!important}
.dhd-hwf__question{margin:0 0 1rem;outline:none}
.dhd-hwf__answers{display:grid;gap:.75rem;grid-template-columns:repeat(auto-fit,minmax(220px,1fr))}
.dhd-hwf__answer{padding:1rem 1.25rem;text-align:left;border:2px solid var(--e-global-color-primary,#1a3c6e);background:transparent;color:inherit;border-radius:6px;cursor:pointer;font:inherit;line-height:1.4;white-space:normal}
.dhd-hwf__answer:hover,.dhd-hwf__answer:focus-visible{background:var(--e-global-color-primary,#1a3c6e);color:#fff}
.dhd-hwf__link{background:none;border:0;padding:.5rem;margin:1rem 1.5rem 0 0;color:var(--e-global-color-primary,#1a3c6e);text-decoration:underline;cursor:pointer;font:inherit}
.dhd-hwf__ctas{display:flex;flex-wrap:wrap;gap:.75rem;margin-top:1.5rem}
.dhd-hwf__cta{display:inline-block;padding:.75rem 1.25rem;border-radius:6px;background:var(--e-global-color-primary,#1a3c6e);color:#fff;text-decoration:none}
.dhd-hwf__cta--secondary{background:transparent;color:var(--e-global-color-primary,#1a3c6e);border:2px solid currentColor}
.dhd-hwf__products{position:relative;margin-top:1.5rem}
.dhd-hwf__products ul.products::before,.dhd-hwf__products ul.products::after{display:none!important}
.dhd-hwf__products--single ul.products{max-width:320px;margin:0!important}
.dhd-hwf__products--single li.product{width:100%!important;float:none!important;margin:0!important}
.dhd-hwf__products--carousel ul.products{display:flex!important;flex-wrap:nowrap;gap:1rem;overflow-x:auto;scroll-snap-type:x mandatory;scrollbar-width:thin;margin:0!important;padding-bottom:.5rem}
.dhd-hwf__products--carousel li.product{flex:0 0 min(80%,280px);width:auto!important;float:none!important;margin:0!important;scroll-snap-align:start}
.dhd-hwf__nav{position:absolute;top:35%;z-index:2;width:2.5rem;height:2.5rem;border-radius:50%;border:0;background:#fff;box-shadow:0 2px 6px rgba(0,0,0,.2);font-size:1.5rem;line-height:1;cursor:pointer}
.dhd-hwf__nav--prev{left:-.75rem}.dhd-hwf__nav--next{right:-.75rem}
@media (hover:none){.dhd-hwf__nav{display:none}}
.dhd-hwf__gap{margin:.25rem 0 .5rem;padding:.6rem .8rem;background:#fff6d6;border-left:4px solid #e0a800;font-size:.9em;color:#4a3b00;grid-column:1/-1}
.dhd-hwf__review{margin-top:2rem;padding:1rem 1.25rem;background:#fff6d6;border:1px dashed #e0a800;color:#4a3b00}
.dhd-hwf__review h4{margin-top:0}
' );

	wp_register_script( 'dhd-hwf', false, [], '1.0.0', true );
	wp_enqueue_script( 'dhd-hwf' );
	wp_add_inline_script( 'dhd-hwf', '
document.querySelectorAll(".dhd-hwf").forEach(function (root) {
	var start = root.dataset.start, history = [];

	function show(id, push) {
		var current = root.querySelector(".dhd-hwf__step:not([hidden])");
		if (push && current) history.push(current.dataset.step);
		root.querySelectorAll(".dhd-hwf__step").forEach(function (s) { s.hidden = s.dataset.step !== id; });
		var target = root.querySelector("[data-step=\"" + id + "\"]");
		if (!target) return;
		if (target.getBoundingClientRect().top < 0) target.scrollIntoView({ behavior: "smooth", block: "start" });
		var heading = target.querySelector(".dhd-hwf__question");
		if (heading) heading.focus({ preventScroll: true });
	}

	root.addEventListener("click", function (e) {
		var btn = e.target.closest("[data-next],[data-back],[data-restart],[data-scroll]");
		if (!btn || !root.contains(btn)) return;
		if (btn.dataset.next) {
			show(btn.dataset.next, true);
		} else if (btn.hasAttribute("data-back")) {
			show(history.pop() || start, false);
		} else if (btn.hasAttribute("data-restart")) {
			history = [];
			show(start, false);
		} else if (btn.dataset.scroll) {
			var track = btn.parentNode.querySelector("ul.products");
			if (track) track.scrollBy({ left: track.clientWidth * 0.8 * (btn.dataset.scroll === "next" ? 1 : -1), behavior: "smooth" });
		}
	});
});
' );
}