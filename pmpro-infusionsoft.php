<?php
/*
Plugin Name: Paid Memberships Pro - Infusionsoft Add On
Plugin URI: https://www.paidmembershipspro.com/add-ons/pmpro-infusionsoft-integration/
Description: Sync your WordPress users and members with Infusionsoft contacts.
Version: 1.4
Author: Paid Memberships Pro
Text Domain: pmpro-keap
Domain Path: /languages
Author URI: https://www.paidmembershipspro.com/
*/

define( 'PMPRO_KEAP_DIR', dirname( __FILE__ ) );

define( 'PMPRO_KEAP_VERSION', '0.1' );

	require_once PMPRO_KEAP_DIR . '/includes/settings.php';
	include_once( PMPRO_KEAP_DIR . '/classes/class-pmpro-keap-api-wrapper.php' );

global $pmprois_error_msg;

//init
function pmpro_keap_init() {

	add_action( 'user_register', 'pmpro_keap_user_register', 10, 1 );
	add_action( 'pmpro_after_change_membership_level', 'pmpro_keap_pmpro_after_change_membership_level', 10, 2 );
	add_action( 'profile_update',  'pmpro_keap_profile_update', 10, 2);


}

add_action( 'init', 'pmpro_keap_init' );

//Enqueue assets
function pmpro_keap_enqueue_css_assets( $hook ) {
	// only include on the PMPro Keap settings page
	if( !empty( $_REQUEST['page'] ) && $_REQUEST['page'] == 'pmpro-keap' ) {
		wp_enqueue_style( 'pmpro_keap', plugins_url( 'css/admin.css', __FILE__ ), '', PMPRO_KEAP_VERSION, 'screen' );
	}
}
add_action( 'admin_enqueue_scripts', 'pmpro_keap_enqueue_css_assets' );

/**
 * Create or Update a Contact in keap given an email address. May include tags and additional fields.
 *
 * @param string $email The email address of the contact.
 * @param array $tags An array of tags to assign to the contact.
 * @param array $additional_fields An array of additional fields to update on the contact.
 * @return int The contact ID of the contact in keap.
 * @since TBD
 */
function pmpro_keap_update_keap_contact( $user ) {
	$levels = pmpro_getMembershipLevelsForUser( $user->ID );
    $options = get_option( 'pmpro_keap_options' );

	$keap = PMPro_Keap_Api_Wrapper::get_instance();
    $response = $keap->pmpro_keap_get_contact_by_email( $user->user_email );
	//Get an array of ids from $tags which is a value, key array
	//array_map(function($item) {	return $item['id'];}, $example2);

	$contact_id = NULL;
	//The user doesn't exist in keap. Add them.
    if(  $response[ 'count' ] == 0 ) {
		//add the contact. 
        $response = $keap->pmpro_keap_add_contact( $user );
		$contact_id = $response[ 'id' ];
    } else {
		//already exists in keap. update the contact
        $contact_id = $response[ 'contacts' ][ 0 ][ 'id'];
		$keap->pmpro_keap_update_contact( $contact_id, $user );
    }

	//Get the tags from the options and user levels
	//Assign tags to the contact
	$tags_id = array();
	foreach( $levels as $level ) {
		if( !empty( $options[ 'levels' ][ $level->id ] ) ) {
			//append to the tags_id array
			$tags_id = array_merge( $tags_id, $options[ 'levels' ][ $level->id ] );
		}
	}
	if( ! empty( $tags_id ) ) {
		$keap->pmpro_keap_assign_tags_to_contact( $contact_id, $tags_id );
	}

	return $contact_id;
}

/**
 * Add a user to Keap when they register.
 *
 * @param int $user_id The ID of the user that just registered.
 * @return void
 * @since TBD
 */
function pmpro_keap_user_register( $user_id ) {
	$user = get_userdata( $user_id );
	pmpro_keap_update_keap_contact( $user );
}

 function pmpro_keap_pmpro_after_checkout( $user_id, $order ) {
	$user = get_userdata( $user_id );
	pmpro_keap_update_keap_contact( $user );
}

/**
 * subscribe new members (PMPro) when they register
 *
 * @param int $level_id The ID of the level the user is changing to.
 * @param int $user_id The ID of the user that is changing levels.
 * @return void
 * @since TBD
 */
function pmpro_keap_pmpro_after_change_membership_level( $level_id, $user_id ) {
	$user = get_userdata( $user_id );
	pmpro_keap_update_keap_contact( $user );
}

//update contact in Keap if a user profile is changed in WordPress
function pmpro_keap_profile_update( $user_id, $old_user_data ) {
    $user = get_userdata( $user_id );
    pmpro_keap_update_keap_contact( $user );
}

/*
Function to add links to the plugin row meta
*/
function pmprois_plugin_row_meta($links, $file) {
	if(strpos($file, 'pmpro-infusionsoft.php') !== false)
	{
		$new_links = array(
			'<a href="' . esc_url('https://www.paidmembershipspro.com/add-ons/pmpro-infusionsoft-integration/') . '" title="' . esc_attr( __( 'View Documentation', 'pmpro' ) ) . '">' . __( 'Docs', 'pmpro' ) . '</a>',
			'<a href="' . esc_url('https://www.paidmembershipspro.com/support/') . '" title="' . esc_attr( __( 'Visit Customer Support Forum', 'pmpro' ) ) . '">' . __( 'Support', 'pmpro' ) . '</a>',
		);
		$links = array_merge($links, $new_links);
	}
	return $links;
}
add_filter('plugin_row_meta', 'pmprois_plugin_row_meta', 10, 2);