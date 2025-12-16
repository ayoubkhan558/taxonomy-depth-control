<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

if ( ! class_exists( 'TDC_Walker_Checklist' ) ) :
    class TDC_Walker_Checklist extends Walker_Category_Checklist {
        public function start_el( &$output, $term, $depth = 0, $args = array(), $id = 0 ) {
            $term_id = $term->term_id;

            $args = (array) $args;
            $max_depth = 0;
            if ( isset( $args['taxonomy'] ) ) {
                $settings = get_option( 'tdc_settings', array() );
                if ( isset( $settings[ $args['taxonomy'] ] ) ) {
                    $max_depth = intval( $settings[ $args['taxonomy'] ] );
                }
            }

            // reuse default output but inject data-depth on the li
            $depth_attr = ' data-depth="' . intval( $depth ) . '"';

            $term_name = apply_filters( 'the_category', $term->name );

            $class = 'category-item category-item-' . $term_id;
            $output .= "<li id='category-$term_id' class='$class' $depth_attr>";
            $output .= '<label class="selectit"><input value="' . esc_attr( $term_id ) . '" type="checkbox" name="tax_input[' . esc_attr( $args['taxonomy'] ) . '][]" id="in-' . esc_attr( $args['taxonomy'] ) . '-' . esc_attr( $term_id ) . '" /> ' . esc_html( $term_name ) . '</label>';
            $output .= "</li>";
        }
    }
endif;
