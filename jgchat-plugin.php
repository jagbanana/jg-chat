<?php
/*
Plugin Name: JG Chat
Description: A customizable chatbot powered by Claude AI
Version: 2.03
Author: jaglab
*/

// Prevent direct access to this file
if (!defined('ABSPATH')) {
    exit;
}

// Define version constant based on plugin version
define('JGCHAT_VERSION', '2.03');

// Define database version
global $jgchat_db_version;
$jgchat_db_version = '1.0';

// Database installation/upgrade function
function jgchat_install() {
    global $wpdb;
    global $jgchat_db_version;

    $table_name = $wpdb->prefix . 'jgchat_logs';
    $charset_collate = $wpdb->get_charset_collate();

    $sql = "CREATE TABLE IF NOT EXISTS $table_name (
        id bigint(20) NOT NULL AUTO_INCREMENT,
        question text NOT NULL,
        created_at datetime DEFAULT CURRENT_TIMESTAMP,
        PRIMARY KEY  (id)
    ) $charset_collate;";

    require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
    dbDelta($sql);

    add_option('jgchat_db_version', $jgchat_db_version);
}
register_activation_hook(__FILE__, 'jgchat_activate');

function jgchat_activate() {
    // Create logs table
    jgchat_install();
    
    // Try to fetch models if API key is set
    $api_key = get_option('jgchat_api_key');
    if (!empty($api_key)) {
        jgchat_fetch_models_on_activation($api_key);
    }
}

// Function to fetch models on plugin activation
function jgchat_fetch_models_on_activation($api_key) {
    $response = wp_remote_get('https://api.anthropic.com/v1/models', array(
        'headers' => array(
            'Content-Type' => 'application/json',
            'x-api-key' => $api_key,
            'anthropic-version' => '2023-06-01'
        ),
        'timeout' => 15
    ));
    
    if (is_wp_error($response) || wp_remote_retrieve_response_code($response) !== 200) {
        return; // Silently fail on activation
    }
    
    $response_body = wp_remote_retrieve_body($response);
    $body = json_decode($response_body, true);
    
    // Filter for Claude models only and organize them
    $claude_models = array();
    
    if (isset($body['data']) && is_array($body['data'])) {
        foreach ($body['data'] as $model) {
            if (isset($model['id']) && strpos($model['id'], 'claude') !== false) {
                // Skip deprecated models
                if (isset($model['deprecated']) && $model['deprecated'] === true) {
                    continue;
                }
                
                // Extract model family and variant
                $id = $model['id'];
                
                // Store model description if available
                $description = isset($model['description']) ? $model['description'] : '';
                
                // Add model to the list
                $claude_models[] = array(
                    'id' => $id,
                    'name' => $id, // Use the full model ID as the name
                    'description' => $description,
                    'created' => isset($model['created']) ? $model['created'] : 0,
                    'latest' => isset($model['latest']) && $model['latest'] === true
                );
            }
        }
    }
    
    // Sort models by created date (descending)
    usort($claude_models, function($a, $b) {
        return $b['created'] - $a['created'];
    });
    
    // Save the models to an option for use in the admin page
    update_option('jgchat_available_models', $claude_models);
}

// Add menu items to WordPress admin
function jgchat_admin_menu() {
    // Main menu item
    add_menu_page(
        'JGChat',
        'JGChat',
        'manage_options',
        'jgchat-settings',
        'jgchat_settings_page',
        'dashicons-format-chat'
    );
    
    // Settings submenu (to match the parent slug)
    add_submenu_page(
        'jgchat-settings',
        'JGChat Settings',
        'Settings',
        'manage_options',
        'jgchat-settings'
    );
    
    // Discussion Log submenu
    add_submenu_page(
        'jgchat-settings',
        'Discussion Log',
        'Discussion Log',
        'manage_options',
        'jgchat-logs',
        'jgchat_logs_page'
    );
}
add_action('admin_menu', 'jgchat_admin_menu');

// Register settings
function jgchat_register_settings() {
    register_setting('jgchat_settings', 'jgchat_name');
    register_setting('jgchat_settings', 'jgchat_welcome');
    register_setting('jgchat_settings', 'jgchat_placeholder');
    register_setting('jgchat_settings', 'jgchat_api_key');
    register_setting('jgchat_settings', 'jgchat_model');
    register_setting('jgchat_settings', 'jgchat_knowledge_base');
    register_setting('jgchat_settings', 'jgchat_widget_enabled');
    register_setting('jgchat_settings', 'jgchat_theme_mode');
}
add_action('admin_init', 'jgchat_register_settings');

// Enqueue scripts and styles
function jgchat_enqueue_scripts() {
    wp_enqueue_style('jgchat-styles', plugins_url('css/jgchat.css', __FILE__), array(), JGCHAT_VERSION);
    wp_enqueue_script('marked', 'https://cdn.jsdelivr.net/npm/marked@4.0.0/marked.min.js', array(), '4.0.0', true);
    wp_enqueue_script('jgchat-script', plugins_url('js/jgchat.js', __FILE__), array('jquery', 'marked'), JGCHAT_VERSION, true);
    
    wp_add_inline_script('marked', '
        marked.setOptions({
            breaks: true,
            gfm: true,
            headerIds: false,
            mangle: false
        });
    ');
    
    wp_localize_script('jgchat-script', 'jgchatAjax', array(
        'ajaxurl' => admin_url('admin-ajax.php'),
        'nonce' => wp_create_nonce('jgchat_nonce'),
        'welcomeMessage' => wp_kses_post(get_option('jgchat_welcome', "Hello! I'm a customizable chatbot powered by Claude AI. How can I help you today?")),
        'placeholder' => esc_attr(get_option('jgchat_placeholder', 'Type your message...')),
        'themeMode' => esc_attr(get_option('jgchat_theme_mode', 'dark'))
    ));
}
add_action('wp_enqueue_scripts', 'jgchat_enqueue_scripts');

// Enqueue admin scripts and styles
function jgchat_admin_enqueue_scripts($hook) {
    // Only load on JGChat settings page
    if ($hook !== 'toplevel_page_jgchat-settings' && $hook !== 'jgchat_page_jgchat-logs') {
        return;
    }
    
    wp_enqueue_style('jgchat-admin-styles', plugins_url('css/jgchat.css', __FILE__), array(), JGCHAT_VERSION);
    
    // Add custom admin styles for the preview
    wp_add_inline_style('jgchat-admin-styles', '
        #jgchat-theme-preview {
            width: 300px;
            height: 200px;
            padding: 15px;
            border-radius: 8px;
            margin-bottom: 15px;
            background: var(--jgchat-bg-primary);
            border: 1px solid var(--jgchat-border-color);
        }
    ');
    
    // Add inline script for theme mode preview
    wp_add_inline_script('jquery', '
        jQuery(document).ready(function($) {
            // Add a preview element if it doesn\'t exist
            if ($("#jgchat-theme-preview").length === 0) {
                $("input[name=\'jgchat_theme_mode\']:first").closest("tr").after(\'<tr><th scope="row">Theme Preview</th><td><div id="jgchat-theme-preview"><div class="jgchat-message jgchat-bot-message"><p>This is how the chatbot messages will look.</p></div><div class="jgchat-message jgchat-user-message"><p>This is how your messages will look.</p></div></div></td></tr>\');
            }
            
            // Handle theme mode change
            $("input[name=\'jgchat_theme_mode\']").on("change", function() {
                if ($(this).val() === "light") {
                    $("#jgchat-theme-preview").addClass("jgchat-light-mode");
                } else {
                    $("#jgchat-theme-preview").removeClass("jgchat-light-mode");
                }
            });
            
            // Apply current theme on page load
            if ($("input[name=\'jgchat_theme_mode\']:checked").val() === "light") {
                $("#jgchat-theme-preview").addClass("jgchat-light-mode");
            }
        });
    ');
}
add_action('admin_enqueue_scripts', 'jgchat_admin_enqueue_scripts');

// Create the settings page
function jgchat_settings_page() {
    ?>
    <div class="wrap">
        <h2>JGChat Settings</h2>
        <form method="post" action="options.php">
            <?php
            settings_fields('jgchat_settings');
            do_settings_sections('jgchat_settings');
            ?>
            <table class="form-table">
                <tr>
                    <th scope="row">Chatbot Name</th>
                    <td>
                        <input type="text" name="jgchat_name" value="<?php echo esc_attr(get_option('jgchat_name', 'JGChat')); ?>" class="regular-text">
                    </td>
                </tr>
                <tr>
                    <th scope="row">Enable Footer Widget</th>
                    <td>
                        <label>
                            <input type="checkbox" name="jgchat_widget_enabled" value="1" <?php checked(get_option('jgchat_widget_enabled', '1')); ?>>
                            Display chat widget in page footer
                        </label>
                        <p class="description">When disabled, use shortcode [jgchat] to embed the chat interface in your content.</p>
                    </td>
                </tr>
                <tr>
                    <th scope="row">Theme Mode</th>
                    <td>
                        <label>
                            <input type="radio" name="jgchat_theme_mode" value="dark" <?php checked(get_option('jgchat_theme_mode', 'dark'), 'dark'); ?>>
                            Dark Mode
                        </label>
                        <br>
                        <label>
                            <input type="radio" name="jgchat_theme_mode" value="light" <?php checked(get_option('jgchat_theme_mode', 'dark'), 'light'); ?>>
                            Light Mode
                        </label>
                        <p class="description">Choose between light and dark mode for the chat interface.</p>
                    </td>
                </tr>
                <tr>
                    <th scope="row">Welcome Message</th>
                    <td>
                        <textarea name="jgchat_welcome" rows="5" cols="50" class="large-text"><?php echo esc_textarea(get_option('jgchat_welcome', "Hello! I'm a customizable chatbot powered by Claude AI. How can I help you today?")); ?></textarea>
                    </td>
                </tr>
                <tr>
                    <th scope="row">Input Placeholder</th>
                    <td>
                        <input type="text" name="jgchat_placeholder" value="<?php echo esc_attr(get_option('jgchat_placeholder', 'Type your message...')); ?>" class="regular-text">
                    </td>
                </tr>
                <tr>
                    <th scope="row">Claude API Key</th>
                    <td>
                        <input type="password" name="jgchat_api_key" value="<?php echo esc_attr(get_option('jgchat_api_key')); ?>" class="regular-text">
                    </td>
                </tr>
                <tr>
                    <th scope="row">Claude Model</th>
                    <td>
                        <select name="jgchat_model" id="jgchat-model">
                            <?php
                            $current_model = get_option('jgchat_model');
                            $available_models = get_option('jgchat_available_models', array());
                            if (!empty($available_models)) {
                                foreach ($available_models as $model) {
                                    $selected = $current_model === $model['id'] ? ' selected' : '';
                                    $model_text = $model['id'];
                                    if ($model['latest']) {
                                        $model_text .= ' (latest)';
                                    }
                                    echo '<option value="' . esc_attr($model['id']) . '"' . $selected . ' data-description="' . esc_attr($model['description']) . '">' . esc_html($model_text) . '</option>';
                                }
                            }
                            ?>
                        </select>
                        <button type="button" id="jgchat-refresh-models" class="button">Refresh Models</button>
                        <p class="description" id="jgchat-model-description"></p>
                    </td>
                </tr>
                <tr>
                    <th scope="row">Knowledge Base</th>
                    <td>
                        <textarea name="jgchat_knowledge_base" rows="10" cols="50" class="large-text code" placeholder="Enter your knowledge base content here..."><?php echo esc_textarea(get_option('jgchat_knowledge_base', '')); ?></textarea>
                        <p class="description">Enter the knowledge base content that will be used to inform the chatbot's responses.</p>
                    </td>
                </tr>
            </table>
            <?php submit_button(); ?>
        </form>
    </div>
    
    <script>
    jQuery(document).ready(function($) {
        $('#jgchat-refresh-models').on('click', function() {
            const button = $(this);
            const statusSpan = $('#jgchat-refresh-status');
            const modelSelect = $('select[name="jgchat_model"]');
            const selectedModel = modelSelect.val();
            
            // Disable button and show loading status
            button.prop('disabled', true);
            statusSpan.text('Fetching models...').show().css('color', '');
            
            $.ajax({
                url: ajaxurl,
                type: 'POST',
                data: {
                    action: 'jgchat_fetch_models',
                    nonce: '<?php echo wp_create_nonce('jgchat_nonce'); ?>'
                },
                success: function(response) {
                    if (response.success && response.data) {
                        // Clear existing options
                        modelSelect.empty();
                        
                        // Add new options
                        $.each(response.data, function(index, model) {
                            modelSelect.append(
                                $('<option></option>')
                                    .attr('value', model.id)
                                    .text(model.name + ' - ' + model.description)
                            );
                        });
                        
                        // Try to select the previously selected model, or select the first one
                        if (selectedModel && modelSelect.find('option[value="' + selectedModel + '"]').length) {
                            modelSelect.val(selectedModel);
                        }
                        
                        statusSpan.text('Models refreshed successfully!').css('color', 'green');
                        
                        // Hide status after 3 seconds
                        setTimeout(function() {
                            statusSpan.fadeOut();
                        }, 3000);
                    } else {
                        statusSpan.text('Error: ' + (response.data || 'Unknown error')).css('color', 'red');
                    }
                },
                error: function(xhr, status, error) {
                    statusSpan.text('Error: ' + error).css('color', 'red');
                },
                complete: function() {
                    button.prop('disabled', false);
                }
            });
        });
        
        // Show model description on select change
        $('#jgchat-model').on('change', function() {
            const selectedOption = $(this).find('option:selected');
            const description = selectedOption.data('description');
            $('#jgchat-model-description').text(description);
        });
        
        // Show initial model description
        const initialModel = $('#jgchat-model option:selected');
        const initialDescription = initialModel.data('description');
        $('#jgchat-model-description').text(initialDescription);
    });
    </script>
    <?php
}

// Handle AJAX request for chat
function jgchat_handle_chat() {
    check_ajax_referer('jgchat_nonce', 'nonce');
    
    $message = sanitize_textarea_field($_POST['message']);
    $chat_history = isset($_POST['history']) ? $_POST['history'] : array();
    $api_key = get_option('jgchat_api_key');
    $model = get_option('jgchat_model', 'claude-3-opus-20240229');
    $knowledge_base = get_option('jgchat_knowledge_base', '');
    
    error_log('JGChat Debug - Message: ' . $message);
    error_log('JGChat Debug - Model: ' . $model);
    
    if (empty($api_key)) {
        wp_send_json_error('API key not configured');
        return;
    }

    // Format system message
    $system_message = "You are " . get_option('jgchat_name', 'JGChat') . ", an AI assistant. Use this knowledge to help answer questions:\n\n" . $knowledge_base;

    // Build messages array
    $messages = array();
    
    // Add chat history
    foreach ($chat_history as $chat) {
        $messages[] = array(
            'role' => $chat['role'],
            'content' => $chat['content']
        );
    }

    // Add the current message
    $messages[] = array(
        'role' => 'user',
        'content' => $message
    );

    $request_body = array(
        'model' => $model,
        'messages' => $messages,
        'system' => $system_message,
        'max_tokens' => 1024
    );

    error_log('JGChat Debug - Request Body: ' . json_encode($request_body));

    $response = wp_remote_post('https://api.anthropic.com/v1/messages', array(
        'headers' => array(
            'Content-Type' => 'application/json',
            'x-api-key' => $api_key,
            'anthropic-version' => '2023-06-01'
        ),
        'body' => json_encode($request_body),
        'timeout' => 30
    ));

    if (is_wp_error($response)) {
        error_log('JGChat Debug - WP Error: ' . $response->get_error_message());
        wp_send_json_error($response->get_error_message());
        return;
    }

    $response_code = wp_remote_retrieve_response_code($response);
    $response_body = wp_remote_retrieve_body($response);
    
    error_log('JGChat Debug - Response Code: ' . $response_code);
    error_log('JGChat Debug - Response Body: ' . $response_body);

    if ($response_code !== 200) {
        wp_send_json_error('API returned status ' . $response_code . ': ' . $response_body);
        return;
    }

    // Log the question
    global $wpdb;
    $table_name = $wpdb->prefix . 'jgchat_logs';
    $wpdb->insert(
        $table_name,
        array('question' => $message),
        array('%s')
    );

    $body = json_decode($response_body, true);
    $text = '';
    if (isset($body['content']) && is_array($body['content'])) {
        foreach ($body['content'] as $content) {
            if (isset($content['type']) && $content['type'] === 'text') {
                $text = $content['text'];
                break;
            }
        }
    }
    wp_send_json_success(array('content' => $text));
}
add_action('wp_ajax_jgchat', 'jgchat_handle_chat');
add_action('wp_ajax_nopriv_jgchat', 'jgchat_handle_chat');

// Handle AJAX request to fetch available Claude models
function jgchat_fetch_models() {
    check_ajax_referer('jgchat_nonce', 'nonce');
    
    $api_key = get_option('jgchat_api_key');
    
    if (empty($api_key)) {
        wp_send_json_error('API key not configured');
        return;
    }
    
    $response = wp_remote_get('https://api.anthropic.com/v1/models', array(
        'headers' => array(
            'Content-Type' => 'application/json',
            'x-api-key' => $api_key,
            'anthropic-version' => '2023-06-01'
        ),
        'timeout' => 15
    ));
    
    if (is_wp_error($response)) {
        error_log('JGChat Debug - WP Error: ' . $response->get_error_message());
        wp_send_json_error($response->get_error_message());
        return;
    }
    
    $response_code = wp_remote_retrieve_response_code($response);
    $response_body = wp_remote_retrieve_body($response);
    
    if ($response_code !== 200) {
        wp_send_json_error('API returned status ' . $response_code . ': ' . $response_body);
        return;
    }
    
    $body = json_decode($response_body, true);
    
    // Filter for Claude models only and organize them
    $claude_models = array();
    
    if (isset($body['data']) && is_array($body['data'])) {
        foreach ($body['data'] as $model) {
            if (isset($model['id']) && strpos($model['id'], 'claude') !== false) {
                // Skip deprecated models
                if (isset($model['deprecated']) && $model['deprecated'] === true) {
                    continue;
                }
                
                // Extract model family and variant
                $id = $model['id'];
                
                // Store model description if available
                $description = isset($model['description']) ? $model['description'] : '';
                
                // Add model to the list
                $claude_models[] = array(
                    'id' => $id,
                    'name' => $id, // Use the full model ID as the name
                    'description' => $description,
                    'created' => isset($model['created']) ? $model['created'] : 0,
                    'latest' => isset($model['latest']) && $model['latest'] === true
                );
            }
        }
    }
    
    // Sort models by created date (descending)
    usort($claude_models, function($a, $b) {
        return $b['created'] - $a['created'];
    });
    
    // Save the models to an option for use in the admin page
    update_option('jgchat_available_models', $claude_models);
    
    wp_send_json_success($claude_models);
}
add_action('wp_ajax_jgchat_fetch_models', 'jgchat_fetch_models');

// Create WP_List_Table subclass for the logs
if (!class_exists('WP_List_Table')) {
    require_once(ABSPATH . 'wp-admin/includes/class-wp-list-table.php');
}

class JGChat_Logs_Table extends WP_List_Table {
    public function prepare_items() {
        global $wpdb;
        $table_name = $wpdb->prefix . 'jgchat_logs';
        
        // Handle bulk actions
        $this->process_bulk_action();
        
        // Set up pagination
        $per_page = 20;
        $current_page = $this->get_pagenum();
        $total_items = $wpdb->get_var("SELECT COUNT(id) FROM $table_name");
        
        // Handle search
        $search = isset($_REQUEST['s']) ? sanitize_text_field($_REQUEST['s']) : '';
        $where = '';
        if (!empty($search)) {
            $where = $wpdb->prepare("WHERE question LIKE %s", '%' . $wpdb->esc_like($search) . '%');
        }
        
        // Get items
        $sql = $wpdb->prepare(
            "SELECT * FROM $table_name $where
            ORDER BY created_at DESC
            LIMIT %d OFFSET %d",
            $per_page,
            ($current_page - 1) * $per_page
        );
        $this->items = $wpdb->get_results($sql);
        
        // Set pagination arguments
        $this->set_pagination_args(array(
            'total_items' => $total_items,
            'per_page' => $per_page,
            'total_pages' => ceil($total_items / $per_page)
        ));
        
        $this->_column_headers = array(
            $this->get_columns(),
            array(),
            $this->get_sortable_columns()
        );
    }
    
    public function get_columns() {
        return array(
            'cb' => '<input type="checkbox" />',
            'created_at' => 'Date/Time',
            'question' => 'Question'
        );
    }
    
    public function column_default($item, $column_name) {
        switch ($column_name) {
            case 'created_at':
                return wp_date(get_option('date_format') . ' ' . get_option('time_format'), strtotime($item->created_at));
            case 'question':
                return esc_html($item->question);
            default:
                return print_r($item, true);
        }
    }
    
    public function column_cb($item) {
        return sprintf('<input type="checkbox" name="logs[]" value="%s" />', $item->id);
    }
    
    public function get_bulk_actions() {
        return array('delete' => 'Delete');
    }
    
    private function process_bulk_action() {
        global $wpdb;
        $table_name = $wpdb->prefix . 'jgchat_logs';
        
        if ('delete' === $this->current_action()) {
            $logs = isset($_REQUEST['logs']) ? array_map('intval', $_REQUEST['logs']) : array();
            
            if (!empty($logs)) {
                $ids = implode(',', $logs);
                $wpdb->query("DELETE FROM $table_name WHERE id IN ($ids)");
                
                wp_redirect(add_query_arg(
                    array('page' => 'jgchat-logs', 'deleted' => count($logs)),
                    admin_url('admin.php')
                ));
                exit;
            }
        }
    }
}

// Add the logs page
function jgchat_logs_page() {
    global $wpdb;
    $table_name = $wpdb->prefix . 'jgchat_logs';
    
    // Handle CSV export
    if (isset($_POST['export_csv']) && check_admin_referer('jgchat_export_csv')) {
        $questions = $wpdb->get_results("SELECT created_at, question FROM $table_name ORDER BY created_at DESC");
        
        header('Content-Type: text/csv');
        header('Content-Disposition: attachment; filename="jgchat-logs-' . date('Y-m-d') . '.csv"');
        
        $output = fopen('php://output', 'w');
        fputcsv($output, array('Date/Time', 'Question'));
        
        foreach ($questions as $row) {
            fputcsv($output, array(
                wp_date(get_option('date_format') . ' ' . get_option('time_format'), strtotime($row->created_at)),
                $row->question
            ));
        }
        
        fclose($output);
        exit;
    }
    
    // Display the page
    ?>
    <div class="wrap">
        <h1 class="wp-heading-inline">Discussion Log</h1>
        
        <form method="post" style="display: inline-block; margin-left: 10px;">
            <?php wp_nonce_field('jgchat_export_csv'); ?>
            <input type="submit" name="export_csv" class="button" value="Export CSV">
        </form>
        
        <hr class="wp-header-end">
        
        <?php
        if (isset($_REQUEST['deleted'])) {
            $count = intval($_REQUEST['deleted']);
            printf('<div class="updated notice"><p>%d item(s) deleted.</p></div>', $count);
        }
        
        $table = new JGChat_Logs_Table();
        $table->prepare_items();
        ?>
        
        <form method="post">
            <?php
            $table->search_box('Search Questions', 'search_id');
            $table->display();
            ?>
        </form>
    </div>
    <?php
}

// Create shortcode for embedding the chat interface
function jgchat_shortcode($atts) {
    $atts = shortcode_atts(array(
        'height' => '600px',
    ), $atts);
    
    $theme_mode = get_option('jgchat_theme_mode', 'dark');
    $theme_class = ($theme_mode === 'light') ? ' jgchat-light-mode' : '';
    
    ob_start();
    ?>
    <div class="jgchat-embedded<?php echo esc_attr($theme_class); ?>" style="height: <?php echo esc_attr($atts['height']); ?>">
        <div id="jgchat-messages"></div>
        <div id="jgchat-typing" style="display: none;" class="jgchat-typing">
            <div class="typing-dot"></div>
            <div class="typing-dot"></div>
            <div class="typing-dot"></div>
        </div>
        <div id="jgchat-input-container">
            <textarea id="jgchat-input" placeholder="<?php echo esc_attr(get_option('jgchat_placeholder', 'Type your message...')); ?>"></textarea>
            <button id="jgchat-send">Send</button>
        </div>
    </div>
    <?php
    return ob_get_clean();
}
add_shortcode('jgchat', 'jgchat_shortcode');

// Add widget to footer only if enabled
function jgchat_add_to_footer() {
    // Check if widget is enabled
    if (get_option('jgchat_widget_enabled', '1') !== '1') {
        return;
    }
    
    $theme_mode = get_option('jgchat_theme_mode', 'dark');
    $theme_class = ($theme_mode === 'light') ? ' jgchat-light-mode' : '';
    
    $chatbot_name = get_option('jgchat_name', 'JGChat');
    ?>
    <div id="jgchat-widget-button">
        <div class="jgchat-widget-icon">💬</div>
    </div>
    
    <div id="jgchat-widget-container" class="<?php echo esc_attr($theme_class); ?>" style="display: none;">
        <div class="jgchat-widget-header">
            <div class="jgchat-widget-title"><?php echo esc_html($chatbot_name); ?></div>
            <button class="jgchat-widget-minimize">✕</button>
        </div>
        <div id="jgchat-messages"></div>
        <div id="jgchat-typing" style="display: none;" class="jgchat-typing">
            <div class="typing-dot"></div>
            <div class="typing-dot"></div>
            <div class="typing-dot"></div>
        </div>
        <div id="jgchat-input-container">
            <textarea id="jgchat-input" placeholder="<?php echo esc_attr(get_option('jgchat_placeholder', 'Type your message...')); ?>"></textarea>
            <button id="jgchat-send">Send</button>
        </div>
    </div>
    <?php
}
add_action('wp_footer', 'jgchat_add_to_footer');