<?php
/**
 * Plugin Name:     EDD Redirect after payment
 * Plugin URI:      https://www.nikhilchavan.com/
 * Description:     Redirect to a custom URL after successful purchase.
 * Author:          Brainstorm Force
 * Author URI:      https://www.brainstormforce.com/
 * Text Domain:     edd-simple-after-payment-redirect
 * Domain Path:     /languages
 * Version:         1.0.0
 *
 * @package         Edd_Simple_After_Payment_Redirect
 */


defined( 'ABSPATH' ) or exit;

class Edd_Simple_After_Payment_Redirect {

	private static $_instance = null;

	private $redirect = false;

	public static function instance() {
		if ( ! isset( self::$_instance ) ) {
			self::$_instance = new self;
		}

		return self::$_instance;
	}

	private function __construct() {
		add_action( 'edd_complete_purchase', array( $this, 'process_standard_payment' ) );
		add_action( 'edd_meta_box_fields', array( $this, 'edd_external_product_render_field' ), 90 );
		add_filter( 'edd_metabox_fields_save', array( $this, 'edd_external_product_save' ) );
		add_filter( 'edd_metabox_save__edd_after_payment_redirect', array( $this, 'edd_external_product_metabox_save' ) );
	}

	public function process_standard_payment( $payment_id ) {

		// get cart items from payment ID
		$cart_items = edd_get_payment_meta_cart_details( $payment_id );

	 	// get the download ID from cart items array
	 	if ( $cart_items ) {
			foreach ( $cart_items as $download ) {
				 $download_id = $download['id'];
			}
		}

	 	// return if more than one item exists in cart. The default purchase confirmation will be used
		if ( count( $cart_items ) > 1 ) {
	 	 	return;
		}

	 	// redirect by default to the normal EDD success page
	 	$this->redirect = apply_filters( 'edd_csr_redirect', get_permalink( edd_get_option( 'success_page' ) ), $download_id );
	 	$this->redirect = get_post_meta( $download_id, '_edd_after_payment_redirect', true );

	 	if ( false == $this->redirect || '' == $this->redirect ) {
	 		return;
	 	}

		add_filter( 'edd_get_success_page_uri', array( $this, 'get_redirect_url' ) );
		add_filter( 'edd_success_page_url', array( $this, 'get_redirect_url' ) );
	}

	public function get_redirect_url() {
		return $this->redirect;
	}

	/**
	 * After Purchase redirect URL Field
	 *
	 * Adds field do the EDD Downloads meta box for specifying the "After Purchase redirect URL"
	 *
	 * @since 1.0.0
	 * @param integer $post_id Download (Post) ID
	 */
	function edd_external_product_render_field( $post_id ) {
		$edd_after_payment_redirect = get_post_meta( $post_id, '_edd_after_payment_redirect', true );
	?>
		<p><strong><?php _e( 'After successful purchase redirect:', 'edd-simple-after-payment-redirect' ); ?></strong></p>
		<label for="edd_after_payment_redirect">
			<input type="text" name="_edd_after_payment_redirect" id="edd_after_payment_redirect" value="<?php echo esc_attr( $edd_after_payment_redirect ); ?>" size="80" placeholder="http://"/>
			<br/><?php _e( 'The external URL (including http://) to redirect to a URL after successful purchase. Leave blank if the product redirect not required.', 'edd-simple-after-payment-redirect' ); ?>
		</label>
	<?php
	}

	/**
	 * Add the _edd_after_payment_redirect field to the list of saved product fields
	 *
	 * @since  1.0.0
	 *
	 * @param  array $fields The default product fields list
	 * @return array         The updated product fields list
	 */
	function edd_external_product_save( $fields ) {
		// Add our field
		$fields[] = '_edd_after_payment_redirect';
		// Return the fields array
		return $fields;
	}

	/**
	 * Sanitize metabox field to only accept URLs
	 *
	 * @since 1.0.0
	*/
	function edd_external_product_metabox_save( $new ) {
		// Convert to raw URL to save into wp_postmeta table
		$new = esc_url_raw( $_POST[ '_edd_after_payment_redirect' ] );
		// Return URL
		return $new;
	}


}

add_action( 'plugins_loaded', 'Edd_Simple_After_Payment_Redirect::instance' );

