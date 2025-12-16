<?php
/**
 * Plugin Name: Taxonomy Depth Control
 * Description: Control how many depth levels can be created or selected for hierarchical taxonomies.
 * Version: 0.1.0
 * Author: Copilot
 * License: GPLv2 or later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain: taxonomy-depth-control
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

final class TDC_Plugin {
    const OPTION_KEY = 'tdc_settings';

    private static $instance;

    public static function init() {
        if ( null === self::$instance ) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    private function __construct() {
        add_action( 'admin_menu', array( $this, 'register_admin_menu' ) );
        add_action( 'admin_init', array( $this, 'register_settings' ) );
        add_action( 'admin_init', array( $this, 'add_column_filters' ) );

        // prevent adding terms deeper than allowed
        add_filter( 'pre_insert_term', array( $this, 'validate_term_depth_on_insert' ), 10, 2 );

        // when editing terms, enforce after update
        add_action( 'edit_term', array( $this, 'validate_term_depth_on_edit' ), 10, 3 );

        // add walker for term checklist to add depth attributes
        add_filter( 'wp_terms_checklist_args', array( $this, 'maybe_set_walker' ), 10, 2 );

        // disable selection of deep terms in post editor via JS
        add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_admin_scripts' ) );

        // prevent assigning terms deeper than allowed
        add_filter( 'pre_set_object_terms', array( $this, 'filter_terms_on_set' ), 10, 3 );

        // admin notices
        add_action( 'admin_notices', array( $this, 'admin_notices' ) );
        // filter parent dropdown in taxonomy edit screens
        add_filter( 'wp_dropdown_cats', array( $this, 'filter_parent_dropdown_html' ), 10, 2 );
        // debug hook removed; keep admin UI clean
        // print CSS to hide Description/Slug/Count columns per-taxonomy
        add_action( 'admin_head', array( $this, 'print_column_hiding_css' ) );
    }

    public function print_column_hiding_css() {
        $settings = get_option( self::OPTION_KEY, array() );
        if ( empty( $settings ) || ! is_array( $settings ) ) {
            return;
        }

        $rules = array();
        foreach ( $settings as $tax => $s ) {
            if ( ! is_array( $s ) ) {
                continue;
            }
            $parts = array();
            if ( ! empty( $s['hide_description'] ) ) {
                $parts[] = '.edit-tags-php thead th.column-description';
                $parts[] = '.edit-tags-php thead th#description';
                $parts[] = '.edit-tags-php tfoot th.column-description';
                $parts[] = '.edit-tags-php tfoot th#description';
                $parts[] = '.edit-tags-php td.column-description';
            }
            if ( ! empty( $s['hide_slug'] ) ) {
                $parts[] = '.edit-tags-php thead th.column-slug';
                $parts[] = '.edit-tags-php thead th#slug';
                $parts[] = '.edit-tags-php tfoot th.column-slug';
                $parts[] = '.edit-tags-php tfoot th#slug';
                $parts[] = '.edit-tags-php td.column-slug';
            }
            if ( ! empty( $s['hide_count'] ) ) {
                $parts[] = '.edit-tags-php thead th.column-posts';
                $parts[] = '.edit-tags-php thead th#posts';
                $parts[] = '.edit-tags-php tfoot th.column-posts';
                $parts[] = '.edit-tags-php tfoot th#posts';
                $parts[] = '.edit-tags-php td.column-posts';
            }

            if ( ! empty( $parts ) ) {
                $rules[] = sprintf( 'body.edit-tags-php.taxonomy-%s .wp-list-table %s, body.edit-tags-php.taxonomy-%s .inline-edit-row %s', esc_attr( $tax ), implode( ', ', $parts ), esc_attr( $tax ), implode( ', ', $parts ) );
            }
        }

        if ( ! empty( $rules ) ) {
            $selector = implode( ', ', $rules );
            printf( '<style>%s { display: none !important; }</style>', esc_html( $selector ) );
        }
    }

    // debug helper: show current taxonomy settings on the edit-tags page when user can manage_options


    public function add_column_filters() {
        $taxonomies = get_taxonomies( array( 'hierarchical' => true ) );
        foreach ( $taxonomies as $tax ) {
            // register a filter specific to this taxonomy so we always know which taxonomy is in context
            add_filter( "manage_edit-{$tax}_columns", function ( $columns ) use ( $tax ) {
                $settings = get_option( self::OPTION_KEY, array() );
                if ( empty( $settings[ $tax ] ) || ! is_array( $settings[ $tax ] ) ) {
                    return $columns;
                }

                $s = $settings[ $tax ];
                // column hiding is now performed via CSS so we don't unset columns server-side

                    // We no longer add a separate Level column server-side; labels are shown inline next to the Name column via JS

                return $columns;
            }, 20 );
        }
    }

    public function filter_taxonomy_columns( $columns ) {
        // old server-side column filtering removed in favor of CSS-based hiding
        return $columns;
    }

    public function register_admin_menu() {
        add_options_page( 'Taxonomy Depth Control', 'Taxonomy Depth', 'manage_options', 'tdc-settings', array( $this, 'settings_page' ) );
    }

    /**
     * Remove options from the parent dropdown that would create a term deeper than allowed.
     * Runs on the HTML output of `wp_dropdown_categories` which is used for the Parent select.
     *
     * @param string $output HTML output for the dropdown
     * @param array  $r      Arguments passed to wp_dropdown_categories
     * @return string Filtered HTML
     */
    public function filter_parent_dropdown_html( $output, $r ) {
        // only affect admin parent dropdowns for taxonomy terms
        if ( ! is_admin() ) {
            return $output;
        }

        // ensure this is the parent select on edit-tags.php
        if ( empty( $r['name'] ) || 'parent' !== $r['name'] ) {
            return $output;
        }

        if ( empty( $r['taxonomy'] ) ) {
            return $output;
        }

        $taxonomy = $r['taxonomy'];
        $max = $this->get_max_depth( $taxonomy );
        if ( ! $max ) {
            return $output;
        }

        // get all terms to compute depths
        $terms = get_terms( array( 'taxonomy' => $taxonomy, 'hide_empty' => false ) );
        if ( is_wp_error( $terms ) || empty( $terms ) ) {
            return $output;
        }

        $remove_ids = array();
        foreach ( $terms as $term ) {
            $depth = count( get_ancestors( $term->term_id, $taxonomy ) );
            // parents must have depth < max (since new child would be depth+1)
            if ( $depth >= $max ) {
                $remove_ids[] = intval( $term->term_id );
            }
        }

        if ( empty( $remove_ids ) ) {
            return $output;
        }

        // remove option elements whose value attribute matches any of the remove_ids
        foreach ( $remove_ids as $id ) {
            // remove option tags with value="{$id}" (allow other attributes/order)
            $output = preg_replace( '#<option[^>]*value="' . preg_quote( $id, '#' ) . '"[^>]*>.*?</option>#is', '', $output );
        }

        return $output;
    }

    public function register_settings() {
        register_setting( 'tdc_settings_group', self::OPTION_KEY, array( $this, 'sanitize_settings' ) );
    }

    public function sanitize_settings( $input ) {
        $out = array();
        if ( ! is_array( $input ) ) {
            return $out;
        }

        foreach ( $input as $tax => $vals ) {
            $tax = sanitize_key( $tax );
            // support legacy scalar depth value
            if ( ! is_array( $vals ) ) {
                $depth = intval( $vals );
                $out[ $tax ] = array( 'depth' => $depth, 'hide_description' => 0, 'hide_slug' => 0, 'hide_count' => 0, 'labels' => array() );
                continue;
            }

            $depth = isset( $vals['depth'] ) ? intval( $vals['depth'] ) : 0;
            $hide_description = ! empty( $vals['hide_description'] ) ? 1 : 0;
            $hide_slug = ! empty( $vals['hide_slug'] ) ? 1 : 0;
            $hide_count = ! empty( $vals['hide_count'] ) ? 1 : 0;
            $labels = array();
            // If checkbox is not present in submission it should default to 0 (off)
            $show_label = ! empty( $vals['show_label'] ) ? 1 : 0;
            if ( isset( $vals['labels'] ) && is_array( $vals['labels'] ) ) {
                foreach ( $vals['labels'] as $lbl ) {
                    $labels[] = sanitize_text_field( $lbl );
                }
            }

            $out[ $tax ] = array( 'depth' => $depth, 'hide_description' => $hide_description, 'hide_slug' => $hide_slug, 'hide_count' => $hide_count, 'labels' => $labels, 'show_label' => $show_label );
        }

        return $out;
    }

    public function settings_page() {
        if ( ! current_user_can( 'manage_options' ) ) {
            return;
        }

        $taxonomies = get_taxonomies( array( 'hierarchical' => true ), 'objects' );
        $settings = get_option( self::OPTION_KEY, array() );
        if ( ! is_array( $settings ) ) {
            $settings = array();
        }
        ?>
<div class="wrap">
    <h1>Taxonomy Depth Control</h1>
    <form method="post" action="options.php">
        <?php wp_nonce_field( 'options.php', 'my_nonce' ); ?>

        <?php settings_fields( 'tdc_settings_group' ); ?>
        <div class="tdc-tabs-wrapper">
            <ul class="tdc-tab-list nav-tab-wrapper" role="tablist">
                <?php $first = true; foreach ( $taxonomies as $taxonomy => $obj ): ?>
                <li role="tab" tabindex="0" class="" data-tax="<?php echo esc_attr( $taxonomy ); ?>">
                    <button type="button" data-tax="<?php echo esc_attr( $taxonomy ); ?>" class="tdc-tab nav-tab <?php echo $first ? 'active nav-tab-active' : ''; ?>">
                        <?php echo esc_html( $obj->labels->name ); ?>
                    </button>
                </li>
                <?php $first = false; endforeach; ?>
            </ul>

            <div class="tdc-tab-panels">
                <?php $first = true; foreach ( $taxonomies as $taxonomy => $obj ): 
                            $s = isset( $settings[ $taxonomy ] ) ? $settings[ $taxonomy ] : array();
                            $depth = is_array( $s ) && isset( $s['depth'] ) ? intval( $s['depth'] ) : ( is_scalar( $s ) ? intval( $s ) : 0 );
                            $hide_description = is_array( $s ) && ! empty( $s['hide_description'] );
                            $hide_slug = is_array( $s ) && ! empty( $s['hide_slug'] );
                            $hide_count = is_array( $s ) && ! empty( $s['hide_count'] );
                        ?>
                <div id="tdc-tab-<?php echo esc_attr( $taxonomy ); ?>"
                    class="tdc-tab-panel <?php echo $first ? 'active' : ''; ?>"
                    data-tax="<?php echo esc_attr( $taxonomy ); ?>">
                    <div class="tdc-tax-card">
                        <h3><?php echo esc_html( $obj->labels->name ); ?></h3>
                        <div class="tdc-settings-row">
                            <label>
                                Depth: <input class="tdc-depth-input" type="number" min="0" max="99" maxlength="2"
                                    name="<?php echo esc_attr( self::OPTION_KEY ); ?>[<?php echo esc_attr( $taxonomy ); ?>][depth]"
                                    value="<?php echo esc_attr( $depth ); ?>" />
                            </label>
                            <p class="description">Set max allowed depth (0 = no limit). Depth counts ancestors.
                                Example: top-level = 0, child = 1.</p>
                        </div>
                        <div class="tdc-settings-row">
                            <label><input type="checkbox"
                                    name="<?php echo esc_attr( self::OPTION_KEY ); ?>[<?php echo esc_attr( $taxonomy ); ?>][hide_description]"
                                    value="1" <?php checked( $hide_description ); ?> /> Hide Description</label>
                            <label><input type="checkbox"
                                    name="<?php echo esc_attr( self::OPTION_KEY ); ?>[<?php echo esc_attr( $taxonomy ); ?>][hide_slug]"
                                    value="1" <?php checked( $hide_slug ); ?> /> Hide Slug</label>
                            <label><input type="checkbox"
                                    name="<?php echo esc_attr( self::OPTION_KEY ); ?>[<?php echo esc_attr( $taxonomy ); ?>][hide_count]"
                                    value="1" <?php checked( $hide_count ); ?> /> Hide Count</label>
                        </div>

                        <div class="tdc-level-labels" data-tax="<?php echo esc_attr( $taxonomy ); ?>">
                            <h4>Level names</h4>
                            <div class="tdc-label-inputs">
                                        <?php
                                            $existing_labels = is_array( $s ) && isset( $s['labels'] ) ? (array) $s['labels'] : array();
                                            $levels = max( 1, $depth + 1 );
                                            for ( $i = 0; $i < $levels; $i++ ):
                                                $val = isset( $existing_labels[ $i ] ) ? $existing_labels[ $i ] : '';
                                            ?>
                                <div class="tdc-label-row">
                                            <label>Level <?php echo intval( $i ); ?> name: <input type="text"
                                            name="<?php echo esc_attr( self::OPTION_KEY ); ?>[<?php echo esc_attr( $taxonomy ); ?>][labels][]"
                                            value="<?php echo esc_attr( $val ); ?>"
                                            placeholder="Optional label for this level" /></label>
                                </div>
                                <?php endfor; ?>
                            </div>
                            <p class="description">Optional: provide human names for each level (top-level is 0).</p>
                            <p>
                                <label><input type="checkbox"
                                        name="<?php echo esc_attr( self::OPTION_KEY ); ?>[<?php echo esc_attr( $taxonomy ); ?>][show_label]"
                                        value="1" <?php checked( ! empty( $s['show_label'] ) ); ?> /> Show level label
                                    beside term name</label>
                            </p>
                            <div class="tdc-preview"></div>
                        </div>
                    </div>
                </div>
                <?php $first = false; endforeach; ?>
            </div>
        </div>
        <?php submit_button(); ?>
    </form>
</div>
<?php
    }

    private function get_max_depth( $taxonomy ) {
        $settings = get_option( self::OPTION_KEY, array() );
        if ( isset( $settings[ $taxonomy ] ) ) {
            $s = $settings[ $taxonomy ];
            if ( is_array( $s ) && isset( $s['depth'] ) ) {
                $v = intval( $s['depth'] );
            } else {
                $v = intval( $s );
            }
            if ( $v > 0 ) {
                return $v;
            }
        }
        return 0; // 0 means no limit
    }

    public function validate_term_depth_on_insert( $term, $taxonomy ) {
        $max = $this->get_max_depth( $taxonomy );
        if ( ! $max ) {
            return $term;
        }

        // parent might be in $_REQUEST when adding from admin
        $parent = 0;
        // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- when present we verify the nonce; this filter may also be called programmatically where a nonce is not available
        if ( isset( $_REQUEST['parent'] ) ) {
            // If a nonce is present, verify it to ensure this request originates from WP admin.
                if ( isset( $_REQUEST['_wpnonce'] ) ) {
                    $nonce = sanitize_text_field( wp_unslash( $_REQUEST['_wpnonce'] ) );
                    if ( ! wp_verify_nonce( $nonce, 'add-tag' ) ) {
                        return $term;
                    }
                }
            $parent = intval( wp_unslash( $_REQUEST['parent'] ) );
        }

            if ( $parent ) {
            $depth = count( get_ancestors( $parent, $taxonomy ) );
            // child will have depth = ancestors + 1
                if ( $depth + 1 > $max ) {
                    /* translators: %1$d: max allowed depth, %2$s: taxonomy name */
                    return new WP_Error( 'tdc_too_deep', sprintf( __( 'You cannot create a term at depth greater than %1$d for %2$s.', 'taxonomy-depth-control' ), $max, esc_html( $taxonomy ) ) );
                }
        }

        return $term;
    }

    public function validate_term_depth_on_edit( $term_id, $tt_id, $taxonomy ) {
        $max = $this->get_max_depth( $taxonomy );
        if ( ! $max ) {
            return;
        }

        $term = get_term( $term_id, $taxonomy );
        if ( ! $term || is_wp_error( $term ) ) {
            return;
        }

        $depth = count( get_ancestors( $term->parent, $taxonomy ) );
        if ( $term->parent && $depth + 1 > $max ) {
            // move term to top level to avoid exceeding limit
            wp_update_term( $term_id, $taxonomy, array( 'parent' => 0 ) );
            /* translators: %1$d: max allowed depth */
            add_settings_error( 'tdc', 'tdc_edit_moved', sprintf( __( 'Term was moved to top-level because its parent would exceed max depth %1$d.', 'taxonomy-depth-control' ), $max ), 'error' );
        }
    }

    public function maybe_set_walker( $args, $taxonomy ) {
        if ( empty( $args['taxonomy'] ) ) {
            return $args;
        }

        $max = $this->get_max_depth( $args['taxonomy'] );
        if ( $max ) {
            if ( empty( $args['walker'] ) || ! is_object( $args['walker'] ) || ! is_a( $args['walker'], 'TDC_Walker_Checklist' ) ) {
                if ( ! class_exists( 'TDC_Walker_Checklist' ) ) {
                    require_once plugin_dir_path( __FILE__ ) . 'includes/class-tdc-walker.php';
                }
                $args['walker'] = new TDC_Walker_Checklist( $args );
            }
        }

        return $args;
    }

    public function enqueue_admin_scripts( $hook ) {
        // load on post edit screens, taxonomy edit screens and our settings page
        if ( in_array( $hook, array( 'post.php', 'post-new.php', 'edit-tags.php', 'settings_page_tdc-settings' ), true ) ) {
            wp_enqueue_script( 'tdc-admin', plugin_dir_url( __FILE__ ) . 'assets/tdc-admin.js', array(), '0.1', true );
            // enqueue admin styles on settings page and taxonomy edit screens so badges render
            if ( in_array( $hook, array( 'settings_page_tdc-settings', 'edit-tags.php' ), true ) ) {
                wp_enqueue_style( 'tdc-admin-style', plugin_dir_url( __FILE__ ) . 'assets/tdc-admin.css', array(), '0.1' );
            }

            $data = array( 'maxDepthByTax' => $this->get_all_max_depths() );

            // when on taxonomy edit screen, include current taxonomy and term depths for client-side filtering
            if ( 'edit-tags.php' === $hook ) {
                $screen = function_exists( 'get_current_screen' ) ? get_current_screen() : null;
                $current_tax = '';
                if ( $screen && ! empty( $screen->taxonomy ) ) {
                    $current_tax = $screen->taxonomy;
                } elseif ( isset( $_GET['taxonomy'] ) ) {
                    // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- reading taxonomy from GET for display-only, not processing a state-changing form
                    $current_tax = sanitize_key( wp_unslash( $_GET['taxonomy'] ) );
                }

                if ( $current_tax ) {
                    $data['currentTax'] = $current_tax;
                    $data['termDepths'] = array( $current_tax => $this->get_term_depths( $current_tax ) );
                    $settings = get_option( self::OPTION_KEY, array() );
                    if ( ! empty( $settings[ $current_tax ] ) && ! empty( $settings[ $current_tax ]['labels'] ) ) {
                        $data['labels'] = array( $current_tax => $settings[ $current_tax ]['labels'] );
                    }
                }
            }

            // provide hide-column flags for all taxonomies so JS can hide headers if needed
            $settings = get_option( self::OPTION_KEY, array() );
            $hide = array();
            if ( is_array( $settings ) ) {
                foreach ( $settings as $tax => $s ) {
                    if ( ! is_array( $s ) ) {
                        continue;
                    }
                    $hide[ $tax ] = array(
                        'description' => ! empty( $s['hide_description'] ),
                        'slug' => ! empty( $s['hide_slug'] ),
                        'count' => ! empty( $s['hide_count'] ),
                    );
                    $show_label_map[ $tax ] = ! empty( $s['show_label'] );
                }
            }
            $data['hideColumns'] = $hide;
            $data['showLabel'] = isset( $show_label_map ) ? $show_label_map : array();

            wp_localize_script( 'tdc-admin', 'tdcSettings', $data );
        }
    }

    private function get_term_depths( $taxonomy ) {
        $out = array();
        $terms = get_terms( array( 'taxonomy' => $taxonomy, 'hide_empty' => false ) );
        if ( is_wp_error( $terms ) || empty( $terms ) ) {
            return $out;
        }
        foreach ( $terms as $t ) {
            $out[ $t->term_id ] = count( get_ancestors( $t->term_id, $taxonomy ) );
        }
        return $out;
    }

    private function get_all_max_depths() {
        $settings = get_option( self::OPTION_KEY, array() );
        if ( ! is_array( $settings ) ) {
            $settings = array();
        }
        $out = array();
        foreach ( $settings as $tax => $v ) {
            if ( is_array( $v ) && isset( $v['depth'] ) ) {
                $d = intval( $v['depth'] );
            } else {
                $d = intval( $v );
            }
            if ( $d > 0 ) {
                $out[ $tax ] = $d;
            }
        }
        return $out;
    }

    public function filter_terms_on_set( $terms, $object_id, $taxonomy ) {
        $max = $this->get_max_depth( $taxonomy );
        if ( ! $max || empty( $terms ) ) {
            return $terms;
        }

        $filtered = array();
            foreach ( (array) $terms as $t ) {
            $term = null;
            if ( is_numeric( $t ) ) {
                $term = get_term( intval( $t ), $taxonomy );
            } else {
                $term = get_term_by( 'slug', $t, $taxonomy );
                if ( ! $term ) {
                    $term = get_term_by( 'name', $t, $taxonomy );
                }
            }

            if ( ! $term || is_wp_error( $term ) ) {
                // include as-is
                $filtered[] = $t;
                continue;
            }

            $depth = count( get_ancestors( $term->term_id, $taxonomy ) );
            if ( $depth <= $max ) {
                $filtered[] = $term->term_id;
            } else {
                // store a transient to show notice to user after save
                /* translators: %1$d: max allowed depth, %2$s: taxonomy name */
                set_transient( 'tdc_removed_terms_' . get_current_user_id(), sprintf( __( 'Removed terms deeper than max depth (%1$d) for taxonomy %2$s.', 'taxonomy-depth-control' ), $max, esc_html( $taxonomy ) ), 30 );
            }
        }

        return $filtered;
    }

    public function admin_notices() {
        settings_errors( 'tdc' );

        $t = get_transient( 'tdc_removed_terms_' . get_current_user_id() );
        if ( $t ) {
            delete_transient( 'tdc_removed_terms_' . get_current_user_id() );
            echo '<div class="notice notice-warning is-dismissible"><p>' . esc_html( $t ) . '</p></div>';
        }
    }
}

TDC_Plugin::init();