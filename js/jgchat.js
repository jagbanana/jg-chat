// js/jgchat.js
jQuery(document).ready(function($) {
    console.log('JGChat JS initialized');

    const messagesContainer = $('#jgchat-messages');
    const inputField = $('#jgchat-input');
    const sendButton = $('#jgchat-send');
    const typingIndicator = $('#jgchat-typing');
    const widgetButton = $('#jgchat-widget-button');
    const widgetContainer = $('#jgchat-widget-container');
    const minimizeButton = $('.jgchat-widget-minimize');
    let isAnimating = false;
    
    // Store chat history
    let chatHistory = [];

    // Widget functionality
    widgetButton.on('click', function() {
        widgetContainer.toggle();
        if (widgetContainer.is(':visible')) {
            inputField.focus();
            messagesContainer.scrollTop(messagesContainer[0].scrollHeight);
        }
    });

    minimizeButton.on('click', function() {
        widgetContainer.hide();
    });

    // Store widget state in localStorage
    function saveWidgetState() {
        localStorage.setItem('jgchatWidgetOpen', widgetContainer.is(':visible'));
    }

    // Restore widget state on page load
    const wasOpen = localStorage.getItem('jgchatWidgetOpen') === 'true';
    if (wasOpen) {
        widgetContainer.show();
    }

    // Save state when toggling
    widgetButton.add(minimizeButton).on('click', saveWidgetState);

    // Add welcome message function
    function addWelcomeMessage() {
        const welcomeMessage = {
            role: 'assistant',
            content: jgchatAjax.welcomeMessage
        };
        addMessageWithAnimation(welcomeMessage.content, false);
        chatHistory.push(welcomeMessage);
    }

    function createMessageElement(isUser) {
        return $('<div>')
            .addClass('jgchat-message')
            .addClass(isUser ? 'jgchat-user-message' : 'jgchat-bot-message')
            .appendTo(messagesContainer);
    }

    function formatMessage(message) {
        // Configure marked
        marked.setOptions({
            breaks: true,
            gfm: true,
            headerIds: false
        });

        // Split the message into lines and process each section
        const sections = message.split(/```/);
        let formattedContent = '';
        
        sections.forEach((section, index) => {
            if (index % 2 === 0) {
                // Regular text section
                formattedContent += section
                    .split('\n')
                    .map(line => {
                        line = line.trim();
                        if (!line) return ''; // Skip empty lines
                        
                        // Check if line is a section header (ends with colon)
                        if (line.endsWith(':')) {
                            return `<p class="chat-header">${makeLinksClickable(line)}</p>`;
                        }
                        
                        // Handle list items (lines starting with dash)
                        if (line.startsWith('-')) {
                            const content = line.substring(1).trim();
                            return `<p class="chat-list-item">&bull; ${makeLinksClickable(content)}</p>`;
                        }
                        
                        // Regular text
                        return `<p>${makeLinksClickable(line)}</p>`;
                    })
                    .filter(line => line)
                    .join('\n');
            } else {
                // Code section
                formattedContent += `<code>${section.trim()}</code>`;
            }
        });

        return formattedContent;
    }

    function makeLinksClickable(text) {
        // URL regex pattern that matches http, https, and www urls
        const urlRegex = /(https?:\/\/[^\s]+)|(www\.[^\s]+)/g;
        
        // Replace URLs with anchor tags
        return text.replace(urlRegex, (url) => {
            // Add https:// to www. urls if needed
            const fullUrl = url.startsWith('www.') ? 'https://' + url : url;
            
            // Create anchor tag with security attributes
            return `<a href="${fullUrl}" target="_blank" rel="noopener noreferrer">${url}</a>`;
        });
    }

    function addMessage(message, isUser) {
        const messageDiv = createMessageElement(isUser);
        
        if (isUser) {
            messageDiv.html(`<p>${message}</p>`);  // Add <p> tags for user messages
        } else {
            messageDiv.html(formatMessage(message));
        }
        
        messagesContainer.scrollTop(messagesContainer[0].scrollHeight);
    }

    async function addMessageWithAnimation(message, isUser) {
        if (isUser) {
            addMessage(message, true);
            return;
        }
    
        isAnimating = true;
        const messageDiv = createMessageElement(false);
        
        // Split into code and non-code sections
        const sections = message.split(/```/);
        let currentContent = '';
        
        for (let i = 0; i < sections.length; i++) {
            const section = sections[i];
            
            if (i % 2 === 0) {
                // Regular text section
                const words = section.split(' ');
                const chunkSize = 3; // Number of words to add at once
                
                for (let j = 0; j < words.length; j += chunkSize) {
                    const chunk = words.slice(j, j + chunkSize).join(' ') + ' ';
                    currentContent += chunk;
                    messageDiv.html(formatMessage(currentContent));
                    messagesContainer.scrollTop(messagesContainer[0].scrollHeight);
                    await new Promise(resolve => setTimeout(resolve, 50));
                }
            } else {
                // Code section - add all at once
                currentContent += '```' + section + '```';
                messageDiv.html(formatMessage(currentContent));
                messagesContainer.scrollTop(messagesContainer[0].scrollHeight);
                await new Promise(resolve => setTimeout(resolve, 200));
            }
        }
        
        isAnimating = false;
    }

    function showTypingIndicator() {
        typingIndicator.show();
        messagesContainer.scrollTop(messagesContainer[0].scrollHeight);
    }

    function hideTypingIndicator() {
        typingIndicator.hide();
    }

    function sendMessage() {
        const message = inputField.val().trim();
        if (!message || isAnimating) return;

        addMessage(message, true);
        inputField.val('').prop('disabled', true);
        sendButton.prop('disabled', true);
        showTypingIndicator();

        chatHistory.push({
            role: 'user',
            content: message
        });

        $.ajax({
            url: jgchatAjax.ajaxurl,
            type: 'POST',
            data: {
                action: 'jgchat',
                nonce: jgchatAjax.nonce,
                message: message,
                history: chatHistory
            },
            success: function(response) {
                hideTypingIndicator();
                
                if (response.success && response.data && response.data.content) {
                    addMessageWithAnimation(response.data.content, false);
                    chatHistory.push({
                        role: 'assistant',
                        content: response.data.content
                    });
                } else {
                    console.error('Invalid response:', response);
                    addMessageWithAnimation('Error: Could not get response from assistant', false);
                }
            },
            error: function(jqXHR, textStatus, errorThrown) {
                hideTypingIndicator();
                console.error('AJAX Error:', textStatus, errorThrown);
                addMessageWithAnimation('Error: Could not connect to the assistant', false);
            },
            complete: function() {
                inputField.prop('disabled', false);
                sendButton.prop('disabled', false);
                inputField.focus();
            }
        });
    }

    sendButton.on('click', sendMessage);
    inputField.on('keypress', function(e) {
        if (e.which === 13 && !e.shiftKey) {
            e.preventDefault();
            sendMessage();
        }
    });

    // Set placeholder text from PHP
    inputField.attr('placeholder', jgchatAjax.placeholder);

    // Add welcome message at initialization
    addWelcomeMessage();

    // Handle model refresh button click
    $('#jgchat-refresh-models').on('click', function(e) {
        e.preventDefault();
        const button = $(this);
        const modelSelect = $('#jgchat-model');
        const currentModel = modelSelect.val();
        
        button.prop('disabled', true);
        
        $.ajax({
            url: jgchatAjax.ajaxurl,
            type: 'POST',
            data: {
                action: 'jgchat_fetch_models',
                nonce: jgchatAjax.nonce
            },
            success: function(response) {
                if (response.success && response.data) {
                    modelSelect.empty();
                    
                    response.data.forEach(function(model) {
                        const option = $('<option>', {
                            value: model.id,
                            text: model.id
                        });
                        
                        // Add description as data attribute
                        if (model.description) {
                            option.attr('data-description', model.description);
                        }
                        
                        // Add 'latest' tag if applicable
                        if (model.latest) {
                            option.text(option.text() + ' (latest)');
                        }
                        
                        modelSelect.append(option);
                    });
                    
                    // Try to restore the previously selected model
                    if (currentModel && modelSelect.find(`option[value="${currentModel}"]`).length) {
                        modelSelect.val(currentModel);
                    }
                    
                    // Add descriptions below the select
                    modelSelect.off('change').on('change', function() {
                        const description = $(this).find(':selected').data('description');
                        $('#jgchat-model-description').text(description || '');
                    }).trigger('change');
                }
            },
            error: function(xhr, status, error) {
                console.error('Error fetching models:', error);
            },
            complete: function() {
                button.prop('disabled', false);
            }
        });
    });
});