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
 * @version 1.0.2
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
		const SCHEMA_VERSION = 1;

		/** @var array Parsed schema (tabs + fields). */
		private $schema;

		public function __construct() {
			$this->schema = $this->get_schema();
			add_action( 'admin_menu', array( $this, 'add_menu' ) );
			add_action( 'admin_init', array( $this, 'register' ) );
			add_action( 'admin_init', array( $this, 'maybe_migrate_from_acf' ), 20 );
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
			?>
			<input type="number" step="any"
				id="<?php echo esc_attr( $id ); ?>"
				name="<?php echo esc_attr( $name ); ?>"
				value="<?php echo esc_attr( $value ); ?>"
				class="small-text" />
			<?php
		}

		private function render_text( $id, $name, $value, $field ) {
			?>
			<input type="text"
				id="<?php echo esc_attr( $id ); ?>"
				name="<?php echo esc_attr( $name ); ?>"
				value="<?php echo esc_attr( $value ); ?>"
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
			$clean_rows = array();
			foreach ( $raw as $row ) {
				if ( ! is_array( $row ) ) {
					continue;
				}
				$clean_row = array();
				$has_value = false;
				foreach ( $subfields as $sf ) {
					$v = isset( $row[ $sf['id'] ] ) ? $row[ $sf['id'] ] : '';
					if ( $sf['type'] === 'number' ) {
						$clean_row[ $sf['id'] ] = ( $v === '' || $v === null ) ? '' : (float) $v;
					} else {
						$clean_row[ $sf['id'] ] = is_scalar( $v ) ? sanitize_text_field( (string) $v ) : '';
					}
					if ( $clean_row[ $sf['id'] ] !== '' && $clean_row[ $sf['id'] ] !== 0 && $clean_row[ $sf['id'] ] !== 0.0 ) {
						$has_value = true;
					}
				}
				if ( $has_value ) {
					$clean_rows[] = $clean_row;
				}
			}
			return array_values( $clean_rows );
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
		private function render_tab_body( $slug, $tab ) {
			// Bespoke layout: paired (toggle, price) rows rendered inline on one line.
			// Fields flagged `sidebar => true` render in a right-hand column.
			if ( isset( $tab['layout'] ) && $tab['layout'] === 'paired_rows' ) {
				$main_fields = array();
				$side_fields = array();
				foreach ( $tab['fields'] as $f ) {
					if ( ! empty( $f['sidebar'] ) ) {
						$side_fields[] = $f;
					} else {
						$main_fields[] = $f;
					}
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

			$blocks = $this->detect_component_blocks( $tab['fields'] );

			// Does this tab have any component-style blocks?
			$has_components = false;
			foreach ( $blocks as $b ) {
				if ( $b['type'] === 'component' ) {
					$has_components = true;
					break;
				}
			}

			if ( ! $has_components ) {
				// Standard form-table rendering for plain-field tabs.
				echo '<table class="form-table" role="presentation">';
				do_settings_fields( self::PAGE_ID . '-' . $slug, 'stairbuilder_section_' . $slug );
				echo '</table>';
				return;
			}

			// Mixed layout: components on top, any trailing plain fields below.
			echo '<div class="stairbuilder-components">';
			foreach ( $blocks as $block ) {
				if ( $block['type'] === 'component' ) {
					$this->render_component_row( $block );
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

				/* Stray single field inside a component-tab */
				.stairbuilder-pricing-wrap .stairbuilder-single-row { margin-top: 12px; background: #fff; }

				/* Narrow screens — stack the columns */
				@media (max-width: 960px) {
					.stairbuilder-pricing-wrap .stairbuilder-component {
						grid-template-columns: 1fr;
					}
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
				});'
			);
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
					'base_costs',
					'quarter_landing',
					'half_landing',
					'postcode_areas',
					'delivery_options',
					'2d_staircase_colours',
				),
			);
		}

		/* ------------------------------------------------------------------ */
		/* Schema                                                                */
		/* ------------------------------------------------------------------ */

			private function get_schema() {
				return [
					'strings' => [
						'label' => 'Strings',
						'fields' => [
							[
								'id' => 'stringer_types',
								'label' => 'Add Stringer Types',
								'type' => 'repeater',
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
								'subfields' => [['id' => 'building_reg_name', 'label' => 'Name', 'type' => 'text'], ['id' => 'building_reg_value', 'label' => 'Value', 'type' => 'text']],
							],
							[
								'id' => 'construction_types',
								'label' => 'Add Construction Types',
								'type' => 'repeater',
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
								'subfields' => [['id' => 'tread_name', 'label' => 'Tread Name', 'type' => 'text'], ['id' => 'tread_code', 'label' => 'Tread Code', 'type' => 'text'], ['id' => 'tread_value', 'label' => 'Tread Value', 'type' => 'number']],
							],
							[
								'id' => 'tread_profiles',
								'label' => 'Add Tread Profiles',
								'type' => 'repeater',
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
								'subfields' => [['id' => 'riser_name', 'label' => 'Riser Name', 'type' => 'text'], ['id' => 'riser_code', 'label' => 'Riser Code', 'type' => 'text'], ['id' => 'riser_value', 'label' => 'Riser Value', 'type' => 'number']],
							],
						],
					],
					'newel_posts' => [
						'label' => 'Newel Posts',
						'fields' => [
							[
								'id' => 'square_newel_option',
								'label' => 'Square Newel Post Options',
								'type' => 'toggle',
								'toggle_label' => 'Add a price direct or reference a product ID',
							],
							[
								'id' => 'pine_newel_post_price',
								'label' => 'Pine Square Newel Post Price',
								'type' => 'price',
								'show_when' => ['field' => 'square_newel_option', 'equals' => false],
								'description' => 'Add a value to be used in builder forms.',
							],
							[
								'id' => 'pine_square_newel_post_id',
								'label' => 'Pine Square Newel Post Product ID',
								'type' => 'product_id',
								'show_when' => ['field' => 'square_newel_option', 'equals' => true],
								'description' => 'Add a product variation id (This will override price)',
							],
							[
								'id' => 'oak_newel_post_price',
								'label' => 'Oak Square Newel Post Price',
								'type' => 'price',
								'show_when' => ['field' => 'square_newel_option', 'equals' => false],
								'description' => 'Add a value to be used in builder forms.',
							],
							[
								'id' => 'oak_newel_post_id',
								'label' => 'Oak Square Newel Post ID',
								'type' => 'product_id',
								'show_when' => ['field' => 'square_newel_option', 'equals' => true],
								'description' => 'Add a product variation id (This will override price)',
							],
							[
								'id' => 'chamfered_newel_option',
								'label' => 'Chamfered Newel Post Options',
								'type' => 'toggle',
								'toggle_label' => 'Add a price direct or reference a product ID',
							],
							[
								'id' => 'pine_chmf_newel_price',
								'label' => 'Pine Chamfered Newel Price',
								'type' => 'price',
								'show_when' => ['field' => 'chamfered_newel_option', 'equals' => false],
								'description' => 'Add a value to be used in builder forms.',
							],
							[
								'id' => 'pine_chmf_newel_post_id',
								'label' => 'Pine Chamfered Newel Post Product ID',
								'type' => 'product_id',
								'show_when' => ['field' => 'chamfered_newel_option', 'equals' => true],
								'description' => 'Add a product variation id (This will override price)',
							],
							[
								'id' => 'oak_chmf_newel_price',
								'label' => 'Oak Chamfered Newel Price',
								'type' => 'price',
								'show_when' => ['field' => 'chamfered_newel_option', 'equals' => false],
								'description' => 'Add a value to be used in builder forms.',
							],
							[
								'id' => 'oak_chmf_newel_post_id',
								'label' => 'Oak Chamfered Newel Post Product ID',
								'type' => 'product_id',
								'show_when' => ['field' => 'chamfered_newel_option', 'equals' => true],
								'description' => 'Add a product variation id (This will override price)',
							],
							[
								'id' => 'turned_newel_option',
								'label' => 'Turned Newel Post Options',
								'type' => 'toggle',
								'toggle_label' => 'Add a price direct or reference a product ID',
							],
							[
								'id' => 'pine_trn_newel_price',
								'label' => 'Pine Turned Newel Price',
								'type' => 'price',
								'show_when' => ['field' => 'turned_newel_option', 'equals' => false],
								'description' => 'Add a value to be used in builder forms.',
							],
							[
								'id' => 'pine_trn_newel_id',
								'label' => 'Pine Turned Newel ID',
								'type' => 'product_id',
								'show_when' => ['field' => 'turned_newel_option', 'equals' => true],
								'description' => 'Add a value to be used in builder forms.',
							],
							[
								'id' => 'oak_trn_newel_price',
								'label' => 'Oak Turned Newel Price',
								'type' => 'price',
								'show_when' => ['field' => 'turned_newel_option', 'equals' => false],
								'description' => 'Add a value to be used in builder forms.',
							],
							[
								'id' => 'oak_trn_newel_id',
								'label' => 'Oak Turned Newel ID',
								'type' => 'product_id',
								'show_when' => ['field' => 'turned_newel_option', 'equals' => true],
								'description' => 'Add a value to be used in builder forms.',
							],
							[
								'id' => 'newel_base_option',
								'label' => 'Newel Base Options',
								'type' => 'toggle',
								'toggle_label' => 'Add a price direct or reference a product ID',
							],
							[
								'id' => 'pine_newel_base_price',
								'label' => 'Pine Newel Base Price',
								'type' => 'price',
								'show_when' => ['field' => 'newel_base_option', 'equals' => false],
							],
							[
								'id' => 'pine_newel_base_id',
								'label' => 'Pine Newel Base ID',
								'type' => 'product_id',
								'show_when' => ['field' => 'newel_base_option', 'equals' => true],
							],
							[
								'id' => 'oak_newel_base_price',
								'label' => 'Oak Newel Base Price',
								'type' => 'price',
								'show_when' => ['field' => 'newel_base_option', 'equals' => false],
							],
							[
								'id' => 'oak_newel_base_id',
								'label' => 'Oak Newel Base ID',
								'type' => 'product_id',
								'show_when' => ['field' => 'newel_base_option', 'equals' => true],
							],
						],
					],
					'newel_caps' => [
						'label' => 'Newel Caps',
						'fields' => [
							[
								'id' => 'pyr_cap_option',
								'label' => 'Pyramid Cap Options',
								'type' => 'toggle',
								'toggle_label' => 'Add a price direct or reference a product ID',
							],
							[
								'id' => 'pine_pyramid_cap_price',
								'label' => 'Pine Pyramid Cap Price',
								'type' => 'price',
								'show_when' => ['field' => 'pyr_cap_option', 'equals' => false],
							],
							[
								'id' => 'pine_pyramid_cap_id',
								'label' => 'Pine Pyramid Cap ID',
								'type' => 'product_id',
								'show_when' => ['field' => 'pyr_cap_option', 'equals' => true],
							],
							[
								'id' => 'oak_pyramid_cap_price',
								'label' => 'Oak Pyramid Cap Price',
								'type' => 'price',
								'show_when' => ['field' => 'pyr_cap_option', 'equals' => false],
							],
							[
								'id' => 'oak_pyramid_cap_id',
								'label' => 'Oak Pyramid Cap ID',
								'type' => 'product_id',
								'show_when' => ['field' => 'pyr_cap_option', 'equals' => true],
							],
							[
								'id' => 'ball_cap_option',
								'label' => 'Ball Cap Options',
								'type' => 'toggle',
								'toggle_label' => 'Add a price direct or reference a product ID',
							],
							[
								'id' => 'pine_ball_cap_price',
								'label' => 'Pine Ball Cap Price',
								'type' => 'price',
								'show_when' => ['field' => 'ball_cap_option', 'equals' => false],
							],
							[
								'id' => 'pine_ball_cap_id',
								'label' => 'Pine Ball Cap ID',
								'type' => 'product_id',
								'show_when' => ['field' => 'ball_cap_option', 'equals' => true],
							],
							[
								'id' => 'oak_ball_cap_price',
								'label' => 'Oak Ball Cap Price',
								'type' => 'price',
								'show_when' => ['field' => 'ball_cap_option', 'equals' => false],
							],
							[
								'id' => 'oak_ball_cap_id',
								'label' => 'Oak Ball Cap ID',
								'type' => 'product_id',
								'show_when' => ['field' => 'ball_cap_option', 'equals' => true],
							],
							[
								'id' => 'flat_cap_option',
								'label' => 'Flat Cap Options',
								'type' => 'toggle',
								'toggle_label' => 'Add a price direct or reference a product ID',
							],
							[
								'id' => 'pine_flat_cap_price',
								'label' => 'Pine Flat Cap Price',
								'type' => 'price',
								'show_when' => ['field' => 'flat_cap_option', 'equals' => false],
							],
							[
								'id' => 'pine_flat_cap_id',
								'label' => 'Pine Flat Cap ID',
								'type' => 'product_id',
								'show_when' => ['field' => 'flat_cap_option', 'equals' => true],
							],
							[
								'id' => 'oak_flat_cap_price',
								'label' => 'Oak Flat Cap Price',
								'type' => 'price',
								'show_when' => ['field' => 'flat_cap_option', 'equals' => false],
							],
							[
								'id' => 'oak_flat_cap_id',
								'label' => 'Oak Flat Cap ID',
								'type' => 'product_id',
								'show_when' => ['field' => 'flat_cap_option', 'equals' => true],
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
								'id' => 'crwn_hand_option',
								'label' => 'Crown Handrail Options',
								'type' => 'toggle',
								'toggle_label' => 'Add a price direct or reference a product ID',
							],
							[
								'id' => 'pine_crwn_hand_price',
								'label' => 'Pine Crown Handrail Price',
								'type' => 'price',
								'show_when' => ['field' => 'crwn_hand_option', 'equals' => false],
								'description' => '(per meter)',
							],
							[
								'id' => 'pine_crwn_hand_id',
								'label' => 'Pine Crown Handrail ID',
								'type' => 'product_id',
								'show_when' => ['field' => 'crwn_hand_option', 'equals' => true],
								'description' => '(per meter)',
							],
							[
								'id' => 'oak_crwn_hand_price',
								'label' => 'Oak Crown Handrail Price',
								'type' => 'price',
								'show_when' => ['field' => 'crwn_hand_option', 'equals' => false],
								'description' => '(per meter)',
							],
							[
								'id' => 'oak_crwn_hand_id',
								'label' => 'Oak Crown Handrail ID',
								'type' => 'product_id',
								'show_when' => ['field' => 'crwn_hand_option', 'equals' => true],
								'description' => '(per meter)',
							],
							[
								'id' => 'hdr_hand_option',
								'label' => 'HDR Handrail Options',
								'type' => 'toggle',
								'toggle_label' => 'Add a price direct or reference a product ID',
							],
							[
								'id' => 'pine_hdr_hand_price',
								'label' => 'Pine HDR Handrail Price',
								'type' => 'price',
								'show_when' => ['field' => 'hdr_hand_option', 'equals' => false],
								'description' => '(per meter)',
							],
							[
								'id' => 'pine_hdr_hand_id',
								'label' => 'Pine HDR Handrail ID',
								'type' => 'product_id',
								'show_when' => ['field' => 'hdr_hand_option', 'equals' => true],
								'description' => '(per meter)',
							],
							[
								'id' => 'oak_hdr_hand_price',
								'label' => 'Oak HDR Handrail Price',
								'type' => 'price',
								'show_when' => ['field' => 'hdr_hand_option', 'equals' => false],
								'description' => '(per meter)',
							],
							[
								'id' => 'oak_hdr_hand_id',
								'label' => 'Oak HDR Handrail ID',
								'type' => 'product_id',
								'show_when' => ['field' => 'hdr_hand_option', 'equals' => true],
								'description' => '(per meter)',
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
								'id' => 'chmf_spin_option',
								'label' => 'Chamfered Spindle Options',
								'type' => 'toggle',
								'toggle_label' => 'Add a price direct or reference a product ID',
							],
							[
								'id' => 'pine_chmf_spindle_price',
								'label' => 'Pine Chamfered Spindle Price',
								'type' => 'price',
								'show_when' => ['field' => 'chmf_spin_option', 'equals' => false],
							],
							[
								'id' => 'pine_chmf_spindle_price_id',
								'label' => 'Pine Chamfered Spindle ID',
								'type' => 'product_id',
								'show_when' => ['field' => 'chmf_spin_option', 'equals' => true],
							],
							[
								'id' => 'oak_chmf_spindle_price',
								'label' => 'Oak Chamfered Spindle Price',
								'type' => 'price',
								'show_when' => ['field' => 'chmf_spin_option', 'equals' => false],
							],
							[
								'id' => 'oak_chmf_spindle_id',
								'label' => 'Oak Chamfered Spindle ID',
								'type' => 'product_id',
								'show_when' => ['field' => 'chmf_spin_option', 'equals' => true],
							],
							[
								'id' => 'edwa_spin_option',
								'label' => 'Edwardian Spindle Options',
								'type' => 'toggle',
								'toggle_label' => 'Add a price direct or reference a product ID',
							],
							[
								'id' => 'pine_edwa_spindle_price',
								'label' => 'Pine Edwardian Spindle Price',
								'type' => 'price',
								'show_when' => ['field' => 'edwa_spin_option', 'equals' => false],
							],
							[
								'id' => 'pine_edwa_spindle_id',
								'label' => 'Pine Edwardian Spindle ID',
								'type' => 'product_id',
								'show_when' => ['field' => 'edwa_spin_option', 'equals' => true],
							],
							[
								'id' => 'oak_edwa_spindle_price',
								'label' => 'Oak Edwardian Spindle Price',
								'type' => 'price',
								'show_when' => ['field' => 'edwa_spin_option', 'equals' => false],
							],
							[
								'id' => 'oak_edwa_spindle_id',
								'label' => 'Oak Edwardian Spindle ID',
								'type' => 'product_id',
								'show_when' => ['field' => 'edwa_spin_option', 'equals' => true],
							],
							[
								'id' => 'twist_spin_option',
								'label' => 'Twist Spindle Options',
								'type' => 'toggle',
								'toggle_label' => 'Add a price direct or reference a product ID',
							],
							[
								'id' => 'pine_twist_spindle_price',
								'label' => 'Pine Twist Spindle Price',
								'type' => 'price',
								'show_when' => ['field' => 'twist_spin_option', 'equals' => false],
							],
							[
								'id' => 'pine_twist_spindle_id',
								'label' => 'Pine Twist Spindle ID',
								'type' => 'product_id',
								'show_when' => ['field' => 'twist_spin_option', 'equals' => true],
							],
							[
								'id' => 'oak_twist_spindle_price',
								'label' => 'Oak Twist Spindle Price',
								'type' => 'price',
								'show_when' => ['field' => 'twist_spin_option', 'equals' => false],
							],
							[
								'id' => 'oak_twist_spindle_id',
								'label' => 'Oak Twist Spindle ID',
								'type' => 'product_id',
								'show_when' => ['field' => 'twist_spin_option', 'equals' => true],
							],
							[
								'id' => 'flute_spin_option',
								'label' => 'Flute Spindle Options',
								'type' => 'toggle',
								'toggle_label' => 'Add a price direct or reference a product ID',
							],
							[
								'id' => 'pine_flt_spindle_price',
								'label' => 'Pine Fluted Spindle Price',
								'type' => 'price',
								'show_when' => ['field' => 'flute_spin_option', 'equals' => false],
							],
							[
								'id' => 'pine_flt_spindle_id',
								'label' => 'Pine Fluted Spindle ID',
								'type' => 'product_id',
								'show_when' => ['field' => 'flute_spin_option', 'equals' => true],
							],
							[
								'id' => 'oak_flt_spindle_price',
								'label' => 'Oak Fluted Spindle Price',
								'type' => 'price',
								'show_when' => ['field' => 'flute_spin_option', 'equals' => false],
							],
							[
								'id' => 'oak_flt_spindle_id',
								'label' => 'Oak Fluted Spindle ID',
								'type' => 'product_id',
								'show_when' => ['field' => 'flute_spin_option', 'equals' => true],
							],
							[
								'id' => 'tulip_spin_option',
								'label' => 'Tulip Spindle Options',
								'type' => 'toggle',
								'toggle_label' => 'Add a price direct or reference a product ID',
							],
							[
								'id' => 'pine_tlp_spindle_price',
								'label' => 'Pine Tulip Spindle Price',
								'type' => 'price',
								'show_when' => ['field' => 'tulip_spin_option', 'equals' => false],
							],
							[
								'id' => 'pine_tlp_spindle_id',
								'label' => 'Pine Tulip Spindle ID',
								'type' => 'product_id',
								'show_when' => ['field' => 'tulip_spin_option', 'equals' => true],
							],
							[
								'id' => 'oak_tlp_spindle_price',
								'label' => 'Oak Tulip Spindle Price',
								'type' => 'price',
								'show_when' => ['field' => 'tulip_spin_option', 'equals' => false],
							],
							[
								'id' => 'oak_tlp_spindle_id',
								'label' => 'Oak Tulip Spindle ID',
								'type' => 'product_id',
								'show_when' => ['field' => 'tulip_spin_option', 'equals' => true],
							],
							[
								'id' => 'vtrn_spin_option',
								'label' => 'Victorian Spindle Options',
								'type' => 'toggle',
								'toggle_label' => 'Add a price direct or reference a product ID',
							],
							[
								'id' => 'pine_vtrn_spindle_price',
								'label' => 'Pine Victorian Spindle Price',
								'type' => 'price',
								'show_when' => ['field' => 'vtrn_spin_option', 'equals' => false],
							],
							[
								'id' => 'pine_vtrn_spindle_id',
								'label' => 'Pine Victorian Spindle ID',
								'type' => 'product_id',
								'show_when' => ['field' => 'vtrn_spin_option', 'equals' => true],
							],
							[
								'id' => 'oak_vtrn_spindle_price',
								'label' => 'Oak Victorian Spindle Price',
								'type' => 'price',
								'show_when' => ['field' => 'vtrn_spin_option', 'equals' => false],
							],
							[
								'id' => 'oak_vtrn_spindle_id',
								'label' => 'Oak Victorian Spindle ID',
								'type' => 'product_id',
								'show_when' => ['field' => 'vtrn_spin_option', 'equals' => true],
							],
							[
								'id' => 'prv_spin_option',
								'label' => 'Provincial Spindle Options',
								'type' => 'toggle',
								'toggle_label' => 'Add a price direct or reference a product ID',
							],
							[
								'id' => 'pine_prv_spindle_price',
								'label' => 'Pine Provincial Spindle Price',
								'type' => 'price',
								'show_when' => ['field' => 'prv_spin_option', 'equals' => false],
							],
							[
								'id' => 'pine_prv_spindle_id',
								'label' => 'Pine Provincial Spindle ID',
								'type' => 'product_id',
								'show_when' => ['field' => 'prv_spin_option', 'equals' => true],
							],
							[
								'id' => 'oak_prv_spindle_price',
								'label' => 'Oak Provincial Spindle Price',
								'type' => 'price',
								'show_when' => ['field' => 'prv_spin_option', 'equals' => false],
							],
							[
								'id' => 'oak_prv_spindle_id',
								'label' => 'Oak Provincial Spindle ID',
								'type' => 'product_id',
								'show_when' => ['field' => 'prv_spin_option', 'equals' => true],
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
								'type' => 'text',
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
					'2d_staircase_colours' => [
						'label' => '2D Staircase Colours',
						'fields' => [
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