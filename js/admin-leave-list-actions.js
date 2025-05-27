jQuery(document).ready(function($) {
    // Event delegation for dynamically added elements (if pagination is used)
    $('#posts-filter').on('click', '.ems-update-status-button', function() {
        var $button = $(this);
        var $container = $button.closest('.ems-leave-status-changer');
        var leaveId = $container.data('leave-id');
        var $select = $container.find('.ems-new-status-select');
        var newStatus = $select.val();
        var $spinner = $container.find('.spinner');
        var $statusTextCell = $('#ems-status-text-' + leaveId); // Target the <strong> tag by ID

        if (!leaveId || !newStatus) {
            alert('Error: Could not get leave details.');
            return;
        }

        if (!confirm(ems_leave_list_data.confirm_change)) {
            return;
        }

        $spinner.addClass('is-active');
        $button.prop('disabled', true);
        $select.prop('disabled', true);

        $.ajax({
            url: ems_leave_list_data.ajax_url,
            type: 'POST',
            data: {
                action: 'ems_change_leave_status',
                nonce: ems_leave_list_data.nonce,
                leave_id: leaveId,
                new_status: newStatus
            },
            dataType: 'json',
            success: function(response) {
                if (response.success) {
                    // Update the status text in the table
                    if ($statusTextCell.length) {
                        $statusTextCell.text(response.data.new_status_label);
                    }
                    // Add a temporary success message near the button or as an admin notice
                    $container.append('<div class="notice notice-success is-dismissible" style="margin-top:5px; padding:5px; display:inline-block;"><p>' + response.data.message + '</p></div>');
                    setTimeout(function() {
                        $container.find('.notice-success').fadeOut(function(){ $(this).remove(); });
                    }, 3000);
                } else {
                    alert(ems_leave_list_data.error_generic + (response.data.message ? '\n' + response.data.message : ''));
                     $container.append('<div class="notice notice-error is-dismissible" style="margin-top:5px; padding:5px; display:inline-block;"><p>' + (response.data.message || ems_leave_list_data.error_generic) + '</p></div>');
                    setTimeout(function() {
                        $container.find('.notice-error').fadeOut(function(){ $(this).remove(); });
                    }, 5000);
                }
            },
            error: function() {
                alert(ems_leave_list_data.error_generic);
            },
            complete: function() {
                $spinner.removeClass('is-active');
                $button.prop('disabled', false);
                $select.prop('disabled', false);
            }
        });
    });
});
