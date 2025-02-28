<?php
/*
Plugin Name: JGChat
Description: A customizable chatbot powered by Claude AI
Version: 1.92
Author: jaglab
*/

// Prevent direct access to this file
if (!defined('ABSPATH')) {
    exit;
}

// Define version constant based on plugin version
define('JGCHAT_VERSION', '1.92');

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
register_activation_hook(__FILE__, 'jgchat_install');

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
            gfm: true
        });
    ');
    
    wp_localize_script('jgchat-script', 'jgchatAjax', array(
        'ajaxurl' => admin_url('admin-ajax.php'),
        'nonce' => wp_create_nonce('jgchat_nonce'),
        'welcomeMessage' => wp_kses_post(get_option('jgchat_welcome', "Hello! I'm a customizable chatbot powered by Claude AI. How can I help you today?")),
        'placeholder' => esc_attr(get_option('jgchat_placeholder', 'Type your message...'))
    ));
}
add_action('wp_enqueue_scripts', 'jgchat_enqueue_scripts');

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
                        <select name="jgchat_model">
                            <optgroup label="Claude 3.5 Models (Latest)">
                                <option value="claude-3-5-sonnet-20241022" <?php selected(get_option('jgchat_model'), 'claude-3-5-sonnet-20241022'); ?>>Claude 3.5 Sonnet (Most Intelligent)</option>
                                <option value="claude-3-5-haiku-20241022" <?php selected(get_option('jgchat_model'), 'claude-3-5-haiku-20241022'); ?>>Claude 3.5 Haiku (Fastest)</option>
                            </optgroup>
                            <optgroup label="Claude 3 Models">
                                <option value="claude-3-opus-20240229" <?php selected(get_option('jgchat_model'), 'claude-3-opus-20240229'); ?>>Claude 3 Opus (Most Capable)</option>
                                <option value="claude-3-sonnet-20240229" <?php selected(get_option('jgchat_model'), 'claude-3-sonnet-20240229'); ?>>Claude 3 Sonnet (Balanced)</option>
                                <option value="claude-3-haiku-20240307" <?php selected(get_option('jgchat_model'), 'claude-3-haiku-20240307'); ?>>Claude 3 Haiku (Fastest)</option>
                            </optgroup>
                        </select>
                        <p class="description">Select the Claude model to power your chatbot. Claude 3.5 models offer improved intelligence and capabilities.</p>
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
    $atts = shortcode_atts(array(), $atts);
    
    ob_start();
    ?>
    <div id="jgchat-container" class="jgchat-embedded">
        <div id="jgchat-messages"></div>
        <div id="jgchat-typing" class="jgchat-typing" style="display: none;">
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
    if (get_option('jgchat_widget_enabled', '1') !== '1') {
        return;
    }
    ?>
    <!-- Chat Widget Button -->
    <div id="jgchat-widget-button" class="jgchat-widget-closed">
        <div class="jgchat-widget-icon">🗪</div>
        <div class="jgchat-notification-dot" style="display: none;"></div>
    </div>

    <!-- Chat Widget Container -->
    <div id="jgchat-widget-container" style="display: none;">
        <div class="jgchat-widget-header">
            <span class="jgchat-widget-title"><?php echo esc_html(get_option('jgchat_name', 'JGChat')); ?></span>
            <button class="jgchat-widget-minimize">−</button>
        </div>
        <div id="jgchat-messages"></div>
        <div id="jgchat-typing" class="jgchat-typing" style="display: none;">
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