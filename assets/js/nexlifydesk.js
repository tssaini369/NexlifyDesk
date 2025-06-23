(function($) {
    'use strict';

    window.nexlifydesk_vars = window.nexlifydesk_vars || window.nexlifydesk_admin_vars || {};

    let isPluginValid = true;

    function disablePluginFunctionality(message) {
        $('form#nexlifydesk-new-ticket, form#nexlifydesk-reply-form, form#nexlifydesk-category-form, form#nexlifydesk-tickets-filter').each(function() {
            $(this).find('input, textarea, select, button').prop('disabled', true);
            $(this).prepend('<div class="nexlifydesk-error integrity-error" style="color: red; margin-bottom: 10px;">' + message + '</div>');
        });

        $('.delete-category, .ticket-status-select, .ticket-agent-select, .delete-position, .edit-position, .page-title-action').off('click change').css('opacity', '0.5').css('cursor', 'not-allowed');

        $('#nexlifydesk-message, .nexlifydesk-form-messages').addClass('error').text(message).show();
    }

    $(document).ready(function() {
        $('.page-title-action').on('click', function(e) {
            e.preventDefault();
            $('#nexlifydesk-category-form').slideToggle();
        });

        $('.delete-category').on('click', function(e) {
            e.preventDefault();
            if (!isPluginValid) return; 
            if (!confirm(nexlifydesk_admin_vars.confirm_delete)) return;

            var categoryId = $(this).data('id');
            $.ajax({
                url: nexlifydesk_admin_vars.ajaxurl,
                type: 'POST',
                data: {
                    action: 'nexlifydesk_delete_category',
                    nonce: nexlifydesk_admin_vars.nonce,
                    category_id: categoryId
                },
                success: function(response) {
                    if (response.success) {
                        location.reload();
                    } else {
                        alert(response.data);
                    }
                }
            });
        });

        $('#nexlifydesk-tickets-filter').on('submit', function(e) {
            if (!isPluginValid) return;
            var bulkAction = $('select[name="bulk_action"]').val();
            if (bulkAction && !confirm(nexlifydesk_admin_vars.bulk_action_confirm)) {
                e.preventDefault();
            }
        });

        $('#nexlifydesk-new-ticket').on('submit', function(e) {
            e.preventDefault();
            if (!isPluginValid) return;

            var $form = $(this);
            var $submitButton = $('#submit-ticket-btn');
            var $buttonText = $submitButton.find('.button-text');
            var $buttonSpinner = $submitButton.find('.button-spinner');
            var $messageContainer = $('#nexlifydesk-message');

            $messageContainer.empty().removeClass('success error').hide();

            $submitButton.prop('disabled', true);
            $buttonText.hide();
            $buttonSpinner.show();

            var formData = new FormData();

            formData.append('action', 'nexlifydesk_submit_ticket');
            formData.append('nonce', $form.find('[name="nonce"]').val());
            formData.append('subject', $form.find('[name="subject"]').val());
            formData.append('message', $form.find('[name="message"]').val());
            formData.append('category_id', $form.find('[name="category_id"]').val());
            formData.append('priority', $form.find('[name="priority"]').val());

            var fileInput = $('#ticket-attachments')[0];
            if (fileInput && fileInput.files.length > 0) {
                for (var i = 0; i < fileInput.files.length; i++) {
                    formData.append('attachments[]', fileInput.files[i]);
                }
            }

            $.ajax({
                url: nexlifydesk_vars.ajaxurl,
                type: 'POST',
                data: formData,
                processData: false,
                contentType: false,
                success: function(response) {
                    if (response.success) {
                        $messageContainer.removeClass('error').addClass('success')
                            .html('<strong>✅ ' + response.data.message + '</strong>').show();

                        $form[0].reset();
                        $('#file-list').empty();

                        $('html, body').animate({
                            scrollTop: $messageContainer.offset().top - 100
                        }, 500);

                        if (response.data.redirect) {
                            setTimeout(function() {
                                window.location.href = response.data.redirect;
                            }, 1500);
                        }
                    } else {
                        $messageContainer.removeClass('success').addClass('error')
                            .text(response.data).show();
                    }
                },
                error: function(xhr, status, error) {
                    var errorMessage = nexlifydesk_vars.error_occurred_text || 'An error occurred. Please try again.';
                    if (xhr.status === 403) {
                        errorMessage = 'Permission denied. Please try logging out and logging back in.';
                    } else if (xhr.status === 0) {
                        errorMessage = 'Network error. Please check your connection.';
                    }

                    $messageContainer.removeClass('success').addClass('error')
                        .text(errorMessage).show();
                },
                complete: function() {
                    $submitButton.prop('disabled', false);
                    $buttonText.show();
                    $buttonSpinner.hide();
                }
            });
        });

        $('#nexlifydesk-category-form').on('submit', function(e) {
            e.preventDefault();
            if (!isPluginValid) return;

            var $form = $(this);
            var $submitButton = $form.find('input[type="submit"]');
            var $messageContainer = $('<div class="nexlifydesk-form-messages"></div>').prependTo($form);

            $messageContainer.empty();

            $submitButton.prop('disabled', true).val(nexlifydesk_vars.adding_text);

            var formData = {
                action: 'nexlifydesk_add_category',
                nexlifydesk_category_nonce: $form.find('input[name="nexlifydesk_category_nonce"]').val(),
                category_name: $form.find('#category_name').val(),
                category_description: $form.find('#category_description').val(),
                submit_category: true
            };

            $.ajax({
                url: nexlifydesk_admin_vars.ajaxurl,
                type: 'POST',
                data: formData,
                success: function(response) {
                    if (response.success) {
                        $messageContainer.addClass('success').text(response.data.message);
                        setTimeout(function() {
                            location.reload();
                        }, 1000);
                    } else {
                        $messageContainer.addClass('error').text(response.data);
                    }
                },
                error: function() {
                    $messageContainer.addClass('error').text(nexlifydesk_vars.error_occurred_text);
                },
                complete: function() {
                    $submitButton.prop('disabled', false).val(nexlifydesk_vars.add_category_text);
                }
            });
        });

        $(document).on('submit', '#nexlifydesk-reply-form', function(e) {
            e.preventDefault();
            if (!isPluginValid) return;

            var $form = $(this);
            var $submitButton = $form.find('button[type="submit"]');
            var $messageContainer = $form.find('.nexlifydesk-form-messages');

            if ($messageContainer.length === 0) {
                $messageContainer = $('<div class="nexlifydesk-form-messages"></div>').prependTo($form);
            }

            $messageContainer.empty();

            $submitButton.prop('disabled', true).text(nexlifydesk_vars.submitting_text);

            var formData = new FormData(this);
            formData.append('action', 'nexlifydesk_add_reply');
            formData.append('nonce', $form.find('input[name="nonce"]').val());

            $.ajax({
                url: nexlifydesk_vars.ajaxurl,
                type: 'POST',
                data: formData,
                processData: false,
                contentType: false,
                success: function(response) {
                    if (response.success) {
                        $messageContainer.removeClass('error').addClass('success').text(response.data.message);

                        if (response.data.reply_html) {
                            var $repliesList = $('#nexlifydesk-replies-list');
                            if ($repliesList.length) {
                                $repliesList.append(response.data.reply_html);
                                $('html, body').animate({
                                    scrollTop: $repliesList.children().last().offset().top - 100
                                }, 500);
                            }
                        }

                        $form[0].reset();
                    } else {
                        $messageContainer.removeClass('success').addClass('error').text(response.data);
                        if (response.data.includes('closed')) {
                            if (nexlifydesk_vars.ticket_form_url) {
                                $messageContainer.append(
                                    '<p><a href="' + nexlifydesk_vars.ticket_form_url +
                                    '" class="nexlifydesk-button">' +
                                    nexlifydesk_vars.create_new_ticket_text + '</a></p>'
                                );
                            }
                        }
                    }
                },
                error: function(xhr, status, error) {
                    $messageContainer.removeClass('success').addClass('error').text(nexlifydesk_vars.error_occurred_text);
                },
                complete: function() {
                    $submitButton.prop('disabled', false).text(nexlifydesk_vars.add_reply_text || 'Add Reply');
                }
            });
        });

        $(document).on('change', 'input[type="file"][name^="attachments"]', function() {
            if (!isPluginValid) return;

            var files = this.files;
            var maxSize = nexlifydesk_vars.max_file_size * 1024 * 1024;
            var allowedTypes = nexlifydesk_vars.allowed_file_types.split(',');

            for (var i = 0; i < files.length; i++) {
                var file = files[i];
                var fileExt = file.name.split('.').pop().toLowerCase();

                if (file.size > maxSize) {
                    alert('File "' + file.name + '" is too large. Maximum size is ' + nexlifydesk_vars.max_file_size + 'MB.');
                    this.value = '';
                    return false;
                }

                if (allowedTypes.indexOf(fileExt) === -1) {
                    alert('File type "' + fileExt + '" is not allowed.');
                    this.value = '';
                    return false;
                }
            }
        });

        $(document).on('change', '.ticket-status-select', function() {
            if (!isPluginValid) return;
        
            var $select = $(this);
            var ticketId = $select.data('ticket-id');
            var newStatus = $select.val();
        
            if (!ticketId) {
                return;
            }
        
            $.ajax({
                url: nexlifydesk_admin_vars.ajaxurl,
                type: 'POST',
                data: {
                    action: 'nexlifydesk_update_status',
                    nonce: nexlifydesk_admin_vars.nonce,
                    ticket_id: ticketId,
                    status: newStatus
                },
                success: function(response) {
                    if (response.success) {
                        var $statusCell = $select.closest('td').length ? $select.closest('td') : $select.parent();
                        var statusText = (typeof nexlifydesk_admin_vars !== 'undefined' && nexlifydesk_admin_vars.status_updated_text)
                            ? nexlifydesk_admin_vars.status_updated_text
                            : (typeof nexlifydesk_vars !== 'undefined' && nexlifydesk_vars.status_updated_text)
                                ? nexlifydesk_vars.status_updated_text
                                : 'Status updated!';

                        var $notice = $('<div class="nexlifydesk-notice success">' +
                            '<span class="dashicons dashicons-yes"></span> ' +
                            statusText +
                            '</div>');

                        $statusCell.append($notice);

                        $select.css('border-color', '#46b450').css('box-shadow', '0 0 2px #46b450');

                        setTimeout(function() {
                            $notice.fadeOut(function() { $(this).remove(); });
                            $select.css('border-color', '').css('box-shadow', '');
                        }, 3000);
                    } else {
                        alert('Error updating status: ' + response.data);
                    }
                },
                error: function(xhr, status, error) {
                    alert('Error updating ticket status: ' + error);
                    $select.val($select.data('original-value'));
                },
                beforeSend: function() {
                    $select.data('original-value', $select.val());
                }
            });
        });

        $(document).on('change', '.ticket-agent-select', function() {
            if (!isPluginValid) return;

            var $select = $(this);
            var ticketId = $select.data('ticket-id');
            var newAgentId = $select.val();

            if (!ticketId) return;

            $select.prop('disabled', true);

            $.ajax({
                url: nexlifydesk_admin_vars.ajaxurl, 
                type: 'POST',
                data: {
                    action: 'nexlifydesk_admin_assign_ticket',
                    nonce: nexlifydesk_admin_vars.nonce,
                    ticket_id: ticketId,
                    user_id: newAgentId
                },
                success: function(response) {
                    if (response.success) {
                      } else {
                        alert('Error assigning agent: ' + response.data);
                    }
                },
                error: function() {
                    alert('Error assigning ticket.');
                },
                complete: function() {
                    $select.prop('disabled', false);
                }
            });
        });

        function fetchAdminTickets() {
            if (!isPluginValid) return;

            var $tableContainer = $('#nexlifydesk-tickets-table');
            var $filterForm = $('#nexlifydesk-tickets-filter');
            var formData = $filterForm.serialize();

            $tableContainer.html('<tr><td colspan="5">' + nexlifydesk_admin_vars.loading_tickets_text + '</td></tr>');

            $.ajax({
                url: nexlifydesk_admin_vars.ajaxurl,
                type: 'GET',
                data: formData + '&action=nexlifydesk_admin_get_tickets&nonce=' + nexlifydesk_admin_vars.nonce,
                success: function(response) {
                    if (response.success) {
                        var tickets = response.data;
                        var tableHtml = '<table><thead><tr>' +
                            '<th>' + nexlifydesk_admin_vars.ticket_id_header + '</th>' +
                            '<th>' + nexlifydesk_admin_vars.subject_header + '</th>' +
                            '<th>' + nexlifydesk_admin_vars.status_header + '</th>' +
                            '<th>' + nexlifydesk_admin_vars.assigned_to_header + '</th>' + // <-- Add this
                            '<th>' + nexlifydesk_admin_vars.created_header + '</th>' +
                            '<th>' + nexlifydesk_admin_vars.actions_header + '</th>' +
                            '</tr></thead><tbody>';

                        if (tickets.length > 0) {
                            $.each(tickets, function(index, ticket) {
                                tableHtml += '<tr>';
                                tableHtml += '<td>' + ticket.ticket_id + '</td>';
                                tableHtml += '<td>' + ticket.subject + '</td>';
                                tableHtml += '<td class="status-' + ticket.status + '">' + ticket.status.charAt(0).toUpperCase() + ticket.status.slice(1) + '</td>';
                                tableHtml += '<td>' + (ticket.assigned_to_display_name ? ticket.assigned_to_display_name : 'Unassigned') + '</td>'; // <-- Add this
                                tableHtml += '<td>' + ticket.created_at + '</td>';
                                tableHtml += '<td><a href="?page=nexlifydesk_tickets&ticket_id=' + ticket.id + '">' + nexlifydesk_admin_vars.view_text + '</a></td>';
                                tableHtml += '</tr>';
                            });
                        } else {
                            tableHtml += '<tr><td colspan="5">' + nexlifydesk_admin_vars.no_tickets_found_text + '</td></tr>';
                        }

                        tableHtml += '</tbody></table>';
                        $tableContainer.html(tableHtml);

                    } else {
                        $tableContainer.html('<tr><td colspan="5">' + nexlifydesk_admin_vars.error_loading_tickets_text + ' ' + response.data + '</td></tr>');
                        console.error('Error fetching admin tickets:', response.data);
                    }
                },
                error: function(xhr, status, error) {
                    $tableContainer.html('<tr><td colspan="5">' + nexlifydesk_admin_vars.ajax_error_loading_tickets_text + '</td></tr>');
                    console.error('AJAX Error fetching admin tickets:', status, error, xhr.responseText);
                }
            });
        }

        if ($('#nexlifydesk-tickets-table').length) {
            if (isPluginValid) {
                fetchAdminTickets();
            }
        }

        $('#nexlifydesk-tickets-filter').on('submit', function(e) {
            e.preventDefault();
            if (isPluginValid) {
                fetchAdminTickets();
            }
        });

        $(document).on('click', '.delete-position', function(e) {
            e.preventDefault();
            if (!isPluginValid) return;
            var slug = $(this).data('slug');
            if (!confirm('Are you sure you want to delete this position?')) return;

            var $form = $('<form method="post" action="' + nexlifydesk_admin_vars.admin_post_url + '" style="display:none;"></form>');
            $form.append('<input type="hidden" name="action" value="nexlifydesk_delete_agent_position">');
            $form.append('<input type="hidden" name="delete_position" value="1">');
            $form.append('<input type="hidden" name="position_slug" value="' + slug + '">');
            $form.append('<input type="hidden" name="nexlifydesk_agent_position_nonce" value="' + nexlifydesk_admin_vars.position_nonce + '">');
            $('body').append($form);
            $form.submit();
        });

        $(document).on('click', '.edit-position', function(e) {
            e.preventDefault();
            if (!isPluginValid) return;
            var $row = $(this).closest('tr');
            var slug = $(this).data('slug');
            var name = $row.find('td').eq(0).text().trim();
            var assignedCaps = $row.data('capabilities') ? $row.data('capabilities').toString().split(',') : [];

            if ($row.next().hasClass('edit-position-row')) return;

            var formHtml = '<tr class="edit-position-row"><td colspan="4">';
            formHtml += '<form class="nexlifydesk-edit-position-form" method="post" action="' + nexlifydesk_admin_vars.admin_post_url + '">';
            formHtml += '<input type="hidden" name="action" value="nexlifydesk_save_agent_position">';
            formHtml += '<input type="hidden" name="edit_position" value="1">';
            formHtml += '<input type="hidden" name="original_slug" value="' + slug + '">';
            formHtml += '<input type="hidden" name="nexlifydesk_agent_position_nonce" value="' + nexlifydesk_admin_vars.position_nonce + '">';
            formHtml += '<label>' + nexlifydesk_admin_vars.position_name_label + ' <input type="text" name="position_name" value="' + name + '" required></label> ';
            formHtml += '<label>' + nexlifydesk_admin_vars.position_slug_label + ' <input type="text" name="position_slug" value="' + slug + '" required></label> ';
            formHtml += '<br><strong>' + nexlifydesk_admin_vars.assign_capabilities_label + '</strong><br>';
            $.each(nexlifydesk_admin_vars.available_capabilities, function(capSlug, capName) {
                var checked = (assignedCaps.indexOf(capSlug) !== -1) ? 'checked' : '';
                formHtml += '<label style="margin-right:15px;"><input type="checkbox" name="position_capabilities[]" value="' + capSlug + '" ' + checked + '> ' + capName + '</label>';
            });
            formHtml += '<br><button type="submit" class="button button-primary">' + nexlifydesk_admin_vars.save_text + '</button> ';
            formHtml += '<button type="button" class="button cancel-edit-position">' + nexlifydesk_admin_vars.cancel_text + '</button>';
            formHtml += '</form></td></tr>';

            $row.after(formHtml);
        });

         $(document).on('click', '.cancel-edit-position', function() {
            $(this).closest('tr.edit-position-row').remove();
        });

        $('#reassign-orphaned-tickets').on('click', function(e) {
            e.preventDefault();
            if (!isPluginValid) return;
            
            var $button = $(this);
            var originalText = $button.text();
            
            $button.prop('disabled', true).text('Reassigning...');
            
            $.ajax({
                url: nexlifydesk_admin_vars.ajaxurl,
                type: 'POST',
                data: {
                    action: 'nexlifydesk_reassign_orphaned_tickets',
                    nonce: nexlifydesk_admin_vars.nonce
                },
                success: function(response) {
                    if (response.success) {
                        alert(response.data);
                        location.reload();
                    } else {
                        alert('Error: ' + response.data);
                    }
                },
                error: function() {
                    alert('Error occurred while reassigning tickets.');
                },
                complete: function() {
                    $button.prop('disabled', false).text(originalText);
                }
            });
        });
    });

    $('#keep_data_on_uninstall').on('change', function() {
        if ($(this).is(':checked')) {
            $('#data-deletion-warning').slideUp();
        } else {
            $('#data-deletion-warning').slideDown();
        }
    });

    if (!$('#keep_data_on_uninstall').is(':checked')) {
        $('#data-deletion-warning').show();
    }

    function loadTickets(status, search) {
        if (!isPluginValid) return;
        
        $('#nexlifydesk-tickets-list').html('<p>' + nexlifydesk_admin_vars.loading_tickets_text + '</p>');
        
        $.ajax({
            url: nexlifydesk_admin_vars.ajaxurl,
            type: 'POST',
            data: {
                action: 'nexlifydesk_admin_get_tickets',
                status: status || 'all',
                search: search || '',
                nonce: nexlifydesk_admin_vars.nonce
            },
            success: function(response) {
                if (response.success && response.data) {
                    displayTickets(response.data);
                } else {
                    var errorMsg = response.data || nexlifydesk_admin_vars.no_tickets_found_text;
                    $('#nexlifydesk-tickets-list').html('<p>' + errorMsg + '</p>');
                    console.log('NexlifyDesk: Server returned error:', response);
                }
            },
            error: function(xhr, status, error) {
                console.log('NexlifyDesk AJAX Error Details:', {
                    xhr: xhr,
                    status: status,
                    error: error,
                    responseText: xhr.responseText
                });
                
                var errorMsg = nexlifydesk_admin_vars.ajax_error_loading_tickets_text;
                if (xhr.responseText) {
                    try {
                        var errorResponse = JSON.parse(xhr.responseText);
                        if (errorResponse.data) {
                            errorMsg += ' ' + errorResponse.data;
                        }
                    } catch (e) {
                        errorMsg += ' ' + xhr.responseText.substring(0, 100);
                    }
                }
                
                $('#nexlifydesk-tickets-list').html('<p style="color: red;">' + errorMsg + '</p>');
            }
        });
    }
})(jQuery);

jQuery(document).ready(function($) {
    $('#keep_data_on_uninstall').on('change', function() {
        if ($(this).is(':checked')) {
            $('#data-deletion-warning').slideUp();
        } else {
            $('#data-deletion-warning').slideDown();
        }
    });
    
    if (!$('#keep_data_on_uninstall').is(':checked')) {
        $('#data-deletion-warning').show();
    }
});

jQuery(document).ready(function($) {
    $('.preview-email-template').on('click', function() {
        var editorId = $(this).data('editor');
        var content = $('#' + editorId).val();
        content = content
            .replace(/{user_name}/g, 'John Doe')
            .replace(/{reply_user_name}/g, 'Support Agent')
            .replace(/{ticket_id}/g, '12345')
            .replace(/{subject}/g, 'Sample Subject')
            .replace(/{reply_message}/g, 'This is a sample reply message.')
            .replace(/{updated_at}/g, '2025-06-19 12:00 PM');
        $('#preview-' + editorId).html(content).show();
    });
});

$(document).ready(function() {
    const urlParams = new URLSearchParams(window.location.search);
    const ticketId = urlParams.get('ticket_id');
    
    if (ticketId && /^\d+$/.test(ticketId) && window.location.href.indexOf('ticket_id=') !== -1) {
        if (window.location.pathname.includes('support') || window.location.pathname.includes('ticket')) {
            console.log('NexlifyDesk: Detected numeric ticket ID, will let server handle the lookup');
        }
    }
});