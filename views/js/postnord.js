/**
 * PostNord Module JavaScript
 * Additional functionality for the PostNord module
 */

var PostNord = {
    init: function() {
        this.bindEvents();
        this.initDeliveryPointMap();
    },

    bindEvents: function() {
        // Bind form validation for checkout
        $(document).on('submit', '#js-delivery', this.validateDeliveryPointSelection);
        
        // Auto-search delivery points when postal code is detected
        $(document).on('change', 'input[name="postcode"]', this.autoSearchDeliveryPoints);
        
        // Handle carrier changes
        $(document).on('change', 'input[name="id_carrier"]', this.handleCarrierChange);
    },

    validateDeliveryPointSelection: function(e) {
        var selectedCarrier = $('input[name="id_carrier"]:checked');
        if (selectedCarrier.length > 0) {
            var carrierName = selectedCarrier.closest('.delivery-option').find('.carrier-name').text().toLowerCase();
            
            if (carrierName.includes('postnord')) {
                var selectedPoint = $('#postnord-selected-point-id').val();
                if (!selectedPoint) {
                    e.preventDefault();
                    PostNord.showError('Please select a delivery point for PostNord shipping.');
                    return false;
                }
            }
        }
        return true;
    },

    autoSearchDeliveryPoints: function() {
        var postalCode = $(this).val();
        var carrierSelected = $('input[name="id_carrier"]:checked').length > 0;
        var isPostNordCarrier = false;

        if (carrierSelected) {
            var carrierName = $('input[name="id_carrier"]:checked')
                .closest('.delivery-option')
                .find('.carrier-name')
                .text()
                .toLowerCase();
            isPostNordCarrier = carrierName.includes('postnord');
        }

        if (postalCode && postalCode.length >= 4 && isPostNordCarrier) {
            setTimeout(function() {
                $('#postnord-postal-code').val(postalCode);
                $('#postnord-search-btn').click();
            }, 500);
        }
    },

    handleCarrierChange: function() {
        var carrierName = $(this).closest('.delivery-option').find('.carrier-name').text().toLowerCase();
        var postnordContainer = $('#postnord-delivery-points');

        if (carrierName.includes('postnord')) {
            postnordContainer.slideDown();
            PostNord.loadSavedDeliveryPoint();
        } else {
            postnordContainer.slideUp();
            PostNord.clearDeliveryPointSelection();
        }
    },

    loadSavedDeliveryPoint: function() {
        // Check cookies or session storage for saved delivery point
        var savedPointId = this.getCookie('postnord_delivery_point_id');
        var savedPointName = this.getCookie('postnord_delivery_point_name');
        var savedPointAddress = this.getCookie('postnord_delivery_point_address');

        if (savedPointId && savedPointName && savedPointAddress) {
            this.selectDeliveryPoint(savedPointId, savedPointName, savedPointAddress);
        }
    },

    clearDeliveryPointSelection: function() {
        $('#postnord-selected-point-id').val('');
        $('#postnord-selected-point-name').val('');
        $('#postnord-selected-point-address').val('');
        $('#postnord-selected-point').hide();
        $('#postnord-delivery-points-list').show();
    },

    selectDeliveryPoint: function(pointId, pointName, pointAddress) {
        $('#postnord-selected-point-id').val(pointId);
        $('#postnord-selected-point-name').val(pointName);
        $('#postnord-selected-point-address').val(pointAddress);

        $('#postnord-selected-point .point-name').text(pointName);
        $('#postnord-selected-point .point-address').text(pointAddress);

        $('#postnord-selected-point').show();
        $('#postnord-delivery-points-list').hide();
    },

    initDeliveryPointMap: function() {
        // Initialize map functionality if needed
        // This could integrate with Google Maps or OpenStreetMap
        if (typeof google !== 'undefined' && google.maps) {
            this.initGoogleMap();
        }
    },

    initGoogleMap: function() {
        // Google Maps integration for showing delivery points on map
        // This is optional functionality
    },

    showError: function(message) {
        // Show error message to user
        if (typeof prestashop !== 'undefined' && prestashop.notification) {
            prestashop.notification.showNotification({
                type: 'error',
                message: message
            });
        } else {
            alert(message);
        }
    },

    showSuccess: function(message) {
        // Show success message to user
        if (typeof prestashop !== 'undefined' && prestashop.notification) {
            prestashop.notification.showNotification({
                type: 'success',
                message: message
            });
        } else {
            alert(message);
        }
    },

    getCookie: function(name) {
        var value = "; " + document.cookie;
        var parts = value.split("; " + name + "=");
        if (parts.length == 2) return parts.pop().split(";").shift();
    },

    setCookie: function(name, value, days) {
        var expires = "";
        if (days) {
            var date = new Date();
            date.setTime(date.getTime() + (days * 24 * 60 * 60 * 1000));
            expires = "; expires=" + date.toUTCString();
        }
        document.cookie = name + "=" + (value || "") + expires + "; path=/";
    }
};

// Admin-specific functionality
var PostNordAdmin = {
    init: function() {
        this.bindAdminEvents();
    },

    bindAdminEvents: function() {
        // Bulk label creation
        $(document).on('click', '.postnord-bulk-create-labels', this.bulkCreateLabels);
        
        // Print multiple labels
        $(document).on('click', '.postnord-print-labels', this.printMultipleLabels);
        
        // Export shipping data
        $(document).on('click', '.postnord-export-data', this.exportShippingData);
    },

    bulkCreateLabels: function() {
        var selectedOrders = $('.order-checkbox:checked').map(function() {
            return $(this).val();
        }).get();

        if (selectedOrders.length === 0) {
            alert('Please select at least one order.');
            return;
        }

        if (!confirm('Create shipping labels for ' + selectedOrders.length + ' orders?')) {
            return;
        }

        var processed = 0;
        var errors = [];

        selectedOrders.forEach(function(orderId) {
            $.post(postnord_admin_ajax_url, {
                action: 'createLabel',
                id_order: orderId
            }, function(response) {
                processed++;
                
                if (!response.success) {
                    errors.push('Order #' + orderId + ': ' + (response.error || 'Unknown error'));
                }

                if (processed === selectedOrders.length) {
                    PostNordAdmin.showBulkResults(selectedOrders.length, errors);
                }
            }).fail(function() {
                processed++;
                errors.push('Order #' + orderId + ': Network error');
                
                if (processed === selectedOrders.length) {
                    PostNordAdmin.showBulkResults(selectedOrders.length, errors);
                }
            });
        });
    },

    showBulkResults: function(total, errors) {
        var successful = total - errors.length;
        var message = 'Processed ' + total + ' orders. ' + successful + ' labels created successfully.';
        
        if (errors.length > 0) {
            message += '\n\nErrors:\n' + errors.join('\n');
        }

        alert(message);
        location.reload();
    },

    printMultipleLabels: function() {
        var labelUrls = $('.postnord-label-url').map(function() {
            return $(this).attr('href');
        }).get();

        if (labelUrls.length === 0) {
            alert('No labels found to print.');
            return;
        }

        // Open each label in a new tab for printing
        labelUrls.forEach(function(url) {
            window.open(url, '_blank');
        });
    },

    exportShippingData: function() {
        var dateFrom = $('#export-date-from').val();
        var dateTo = $('#export-date-to').val();

        if (!dateFrom || !dateTo) {
            alert('Please select date range for export.');
            return;
        }

        window.location.href = postnord_admin_ajax_url + '?action=exportData&date_from=' + 
                               encodeURIComponent(dateFrom) + '&date_to=' + encodeURIComponent(dateTo);
    }
};

// Initialize on document ready
$(document).ready(function() {
    PostNord.init();
    
    // Initialize admin functionality if on admin pages
    if ($('body').hasClass('adminhtml')) {
        PostNordAdmin.init();
    }
});

// Hook into PrestaShop checkout process
if (typeof prestashop !== 'undefined') {
    prestashop.on('updateCart', function() {
        // Re-initialize delivery point selection after cart update
        setTimeout(function() {
            PostNord.handleCarrierChange();
        }, 1000);
    });
}
