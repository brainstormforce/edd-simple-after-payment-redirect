<?php
/**
 * Plugin Name:     EDD Redirect after payment
 * Plugin URI:      https://www.nikhilchavan.com/
 * Description:     Redirect to a custom URL after successful purchase.
 * Author:          Brainstorm Force
 * Author URI:      https://www.brainstormforce.com/
 * Text Domain:     edd-simple-after-payment-redirect
 * Domain Path:     /languages
 * Version:         1.0.2
 *
 * PHP version 7
 *
 * @category PHP
 * @package  Edd_Simple_After_Payment_Redirect
 * @author   Display Name <nikhilc@bsf.io>
 * @license  GPLv2 or later https://brainstormforce.com
 * @link     https://brainstormforce.com
 */

/**
 * Exit if accessed directly.
 */
defined( 'ABSPATH' ) || exit;

/**
 * Edd_Simple_After_Payment_Redirect
 *
 * @category PHP
 * @package  Edd_Simple_After_Payment_Redirect
 * @author   Display Name <nikhilc@bsf.io>
 * @license  GPLv2 or later https://brainstormforce.com
 * @link     https://brainstormforce.com
 */
class Edd_Simple_After_Payment_Redirect {

	/**
	 * Member Variable
	 *
	 * @var instance
	 */
	private static $instance = null;


	/**
	 * Redirect
	 *
	 * @var bool
	 */
	private $redirect = false;


	/**
	 * Instance
	 *
	 * @return object
	 */
	public static function instance() {
		if ( ! isset( self::$instance ) ) {
			self::$instance = new self();
		}

		return self::$instance;
	}

	/**
	 * __construct
	 *
	 * @return void
	 */
	private function __construct() {
		add_action( 'edd_complete_purchase', array( $this, 'process_standard_payment' ) );
		add_filter( 'edd_payment_confirm_paypal', array( $this, 'process_paypal_standard' ) );
		add_action( 'template_redirect', array( $this, 'process_offsite_payment' ) );
		add_action( 'edd_meta_box_fields', array( $this, 'edd_external_product_render_field' ), 90 );
		add_filter( 'edd_metabox_fields_save', array( $this, 'edd_external_product_save' ) );
		add_filter( 'edd_metabox_save__edd_after_payment_redirect', array( $this, 'edd_external_product_metabox_save' ) );
	}

	/**
	 * Process_standard_payment
	 *
	 * @param mixed $payment_id get cart items from payment ID.
	 *
	 * @return object
	 */
	public function process_standard_payment( $payment_id ) {

		// get cart items from payment ID.
		$cart_items = edd_get_payment_meta_cart_details( $payment_id );

		// get the download ID from cart items array.
		if ( $cart_items ) {
			foreach ( $cart_items as $download ) {
				$download_id = $download['id'];
			}
		}

		// return if more than one item exists in cart. The default purchase confirmation will be used.
		if ( count( $cart_items ) > 1 ) {
			return;
		}

		$payment_status = edd_get_payment_status( absint( $payment_id ), true );

		if ( 'Pending' === $payment_status || 'private' === $payment_status ) {
			return false;
		}

		// redirect by default to the normal EDD success page.
		$this->redirect = apply_filters( 'edd_csr_redirect', get_permalink( edd_get_option( 'success_page' ) ), $download_id );
		$this->redirect = get_post_meta( $download_id, '_edd_after_payment_redirect', true );

		if ( false === $this->redirect || '' === $this->redirect ) {
			return;
		}

		$customer_id = edd_get_payment_customer_id( $payment_id );
		$customer    = new EDD_Customer( $customer_id );

		$this->redirect = add_query_arg(
			array(
				'username'   => $customer->name,
				'useremail'  => $customer->email,
				'payment_id' => $payment_id,
			),
			$this->redirect
		);

		add_filter( 'edd_get_success_page_uri', array( $this, 'get_redirect_url' ) );
		add_filter( 'edd_success_page_url', array( $this, 'get_redirect_url' ) );
	}

	/**
	 * Process_paypal_standard
	 *
	 * @param mixed $content return if no payment-id query string or purchase session.
	 *
	 * @return string
	 */
	public function process_paypal_standard( $content ) {
		// return if no payment-id query string or purchase session.
		if ( ! isset( $_GET['payment-id'] ) && ! edd_get_purchase_session() ) {
			return $content;
		}

		// get payment ID from the query string.
		$payment_id = isset( $_GET['payment-id'] ) ? absint( $_GET['payment-id'] ) : false;

		// no query string, get the payment ID from the purchase session.
		if ( ! $payment_id ) {
			$session    = edd_get_purchase_session();
			$payment_id = edd_get_purchase_id_by_key( $session['purchase_key'] );
		}

		// get cart items from payment ID.
		$cart_items = edd_get_payment_meta_cart_details( $payment_id );

		// get the download ID from cart items array.
		if ( $cart_items ) {
			foreach ( $cart_items as $download ) {
				$download_id = $download['id'];
			}
		}

		// return if more than one item exists in cart. The default purchase confirmation will be used.
		if ( count( $cart_items ) > 1 ) {
			return;
		}

		// redirect by default to the normal EDD success page.
		$this->redirect = apply_filters( 'edd_csr_redirect', get_permalink( edd_get_option( 'success_page' ) ), $download_id );
		$this->redirect = get_post_meta( $download_id, '_edd_after_payment_redirect', true );

		if ( false === $this->redirect || '' === $this->redirect ) {
			return;
		}

		$customer_id = edd_get_payment_customer_id( $payment_id );
		$customer    = new EDD_Customer( $customer_id );

		$this->redirect = add_query_arg(
			array(
				'username'  => $customer->name,
				'useremail' => $customer->email,
			),
			$this->redirect
		);

		// if payment is pending or private (buy now button behavior), load the payment processing template.
		if ( $payment && ( 'pending' === $payment->post_status || 'private' === $payment->post_status ) ) {

			return false;
		} elseif ( $payment && 'publish' === $payment->post_status ) {

			// payment is complete, it can redirect straight away.
			wp_safe_redirect( $this->get_redirect_url(), 301 );
			exit;
		}

		return $content;
	}

	/**
	 * Payment processing template
	 * The idea here is to give the website enough time to receive instructions from PayPal as per https://github.com/easydigitaldownloads/Easy-Digital-Downloads/issues/1839
	 * You should always add the neccessary checks on the redirected page if you are going to show the customer sensitive information
	 *
	 * Similar to EDD's /templates/payment-processing.php file
	 *
	 * @credits - https://github.com/easydigitaldownloads/edd-conditional-success-redirects/blob/master/includes/class-process-redirects.php#L68-L89
	 *
	 * @since  1.0.4
	 * @return void
	 */
	public function payment_processing() {
		$redirect = $this->get_redirect_url();
		?>
		<div id="edd-payment-processing">
			<p><?php printf( wp_kses_post( 'Your purchase is processing. This page will reload automatically in 8 seconds. If it does not, click <a href="%s">here</a>.', 'edd' ), esc_url( $redirect ) ); ?>
			<span class="edd-cart-ajax"><i class="edd-icon-spinner edd-icon-spin"></i></span>
			<script type="text/javascript">setTimeout(function(){ window.location = '<?php echo esc_url( $redirect ); ?>'; }, 8000);</script>
		</div>

		<?php
	}

	/**
	 * Process_offsite_payment
	 *
	 * @return void
	 */
	public function process_offsite_payment() {
		// check if we have query string and on purchase confirmation page.
		if ( ! is_page( edd_get_option( 'success_page' ) ) ) {
			return;
		}

		// get the purchase session.
		$purchase_session = edd_get_purchase_session();

		if ( ! $purchase_session ) {
			return false;
		}

		$cart_items = $purchase_session['downloads'];

		// get the download ID from cart items array.
		if ( $cart_items ) {
			foreach ( $cart_items as $download ) {
				$download_id = $download['id'];
			}
		}

		// get payment ID from the query string.
		$payment_id = isset( $_GET['payment_id'] ) ? absint( $_GET['payment_id'] ) : false;

		// no query string, get the payment ID from the purchase session.
		if ( ! $payment_id ) {
			$session    = edd_get_purchase_session();
			$payment_id = edd_get_purchase_id_by_key( $session['purchase_key'] );
		}

		$payment_status = edd_get_payment_status( $payment_id, true );

		if ( 'Pending' === $payment_status || 'private' === $payment_status ) {
			return false;
		}

		// return if more than one item exists in cart. The default purchase confirmation will be used.
		if ( count( $cart_items ) > 1 ) {
			return false;
		}

		// redirect by default to the normal EDD success page.
		$this->redirect = apply_filters( 'edd_csr_redirect', get_permalink( edd_get_option( 'success_page' ) ), $download_id );
		$this->redirect = get_post_meta( $download_id, '_edd_after_payment_redirect', true );

		$customer_id = edd_get_payment_customer_id( $payment_id );
		$customer    = new EDD_Customer( $customer_id );

		$this->redirect = add_query_arg(
			array(
				'username'  => $customer->name,
				'useremail' => $customer->email,
			),
			$this->redirect
		);

		// normal offsite redirect.
		if ( isset( $_GET['payment-confirmation'] ) && $_GET['payment-confirmation'] ) {
			// return if using PayPal express. Customer needs to "confirm" the payment first before redirecting.
			// also redirects if paypal standard was used. It has its own processing function.
			if ( 'paypalexpress' === $_GET['payment-confirmation'] || 'paypal' === $_GET['payment-confirmation'] ) {
				return;
			}
			// redirect.
			wp_safe_redirect( $this->get_redirect_url(), 301 );
			exit;
		}
		// PayPal Express.
		// Customer must "confirm" purchase.
		if ( isset( $_GET['token'] ) && $_GET['token'] && ! isset( $_GET['payment-confirmation'] ) ) {

			// redirect.
			wp_safe_redirect( $this->get_redirect_url(), 301 );
			exit;
		}
	}

	/**
	 * Get_redirect_url
	 *
	 * @return object
	 */
	public function get_redirect_url() {
		return $this->redirect;
	}

	/**
	 * After Purchase redirect URL Field.
	 *
	 * Adds field do the EDD Downloads meta box for specifying the "After Purchase redirect URL".
	 *
	 * @param integer $post_id Download (Post) ID.
	 *
	 * @since 1.0.0
	 *
	 * @return void
	 */
	public function edd_external_product_render_field( $post_id ) {
		$edd_after_payment_redirect = get_post_meta( $post_id, '_edd_after_payment_redirect', true );
		?>
		<p><strong><?php esc_html_e( 'After successful purchase redirect:', 'edd-simple-after-payment-redirect' ); ?></strong></p>
		<label for="edd_after_payment_redirect">
			<input type="text" name="_edd_after_payment_redirect" id="edd_after_payment_redirect" value="<?php echo esc_attr( $edd_after_payment_redirect ); ?>" size="80" placeholder="http://"/>
			<br/><?php esc_html_e( 'The external URL (including http://) to redirect to a URL after successful purchase. Leave blank if the product redirect not required.', 'edd-simple-after-payment-redirect' ); ?>
		</label>
		<?php
	}

	/**
	 * Add the _edd_after_payment_redirect field to the list of saved product fields.
	 *
	 * @param array $fields The default product fields list.
	 *
	 * @since 1.0.0
	 *
	 * @return array         The updated product fields list.
	 */
	public function edd_external_product_save( $fields ) {
		// Add our field.
		$fields[] = '_edd_after_payment_redirect';
		// Return the fields array.
		return $fields;
	}

	/**
	 * Sanitize metabox field to only accept URLs.
	 *
	 * @param mixed $new Convert to raw URL to save into wp_postmeta table.
	 *
	 * @since 1.0.0
	 *
	 * @return object
	 */
	public function edd_external_product_metabox_save( $new ) {
		// Convert to raw URL to save into wp_postmeta table.
		$new = esc_url_raw( $_POST['_edd_after_payment_redirect'] );
		// Return URL.
		return $new;
	}


}

add_action( 'plugins_loaded', 'Edd_Simple_After_Payment_Redirect::instance' );

