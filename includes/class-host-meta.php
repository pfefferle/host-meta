<?php
/**
 * the host-meta class
 *
 * @author Matthias Pfefferle
 */
class Host_Meta {
	/**
	 * adds some query vars
	 *
	 * @param array $vars
	 * @return array
	 */
	public static function query_vars( $vars ) {
		$vars[] = 'well-known';
		$vars[] = 'format';

		return $vars;
	}

	/**
	 * Add rewrite rules
	 */
	public static function rewrite_rules() {
		add_rewrite_rule( '^.well-known/host-meta.json', 'index.php?well-known=host-meta.json', 'top' );
		add_rewrite_rule( '^.well-known/host-meta', 'index.php?well-known=host-meta', 'top' );
	}

	/**
	 * renders the output-file
	 *
	 * @param array $wp
	 */
	public static function parse_request( $wp ) {
		// check if "host-meta" param exists
		if ( ! array_key_exists( 'well-known', $wp->query_vars ) ) {
			return;
		}

		if ( 'host-meta' == $wp->query_vars['well-known'] ) {
			$format = 'xrd';
		} elseif ( 'host-meta.json' == $wp->query_vars['well-known'] ) {
			$format = 'jrd';
		} else {
			return;
		}

		$host_meta = apply_filters( 'host_meta', array(), $wp->query_vars );

		do_action( 'host_meta_render', $format, $host_meta, $wp->query_vars );
		do_action( "host_meta_render_{$format}", $host_meta, $wp->query_vars );
	}

	/**
	 * renders the host-meta file in xml
	 *
	 * @param array $host_meta
	 */
	public static function render_xrd( $host_meta ) {
		header( 'Access-Control-Allow-Origin: *' );
		header( 'Content-Type: application/xrd+xml; charset=' . get_bloginfo( 'charset' ), true );
		echo '<?xml version="1.0" encoding="UTF-8"?>' . PHP_EOL;
		?>
<XRD xmlns="http://docs.oasis-open.org/ns/xri/xrd-1.0<?php do_action( 'host_meta_ns' ); ?>">
		<?php
		echo self::jrd_to_xrd( $host_meta );
		do_action( 'host_meta_xrd' );
		?>
</XRD>
		<?php
		exit;
	}

	/**
	 * renders the host-meta file in json
	 *
	 * @param array $host_meta
	 */
	public static function render_jrd( $host_meta ) {
		header( 'Access-Control-Allow-Origin: *' );
		header( 'Content-Type: application/json; charset=' . get_bloginfo( 'charset' ), true );

		echo wp_json_encode( $host_meta );
		exit;
	}

	/**
	 * generates the host-meta base array (and activate filter)
	 *
	 * @param array $host_meta
	 * @return array
	 */
	public static function generate_default_content( $host_meta ) {
		$host_meta = array();
		// add subject
		$host_meta['subject'] = site_url();

		// add feeds
		$host_meta['links'] = array(
			array(
				'rel'  => 'alternate',
				'href' => get_bloginfo( 'atom_url' ),
				'type' => 'application/atom+xml',
			),
			array(
				'rel'  => 'alternate',
				'href' => get_bloginfo( 'rss2_url' ),
				'type' => 'application/rss+xml',
			),
			array(
				'rel'  => 'alternate',
				'href' => get_bloginfo( 'rdf_url' ),
				'type' => 'application/rdf+xml',
			),
		);

		// RSD discovery link
		$host_meta['links'][] = array(
			'rel' => 'EditURI',
			'href' => esc_url( site_url( 'xmlrpc.php?rsd', 'rpc' ) ),
			'type' => 'application/rsd+xml',
		);

		// add WordPress API
		if ( function_exists( 'get_rest_url' ) ) {
			$host_meta['links'][] = array(
				'rel' => 'https://api.w.org/',
				'href' => esc_url( get_rest_url() ),
			);
		}

		return $host_meta;
	}

	/**
	 * recursive helper to generade the xrd-xml from the jrd array
	 *
	 * @param string $host_meta
	 * @return string the genereated XRD file
	 */
	public static function jrd_to_xrd( $host_meta ) {
		$xrd = null;

		foreach ( $host_meta as $type => $content ) {
			// print subject
			if ( 'subject' == $type ) {
				$xrd .= '<Subject>' . esc_html( $content ) . '</Subject>';
				continue;
			}

			// print aliases
			if ( 'aliases' == $type ) {
				foreach ( $content as $uri ) {
					$xrd .= '<Alias>' . esc_url( $uri ) . '</Alias>';
				}
				continue;
			}

			// print properties
			if ( 'properties' == $type ) {
				foreach ( $content as $type => $uri ) {
					$xrd .= '<Property type="' . esc_attr( $type ) . '">' . esc_url( $uri ) . '</Property>';
				}
				continue;
			}

			// print titles
			if ( 'titles' == $type ) {
				foreach ( $content as $key => $value ) {
					if ( 'default' === $key ) {
						$xrd .= '<Title>' . esc_html( $value ) . '</Title>';
					} else {
						$xrd .= '<Title xml:lang="' . esc_attr( $key ) . '">' . esc_html( $value ) . '</Title>';
					}
				}
				continue;
			}

			// print links
			if ( 'links' == $type ) {
				foreach ( $content as $links ) {
					$temp = array();
					$cascaded = false;
					$xrd .= '<Link ';

					foreach ( $links as $key => $value ) {
						if ( is_array( $value ) ) {
							$temp[ $key ] = $value;
							$cascaded = true;
						} else {
							$xrd .= esc_attr( $key ) . '="' . esc_attr( $value ) . '" ';
						}
					}
					if ( $cascaded ) {
						$xrd .= '>';
						$xrd .= self::jrd_to_xrd( $temp );
						$xrd .= '</Link>';
					} else {
						$xrd .= ' />';
					}
				}

				continue;
			}
		}

		return $xrd;
	}
}
