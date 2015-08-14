<?php

if ( ! defined( 'ABSPATH' ) ) exit; // Exit if accessed directly

class TitanFrameworkOptionAjaxButton extends TitanFrameworkOption {

	private static $firstLoad = true;

	public $defaultSecondarySettings = array(
		'action' => 'custom_action',
		'label' => '',
		'class' => 'button-secondary',
		'wait_label' => '',
		'success_label' => '',
		'error_label' => '',
		'success_callback' => '',
		'error_callback' => '',
	);
	
	public function ajaxSecurityChecker() {
		if ( empty( $_POST['nonce'] ) ) {
			wp_send_json_error( __( 'Security check failed, please refresh the page and try again.', TF_I18NDOMAIN ) );
		}
		if ( ! wp_verify_nonce( $_POST['nonce'], 'tf-ajax-button' ) ) {
			wp_send_json_error( __( 'Security check failed, please refresh the page and try again.', TF_I18NDOMAIN ) );
		}
	}
	
	public function ajaxLastSuccess() {
		wp_send_json_success();
	}
	
	function __construct( $settings, $owner ) {
		parent::__construct( $settings, $owner );

		add_action( 'admin_head', array( __CLASS__, 'createAjaxScript' ) );

		// Adjust the settings
		foreach ( $this->defaultSecondarySettings as $key => $default ) {
			if ( ! is_array( $this->settings[ $key ] ) ) {
				$this->settings[ $key ] = array( $this->settings[ $key ] );
			}
		}
		
		while ( count( $this->settings['label'] ) < count( $this->settings['action'] ) ) {
			$this->settings['label'][] = $this->settings['label'][ count( $this->settings['label'] ) - 1 ];
		}
		while ( count( $this->settings['class'] ) < count( $this->settings['action'] ) ) {
			$this->settings['class'][] = 'button-secondary';
		}
		while ( count( $this->settings['wait_label'] ) < count( $this->settings['action'] ) ) {
			$this->settings['wait_label'][] = $this->settings['wait_label'][ count( $this->settings['wait_label'] ) - 1 ];
		}
		while ( count( $this->settings['error_label'] ) < count( $this->settings['action'] ) ) {
			$this->settings['error_label'][] = $this->settings['error_label'][ count( $this->settings['error_label'] ) - 1 ];
		}
		while ( count( $this->settings['success_label'] ) < count( $this->settings['action'] ) ) {
			$this->settings['success_label'][] = $this->settings['success_label'][ count( $this->settings['success_label'] ) - 1 ];
		}
		while ( count( $this->settings['success_callback'] ) < count( $this->settings['action'] ) ) {
			$this->settings['success_callback'][] = '';
		}
		while ( count( $this->settings['error_callback'] ) < count( $this->settings['action'] ) ) {
			$this->settings['error_callback'][] = __( 'Something went wrong', TF_I18NDOMAIN );
		}
		
		foreach ( $this->settings['label'] as $i => $label ) {
			if ( empty( $label ) ) {
				$this->settings['label'][ $i ] = __( 'Click me', TF_I18NDOMAIN );
			}
		}
		foreach ( $this->settings['wait_label'] as $i => $label ) {
			if ( empty( $label ) ) {
				$this->settings['wait_label'][ $i ] = __( 'Please wait...', TF_I18NDOMAIN );
			}
		}
		foreach ( $this->settings['error_label'] as $i => $label ) {
			if ( empty( $label ) ) {
				$this->settings['error_label'][ $i ] = $this->settings['label'][ $i ];
			}
		}
		foreach ( $this->settings['success_label'] as $i => $label ) {
			if ( empty( $label ) ) {
				$this->settings['success_label'][ $i ] = $this->settings['label'][ $i ];
			}
		}

		/**
		 * Create ajax handlers for security and last resort success returns
		 */
		foreach ( $this->settings['action'] as $i => $action ) {
			if ( ! empty( $action ) ) {
				add_action( 'wp_ajax_' . $action, array( $this, 'ajaxSecurityChecker'), 1 );
				add_action( 'wp_ajax_' . $action, array( $this, 'ajaxLastSuccess'), 99999 );
			}
		}
		
	}

	public function display() {
		$this->echoOptionHeader();
		
		foreach ( $this->settings['action'] as $i => $action ) {
			printf( '<button class="button %s" data-action="%s" data-label="%s" data-wait-label="%s" data-error-label="%s" data-success-label="%s" data-nonce="%s" data-success-callback="%s" data-error-callback="%s">%s</button>',
				$this->settings['class'][ $i ],
				esc_attr( $action ),
				esc_attr( $this->settings['label'][ $i ] ),
				esc_attr( $this->settings['wait_label'][ $i ] ),
				esc_attr( $this->settings['error_label'][ $i ] ),
				esc_attr( $this->settings['success_label'][ $i ] ),
				esc_attr( wp_create_nonce( 'tf-ajax-button' ) ),
				esc_attr( $this->settings['success_callback'][ $i ] ),
				esc_attr( $this->settings['error_callback'][ $i ] ),
				esc_attr( $this->settings['label'][ $i ] )
			);
		}
		
		$this->echoOptionFooter();
	}
	
	public static function createAjaxScript() {
		if ( ! self::$firstLoad ) {
			return;
		}
		self::$firstLoad = false;

		?>
		<script>
		jQuery(document).ready(function($) {
			"use strict";
			
			$('.form-table').on( 'click', '.tf-ajax-button .button', function( e ) {
				
				// Only perform one ajax at a time
				if ( typeof this.doingAjax === 'undefined' ) {
					this.doingAjax = false;
				}
				e.preventDefault();
				if ( this.doingAjax ) {
					return false;
				}
				this.doingAjax = true;
				
				// Perform the ajax call
				wp.ajax.send( $(this).attr('data-action'), {
					
					// Success callback
					success: function( successMessage ) {
						
						this.labelTimer = setTimeout(function() {
							$(this).text( $(this).attr('data-label') );
							this.labelTimer = undefined;
						}.bind(this), 3000 );
						
						$(this).text( successMessage || $(this).attr('data-success-label') );
						
						// Call the error callback
						if ( $(this).attr('data-success-callback') !== '' ) {
							if ( typeof window[ $(this).attr('data-success-callback') ] !== 'undefined' ) {
								window[ $(this).attr('data-success-callback') ]();
							}
						}
						this.doingAjax = false;
						
					}.bind(this),
					
					// Error callback
					error: function( errorMessage ) {
						this.labelTimer = setTimeout(function() {
							$(this).text( $(this).attr('data-label') );
							this.labelTimer = undefined;
						}.bind(this), 3000 );

						$(this).text( errorMessage || $(this).attr('data-error-label') );
						
						// Call the error callback
						if ( $(this).attr('data-error-callback') !== '' ) {
							if ( typeof window[ $(this).attr('data-error-callback') ] !== 'undefined' ) {
								window[ $(this).attr('data-error-callback') ]();
							}
						}
						this.doingAjax = false;
						
					}.bind(this),
					
					// Just pass the nonce
					data: {
						nonce: $(this).attr('data-nonce')
					}
					
				});
				
				// Clear the label timer
				if ( typeof this.labelTimer !== 'undefined' ) {
					clearTimeout( this.labelTimer );
					this.labelTimer = undefined;
				}
				$(this).text( $(this).attr('data-wait-label') );
				
				return false;
			} );
		});
		</script>
		<?php
	}
}