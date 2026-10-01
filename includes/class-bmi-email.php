<?php
// Voorkom direct aanroepen van dit bestand.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Regelt het tabblad "E-mail": eigen afzendernaam en -adres voor
 * uitgaande WordPress-e-mails (in plaats van "WordPress" / wordpress@domein).
 */
class BMI_Email {

	/**
	 * Opgeslagen instellingen voor dit tabblad.
	 *
	 * @var array
	 */
	private $options;

	public function __construct() {
		$this->options = get_option(
			'bmi_email_options',
			array(
				'sender_name'  => '',
				'sender_email' => '',
			)
		);

		if ( ! empty( $this->options['sender_name'] ) ) {
			add_filter( 'wp_mail_from_name', array( $this, 'filter_from_name' ) );
		}

		if ( ! empty( $this->options['sender_email'] ) ) {
			add_filter( 'wp_mail_from', array( $this, 'filter_from_email' ) );
		}
	}

	/**
	 * @return string
	 */
	public function filter_from_name() {
		return $this->options['sender_name'];
	}

	/**
	 * @return string
	 */
	public function filter_from_email() {
		return sanitize_email( $this->options['sender_email'] );
	}
}
