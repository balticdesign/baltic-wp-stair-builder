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
 * @version 2.1.0
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

		/** Bulk price-update tool page slug. */
		const BULK_PAGE_SLUG = 'stairbuilder-bulk-price';

		/** admin-post action name for applying a bulk price change. */
		const BULK_ACTION = 'stairbuilder_bulk_price';

		/** Nonce action for the bulk price form. */
		const BULK_NONCE = 'stairbuilder_bulk_price_nonce';

		/** Settings API page ID (distinct from menu slug). */
		const PAGE_ID = 'stairbuilder-pricing-settings';

		/** Option flag set to 1 after a successful ACF migration. */
		const MIGRATION_FLAG = 'stairbuilder_migrated_from_acf';

		/** Schema version, written into the blob so future migrations can detect shape. */
		// v4 (2.16.0): availability tags — construction_types.strict_for + the four
		// material/profile repeaters' available_for. Additive; missing keys default
		// to empty, so no backfill migration is required.
		// v5 (2.16.0 Phase 1): building_regs numeric limit columns (additive; the
		// canonical rows are seeded when empty via maybe_seed_building_regs()).
		const SCHEMA_VERSION = 5;

		/** Option flag set to 1 after the v2 flat-key → repeater migration. */
		const REPEATER_MIGRATION_FLAG = 'stairbuilder_repeater_migrated_v2';

		/** Option flag set to 1 after the v2.4 spindle material-mode backfill. */
		const BALLUSTRADE_MODES_FLAG = 'stairbuilder_balustrading_modes_migrated_v24';

		/** Option flag set to 1 after the v2.16 building-regs canonical seed. */
		const BUILDING_REGS_SEED_FLAG = 'stairbuilder_building_regs_seeded_v216';

		/** @var array Parsed schema (tabs + fields). */
		private $schema;

		/** @var string Hook suffix for the Pricing page (set in add_menu). */
		private $hook_pricing = '';

		/** @var string Hook suffix for the Bulk Price Update page (set in add_menu). */
		private $hook_bulk = '';

		public function __construct() {
			$this->schema = $this->get_schema();
			add_action( 'admin_menu', array( $this, 'add_menu' ) );
			add_action( 'admin_init', array( $this, 'register' ) );
			add_action( 'admin_init', array( $this, 'maybe_migrate_from_acf' ), 20 );
			add_action( 'admin_init', array( $this, 'maybe_migrate_repeaters_v2' ), 30 );
			add_action( 'admin_init', array( $this, 'maybe_backfill_balustrade_modes' ), 40 );
			add_action( 'admin_init', array( $this, 'maybe_seed_building_regs' ), 50 );
			add_action( 'admin_enqueue_scripts', array( $this, 'enqueue' ) );
			add_action( 'admin_post_' . self::BULK_ACTION, array( $this, 'handle_bulk_apply' ) );
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
		/* Bulk price tool — schema walk                                       */
		/* ------------------------------------------------------------------ */

		/**
		 * Walk the schema and return every adjustable price currently stored,
		 * flat keys and repeater sub-rows alike. This is the data source for the
		 * bulk price-increase tool — it decides what the %-sweep may touch purely
		 * from the `'adjustable' => true` schema flag, never from a hardcoded key
		 * list and never by inferring "is a price" from field type.
		 *
		 * Each entry:
		 *   - id       Stable string key, unique per row (for form field names /
		 *              preview mapping). 'flat:{field}' or 'rep:{field}:{i}:{sub}'.
		 *   - path     Structured locator for writing the value back:
		 *              ['kind'=>'flat','field'=>id] or
		 *              ['kind'=>'repeater','field'=>id,'row'=>int,'sub'=>id].
		 *   - section  Tab label the price lives under.
		 *   - label    Human label (repeater rows include the row name/code).
		 *   - material  '' | pine | oak | mdf | ply | metal | glass — drives the
		 *              "All Oak / All Pine / …" quick-selects. '' = no material axis.
		 *   - value    Current stored value (float, or '' when blank/unset).
		 *
		 * @return array<int,array<string,mixed>>
		 */
		public function get_adjustable_prices() {
			$options = get_option( self::OPTION_KEY, array() );
			if ( ! is_array( $options ) ) {
				$options = array();
			}

			$out = array();
			$bulk_mats = $this->bulk_materials();

			foreach ( $this->schema as $tab_slug => $tab ) {
				$section = isset( $tab['label'] ) ? $tab['label'] : $tab_slug;

				foreach ( $tab['fields'] as $field ) {
					$fid = $field['id'];

					if ( 'repeater' === $field['type'] ) {
						$subfields = isset( $field['subfields'] ) ? $field['subfields'] : array();

						// Which sub-fields are adjustable? Skip the whole repeater if none.
						$adj_subs = array();
						foreach ( $subfields as $sf ) {
							if ( ! empty( $sf['adjustable'] ) ) {
								$adj_subs[] = $sf;
							}
						}
						if ( empty( $adj_subs ) ) {
							continue;
						}

						$rows = ( isset( $options[ $fid ] ) && is_array( $options[ $fid ] ) ) ? $options[ $fid ] : array();
						foreach ( $rows as $i => $row ) {
							if ( ! is_array( $row ) ) {
								continue;
							}
							$identity  = $this->repeater_row_identity( $field, $row );
							$row_label = $identity['name'] !== '' ? $identity['name']
								: ( $identity['code'] !== '' ? $identity['code'] : 'Row ' . ( (int) $i + 1 ) );

							// Rows whose material is chosen per-row (stringer/tread/riser,
							// marked by 'material_source' on the code select) carry a single
							// price; tag it with the row's material so the bulk quick-selects
							// can sweep e.g. "all Oak" across those rows too.
							$row_material = '';
							foreach ( $subfields as $sf ) {
								if ( ! empty( $sf['material_source'] ) ) {
									$mv = isset( $row[ $sf['id'] ] ) ? (string) $row[ $sf['id'] ] : '';
									if ( isset( $bulk_mats[ $mv ] ) ) {
										$row_material = $mv;
									}
									break;
								}
							}

							foreach ( $adj_subs as $sf ) {
								$sid          = $sf['id'];
								$sub_label    = isset( $sf['label'] ) ? $sf['label'] : $sid;
								$sid_material = $this->derive_price_material( $sid );
								$out[]        = array(
									'id'       => 'rep:' . $fid . ':' . $i . ':' . $sid,
									'path'     => array( 'kind' => 'repeater', 'field' => $fid, 'row' => (int) $i, 'sub' => $sid ),
									'section'  => $section,
									'label'    => $row_label . ' — ' . $sub_label,
									'material' => '' !== $sid_material ? $sid_material : $row_material,
									'value'    => isset( $row[ $sid ] ) ? $row[ $sid ] : '',
								);
							}
						}
					} else {
						if ( empty( $field['adjustable'] ) ) {
							continue;
						}
						$out[] = array(
							'id'       => 'flat:' . $fid,
							'path'     => array( 'kind' => 'flat', 'field' => $fid ),
							'section'  => $section,
							'label'    => isset( $field['label'] ) ? $field['label'] : $fid,
							'material' => $this->derive_price_material( $fid ),
							'value'    => isset( $options[ $fid ] ) ? $options[ $fid ] : '',
						);
					}
				}
			}

			return $out;
		}

		/**
		 * Derive a price's material tag from its (sub-)field id, for the bulk
		 * tool's material quick-selects. Order matters — most specific first.
		 * See STAIRBUILDER-BULK-PRICE-TOOL-2.6.0.md §4 for the contract.
		 *
		 * @param string $id Field or sub-field id.
		 * @return string '' | pine | oak | mdf | ply | metal | glass
		 */
		private function derive_price_material( $id ) {
			// Landing "no oak" figures carry no material axis (must precede the
			// generic "contains oak" fallback below, which they would match).
			if ( preg_match( '/_no_oak$/', $id ) ) {
				return '';
			}
			// Explicit price sub-field keys (newel/cap/handrail/spindle rows).
			switch ( $id ) {
				case 'pine_price':
					return 'pine';
				case 'oak_price':
					return 'oak';
				case 'metal_price':
					return 'metal';
				case 'glass_price':
					return 'glass';
			}
			// Flat featured-step / baserail prices are keyed by material prefix.
			if ( 0 === strpos( $id, 'mdf_' ) ) {
				return 'mdf';
			}
			if ( 0 === strpos( $id, 'ply_' ) ) {
				return 'ply';
			}
			if ( 0 === strpos( $id, 'pine_' ) ) {
				return 'pine';
			}
			if ( 0 === strpos( $id, 'oak_' ) ) {
				return 'oak';
			}
			// Landing oak figures (all_oak / oak_string / oak_tr / oak_tread).
			if ( false !== strpos( $id, 'oak' ) ) {
				return 'oak';
			}
			// Single-value rows (stringer/tread/riser/construction/profile) and
			// cut_string_price have no material dimension.
			return '';
		}

		/**
		 * Resolve a repeater row's display name + code, tolerating both the plain
		 * `name`/`code` sub-keys (newel/cap/handrail/spindle rows) and the prefixed
		 * `{thing}_name`/`{thing}_code` keys (stringer/tread/riser/construction).
		 *
		 * @param array $field Repeater field spec (needs 'subfields').
		 * @param array $row   Stored row.
		 * @return array{name:string,code:string}
		 */
		private function repeater_row_identity( $field, $row ) {
			$name_key = null;
			$code_key = null;
			$subfields = isset( $field['subfields'] ) ? $field['subfields'] : array();
			foreach ( $subfields as $sf ) {
				$sid = $sf['id'];
				if ( null === $name_key && ( 'name' === $sid || '_name' === substr( $sid, -5 ) ) ) {
					$name_key = $sid;
				}
				if ( null === $code_key && ( 'code' === $sid || '_code' === substr( $sid, -5 ) ) ) {
					$code_key = $sid;
				}
			}
			$name = ( $name_key && isset( $row[ $name_key ] ) && is_scalar( $row[ $name_key ] ) ) ? (string) $row[ $name_key ] : '';
			$code = ( $code_key && isset( $row[ $code_key ] ) && is_scalar( $row[ $code_key ] ) ) ? (string) $row[ $code_key ] : '';
			return array( 'name' => $name, 'code' => $code );
		}

		/* ------------------------------------------------------------------ */
		/* Bulk price tool — screen                                            */
		/* ------------------------------------------------------------------ */

		/**
		 * Material options for the stringer/tread/riser "code" selects. These rows
		 * carry a single price whose material is chosen here (unlike newel/handrail
		 * rows, which price pine + oak side by side). Kept as one array so a new
		 * material (e.g. plywood) drops in for every panel at once, and so the bulk
		 * price tool can tag these rows by material. Keys must match bulk_materials().
		 *
		 * @return array<string,string> value => label, in display order.
		 */
		private function material_code_choices() {
			return array(
				'mdf'  => __( 'MDF', 'stairbuilder' ),
				'pine' => __( 'Pine', 'stairbuilder' ),
				'oak'  => __( 'Oak', 'stairbuilder' ),
				'ply'  => __( 'Plywood', 'stairbuilder' ),
			);
		}

		/** Material tags offered as quick-select buttons, in display order. */
		private function bulk_materials() {
			return array(
				'oak'   => __( 'Oak', 'stairbuilder' ),
				'pine'  => __( 'Pine', 'stairbuilder' ),
				'mdf'   => __( 'MDF', 'stairbuilder' ),
				'ply'   => __( 'Ply', 'stairbuilder' ),
				'metal' => __( 'Metal', 'stairbuilder' ),
				'glass' => __( 'Glass', 'stairbuilder' ),
			);
		}

		/**
		 * Render the Bulk Price Update screen: every adjustable component/material
		 * price with an include checkbox + material tag, material quick-selects, a
		 * single % input, a client-side Preview, and an Apply & Save that posts to
		 * the admin-post handler. Preview is pure (JS only); only Apply writes.
		 */
		public function render_bulk_page() {
			if ( ! current_user_can( 'manage_options' ) ) {
				return;
			}

			// Success / no-op notices after an Apply redirect.
			if ( isset( $_GET['bulk-updated'] ) ) {
				$n   = max( 0, (int) $_GET['bulk-updated'] );
				$pct = isset( $_GET['bulk-pct'] ) ? sanitize_text_field( wp_unslash( $_GET['bulk-pct'] ) ) : '';
				if ( $n > 0 ) {
					printf(
						'<div class="notice notice-success is-dismissible"><p>%s</p></div>',
						esc_html( sprintf( _n( '%1$s price updated by %2$s%%.', '%1$s prices updated by %2$s%%.', $n, 'stairbuilder' ), number_format_i18n( $n ), $pct ) )
					);
				}
			}
			if ( isset( $_GET['bulk-noop'] ) ) {
				echo '<div class="notice notice-warning is-dismissible"><p>' . esc_html__( 'No changes applied — enter a non-zero percentage and select at least one price.', 'stairbuilder' ) . '</p></div>';
			}

			$rows = $this->get_adjustable_prices();

			// Group rows by section, preserving schema order.
			$grouped = array();
			foreach ( $rows as $r ) {
				$grouped[ $r['section'] ][] = $r;
			}

			$pricing_url = admin_url( 'admin.php?page=' . self::PAGE_SLUG );
			?>
			<div class="wrap stairbuilder-bulk-wrap">
				<h1><?php esc_html_e( 'Bulk Price Update', 'stairbuilder' ); ?></h1>
				<p class="description">
					<?php esc_html_e( 'Apply a single percentage change to the component & material prices you select. Use the material buttons to tick a whole column (e.g. every Oak price), set the percentage, Preview, then Apply. Values are rounded to 2 decimal places. Blank prices are left untouched.', 'stairbuilder' ); ?>
					<a href="<?php echo esc_url( $pricing_url ); ?>">&larr; <?php esc_html_e( 'Back to Stair Builder Pricing', 'stairbuilder' ); ?></a>
				</p>

				<?php if ( empty( $rows ) ) : ?>
					<div class="notice notice-info inline"><p><?php esc_html_e( 'No adjustable prices found. Add some pricing under Stair Builder Pricing first.', 'stairbuilder' ); ?></p></div>
				<?php else : ?>

				<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" id="sb-bulk-form">
					<input type="hidden" name="action" value="<?php echo esc_attr( self::BULK_ACTION ); ?>" />
					<?php wp_nonce_field( self::BULK_NONCE ); ?>

					<div class="sb-bulk-controls">
						<label class="sb-bulk-pct-label">
							<?php esc_html_e( 'Percentage change', 'stairbuilder' ); ?>
							<input type="number" step="0.01" name="pct" id="sb-bulk-pct" class="small-text" placeholder="e.g. 5" />
							<span>%</span>
						</label>
						<span class="sb-bulk-hint"><?php esc_html_e( 'Use a negative number to reduce (e.g. -2.5).', 'stairbuilder' ); ?></span>

						<div class="sb-bulk-quickselect">
							<span class="sb-bulk-qs-label"><?php esc_html_e( 'Toggle:', 'stairbuilder' ); ?></span>
							<?php foreach ( $this->bulk_materials() as $key => $label ) : ?>
								<button type="button" class="button sb-bulk-mat" data-mat="<?php echo esc_attr( $key ); ?>"><?php echo esc_html( $label ); ?></button>
							<?php endforeach; ?>
							<button type="button" class="button sb-bulk-all"><?php esc_html_e( 'Select all', 'stairbuilder' ); ?></button>
							<button type="button" class="button sb-bulk-none"><?php esc_html_e( 'Select none', 'stairbuilder' ); ?></button>
						</div>
					</div>

					<?php $this->render_bulk_table( $grouped ); ?>

					<p class="sb-bulk-actions">
						<button type="button" class="button button-secondary" id="sb-bulk-preview"><?php esc_html_e( 'Preview', 'stairbuilder' ); ?></button>
						<button type="submit" class="button button-primary" id="sb-bulk-apply"><?php esc_html_e( 'Apply &amp; Save', 'stairbuilder' ); ?></button>
						<span class="sb-bulk-summary" id="sb-bulk-summary"></span>
					</p>
				</form>

				<?php endif; ?>
			</div>
			<?php
		}

		/**
		 * Render the grouped price table. Each row carries data-* attributes the
		 * Preview JS reads, and a checkbox whose value is the row's stable id.
		 *
		 * @param array $grouped section label => list of price rows.
		 */
		private function render_bulk_table( $grouped ) {
			$mats = $this->bulk_materials();
			?>
			<table class="widefat sb-bulk-table" id="sb-bulk-table">
				<thead>
					<tr>
						<th class="check-column"><input type="checkbox" id="sb-bulk-checkall" checked /></th>
						<th><?php esc_html_e( 'Price', 'stairbuilder' ); ?></th>
						<th><?php esc_html_e( 'Material', 'stairbuilder' ); ?></th>
						<th class="sb-bulk-num"><?php esc_html_e( 'Current (£)', 'stairbuilder' ); ?></th>
						<th class="sb-bulk-num"><?php esc_html_e( 'New (£)', 'stairbuilder' ); ?></th>
					</tr>
				</thead>
				<tbody>
					<?php foreach ( $grouped as $section => $items ) : ?>
						<tr class="sb-bulk-section"><td colspan="5"><strong><?php echo esc_html( $section ); ?></strong></td></tr>
						<?php foreach ( $items as $r ) :
							$is_num   = ( $r['value'] !== '' && is_numeric( $r['value'] ) );
							$mat_lbl  = ( $r['material'] !== '' && isset( $mats[ $r['material'] ] ) ) ? $mats[ $r['material'] ] : '';
							$cur_disp = $is_num ? number_format( (float) $r['value'], 2 ) : '—';
							?>
							<tr class="sb-bulk-row" data-id="<?php echo esc_attr( $r['id'] ); ?>"
								data-material="<?php echo esc_attr( $r['material'] ); ?>"
								data-value="<?php echo $is_num ? esc_attr( (float) $r['value'] ) : ''; ?>">
								<td class="check-column">
									<input type="checkbox" class="sb-bulk-include" name="rows[]" value="<?php echo esc_attr( $r['id'] ); ?>" <?php checked( $is_num ); ?> <?php disabled( ! $is_num ); ?> />
								</td>
								<td><?php echo esc_html( $r['label'] ); ?></td>
								<td><?php echo $mat_lbl ? '<span class="sb-bulk-tag sb-bulk-tag-' . esc_attr( $r['material'] ) . '">' . esc_html( $mat_lbl ) . '</span>' : '<span class="sb-bulk-tag sb-bulk-tag-none">&mdash;</span>'; ?></td>
								<td class="sb-bulk-num sb-bulk-current"><?php echo esc_html( $cur_disp ); ?></td>
								<td class="sb-bulk-num sb-bulk-new">&mdash;</td>
							</tr>
						<?php endforeach; ?>
					<?php endforeach; ?>
				</tbody>
			</table>
			<?php
		}

		/**
		 * admin-post handler: recompute the selected prices server-side (never
		 * trusting client maths), write them into the options blob in place, and
		 * redirect back with a result notice.
		 *
		 * In-place write (rather than a full round-trip through sanitize()) is
		 * deliberate: the stored blob is already in canonical shape, and re-running
		 * the whole array through sanitize() would coerce any not-yet-saved toggle
		 * defaults to 0. We only touch the selected price slots, casting each new
		 * value to a 2dp float — same coercion sanitize() applies to a price.
		 */
		public function handle_bulk_apply() {
			if ( ! current_user_can( 'manage_options' ) ) {
				wp_die( esc_html__( 'You do not have permission to do this.', 'stairbuilder' ) );
			}
			check_admin_referer( self::BULK_NONCE );

			$redirect = admin_url( 'admin.php?page=' . self::BULK_PAGE_SLUG );

			$pct_raw  = isset( $_POST['pct'] ) ? sanitize_text_field( wp_unslash( $_POST['pct'] ) ) : '';
			$pct      = is_numeric( $pct_raw ) ? (float) $pct_raw : 0.0;
			$selected = isset( $_POST['rows'] ) && is_array( $_POST['rows'] )
				? array_map( 'sanitize_text_field', wp_unslash( $_POST['rows'] ) )
				: array();

			if ( 0.0 === $pct || empty( $selected ) ) {
				wp_safe_redirect( add_query_arg( 'bulk-noop', '1', $redirect ) );
				exit;
			}

			// Authoritative current values + paths, keyed by stable id.
			$catalogue = array();
			foreach ( $this->get_adjustable_prices() as $r ) {
				$catalogue[ $r['id'] ] = $r;
			}

			$options = get_option( self::OPTION_KEY, array() );
			if ( ! is_array( $options ) ) {
				$options = array();
			}

			$factor  = 1 + ( $pct / 100 );
			$changed = 0;

			foreach ( $selected as $id ) {
				if ( ! isset( $catalogue[ $id ] ) ) {
					continue; // Unknown / stale id — ignore.
				}
				$entry = $catalogue[ $id ];
				if ( $entry['value'] === '' || ! is_numeric( $entry['value'] ) ) {
					continue; // Blank prices stay blank.
				}
				$new = round( (float) $entry['value'] * $factor, 2 );
				if ( $new === (float) $entry['value'] ) {
					continue; // No effective change (e.g. value 0).
				}

				$path = $entry['path'];
				if ( 'flat' === $path['kind'] ) {
					$options[ $path['field'] ] = $new;
					$changed++;
				} elseif ( 'repeater' === $path['kind'] ) {
					if ( isset( $options[ $path['field'] ][ $path['row'] ] ) && is_array( $options[ $path['field'] ][ $path['row'] ] ) ) {
						$options[ $path['field'] ][ $path['row'] ][ $path['sub'] ] = $new;
						$changed++;
					}
				}
			}

			if ( $changed > 0 ) {
				update_option( self::OPTION_KEY, $options );
				$redirect = add_query_arg(
					array( 'bulk-updated' => $changed, 'bulk-pct' => rawurlencode( $pct_raw ) ),
					$redirect
				);
			} else {
				$redirect = add_query_arg( 'bulk-noop', '1', $redirect );
			}

			wp_safe_redirect( $redirect );
			exit;
		}

		/* ------------------------------------------------------------------ */
		/* Admin menu                                                          */
		/* ------------------------------------------------------------------ */

		public function add_menu() {
			// Top-level "Stairbuilder" menu. The parent slug reuses PAGE_SLUG so the
			// pricing screen is the menu's landing page and its first submenu (below,
			// sharing the slug) simply relabels the auto-generated child to "Pricing"
			// rather than adding a duplicate entry.
			$this->hook_pricing = add_menu_page(
				__( 'Stair Builder Pricing', 'stairbuilder' ),
				__( 'Stairbuilder', 'stairbuilder' ),
				'manage_options',
				self::PAGE_SLUG,
				array( $this, 'render_page' ),
				'dashicons-building',
				58
			);
			add_submenu_page(
				self::PAGE_SLUG,
				__( 'Stair Builder Pricing', 'stairbuilder' ),
				__( 'Pricing', 'stairbuilder' ),
				'manage_options',
				self::PAGE_SLUG,
				array( $this, 'render_page' )
			);
			$this->hook_bulk = add_submenu_page(
				self::PAGE_SLUG,
				__( 'Bulk Price Update', 'stairbuilder' ),
				__( 'Bulk Price Update', 'stairbuilder' ),
				'manage_options',
				self::BULK_PAGE_SLUG,
				array( $this, 'render_bulk_page' )
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
				case 'image':
					$this->render_image( $id, $name, $value, $field );
					break;
				case 'select':
					$this->render_select( $id, $name, $value, $field );
					break;
				case 'multiselect':
					$this->render_multiselect( $id, $name, $value, $field );
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

		/**
		 * Media-library image picker. Stores the attachment ID (so the PDF can
		 * resolve a local file path for mPDF, which is more reliable than a URL).
		 */
		private function render_image( $id, $name, $value, $field ) {
			$att_id  = absint( $value );
			$img_url = $att_id ? wp_get_attachment_image_url( $att_id, 'medium' ) : '';
			?>
			<div class="stairbuilder-image-field">
				<input type="hidden" id="<?php echo esc_attr( $id ); ?>" name="<?php echo esc_attr( $name ); ?>" value="<?php echo esc_attr( $att_id ? $att_id : '' ); ?>" />
				<div class="stairbuilder-image-preview">
					<?php if ( $img_url ) : ?><img src="<?php echo esc_url( $img_url ); ?>" alt="" /><?php endif; ?>
				</div>
				<button type="button" class="button stairbuilder-image-select"><?php esc_html_e( 'Select image', 'stairbuilder' ); ?></button>
				<button type="button" class="button-link stairbuilder-image-remove"<?php echo $att_id ? '' : ' style="display:none"'; ?>><?php esc_html_e( 'Remove', 'stairbuilder' ); ?></button>
			</div>
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

		/**
		 * Resolve a field/sub-field's choices, supporting a dynamic source so a
		 * multi-select's options can be built from live data rather than a
		 * hardcoded list. `choices_source` wins over a static `choices` map.
		 *
		 *   'construction_codes' → [ construction_code => construction_name ]
		 *
		 * Returns an associative value => label array (may be empty).
		 */
		private function resolve_field_choices( $field ) {
			if ( ! empty( $field['choices_source'] ) ) {
				switch ( $field['choices_source'] ) {
					case 'construction_codes':
						$out  = array();
						$rows = function_exists( 'stairbuilder_get_option' ) ? stairbuilder_get_option( 'construction_types', array() ) : array();
						if ( is_array( $rows ) ) {
							foreach ( $rows as $r ) {
								if ( ! is_array( $r ) ) {
									continue;
								}
								$code = isset( $r['construction_code'] ) ? (string) $r['construction_code'] : '';
								if ( $code === '' ) {
									continue;
								}
								$label       = ( isset( $r['construction_name'] ) && $r['construction_name'] !== '' ) ? (string) $r['construction_name'] : $code;
								$out[ $code ] = $label;
							}
						}
						return $out;
				}
			}
			return isset( $field['choices'] ) ? (array) $field['choices'] : array();
		}

		/**
		 * Multi-select rendered as a checkbox group. Reusable field type
		 * (v2.16.0) — value is an array of selected choice keys. Choices may be
		 * static (`choices`) or dynamic (`choices_source`, see
		 * resolve_field_choices()). An all-unchecked group simply submits no
		 * key; the sanitiser reads that as an empty selection.
		 */
		private function render_multiselect( $id, $name, $value, $field ) {
			$this->render_multiselect_group( $name, $value, $field );
		}

		/**
		 * Shared checkbox-group markup used by both the top-level field and the
		 * repeater sub-field renderers. $name is the fully-qualified field name;
		 * option checkboxes append `[]`.
		 */
		private function render_multiselect_group( $name, $value, $field ) {
			$choices  = $this->resolve_field_choices( $field );
			$selected = is_array( $value ) ? array_map( 'strval', $value ) : array();
			echo '<div class="stairbuilder-multiselect">';
			if ( empty( $choices ) ) {
				echo '<span class="description">' . esc_html__( 'No options available yet.', 'stairbuilder' ) . '</span>';
			}
			foreach ( $choices as $cv => $cl ) {
				$cv = (string) $cv;
				?>
				<label class="stairbuilder-multiselect-option">
					<input type="checkbox" name="<?php echo esc_attr( $name ); ?>[]" value="<?php echo esc_attr( $cv ); ?>" <?php checked( in_array( $cv, $selected, true ) ); ?> />
					<?php echo esc_html( $cl ); ?>
				</label>
				<?php
			}
			echo '</div>';
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
			// Repeaters that lock their code after creation name the code sub-field
			// here (e.g. construction_types → construction_code). Threaded to the row
			// renderer so a persisted row's code renders read-only. See §Phase 0.
			$lock_code = isset( $field['lock_code'] ) ? (string) $field['lock_code'] : '';
			?>
			<div class="stairbuilder-card-repeater" data-field-id="<?php echo esc_attr( $id ); ?>" data-next-index="<?php echo count( $rows ); ?>">
				<div class="stairbuilder-cards">
					<?php foreach ( $rows as $i => $row ) {
						$this->$row_cb( $name, (string) $i, $subfields, is_array( $row ) ? $row : array(), $lock_code );
					} ?>
				</div>
				<script type="text/html" class="stairbuilder-card-proto"><?php
					$this->$row_cb( $name, '__i__', $subfields, array(), $lock_code );
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
		private function render_generic_card_row( $name, $i, $subfields, $row, $lock_code = '' ) {
			?>
			<div class="stairbuilder-component stairbuilder-card-row stairbuilder-card-row-generic">
				<?php foreach ( $subfields as $sf ) :
					$fname   = $name . '[' . $i . '][' . $sf['id'] . ']';
					$rv      = isset( $row[ $sf['id'] ] ) ? $row[ $sf['id'] ] : '';
					$sf_type = isset( $sf['type'] ) ? $sf['type'] : 'text';
					// Code lock: a persisted row's code is a stable machine key that
					// tags + historical leads depend on, so it renders read-only once
					// set. New rows (blank code, or the __i__ prototype) stay editable.
					$is_locked_code = ( $lock_code !== '' && $sf['id'] === $lock_code && '__i__' !== (string) $i && $rv !== '' );
					?>
					<div class="stairbuilder-card-field">
						<label class="stairbuilder-card-label"><?php echo esc_html( $sf['label'] ); ?>
							<?php if ( 'select' === $sf_type ) :
								$choices = isset( $sf['choices'] ) ? $sf['choices'] : array();
								// Keep any legacy stored value that predates the fixed list so
								// a save never silently rewrites it (see §2 conversion note).
								if ( $rv !== '' && ! isset( $choices[ $rv ] ) ) {
									$choices = array( (string) $rv => (string) $rv ) + $choices;
								}
								$sel = ( $rv !== '' ) ? (string) $rv : ( isset( $sf['default'] ) ? (string) $sf['default'] : '' );
								?>
								<select name="<?php echo esc_attr( $fname ); ?>" class="widefat">
									<?php foreach ( $choices as $cv => $cl ) : ?>
										<option value="<?php echo esc_attr( $cv ); ?>" <?php selected( $sel, (string) $cv ); ?>><?php echo esc_html( $cl ); ?></option>
									<?php endforeach; ?>
								</select>
							<?php elseif ( 'multiselect' === $sf_type ) :
								$this->render_multiselect_group( $fname, is_array( $rv ) ? $rv : array(), $sf );
							elseif ( 'textarea' === $sf_type ) : ?>
								<textarea name="<?php echo esc_attr( $fname ); ?>" rows="2" class="widefat"><?php echo esc_textarea( $rv ); ?></textarea>
							<?php elseif ( $is_locked_code ) : ?>
								<input type="text" name="<?php echo esc_attr( $fname ); ?>"
									value="<?php echo esc_attr( $rv ); ?>"
									class="widefat" readonly
									title="<?php esc_attr_e( 'Locked after creation — rename the label instead. Delete and re-create to change the code.', 'stairbuilder' ); ?>" />
							<?php else : ?>
								<input type="<?php echo 'number' === $sf_type ? 'number' : 'text'; ?>" <?php echo 'number' === $sf_type ? 'step="any"' : ''; ?>
									name="<?php echo esc_attr( $fname ); ?>"
									value="<?php echo esc_attr( $rv ); ?>"
									class="widefat" />
							<?php endif; ?>
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
		private function render_card_row( $name, $i, $subfields, $row, $lock_code = '' ) {
			// $lock_code is unused here — variant repeaters (newel/cap/handrail/
			// spindle) don't lock codes; the param exists so both row renderers
			// share the render_rich_repeater() call signature.
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
			// ("Pine £" / "Price £") so it aligns with Name on the single-line rows.
			// All panels now pass $flat = true; the non-flat branch below is the
			// original stacked layout, kept as a fallback but no longer called.
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
						<?php // A newel takes exactly one cap, so this is hard-wired to 1 and
						// hidden from the UI. Kept as a submitted field so the front-end cap
						// select preserves its `{code}:{caps_per_newel}` encoding (see
						// stairbuilder-prices.php). ?>
						<input type="hidden" name="<?php echo esc_attr( $fname( 'caps_per_newel' ) ); ?>" value="1" />
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
					<?php $variant_col( __( 'Pine', 'stairbuilder' ), 'pine_price', 'pine_id', true ); ?>
					<?php $variant_col( __( 'Oak', 'stairbuilder' ), 'oak_price', 'oak_id', true ); ?>
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
					case 'image':
						$clean[ $id ] = absint( $raw );
						break;
					case 'select':
						$choices = isset( $field['choices'] ) ? array_keys( $field['choices'] ) : array();
						$clean[ $id ] = ( is_scalar( $raw ) && in_array( (string) $raw, array_map( 'strval', $choices ), true ) ) ? (string) $raw : '';
						break;
					case 'multiselect':
						$clean[ $id ] = $this->sanitize_multiselect( $raw, $field );
						break;
					case 'repeater':
						$clean[ $id ] = $this->sanitize_repeater( $raw, $field );
						break;
					default:
						$clean[ $id ] = is_scalar( $raw ) ? sanitize_text_field( (string) $raw ) : '';
				}
			}

			// Orphan-drop post-pass (v2.16.0). Runs on the final $clean so it is
			// order-independent: after every repeater is sanitised, drop any
			// multi-select tag sourced from construction codes (choices_source:
			// construction_codes) that references a code no longer present in this
			// save. A deleted construction type therefore can't leave stale tags
			// behind. (Dormant until the tag sub-fields land in Phase 2; the empty
			// strict-repeater warning that pairs with it is §6.1.)
			$valid_codes = array();
			if ( isset( $clean['construction_types'] ) && is_array( $clean['construction_types'] ) ) {
				foreach ( $clean['construction_types'] as $r ) {
					if ( is_array( $r ) && isset( $r['construction_code'] ) && '' !== $r['construction_code'] ) {
						$valid_codes[] = (string) $r['construction_code'];
					}
				}
			}
			foreach ( $by_id as $fid => $field ) {
				if ( ! isset( $field['type'] ) || 'repeater' !== $field['type'] || empty( $clean[ $fid ] ) || ! is_array( $clean[ $fid ] ) ) {
					continue;
				}
				$code_subs = array();
				foreach ( ( isset( $field['subfields'] ) ? $field['subfields'] : array() ) as $sf ) {
					if ( isset( $sf['type'] ) && 'multiselect' === $sf['type'] && isset( $sf['choices_source'] ) && 'construction_codes' === $sf['choices_source'] ) {
						$code_subs[] = $sf['id'];
					}
				}
				if ( ! $code_subs ) {
					continue;
				}
				foreach ( $clean[ $fid ] as &$row ) {
					if ( ! is_array( $row ) ) {
						continue;
					}
					foreach ( $code_subs as $sub ) {
						if ( isset( $row[ $sub ] ) && is_array( $row[ $sub ] ) ) {
							$row[ $sub ] = array_values( array_intersect( array_map( 'strval', $row[ $sub ] ), $valid_codes ) );
						}
					}
				}
				unset( $row );
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
						case 'multiselect':
							$clean_row[ $sf['id'] ] = $this->sanitize_multiselect( $v, $sf );
							break;
						case 'textarea':
							$clean_row[ $sf['id'] ] = is_scalar( $v ) ? sanitize_textarea_field( (string) $v ) : '';
							break;
						default:
							$clean_row[ $sf['id'] ] = is_scalar( $v ) ? sanitize_text_field( (string) $v ) : '';
					}
					// Selects (always a default) and multi-selects (a tag list, not
					// content) must not count toward "row has content" — otherwise a
					// blank row would never drop.
					if ( $sf['type'] !== 'select' && $sf['type'] !== 'multiselect' ) {
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

			// Code lock (v2.16.0). Repeaters naming a `lock_code` sub-field (e.g.
			// construction_types → construction_code) get slugify-on-create for NEW
			// codes and NEVER-retro-slug for existing ones: a code already in the
			// stored set is preserved byte-for-byte so historical leads resolved via
			// bd_code_label() are never orphaned. Without stable row ids this
			// "is it already stored?" test is the only safe rename/create signal.
			if ( ! empty( $field['lock_code'] ) && isset( $field['id'] ) ) {
				$code_key = (string) $field['lock_code'];
				$name_key = '';
				foreach ( $subfields as $sf2 ) {
					if ( 'name' === $sf2['id'] || '_name' === substr( $sf2['id'], -5 ) ) {
						$name_key = $sf2['id'];
						break;
					}
				}
				$existing = array();
				$stored   = function_exists( 'stairbuilder_get_option' ) ? stairbuilder_get_option( $field['id'], array() ) : array();
				if ( is_array( $stored ) ) {
					foreach ( $stored as $sr ) {
						if ( is_array( $sr ) && isset( $sr[ $code_key ] ) && '' !== $sr[ $code_key ] ) {
							$existing[] = (string) $sr[ $code_key ];
						}
					}
				}
				$clean_rows = $this->slugify_new_codes( $clean_rows, $code_key, $name_key, $existing );
			}

			return array_values( $clean_rows );
		}

		/**
		 * Sanitise a multi-select value (v2.16.0) to an array of valid,
		 * de-duplicated choice keys. Unknown values are dropped — this is also
		 * the per-field orphan-drop for tags whose choices come from live data
		 * (a construction code that no longer exists can't survive the save).
		 */
		private function sanitize_multiselect( $raw, $field ) {
			if ( ! is_array( $raw ) ) {
				return array();
			}
			$out = array();
			foreach ( $raw as $v ) {
				if ( ! is_scalar( $v ) ) {
					continue;
				}
				$v = sanitize_text_field( (string) $v );
				if ( '' !== $v && ! in_array( $v, $out, true ) ) {
					$out[] = $v;
				}
			}
			// Static choices are validated here. Dynamic tags (choices_source) are
			// validated in the sanitize() post-pass against the FINAL construction
			// set instead — otherwise a tag referencing a construction type created
			// in the same save would be dropped before that type is committed.
			if ( empty( $field['choices_source'] ) ) {
				$valid = array_map( 'strval', array_keys( $this->resolve_field_choices( $field ) ) );
				$out   = array_values( array_intersect( $out, $valid ) );
			}
			return $out;
		}

		/**
		 * Slugify NEW row codes only; preserve existing ones byte-for-byte.
		 * A code present in $existing_codes (the stored set) is treated as an
		 * established machine key and never re-slugified — retro-slugging would
		 * orphan historical leads through bd_code_label(). New/blank codes are
		 * slugified (derived from the name when blank) and de-duplicated.
		 */
		private function slugify_new_codes( $rows, $code_key, $name_key, $existing_codes ) {
			$existing = array_map( 'strval', $existing_codes );
			$seen     = array();
			foreach ( $rows as &$row ) {
				if ( ! is_array( $row ) ) {
					continue;
				}
				$code = isset( $row[ $code_key ] ) ? (string) $row[ $code_key ] : '';
				if ( '' !== $code && in_array( $code, $existing, true ) ) {
					$seen[] = $code;
					continue;
				}
				$slug = sanitize_title( $code );
				if ( '' === $slug && '' !== $name_key ) {
					$slug = sanitize_title( isset( $row[ $name_key ] ) ? (string) $row[ $name_key ] : '' );
				}
				if ( '' === $slug ) {
					$slug = 'type';
				}
				$base = $slug;
				$n    = 2;
				while ( in_array( $slug, $seen, true ) || in_array( $slug, $existing, true ) ) {
					$slug = $base . '-' . $n;
					$n++;
				}
				$row[ $code_key ] = $slug;
				$seen[]           = $slug;
			}
			unset( $row );
			return $rows;
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
				<h1 class="wp-heading-inline"><?php esc_html_e( 'Stair Builder Pricing', 'stairbuilder' ); ?></h1>
				<a href="<?php echo esc_url( admin_url( 'admin.php?page=' . self::BULK_PAGE_SLUG ) ); ?>" class="page-title-action"><?php esc_html_e( 'Bulk Price Update', 'stairbuilder' ); ?></a>
				<hr class="wp-header-end" />

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

		/** Inline CSS + JS for the Bulk Price Update screen. */
		private function enqueue_bulk() {
			wp_enqueue_script( 'jquery' );

			wp_add_inline_style(
				'common',
				'.stairbuilder-bulk-wrap .sb-bulk-controls { background:#fff; border:1px solid #c3c4c7; border-radius:4px; padding:14px 16px; margin:16px 0; display:flex; flex-wrap:wrap; align-items:center; gap:10px 18px; }
				.stairbuilder-bulk-wrap .sb-bulk-pct-label { font-weight:600; }
				.stairbuilder-bulk-wrap .sb-bulk-pct-label input { margin:0 4px; }
				.stairbuilder-bulk-wrap .sb-bulk-hint { color:#646970; font-size:12px; }
				.stairbuilder-bulk-wrap .sb-bulk-quickselect { display:flex; flex-wrap:wrap; align-items:center; gap:6px; margin-left:auto; }
				.stairbuilder-bulk-wrap .sb-bulk-qs-label { font-weight:600; margin-right:2px; }
				/* Active material quick-select: keep the blue through hover/focus so it
				   repaints instantly on click. WP core .button:focus otherwise repaints
				   the default grey over .is-active until the button loses focus. */
				.stairbuilder-bulk-wrap .sb-bulk-mat.is-active,
				.stairbuilder-bulk-wrap .sb-bulk-mat.is-active:hover,
				.stairbuilder-bulk-wrap .sb-bulk-mat.is-active:focus { background:#2271b1; border-color:#2271b1; color:#fff; box-shadow:none; }
				/* Two-column price grid. Rendered as a grid over the table elements so
				   the Preview JS keeps its tr[data-id] / .sb-bulk-new selectors. thead
				   is dropped (a single header row cannot label two columns); a "->"
				   glyph before the New value carries the current->new meaning instead. */
				.stairbuilder-bulk-wrap .sb-bulk-table { margin-top:8px; border:0; background:transparent; }
				.stairbuilder-bulk-wrap .sb-bulk-table thead { display:none; }
				.stairbuilder-bulk-wrap .sb-bulk-table,
				.stairbuilder-bulk-wrap .sb-bulk-table tbody { display:block; }
				.stairbuilder-bulk-wrap .sb-bulk-table tbody { display:grid; grid-template-columns:1fr 1fr; gap:6px 18px; }
				.stairbuilder-bulk-wrap .sb-bulk-section { grid-column:1 / -1; }
				.stairbuilder-bulk-wrap .sb-bulk-section td { display:block; background:#f0f0f1; padding:6px 10px; margin-top:6px; border-radius:3px; }
				.stairbuilder-bulk-wrap .sb-bulk-row { display:grid; grid-template-columns:auto auto minmax(0,1fr) auto auto; align-items:center; column-gap:10px; padding:5px 10px; border:1px solid #e0e0e0; border-radius:4px; background:#fff; }
				.stairbuilder-bulk-wrap .sb-bulk-row td { display:block; border:0; padding:0; }
				.stairbuilder-bulk-wrap .sb-bulk-row .check-column { width:auto; padding-left:10px; }
				.stairbuilder-bulk-wrap .sb-bulk-num { text-align:right; font-variant-numeric:tabular-nums; padding-right:15px; }
				.stairbuilder-bulk-wrap .sb-bulk-new::before { content:"\2192"; color:#a7aaad; margin-right:6px; font-weight:400; }
				/* Preview highlight: rows whose value will change go red. */
				.stairbuilder-bulk-wrap .sb-bulk-row.sb-changed { background:#fcf0f1; border-color:#f0c0c2; }
				.stairbuilder-bulk-wrap .sb-bulk-row.sb-changed .sb-bulk-new { color:#b32d2e; font-weight:600; }
				/* Selection highlight: ticked rows get a blue left border + faint tint,
				   kept visually distinct from the red change highlight above. */
				.stairbuilder-bulk-wrap .sb-bulk-row.sb-checked { border-left:3px solid #2271b1; background:#f3f8fc; }
				.stairbuilder-bulk-wrap .sb-bulk-row.sb-checked.sb-changed { background:#fcf0f1; }
				.stairbuilder-bulk-wrap .sb-bulk-tag { display:inline-block; padding:1px 8px; border-radius:10px; font-size:11px; background:#e5e5e5; color:#1d2327; }
				.stairbuilder-bulk-wrap .sb-bulk-tag-oak { background:#dbead5; } .stairbuilder-bulk-wrap .sb-bulk-tag-pine { background:#f4e8cf; }
				.stairbuilder-bulk-wrap .sb-bulk-tag-none { background:transparent; color:#a7aaad; }
				.stairbuilder-bulk-wrap .sb-bulk-actions { margin-top:16px; display:flex; align-items:center; gap:12px; }
				.stairbuilder-bulk-wrap .sb-bulk-summary { color:#646970; }
				@media (max-width:782px){ .stairbuilder-bulk-wrap .sb-bulk-table tbody { grid-template-columns:1fr; } }'
			);

			wp_add_inline_script(
				'jquery',
				'jQuery(function($){
					var $rows = $("#sb-bulk-table tbody tr[data-id]");

					function num(v){ v = parseFloat(v); return isNaN(v) ? null : v; }

					function recompute(){
						var pct = num($("#sb-bulk-pct").val());
						var changed = 0, selected = 0;
						$rows.each(function(){
							var $tr = $(this);
							var on  = $tr.find(".sb-bulk-include").prop("checked");
							var old = num($tr.attr("data-value"));
							var $new = $tr.find(".sb-bulk-new");
							$tr.toggleClass("sb-checked", on);
							if (on) { selected++; }
							if (on && old !== null && pct !== null && pct !== 0){
								var nv = Math.round(old * (1 + pct/100) * 100) / 100;
								$new.text(nv.toFixed(2));
								if (nv !== old) { $tr.addClass("sb-changed"); changed++; }
								else { $tr.removeClass("sb-changed"); }
							} else {
								$new.html("&mdash;");
								$tr.removeClass("sb-changed");
							}
						});
						var msg = selected + " selected";
						if (pct !== null && pct !== 0) { msg += ", " + changed + " will change"; }
						$("#sb-bulk-summary").text(msg);
						refreshMatButtons();
					}

					// A material button is "active" when every enabled row of that
					// material is currently ticked.
					function refreshMatButtons(){
						$(".sb-bulk-mat").each(function(){
							var mat = $(this).data("mat");
							var $g = $rows.filter("[data-material=" + mat + "]").find(".sb-bulk-include:enabled");
							var all = $g.length > 0 && $g.filter(":checked").length === $g.length;
							$(this).toggleClass("is-active", all);
						});
					}

					$("#sb-bulk-checkall").on("change", function(){
						$rows.find(".sb-bulk-include:enabled").prop("checked", $(this).prop("checked"));
						recompute();
					});

					$(".sb-bulk-mat").on("click", function(){
						var mat = $(this).data("mat");
						var $g = $rows.filter("[data-material=" + mat + "]").find(".sb-bulk-include:enabled");
						var allOn = $g.length > 0 && $g.filter(":checked").length === $g.length;
						$g.prop("checked", !allOn); // toggle the whole material group
						recompute();
					});

					$(".sb-bulk-all").on("click", function(){ $rows.find(".sb-bulk-include:enabled").prop("checked", true); recompute(); });
					$(".sb-bulk-none").on("click", function(){ $rows.find(".sb-bulk-include:enabled").prop("checked", false); recompute(); });

					$("#sb-bulk-pct").on("input", recompute);
					$("#sb-bulk-preview").on("click", recompute);
					$("#sb-bulk-table").on("change", ".sb-bulk-include", recompute);

					$("#sb-bulk-form").on("submit", function(e){
						var pct = num($("#sb-bulk-pct").val());
						var selected = $rows.find(".sb-bulk-include:checked").length;
						if (pct === null || pct === 0 || selected === 0){
							e.preventDefault();
							window.alert("Enter a non-zero percentage and select at least one price.");
							return;
						}
						if (!window.confirm("Apply " + pct + "% to " + selected + " selected price(s)? This overwrites the stored prices.")){
							e.preventDefault();
						}
					});

					recompute();
				});'
			);
		}

		public function enqueue( $hook ) {
			// Bulk price-update page has its own lightweight assets.
			if ( $this->hook_bulk && $hook === $this->hook_bulk ) {
				$this->enqueue_bulk();
				return;
			}

			// Only load on our pricing page.
			if ( ! $this->hook_pricing || $hook !== $this->hook_pricing ) {
				return;
			}

			wp_enqueue_style( 'wp-color-picker' );
			wp_enqueue_script( 'wp-color-picker' );
			wp_enqueue_media(); // for the 'image' field media picker (PDF logo)

			wp_add_inline_style(
				'wp-color-picker',
				'.stairbuilder-pricing-wrap .stairbuilder-image-preview img { max-width: 200px; max-height: 100px; display: block; margin-bottom: 8px; border: 1px solid #dcdcde; background: #fff; padding: 4px; }
				.stairbuilder-pricing-wrap .stairbuilder-image-remove { margin-left: 8px; color: #b32d2e; }
				.stairbuilder-pricing-wrap .stairbuilder-tab-group { margin-bottom: 24px; }
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
					display: flex;
					flex-wrap: wrap;
					align-items: flex-end;
					gap: 14px;
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
				/* Every card row (newel posts/caps, handrails & baserails, spindles)
				   renders as a SINGLE compact line: each field a labelled column with
				   the label above its input, toggle + remove pushed to the right. The
				   header wrapper (and, on spindle rows, the active price block) use
				   display:contents so their fields become direct flex items of the row;
				   `order` keeps the toggle + remove button on the right regardless of
				   DOM order. Spindle rows additionally carry material-mode fields
				   (material type / glass basis) shown per mode below. */
				.stairbuilder-pricing-wrap .stairbuilder-card-row .stairbuilder-component-header { display: contents; }
				.stairbuilder-pricing-wrap .stairbuilder-card-row .stairbuilder-card-label { flex: 0 1 150px; margin-bottom: 0; order: 1; }
				.stairbuilder-pricing-wrap .stairbuilder-card-row .stairbuilder-component-variant { flex: 0 1 150px; min-width: 130px; order: 2; }
				.stairbuilder-pricing-wrap .stairbuilder-card-row .stairbuilder-card-switch { flex: 0 0 auto; order: 3; margin-left: auto; align-self: center; }
				.stairbuilder-pricing-wrap .stairbuilder-card-row .stairbuilder-card-actions { flex: 0 0 auto; order: 4; align-self: center; }
				/* Spindle material-mode blocks: show only the active block; glass basis
				   fields appear only when the row is in glass mode. Inert on non-spindle
				   rows (they carry no .bd-mode-block / .bd-glass-inline elements). */
				.stairbuilder-pricing-wrap .stairbuilder-card-row .bd-glass-inline { display: none; }
				.stairbuilder-pricing-wrap .stairbuilder-card-row.mode-glass .bd-glass-inline { display: block; }
				.stairbuilder-pricing-wrap .stairbuilder-card-row .bd-mode-block { display: none; }
				.stairbuilder-pricing-wrap .stairbuilder-card-row.mode-wood .bd-mode-wood,
				.stairbuilder-pricing-wrap .stairbuilder-card-row.mode-metal .bd-mode-metal,
				.stairbuilder-pricing-wrap .stairbuilder-card-row.mode-glass .bd-mode-glass { display: contents; }
				.stairbuilder-pricing-wrap .stairbuilder-card-mode select { font-weight: 400; }
				.stairbuilder-pricing-wrap .stairbuilder-card-add { margin-top: 14px; }
				.stairbuilder-pricing-wrap .stairbuilder-multiselect { display: flex; flex-wrap: wrap; gap: 4px 14px; padding-top: 4px; }
				.stairbuilder-pricing-wrap .stairbuilder-multiselect-option { display: inline-flex; align-items: center; gap: 4px; font-weight: 400; white-space: nowrap; }
				.stairbuilder-pricing-wrap .stairbuilder-multiselect .description { flex-basis: 100%; }
				.stairbuilder-pricing-wrap .stairbuilder-card-row input[readonly] { background: #f3f3f3; color: #666; cursor: not-allowed; }
				@media (max-width: 960px) {
					.stairbuilder-pricing-wrap .stairbuilder-card-row { flex-direction: column; align-items: stretch; }
					.stairbuilder-pricing-wrap .stairbuilder-card-row .stairbuilder-card-label,
					.stairbuilder-pricing-wrap .stairbuilder-card-row .stairbuilder-component-variant { flex-basis: auto; }
					.stairbuilder-pricing-wrap .stairbuilder-card-row .stairbuilder-card-switch { margin-left: 0; }
					.stairbuilder-pricing-wrap .stairbuilder-card-actions { text-align: left; }
				}'
			);

			wp_add_inline_script(
				'wp-color-picker',
				'jQuery(function($){
					// Colour pickers
					$(".stairbuilder-color-picker").wpColorPicker();

					// Media picker for image fields (e.g. the Quote PDF logo).
					$(document).on("click", ".stairbuilder-image-select", function(e){
						e.preventDefault();
						var $wrap = $(this).closest(".stairbuilder-image-field");
						var frame = wp.media({ title: "Select image", multiple: false, library: { type: "image" }, button: { text: "Use image" } });
						frame.on("select", function(){
							var att = frame.state().get("selection").first().toJSON();
							var url = (att.sizes && att.sizes.medium) ? att.sizes.medium.url : att.url;
							$wrap.find("input[type=hidden]").val(att.id);
							$wrap.find(".stairbuilder-image-preview").html("<img src=\"" + url + "\" alt=\"\" />");
							$wrap.find(".stairbuilder-image-remove").show();
						});
						frame.open();
					});
					$(document).on("click", ".stairbuilder-image-remove", function(e){
						e.preventDefault();
						var $wrap = $(this).closest(".stairbuilder-image-field");
						$wrap.find("input[type=hidden]").val("");
						$wrap.find(".stairbuilder-image-preview").empty();
						$(this).hide();
					});

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

		/**
		 * Seed the four canonical building-regs rows (v2.16.0 §3.2) when the
		 * repeater is empty. Building regs are FACTS ABOUT THE WORLD (identical
		 * for every UK licensee), so they ship as editable seeded defaults — see
		 * §11 seed-data policy. Seed-if-empty: a client's own configuration is
		 * never clobbered. One-shot, flag-gated.
		 */
		public function maybe_seed_building_regs() {
			if ( get_option( self::BUILDING_REGS_SEED_FLAG ) ) {
				return;
			}
			$opts = get_option( self::OPTION_KEY, array() );
			if ( ! is_array( $opts ) ) {
				$opts = array();
			}
			if ( empty( $opts['building_regs'] ) || ! is_array( $opts['building_regs'] ) ) {
				$opts['building_regs'] = self::default_building_regs();
				update_option( self::OPTION_KEY, $opts );
			}
			update_option( self::BUILDING_REGS_SEED_FLAG, 1 );
		}

		/**
		 * Canonical Approved Document K regime rows. Numeric columns left as ''
		 * mean "no constraint". Labels are plain; the formal ADK classification
		 * ("General Access" / "Utility") lives in the description. Escalation
		 * URLs are intentionally blank — a client's landing-calculator permalinks
		 * are their data, not a shipped constant (§11).
		 */
		private static function default_building_regs() {
			return array(
				array(
					'building_reg_name'     => 'Domestic – England & Wales',
					'building_reg_value'    => 'domestic-ew',
					'description'           => 'Private dwellings (houses and flats).',
					'min_going'             => 220,
					'max_rise'              => 220,
					'min_rise'              => '',
					'min_width'             => '',
					'max_pitch'             => 42,
					'two_r_g_min'           => 550,
					'two_r_g_max'           => 700,
					'max_open_gap'          => 100,
					'max_risers_run'        => '',
					'on_exceed_mode'        => 'warn',
					'on_exceed_message'     => '',
					'on_exceed_url_quarter' => '',
					'on_exceed_url_half'    => '',
				),
				array(
					'building_reg_name'     => 'Commercial – public areas',
					'building_reg_value'    => 'commercial-general',
					'description'           => 'Approved Document K “General Access” — retail, offices, schools and other public-circulation stairs.',
					'min_going'             => 280,
					'max_rise'              => 170,
					'min_rise'              => 150,
					'min_width'             => '',
					'max_pitch'             => 31.3,
					'two_r_g_min'           => 550,
					'two_r_g_max'           => 700,
					'max_open_gap'          => 100,
					'max_risers_run'        => 16,
					'on_exceed_mode'        => 'redirect',
					'on_exceed_message'     => 'A public-access stair this tall needs a landing. Try our quarter- or half-landing calculators, or call for advice.',
					'on_exceed_url_quarter' => '',
					'on_exceed_url_half'    => '',
				),
				array(
					'building_reg_name'     => 'Commercial – staff & maintenance only',
					'building_reg_value'    => 'commercial-utility',
					'description'           => 'Approved Document K “Utility” — maintenance access, secondary escape and staff-only stairs.',
					'min_going'             => 250,
					'max_rise'              => 190,
					'min_rise'              => 150,
					'min_width'             => '',
					'max_pitch'             => 37.2,
					'two_r_g_min'           => 550,
					'two_r_g_max'           => 700,
					'max_open_gap'          => 100,
					'max_risers_run'        => 16,
					'on_exceed_mode'        => 'redirect',
					'on_exceed_message'     => 'A utility stair this tall needs a landing. Try our quarter- or half-landing calculators, or call for advice.',
					'on_exceed_url_quarter' => '',
					'on_exceed_url_half'    => '',
				),
				array(
					'building_reg_name'     => 'No Building Regulations',
					'building_reg_value'    => 'none',
					'description'           => 'Unregulated — no dimensional limits applied.',
					'min_going'             => '',
					'max_rise'              => '',
					'min_rise'              => '',
					'min_width'             => '',
					'max_pitch'             => '',
					'two_r_g_min'           => '',
					'two_r_g_max'           => '',
					'max_open_gap'          => '',
					'max_risers_run'        => '',
					'on_exceed_mode'        => 'warn',
					'on_exceed_message'     => '',
					'on_exceed_url_quarter' => '',
					'on_exceed_url_half'    => '',
				),
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
					'geometry_defaults',
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
				// Stringer/tread/riser "code" is really a material choice (single
				// price per row), so those rows use a fixed material dropdown.
				$material_choices = $this->material_code_choices();
				// Shared sub-field set for the rich "card" repeaters (newels,
				// handrails, spindles). Each row carries its own name/code, a
				// per-row Use-Product-ID switch, and pine/oak price + product ID.
				$variant_subfields = [
					['id' => 'name',           'label' => 'Name',           'type' => 'text'],
					['id' => 'code',           'label' => 'Code',           'type' => 'text'],
					['id' => 'use_product_id', 'label' => 'Use Product ID', 'type' => 'toggle'],
					['id' => 'pine_price',     'label' => 'Pine Price',     'type' => 'price', 'adjustable' => true],
					['id' => 'oak_price',      'label' => 'Oak Price',      'type' => 'price', 'adjustable' => true],
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
					['id' => 'pine_price',     'label' => 'Pine Price',      'type' => 'price', 'adjustable' => true],
					['id' => 'oak_price',      'label' => 'Oak Price',       'type' => 'price', 'adjustable' => true],
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
								'subfields' => [['id' => 'stringer_name', 'label' => 'Stringer Name', 'type' => 'text'], ['id' => 'stringer_code', 'label' => 'Stringer Material', 'type' => 'select', 'choices' => $material_choices, 'default' => 'mdf', 'material_source' => true], ['id' => 'stringer_value', 'label' => 'Stringer Value', 'type' => 'number', 'adjustable' => true], ['id' => 'available_for', 'label' => 'Available for construction types', 'type' => 'multiselect', 'choices_source' => 'construction_codes']],
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
								// Numeric limit columns (v2.16.0 §3.1). EMPTY means "no
								// constraint", not zero — every consumer tests for empty before
								// comparing, which is what makes "No Building Regs" work with no
								// code path of its own.
								'subfields' => [
									['id' => 'building_reg_name', 'label' => 'Name', 'type' => 'text'],
									['id' => 'building_reg_value', 'label' => 'Code / identifier', 'type' => 'text'],
									['id' => 'description', 'label' => 'Customer description', 'type' => 'textarea'],
									['id' => 'min_going', 'label' => 'Min going (mm)', 'type' => 'number'],
									['id' => 'max_rise', 'label' => 'Max rise (mm)', 'type' => 'number'],
									['id' => 'min_rise', 'label' => 'Min rise (mm)', 'type' => 'number'],
									['id' => 'min_width', 'label' => 'Min width (mm)', 'type' => 'number'],
									['id' => 'max_pitch', 'label' => 'Max pitch (°)', 'type' => 'number'],
									['id' => 'two_r_g_min', 'label' => '2R+G min (mm)', 'type' => 'number'],
									['id' => 'two_r_g_max', 'label' => '2R+G max (mm)', 'type' => 'number'],
									['id' => 'max_open_gap', 'label' => 'Max open-riser gap (mm)', 'type' => 'number'],
									['id' => 'max_risers_run', 'label' => 'Max risers per run', 'type' => 'number'],
									['id' => 'on_exceed_mode', 'label' => 'On exceed', 'type' => 'select', 'choices' => ['warn' => 'Warn (allow)', 'block' => 'Block (no price)', 'redirect' => 'Redirect to landing calculator'], 'default' => 'warn'],
									['id' => 'on_exceed_message', 'label' => 'On-exceed message', 'type' => 'textarea'],
									['id' => 'on_exceed_url_quarter', 'label' => 'Quarter-landing URL', 'type' => 'text'],
									['id' => 'on_exceed_url_half', 'label' => 'Half-landing URL', 'type' => 'text'],
								],
							],
							[
								'id' => 'construction_types',
								'label' => 'Add Construction Types',
								'type' => 'repeater',
								'style' => 'card',
								// Code is a stable machine key that availability tags + historical
								// leads depend on: slugified on create, locked (read-only) once
								// saved, never retro-slugged. See §Phase 0.
								'lock_code' => 'construction_code',
								'subfields' => [
									['id' => 'construction_name', 'label' => 'Construction Name', 'type' => 'text'],
									['id' => 'construction_code', 'label' => 'Construction Code', 'type' => 'text'],
									['id' => 'construction_value', 'label' => 'Construction Value', 'type' => 'number', 'adjustable' => true],
									// strict_for: repeaters this construction type gates strictly —
									// only rows tagged available_for THIS type are selectable; untagged
									// rows are excluded. Enforcement (option filtering) lands in Phase 2.
									['id' => 'strict_for', 'label' => 'Restrict strictly (only tagged rows selectable)', 'type' => 'multiselect', 'choices' => ['stringer_types' => 'Stringers', 'tread_types' => 'Treads', 'riser_types' => 'Risers', 'tread_profiles' => 'Tread Profiles']],
								],
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
								'subfields' => [['id' => 'tread_name', 'label' => 'Tread Name', 'type' => 'text'], ['id' => 'tread_code', 'label' => 'Tread Material', 'type' => 'select', 'choices' => $material_choices, 'default' => 'mdf', 'material_source' => true], ['id' => 'tread_value', 'label' => 'Tread Value', 'type' => 'number', 'adjustable' => true], ['id' => 'available_for', 'label' => 'Available for construction types', 'type' => 'multiselect', 'choices_source' => 'construction_codes']],
							],
							[
								'id' => 'tread_profiles',
								'label' => 'Add Tread Profiles',
								'type' => 'repeater',
								'style' => 'card',
								'subfields' => [['id' => 'tread_profile_name', 'label' => 'Profile Name', 'type' => 'text'], ['id' => 'tread_profile_code', 'label' => 'Profile Code', 'type' => 'text'], ['id' => 'tread_profile_value', 'label' => 'Per-Tread Surcharge', 'type' => 'number', 'adjustable' => true], ['id' => 'available_for', 'label' => 'Available for construction types', 'type' => 'multiselect', 'choices_source' => 'construction_codes']],
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
								'subfields' => [['id' => 'riser_name', 'label' => 'Riser Name', 'type' => 'text'], ['id' => 'riser_code', 'label' => 'Riser Material', 'type' => 'select', 'choices' => $material_choices, 'default' => 'mdf', 'material_source' => true], ['id' => 'riser_value', 'label' => 'Riser Value', 'type' => 'number', 'adjustable' => true], ['id' => 'available_for', 'label' => 'Available for construction types', 'type' => 'multiselect', 'choices_source' => 'construction_codes']],
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
								'adjustable' => true,
								'description' => 'Add a value to be used in builder forms.',
							],
							[
								'id' => 'ply_bullnose_price',
								'label' => 'Plywood Bullnose',
								'type' => 'number',
								'adjustable' => true,
								'description' => 'Add a value to be used in builder forms.',
							],
							[
								'id' => 'pine_bullnose_price',
								'label' => 'Pine Bullnose',
								'type' => 'number',
								'adjustable' => true,
								'description' => 'Add a value to be used in builder forms.',
							],
							[
								'id' => 'oak_bullnose_price',
								'label' => 'Oak Bullnose',
								'type' => 'number',
								'adjustable' => true,
								'description' => 'Add a value to be used in builder forms.',
							],
							[
								'id' => 'mdf_curtail_price',
								'label' => 'MDF Curtail',
								'type' => 'number',
								'adjustable' => true,
								'description' => 'Add a value to be used in builder forms.',
							],
							[
								'id' => 'ply_curtail_price',
								'label' => 'Plywood Curtail',
								'type' => 'number',
								'adjustable' => true,
								'description' => 'Add a value to be used in builder forms.',
							],
							[
								'id' => 'pine_curtail_price',
								'label' => 'Pine Curtail',
								'type' => 'number',
								'adjustable' => true,
								'description' => 'Add a value to be used in builder forms.',
							],
							[
								'id' => 'oak_curtail_price',
								'label' => 'Oak Curtail',
								'type' => 'number',
								'adjustable' => true,
								'description' => 'Add a value to be used in builder forms.',
							],
							[
								'id' => 'mdf_dbl_curtail_price',
								'label' => 'MDF Double Curtail',
								'type' => 'number',
								'adjustable' => true,
								'description' => 'Add a value to be used in builder forms.',
							],
							[
								'id' => 'ply_dbl_curtail_price',
								'label' => 'Plywood Double Curtail',
								'type' => 'number',
								'adjustable' => true,
								'description' => 'Add a value to be used in builder forms.',
							],
							[
								'id' => 'pine_dbl_curtail_price',
								'label' => 'Pine Double Curtail',
								'type' => 'number',
								'adjustable' => true,
								'description' => 'Add a value to be used in builder forms.',
							],
							[
								'id' => 'oak_dbl_curtail_price',
								'label' => 'Oak Double Curtail',
								'type' => 'number',
								'adjustable' => true,
								'description' => 'Add a value to be used in builder forms.',
							],
							[
								'id' => 'mdf_dcb_curtail_price',
								'label' => 'MDF Dble Curtail + Bullnose',
								'type' => 'number',
								'adjustable' => true,
								'description' => 'Add a value to be used in builder forms.',
							],
							[
								'id' => 'ply_dcb_curtail_price',
								'label' => 'Plywood Dble Curtail + Bullnose',
								'type' => 'number',
								'adjustable' => true,
								'description' => 'Add a value to be used in builder forms.',
							],
							[
								'id' => 'pine_dcb_curtail_price',
								'label' => 'Pine Dble Curtail + Bullnose',
								'type' => 'number',
								'adjustable' => true,
								'description' => 'Add a value to be used in builder forms.',
							],
							[
								'id' => 'oak_dcb_curtail_price',
								'label' => 'Oak Dble Curtail + Bullnose',
								'type' => 'number',
								'adjustable' => true,
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
								'adjustable' => true,
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
								'adjustable' => true,
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
					'geometry_defaults' => [
						'label' => 'Geometry / Defaults',
						'fields' => [
							// Dimensions, NOT prices — no 'adjustable' flags here, so the
							// bulk price tool never enumerates them.
							[
								'id' => 'riser_search_min',
								'label' => 'Riser height — search minimum (mm)',
								'type' => 'number',
								'placeholder' => 150,
								'default' => 150,
								'description' => 'Lower bound of the riser-height search used when the selected building-regs regime sets no minimum rise (e.g. No Building Regs). 150 is the Approved Document K non-domestic minimum — lower it only for products like loft ladders or space savers. Leave empty to use 150.',
							],
							[
								'id' => 'riser_search_max',
								'label' => 'Riser height — search maximum (mm)',
								'type' => 'number',
								'placeholder' => 220,
								'default' => 220,
								'description' => 'Upper bound of the riser-height search used when the regime sets no maximum rise. 220 is the Approved Document K domestic maximum — raise it for steeper unregulated stairs. Leave empty to use 220.',
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
								'adjustable' => true,
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
								'adjustable' => true,
							],
							[
								'id' => 'quarter_landing_oak_string',
								'label' => 'Quarter Landing Oak String',
								'type' => 'number',
								'adjustable' => true,
							],
							[
								'id' => 'quarter_landing_oak_tr',
								'label' => 'Quarter Landing Oak Tread & Riser',
								'type' => 'number',
								'adjustable' => true,
							],
							[
								'id' => 'quarter_landing_oak_tread',
								'label' => 'Quarter Landing Oak Tread',
								'type' => 'number',
								'adjustable' => true,
							],
							[
								'id' => 'quarter_landing_no_oak',
								'label' => 'Quarter Landing No Oak',
								'type' => 'number',
								'adjustable' => true,
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
								'adjustable' => true,
							],
							[
								'id' => 'half_landing_oak_string',
								'label' => 'Half Landing Oak String',
								'type' => 'number',
								'adjustable' => true,
							],
							[
								'id' => 'half_landing_oak_tr',
								'label' => 'Half Landing Oak Tread & Riser',
								'type' => 'number',
								'adjustable' => true,
							],
							[
								'id' => 'half_landing_oak_tread',
								'label' => 'Half Landing Oak Tread',
								'type' => 'number',
								'adjustable' => true,
							],
							[
								'id' => 'half_landing_no_oak',
								'label' => 'Half Landing No Oak',
								'type' => 'number',
								'adjustable' => true,
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
							// --- Configure panel (#stairbuild, left) ---
							[
								'id' => 'form_bg',
								'label' => 'Configure Panel — Background',
								'type' => 'color',
								'default' => '#FBF9F4',
								'group' => 'Panel & Button Colours',
							],
							[
								'id' => 'form_text',
								'label' => 'Configure Panel — Text & Ticks',
								'type' => 'color',
								'default' => '#2B2522',
								'description' => 'Section headings, completion ticks, active-section accent and the price total.',
							],
							[
								'id' => 'form_link',
								'label' => 'Configure Panel — Links',
								'type' => 'color',
								'default' => '#8F7A34',
							],
							[
								'id' => 'section_open_bg',
								'label' => 'Configure — Open Section Tint',
								'type' => 'color',
								'default' => '#F4F0E4',
								'description' => 'Background of the currently expanded accordion section.',
							],
							[
								'id' => 'panel_hairline',
								'label' => 'Panel — Hairlines & Dividers',
								'type' => 'color',
								'default' => '#E3DCCB',
							],
							[
								'id' => 'panel_muted',
								'label' => 'Panel — Secondary Text',
								'type' => 'color',
								'default' => '#7A7062',
								'description' => 'Section summaries, field sub-labels and the price breakdown lines.',
							],
							// --- Measurements panel (.mm_breakout, right) ---
							[
								'id' => 'measurements_bg',
								'label' => 'Measurements Panel — Background',
								'type' => 'color',
								'default' => '#2B2522',
							],
							[
								'id' => 'measurements_text',
								'label' => 'Measurements Panel — Text',
								'type' => 'color',
								'default' => '#EFE8D8',
							],
							[
								'id' => 'measurements_link',
								'label' => 'Measurements Panel — Links',
								'type' => 'color',
								'default' => '#EFE8D8',
							],
							// --- Primary action: "Get free quote" button + mobile Configure bar ---
							[
								'id' => 'quote_bg',
								'label' => 'Primary Button — Background',
								'type' => 'color',
								'default' => '#8F7A34',
								'description' => 'The "Get free quote" button and, on mobile, the Configure launcher bar.',
							],
							[
								'id' => 'quote_text',
								'label' => 'Primary Button — Text',
								'type' => 'color',
								'default' => '#FFF9E9',
							],
							// --- Selected option pills (delivery / packaging choices) ---
							[
								'id' => 'tab_active_bg',
								'label' => 'Selected Option — Background',
								'type' => 'color',
								'default' => '#2B2522',
								'description' => 'Highlighted delivery / packaging choice pills and the delivery update button.',
							],
							[
								'id' => 'tab_active_text',
								'label' => 'Selected Option — Text',
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
							// --- Quote PDF branding (templates/stairbuilder_pdf.php) ---
							[
								'id' => 'pdf_accent',
								'label' => 'PDF — Accent (brand)',
								'type' => 'color',
								'default' => '#A6914E',
								'group' => 'Quote PDF',
							],
							[
								'id' => 'pdf_dark',
								'label' => 'PDF — Dark (header / footer)',
								'type' => 'color',
								'default' => '#35332F',
							],
							[
								'id' => 'pdf_muted',
								'label' => 'PDF — Muted text',
								'type' => 'color',
								'default' => '#7A756A',
							],
							[
								'id' => 'pdf_panel',
								'label' => 'PDF — Panel background',
								'type' => 'color',
								'default' => '#EBE8E0',
							],
							[
								'id' => 'pdf_logo',
								'label' => 'PDF — Logo',
								'type' => 'image',
								'description' => 'Shown top-left of the quote masthead. A PNG with a transparent background works best on the dark band.',
							],
							[
								'id' => 'pdf_header_left',
								'label' => 'PDF — Top strip (left)',
								'type' => 'text',
								'placeholder' => 'e.g. Call today on 0191 341 0077',
							],
							[
								'id' => 'pdf_header_right',
								'label' => 'PDF — Top strip (right)',
								'type' => 'text',
								'placeholder' => 'e.g. yourdomain.co.uk',
							],
							[
								'id' => 'pdf_footer_left',
								'label' => 'PDF — Footer (left)',
								'type' => 'text',
								'placeholder' => 'e.g. Your Company Ltd',
							],
							[
								'id' => 'pdf_footer_right',
								'label' => 'PDF — Footer (right)',
								'type' => 'text',
								'placeholder' => 'e.g. 0191 341 0077 · yourdomain.co.uk',
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