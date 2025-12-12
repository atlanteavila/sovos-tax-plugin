<?php
/**
 * Mail.
 * 
 * @since   1.0.0
 * @author  Built Mighty
 */

// Namespace.
namespace BuiltMighty\WOO_SOVOS\Utility\Mail;

// Define HTML headers.
define( 'HTML_EMAIL_HEADERS', [ 'Content-Type: text/html; charset=UTF-8' ] );

class Woo_Sovos_Mail {
    
    /**
     * Parameters.
     */
    public $mail;
    public $headers;

    /**
     * Construct.
     */
    public function __construct() {

        // Get mailer.
        $this->headers = [ 'Content-Type: text/html; charset=UTF-8' ];

    }

    /**
     * Send.
     */
    public function send( $email, $subject, $heading, $message, $attachment = NULL ) {

        // Get woocommerce mailer from instance
        $mailer = WC()->mailer();

        // Wrap message using woocommerce html email template
        $wrapped_message = $mailer->wrap_message($heading, $message);

        // Create new WC_Email instance
        $wc_email = new WC_Email;

        // Style the wrapped message with woocommerce inline styles
        $html_message = $wc_email->style_inline($wrapped_message);

        // Send the email using wordpress mail function
        wp_mail( $email, $subject, $html_message, HTML_EMAIL_HEADERS, $attachment );

    }

}