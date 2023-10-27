<?php
/**
* Plugin Name: Meta Description Boy
* Description: Auto-generates meta description for post types using OpenAI.
* Version: 1.0
* Plugin URI:  https://www.katsambiris.com
* Author: Nicholas Katsambiris
* Update URI: meta-description-boy
* License: GPL v3
* Tested up to: 6.3
* Requires at least: 6.2
* Requires PHP: 7.2.5
*
* This program is free software: you can redistribute it and/or modify
* it under the terms of the GNU General Public License as published by
* the Free Software Foundation, either version 3 of the License, or
* (at your option) any later version.
*
* This program is distributed in the hope that it will be useful,
* but WITHOUT ANY WARRANTY; without even the implied warranty of
* MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
* GNU General Public License for more details.
*
* You should have received a copy of the GNU General Public License
* along with this program.  If not, see <http://www.gnu.org/licenses/>.
*/

defined( 'ABSPATH' ) || exit;


$plugin = plugin_basename(__FILE__);  // Gets the correct file name for your plugin.
add_filter("plugin_action_links_$plugin", 'meta_description_boy_add_settings_link');

// Code to run during plugin activation
function meta_description_boy_activate() {
    add_option('meta_description_boy_api_key', '');
    add_option('meta_description_boy_post_types', array('post', 'page'));
}
register_activation_hook(__FILE__, 'meta_description_boy_activate');

// Remove options when the plugin is uninstalled
function meta_description_boy_uninstall() {
    delete_option('meta_description_boy_api_key');
    delete_option('meta_description_boy_post_types');
    delete_option('meta_description_boy_selected_model');
    delete_option('meta_description_boy_prompt_text');
    delete_option('meta_description_boy_access_role');
}
register_uninstall_hook(__FILE__, 'meta_description_boy_uninstall');

// Add admin menu
function meta_description_boy_add_admin_menu() {
    $user = wp_get_current_user();
    $allowed_roles = get_option('meta_description_boy_allowed_roles', array('administrator'));
    if (array_intersect($allowed_roles, $user->roles)) {
        add_options_page('Meta Description Boy', 'Meta Description Boy', 'manage_options', 'meta-description-boy', 'meta_description_boy_options_page');
    }
}
add_action('admin_menu', 'meta_description_boy_add_admin_menu');



function meta_description_boy_add_settings_link($links) {
    $settings_link = '<a href="options-general.php?page=meta-description-boy">' . __('Settings') . '</a>';
    array_unshift($links, $settings_link);  // To add the Settings link before other links like Activate/Deactivate.
    return $links;
}


// Display the options page
function meta_description_boy_options_page() {
    ?>
    <div class="wrap">
        <h2>Meta Description Boy Settings</h2>
        <form action="options.php" method="post">
            <?php
            settings_fields('meta_description_boy_options');
            do_settings_sections('meta-description-boy');
            submit_button();
            ?>
        </form>
    </div>
    <?php
}

// Register custom metabox
function meta_description_boy_add_meta_box() {
    $user = wp_get_current_user();
    $allowed_roles = get_option('meta_description_boy_allowed_roles', array('administrator'));
    if (!array_intersect($allowed_roles, $user->roles)) {
        return;
    }

    $selected_post_types = get_option('meta_description_boy_post_types', array('post', 'page')); // Default to post and page if the option is not set.
    foreach ($selected_post_types as $post_type) {
        add_meta_box(
            'meta_description_boy_meta_box', // Unique ID
            'Meta Description', // Title of the box
            'meta_description_boy_meta_box_callback', // Callback function
            $post_type, // Post type
            'side', // Context
            'default' // Priority
        );
    }
}

add_action('add_meta_boxes', 'meta_description_boy_add_meta_box');

// This function will generate the content displayed inside the meta box:
function meta_description_boy_meta_box_callback($post) {
    $post_id = $post->ID;

    $title = get_the_title($post_id) . ' '; // Equivalent to get_name

    $wc_content = '';
    if ('product' === get_post_type($post_id)) {
        $wc_content = get_post_field('post_excerpt', $post_id);
    }

    $acf_content = '';
    if (function_exists('get_fields')) {
        $acf_data = get_fields($post_id);
        $acf_content_raw = extract_acf_content($acf_data);
        $acf_content = remove_table_content($acf_content_raw);
    }

    $post_content_raw = get_post_field('post_content', $post_id);

    if (!empty(trim($wc_content)) || !empty(trim($acf_content)) || !empty(trim($post_content_raw))) {
        echo '<button id="meta_description_boy_generate_meta_description" class="button button-primary">Generate Meta Description</button>';
        echo '<div id="meta_description_boy_output" style="margin-top: 10px;"></div>'; // Container for output
        
        // Retrieve the selected model from the database
        $selected_model = get_option('meta_description_boy_selected_model', 'gpt-3.5-turbo-0613');  // Default to 'gpt-3.5-turbo-0613'
        
        // Display the model selection radio buttons
        echo '<div class="meta-description-boy-model-selector">';
        echo '<div><input id="radio-gpt-3-5" type="radio" name="meta_description_boy_selected_model" value="gpt-3.5-turbo-0613"' . checked($selected_model, 'gpt-3.5-turbo-0613', false) . '> <label for="radio-gpt-3-5">GPT-3.5</label></div>';
        echo '<div><input id="radio-gpt-4" type="radio" name="meta_description_boy_selected_model" value="gpt-4"' . checked($selected_model, 'gpt-4', false) . '> <label for="radio-gpt-4">GPT-4</label></div>';
        echo '</div>';
        
        echo '<div id="meta_description_boy_output" style="margin-top: 10px;"></div>'; // Container for output
    } else {
        echo '<p>Add some content to this page first before generating a meta description.</p>';
    }
    
}


function meta_description_boy_update_selected_model() {
    // Verify nonce
    if (!isset($_POST['nonce']) || !wp_verify_nonce($_POST['nonce'], 'meta_description_boy_model_nonce')) {
        wp_send_json_error(array('message' => 'Invalid nonce'));
    }

    // Update the selected model in the database
    $selected_model = sanitize_text_field($_POST['selected_model']);
    update_option('meta_description_boy_selected_model', $selected_model);
    
    wp_send_json_success();
}
add_action('wp_ajax_meta_description_boy_update_selected_model', 'meta_description_boy_update_selected_model');



// Register settings
function meta_description_boy_admin_init() {
    register_setting('meta_description_boy_options', 'meta_description_boy_api_key');
    register_setting('meta_description_boy_options', 'meta_description_boy_post_types');
    register_setting('meta_description_boy_options', 'meta_description_boy_selected_model');
    register_setting('meta_description_boy_options', 'meta_description_boy_instruction_text');
    register_setting('meta_description_boy_options', 'meta_description_boy_allowed_roles');

    // Settings sections & fields
    add_settings_section('meta_description_boy_api_settings', 'API Settings', null, 'meta-description-boy');
    add_settings_field('meta_description_boy_api_key_field', 'OpenAI API Key', 'meta_description_boy_api_key_field_cb', 'meta-description-boy', 'meta_description_boy_api_settings');
    add_settings_field('meta_description_boy_post_types_field', 'Post Types', 'meta_description_boy_post_types_field_cb', 'meta-description-boy', 'meta_description_boy_api_settings');
    add_settings_field('meta_description_boy_model_field', 'OpenAI Model', 'meta_description_boy_model_field_cb', 'meta-description-boy', 'meta_description_boy_api_settings');
    add_settings_field('meta_description_boy_instruction_text_field', 'Instruction Text', 'meta_description_boy_instruction_text_field_cb', 'meta-description-boy', 'meta_description_boy_api_settings');
    add_settings_field('meta_description_boy_allowed_roles_field', 'Allowed User Roles', 'meta_description_boy_allowed_roles_field_cb', 'meta-description-boy', 'meta_description_boy_api_settings');

}
add_action('admin_init', 'meta_description_boy_admin_init');

function meta_description_boy_instruction_text_field_cb() {
    $instruction_text = get_option('meta_description_boy_instruction_text', 'Write a 160 character or less SEO meta description based on the following content.');
    echo "<input type='text' name='meta_description_boy_instruction_text' value='{$instruction_text}' style='width: 100%;'>";
}

function meta_description_boy_allowed_roles_field_cb() {
    $selected_roles = get_option('meta_description_boy_allowed_roles', array('administrator'));  // Default to administrator.
    $all_roles = wp_roles()->roles;
    foreach ($all_roles as $role_slug => $role) {
        $checked = in_array($role_slug, $selected_roles) ? 'checked' : '';
        echo "<input type='checkbox' name='meta_description_boy_allowed_roles[]' value='{$role_slug}' {$checked}> {$role['name']}<br>";
    }
}

function meta_description_boy_api_key_field_cb() {
    $api_key = get_option('meta_description_boy_api_key');
    echo "<input type='password' name='meta_description_boy_api_key' value='" . esc_attr($api_key) . "'>";
}

function meta_description_boy_post_types_field_cb() {
    $selected_post_types = get_option('meta_description_boy_post_types');
    $post_types = get_post_types();

    // List of post types to exclude
    $excluded_post_types = array(
        'attachment',
        'revision',
        'nav_menu_item',
        'custom_css',
        'customize_changeset',
        'oembed_cache',
        'user_request',
        'wp_block',
        'wp_template',
        'wp_template_part',
        'wp_global_styles',
        'wp_navigation',
        'acf-taxonomy',
        'acf-post-type',
        'acf-ui-options-page',
        'acf-field-group',
        'acf-field',
        'shop_order',
        'shop_order_refund',
        'shop_coupon',
        'shop_order_placehold',
        'product_variation',
        'scheduled-action'
    );

    // Filter out the excluded post types
    $post_types = array_diff($post_types, $excluded_post_types);

    foreach ($post_types as $post_type) {
        $checked = in_array($post_type, $selected_post_types) ? 'checked' : '';
        echo "<input type='checkbox' name='meta_description_boy_post_types[]' value='{$post_type}' {$checked}> {$post_type}<br>";
    }
}

function meta_description_boy_model_field_cb() {
    $selected_model = get_option('meta_description_boy_selected_model', 'gpt-3.5-turbo-0613'); // Default to 'gpt-3.5-turbo-0613'
    $models = array(
        'gpt-3.5-turbo-0613' => 'GPT-3.5 Turbo',
        'gpt-4' => 'GPT-4'
    );
    foreach ($models as $model => $label) {
        $checked = ($selected_model == $model) ? 'checked' : '';
        echo "<label><input type='radio' name='meta_description_boy_selected_model' value='{$model}' {$checked}> {$label}</label><br>";
    }
}



function meta_description_boy_enqueue_admin_scripts($hook) {
    if ('post.php' == $hook || 'post-new.php' == $hook) {
        wp_enqueue_script('meta_description_boy_admin_js', plugin_dir_url(__FILE__) . 'admin.js', array('jquery'), '1.3', true);
        wp_enqueue_style('meta-description-boy-admin-styles', plugin_dir_url(__FILE__) . 'admin.css');
        // Localize the script with server-side data
        $meta_description_boy_data = array(
            'ajax_url' => admin_url('admin-ajax.php'),
            'post_id' => get_the_ID(),
            'nonce' => wp_create_nonce('meta_description_boy_nonce')
        );
        wp_localize_script('meta_description_boy_admin_js', 'meta_description_boy_data', $meta_description_boy_data);

        wp_enqueue_script('meta_description_boy_model_switcher', plugin_dir_url(__FILE__) . 'model-switcher.js', array('jquery'), '1.0', true);
        wp_localize_script('meta_description_boy_model_switcher', 'meta_description_boy_model_data', array(
            'ajax_url' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('meta_description_boy_model_nonce')
        ));

    }
}
add_action('admin_enqueue_scripts', 'meta_description_boy_enqueue_admin_scripts');

function meta_description_boy_handle_ajax_request() {
    // Verify nonce
    if (!isset($_POST['nonce']) || !wp_verify_nonce($_POST['nonce'], 'meta_description_boy_nonce')) {
        wp_send_json_error(array('message' => 'Invalid nonce'));
    }

    $post_id = isset($_POST['post_id']) ? intval($_POST['post_id']) : 0;
    
    $title = get_the_title($post_id) . ' '; // Equivalent to get_name

    $wc_content = '';
    if ('product' === get_post_type($post_id)) {
        $wc_content = get_post_field('post_excerpt', $post_id);
    }

    $acf_content = '';
    if (function_exists('get_fields')) {
        $acf_data = get_fields($post_id);
        $acf_content_raw = extract_acf_content($acf_data);
        $acf_content = remove_table_content($acf_content_raw);
    }

    $post_content_raw = get_post_field('post_content', $post_id);
    $post_content = $title . ' ' . remove_table_content($post_content_raw) . ' ' . esc_html($acf_content) . ' ' . $wc_content;


    $api_key = get_option('meta_description_boy_api_key');

    $model = get_option('meta_description_boy_selected_model', 'gpt-3.5-turbo-0613'); // Get the selected model or default to 'gpt-3.5-turbo-0613'

    $instruction_text = get_option('meta_description_boy_instruction_text', 'Write a 160 character or less SEO meta description based on the following content.');
    
    // Making an API call to OpenAI
    $response = wp_remote_post('https://api.openai.com/v1/chat/completions', array(
        'headers' => array(
            'Authorization' => 'Bearer ' . $api_key,
            'Content-Type' => 'application/json',
        ),
        'body' => json_encode(array(
            'model' => $model, // Use the selected model here
            'messages' => array(
                array(
                    'role' => 'system',
                    'content' => $instruction_text,
                ),
                array(
                    'role' => 'user',
                    'content' => $post_content,
                )
            )
        )),
    ));

    if (is_wp_error($response)) {
        $error_message = $response->get_error_message();
        wp_send_json_error(array('message' => 'OpenAI API Request Error: ' . $error_message));
    } else {
        $response_code = wp_remote_retrieve_response_code($response);
        if ($response_code !== 200) {
            wp_send_json_error(array('message' => 'OpenAI API Response Error: HTTP ' . $response_code));
        } else {
            $body = wp_remote_retrieve_body($response);
            $data = json_decode($body, true);

            $description = isset($data['choices'][0]['message']['content']) ? trim($data['choices'][0]['message']['content']) : '';
            
            if ($description) {
                wp_send_json_success(array('description' => $description));
            } else {
                $error_message = isset($data['error']) ? $data['error']['message'] : 'Unexpected error generating description';
                wp_send_json_error(array('message' => 'OpenAI Error: ' . $error_message));
            }
        }
    }
    
}
add_action('wp_ajax_meta_description_boy_generate_description', 'meta_description_boy_handle_ajax_request');

// Handle flexible content
function extract_acf_content($data) {
    $content = '';

    if (is_array($data)) {
        foreach ($data as $key => $value) {
            // If it's a "content" key and the value is a string
            if ($key === "content" && is_string($value)) {
                $content .= ' ' . $value;
            }
            // If it's an array, delve deeper (recursive)
            elseif (is_array($value)) {
                $content .= ' ' . extract_acf_content($value);
            }
            // If it's a standard field, simply add it to the content
            elseif (is_string($value)) {
                $content .= ' ' . $value;
            }
        }
    } elseif (is_string($data)) {
        $content = $data;
    }

    return $content;
}



// Debug output
function debug_content_on_admin() {
    global $pagenow;
    
    // Check if we're editing a post or a page
    if ($pagenow == 'post.php' && isset($_GET['action']) && $_GET['action'] == 'edit') {

        $post_id = isset($_POST['post_id']) ? intval($_POST['post_id']) : 0;
    
        $title = get_the_title($post_id) . ' '; // Equivalent to get_name
    
        $wc_content = '';
        if ('product' === get_post_type($post_id)) {
            $wc_content = get_post_field('post_excerpt', $post_id);
        }
    
        $acf_content = '';
        if (function_exists('get_fields')) {
            $acf_data = get_fields($post_id);
            $acf_content_raw = extract_acf_content($acf_data);
            $acf_content = remove_table_content($acf_content_raw);
        }
    
        $post_content_raw = get_post_field('post_content', $post_id);
        $post_content = $title . ' ' . remove_table_content($post_content_raw) . ' ' . esc_html($acf_content) . ' ' . $wc_content;
    
    }
}
// add_action('admin_notices', 'debug_content_on_admin');

// Remove table content from being sent to OpenAI
function remove_table_content($content) {
    return preg_replace('/<table.*?>.*?<\/table>/si', '', $content);
}


// Updater
class My_Plugin_Updater {
    
    private $current_version;
    private $api_url;

    public function __construct($current_version, $api_url) {
        $this->current_version = $current_version;
        $this->api_url = $api_url;
    }

    public function check_for_update() {
        $response = wp_remote_get($this->api_url);
        if (is_wp_error($response)) {
            return false;
        }
        $data = json_decode(wp_remote_retrieve_body($response), true);
        if ($data['version'] && version_compare($data['version'], $this->current_version, '>')) {
            return $data;
        }
        return false;
    }
}

function meta_description_boy_check_for_update($transient) {
    if (empty($transient->checked)) {
        return $transient;
    }

    $updater = new My_Plugin_Updater('1.0.0', 'https://raw.githubusercontent.com/nkatsambiris/meta-description-boy/main/updates.json');
    $update_data = $updater->check_for_update();

    if ($update_data) {
        $transient->response['meta-description-boy/index.php'] = (object) array(
            'new_version' => $update_data['version'],
            'package'     => $update_data['download_url'],
            'slug'        => 'meta-description-boy',
            'plugin'      => 'meta-description-boy/index.php',  // This line ensures WordPress knows which plugin is being updated
        );
    }

    return $transient;
}
add_filter('pre_set_site_transient_update_plugins', 'meta_description_boy_check_for_update');


function meta_description_boy_plugin_info($false, $action, $args) {
    if (isset($args->slug) && $args->slug === 'meta-description-boy') {
        $response = wp_remote_get('https://raw.githubusercontent.com/nkatsambiris/meta-description-boy/main/plugin-info.json');
        if (!is_wp_error($response)) {
            $plugin_info = json_decode(wp_remote_retrieve_body($response));
            if ($plugin_info) {
                return (object) array(
                    'slug' => $args->slug, 
                    'name' => $plugin_info->name,
                    'version' => $plugin_info->version,
                    'author' => $plugin_info->author,
                    'requires' => $plugin_info->requires,
                    'tested' => $plugin_info->tested,
                    'last_updated' => $plugin_info->last_updated,
                    'sections' => array(
                        'description' => $plugin_info->sections->description,
                        'changelog' => $plugin_info->sections->changelog
                    ),
                    'download_link' => $plugin_info->download_link,
                    'banners' => array(
                        'low' => 'https://raw.githubusercontent.com/nkatsambiris/meta-description-boy/main/banner-772x250.jpg',
                        'high' => 'https://raw.githubusercontent.com/nkatsambiris/meta-description-boy/main/banner-1544x500.jpg'
                    ),
                );
            }
        }
    }
    return $false;
}
add_filter('plugins_api', 'meta_description_boy_plugin_info', 10, 3);
