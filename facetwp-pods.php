<?php
/**
 * Plugin Name: Pods Integration for FacetWP
 * Plugin URI: https://github.com/pods-framework/facetwp-pods
 * Description: Allow FacetWP to properly utilize data created and stored by the Pods Framework.
 * Version: 1.0.0
 * Author: Matt Gibbs, Scott Kingsley Clark, Jeremy Pry (WebDevStudios)
 * License: GPL2
 */

// Prevent direct access to this file
if ( ! defined( 'ABSPATH' ) ) {
	die( "You can't do anything by accessing this file directly." );
}

class FacetWP_Integration_Pods {

	/**
	 * @var FacetWP_Integration_Pods
	 */
	protected static $instance = null;

	private function __construct() {
		
		add_action( 'plugins_loaded', array( $this, 'setup_hooks' ) );
		
	}

	/**
	 * @return FacetWP_Integration_Pods
	 */
	public static function instance() {

		if ( is_null( self::$instance ) ) {
			self::$instance = new self();
		}

		return self::$instance;

	}

	/**
	 * Hook class methods to FacetWP hooks.
	 *
	 * @since 1.0.0
	 */
	public function setup_hooks() {

		// If we don't have Pods, then there's nothing to do.
		if ( ! defined( 'PODS_VERSION' ) ) {
			return;
		}

		// Add Pods fields to the Data Sources dropdown
		add_filter( 'facetwp_facet_sources', array( $this, 'facet_sources' ) );

		// Pods field handler
		add_filter( 'facetwp_indexer_post_facet', array( $this, 'indexer_post_facet' ), 10, 2 );

		// Text fields that should be indexed
		add_filter( 'facetwp_pods_skip_index_text', array( $this, 'text_field_exceptions' ), 10, 2 );

		// Special handling for time/date fields
		add_filter( 'facetwp_pods_default_index', array( $this, 'time_date_indexing' ), 10, 3 );

	}


	/**
	 * Add Pods fields to the Data Sources dropdown.
	 *
	 * @since 1.0.0
	 *
	 * @param array $sources The current set of data sources.
	 * @return array The updated set of data sources.
	 */
	public function facet_sources( $sources ) {

		$sources['pods'] = array(
			'label'   => 'Pods',
			'choices' => array(),
		);

		// Get every Pods registered field as an array
		$fields = $this->get_pods_fields();
		
		foreach ( $fields as $field ) {
			// The Field ID string is used later to determine the Pod name and Field name
			$field_id_string = "pods/{$field['pod_name']}/{$field['name']}";

			$sources['pods']['choices'][ $field_id_string ] = $field['label'];
		}

		return $sources;

	}

	/**
	 * Index Pods field data
	 *
	 * @param bool $return
	 * @param array $params
	 *
	 * @return bool
	 */
	public function indexer_post_facet( $return, $params ) {

		$defaults = $params['defaults'];
		$facet    = $params['facet'];

		/**
		 * Filter to enable debugging.
		 *
		 * @since 1.0.0
		 *
		 * @param bool $debugging Whether to enable debugging. Defaults to false./
		 */
		if ( apply_filters( 'facetwp_pods_debugging', false ) ) {
			error_log( "Param info: " . print_r( $params, true ) . PHP_EOL, 3, WP_CONTENT_DIR . '/facet.log' );
		}

		// Split up the facet source
		$source = explode( '/', $facet['source'] );

		// Maybe return early. Includes class check, just in case
		if ( 'pods' !== $source[0] || count( $source ) < 3 ) {
			return $return;
		}

		// Initial var setup
		$pod_name   = $source[1];
		$field_name = $source[2];

		// Make sure the pod exists
		$pods = pods( $pod_name );
		
		if ( ! $pods || $pods->is_valid() ) {
			return $return;
		}

		// Make sure the field exists
		$field = $pods->fields( $field_name );
		
		if ( ! $field ) {
			return $return;
		}

		$field_type = $field['type']

		/**
		 * Filter the Pods field types that do not need to be indexed by FacetWP.
		 *
		 * @since 1.0.0
		 *
		 * @param array $fields Array of field types that do not need to be indexed.
		 */
		$skip_index = apply_filters( 'facetwp_pods_skip_index_field_types', array( 'html' ) );
		
		if ( in_array( $field_type, $skip_index ) ) {
			return true;
		}

		/**
		 * Filter to skip indexing text fields.
		 *
		 * By default, skip indexing text fields, because data is likely to be unique. This filter provides access
		 * to the field type, so that more granular control can be achieved.
		 *
		 * @since 1.0.0
		 *
		 * @param bool   $skip       Whether to skip indexing text fields. Default: true.
		 * @param string $field_type The field type.
		 */
		 // @todo Fix this
		/*$skip_index_text = apply_filters( 'facetwp_pods_skip_index_text', true, $field_type );
		
		if ( false !== strpos( $field_type, 'text' ) && $skip_index_text ) {
			return false;
		}*/

		/**
		 * Filter to skip indexing WYSIWYG fields.
		 *
		 * Similar to text fields, skip indexing by default because data is likely to be unique.
		 *
		 * @since 1.0.0
		 *
		 * @param bool $skip Whether to skip indexing WYSIWYG fields.
		 */
		 // @todo Fix this
		/*$skip_index_wysiwyg = apply_filters( 'facetwp_pods_skip_index_wysiwyg', true );
		
		if ( 'wysiwyg' == $field_type && $skip_index_wysiwyg ) {
			return false;
		}*/

		// Checkboxes are either on or off. Only index the "on" value.
		// @todo Fix this
		/*if ( 'checkbox' == $field_type ) {
			if ( 'on' == $field->value() ) {
				$this->index_field( $field, $defaults );
			} else {
				return true;
			}
		}*/

		/**
		 * Filter whether to do the default indexing.
		 *
		 * @since 1.0.0
		 *
		 * @param bool                     $index    Whether to use the default indexing.
		 * @param array                    $field    The Pods field array.
		 * @param array                    $defaults The array of defaults.
		 * @param FacetWP_Integration_Pods $obj      The current class object.
		 */
		$default_index = apply_filters( 'facetwp_pods_default_index', true, $field, $defaults, $this );

		if ( $default_index ) {
			$this->index_field_values( $field, $defaults );
		}

		return true;

	}

	/**
	 * Index a field based on the field object settings.
	 *
	 * @since 1.0.0
	 *
	 * @param array  $field    Field array.
	 * @param array  $defaults Array of default values.
	 */
	public function index_field( $field, $defaults ) {

 		// @todo Fix this
		$index = array(
			'facet_value'         => $field['name'],
			'facet_display_value' => $field['label'],
		);

		$this->index_row( $index, $defaults );

	}

	/**
	 * Index a field based on the stored value(s).
	 *
	 * @since 1.0.0
	 *
	 * @param array  $field    Field object.
	 * @param array  $defaults Array of default values.
	 */
	public function index_field_values( $field, $defaults ) {

		$index  = array();
		$values = (array) $field; // @todo Fix this

		foreach ( $values as $value ) {
			if ( empty( $value ) ) {
				continue;
			}

			$index[] = array(
				'facet_value'         => $value,
				'facet_display_value' => $field['name'],
			);
		}

		$this->index_multiple( $index, $defaults );

	}

	/**
	 * Index a single value.
	 *
	 * @since 1.0.0
	 *
	 * @param array $value    The array of values to index. Available keys in the array are: post_id, facet_name,
	 *                        facet_source, facet_value, facet_display_value, term_id, parent_id, and depth. For more
	 *                        information, see @link https://facetwp.com/documentation/facetwp_index_row/
	 * @param array $defaults Default values to use when indexing.
	 */
	public function index_row( $value, $defaults ) {

		FWP()->indexer->index_row( wp_parse_args( $value, $defaults ) );

	}

	/**
	 * Helper function to index an array of values.
	 *
	 * @since 1.0.0
	 *
	 * @param array $values   Multidimensional array of values to index.
	 * @param array $defaults Default values to use when indexing.
	 */
	public function index_multiple( $values, $defaults ) {

		// Loop through each value and index it
		foreach ( $values as $value ) {
			$this->index_row( $value, $defaults );
		}

	}

	/**
	 * Filter the text fields that should be skipped.
	 *
	 * This also serves as an example for how to use the 'facetwp_pods_skip_index_text' filter.
	 *
	 * @since 1.0.0
	 *
	 * @param bool   $index      Whether to skip indexing this field.
	 * @param string $field_type The type of field.
	 *
	 * @return bool
	 */
	public function text_field_exceptions( $index, $field_type ) {

		return $index;
		
		// @todo Fix this
		$exception = array(
			'text_date',
			'text_time',
			'text_date_timestamp',
			'text_datetime_timestamp',
			'text_datetime_timestamp_timezone',
		);

		if ( in_array( $field_type, $exception ) ) {
			return false;
		}

		return $index;

	}

	/**
	 * Handle indexing the various date/time fields.
	 *
	 * This method also serves as an example of the 'facetwp_pods_default_index' filter, although it should be noted
	 * that since it is part of the class, it does not use the $obj class object, but makes method calls directly.
	 * For use outside of this class, be sure to use $obj->index_row() or $obj->index_multiple().
	 *
	 * @since 1.0.0
	 *
	 * @param bool   $filter   Continue with normal indexing.
	 * @param array  $field    The field array.
	 * @param array  $defaults Array of default data.
	 *
	 * @return bool Whether to continue with the normal indexing.
	 */
	public function time_date_indexing( $filter, $field, $defaults ) {

		// @todo Fix this
		$date_format = 'Y-m-d';
		$extended_format = "{$date_format} H:i:s";
		$index = array(
			'facet_display_value' => $field['name'],
		);

		// Check for special field types
		if ( 'text_data' == $field->type() ) {
			$index['facet_value'] = date( $date_format, strtotime( $field->value() ) );
			$this->index_row( $index, $defaults );

			return false;
		} elseif ( 'text_date_timestamp' == $field->type() ) {
			$index['facet_value'] = date( $date_format, $field->value() );
			$this->index_row( $index, $defaults );

			return false;
		} elseif ( 'text_datetime_timestamp' == $field->type() ) {
			$index['facet_value'] = date( $extended_format, $field->value() );
			$this->index_row( $index, $defaults );

			return false;
		} elseif ( 'text_datetime_timestamp_timezone' == $field->type() ) {
			$value = maybe_unserialize( $field->value() );
			if ( $value instanceof DateTime ) {
				$index['facet_value'] = $value->format( $extended_format );
				$this->index_row( $index, $defaults );

				return false;
			}
		}

		return $filter;
	}

	/**
	 * Get registered Pods fields.
	 *
	 * @return array Multidimensional array of field data. Each array item contains 'id', 'label',
	 *               and 'metabox_id' keys.
	 */
	protected function get_pods_fields() {
		
		$return = array();

		return $return;
		
		// @todo Fix this
		$boxes  = CMB2_Boxes::get_all();

		foreach ( $boxes as $cmb ) {
			// Secret override method to skip indexing a metabox's fields
			if ( $cmb->prop( 'no_facetwp_index', false ) ) {
				continue;
			}

			/**
			 * Filter to skip metaboxes with no default hookup.
			 *
			 * Typically "hookup" => false is used by metaboxes that are on option pages, or are dispalyed on the front
			 * end.
			 *
			 * @since 1.0.0
			 *
			 * @param bool $skip_false_hookup Whether to skip metaboxes with hookup => false.
			 */
			$skip_false_hookup = apply_filters( 'facetwp_pods_skip_false_hookup', true );

			if ( $skip_false_hookup && false === $cmb->prop( 'hookup' ) ) {
				continue;
			}

			$fields = $cmb->prop( 'fields', array() );

			foreach ( $fields as $field ) {

				/**
				 * Filter to skip indexing hidden fields.
				 *
				 * @since 1.0.0
				 *
				 * @param bool $skip_hidden_fields Whether to skip indexing hidden fields.
				 */
				$skip_hidden_fields = apply_filters( 'facetwp_pods_skip_hidden_fields', true );

				if ( $skip_hidden_fields && 'hidden' == $field['type'] ) {
					continue;
				}

				$return[] = array(
					'id'         => $field['id'],
					'label'      => isset( $field['name'] ) ? $field['name'] : $field['id'],
					'metabox_id' => $cmb->cmb_id,
				);
			}
		}

		return $return;

	}
}

FacetWP_Integration_Pods::instance();
