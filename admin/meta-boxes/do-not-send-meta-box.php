<?php

class SBE_Do_Not_Send_Meta_Box {
	public function __construct() {
		add_action( 'add_meta_boxes', array( $this, 'add_meta_box' ) );
		add_action( 'save_post', array( $this, 'save' ) );

		add_action( 'enqueue_block_editor_assets', function() {
			add_filter( 'get_user_metadata', array( $this, 'force_to_side_for_block_editor' ), 10, 5 );
		} );
	}

	public function add_meta_box() {
		$settings = incsub_sbe_get_settings();
		$screens = $settings['post_types'];

		foreach ( $screens as $screen ) {
			add_meta_box(
				'sbe-do-not-send',
				__( 'Subscribe By Email', INCSUB_SBE_LANG_DOMAIN ),
				array( $this, 'render' ),
				$screen,
				'side',
				'default'
			);

		}
	}

	public function render( $post ) {
		wp_nonce_field( 'sbe_do_not_send_save_data', 'sbe_do_not_send_nonce' );

		$value = get_post_meta( $post->ID, '_sbe_do_not_send', true );
		$disabled = disabled( get_post_status( $post->ID ) === 'publish' || get_post_meta( $post->ID, 'sbe_sent', true ), true, false );
		?>
			<input type="checkbox" id="sbe-do-not-send-checkbox" name="sbe-do-not-send" <?php checked( $value ); ?> <?php echo $disabled; ?>>
			<label for="sbe-do-not-send-checkbox"><?php _e( 'Do not send this post', INCSUB_SBE_LANG_DOMAIN ); ?></label><br/>
			<p class="description"><?php _e( 'Check this box if you don\'t want to send this post (once the post is published, you cannot change this option)', INCSUB_SBE_LANG_DOMAIN ); ?></p>
		<?php
	}

	public function save( $post_id ) {
		if ( ! isset( $_POST['sbe_do_not_send_nonce'] ) )
			return $post_id;

		$nonce = $_POST['sbe_do_not_send_nonce'];

		if ( ! wp_verify_nonce( $nonce, 'sbe_do_not_send_save_data' ) )
			return $post_id;

		if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE )
			return $post_id;

		$settings = incsub_sbe_get_settings();
		$screens = $settings['post_types'];
		if ( ! in_array( $_POST['post_type'], $screens ) )
			return $post_id;

		if ( ! isset( $_POST['sbe-do-not-send'] ) )
			return $post_id;

		update_post_meta( $post_id, '_sbe_do_not_send', true );
	}

	/**
	 * Force display of SBE metabox to the side for the Block Editor only.
	 *
	 * @param  mixed  $retval    Null by default.
	 * @param  int    $object_id User ID
	 * @param  string $meta_key  User's meta key to check.
	 * @param  bool   $single    Whether to return the first result of the meta.
	 * @param  string $meta_type Metadata type.
	 * @return mixed
	 */
	public function force_to_side_for_block_editor( $retval, $object_id, $meta_key, $single, $meta_type ) {
		// If not checking our metabox order option, bail.
		if ( 'meta-box-order_post' !== $meta_key ) {
			return $retval;
		}

		/** The following logic copies get_metadata_raw() unless where stated */

		$meta_cache = wp_cache_get( $object_id, $meta_type . '_meta' );
	
		if ( ! $meta_cache ) {
			$meta_cache = update_meta_cache( $meta_type, array( $object_id ) );
			if ( isset( $meta_cache[ $object_id ] ) ) {
				$meta_cache = $meta_cache[ $object_id ];
			} else {
				$meta_cache = null;
			}
		}
	
		if ( ! $meta_key ) {
			return $meta_cache;
		}
	
		if ( isset( $meta_cache[ $meta_key ] ) ) {
			if ( $single ) {
				return maybe_unserialize( $meta_cache[ $meta_key ][0] );

			// MOD: Enforce SBE metabox to the side here.
			} else {
				$meta_cache = array_map( 'maybe_unserialize', $meta_cache[ $meta_key ] );

				$sbe = 'sbe-do-not-send';

				if ( ! empty( $meta_cache['normal'] ) && false !== strpos( $meta_cache['normal'], $sbe ) ) {
					// Remove 'sbe-do-not-send' from normal metabox.
					$meta_cache['normal'] = array_diff( str_getcsv( $meta_cache['normal'] ), [ $sbe ] );
					$meta_cache['normal'] = implode( ',', $meta_cache['normal'] );

					// Now add 'sbe-do-not-send' to the side.
					$meta_cache['side'] .= ',' . $sbe;
				}

				return $meta_cache;
			}
		}
	
		return null;
	}
}

new SBE_Do_Not_Send_Meta_Box();

