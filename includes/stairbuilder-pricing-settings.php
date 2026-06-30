<?php
/**
 * Stair Builder Pricing Settings
 *
 * Native wp_options replacement for the ACF "Stair Builder Pricing" options page.
 * Stores all pricing configuration as a single serialised array under the option
 * key `stairbuilder_options`, driven by a single schema declaration.
 *
 * Drop-in wrapper `stairbuilder_get_option()` provides a replacement for
 * existing `get_field($name, 'option')` calls elsewhere in the plugin.
 *
 * @package BalticWpStairBuilder
 * @version 2.0.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! class_exists( 'Stairbuilder_Pricing_Settings' ) ) {

	class Stairbuilder_Pricing_Settings {

		/** Single wp_options row holding the whole blob. */
		const OPTION_KEY = 'stairbuilder_options';

		/** Settings API group name (for register_setting / settings_fields). */
		const OPTION_GROUP = 'stairbuilder_settings';

		/** Admin page slug. */
		const PAGE_SLUG = 'stairbuilder-pricing';

		/** Settings API page ID (distinct from menu slug). */
		const PAGE_ID = 'stairbuilder-pricing-settings';

		/** Option flag set to 1 after a successful ACF migration. */
		const MIGRATION_FLAG = 'stairbuilder_migrated_from_acf';

		/** Schema version, written into the blob so future migrations can detect shape. */
		const SCHEMA_VERSION = 3;

		/** Option flag set to 1 after the v2 flat-key → repeater migration. */
		const REPEATER_MIGRATION_FLAG = 'stairbuilder_repeater_migrated_v2';

		/** Option flag set to 1 after the v2.4 spindle material-mode backfill. */
		const BALLUSTRADE_MODES_FLAG = 'stairbuilder_balustrading_modes_migrated_v24';

		/** @var array Parsed schema (tabs + fields). */
		private $schema;

		public function __construct() {
			$this->schema = $this->get_schema();
			add_action( 'admin_menu', array( $this, 'add_menu' ) );
			add_action( 'admin_init', array( $this, 'register' ) );
			add_action( 'admin_init', array( $this, 'maybe_migrate_from_acf' ), 20 );
			add_action( 'admin_init', array( $this, 'maybe_migrate_repeaters_v2' ), 30 );
			add_action( 'admin_init', array( $this, 'maybe_backfill_balustrade_modes' ), 40 );
			add_action( 'admin_enqueue_scripts', array( $this, 'enqueue' ) );
		}

		/* ------------------------------------------------------------------ */
		/* Public helpers                                                      */
		/* ------------------------------------------------------------------ */

		/**
		 * Read a single value from the options blob.
		 *
		 * @param string $key     Field id (matches ACF field name).
		 * @param mixed  $default Default value if missing.
		 * @return mixed
		 */
		public static function get( $key, $default = null ) {
			$options = get_option( self::OPTION_KEY, array() );
			if ( ! is_array( $options ) ) {
				return $default;
			}
			return isset( $options[ $key ] ) ? $options[ $key ] : $default;
		}

		/**
		 * Write a single value into the options blob (preserves other keys).
		 *
		 * @param string $key   Field id.
		 * @param mixed  $value New value.
		 * @return bool
		 */
		public static function set( $key, $value ) {
			$options         = get_option( self::OPTION_KEY, array() );
			if ( ! is_array( $options ) ) {
				$options = array();
			}
			$options[ $key ] = $value;
			return update_option( self::OPTION_KEY, $options );
		}

		/* ------------------------------------------------------------------ */
		/* Admin menu                                                          */
		/* ------------------------------------------------------------------ */

		public function add_menu() {
			add_options_page(
				__( 'Stair Builder Pricing', 'stairbuilder' ),
				__( 'Stair Builder Pricing', 'stairbuilder' ),
				'manage_options',
				self::PAGE_SLUG,
				array( $this, 'render_page' )
			);
		}

		/* ------------------------------------------------------------------ */
		/* Settings API registration (driven entirely by the schema)           */
		/* ------------------------------------------------------------------ */

		public function register() {
			register_setting(
				self::OPTION_GROUP,
				self::OPTION_KEY,
				array(
					'type'              => 'array',
					'sanitize_callback' => array( $this, 'sanitize' ),
					'default'           => array(),
				)
			);

			foreach ( $this->schema as $tab_slug => $tab ) {
				$section_id = 'stairbuilder_section_' . $tab_slug;
				add_settings_section( $section_id, '', '__return_false', self::PAGE_ID . '-' . $tab_slug );

				foreach ( $tab['fields'] as $field ) {
					add_settings_field(
						$field['id'],
						esc_html( $field['label'] ),
						array( $this, 'render_field' ),
						self::PAGE_ID . '-' . $tab_slug,
						$section_id,
						$field
					);
				}
			}
		}

		/* ------------------------------------------------------------------ */
		/* Field renderer (dispatches by type)                                  */
		/* ------------------------------------------------------------------ */

		public function render_field( $field ) {
			$options = get_option( self::OPTION_KEY, array() );
			if ( ! is_array( $options ) ) {
				$options = array();
			}
			$id    = $field['id'];
			$value = isset( $options[ $id ] ) ? $options[ $id ] : null;
			$name  = self::OPTION_KEY . '[' . esc_attr( $id ) . ']';

			// Conditional visibility wrapper (data-* attrs — JS hides/shows).
			$wrapper_attrs = '';
			if ( ! empty( $field['show_when'] ) ) {
				$wrapper_attrs = sprintf(
					' data-show-when="%s" data-show-equals="%s"',
					esc_attr( $field['show_when']['field'] ),
					esc_attr( $field['show_when']['equals'] ? '1' : '0' )
				);
			}
			// Conditional disable wrapper — JS greys + disables the input.
			if ( ! empty( $field['disable_when'] ) ) {
				$wrapper_attrs .= sprintf(
					' data-disable-when="%s" data-disable-equals="%s"',
					esc_attr( $field['disable_when']['field'] ),
					esc_attr( $field['disable_when']['equals'] ? '1' : '0' )
				);
			}

			echo '<div class="stairbuilder-field" data-field-id="' . esc_attr( $id ) . '"' . $wrapper_attrs . '>';

			switch ( $field['type'] ) {
				case 'toggle':
					$this->render_toggle( $id, $name, $value, $field );
					break;
				case 'price':
					$this->render_price( $id, $name, $value, $field );
					break;
				case 'product_id':
					$this->render_product_id( $id, $name, $value, $field );
					break;
				case 'number':
					$this->render_number( $id, $name, $value, $field );
					break;
				case 'text':
					$this->render_text( $id, $name, $value, $field );
					break;
				case 'textarea':
					$this->render_textarea( $id, $name, $value, $field );
					break;
				case 'color':
					$this->render_color( $id, $name, $value, $field );
					break;
				case 'select':
					$this->render_select( $id, $name, $value, $field );
					break;
				case 'repeater':
					$this->render_repeater( $id, $name, $value, $field );
					break;
				default:
					echo '<em>Unknown field type: ' . esc_html( $field['type'] ) . '</em>';
			}

			if ( ! empty( $field['description'] ) ) {
				echo '<p class="description">' . esc_html( $field['description'] ) . '</p>';
			}

			echo '</div>';
		}

		private function render_toggle( $id, $name, $value, $field ) {
			// Apply per-field default when the option blob doesn't yet contain this key.
			if ( $value === null && isset( $field['default'] ) ) {
				$value = $field['default'];
			}
			$checked = ! empty( $value );
			$label   = isset( $field['toggle_label'] ) ? $field['toggle_label'] : '';
			?>
			<label class="stairbuilder-toggle">
				<input type="hidden" name="<?php echo esc_attr( $name ); ?>" value="0" />
				<input type="checkbox" id="<?php echo esc_attr( $id ); ?>" name="<?php echo esc_attr( $name ); ?>" value="1" <?php checked( $checked ); ?> />
				<span class="stairbuilder-toggle-label"><?php echo esc_html( $label ); ?></span>
			</label>
			<?php
		}

		private function render_price( $id, $name, $value, $field ) {
			?>
			<span class="stairbuilder-currency">£</span>
			<input type="number" step="0.01" min="0"
				id="<?php echo esc_attr( $id ); ?>"
				name="<?php echo esc_attr( $name ); ?>"
				value="<?php echo esc_attr( $value ); ?>"
				class="small-text" />
			<?php
		}

		private function render_product_id( $id, $name, $value, $field ) {
			?>
			<input type="number" step="1" min="0"
				id="<?php echo esc_attr( $id ); ?>"
				name="<?php echo esc_attr( $name ); ?>"
				value="<?php echo esc_attr( $value ); ?>"
				class="small-text"
				placeholder="<?php esc_attr_e( 'Variation ID', 'stairbuilder' ); ?>" />
			<p class="description"><?php esc_html_e( 'WooCommerce product (or variation) ID — overrides the direct price.', 'stairbuilder' ); ?></p>
			<?php
		}

		private function render_number( $id, $name, $value, $field ) {
			$placeholder = isset( $field['placeholder'] ) ? $field['placeholder'] : '';
			?>
			<input type="number" step="any"
				id="<?php echo esc_attr( $id ); ?>"
				name="<?php echo esc_attr( $name ); ?>"
				value="<?php echo esc_attr( $value ); ?>"
				placeholder="<?php echo esc_attr( $placeholder ); ?>"
				class="small-text" />
			<?php
		}

		private function render_text( $id, $name, $value, $field ) {
			$placeholder = isset( $field['placeholder'] ) ? $field['placeholder'] : '';
			?>
			<input type="text"
				id="<?php echo esc_attr( $id ); ?>"
				name="<?php echo esc_attr( $name ); ?>"
				value="<?php echo esc_attr( $value ); ?>"
				placeholder="<?php echo esc_attr( $placeholder ); ?>"
				class="regular-text" />
			<?php
		}

		private function render_textarea( $id, $name, $value, $field ) {
			?>
			<textarea id="<?php echo esc_attr( $id ); ?>"
				name="<?php echo esc_attr( $name ); ?>"
				rows="5" cols="60" class="large-text code"><?php echo esc_textarea( $value ); ?></textarea>
			<?php
		}

		private function render_color( $id, $name, $value, $field ) {
			// Apply per-field default when the option blob doesn't yet contain this key.
			if ( $value === null && isset( $field['default'] ) ) {
				$value = $field['default'];
			}
			?>
			<input type="text"
				id="<?php echo esc_attr( $id ); ?>"
				name="<?php echo esc_attr( $name ); ?>"
				value="<?php echo esc_attr( $value ); ?>"
				class="stairbuilder-color-picker" />
			<?php
		}

		private function render_select( $id, $name, $value, $field ) {
			$choices = isset( $field['choices'] ) ? $field['choices'] : array();
			?>
			<select id="<?php echo esc_attr( $id ); ?>" name="<?php echo esc_attr( $name ); ?>">
				<?php foreach ( $choices as $ck => $cv ) : ?>
					<option value="<?php echo esc_attr( $ck ); ?>" <?php selected( $value, $ck ); ?>><?php echo esc_html( $cv ); ?></option>
				<?php endforeach; ?>
			</select>
			<?php
		}

		private function render_repeater( $id, $name, $value, $field ) {
			// Rich "card" repeaters (newels, caps, handrails, spindles) render
			// each row as a component card with a per-row Use-Product-ID switch.
			if ( isset( $field['style'] ) && $field['style'] === 'card' ) {
				$this->render_rich_repeater( $id, $name, $value, $field );
				return;
			}
			$rows       = is_array( $value ) ? $value : array();
			$subfields  = isset( $field['subfields'] ) ? $field['subfields'] : array();
			$subfields_json = wp_json_encode( $subfields );
			?>
			<table class="widefat stairbuilder-repeater"
				data-field-id="<?php echo esc_attr( $id ); ?>"
				data-subfields="<?php echo esc_attr( $subfields_json ); ?>">
				<thead>
					<tr>
						<?php foreach ( $subfields as $sf ) : ?>
							<th><?php echo esc_html( $sf['label'] ); ?></th>
						<?php endforeach; ?>
						<th style="width: 60px;"></th>
					</tr>
				</thead>
				<tbody>
					<?php foreach ( $rows as $i => $row ) : ?>
						<tr>
							<?php foreach ( $subfields as $sf ) : ?>
								<td>
									<input type="<?php echo $sf['type'] === 'number' ? 'number' : 'text'; ?>"
										<?php echo $sf['type'] === 'number' ? 'step="any"' : ''; ?>
										name="<?php echo esc_attr( $name ); ?>[<?php echo (int) $i; ?>][<?php echo esc_attr( $sf['id'] ); ?>]"
										value="<?php echo isset( $row[ $sf['id'] ] ) ? esc_attr( $row[ $sf['id'] ] ) : ''; ?>"
										class="widefat" />
								</td>
							<?php endforeach; ?>
							<td><button type="button" class="button stairbuilder-repeater-remove" aria-label="<?php esc_attr_e( 'Remove row', 'stairbuilder' ); ?>">&times;</button></td>
						</tr>
					<?php endforeach; ?>
				</tbody>
				<tfoot>
					<tr>
						<td colspan="<?php echo count( $subfields ) + 1; ?>">
							<button type="button" class="button stairbuilder-repeater-add"><?php esc_html_e( 'Add Row', 'stairbuilder' ); ?></button>
						</td>
					</tr>
				</tfoot>
			</table>
			<?php
		}

		/**
		 * Rich card repeater — each row is a component card with editable
		 * name/code, an optional Caps/Newel quantity, a per-row Use-Product-ID
		 * switch, and Pine/Oak price + product-ID inputs. A hidden prototype row
		 * (index token `__i__`) is cloned by the Add-Row JS so the rich markup
		 * never has to be reconstructed in JavaScript.
		 */
		private function render_rich_repeater( $id, $name, $value, $field ) {
			$rows      = is_array( $value ) ? $value : array();
			$subfields = isset( $field['subfields'] ) ? $field['subfields'] : array();

			// Variant cards (newels/caps/handrails/spindles) carry the per-row
			// Use-Product-ID switch + Pine/Oak columns. Everything else (strings,
			// construction, treads, risers) renders as a generic card — each
			// sub-field a labelled input laid out in a row.
			$is_variant = false;
			foreach ( $subfields as $sf ) {
				if ( $sf['id'] === 'use_product_id' ) {
					$is_variant = true;
					break;
				}
			}
			$row_cb = $is_variant ? 'render_card_row' : 'render_generic_card_row';
			?>
			<div class="stairbuilder-card-repeater" data-field-id="<?php echo esc_attr( $id ); ?>" data-next-index="<?php echo count( $rows ); ?>">
				<div class="stairbuilder-cards">
					<?php foreach ( $rows as $i => $row ) {
						$this->$row_cb( $name, (string) $i, $subfields, is_array( $row ) ? $row : array() );
					} ?>
				</div>
				<script type="text/html" class="stairbuilder-card-proto"><?php
					$this->$row_cb( $name, '__i__', $subfields, array() );
				?></script>
				<button type="button" class="button button-secondary stairbuilder-card-add"><?php esc_html_e( 'Add Row', 'stairbuilder' ); ?></button>
			</div>
			<?php
		}

		/**
		 * Render one generic card row — a component card containing each
		 * sub-field as a labelled input. Used by the simpler name/code/value
		 * repeaters (strings, construction types, treads, risers).
		 */
		private function render_generic_card_row( $name, $i, $subfields, $row ) {
			?>
			<div class="stairbuilder-component stairbuilder-card-row stairbuilder-card-row-generic">
				<?php foreach ( $subfields as $sf ) :
					$fname = $name . '[' . $i . '][' . $sf['id'] . ']';
					$rv    = isset( $row[ $sf['id'] ] ) ? $row[ $sf['id'] ] : '';
					$is_num = ( isset( $sf['type'] ) && $sf['type'] === 'number' );
					?>
					<div class="stairbuilder-card-field">
						<label class="stairbuilder-card-label"><?php echo esc_html( $sf['label'] ); ?>
							<input type="<?php echo $is_num ? 'number' : 'text'; ?>" <?php echo $is_num ? 'step="any"' : ''; ?>
								name="<?php echo esc_attr( $fname ); ?>"
								value="<?php echo esc_attr( $rv ); ?>"
								class="widefat" />
						</label>
					</div>
				<?php endforeach; ?>
				<div class="stairbuilder-card-actions">
					<button type="button" class="button stairbuilder-card-remove" aria-label="<?php esc_attr_e( 'Remove row', 'stairbuilder' ); ?>">&times;</button>
				</div>
			</div>
			<?php
		}

		/**
		 * Render one card row for a rich repeater. $i is the row index (or the
		 * `__i__` token for the cloneable prototype).
		 */
		private function render_card_row( $name, $i, $subfields, $row ) {
			$by = array();
			foreach ( $subfields as $sf ) {
				$by[ $sf['id'] ] = $sf;
			}
			$has_caps  = isset( $by['caps_per_newel'] );
			// Spindle rows carry a material_mode select (Wood / Metal / Glass). Other
			// component rows (newel/cap/handrail) have no mode and render Pine/Oak only.
			$has_modes = isset( $by['material_mode'] );
			$toggle_on = ! empty( $row['use_product_id'] );
			$fname     = function( $k ) use ( $name, $i ) {
				return $name . '[' . $i . '][' . $k . ']';
			};
			$val       = function( $k, $fallback = '' ) use ( $row ) {
				return ( isset( $row[ $k ] ) && $row[ $k ] !== '' ) ? $row[ $k ] : $fallback;
			};
			// Reusable price/product-ID column. The row-level `is-product-id` switch
			// flips .bd-variant-price ↔ .bd-variant-id via CSS, so metal/glass single
			// columns get the same Use-Product-ID behaviour as Pine/Oak for free.
			// $flat collapses the title + "Price"/"£" sub-rows into one label line
			// ("Pine £" / "Price £") so it aligns with Name on the single-line spindle
			// rows. Non-flat keeps the original stacked layout for newel/cap/handrail.
			$variant_col = function( $label, $price_key, $id_key, $flat = false ) use ( $fname, $val ) {
				if ( $flat ) {
					$base       = ( $label === '' ) ? __( 'Price', 'stairbuilder' ) : $label;
					$price_lbl  = sprintf( '%s £', $base );
					$id_lbl     = ( $label === '' ) ? __( 'Product ID', 'stairbuilder' ) : sprintf( '%s ID', $label );
					?>
					<div class="stairbuilder-component-col stairbuilder-component-variant is-flat">
						<label class="stairbuilder-card-label bd-variant-price"><?php echo esc_html( $price_lbl ); ?>
							<input type="number" step="0.01" min="0" name="<?php echo esc_attr( $fname( $price_key ) ); ?>" value="<?php echo esc_attr( $val( $price_key ) ); ?>" class="widefat" />
						</label>
						<label class="stairbuilder-card-label bd-variant-id"><?php echo esc_html( $id_lbl ); ?>
							<input type="number" step="1" min="0" name="<?php echo esc_attr( $fname( $id_key ) ); ?>" value="<?php echo esc_attr( $val( $id_key ) ); ?>" class="widefat" placeholder="<?php esc_attr_e( 'Variation ID', 'stairbuilder' ); ?>" />
						</label>
					</div>
					<?php
					return;
				}
				?>
				<div class="stairbuilder-component-col stairbuilder-component-variant">
					<h4 class="stairbuilder-variant-title"><?php echo esc_html( $label ); ?></h4>
					<div class="stairbuilder-field bd-variant-price">
						<label class="stairbuilder-variant-label"><?php esc_html_e( 'Price', 'stairbuilder' ); ?></label>
						<span class="stairbuilder-currency">£</span>
						<input type="number" step="0.01" min="0" name="<?php echo esc_attr( $fname( $price_key ) ); ?>" value="<?php echo esc_attr( $val( $price_key ) ); ?>" class="regular-text" />
					</div>
					<div class="stairbuilder-field bd-variant-id">
						<label class="stairbuilder-variant-label"><?php esc_html_e( 'Product ID', 'stairbuilder' ); ?></label>
						<input type="number" step="1" min="0" name="<?php echo esc_attr( $fname( $id_key ) ); ?>" value="<?php echo esc_attr( $val( $id_key ) ); ?>" class="regular-text" placeholder="<?php esc_attr_e( 'Variation ID', 'stairbuilder' ); ?>" />
					</div>
				</div>
				<?php
			};
			$mode = $has_modes ? $val( 'material_mode', 'wood_pine_oak' ) : '';
			$mode_class = '';
			if ( $has_modes ) {
				$m = ( $mode === 'metal' ) ? 'metal' : ( ( $mode === 'glass' ) ? 'glass' : 'wood' );
				$mode_class = ' mode-' . $m;
			}
			?>
			<div class="stairbuilder-component stairbuilder-card-row<?php echo $toggle_on ? ' is-product-id' : ''; ?><?php echo esc_attr( $mode_class ); ?>">
				<div class="stairbuilder-component-col stairbuilder-component-header">
					<label class="stairbuilder-card-label"><?php esc_html_e( 'Name', 'stairbuilder' ); ?>
						<input type="text" name="<?php echo esc_attr( $fname( 'name' ) ); ?>" value="<?php echo esc_attr( $val( 'name' ) ); ?>" class="widefat" />
					</label>
					<?php if ( $has_modes ) : ?>
						<?php // Balustrading rows hide Code (frees the row for Price); the
						// stable key is preserved here and auto-slugs from Name when blank. ?>
						<input type="hidden" name="<?php echo esc_attr( $fname( 'code' ) ); ?>" value="<?php echo esc_attr( $val( 'code' ) ); ?>" />
					<?php else : ?>
						<label class="stairbuilder-card-label"><?php esc_html_e( 'Code', 'stairbuilder' ); ?>
							<input type="text" name="<?php echo esc_attr( $fname( 'code' ) ); ?>" value="<?php echo esc_attr( $val( 'code' ) ); ?>" class="widefat" placeholder="<?php esc_attr_e( 'auto from name', 'stairbuilder' ); ?>" />
						</label>
					<?php endif; ?>
					<?php if ( $has_caps ) : ?>
					<label class="stairbuilder-card-label stairbuilder-card-qty"><?php esc_html_e( 'Caps / Newel', 'stairbuilder' ); ?>
						<input type="number" step="any" min="0" name="<?php echo esc_attr( $fname( 'caps_per_newel' ) ); ?>" value="<?php echo esc_attr( $val( 'caps_per_newel', '1' ) ); ?>" class="small-text" />
					</label>
					<?php endif; ?>
					<?php if ( $has_modes ) : ?>
					<label class="stairbuilder-card-label stairbuilder-card-mode"><?php esc_html_e( 'Material Type', 'stairbuilder' ); ?>
						<select class="bd-mode-select widefat" name="<?php echo esc_attr( $fname( 'material_mode' ) ); ?>">
							<option value="wood_pine_oak" <?php selected( $mode, 'wood_pine_oak' ); ?>><?php esc_html_e( 'Wood (Pine / Oak)', 'stairbuilder' ); ?></option>
							<option value="metal" <?php selected( $mode, 'metal' ); ?>><?php esc_html_e( 'Metal', 'stairbuilder' ); ?></option>
							<option value="glass" <?php selected( $mode, 'glass' ); ?>><?php esc_html_e( 'Glass', 'stairbuilder' ); ?></option>
						</select>
					</label>
					<?php // Glass basis controls sit inline in the header; CSS shows them only in glass mode. ?>
					<label class="stairbuilder-card-label bd-glass-inline"><?php esc_html_e( 'Glass Pricing', 'stairbuilder' ); ?>
						<select class="bd-glass-unit widefat" name="<?php echo esc_attr( $fname( 'pricing_unit' ) ); ?>">
							<option value="per_metre" <?php selected( $val( 'pricing_unit', 'per_metre' ), 'per_metre' ); ?>><?php esc_html_e( 'Per Linear Metre', 'stairbuilder' ); ?></option>
							<option value="per_panel" <?php selected( $val( 'pricing_unit', 'per_metre' ), 'per_panel' ); ?>><?php esc_html_e( 'Per Panel', 'stairbuilder' ); ?></option>
						</select>
					</label>
					<label class="stairbuilder-card-label bd-glass-inline bd-glass-panel"><?php esc_html_e( 'Panel Width (mm)', 'stairbuilder' ); ?>
						<input type="number" step="any" min="0" name="<?php echo esc_attr( $fname( 'panel_width_mm' ) ); ?>" value="<?php echo esc_attr( $val( 'panel_width_mm' ) ); ?>" class="widefat" placeholder="<?php esc_attr_e( 'e.g. 600', 'stairbuilder' ); ?>" />
					</label>
					<label class="stairbuilder-card-label bd-glass-inline bd-glass-panel"><?php esc_html_e( 'Panel Gap (mm)', 'stairbuilder' ); ?>
						<input type="number" step="any" min="0" name="<?php echo esc_attr( $fname( 'panel_gap_mm' ) ); ?>" value="<?php echo esc_attr( $val( 'panel_gap_mm' ) ); ?>" class="widefat" placeholder="<?php esc_attr_e( 'e.g. 20', 'stairbuilder' ); ?>" />
					</label>
					<?php endif; ?>
					<label class="stairbuilder-switch stairbuilder-card-switch">
						<input type="hidden" name="<?php echo esc_attr( $fname( 'use_product_id' ) ); ?>" value="0" />
						<input type="checkbox" class="bd-row-toggle" name="<?php echo esc_attr( $fname( 'use_product_id' ) ); ?>" value="1" <?php checked( $toggle_on ); ?> />
						<span class="stairbuilder-switch-track"><span class="stairbuilder-switch-thumb"></span></span>
						<span class="stairbuilder-switch-text"><?php esc_html_e( 'Use Product ID', 'stairbuilder' ); ?></span>
					</label>
				</div>

				<?php if ( ! $has_modes ) : ?>
					<?php $variant_col( __( 'Pine', 'stairbuilder' ), 'pine_price', 'pine_id' ); ?>
					<?php $variant_col( __( 'Oak', 'stairbuilder' ), 'oak_price', 'oak_id' ); ?>
				<?php else : ?>
					<div class="bd-mode-block bd-mode-wood">
						<?php $variant_col( __( 'Pine', 'stairbuilder' ), 'pine_price', 'pine_id', true ); ?>
						<?php $variant_col( __( 'Oak', 'stairbuilder' ), 'oak_price', 'oak_id', true ); ?>
					</div>
					<div class="bd-mode-block bd-mode-metal">
						<?php $variant_col( '', 'metal_price', 'metal_id', true ); ?>
					</div>
					<div class="bd-mode-block bd-mode-glass">
						<?php $variant_col( '', 'glass_price', 'glass_id', true ); ?>
					</div>
				<?php endif; ?>

				<div class="stairbuilder-card-actions">
					<button type="button" class="button stairbuilder-card-remove" aria-label="<?php esc_attr_e( 'Remove row', 'stairbuilder' ); ?>">&times;</button>
				</div>
			</div>
			<?php
		}

		/* ------------------------------------------------------------------ */
		/* Sanitization (type-aware, driven by schema)                         */
		/* ------------------------------------------------------------------ */

		public function sanitize( $input ) {
			if ( ! is_array( $input ) ) {
				$input = array();
			}

			$clean = array();

			// Flatten schema into field_id => spec lookup.
			$by_id = array();
			foreach ( $this->schema as $tab ) {
				foreach ( $tab['fields'] as $f ) {
					$by_id[ $f['id'] ] = $f;
				}
			}

			foreach ( $by_id as $id => $field ) {
				$raw = isset( $input[ $id ] ) ? $input[ $id ] : null;

				switch ( $field['type'] ) {
					case 'toggle':
						$clean[ $id ] = ! empty( $raw ) ? 1 : 0;
						break;
					case 'price':
						$clean[ $id ] = ( $raw === '' || $raw === null ) ? '' : (float) $raw;
						break;
					case 'product_id':
						$clean[ $id ] = ( $raw === '' || $raw === null ) ? '' : absint( $raw );
						break;
					case 'number':
						$clean[ $id ] = ( $raw === '' || $raw === null ) ? '' : (float) $raw;
						break;
					case 'text':
						$clean[ $id ] = is_scalar( $raw ) ? sanitize_text_field( (string) $raw ) : '';
						break;
					case 'textarea':
						$clean[ $id ] = is_scalar( $raw ) ? sanitize_textarea_field( (string) $raw ) : '';
						break;
					case 'color':
						$hex = is_scalar( $raw ) ? sanitize_hex_color( (string) $raw ) : '';
						$clean[ $id ] = $hex ? $hex : '';
						break;
					case 'select':
						$choices = isset( $field['choices'] ) ? array_keys( $field['choices'] ) : array();
						$clean[ $id ] = ( is_scalar( $raw ) && in_array( (string) $raw, array_map( 'strval', $choices ), true ) ) ? (string) $raw : '';
						break;
					case 'repeater':
						$clean[ $id ] = $this->sanitize_repeater( $raw, $field );
						break;
					default:
						$clean[ $id ] = is_scalar( $raw ) ? sanitize_text_field( (string) $raw ) : '';
				}
			}

			// Stamp schema version so future migrations can detect shape.
			$clean['_schema_version'] = self::SCHEMA_VERSION;

			return $clean;
		}

		private function sanitize_repeater( $raw, $field ) {
			if ( ! is_array( $raw ) ) {
				return array();
			}
			$subfields = isset( $field['subfields'] ) ? $field['subfields'] : array();
			$has_code  = false;
			foreach ( $subfields as $sf ) {
				if ( $sf['id'] === 'code' ) {
					$has_code = true;
					break;
				}
			}
			$clean_rows = array();
			foreach ( $raw as $row ) {
				if ( ! is_array( $row ) ) {
					continue;
				}
				$clean_row = array();
				$has_value = false;
				foreach ( $subfields as $sf ) {
					$v = isset( $row[ $sf['id'] ] ) ? $row[ $sf['id'] ] : '';
					switch ( $sf['type'] ) {
						case 'toggle':
							$clean_row[ $sf['id'] ] = ! empty( $v ) ? 1 : 0;
							break;
						case 'price':
						case 'number':
							$clean_row[ $sf['id'] ] = ( $v === '' || $v === null ) ? '' : (float) $v;
							break;
						case 'product_id':
							$clean_row[ $sf['id'] ] = ( $v === '' || $v === null ) ? '' : absint( $v );
							break;
						case 'select':
							$choices = isset( $sf['choices'] ) ? array_keys( $sf['choices'] ) : array();
							if ( is_scalar( $v ) && in_array( (string) $v, array_map( 'strval', $choices ), true ) ) {
								$clean_row[ $sf['id'] ] = (string) $v;
							} else {
								$clean_row[ $sf['id'] ] = isset( $sf['default'] ) ? (string) $sf['default'] : ( $choices ? (string) $choices[0] : '' );
							}
							break;
						default:
							$clean_row[ $sf['id'] ] = is_scalar( $v ) ? sanitize_text_field( (string) $v ) : '';
					}
					// Selects always carry a (default) value, so they must not count
					// toward "row has content" — otherwise blank rows would never drop.
					if ( $sf['type'] !== 'select' ) {
						$cv = $clean_row[ $sf['id'] ];
						if ( $cv !== '' && $cv !== 0 && $cv !== 0.0 ) {
							$has_value = true;
						}
					}
				}
				if ( $has_value ) {
					$clean_rows[] = $clean_row;
				}
			}

			// Codes are the stable machine keys the front-end + price lookup use.
			// Auto-slugify blanks from the name and de-duplicate; reserved codes
			// (e.g. caps' built-in "none") are never allowed as a row code.
			if ( $has_code ) {
				$reserved   = isset( $field['reserved_codes'] ) ? (array) $field['reserved_codes'] : array();
				$clean_rows = $this->ensure_unique_codes( $clean_rows, $reserved );
			}

			return array_values( $clean_rows );
		}

		/**
		 * Guarantee every row has a non-blank, unique, slugified `code`.
		 * Blank codes are derived from the row name; collisions and reserved
		 * codes get a numeric suffix (-2, -3, …).
		 */
		private function ensure_unique_codes( $rows, $reserved = array() ) {
			$seen = array();
			foreach ( $rows as &$row ) {
				if ( ! array_key_exists( 'code', $row ) ) {
					continue;
				}
				$code = sanitize_title( (string) $row['code'] );
				if ( $code === '' ) {
					$code = sanitize_title( isset( $row['name'] ) ? (string) $row['name'] : '' );
				}
				if ( $code === '' ) {
					$code = 'item';
				}
				$base = $code;
				$n    = 2;
				while ( in_array( $code, $seen, true ) || in_array( $code, $reserved, true ) ) {
					$code = $base . '-' . $n;
					$n++;
				}
				$seen[]      = $code;
				$row['code'] = $code;
			}
			unset( $row );
			return $rows;
		}

		/* ------------------------------------------------------------------ */
		/* Settings page render                                                 */
		/* ------------------------------------------------------------------ */

		public function render_page() {
			if ( ! current_user_can( 'manage_options' ) ) {
				return;
			}
			if ( isset( $_GET['settings-updated'] ) ) {
				add_settings_error( 'stairbuilder_messages', 'stairbuilder_saved', __( 'Settings saved.', 'stairbuilder' ), 'updated' );
			}
			settings_errors( 'stairbuilder_messages' );

			$groups = $this->get_tab_groups();
			?>
			<div class="wrap stairbuilder-pricing-wrap">
				<h1><?php esc_html_e( 'Stair Builder Pricing', 'stairbuilder' ); ?></h1>

				<form action="options.php" method="post">
					<?php settings_fields( self::OPTION_GROUP ); ?>

					<?php foreach ( $groups as $group_index => $group_slugs ) :
						$group_first = $group_slugs[0]; ?>
						<div class="stairbuilder-tab-group" data-group="<?php echo (int) $group_index; ?>">
							<nav class="nav-tab-wrapper stairbuilder-tabs" data-group="<?php echo (int) $group_index; ?>">
								<?php foreach ( $group_slugs as $slug ) :
									if ( ! isset( $this->schema[ $slug ] ) ) { continue; }
									$tab = $this->schema[ $slug ]; ?>
									<a href="#stairbuilder-tab-<?php echo esc_attr( $slug ); ?>"
										class="nav-tab <?php echo $slug === $group_first ? 'nav-tab-active' : ''; ?>"
										data-tab="<?php echo esc_attr( $slug ); ?>"
										data-group="<?php echo (int) $group_index; ?>">
										<?php echo esc_html( $tab['label'] ); ?>
									</a>
								<?php endforeach; ?>
							</nav>

							<?php foreach ( $group_slugs as $slug ) :
								if ( ! isset( $this->schema[ $slug ] ) ) { continue; }
								$tab = $this->schema[ $slug ]; ?>
								<div id="stairbuilder-tab-<?php echo esc_attr( $slug ); ?>"
									class="stairbuilder-tab-panel<?php echo $slug === $group_first ? ' is-active' : ''; ?>"
									data-group="<?php echo (int) $group_index; ?>">
									<?php $this->render_tab_body( $slug, $tab ); ?>
								</div>
							<?php endforeach; ?>
						</div>
					<?php endforeach; ?>

					<?php submit_button( __( 'Save Settings', 'stairbuilder' ) ); ?>
				</form>
			</div>
			<?php
		}

		/**
		 * Render a single tab's body.
		 *
		 * If the tab contains one or more "component groups" (a toggle field
		 * followed by 4 conditional price/id fields for pine + oak), those
		 * are rendered as 3-column component rows. Any remaining fields are
		 * rendered via the standard form-table layout underneath.
		 */
		/**
		 * Render the Help / Shortcodes documentation tab. Static reference for the
		 * [stairbuilder_form] shortcode and its stair_type / stair_config attributes.
		 */
		private function render_help_tab() {
			// Each row: shortcode, what page it represents, what it does.
			$rows = array(
				array( '[stairbuilder_form stair_type="straight"]', 'Straight Flight', 'Single straight flight. No turn.' ),
				array( '[stairbuilder_form stair_type="quarter"]', 'Quarter Turn (open)', 'Quarter-turn form, customer chooses the turn type (landing / winders).' ),
				array( '[stairbuilder_form stair_type="half"]', 'Half Turn (open)', 'Half-turn form, customer chooses both turns.' ),
				array( '[stairbuilder_form stair_type="quarter" stair_config="landing"]', 'Quarter Landing', 'Locks the turn to a Quarter Landing.' ),
				array( '[stairbuilder_form stair_type="quarter" stair_config="winder"]', 'Single Winder', 'Winder page — customer still picks 2 or 3 winders (not locked).' ),
				array( '[stairbuilder_form stair_type="half" stair_config="landing"]', 'Half Landing', 'Locks the turn to a Half Landing (single 180° landing).' ),
				array( '[stairbuilder_form stair_type="half" stair_config="winder"]', 'Double Winder', 'Winder page — both turns are customer choice (not locked).' ),
				array( '[stairbuilder_form stair_type="half" stair_config="double_quarter"]', 'Double Quarter', 'Locks both turns to Quarter Landings (two 90° turns).' ),
			);
			?>
			<div class="stairbuilder-help">
				<h2><?php esc_html_e( 'Staircase Form Shortcode', 'stairbuilder' ); ?></h2>
				<p><?php esc_html_e( 'Place a configurator on any page or post with the shortcode below. Two attributes control it:', 'stairbuilder' ); ?></p>
				<ul style="list-style:disc;margin-left:20px;">
					<li><code>stair_type</code> — <?php esc_html_e( 'which flight: ', 'stairbuilder' ); ?><code>straight</code>, <code>quarter</code>, <code>half</code>. <?php esc_html_e( 'Defaults to', 'stairbuilder' ); ?> <code>straight</code> <?php esc_html_e( 'if omitted.', 'stairbuilder' ); ?></li>
					<li><code>stair_config</code> <?php esc_html_e( '(optional) — pre-selects and locks the turn for fixed-configuration pages: ', 'stairbuilder' ); ?><code>landing</code>, <code>winder</code>, <code>double_quarter</code>.</li>
				</ul>

				<h3><?php esc_html_e( 'Every variation', 'stairbuilder' ); ?></h3>
				<table class="widefat striped" style="max-width:880px;">
					<thead>
						<tr>
							<th><?php esc_html_e( 'Shortcode', 'stairbuilder' ); ?></th>
							<th><?php esc_html_e( 'Page', 'stairbuilder' ); ?></th>
							<th><?php esc_html_e( 'Behaviour', 'stairbuilder' ); ?></th>
						</tr>
					</thead>
					<tbody>
						<?php foreach ( $rows as $r ) : ?>
						<tr>
							<td><code style="white-space:nowrap;"><?php echo esc_html( $r[0] ); ?></code></td>
							<td><strong><?php echo esc_html( $r[1] ); ?></strong></td>
							<td><?php echo esc_html( $r[2] ); ?></td>
						</tr>
						<?php endforeach; ?>
					</tbody>
				</table>

				<h3><?php esc_html_e( 'Notes', 'stairbuilder' ); ?></h3>
				<ul style="list-style:disc;margin-left:20px;">
					<li><?php esc_html_e( 'Locked configs (landing / double_quarter) pre-select the turn and disable the field, so the customer cannot change the staircase configuration on that page.', 'stairbuilder' ); ?></li>
					<li><?php esc_html_e( 'Winder configs do NOT lock anything — the customer still chooses 2 or 3 winders. The stair_config value is recorded on the quote/PDF for identification only.', 'stairbuilder' ); ?></li>
					<li><?php esc_html_e( 'stair_config only applies to quarter and half. It is ignored on straight, and any unknown value is ignored (falls back to the open form).', 'stairbuilder' ); ?></li>
					<li><?php esc_html_e( 'The chosen type/config is shown on the generated PDF as the "Staircase Type" line.', 'stairbuilder' ); ?></li>
				</ul>
			</div>
			<?php
		}

		private function render_tab_body( $slug, $tab ) {
			// Documentation-only tab — static reference, no form fields.
			if ( isset( $tab['layout'] ) && $tab['layout'] === 'help' ) {
				$this->render_help_tab();
				return;
			}
			// Bespoke layout: paired (toggle, price) rows rendered inline on one line.
			// Fields flagged `sidebar => true` render in a right-hand column.
			if ( isset( $tab['layout'] ) && $tab['layout'] === 'paired_rows' ) {
				$main_fields = array();
				$side_fields = array();
				$full_fields = array();
				foreach ( $tab['fields'] as $f ) {
					if ( ! empty( $f['full_row'] ) ) {
						$full_fields[] = $f;
					} elseif ( ! empty( $f['sidebar'] ) ) {
						$side_fields[] = $f;
					} else {
						$main_fields[] = $f;
					}
				}
				// Full-width section toggles (e.g. a master on/off for the whole tab)
				// render as a banner above the paired (toggle, price) rows.
				foreach ( $full_fields as $f ) {
					$this->render_full_toggle( $f );
				}
				if ( $side_fields ) {
					echo '<div class="stairbuilder-paired-tab">';
					echo '<div class="stairbuilder-paired-main">';
					$this->render_paired_rows( $main_fields );
					echo '</div>';
					echo '<div class="stairbuilder-paired-side">';
					foreach ( $side_fields as $f ) {
						echo '<h3 class="stairbuilder-side-title">' . esc_html( $f['label'] ) . '</h3>';
						$this->render_field( $f );
					}
					echo '</div>';
					echo '</div>';
				} else {
					$this->render_paired_rows( $main_fields );
				}
				return;
			}

			// Bespoke layout: simple multi-column grid of plain fields. Each
			// field renders its own label above the input (no form-table <th>).
			if ( isset( $tab['layout'] ) && $tab['layout'] === 'grid' ) {
				// A field carrying a `group` key starts a new bordered section;
				// following fields without one belong to the current section.
				$open = false;
				foreach ( $tab['fields'] as $f ) {
					if ( isset( $f['group'] ) || ! $open ) {
						if ( $open ) {
							echo '</div></div>'; // close .stairbuilder-grid + .stairbuilder-grid-group
						}
						echo '<div class="stairbuilder-grid-group">';
						if ( ! empty( $f['group'] ) ) {
							echo '<h3 class="stairbuilder-grid-group-title">' . esc_html( $f['group'] ) . '</h3>';
						}
						echo '<div class="stairbuilder-grid">';
						$open = true;
					}
					echo '<div class="stairbuilder-grid-item">';
					echo '<label class="stairbuilder-grid-label" for="' . esc_attr( $f['id'] ) . '">' . esc_html( $f['label'] ) . '</label>';
					$this->render_field( $f );
					echo '</div>';
				}
				if ( $open ) {
					echo '</div></div>';
				}
				return;
			}

			$blocks = $this->detect_component_blocks( $tab['fields'] );

			// Does this tab have any component-style blocks or rich card
			// repeaters? Both render full-width (title above, no form-table
			// left-hand label column) so they match the component cards.
			$has_components = false;
			$has_card       = false;
			foreach ( $blocks as $b ) {
				if ( $b['type'] === 'component' ) {
					$has_components = true;
				} elseif ( $this->is_card_repeater( $b['field'] ) ) {
					$has_card = true;
				}
			}

			if ( ! $has_components && ! $has_card ) {
				// Standard form-table rendering for plain-field tabs.
				echo '<table class="form-table" role="presentation">';
				do_settings_fields( self::PAGE_ID . '-' . $slug, 'stairbuilder_section_' . $slug );
				echo '</table>';
				return;
			}

			// Mixed layout: component cards + card repeaters render full-width
			// with their title above; any other stray field falls back to a
			// single-row form-table to stay visible.
			echo '<div class="stairbuilder-components">';
			foreach ( $blocks as $block ) {
				if ( $block['type'] === 'component' ) {
					$this->render_component_row( $block );
				} elseif ( $this->is_card_repeater( $block['field'] ) ) {
					echo '<div class="stairbuilder-card-block">';
					echo '<h3 class="stairbuilder-component-title">' . esc_html( $block['field']['label'] ) . '</h3>';
					$this->render_field( $block['field'] );
					echo '</div>';
				} else {
					// A stray non-component field inside a component-heavy tab —
					// render it as a single-row form-table to keep it visible.
					echo '<table class="form-table stairbuilder-single-row" role="presentation"><tr>';
					echo '<th scope="row">' . esc_html( $block['field']['label'] ) . '</th>';
					echo '<td>';
					$this->render_field( $block['field'] );
					echo '</td></tr></table>';
				}
			}
			echo '</div>';
		}

		/** True when a field is a rich "card" style repeater. */
		private function is_card_repeater( $field ) {
			return isset( $field['type'], $field['style'] ) && $field['type'] === 'repeater' && $field['style'] === 'card';
		}

		/**
		 * Walk a tab's flat fields list and group any [toggle + 4 conditional
		 * price/id fields] sequences into a single "component" block.
		 *
		 * Matches the ACF pattern used for newel posts, caps, handrails, and
		 * spindles: each component is one toggle followed by pine_price,
		 * pine_id, oak_price, oak_id (all conditional on the toggle's value).
		 *
		 * @param array $fields Flat list of field specs.
		 * @return array[] List of blocks: {type:'component', toggle, variants}
		 *                 or {type:'single', field}.
		 */
		private function detect_component_blocks( $fields ) {
			$blocks = array();
			$i = 0;
			$n = count( $fields );

			while ( $i < $n ) {
				$f = $fields[ $i ];

				// Candidate: a toggle followed by 4 conditional fields bound to it.
				if ( $f['type'] === 'toggle' && ( $i + 4 ) < $n ) {
					$next = array_slice( $fields, $i + 1, 4 );
					$all_bound = count( $next ) === 4;
					foreach ( $next as $nf ) {
						if ( empty( $nf['show_when'] )
							|| $nf['show_when']['field'] !== $f['id']
							|| ! in_array( $nf['type'], array( 'price', 'product_id' ), true ) ) {
							$all_bound = false;
							break;
						}
					}
					if ( $all_bound ) {
						// Expected order: pine_price, pine_id, oak_price, oak_id.
						$blocks[] = array(
							'type'    => 'component',
							'title'   => preg_replace( '/\s*Options\s*$/i', '', $f['label'] ),
							'toggle'  => $f,
							'variants' => array(
								'Pine' => array( 'price' => $next[0], 'product_id' => $next[1] ),
								'Oak'  => array( 'price' => $next[2], 'product_id' => $next[3] ),
							),
						);
						$i += 5;
						continue;
					}
				}

				$blocks[] = array( 'type' => 'single', 'field' => $f );
				$i++;
			}

			return $blocks;
		}

		/**
		 * Render a single full-width section toggle (a master on/off switch),
		 * shown as a banner above paired rows. Optional `description` renders
		 * underneath as help text.
		 */
		private function render_full_toggle( $field ) {
			$options = get_option( self::OPTION_KEY, array() );
			if ( ! is_array( $options ) ) { $options = array(); }

			$value = isset( $options[ $field['id'] ] ) ? $options[ $field['id'] ] : null;
			if ( $value === null && isset( $field['default'] ) ) {
				$value = $field['default'];
			}
			$on   = ! empty( $value );
			$name = self::OPTION_KEY . '[' . $field['id'] . ']';
			?>
			<div class="stairbuilder-section-toggle">
				<label class="stairbuilder-switch">
					<input type="hidden" name="<?php echo esc_attr( $name ); ?>" value="0" />
					<input type="checkbox"
						id="<?php echo esc_attr( $field['id'] ); ?>"
						name="<?php echo esc_attr( $name ); ?>"
						value="1"
						<?php checked( $on ); ?> />
					<span class="stairbuilder-switch-track"><span class="stairbuilder-switch-thumb"></span></span>
					<span class="stairbuilder-switch-text"><?php echo wp_kses_post( isset( $field['toggle_label'] ) ? $field['toggle_label'] : '' ); ?></span>
				</label>
				<?php if ( ! empty( $field['description'] ) ) : ?>
					<p class="description"><?php echo wp_kses_post( $field['description'] ); ?></p>
				<?php endif; ?>
			</div>
			<?php
		}

		/**
		 * Render fields as pairs of (toggle, number) on a single row:
		 * [✓ Toggle Label  ............  Price Label  £___]
		 *
		 * Pairs are detected by sequence — every two consecutive fields form a row.
		 */
		private function render_paired_rows( $fields ) {
			$options = get_option( self::OPTION_KEY, array() );
			if ( ! is_array( $options ) ) { $options = array(); }
			?>
			<table class="form-table stairbuilder-paired-rows" role="presentation">
				<tbody>
			<?php
			$n = count( $fields );
			for ( $i = 0; $i < $n; $i += 2 ) {
				$toggle = isset( $fields[ $i ] )     ? $fields[ $i ]     : null;
				$price  = isset( $fields[ $i + 1 ] ) ? $fields[ $i + 1 ] : null;
				if ( ! $toggle || ! $price ) { continue; }

				$toggle_value = isset( $options[ $toggle['id'] ] ) ? $options[ $toggle['id'] ] : null;
				if ( $toggle_value === null && isset( $toggle['default'] ) ) {
					$toggle_value = $toggle['default'];
				}
				$toggle_on   = ! empty( $toggle_value );
				$toggle_name = self::OPTION_KEY . '[' . $toggle['id'] . ']';
				$price_value = isset( $options[ $price['id'] ] ) ? $options[ $price['id'] ] : '';
				$price_name  = self::OPTION_KEY . '[' . $price['id'] . ']';
				?>
				<tr>
					<th scope="row">
						<label class="stairbuilder-switch">
							<input type="hidden" name="<?php echo esc_attr( $toggle_name ); ?>" value="0" />
							<input type="checkbox"
								id="<?php echo esc_attr( $toggle['id'] ); ?>"
								name="<?php echo esc_attr( $toggle_name ); ?>"
								value="1"
								<?php checked( $toggle_on ); ?> />
							<span class="stairbuilder-switch-track"><span class="stairbuilder-switch-thumb"></span></span>
							<span class="stairbuilder-switch-text"><?php echo esc_html( isset( $toggle['toggle_label'] ) ? $toggle['toggle_label'] : '' ); ?></span>
						</label>
					</th>
					<td>
						<div class="stairbuilder-field"
							data-field-id="<?php echo esc_attr( $price['id'] ); ?>"
							data-disable-when="<?php echo esc_attr( $toggle['id'] ); ?>"
							data-disable-equals="0">
							<span class="stairbuilder-currency">£</span>
							<input type="number" step="any"
								id="<?php echo esc_attr( $price['id'] ); ?>"
								name="<?php echo esc_attr( $price_name ); ?>"
								value="<?php echo esc_attr( $price_value ); ?>"
								class="small-text" />
						</div>
					</td>
				</tr>
				<?php
			}
			?>
				</tbody>
			</table>
			<?php
		}

		/**
		 * Render a single component row: [toggle + title | Pine variant | Oak variant].
		 */
		private function render_component_row( $block ) {
			$toggle = $block['toggle'];
			$options = get_option( self::OPTION_KEY, array() );
			if ( ! is_array( $options ) ) { $options = array(); }

			$toggle_value = isset( $options[ $toggle['id'] ] ) ? $options[ $toggle['id'] ] : 0;
			$toggle_name  = self::OPTION_KEY . '[' . $toggle['id'] . ']';
			$toggle_on    = ! empty( $toggle_value );
			?>
			<div class="stairbuilder-component">
				<div class="stairbuilder-component-col stairbuilder-component-header">
					<h3 class="stairbuilder-component-title"><?php echo esc_html( $block['title'] ); ?></h3>
					<label class="stairbuilder-switch">
						<input type="hidden" name="<?php echo esc_attr( $toggle_name ); ?>" value="0" />
						<input type="checkbox"
							id="<?php echo esc_attr( $toggle['id'] ); ?>"
							name="<?php echo esc_attr( $toggle_name ); ?>"
							value="1"
							<?php checked( $toggle_on ); ?> />
						<span class="stairbuilder-switch-track"><span class="stairbuilder-switch-thumb"></span></span>
						<span class="stairbuilder-switch-text"><?php esc_html_e( 'Use Product ID', 'stairbuilder' ); ?></span>
					</label>
					<p class="description"><?php esc_html_e( 'Add a price direct, or reference a WooCommerce product ID.', 'stairbuilder' ); ?></p>
				</div>

				<?php foreach ( $block['variants'] as $material => $pair ) :
					$price_field = $pair['price'];
					$id_field    = $pair['product_id'];
					$price_val   = isset( $options[ $price_field['id'] ] ) ? $options[ $price_field['id'] ] : '';
					$id_val      = isset( $options[ $id_field['id'] ] ) ? $options[ $id_field['id'] ] : '';
					$price_name  = self::OPTION_KEY . '[' . $price_field['id'] . ']';
					$id_name     = self::OPTION_KEY . '[' . $id_field['id'] . ']';
					?>
					<div class="stairbuilder-component-col stairbuilder-component-variant">
						<h4 class="stairbuilder-variant-title"><?php echo esc_html( $material . ' ' . $block['title'] ); ?></h4>

						<div class="stairbuilder-field stairbuilder-variant-price"
							data-field-id="<?php echo esc_attr( $price_field['id'] ); ?>"
							data-show-when="<?php echo esc_attr( $toggle['id'] ); ?>"
							data-show-equals="0">
							<label class="stairbuilder-variant-label" for="<?php echo esc_attr( $price_field['id'] ); ?>"><?php esc_html_e( 'Price', 'stairbuilder' ); ?></label>
							<span class="stairbuilder-currency">£</span>
							<input type="number" step="0.01" min="0"
								id="<?php echo esc_attr( $price_field['id'] ); ?>"
								name="<?php echo esc_attr( $price_name ); ?>"
								value="<?php echo esc_attr( $price_val ); ?>"
								class="regular-text" />
						</div>

						<div class="stairbuilder-field stairbuilder-variant-id"
							data-field-id="<?php echo esc_attr( $id_field['id'] ); ?>"
							data-show-when="<?php echo esc_attr( $toggle['id'] ); ?>"
							data-show-equals="1">
							<label class="stairbuilder-variant-label" for="<?php echo esc_attr( $id_field['id'] ); ?>"><?php esc_html_e( 'Product ID', 'stairbuilder' ); ?></label>
							<input type="number" step="1" min="0"
								id="<?php echo esc_attr( $id_field['id'] ); ?>"
								name="<?php echo esc_attr( $id_name ); ?>"
								value="<?php echo esc_attr( $id_val ); ?>"
								class="regular-text"
								placeholder="<?php esc_attr_e( 'Variation ID', 'stairbuilder' ); ?>" />
						</div>
					</div>
				<?php endforeach; ?>
			</div>
			<?php
		}

		/* ------------------------------------------------------------------ */
		/* Admin assets                                                         */
		/* ------------------------------------------------------------------ */

		public function enqueue( $hook ) {
			// Only load on our settings page.
			if ( $hook !== 'settings_page_' . self::PAGE_SLUG ) {
				return;
			}

			wp_enqueue_style( 'wp-color-picker' );
			wp_enqueue_script( 'wp-color-picker' );

			wp_add_inline_style(
				'wp-color-picker',
				'.stairbuilder-pricing-wrap .stairbuilder-tab-group { margin-bottom: 24px; }
				.stairbuilder-pricing-wrap .stairbuilder-tab-panel { display: none; background: #fff; padding: 16px 20px; border: 1px solid #c3c4c7; border-top: none; }
				.stairbuilder-pricing-wrap .stairbuilder-tab-panel.is-active { display: block; }
				.stairbuilder-pricing-wrap .stairbuilder-currency { font-weight: 600; margin-right: 4px; }
				.stairbuilder-pricing-wrap .stairbuilder-field { line-height: 28px; }
				.stairbuilder-pricing-wrap .stairbuilder-field.is-hidden,
				.stairbuilder-pricing-wrap tr.is-hidden { display: none; }
				.stairbuilder-pricing-wrap .stairbuilder-field.is-disabled { opacity: 0.45; }
				.stairbuilder-pricing-wrap .stairbuilder-field.is-disabled input { pointer-events: none; background: #f0f0f1; }
				.stairbuilder-pricing-wrap .stairbuilder-paired-rows th { width: 280px; }
				.stairbuilder-pricing-wrap .stairbuilder-paired-tab { display: grid; grid-template-columns: minmax(0, 1fr) minmax(260px, 380px); gap: 32px; align-items: start; }
				.stairbuilder-pricing-wrap .stairbuilder-paired-side .stairbuilder-side-title { margin: 0 0 8px; font-size: 14px; font-weight: 600; }
				.stairbuilder-pricing-wrap .stairbuilder-paired-side .stairbuilder-repeater { margin-top: 0; }
				@media (max-width: 960px) { .stairbuilder-pricing-wrap .stairbuilder-paired-tab { grid-template-columns: 1fr; } }
				.stairbuilder-pricing-wrap .stairbuilder-repeater { margin-top: 8px; max-width: 720px; }
				.stairbuilder-pricing-wrap .stairbuilder-repeater input { width: 100%; }
				.stairbuilder-pricing-wrap .form-table th { width: 260px; padding-left: 10px; }

				/* Grid layout — plain fields (e.g. Brand Colours) flow in columns */
				.stairbuilder-pricing-wrap .stairbuilder-grid-group { border: 1px solid #dcdcde; border-radius: 12px; padding: 20px 24px; margin: 0 0 20px; background: #fdfdfd; }
				.stairbuilder-pricing-wrap .stairbuilder-grid-group-title { margin: 0 0 14px; font-size: 14px; font-weight: 600; color: #1d2327; }
				.stairbuilder-pricing-wrap .stairbuilder-grid { display: flex; flex-wrap: wrap; gap: 16px 24px; margin-top: 8px; }
				.stairbuilder-pricing-wrap .stairbuilder-grid-item { flex: 0 1 calc(33.333% - 24px); min-width: 200px; box-sizing: border-box; }
				.stairbuilder-pricing-wrap .stairbuilder-grid-label { display: block; font-weight: 600; margin-bottom: 4px; color: #1d2327; }
				@media (max-width: 1200px) { .stairbuilder-pricing-wrap .stairbuilder-grid-item { flex-basis: calc(50% - 24px); } }
				@media (max-width: 600px) { .stairbuilder-pricing-wrap .stairbuilder-grid-item { flex-basis: 100%; } }

				/* Component rows — 3-column layout for toggle-bearing pricing items */
				.stairbuilder-pricing-wrap .stairbuilder-components { display: flex; flex-direction: column; gap: 12px; }
				.stairbuilder-pricing-wrap .stairbuilder-component {
					display: grid;
					grid-template-columns: minmax(240px, 1.2fr) 1fr 1fr;
					gap: 16px;
					padding: 14px 16px;
					background: #f6f7f7;
					border: 1px solid #e0e0e0;
					border-radius: 4px;
				}
				.stairbuilder-pricing-wrap .stairbuilder-component-col { min-width: 0; }
				.stairbuilder-pricing-wrap .stairbuilder-component-title { margin: 0 0 8px; font-size: 14px; font-weight: 600; }
				.stairbuilder-pricing-wrap .stairbuilder-variant-title { margin: 0 0 6px; font-size: 13px; font-weight: 600; color: #1d2327; }
				.stairbuilder-pricing-wrap .stairbuilder-variant-label { display: block; font-size: 12px; color: #50575e; margin-bottom: 2px; }
				.stairbuilder-pricing-wrap .stairbuilder-component .description { margin: 6px 0 0; font-size: 12px; color: #646970; }
				.stairbuilder-pricing-wrap .stairbuilder-component input[type="number"] { max-width: 140px; }

				/* Toggle switch (pill style) */
				.stairbuilder-pricing-wrap .stairbuilder-switch { display: inline-flex; align-items: center; gap: 10px; cursor: pointer; user-select: none; }
				.stairbuilder-pricing-wrap .stairbuilder-switch input[type="checkbox"] { position: absolute; opacity: 0; width: 0; height: 0; }
				.stairbuilder-pricing-wrap .stairbuilder-switch-track {
					position: relative;
					display: inline-block;
					width: 40px; height: 22px;
					background: #c3c4c7;
					border-radius: 11px;
					transition: background 0.15s ease;
				}
				.stairbuilder-pricing-wrap .stairbuilder-switch-thumb {
					position: absolute;
					top: 2px; left: 2px;
					width: 18px; height: 18px;
					background: #fff;
					border-radius: 50%;
					box-shadow: 0 1px 2px rgba(0,0,0,0.2);
					transition: transform 0.15s ease;
				}
				.stairbuilder-pricing-wrap .stairbuilder-switch input:checked + .stairbuilder-switch-track { background: #2271b1; }
				.stairbuilder-pricing-wrap .stairbuilder-switch input:checked + .stairbuilder-switch-track .stairbuilder-switch-thumb { transform: translateX(18px); }
				.stairbuilder-pricing-wrap .stairbuilder-switch input:focus-visible + .stairbuilder-switch-track { outline: 2px solid #2271b1; outline-offset: 2px; }
				.stairbuilder-pricing-wrap .stairbuilder-switch-text { font-size: 13px; color: #1d2327; }

				/* Full-width section master toggle, banner above paired rows */
				.stairbuilder-pricing-wrap .stairbuilder-section-toggle { margin: 0 0 18px; padding: 14px 16px; background: #fff; border: 1px solid #c3c4c7; border-left: 4px solid #2271b1; border-radius: 4px; }
				.stairbuilder-pricing-wrap .stairbuilder-section-toggle .stairbuilder-switch-text { font-size: 14px; font-weight: 600; }
				.stairbuilder-pricing-wrap .stairbuilder-section-toggle .description { margin: 8px 0 0; }

				/* Stray single field inside a component-tab */
				.stairbuilder-pricing-wrap .stairbuilder-single-row { margin-top: 12px; background: #fff; }

				/* Narrow screens — stack the columns */
				@media (max-width: 960px) {
					.stairbuilder-pricing-wrap .stairbuilder-component {
						grid-template-columns: 1fr;
					}
				}

				/* Rich card repeaters (newels, caps, handrails, spindles) — each
				   row reuses the .stairbuilder-component card look (grey bg,
				   border, padding, radius) so they match the baserail cards. */
				.stairbuilder-pricing-wrap .stairbuilder-card-block { margin-bottom: 20px; }
				.stairbuilder-pricing-wrap .stairbuilder-card-block > .stairbuilder-component-title { margin: 0 0 10px; font-size: 14px; font-weight: 600; }
				.stairbuilder-pricing-wrap .stairbuilder-card-repeater .stairbuilder-cards { display: flex; flex-direction: column; gap: 12px; }
				.stairbuilder-pricing-wrap .stairbuilder-card-row {
					grid-template-columns: minmax(220px, 1.4fr) 1fr 1fr 44px;
					align-items: start;
					margin: 0;
				}
				/* Generic card rows (strings, construction, treads, risers) —
				   each sub-field a labelled input flowing in a flex row. */
				.stairbuilder-pricing-wrap .stairbuilder-card-row-generic {
					display: flex;
					flex-wrap: wrap;
					gap: 12px 16px;
					align-items: flex-end;
					grid-template-columns: none;
				}
				.stairbuilder-pricing-wrap .stairbuilder-card-field { flex: 1 1 160px; min-width: 140px; }
				.stairbuilder-pricing-wrap .stairbuilder-card-row-generic .stairbuilder-card-label { margin-bottom: 0; }
				.stairbuilder-pricing-wrap .stairbuilder-card-row-generic .stairbuilder-card-actions { flex: 0 0 auto; margin-left: auto; }
				.stairbuilder-pricing-wrap .stairbuilder-card-label { display: block; font-size: 12px; font-weight: 600; color: #1d2327; margin-bottom: 8px; }
				.stairbuilder-pricing-wrap .stairbuilder-card-label input { font-weight: 400; }
				.stairbuilder-pricing-wrap .stairbuilder-card-qty input { max-width: 90px; }
				.stairbuilder-pricing-wrap .stairbuilder-card-switch { margin-top: 4px; }
				.stairbuilder-pricing-wrap .stairbuilder-card-actions { text-align: right; }
				.stairbuilder-pricing-wrap .stairbuilder-card-remove { color: #b32d2e; }
				/* Per-row toggle: show price by default, product ID when switched on */
				.stairbuilder-pricing-wrap .stairbuilder-card-row .bd-variant-id { display: none; }
				.stairbuilder-pricing-wrap .stairbuilder-card-row.is-product-id .bd-variant-price { display: none; }
				.stairbuilder-pricing-wrap .stairbuilder-card-row.is-product-id .bd-variant-id { display: block; }
				/* Spindle material-mode rows: the fixed Pine/Oak 3-col grid does not fit
				   the variable mode blocks (wood = 2 cols, metal = 1, glass = opts + 1),
				   so switch those rows to a flex layout and show only the active block. */
				/* Balustrading rows render as a SINGLE compact line: name / material
				   type (+ glass pricing / panel width / gap in glass mode) / price
				   column(s) / toggle. The header and the active price block use
				   display:contents so their fields become direct flex items of the row;
				   `order` keeps the toggle + remove button on the right regardless of
				   DOM order. Code is hidden on these rows (see render_card_row). */
				.stairbuilder-pricing-wrap .stairbuilder-card-row.mode-wood,
				.stairbuilder-pricing-wrap .stairbuilder-card-row.mode-metal,
				.stairbuilder-pricing-wrap .stairbuilder-card-row.mode-glass { display: flex; flex-wrap: wrap; align-items: flex-end; gap: 14px; }
				.stairbuilder-pricing-wrap .stairbuilder-card-row[class*="mode-"] .stairbuilder-component-header { display: contents; }
				.stairbuilder-pricing-wrap .stairbuilder-card-row[class*="mode-"] .stairbuilder-card-label { flex: 0 1 150px; margin-bottom: 0; order: 1; }
				/* Glass basis fields appear only when the row is in glass mode. */
				.stairbuilder-pricing-wrap .stairbuilder-card-row .bd-glass-inline { display: none; }
				.stairbuilder-pricing-wrap .stairbuilder-card-row.mode-glass .bd-glass-inline { display: block; }
				.stairbuilder-pricing-wrap .stairbuilder-card-row .bd-mode-block { display: none; }
				.stairbuilder-pricing-wrap .stairbuilder-card-row.mode-wood .bd-mode-wood,
				.stairbuilder-pricing-wrap .stairbuilder-card-row.mode-metal .bd-mode-metal,
				.stairbuilder-pricing-wrap .stairbuilder-card-row.mode-glass .bd-mode-glass { display: contents; }
				.stairbuilder-pricing-wrap .stairbuilder-card-row[class*="mode-"] .stairbuilder-component-variant { flex: 0 1 150px; min-width: 130px; order: 2; }
				.stairbuilder-pricing-wrap .stairbuilder-card-row[class*="mode-"] .stairbuilder-card-switch { flex: 0 0 auto; order: 3; margin-left: auto; align-self: center; }
				.stairbuilder-pricing-wrap .stairbuilder-card-row[class*="mode-"] .stairbuilder-card-actions { flex: 0 0 auto; order: 4; align-self: center; }
				.stairbuilder-pricing-wrap .stairbuilder-card-mode select { font-weight: 400; }
				.stairbuilder-pricing-wrap .stairbuilder-card-add { margin-top: 14px; }
				@media (max-width: 960px) {
					.stairbuilder-pricing-wrap .stairbuilder-card-row { grid-template-columns: 1fr; }
					.stairbuilder-pricing-wrap .stairbuilder-card-actions { text-align: left; }
				}'
			);

			wp_add_inline_script(
				'wp-color-picker',
				'jQuery(function($){
					// Colour pickers
					$(".stairbuilder-color-picker").wpColorPicker();

					// Tab switching — scoped per tab-group so each row of tabs is independent
					$(".stairbuilder-tabs .nav-tab").on("click", function(e){
						e.preventDefault();
						var $tab = $(this);
						var slug = $tab.data("tab");
						var $wrapper = $tab.closest(".stairbuilder-tab-group");
						$wrapper.find(".nav-tab").removeClass("nav-tab-active");
						$tab.addClass("nav-tab-active");
						$wrapper.find(".stairbuilder-tab-panel").removeClass("is-active");
						$("#stairbuilder-tab-" + slug).addClass("is-active");
					});

					// Conditional visibility — hide the field wrapper and, if it lives
					// inside a form-table row, the enclosing <tr> too.
					function applyConditionals(){
						$(".stairbuilder-field[data-show-when]").each(function(){
							var $field = $(this);
							var depId = $field.data("show-when");
							var equals = String($field.data("show-equals"));
							var $dep = $("#" + depId);
							if (!$dep.length) return;
							var depVal = $dep.is(":checkbox") ? ($dep.is(":checked") ? "1" : "0") : String($dep.val());
							var show = depVal === equals;
							$field.toggleClass("is-hidden", !show);
							$field.closest("tr").toggleClass("is-hidden", !show);
						});
						// Conditional disable — grey out + disable rather than hide.
						$(".stairbuilder-field[data-disable-when]").each(function(){
							var $field = $(this);
							var depId = $field.data("disable-when");
							var equals = String($field.data("disable-equals"));
							var $dep = $("#" + depId);
							if (!$dep.length) return;
							var depVal = $dep.is(":checkbox") ? ($dep.is(":checked") ? "1" : "0") : String($dep.val());
							var disabled = depVal === equals;
							// Use readonly (still submits the value) rather than disabled (drops it).
							$field.toggleClass("is-disabled", disabled);
							$field.find("input, select, textarea").prop("readonly", disabled);
						});
					}
					applyConditionals();
					$(document).on("change", ".stairbuilder-pricing-wrap input[type=checkbox]", applyConditionals);

					// Repeater: add row
					$(document).on("click", ".stairbuilder-repeater-add", function(){
						var $table = $(this).closest("table.stairbuilder-repeater");
						var fieldId = $table.data("field-id");
						var subfields = $table.data("subfields");
						var idx = $table.find("tbody tr").length;
						var optName = "' . esc_js( self::OPTION_KEY ) . '";
						var $tr = $("<tr></tr>");
						subfields.forEach(function(sf){
							var inputType = sf.type === "number" ? "number" : "text";
							var step = sf.type === "number" ? " step=\"any\"" : "";
							$tr.append(
								"<td><input type=\"" + inputType + "\"" + step +
								" name=\"" + optName + "[" + fieldId + "][" + idx + "][" + sf.id + "]\"" +
								" value=\"\" class=\"widefat\" /></td>"
							);
						});
						$tr.append("<td><button type=\"button\" class=\"button stairbuilder-repeater-remove\">&times;</button></td>");
						$table.find("tbody").append($tr);
					});

					// Repeater: remove row
					$(document).on("click", ".stairbuilder-repeater-remove", function(){
						$(this).closest("tr").remove();
					});

					// Rich card repeater: add row by cloning the hidden prototype.
					// A monotonic per-repeater counter keeps POST indices unique even
					// after removals (PHP re-indexes on save).
					$(document).on("click", ".stairbuilder-card-add", function(){
						var $rep = $(this).closest(".stairbuilder-card-repeater");
						var next = parseInt($rep.attr("data-next-index"), 10);
						if (isNaN(next)) { next = $rep.find(".stairbuilder-cards .stairbuilder-card-row").length; }
						var proto = $rep.children(".stairbuilder-card-proto").html() || "";
						$rep.find(".stairbuilder-cards").append(proto.replace(/__i__/g, next));
						$rep.attr("data-next-index", next + 1);
					});

					// Rich card repeater: remove row
					$(document).on("click", ".stairbuilder-card-remove", function(){
						$(this).closest(".stairbuilder-card-row").remove();
					});

					// Rich card repeater: per-row Use-Product-ID switch toggles
					// price vs product-ID inputs for that row only.
					$(document).on("change", ".stairbuilder-card-repeater .bd-row-toggle", function(){
						$(this).closest(".stairbuilder-card-row").toggleClass("is-product-id", $(this).is(":checked"));
					});

					// Spindle rows: Material Type select swaps which mode block (Wood /
					// Metal / Glass) is shown. Cloned rows render with mode-wood baked in,
					// so no init pass is needed beyond this change handler.
					$(document).on("change", ".stairbuilder-card-repeater .bd-mode-select", function(){
						var v = $(this).val();
						var cls = v === "metal" ? "mode-metal" : (v === "glass" ? "mode-glass" : "mode-wood");
						$(this).closest(".stairbuilder-card-row")
							.removeClass("mode-wood mode-metal mode-glass")
							.addClass(cls);
					});
				});'
			);
		}

		/* ------------------------------------------------------------------ */
		/* One-shot backfill: spindle material_mode (v2.4)                      */
		/* ------------------------------------------------------------------ */

		/**
		 * Stamp existing spindle rows with material_mode = 'wood_pine_oak' so the
		 * admin shows them explicitly as Wood and the future bulk price tool sees a
		 * mode on every row. Purely additive — the renderer / price lookup / price
		 * calc already treat a missing mode as wood, so this changes no behaviour.
		 */
		public function maybe_backfill_balustrade_modes() {
			if ( get_option( self::BALLUSTRADE_MODES_FLAG ) ) {
				return;
			}
			$opts = get_option( self::OPTION_KEY, array() );
			if ( ! is_array( $opts ) || empty( $opts['spindle_types'] ) || ! is_array( $opts['spindle_types'] ) ) {
				update_option( self::BALLUSTRADE_MODES_FLAG, 1 );
				return;
			}
			$changed = false;
			foreach ( $opts['spindle_types'] as &$row ) {
				if ( is_array( $row ) && empty( $row['material_mode'] ) ) {
					$row['material_mode'] = 'wood_pine_oak';
					$changed = true;
				}
			}
			unset( $row );
			if ( $changed ) {
				update_option( self::OPTION_KEY, $opts );
			}
			update_option( self::BALLUSTRADE_MODES_FLAG, 1 );
		}

		/* ------------------------------------------------------------------ */
		/* One-shot migration from ACF                                          */
		/* ------------------------------------------------------------------ */

		public function maybe_migrate_from_acf() {
			if ( get_option( self::MIGRATION_FLAG ) ) {
				return;
			}
			if ( ! function_exists( 'get_field' ) ) {
				return; // ACF not active — nothing to migrate.
			}

			$existing = get_option( self::OPTION_KEY, array() );
			if ( is_array( $existing ) && ! empty( $existing ) ) {
				// Someone already populated the new option; don't overwrite.
				update_option( self::MIGRATION_FLAG, 1 );
				return;
			}

			$blob = array();

			foreach ( $this->schema as $tab ) {
				foreach ( $tab['fields'] as $field ) {
					$id = $field['id'];
					$v  = get_field( $id, 'option' );

					switch ( $field['type'] ) {
						case 'toggle':
							$blob[ $id ] = $v ? 1 : 0;
							break;
						case 'price':
						case 'number':
							$blob[ $id ] = ( $v === null || $v === '' ) ? '' : (float) $v;
							break;
						case 'product_id':
							$blob[ $id ] = ( $v === null || $v === '' ) ? '' : absint( $v );
							break;
						case 'color':
							$hex = is_scalar( $v ) ? sanitize_hex_color( (string) $v ) : '';
							$blob[ $id ] = $hex ? $hex : '';
							break;
						case 'textarea':
							$blob[ $id ] = is_scalar( $v ) ? sanitize_textarea_field( (string) $v ) : '';
							break;
						case 'text':
						case 'select':
							$blob[ $id ] = is_scalar( $v ) ? sanitize_text_field( (string) $v ) : '';
							break;
						case 'repeater':
							$blob[ $id ] = is_array( $v ) ? array_values( $v ) : array();
							break;
						default:
							$blob[ $id ] = $v;
					}
				}
			}

			$blob['_schema_version'] = self::SCHEMA_VERSION;

			update_option( self::OPTION_KEY, $blob );
			update_option( self::MIGRATION_FLAG, 1 );
		}

		/* ------------------------------------------------------------------ */
		/* One-shot v2 migration — flat component keys → typed repeater rows.   */
		/*                                                                      */
		/* Seeds newel_types / cap_types / handrail_types / spindle_types from  */
		/* the existing flat keys in stairbuilder_options so live prices and    */
		/* product IDs survive the conversion. Gated by its OWN flag — never    */
		/* reuse the ACF migration flag.                                        */
		/* ------------------------------------------------------------------ */

		public function maybe_migrate_repeaters_v2() {
			if ( get_option( self::REPEATER_MIGRATION_FLAG ) ) {
				return;
			}

			$opts = get_option( self::OPTION_KEY, array() );
			if ( ! is_array( $opts ) ) {
				$opts = array();
			}

			// If any repeater is already populated (manual setup or re-run),
			// leave it alone — just record that the migration is satisfied.
			if ( ! empty( $opts['newel_types'] ) || ! empty( $opts['cap_types'] )
				|| ! empty( $opts['handrail_types'] ) || ! empty( $opts['spindle_types'] ) ) {
				update_option( self::REPEATER_MIGRATION_FLAG, 1 );
				return;
			}

			$price = function( $k ) use ( $opts ) {
				$v = isset( $opts[ $k ] ) ? $opts[ $k ] : '';
				return ( $v === '' || $v === null ) ? '' : (float) $v;
			};
			$pid = function( $k ) use ( $opts ) {
				$v = isset( $opts[ $k ] ) ? $opts[ $k ] : '';
				return ( $v === '' || $v === null ) ? '' : absint( $v );
			};
			$tog = function( $k ) use ( $opts ) {
				return ! empty( $opts[ $k ] ) ? 1 : 0;
			};
			$row = function( $name, $code, $opt, $pp, $op, $pi, $oi, $extra = array() ) use ( $price, $pid, $tog ) {
				return array_merge( array(
					'name'           => $name,
					'code'           => $code,
					'use_product_id' => $tog( $opt ),
					'pine_price'     => $price( $pp ),
					'oak_price'      => $price( $op ),
					'pine_id'        => $pid( $pi ),
					'oak_id'         => $pid( $oi ),
				), $extra );
			};

			$opts['newel_types'] = array(
				$row( 'Plain Square', 'square',    'square_newel_option',    'pine_newel_post_price', 'oak_newel_post_price', 'pine_square_newel_post_id', 'oak_newel_post_id' ),
				$row( 'Chamfered',    'chamfered', 'chamfered_newel_option', 'pine_chmf_newel_price', 'oak_chmf_newel_price', 'pine_chmf_newel_post_id',   'oak_chmf_newel_post_id' ),
				$row( 'Turned',       'turned',    'turned_newel_option',    'pine_trn_newel_price',  'oak_trn_newel_price',  'pine_trn_newel_id',         'oak_trn_newel_id' ),
				$row( 'Base Only',    'base',      'newel_base_option',      'pine_newel_base_price', 'oak_newel_base_price', 'pine_newel_base_id',        'oak_newel_base_id' ),
			);

			$opts['handrail_types'] = array(
				$row( 'Crown', 'crown', 'crwn_hand_option', 'pine_crwn_hand_price', 'oak_crwn_hand_price', 'pine_crwn_hand_id', 'oak_crwn_hand_id' ),
				$row( 'HDR',   'hdr',   'hdr_hand_option',  'pine_hdr_hand_price',  'oak_hdr_hand_price',  'pine_hdr_hand_id',  'oak_hdr_hand_id' ),
			);

			$opts['spindle_types'] = array(
				// Seeded as 'fluted' (matches the form) — retires the old map key
				// 'flute' and the fluted→default fallthrough bug along with it.
				$row( 'Chamfered',  'chamfered',  'chmf_spin_option',  'pine_chmf_spindle_price',  'oak_chmf_spindle_price',  'pine_chmf_spindle_price_id', 'oak_chmf_spindle_id' ),
				$row( 'Edwardian',  'edwardian',  'edwa_spin_option',  'pine_edwa_spindle_price',  'oak_edwa_spindle_price',  'pine_edwa_spindle_id',       'oak_edwa_spindle_id' ),
				$row( 'Twist',      'twist',      'twist_spin_option', 'pine_twist_spindle_price', 'oak_twist_spindle_price', 'pine_twist_spindle_id',      'oak_twist_spindle_id' ),
				$row( 'Fluted',     'fluted',     'flute_spin_option', 'pine_flt_spindle_price',   'oak_flt_spindle_price',   'pine_flt_spindle_id',        'oak_flt_spindle_id' ),
				$row( 'Tulip',      'tulip',      'tulip_spin_option', 'pine_tlp_spindle_price',   'oak_tlp_spindle_price',   'pine_tlp_spindle_id',        'oak_tlp_spindle_id' ),
				$row( 'Victorian',  'victorian',  'vtrn_spin_option',  'pine_vtrn_spindle_price',  'oak_vtrn_spindle_price',  'pine_vtrn_spindle_id',       'oak_vtrn_spindle_id' ),
				$row( 'Provincial', 'provincial', 'prv_spin_option',   'pine_prv_spindle_price',   'oak_prv_spindle_price',   'pine_prv_spindle_id',        'oak_prv_spindle_id' ),
			);

			$opts['cap_types'] = array(
				$row( 'Flat',    'flat',    'flat_cap_option', 'pine_flat_cap_price',    'oak_flat_cap_price',    'pine_flat_cap_id',    'oak_flat_cap_id',    array( 'caps_per_newel' => 1 ) ),
				$row( 'Pyramid', 'pyramid', 'pyr_cap_option',  'pine_pyramid_cap_price', 'oak_pyramid_cap_price', 'pine_pyramid_cap_id', 'oak_pyramid_cap_id', array( 'caps_per_newel' => 1 ) ),
				$row( 'Ball',    'ball',    'ball_cap_option', 'pine_ball_cap_price',    'oak_ball_cap_price',    'pine_ball_cap_id',    'oak_ball_cap_id',    array( 'caps_per_newel' => 1 ) ),
			);

			update_option( self::OPTION_KEY, $opts );
			update_option( self::REPEATER_MIGRATION_FLAG, 1 );
		}

		/* ------------------------------------------------------------------ */
		/* Tab groups — splits the tab bar into multiple rows, mirroring the   */
		/* ACF `endpoint` flag that divided component pricing (top) from       */
		/* staircase-wide settings (bottom).                                   */
		/* ------------------------------------------------------------------ */

		private function get_tab_groups() {
			return array(
				// Group 1 — per-component pricing.
				array(
					'strings',
					'construction_types',
					'treads',
					'risers',
					'newel_posts',
					'newel_caps',
					'featured_step',
					'handrails_and_baserails',
					'spindles_balustrading',
				),
				// Group 2 — staircase-wide settings + display.
				array(
					'construction_settings',
					'base_costs',
					'quarter_landing',
					'half_landing',
					'postcode_areas',
					'delivery_options',
					'brand_colours',
					'help',
				),
			);
		}

		/* ------------------------------------------------------------------ */
		/* Schema                                                                */
		/* ------------------------------------------------------------------ */

			private function get_schema() {
				// Shared sub-field set for the rich "card" repeaters (newels,
				// handrails, spindles). Each row carries its own name/code, a
				// per-row Use-Product-ID switch, and pine/oak price + product ID.
				$variant_subfields = [
					['id' => 'name',           'label' => 'Name',           'type' => 'text'],
					['id' => 'code',           'label' => 'Code',           'type' => 'text'],
					['id' => 'use_product_id', 'label' => 'Use Product ID', 'type' => 'toggle'],
					['id' => 'pine_price',     'label' => 'Pine Price',     'type' => 'price'],
					['id' => 'oak_price',      'label' => 'Oak Price',      'type' => 'price'],
					['id' => 'pine_id',        'label' => 'Pine Product ID', 'type' => 'product_id'],
					['id' => 'oak_id',         'label' => 'Oak Product ID',  'type' => 'product_id'],
				];
				// Caps additionally carry a per-newel quantity multiplier. The
				// front-end cap select encodes this as `{code}:{caps_per_newel}`
				// (mirroring the always-present `none:0` no-cap choice), which the
				// price calc reads to multiply cap cost by the newel count.
				$cap_subfields = [
					['id' => 'name',           'label' => 'Name',            'type' => 'text'],
					['id' => 'code',           'label' => 'Code',            'type' => 'text'],
					['id' => 'caps_per_newel', 'label' => 'Caps / Newel',    'type' => 'number'],
					['id' => 'use_product_id', 'label' => 'Use Product ID',  'type' => 'toggle'],
					['id' => 'pine_price',     'label' => 'Pine Price',      'type' => 'price'],
					['id' => 'oak_price',      'label' => 'Oak Price',       'type' => 'price'],
					['id' => 'pine_id',        'label' => 'Pine Product ID', 'type' => 'product_id'],
					['id' => 'oak_id',         'label' => 'Oak Product ID',  'type' => 'product_id'],
				];
				// Spindle rows extend the wood variant with a material_mode dimension:
				// Wood (pine/oak, existing), Metal (single per-spindle price, count via
				// 141/112 divisor in priceCalc.js) and Glass (single price, per metre or
				// per panel). render_card_row() detects material_mode and renders the
				// per-mode blocks; the sanitiser/migration default missing modes to wood.
				// 'adjustable' marks prices the future bulk price-increase tool sweeps.
				$spindle_subfields = [
					['id' => 'name',           'label' => 'Name',            'type' => 'text'],
					['id' => 'code',           'label' => 'Code',            'type' => 'text'],
					['id' => 'material_mode',  'label' => 'Material Type',   'type' => 'select',
						'choices' => ['wood_pine_oak' => 'Wood (Pine / Oak)', 'metal' => 'Metal', 'glass' => 'Glass'],
						'default' => 'wood_pine_oak'],
					['id' => 'use_product_id', 'label' => 'Use Product ID',  'type' => 'toggle'],
					// Wood (pine/oak) — unchanged from the shared variant set.
					['id' => 'pine_price',     'label' => 'Pine Price',      'type' => 'price', 'adjustable' => true],
					['id' => 'oak_price',      'label' => 'Oak Price',       'type' => 'price', 'adjustable' => true],
					['id' => 'pine_id',        'label' => 'Pine Product ID', 'type' => 'product_id'],
					['id' => 'oak_id',         'label' => 'Oak Product ID',  'type' => 'product_id'],
					// Metal single-material price / product ID. Distinct keys from glass
					// so the hidden (inactive-mode) inputs can't clobber each other on save.
					['id' => 'metal_price',    'label' => 'Metal Price',     'type' => 'price', 'adjustable' => true],
					['id' => 'metal_id',       'label' => 'Metal Product ID', 'type' => 'product_id'],
					// Glass single-material price / product ID + per-metre vs per-panel
					// basis (+ panel width, used only when per-panel).
					['id' => 'glass_price',    'label' => 'Glass Price',     'type' => 'price', 'adjustable' => true],
					['id' => 'glass_id',       'label' => 'Glass Product ID', 'type' => 'product_id'],
					['id' => 'pricing_unit',   'label' => 'Glass Pricing',   'type' => 'select',
						'choices' => ['per_metre' => 'Per Linear Metre', 'per_panel' => 'Per Panel'],
						'default' => 'per_metre'],
					['id' => 'panel_width_mm', 'label' => 'Panel Width (mm)', 'type' => 'number'],
					['id' => 'panel_gap_mm',   'label' => 'Panel Gap (mm)',   'type' => 'number'],
				];
				return [
					'strings' => [
						'label' => 'Strings',
						'fields' => [
							[
								'id' => 'stringer_types',
								'label' => 'Add Stringer Types',
								'type' => 'repeater',
								'style' => 'card',
								'subfields' => [['id' => 'stringer_name', 'label' => 'Stringer Name', 'type' => 'text'], ['id' => 'stringer_code', 'label' => 'Stringer Code', 'type' => 'text'], ['id' => 'stringer_value', 'label' => 'Stringer Value', 'type' => 'number']],
							],
						],
					],
					'construction_types' => [
						'label' => 'Construction Types',
						'fields' => [
							[
								'id' => 'building_regs',
								'label' => 'Applicable Building Regs',
								'type' => 'repeater',
								'style' => 'card',
								'subfields' => [['id' => 'building_reg_name', 'label' => 'Name', 'type' => 'text'], ['id' => 'building_reg_value', 'label' => 'Value', 'type' => 'text']],
							],
							[
								'id' => 'construction_types',
								'label' => 'Add Construction Types',
								'type' => 'repeater',
								'style' => 'card',
								'subfields' => [['id' => 'construction_name', 'label' => 'Construction Name', 'type' => 'text'], ['id' => 'construction_code', 'label' => 'Construction Code', 'type' => 'text'], ['id' => 'construction_value', 'label' => 'Construction Value', 'type' => 'number']],
							],
						],
					],
					'treads' => [
						'label' => 'Treads',
						'fields' => [
							[
								'id' => 'tread_types',
								'label' => 'Add Tread Types',
								'type' => 'repeater',
								'style' => 'card',
								'subfields' => [['id' => 'tread_name', 'label' => 'Tread Name', 'type' => 'text'], ['id' => 'tread_code', 'label' => 'Tread Code', 'type' => 'text'], ['id' => 'tread_value', 'label' => 'Tread Value', 'type' => 'number']],
							],
							[
								'id' => 'tread_profiles',
								'label' => 'Add Tread Profiles',
								'type' => 'repeater',
								'style' => 'card',
								'subfields' => [['id' => 'tread_profile_name', 'label' => 'Profile Name', 'type' => 'text'], ['id' => 'tread_profile_code', 'label' => 'Profile Code', 'type' => 'text'], ['id' => 'tread_profile_value', 'label' => 'Per-Tread Surcharge', 'type' => 'number']],
							],
						],
					],
					'risers' => [
						'label' => 'Risers',
						'fields' => [
							[
								'id' => 'riser_types',
								'label' => 'Add Riser Types',
								'type' => 'repeater',
								'style' => 'card',
								'subfields' => [['id' => 'riser_name', 'label' => 'Riser Name', 'type' => 'text'], ['id' => 'riser_code', 'label' => 'Riser Code', 'type' => 'text'], ['id' => 'riser_value', 'label' => 'Riser Value', 'type' => 'number']],
							],
						],
					],
					'newel_posts' => [
						'label' => 'Newel Posts',
						'fields' => [
							[
								'id' => 'newel_types',
								'label' => 'Newel Post Types',
								'type' => 'repeater',
								'style' => 'card',
								'description' => 'Each row is a selectable newel style. Name = label shown to the customer; Code = stable machine key.',
								'subfields' => $variant_subfields,
							],
						],
					],
					'newel_caps' => [
						'label' => 'Newel Caps',
						'fields' => [
							[
								'id' => 'cap_types',
								'label' => 'Newel Cap Types',
								'type' => 'repeater',
								'style' => 'card',
								'reserved_codes' => ['none'],
								'description' => 'Each row is a selectable cap. "Caps / Newel" multiplies the cap cost by the newel count. A "None" choice is always offered on the form automatically — do not add it here ("none" is a reserved code).',
								'subfields' => $cap_subfields,
							],
						],
					],
					'featured_step' => [
						'label' => 'Featured Step',
						'fields' => [
							[
								'id' => 'mdf_bullnose_price',
								'label' => 'MDF Bullnose',
								'type' => 'number',
								'description' => 'Add a value to be used in builder forms.',
							],
							[
								'id' => 'ply_bullnose_price',
								'label' => 'Plywood Bullnose',
								'type' => 'number',
								'description' => 'Add a value to be used in builder forms.',
							],
							[
								'id' => 'pine_bullnose_price',
								'label' => 'Pine Bullnose',
								'type' => 'number',
								'description' => 'Add a value to be used in builder forms.',
							],
							[
								'id' => 'oak_bullnose_price',
								'label' => 'Oak Bullnose',
								'type' => 'number',
								'description' => 'Add a value to be used in builder forms.',
							],
							[
								'id' => 'mdf_curtail_price',
								'label' => 'MDF Curtail',
								'type' => 'number',
								'description' => 'Add a value to be used in builder forms.',
							],
							[
								'id' => 'ply_curtail_price',
								'label' => 'Plywood Curtail',
								'type' => 'number',
								'description' => 'Add a value to be used in builder forms.',
							],
							[
								'id' => 'pine_curtail_price',
								'label' => 'Pine Curtail',
								'type' => 'number',
								'description' => 'Add a value to be used in builder forms.',
							],
							[
								'id' => 'oak_curtail_price',
								'label' => 'Oak Curtail',
								'type' => 'number',
								'description' => 'Add a value to be used in builder forms.',
							],
							[
								'id' => 'mdf_dbl_curtail_price',
								'label' => 'MDF Double Curtail',
								'type' => 'number',
								'description' => 'Add a value to be used in builder forms.',
							],
							[
								'id' => 'ply_dbl_curtail_price',
								'label' => 'Plywood Double Curtail',
								'type' => 'number',
								'description' => 'Add a value to be used in builder forms.',
							],
							[
								'id' => 'pine_dbl_curtail_price',
								'label' => 'Pine Double Curtail',
								'type' => 'number',
								'description' => 'Add a value to be used in builder forms.',
							],
							[
								'id' => 'oak_dbl_curtail_price',
								'label' => 'Oak Double Curtail',
								'type' => 'number',
								'description' => 'Add a value to be used in builder forms.',
							],
							[
								'id' => 'mdf_dcb_curtail_price',
								'label' => 'MDF Dble Curtail + Bullnose',
								'type' => 'number',
								'description' => 'Add a value to be used in builder forms.',
							],
							[
								'id' => 'ply_dcb_curtail_price',
								'label' => 'Plywood Dble Curtail + Bullnose',
								'type' => 'number',
								'description' => 'Add a value to be used in builder forms.',
							],
							[
								'id' => 'pine_dcb_curtail_price',
								'label' => 'Pine Dble Curtail + Bullnose',
								'type' => 'number',
								'description' => 'Add a value to be used in builder forms.',
							],
							[
								'id' => 'oak_dcb_curtail_price',
								'label' => 'Oak Dble Curtail + Bullnose',
								'type' => 'number',
								'description' => 'Add a value to be used in builder forms.',
							],
						],
					],
					'handrails_and_baserails' => [
						'label' => 'Handrails & Baserails',
						'fields' => [
							[
								'id' => 'handrail_types',
								'label' => 'Handrail Types',
								'type' => 'repeater',
								'style' => 'card',
								'description' => 'Selectable handrail styles (per metre). Baserail is fixed below and is not a repeater row.',
								'subfields' => $variant_subfields,
							],
							[
								'id' => 'baserail_option',
								'label' => 'Baserail Options',
								'type' => 'toggle',
								'toggle_label' => 'Add a price direct or reference a product ID',
							],
							[
								'id' => 'pine_baserail_price',
								'label' => 'Pine Baserail Price',
								'type' => 'price',
								'show_when' => ['field' => 'baserail_option', 'equals' => false],
								'description' => '(per meter)',
							],
							[
								'id' => 'pine_baserail_id',
								'label' => 'Pine Baserail ID',
								'type' => 'product_id',
								'show_when' => ['field' => 'baserail_option', 'equals' => true],
								'description' => '(per meter)',
							],
							[
								'id' => 'oak_baserail_price',
								'label' => 'Oak Baserail Price',
								'type' => 'price',
								'show_when' => ['field' => 'baserail_option', 'equals' => false],
								'description' => '(per meter)',
							],
							[
								'id' => 'oak_baserail_id',
								'label' => 'Oak Baserail ID',
								'type' => 'product_id',
								'show_when' => ['field' => 'baserail_option', 'equals' => true],
								'description' => '(per meter)',
							],
						],
					],
					'spindles_balustrading' => [
						'label' => 'Spindles (Balustrading)',
						'fields' => [
							[
								'id' => 'spindle_types',
								'label' => 'Spindle Types',
								'type' => 'repeater',
								'style' => 'card',
								'description' => 'Each row is a selectable balustrading style. Name = customer-facing label; Code = stable machine key. Material Type switches between Wood (Pine/Oak), Metal (priced per spindle) and Glass (priced per metre or per panel).',
								'subfields' => $spindle_subfields,
							],
						],
					],
					'construction_settings' => [
						'label' => 'Construction Settings',
						'fields' => [
							[
								'id' => 'material_quick_set_enabled',
								'label' => 'Material Quick-Set Buttons',
								'type' => 'toggle',
								'toggle_label' => 'Show "Set all to Pine" / "Set all to Oak" buttons on the Material section',
								'default' => 0,
								'full_row' => true,
								'description' => 'When on, two quick buttons appear above the front-end Material options letting the customer set every component to Pine or Oak at once. Off by default.',
							],
							[
								'id' => 'going_regs_warning_enabled',
								'label' => 'Building Regs Warning',
								'type' => 'toggle',
								'toggle_label' => 'Highlight "Going" in red when outside the building-regs range',
								'default' => 1,
								'description' => 'When on, the front-end "Going" field turns red if its value is below the minimum or above the maximum set below (a soft warning — the value is still allowed). Turn off to disable the warning entirely.',
							],
							[
								'id' => 'going_regs_warning_min',
								'label' => 'Going Warning — Minimum (mm)',
								'type' => 'number',
								'placeholder' => '220',
								'description' => 'Going below this turns red. Defaults to 220mm if left empty. Only used when the warning is on.',
								'disable_when' => ['field' => 'going_regs_warning_enabled', 'equals' => false],
							],
							[
								'id' => 'going_regs_warning_max',
								'label' => 'Going Warning — Maximum (mm)',
								'type' => 'number',
								'placeholder' => '250',
								'description' => 'Going above this turns red. Defaults to 250mm if left empty. Only used when the warning is on.',
								'disable_when' => ['field' => 'going_regs_warning_enabled', 'equals' => false],
							],
							[
								'id' => 'going_max',
								'label' => 'Going — Hard Maximum (mm)',
								'type' => 'number',
								'placeholder' => 'No limit',
								'description' => 'The front-end form will not allow a Going larger than this — the value snaps back to the maximum and a message is shown. Leave empty for no limit.',
							],
							[
								'id' => 'going_max_message',
								'label' => 'Going Maximum — Message',
								'type' => 'text',
								'placeholder' => 'Maximum going is {max}mm.',
								'description' => 'Red message shown when someone exceeds the Going maximum. Use {max} for the maximum value. Leave empty for a default message.',
							],
							[
								'id' => 'width_max',
								'label' => 'Staircase Width — Hard Maximum (mm)',
								'type' => 'number',
								'placeholder' => 'No limit',
								'description' => 'Applies to all flight widths. The form will not allow a width larger than this — the value snaps back and a message is shown. Leave empty for no limit.',
							],
							[
								'id' => 'width_max_message',
								'label' => 'Width Maximum — Message',
								'type' => 'text',
								'placeholder' => 'Maximum width is {max}mm.',
								'description' => 'Red message shown when someone exceeds the width maximum. Use {max} for the maximum value. Leave empty for a default message.',
							],
						],
					],
					'base_costs' => [
						'label' => 'Base Costs',
						'fields' => [
							[
								'id' => 'setup_fee',
								'label' => 'Setup Fee',
								'type' => 'number',
								'description' => 'Fires if staircase is small and under 7 risers',
							],
							[
								'id' => 'width_mp',
								'label' => 'Extra Wide Multiplier',
								'type' => 'number',
								'description' => 'Fires if staircase is greater than 1000mm wide',
							],
							[
								'id' => 'cut_string_price',
								'label' => 'Cut String Price',
								'type' => 'price',
							],
						],
					],
					'quarter_landing' => [
						'label' => 'Quarter Landing',
						'fields' => [
							[
								'id' => 'quarter_landing_all_oak',
								'label' => 'Quarter Landing All Oak',
								'type' => 'number',
							],
							[
								'id' => 'quarter_landing_oak_string',
								'label' => 'Quarter Landing Oak String',
								'type' => 'number',
							],
							[
								'id' => 'quarter_landing_oak_tr',
								'label' => 'Quarter Landing Oak Tread & Riser',
								'type' => 'number',
							],
							[
								'id' => 'quarter_landing_oak_tread',
								'label' => 'Quarter Landing Oak Tread',
								'type' => 'number',
							],
							[
								'id' => 'quarter_landing_no_oak',
								'label' => 'Quarter Landing No Oak',
								'type' => 'number',
							],
						],
					],
					'half_landing' => [
						'label' => 'Half Landing',
						'fields' => [
							[
								'id' => 'half_landing_all_oak',
								'label' => 'Half Landing All Oak',
								'type' => 'number',
							],
							[
								'id' => 'half_landing_oak_string',
								'label' => 'Half Landing Oak String',
								'type' => 'number',
							],
							[
								'id' => 'half_landing_oak_tr',
								'label' => 'Half Landing Oak Tread & Riser',
								'type' => 'number',
							],
							[
								'id' => 'half_landing_oak_tread',
								'label' => 'Half Landing Oak Tread',
								'type' => 'number',
							],
							[
								'id' => 'half_landing_no_oak',
								'label' => 'Half Landing No Oak',
								'type' => 'number',
							],
						],
					],
					'postcode_areas' => [
						'label' => 'Postcode Areas',
						'fields' => [
							[
								'id' => 'greater_london_pcodes',
								'label' => 'Greater London',
								'type' => 'textarea',
							],
							[
								'id' => 'greater_london_delivery_price',
								'label' => 'Greater London Delivery Price',
								'type' => 'number',
							],
							[
								'id' => 'mainland_uk_pcodes',
								'label' => 'Mainland UK',
								'type' => 'textarea',
							],
							[
								'id' => 'mainland_uk_delivery_price',
								'label' => 'Mainland UK Delivery Price',
								'type' => 'number',
							],
						],
					],
					'delivery_options' => [
						'label' => 'Delivery Options',
						'layout' => 'paired_rows',
						'fields' => [
							['id' => 'delivery_options_enabled', 'label' => '', 'type' => 'toggle', 'toggle_label' => 'Enable Packaging &amp; Delivery section', 'default' => 1, 'full_row' => true, 'description' => 'When off, the front-end "Packaging &amp; Delivery" tab is hidden and its delivery, package and add-on charges are not applied. "Project Delivery Date" and "Postcode" move into the "Your Details" tab (Postcode becomes a plain text field, no delivery lookup). VAT is unaffected.'],
							['id' => 'two_man_delivery_enabled', 'label' => '', 'type' => 'toggle', 'toggle_label' => 'Two Man Delivery', 'default' => 1],
							['id' => 'two_man_delivery_price', 'label' => 'Two Man Delivery Price', 'type' => 'number', 'disable_when' => ['field' => 'two_man_delivery_enabled', 'equals' => false]],
							['id' => 'part_assembled_enabled', 'label' => '', 'type' => 'toggle', 'toggle_label' => 'Part Assembled', 'default' => 1],
							['id' => 'part_assembled_price', 'label' => 'Part Assembled Price', 'type' => 'number', 'disable_when' => ['field' => 'part_assembled_enabled', 'equals' => false]],
							['id' => 'fixing_kit_enabled', 'label' => '', 'type' => 'toggle', 'toggle_label' => 'Fixing Kit', 'default' => 1],
							['id' => 'fixing_kit_price', 'label' => 'Fixing Kit Price', 'type' => 'number', 'disable_when' => ['field' => 'fixing_kit_enabled', 'equals' => false]],
							['id' => 'extra_packaging_enabled', 'label' => '', 'type' => 'toggle', 'toggle_label' => 'Extra Packaging', 'default' => 1],
							['id' => 'extra_packaging_price', 'label' => 'Extra Packaging Price', 'type' => 'number', 'disable_when' => ['field' => 'extra_packaging_enabled', 'equals' => false]],
							[
								'id' => 'project_delivery_dates',
								'label' => 'Project Delivery Date',
								'type' => 'repeater',
								'sidebar' => true,
								'description' => 'Populates the front-end "Project Delivery Date" dropdown — captures enquiry urgency.',
								'subfields' => [
									['id' => 'project_delivery_date_name', 'label' => 'Name', 'type' => 'text'],
								],
							],
						],
					],
					'brand_colours' => [
						'label' => 'Brand Colours',
						'layout' => 'grid',
						'fields' => [
							// --- Popout box: Form panel (#stairbuild) ---
							[
								'id' => 'form_bg',
								'label' => 'Form Box — Background',
								'type' => 'color',
								'default' => '#ffffff',
								'group' => 'Popout Boxes & Tabs',
							],
							[
								'id' => 'form_text',
								'label' => 'Form Box — Text',
								'type' => 'color',
								'default' => '#222222',
							],
							[
								'id' => 'form_link',
								'label' => 'Form Box — Links',
								'type' => 'color',
								'default' => '#5a300d',
							],
							[
								'id' => 'tab_bg',
								'label' => 'Inactive Tab — Background',
								'type' => 'color',
								'default' => '#302f2f',
							],
							[
								'id' => 'tab_text',
								'label' => 'Inactive Tab — Text',
								'type' => 'color',
								'default' => '#ffffff',
							],
							[
								'id' => 'tab_hover_bg',
								'label' => 'Inactive Tab — Hover',
								'type' => 'color',
								'default' => '#463f3f',
							],
							[
								'id' => 'tab_active_bg',
								'label' => 'Active Tab — Background',
								'type' => 'color',
								'default' => '#1a252f',
							],
							[
								'id' => 'tab_active_text',
								'label' => 'Active Tab — Text',
								'type' => 'color',
								'default' => '#ffffff',
							],
							// --- Popout box: Measurements panel (.mm_breakout) ---
							[
								'id' => 'measurements_bg',
								'label' => 'Measurements Box — Background',
								'type' => 'color',
								'default' => '#5a300d',
							],
							[
								'id' => 'measurements_text',
								'label' => 'Measurements Box — Text',
								'type' => 'color',
								'default' => '#ffffff',
							],
							[
								'id' => 'measurements_link',
								'label' => 'Measurements Box — Links',
								'type' => 'color',
								'default' => '#ffffff',
							],
							// --- Popout box: Quote panel (.breakout) ---
							[
								'id' => 'quote_bg',
								'label' => 'Quote Box — Background',
								'type' => 'color',
								'default' => '#292003',
							],
							[
								'id' => 'quote_text',
								'label' => 'Quote Box — Text',
								'type' => 'color',
								'default' => '#ffffff',
							],
							[
								'id' => 'quote_link',
								'label' => 'Quote Box — Links',
								'type' => 'color',
								'default' => '#ffffff',
							],
							// --- 2D staircase diagram colours ---
							[
								'id' => 'canvas_bg',
								'label' => 'Canvas - Background',
								'type' => 'color',
								'group' => '2D Staircase Diagram',
								'description' => 'Leave blank for a transparent canvas (page background shows through).',
							],
							[
								'id' => 'treads_fill',
								'label' => 'Treads - Fill',
								'type' => 'color',
							],
							[
								'id' => 'treads_outline',
								'label' => 'Treads - Outline',
								'type' => 'color',
							],
							[
								'id' => 'treads_text',
								'label' => 'Treads - Text',
								'type' => 'color',
							],
							[
								'id' => 'posts_fill',
								'label' => 'Posts - Fill',
								'type' => 'color',
							],
							[
								'id' => 'posts_outline',
								'label' => 'Posts - Outline',
								'type' => 'color',
							],
							[
								'id' => 'posts_text',
								'label' => 'Posts - Text',
								'type' => 'color',
							],
							[
								'id' => 'stringer_fill',
								'label' => 'Stringer - Fill',
								'type' => 'color',
							],
							[
								'id' => 'stringer_outline',
								'label' => 'Stringer - Outline',
								'type' => 'color',
							],
							[
								'id' => 'spindles',
								'label' => 'Spindles',
								'type' => 'color',
							],
						],
					],
					// Documentation-only tab (no saved fields) — rendered by the
					// `help` layout branch in render_tab_body().
					'help' => [
						'label'  => 'Help / Shortcodes',
						'layout' => 'help',
						'fields' => [],
					],
				];
			}
	}

	new Stairbuilder_Pricing_Settings();
}

/* ---------------------------------------------------------------------- */
/* Drop-in BC wrapper                                                       */
/*                                                                          */
/* Use in place of `get_field( $name, 'option' )` elsewhere in the plugin:  */
/*                                                                          */
/*   $price = stairbuilder_get_option( 'pine_bullnose_price', 0 );          */
/*                                                                          */
/* ---------------------------------------------------------------------- */

if ( ! function_exists( 'stairbuilder_get_option' ) ) {
	/**
	 * Retrieve a single Stair Builder pricing option.
	 *
	 * @param string $key     Field id (matches the ACF field name).
	 * @param mixed  $default Default if not set.
	 * @return mixed
	 */
	function stairbuilder_get_option( $key, $default = null ) {
		return Stairbuilder_Pricing_Settings::get( $key, $default );
	}
}