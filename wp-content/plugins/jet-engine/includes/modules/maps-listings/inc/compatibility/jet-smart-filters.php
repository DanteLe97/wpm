<?php
namespace Jet_Engine\Modules\Maps_Listings\Compatibility;

class Jet_Smart_Filters {

	/**
	 * @var string JetSmartFilters request signature; captured before verification, as it is deleted after the request
	 */
	private $signature = '';

	public function __construct() {
		add_action( 'jet-smart-filters/render/ajax/before', array( $this, 'save_signature' ) );
		add_filter( 'jet-smart-filters/render/ajax/verify-signature', array( $this, 'check_signature' ) );
	}

	public function save_signature() {
		if ( ! empty( $_REQUEST['settings']['jsf_signature'] ) ) {
			$this->signature = $_REQUEST['settings']['jsf_signature'];
		}
	}

	public function check_signature( $result ) {

		if ( empty( $this->signature ) || empty( $_REQUEST['settings'] ) ) {
			return $result;
		}

		if ( false === strpos( $_REQUEST['provider'] ?? '', 'jet-engine-maps' ) ) {
			return $result;
		}

		$settings = $_REQUEST['settings'];

		unset( $settings['jsf_signature'] );

		$multiple_markers = $settings['multiple_markers'];

		foreach ( $multiple_markers as $i => $marker ) {
			foreach ( $marker as $prop => $value ) {
				if ( is_string( $value ) ) {
					$multiple_markers[ $i ][ $prop ] = stripslashes( $value );
				}
			}
		}

		$settings['multiple_markers'] = $multiple_markers;

		if ( ! empty( $settings['marker_icon'] ) && is_string( $settings['marker_icon'] ) ) {
			$settings['marker_icon'] = stripslashes( $settings['marker_icon'] );
		}
		
		$check_signature = jet_smart_filters()->render->create_signature( $settings );

		$result = $check_signature === $this->signature;

		return $result;
	}

}
