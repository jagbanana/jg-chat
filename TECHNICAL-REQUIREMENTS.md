# JGChat Technical Requirements Document

## Overview

JGChat is a WordPress plugin that integrates Anthropic's Claude AI into WordPress websites, allowing site owners to provide intelligent chatbot functionality to their visitors. This document outlines the technical architecture, requirements, and implementation details for developers who want to understand, maintain, or extend the plugin.

## System Architecture

### Components

1. **WordPress Plugin Structure**
   - Main plugin file: `jgchat-plugin.php` - Contains core plugin functionality, admin menu registration, database setup, and AJAX handlers
   - JavaScript: `js/jgchat.js` - Frontend functionality, chat UI interaction, and AJAX communication
   - Stylesheet: `css/jgchat.css` - Dark-themed styling based on the JG Widget Style Guide

2. **Database**
   - Table: `{prefix}_jgchat_logs` - Stores user questions for analytics and review

3. **External Dependencies**
   - Anthropic Claude API - Used for the AI functionality
   - Marked.js - For Markdown parsing in chat messages

## Functionality Requirements

### Core Features

1. **Chat Interface**
   - Two presentation modes:
     - Floating widget (bottom right of screen)
     - Embedded chat via shortcode `[jgchat]`
   - Message types supported:
     - Plain text
     - Markdown formatting
     - Code blocks
     - Lists
     - Links (auto-detected and made clickable)

2. **Conversation Flow**
   - Welcome message on initialization
   - Messages sent via AJAX to WordPress backend
   - Chat history maintained within session
   - Typing animation for AI responses
   - Scrollable message container

3. **Admin Settings**
   - Chatbot name
   - Welcome message
   - Input placeholder text
   - Claude API key
   - Model selection (Claude 3.5 models and Claude 3 models)
   - Knowledge base content input
   - Toggle for enabling/disabling the footer widget

4. **Analytics & Logging**
   - Log of all user questions
   - Admin interface to review logs
   - Search functionality
   - CSV export capability
   - Bulk log deletion

## Technical Implementation Details

### WordPress Integration

1. **Admin Interface**
   - Custom admin menu page for settings
   - Custom admin menu page for logs
   - Settings API integration for storing configuration
   - Custom WP_List_Table implementation for logs display

2. **Database Handling**
   - Uses `dbDelta()` for table creation/updates
   - Simple schema: ID, question text, timestamp
   - Query capability for log retrieval

3. **WordPress Hooks**
   - `admin_menu` - Registers admin pages
   - `admin_init` - Registers settings
   - `wp_enqueue_scripts` - Loads assets
   - `wp_ajax_jgchat` & `wp_ajax_nopriv_jgchat` - AJAX handlers
   - `wp_footer` - Adds chat widget

### JavaScript Implementation

1. **Initialization**
   - DOM-ready event handler
   - Welcomes message display
   - Widget state management using localStorage

2. **UI Components**
   - Chat message container
   - Input field
   - Send button
   - Typing indicator
   - Widget toggle

3. **Message Handling**
   - Formats messages with Markdown support
   - Animates AI response typing
   - Maintains chat history
   - Handles code block rendering
   - Auto-detects and formats links

4. **AJAX Communication**
   - Sends user messages to WordPress backend
   - Receives AI responses
   - Uses WP nonce for security
   - Handles errors gracefully

### Claude AI Integration

1. **API Communication**
   - HTTP POST to Anthropic's Messages API endpoint
   - Headers include API key and version
   - Request body includes:
     - Selected model
     - Chat history
     - System message with knowledge base
     - Max tokens parameter

2. **Response Parsing**
   - Extracts text content from structured API response
   - Handles error conditions
   - Stores successful responses in chat history

## CSS Implementation

1. **Theming**
   - Dark theme per JG Widget Style Guide
   - Color variables for primary colors, status colors, and text colors
   - Responsive design accommodations

2. **Components**
   - Message bubbles
   - Input container
   - Typing indicator with animation
   - Widget button with hover effects
   - Modal container for widget

3. **Animations**
   - Message appear animation
   - Typing indicator
   - Widget button hover
   - Smooth scrolling

## Performance Considerations

1. **Optimizations**
   - Minimal DOM manipulation
   - Throttled animations
   - Efficient AJAX payloads

2. **Limitations**
   - API timeouts set to 30 seconds
   - Maximum token count set to 1024

## Security Implementation

1. **Data Protection**
   - API key stored securely in WordPress options
   - AJAX nonce verification
   - Input sanitization
   - Output escaping

2. **Error Handling**
   - Graceful failure for API errors
   - User feedback for connection issues
   - Error logging for debugging

## Extension Points

Developers can extend JGChat through the following methods:

1. **WordPress Filters** (to be implemented)
   - `jgchat_system_message` - Modify the system message sent to Claude
   - `jgchat_api_response` - Process API responses before display
   - `jgchat_message_format` - Custom message formatting
   - `jgchat_widget_enabled` - Conditional widget display

2. **WordPress Actions** (to be implemented)
   - `jgchat_before_send` - Fires before sending message to API
   - `jgchat_after_response` - Fires after receiving a response
   - `jgchat_log_question` - Fires when logging a question

3. **CSS Customization**
   - Target specific selectors for styling modifications
   - Override default styles with higher specificity

## Future Development Roadmap

1. **Planned Enhancements**
   - Message attachments support
   - User avatar customization
   - Theme selection (light/dark mode)
   - Advanced analytics
   - Multi-model switching
   - Rate limiting
   - User feedback collection

2. **Technical Debt Items**
   - Add proper unit tests
   - Implement template system for flexibility
   - Create developer hooks as mentioned above
   - Optimize JavaScript bundle
   - Add fallback for browsers without localStorage

## Development Environment Setup

1. **Requirements**
   - WordPress 5.6+
   - PHP 7.4+
   - MySQL 5.6+
   - Anthropic API key for testing

2. **Local Setup**
   - WordPress development environment (Local, XAMPP, etc.)
   - Plugin installed to `wp-content/plugins/jgchat/`
   - API key configuration

3. **Testing**
   - Test with multiple WordPress versions
   - Test with multiple PHP versions
   - Test in different browsers
   - Test responsive design
   - Validate security measures

## API Reference

### WordPress Options

- `jgchat_name` - Chatbot name
- `jgchat_welcome` - Welcome message
- `jgchat_placeholder` - Input placeholder
- `jgchat_api_key` - Claude API key
- `jgchat_model` - Selected Claude model
- `jgchat_knowledge_base` - Custom knowledge content
- `jgchat_widget_enabled` - Widget visibility toggle

### Database Schema

```sql
CREATE TABLE {prefix}_jgchat_logs (
    id bigint(20) NOT NULL AUTO_INCREMENT,
    question text NOT NULL,
    created_at datetime DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY  (id)
)
```

### Claude API

The plugin uses the [Claude Messages API](https://docs.anthropic.com/claude/reference/messages_post):

- Endpoint: `https://api.anthropic.com/v1/messages`
- Method: POST
- Authentication: API key in header
- Request format:
  ```json
  {
    "model": "claude-3-5-sonnet-20241022",
    "messages": [
      {"role": "user", "content": "Hello!"},
      {"role": "assistant", "content": "Hi there! How can I help you today?"},
      {"role": "user", "content": "What's the capital of France?"}
    ],
    "system": "You are JGChat, an AI assistant. Use this knowledge to help answer questions: ...",
    "max_tokens": 1024
  }
  ```

## Common Development Tasks

### Adding New Settings

1. Register the setting in `jgchat_register_settings()`
2. Add the field to the settings form in `jgchat_settings_page()`
3. Access the setting with `get_option('setting_name')`

### Modifying Chat Appearance

1. Update CSS in `css/jgchat.css`
2. For structural changes, modify the message creation in `js/jgchat.js`

### Extending Logging Capabilities

1. Modify database schema in `jgchat_install()`
2. Update log insertion in AJAX handler
3. Extend WP_List_Table implementation for display

### Adding Shortcode Parameters

1. Modify the `jgchat_shortcode()` function to accept and process attributes
2. Update the UI creation based on those attributes