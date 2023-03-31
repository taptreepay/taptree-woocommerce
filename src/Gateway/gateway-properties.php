<?php

defined('ABSPATH') || exit;

return array(
    'enabled' => array(
        'title' => __('Enable/Disable', 'woocommerce'),
        'type' => 'checkbox',
        'label' => __('Enable TapTree Payments', 'woocommerce'),
        'default' => 'no'
    ),
    /*'title' => array(
        'title' => __( 'Title', 'woocommerce' ),
        'type' => 'safe_text',
        'description' => __( 'This controls the title which the user sees during checkout.', 'woocommerce' ),
        'default' => __( 'TapTree', 'woocommerce' ),
        'desc_tip'      => true,
    ),*/
    'api_key' => array(
        'title' => __('API Key', 'woocommerce'),
        'type' => 'password',
        'description' => __('Insert your TapTree API key here. Don\'t have an API key yet? Get one in your TapTree dashboard. Attention: Use test keys for test mode and use live keys for production only!', 'woocommerce'),
        //'desc_tip'      => true,
    ),
    'alt_title' => array(
        'title' => __('Title', 'woocommerce'),
        'type' => 'checkbox',
        'label' => __('Set title of payment method to "ClimatePay"', 'woocommerce'),
        'description' => __( 'Check this option if you want to set the title of the payment method to "ClimatePay" instead of "Kreditkarte und mehr"', 'woocommerce' ),
        'default' => 'no'
    ),
    'as_redirect' => array(
        'title' => __('Popup', 'woocommerce'),
        'type' => 'checkbox',
        'label' => __('Checkout as Redirect', 'woocommerce'),
        'description' => __( 'Check this option if you do not want to open the TapTree Checkout Popup but have your clients redirected instead.', 'woocommerce' ),
        'default' => 'no'
    ),
    'visa' => array(
        'title' => __('Visa', 'woocommerce'),
        'type' => 'checkbox',
        'label' => __('Display Visa Logo', 'woocommerce'),
        'description' => __( 'Decide whether to display Visa logo during checkout. Attention: Only select the logo if your TapTree account is set up for this payment method!', 'woocommerce' ),
        'default' => 'no'
    ),
    'mastercard' => array(
        'title' => __('Mastercard', 'woocommerce'),
        'type' => 'checkbox',
        'label' => __('Display Mastercard Logo', 'woocommerce'),
        'description' => __( 'Decide whether to display Mastercard logo during checkout. Attention: Only select the logo if your TapTree account is set up for this payment method!', 'woocommerce' ),
        'default' => 'no'
    ),
    'amex' => array(
        'title' => __('American Express', 'woocommerce'),
        'type' => 'checkbox',
        'label' => __('Display American Express Logo', 'woocommerce'),
        'description' => __( 'Decide whether to display American Express logo during checkout. Attention: Only select the logo if your TapTree account is set up for this payment method!', 'woocommerce' ),
        'default' => 'no'
    ),
    'jcb' => array(
        'title' => __('JCB', 'woocommerce'),
        'type' => 'checkbox',
        'label' => __('Display JCB Logo', 'woocommerce'),
        'description' => __( 'Decide whether to display JCB logo during checkout. Attention: Only select the logo if your TapTree account is set up for this payment method!', 'woocommerce' ),
        'default' => 'no'
    ),
    'diners' => array(
        'title' => __('Diners Club', 'woocommerce'),
        'type' => 'checkbox',
        'label' => __('Display Diners Club Logo', 'woocommerce'),
        'description' => __( 'Decide whether to display Diners Club logo during checkout. Attention: Only select the logo if your TapTree account is set up for this payment method!', 'woocommerce' ),
        'default' => 'no'
    ),
    'applepay' => array(
        'title' => __('Apple Pay', 'woocommerce'),
        'type' => 'checkbox',
        'label' => __('Display Apple Pay Logo', 'woocommerce'),
        'description' => __( 'Decide whether to display Apple Pay logo during checkout. Attention: Only select the logo if your TapTree account is set up for this payment method!', 'woocommerce' ),
        'default' => 'no'
    ),
    'googlepay' => array(
        'title' => __('Google Pay', 'woocommerce'),
        'type' => 'checkbox',
        'label' => __('Display Google Pay Logo', 'woocommerce'),
        'description' => __( 'Decide whether to display Google Pay logo during checkout. Attention: Only select the logo if your TapTree account is set up for this payment method!', 'woocommerce' ),
        'default' => 'no'
    ),
    'sofort' => array(
        'title' => __('Sofort', 'woocommerce'),
        'type' => 'checkbox',
        'label' => __('Display Sofort Logo', 'woocommerce'),
        'description' => __( 'Decide whether to display Sofort logo during checkout. Attention: Only select the logo if your TapTree account is set up for this payment method!', 'woocommerce' ),
        'default' => 'no'
    ),
    'giropay' => array(
        'title' => __('giropay', 'woocommerce'),
        'type' => 'checkbox',
        'label' => __('Display giropay Logo', 'woocommerce'),
        'description' => __( 'Decide whether to display giropay logo during checkout. Attention: Only select the logo if your TapTree account is set up for this payment method!', 'woocommerce' ),
        'default' => 'no'
    ),
    'paypal' => array(
        'title' => __('PayPal', 'woocommerce'),
        'type' => 'checkbox',
        'label' => __('Display PayPal Logo', 'woocommerce'),
        'description' => __( 'Decide whether to display PayPal logo during checkout. Attention: Only select the logo if your TapTree account is set up for this payment method!', 'woocommerce' ),
        'default' => 'no'
    ),
    'klarna' => array(
        'title' => __('Klarna', 'woocommerce'),
        'type' => 'checkbox',
        'label' => __('Display Klarna Logo', 'woocommerce'),
        'description' => __( 'Decide whether to display Klarna logo during checkout. Attention: Only select the logo if your TapTree account is set up for this payment method!', 'woocommerce' ),
        'default' => 'no'
    ),
    /*'more' => array(
        'title' => __('And More', 'woocommerce'),
        'type' => 'checkbox',
        'label' => __('Display "And More" Logo', 'woocommerce'),
        'default' => 'no'
    )*/
    /*'description' => array(
        'title' => __( 'Customer Message', 'woocommerce' ),
        'type' => 'textarea',
        'default' => 'After enabling the TapTree Payment Gateway and providing an API key payments are securely be processed via the TapTrhosted checkout.'
    )*/
);
