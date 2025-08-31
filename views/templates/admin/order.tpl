{* PostNord Admin Order Template *}
<div class="panel panel-default postnord-admin-panel">
    <div class="panel-heading">
        <i class="icon-truck"></i>
        {l s='PostNord Shipping' mod='postnord'}
    </div>
    <div class="panel-body">
        {if $shipment && $shipment.tracking_number}
            <div class="alert alert-success">
                <strong>{l s='Tracking Number:' mod='postnord'}</strong> {$shipment.tracking_number}
            </div>
            
            <div class="postnord-shipment-info">
                <p><strong>{l s='Shipment ID:' mod='postnord'}</strong> {$shipment.shipment_id}</p>
                {if $shipment.service_point_id}
                    <p><strong>{l s='Delivery Point:' mod='postnord'}</strong> {$shipment.service_point_id}</p>
                {/if}
                <p><strong>{l s='Status:' mod='postnord'}</strong> {$shipment.status|ucfirst}</p>
                <p><strong>{l s='Created:' mod='postnord'}</strong> {$shipment.date_add|date_format:"%d/%m/%Y %H:%M"}</p>
            </div>

            <div class="postnord-actions">
                <a href="{$shipment.label_url}" target="_blank" class="btn btn-primary">
                    <i class="icon-download"></i>
                    {l s='Download Label' mod='postnord'}
                </a>
                
                <button type="button" class="btn btn-info postnord-track-btn" data-tracking="{$shipment.tracking_number}">
                    <i class="icon-search"></i>
                    {l s='Track Shipment' mod='postnord'}
                </button>
            </div>

            <div id="postnord-tracking-info" class="postnord-tracking-info" style="display:none;">
                <h4>{l s='Tracking Information' mod='postnord'}</h4>
                <div id="postnord-tracking-content"></div>
            </div>
        {else}
            <div class="alert alert-info">
                {l s='No shipping label has been created for this order yet.' mod='postnord'}
            </div>
            
            {if $can_create_label}
                <button type="button" id="postnord-create-label-btn" class="btn btn-success" data-order-id="{$order->id}">
                    <i class="icon-plus"></i>
                    {l s='Create Shipping Label' mod='postnord'}
                </button>
            {/if}
        {/if}

        <div id="postnord-messages" class="postnord-messages"></div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    // Create label button
    const createLabelBtn = document.getElementById('postnord-create-label-btn');
    if (createLabelBtn) {
        createLabelBtn.addEventListener('click', function() {
            const orderId = this.dataset.orderId;
            createShippingLabel(orderId);
        });
    }

    // Track shipment button
    const trackBtn = document.querySelector('.postnord-track-btn');
    if (trackBtn) {
        trackBtn.addEventListener('click', function() {
            const trackingNumber = this.dataset.tracking;
            trackShipment(trackingNumber);
        });
    }

    function createShippingLabel(orderId) {
        const btn = document.getElementById('postnord-create-label-btn');
        const originalText = btn.innerHTML;
        
        btn.disabled = true;
        btn.innerHTML = '<i class="icon-spinner icon-spin"></i> {l s="Creating label..." mod="postnord"}';

        fetch('{$ajax_url}', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/x-www-form-urlencoded',
            },
            body: 'action=createLabel&id_order=' + orderId
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                showMessage('success', '{l s="Shipping label created successfully!" mod="postnord"}');
                // Reload page to show the new label information
                setTimeout(() => {
                    window.location.reload();
                }, 1500);
            } else {
                showMessage('error', data.error || '{l s="Failed to create shipping label" mod="postnord"}');
                btn.disabled = false;
                btn.innerHTML = originalText;
            }
        })
        .catch(error => {
            showMessage('error', '{l s="Error creating shipping label" mod="postnord"}');
            btn.disabled = false;
            btn.innerHTML = originalText;
        });
    }

    function trackShipment(trackingNumber) {
        const trackingInfo = document.getElementById('postnord-tracking-info');
        const trackingContent = document.getElementById('postnord-tracking-content');
        
        trackingContent.innerHTML = '<i class="icon-spinner icon-spin"></i> {l s="Loading tracking information..." mod="postnord"}';
        trackingInfo.style.display = 'block';

        fetch('{$ajax_url}', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/x-www-form-urlencoded',
            },
            body: 'action=trackShipment&tracking_number=' + trackingNumber
        })
        .then(response => response.json())
        .then(data => {
            if (data.success && data.tracking_info) {
                displayTrackingInfo(data.tracking_info);
            } else {
                trackingContent.innerHTML = '<div class="alert alert-warning">' + 
                    (data.error || '{l s="No tracking information available" mod="postnord"}') + '</div>';
            }
        })
        .catch(error => {
            trackingContent.innerHTML = '<div class="alert alert-danger">{l s="Error loading tracking information" mod="postnord"}</div>';
        });
    }

    function displayTrackingInfo(trackingData) {
        const content = document.getElementById('postnord-tracking-content');
        let html = '';

        if (trackingData.trackingInformationResponse && trackingData.trackingInformationResponse.shipments) {
            const shipments = trackingData.trackingInformationResponse.shipments;
            
            shipments.forEach(shipment => {
                if (shipment.items && shipment.items.length > 0) {
                    const item = shipment.items[0];
                    
                    html += '<div class="postnord-tracking-item">';
                    html += '<h5>{l s="Package Status" mod="postnord"}: ' + (item.status || 'Unknown') + '</h5>';
                    
                    if (item.events && item.events.length > 0) {
                        html += '<div class="postnord-tracking-events">';
                        html += '<h6>{l s="Tracking Events" mod="postnord"}:</h6>';
                        html += '<ul class="list-group">';
                        
                        item.events.forEach(event => {
                            html += '<li class="list-group-item">';
                            html += '<strong>' + event.eventDescription + '</strong><br>';
                            html += '<small>' + event.eventTime + '</small>';
                            if (event.location) {
                                html += '<br><small>{l s="Location" mod="postnord"}: ' + event.location.displayName + '</small>';
                            }
                            html += '</li>';
                        });
                        
                        html += '</ul>';
                        html += '</div>';
                    }
                    html += '</div>';
                }
            });
        }

        if (!html) {
            html = '<div class="alert alert-info">{l s="No detailed tracking information available" mod="postnord"}</div>';
        }

        content.innerHTML = html;
    }

    function showMessage(type, message) {
        const messagesContainer = document.getElementById('postnord-messages');
        const alertClass = type === 'success' ? 'alert-success' : 'alert-danger';
        
        messagesContainer.innerHTML = '<div class="alert ' + alertClass + '">' + message + '</div>';
        
        // Auto-hide after 5 seconds
        setTimeout(() => {
            messagesContainer.innerHTML = '';
        }, 5000);
    }
});
</script>
