function sendMessageToBot(message) {
    $.post(ajaxurl, {
        action: 'nexlifydesk_livechat_bot',
        message: message,
        nonce: nexlifydesk_vars.nonce
    }, function(response) {
        // Display bot response in chat UI
        if (response.data.escalate) {
            // Show "Create Ticket" button
        }
    });
}