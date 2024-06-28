<?php
/*
Plugin Name: Paid Memberships Pro - Infusionsoft Add On
Plugin URI: https://www.paidmembershipspro.com/add-ons/pmpro-infusionsoft-integration/
Description: Sync your WordPress users and members with Infusionsoft contacts.
Version: 1.4
Author: Paid Memberships Pro
Author URI: https://www.paidmembershipspro.com/
*/


/*
	Copyright 2011	Stranger Studios	(email : jason@strangerstudios.com)
	GPLv2 Full license details in license.txt
*/
define('PMPRO_INFUSIONSOFT_DIR', dirname(__FILE__));

define( 'PMPROKEAP_DIR', dirname( __FILE__ ) );

define( 'PMPRO_KEAP_VERSION', '0.1' );

require_once PMPROKEAP_DIR . '/includes/settings.php';

//Require API wrapper class in classes folder
include_once( PMPRO_INFUSIONSOFT_DIR . '/classes/class-pmprokeap-api-wrapper.php' );

global $pmprois_error_msg;

//init
function pmprois_init() {

    //setup hooks for new users
    if( !empty( $options['users_tags'] ) ) {
        add_action( 'user_register', 'pmprokeap_user_register' );
	}

    if( ! empty( $pmprois_levels ) ) {
        add_action("pmpro_after_change_membership_level", "pmprois_pmpro_after_change_membership_level", 10, 2);
    }

   // pmprois_testConnection();
}
add_action("init", "pmprois_init");

/*
	Enqueue Select2 JS
*/
function pmprois_enqueue_select2($hook)
{
	// only include on the PMPro Infusionsoft settings page
	if( !empty( $_REQUEST['page'] ) && $_REQUEST['page'] == 'pmprokeap_options' ) {
		wp_enqueue_style('select2', plugins_url('css/select2.min.css', __FILE__), '', '4.0.3', 'screen');
		wp_enqueue_script('select2', plugins_url('js/select2.min.js', __FILE__), array( 'jquery' ), '4.0.3' );
		wp_enqueue_style( 'pmprokeap', plugins_url( 'css/admin.css', __FILE__ ), '', PMPRO_KEAP_VERSION, 'screen' );
	}
}
add_action("admin_enqueue_scripts", "pmprois_enqueue_select2");

function pmprois_loadISDK()
{
	if(!class_exists('iSDK'))
		require_once(PMPRO_INFUSIONSOFT_DIR . "/includes/isdk.php");
}



/**
 *  Create or Update a Contact in keap given an email address. May include tags and additional fields.
 * 
 */
function pmprokeap_update_keap_contact( $email, $tags = NULL, $additional_fields = array() ) {
	global $wpdb;

    $options = get_option( 'pmprokeap_options' );
	$keap = PMProKeap_Api_Wrapper::get_instance();
    $dups = $keap->pmprokeap_get_contact_by_email( $email );
	//Get an array of ids from $tags which is a value, key array
	//array_map(function($item) {	return $item['id'];}, $example2);
	$tags_id =  array_keys( $tags );
	$contact_id = NULL;
	//The user doesn't exist in keap. Add them.
    if( empty( $dups ) ) {
		//add the contact. 
        $response = $keap->pmprokeap_add_contact( $email, $additional_fields );
		$contact_id = $response[ 'id' ];
    } elseif( is_array( $dups ) ) {
		//already exists in keap. update the contact
        $contact_id = $dups[ 0 ][ 'id' ];
		$keap->pmprokeap_update_contact( $contact_id, $email,  $additional_fields );
    }

	//Assign tags to the contact
	if( ! empty( $tags_id ) ) {
		$keap->pmprokeap_assign_tags( $contact_id, $tags_id );
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
function pmprokeap_user_register( $user_id ) {
    $options = get_option( 'pmprokeap_options' );
	//Bail if there's no user tags to add
	if( empty( $options['users_tags'] ) ) {
		return;
	}
	//get user info
	$user = get_userdata( $user_id );

	//add/update the contact and assign the tag
	pmprokeap_update_keap_contact( $user->user_email, $options['users_tags'],
		apply_filters( 'pmpro_keap_addcon_fields', 
			array( 'FirstName' => $user->first_name, 'LastName' => $user->last_name ), $user ) );
}

//for when checking out
function pmprois_pmpro_after_checkout($user_id)
{
    pmprois_pmpro_after_change_membership_level(intval($_REQUEST['level']), $user_id);
}
add_action("pmpro_after_checkout", "pmprois_pmpro_after_checkout", 30);

//subscribe new members (PMPro) when they register
function pmprois_pmpro_after_change_membership_level($level_id, $user_id)
{
    clean_user_cache($user_id);

    global $pmprois_levels;
    $options = get_option("pmprois_options");

    //should we add them to any tags?
    if(!empty($options['level_' . $level_id . '_tags']) && !empty($options['api_key']))
    {
        //get user info
        $list_user = get_userdata($user_id);

        //add/update the contact and assign the tag
        pmprokeap_update_keap_contact($list_user->user_email, $options['level_' . $level_id . '_tags'], apply_filters("pmpro_infusionsoft_addcon_fields", array("FirstName"=>$list_user->first_name, "LastName"=>$list_user->last_name), $list_user));
    }
    elseif(!empty($options['api_key']) && count($options) > 3)
    {
        //now they are a normal user should we add them to any tags?
        if(!empty($options['users_tags']) && !empty($options['api_key']))
        {
            //get user info
            $list_user = get_userdata($user_id);

            //add/update the contact and assign the tag
            pmprokeap_update_keap_contact($list_user->user_email, $options['users_tags'], apply_filters("pmpro_infusionsoft_addcon_fields", array("FirstName"=>$list_user->first_name, "LastName"=>$list_user->last_name), $list_user));
        }
        else
        {
            //NOTE: We don't have a way to remove tags from contacts yet
            //some memberships have tags. assuming the admin intends this level to be unsubscribed from everything
            if(is_array($all_tags))
            {
                //get user info
                $list_user = get_userdata($user_id);

                //add/update the contact and assign the tag
                //pmprokeap_update_keap_contact($list_user->user_email, $options['users_tags'], apply_filters("pmpro_infusionsoft_addcon_fields", array(), $list_user));
            }
        }
    }
}

//update contact in Infusionsoft if a user profile is changed in WordPress
function pmprois_profile_update($user_id, $old_user_data)
{
    //get user info
    $new_user_data = get_userdata($user_id);

    //get options
    $options = get_option("pmprois_options");

	if(function_exists("pmpro_getMembershipLevelForUser"))
	{
		$user_level = pmpro_getMembershipLevelForUser($user_id);
		if(!empty($user_level))
			$level_id = $user_level->id;
	}
		
    //should we add them to any tags?
    if(!empty($level_id) && !empty($options['level_' . $level_id . '_tags']) && !empty($options['api_key']))
    {
        //get user info
        $list_user = get_userdata($user_id);

        //add/update the contact and assign the tag
        pmprokeap_update_keap_contact($old_user_data->user_email, $options['level_' . $level_id . '_tags'], apply_filters("pmpro_infusionsoft_addcon_fields", array("Email"=>$list_user->user_email, "FirstName"=>$list_user->first_name, "LastName"=>$list_user->last_name), $list_user));
    }
    elseif(!empty($options['api_key']) && count($options) > 3)
    {
        //now they are a normal user should we add them to any tags?
        if(!empty($options['users_tags']) && !empty($options['api_key']))
        {
            //get user info
            $list_user = get_userdata($user_id);

			//add/update the contact and assign the tag
			pmprokeap_update_keap_contact($old_user_data->user_email, $options['users_tags'], apply_filters("pmpro_infusionsoft_addcon_fields", array("Email"=>$list_user->user_email, "FirstName"=>$list_user->first_name, "LastName"=>$list_user->last_name), $list_user));			                       
        }
        else
        {
            //NOTE: We don't have a way to remove tags from contacts yet
            //some memberships have tags. assuming the admin intends this level to be unsubscribed from everything
            if(is_array($all_tags))
            {
                //get user info
                $list_user = get_userdata($user_id);

                //add/update the contact and assign the tag
                //pmprokeap_update_keap_contact($list_user->user_email, $options['users_tags'], apply_filters("pmpro_infusionsoft_addcon_fields", array(), $list_user));
            }
        }
    }		
}
add_action("profile_update", "pmprois_profile_update", 10, 2);



// validate our options
function pmprois_options_validate($input) {
    //api key
    $newinput['id'] = trim(preg_replace("[^a-zA-Z0-9\-]", "", $input['id']));
    $newinput['api_key'] = trim(preg_replace("[^a-zA-Z0-9\-]", "", $input['api_key']));

    //user tags
    if(!empty($input['users_tags']) && is_array($input['users_tags']))
    {
        $count = count($input['users_tags']);
        for($i = 0; $i < $count; $i++)
            $newinput['users_tags'][] = trim(preg_replace("[^a-zA-Z0-9\-]", "", $input['users_tags'][$i]));	;
    }

    //membership tags
    global $pmprois_levels;
    if(!empty($pmprois_levels))
    {
        foreach($pmprois_levels as $level)
        {
            if(!empty($input['level_' . $level->id . '_tags']) && is_array($input['level_' . $level->id . '_tags']))
            {
                $count = count($input['level_' . $level->id . '_tags']);
                for($i = 0; $i < $count; $i++)
                    $newinput['level_' . $level->id . '_tags'][] = trim(preg_replace("[^a-zA-Z0-9\-]", "", $input['level_' . $level->id . '_tags'][$i]));	;
            }
        }
    }

    return $newinput;
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