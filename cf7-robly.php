<?php
/**
 * Plugin Name: Contact Form 7 to Robly
 * Plugin URI: http://code.andrewrminion.com/contact-form-7-to-robly
 * Description: Adds Contact Form 7 submissions to Robly using their API
 * Version: 1.0.3
 * Author: AndrewRMinion Design
 * Author URI: https://andrewrminion.com
 * License: GPL2
 * GitHub Plugin URI: https://github.com/macbookandrew/contact-form-7-robly
 */

/* prevent this file from being accessed directly */
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/* add settings page */
add_action( 'admin_menu', 'cf7_robly_add_admin_menu' );
add_action( 'admin_init', 'cf7_robly_settings_init' );

// add to menu
function cf7_robly_add_admin_menu() {
    add_options_page( 'Contact Form 7 to Robly', 'CF7 to Robly', 'manage_options', 'contact-form-7-robly', 'cf7_robly_options_page' );
}

// add settings section and fields
function cf7_robly_settings_init() {
    register_setting( 'cf7_robly_options', 'cf7_robly_settings' );

    // API settings
    add_settings_section(
        'cf7_robly_options_keys_section',
        __( 'Add your API Keys', 'cf7_robly' ),
        'cf7_robly_api_settings_section_callback',
        'cf7_robly_options'
    );

    add_settings_field(
        'cf7_robly_api_id',
        __( 'API ID', 'cf7_robly' ),
        'cf7_robly_api_id_render',
        'cf7_robly_options',
        'cf7_robly_options_keys_section'
    );

    add_settings_field(
        'cf7_robly_api_key',
        __( 'API Key', 'cf7_robly' ),
        'cf7_robly_api_key_render',
        'cf7_robly_options',
        'cf7_robly_options_keys_section'
    );

    // alternate email settings
    add_settings_section(
        'cf7_robly_options_alternate_email_section',
        __( 'Alternate Email', 'cf7_robly' ),
        'cf7_robly_alternate_email_settings_section_callback',
        'cf7_robly_options'
    );

    add_settings_field(
        'cf7_robly_alternate_email',
        __( 'Alternate Email Address', 'cf7_robly' ),
        'cf7_robly_alternate_email_render',
        'cf7_robly_options',
        'cf7_robly_options_alternate_email_section'
    );
}

// print API ID field
function cf7_robly_api_id_render() {
    $options = get_option( 'cf7_robly_settings' ); ?>
    <input type="text" name="cf7_robly_settings[cf7_robly_api_id]" placeholder="8c5cc6b52e139888c3a3eb2cc7dacd9b" size="40" value="<?php echo $options['cf7_robly_api_id']; ?>">
    <?php
}

// print API Key field
function cf7_robly_api_key_render() {
    $options = get_option( 'cf7_robly_settings' ); ?>
    <input type="text" name="cf7_robly_settings[cf7_robly_api_key]" placeholder="f1a80ae1cb0c73d4f4d341" size="40" value="<?php echo $options['cf7_robly_api_key']; ?>">
    <?php
    // cache lists if valid keys
    if ( $options['cf7_robly_api_id'] && $options['cf7_robly_api_key'] ) {
        cache_robly_lists( $options['cf7_robly_api_id'], $options['cf7_robly_api_key'] );
    }
}

// print alternate email field
function cf7_robly_alternate_email_render() {
    $options = get_option( 'cf7_robly_settings' ); ?>
    <input type="email" name="cf7_robly_settings[cf7_robly_alternate_email]" placeholder="john.doe@example.com" value="<?php echo $options['cf7_robly_alternate_email']; ?>">
    <?php
}

// print API settings description
function cf7_robly_api_settings_section_callback() {
    echo __( 'Enter your API Keys below. Don’t have any? <a href="mailto:support@robly.com?subject=API access">Request them here</a>.', 'cf7_robly' );
}

// print alternate email settings description
function cf7_robly_alternate_email_settings_section_callback() {
    echo __( 'By default, failed API results will be emailed to the site administrator. To send to a different email address, enter it below; separate multiple addresses with commas.', 'cf7_robly' );
}

// print form
function cf7_robly_options_page() { ?>
    <div class="wrap">
       <h2>Contact Form 7 to Robly</h2>
        <form action="options.php" method="post">

            <?php
            settings_fields( 'cf7_robly_options' );
            do_settings_sections( 'cf7_robly_options' );
            submit_button();
            ?>

        </form>
    </div>
    <?php
}

// TODO: get all Robly lists and CF7 forms and match them up
// cache Robly lists
function cache_robly_lists( $robly_API_id = NULL, $robly_API_key = NULL ) {
    if ( $robly_API_id && $robly_API_key ) {
        // get all sublists from API
        $sublists_ch = curl_init();
        curl_setopt( $sublists_ch, CURLOPT_URL, 'https://api.robly.com/api/v1/sub_lists/show?api_id=' . $robly_API_id . '&api_key=' . $robly_API_key . '&include_all=true' );
        curl_setopt( $sublists_ch, CURLOPT_RETURNTRANSFER, true );
        $sublists_ch_response = curl_exec( $sublists_ch );
        curl_close( $sublists_ch );

        // decode JSON return
        $all_sublists = json_decode( $sublists_ch_response );

        // save to options
        if ( $all_sublists ) {
            $sublists = array();
            foreach ( $all_sublists as $list ) {
                $sublists[$list->sub_list->id] = $list->sub_list->name;
            }
            update_option( 'robly_sublists', maybe_serialize( $sublists ) );
        }
    }
}


/* hook into CF7 submission */
add_action( 'wpcf7_before_send_mail', 'submit_to_robly', 10, 1 );
function submit_to_robly( $form ) {
    global $wpdb;

    // get API keys
    $options = get_option( 'cf7_robly_settings' );
    $robly_API_id = $options['cf7_robly_api_id'];
    $robly_API_key = $options['cf7_robly_api_key'];
    $API_base = 'https://api.robly.com/api/v1/';
    $API_credentials = '?api_id=' . $robly_API_id . '&api_key=' . $robly_API_key;

    // set notification email address
    if ( $options['alternate_email'] ) {
        $notification_email = $options['alternate_email'];
    } else {
        $notification_email = get_option( 'admin_email' );
    }

    // get posted data
    $submission = WPCF7_Submission::get_instance();
    if ( $submission ) {
        $posted_data = $submission->get_posted_data();
    }

    // get array keys for form data
    $email_field = filter_array_keys( 'email', $posted_data );
    $first_name_field = filter_array_keys( 'first-name', $posted_data );
    $last_name_field = filter_array_keys( 'last-name', $posted_data );
    $name_field = filter_array_keys( 'name', $posted_data );

    // get form data
    $email = esc_attr( $posted_data[$email_field] );
    if ( isset( $first_name_field) || isset( $last_name_field ) ) {
        $first_name = esc_attr( $posted_data[$first_name_field] );
        $last_name = esc_attr( $posted_data[$last_name_field] );
    } else {
        $first_name = esc_attr( $posted_data[$name_field] );
    }

    // check for email address
    if ( isset( $email ) && $email != NULL && $email != '' ) {
        // search Robly for customer by email
        $ch = curl_init();
        curl_setopt( $ch, CURLOPT_URL, $API_base . 'contacts/search' . $API_credentials . '&email=' . $email );
        curl_setopt( $ch, CURLOPT_RETURNTRANSFER, true );
        $curl_search = curl_exec( $ch );
        $curl_search_response = json_decode( $curl_search );

        // set API method for subsequent call
        if ( isset( $curl_search_response->member ) ) {
            // handle deleted/unsubscribed members
            if ( $curl_search_response->member->is_subscribed == false || $curl_search_response->member->is_deleted == true ) {
                curl_setopt( $ch, CURLOPT_URL, $API_base . 'contacts/resubscribe' . $API_credentials . '&email=' . $email );
                curl_setopt( $ch, CURLOPT_POST, 1 );
                curl_setopt( $ch, CURLOPT_RETURNTRANSFER, true );

                // run the request and check to see if manual email is needed
                $resubscribe_curl_response = curl_exec( $ch );
                $json_response = json_decode( $resubscribe_curl_response );
                if ( $json_response->successful != true ) {
                    $send_email = true;
                    $error_message .= 'Resubscribe: ' . json_decode( $resubscribe_curl_response )->message;
                } else {
                    $send_email = false;
                }
            }
            // continue with updating contact info
            $API_method = 'contacts/update_full_contact';
        // handle new members
        } else {
            $API_method = 'sign_up/generate';
        }

        // set up user data for the request
        $post_url = $API_method . $API_credentials;
        $user_parameters = array(
            'email'         => urlencode( $email ),
            'fname'         => urlencode( $first_name ),
            'lname'         => urlencode( $last_name )
        );
        $user_parameters = http_build_query( $user_parameters );

        // add sublist IDs
        $post_data = NULL;
        if ( $robly_sublists ) {
            foreach ( $robly_sublists as $this_list ) {
                $post_data .= 'sub_lists[]=' . $this_list . '&';
            }
        }
        $post_data = rtrim( $post_data, '&' );
var_dump($API_base . $API_method . $API_credentials . '&' . $user_parameters);
        // set up the rest of the request
        curl_setopt( $ch, CURLOPT_URL, $API_base . $API_method . $API_credentials . '&' . $user_parameters );
        curl_setopt( $ch, CURLOPT_POST, 1 );
        curl_setopt( $ch, CURLOPT_POSTFIELDS, $post_data );
        curl_setopt( $ch, CURLOPT_RETURNTRANSFER, true );

        // run the request and check to see if manual email is needed
        $user_curl_response = curl_exec( $ch );
        $json_response = json_decode( $user_curl_response );
        if ( $json_response->successful != true || ( $json_response->successful == true && strpos( $json_response->message, 'already exists' ) !== false ) ) {
            $send_email = true;
            $error_message .= 'Update Contact: ' . json_decode( $user_curl_response )->message;
        } else {
            $send_email = false;
        }

        // close cUrl connection
        curl_close( $ch );

        // send notification email if necessary
        if ( $send_email ) {
            $email_sent = mail( $notification_email, 'Contact to manually add to Robly', "API failure\n\nAPI call:\n" . $API_base . $API_method . '?api_id=XXX&api_key=XXX&' . $user_parameters . "\nLists: " . $post_data . "\n\nDetails:\n" . $error_message . "\n\nSent by the Contact Form 7 to Robly plugin on " . home_url() );
        }
    }
}

function filter_array_keys( $needle, array $haystack ) {
    foreach ( $haystack as $key => $value ) {
        if ( stripos( $key, $needle ) !== false ) {
            return $key;
        }
    }
}
