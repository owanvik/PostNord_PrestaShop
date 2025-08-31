{* PostNord Delivery Point Selection Template *}
<div id="postnord-delivery-points" class="postnord-container" style="display:none;">
    <h4>{l s='Choose delivery point' mod='postnord'}</h4>
    
    <div class="postnord-search">
        <div class="form-group">
            <label for="postnord-postal-code">{l s='Enter postal code to find delivery points:' mod='postnord'}</label>
            <div class="input-group">
                <input type="text" id="postnord-postal-code" class="form-control" placeholder="{l s='Postal code' mod='postnord'}" maxlength="10">
                <span class="input-group-btn">
                    <button type="button" id="postnord-search-btn" class="btn btn-primary">
                        {l s='Search' mod='postnord'}
                    </button>
                </span>
            </div>
        </div>
    </div>

    <div id="postnord-loading" class="postnord-loading" style="display:none;">
        <i class="fa fa-spinner fa-spin"></i> {l s='Searching for delivery points...' mod='postnord'}
    </div>

    <div id="postnord-delivery-points-list" class="postnord-delivery-points-list">
        <!-- Delivery points will be loaded here via AJAX -->
    </div>

    <div id="postnord-selected-point" class="postnord-selected-point" style="display:none;">
        <h5>{l s='Selected delivery point:' mod='postnord'}</h5>
        <div class="postnord-point-info">
            <strong class="point-name"></strong><br>
            <span class="point-address"></span>
        </div>
        <button type="button" id="postnord-change-point" class="btn btn-link">
            {l s='Change delivery point' mod='postnord'}
        </button>
    </div>

    <input type="hidden" id="postnord-selected-point-id" name="postnord_delivery_point_id" value="">
    <input type="hidden" id="postnord-selected-point-name" name="postnord_delivery_point_name" value="">
    <input type="hidden" id="postnord-selected-point-address" name="postnord_delivery_point_address" value="">
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const postnordContainer = document.getElementById('postnord-delivery-points');
    const carrierInputs = document.querySelectorAll('input[name="id_carrier"]');
    
    // Show/hide delivery points based on carrier selection
    function toggleDeliveryPoints() {
        const selectedCarrier = document.querySelector('input[name="id_carrier"]:checked');
        if (selectedCarrier) {
            const carrierName = selectedCarrier.closest('.delivery-option').querySelector('.carrier-name').textContent.toLowerCase();
            if (carrierName.includes('postnord')) {
                postnordContainer.style.display = 'block';
                loadSavedDeliveryPoint();
            } else {
                postnordContainer.style.display = 'none';
            }
        }
    }

    // Add event listeners to carrier selection
    carrierInputs.forEach(function(input) {
        input.addEventListener('change', toggleDeliveryPoints);
    });

    // Initialize on page load
    toggleDeliveryPoints();

    // Search functionality
    document.getElementById('postnord-search-btn').addEventListener('click', searchDeliveryPoints);
    document.getElementById('postnord-postal-code').addEventListener('keypress', function(e) {
        if (e.key === 'Enter') {
            searchDeliveryPoints();
        }
    });

    // Change delivery point
    document.getElementById('postnord-change-point').addEventListener('click', function() {
        document.getElementById('postnord-selected-point').style.display = 'none';
        document.getElementById('postnord-delivery-points-list').style.display = 'block';
    });

    function searchDeliveryPoints() {
        const postalCode = document.getElementById('postnord-postal-code').value.trim();
        if (!postalCode) {
            alert('{l s="Please enter a postal code" mod="postnord"}');
            return;
        }

        const loadingEl = document.getElementById('postnord-loading');
        const listEl = document.getElementById('postnord-delivery-points-list');
        
        loadingEl.style.display = 'block';
        listEl.innerHTML = '';

        fetch('{$postnord_ajax_url}', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/x-www-form-urlencoded',
            },
            body: 'action=getDeliveryPoints&postal_code=' + encodeURIComponent(postalCode) + '&country_code=NO'
        })
        .then(response => response.json())
        .then(data => {
            loadingEl.style.display = 'none';
            
            if (data.success && data.delivery_points) {
                displayDeliveryPoints(data.delivery_points);
            } else {
                listEl.innerHTML = '<div class="alert alert-warning">' + 
                    (data.error || '{l s="No delivery points found" mod="postnord"}') + '</div>';
            }
        })
        .catch(error => {
            loadingEl.style.display = 'none';
            listEl.innerHTML = '<div class="alert alert-danger">{l s="Error searching for delivery points" mod="postnord"}</div>';
        });
    }

    function displayDeliveryPoints(points) {
        const listEl = document.getElementById('postnord-delivery-points-list');
        let html = '<div class="postnord-points-grid">';

        points.forEach(function(point) {
            html += '<div class="postnord-point" data-point-id="' + point.id + '" data-point-name="' + 
                    point.name + '" data-point-address="' + point.address + ', ' + point.postal_code + ' ' + point.city + '">';
            html += '<div class="point-header">';
            html += '<strong>' + point.name + '</strong>';
            if (point.distance > 0) {
                html += '<span class="point-distance">' + point.distance + ' km</span>';
            }
            html += '</div>';
            html += '<div class="point-address">' + point.address + '<br>' + point.postal_code + ' ' + point.city + '</div>';
            html += '<button type="button" class="btn btn-primary btn-sm select-point-btn">{l s="Select" mod="postnord"}</button>';
            html += '</div>';
        });

        html += '</div>';
        listEl.innerHTML = html;

        // Add click handlers to select buttons
        document.querySelectorAll('.select-point-btn').forEach(function(btn) {
            btn.addEventListener('click', function() {
                const pointEl = this.closest('.postnord-point');
                selectDeliveryPoint(
                    pointEl.dataset.pointId,
                    pointEl.dataset.pointName,
                    pointEl.dataset.pointAddress
                );
            });
        });
    }

    function selectDeliveryPoint(pointId, pointName, pointAddress) {
        // Update hidden fields
        document.getElementById('postnord-selected-point-id').value = pointId;
        document.getElementById('postnord-selected-point-name').value = pointName;
        document.getElementById('postnord-selected-point-address').value = pointAddress;

        // Update display
        document.querySelector('#postnord-selected-point .point-name').textContent = pointName;
        document.querySelector('#postnord-selected-point .point-address').textContent = pointAddress;

        // Show selected point, hide list
        document.getElementById('postnord-selected-point').style.display = 'block';
        document.getElementById('postnord-delivery-points-list').style.display = 'none';

        // Save to backend
        fetch('{$postnord_ajax_url}', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/x-www-form-urlencoded',
            },
            body: 'action=selectDeliveryPoint&delivery_point_id=' + encodeURIComponent(pointId) + 
                  '&delivery_point_name=' + encodeURIComponent(pointName) + 
                  '&delivery_point_address=' + encodeURIComponent(pointAddress)
        });
    }

    function loadSavedDeliveryPoint() {
        // Check if there's a saved delivery point in session/cookie
        if (typeof postnord_saved_point !== 'undefined' && postnord_saved_point.id) {
            selectDeliveryPoint(postnord_saved_point.id, postnord_saved_point.name, postnord_saved_point.address);
        }
    }
});
</script>
