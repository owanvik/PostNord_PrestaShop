# PostNord Module for PrestaShop 9.0

A comprehensive PostNord shipping integration module for PrestaShop 9.0 that provides label printing functionality and delivery point selection in the frontend.

## Features

- **Label Printing**: Create and print shipping labels directly from order management
- **Delivery Points**: Allow customers to select PostNord delivery points during checkout
- **Order Tracking**: Track shipments and display status updates
- **API Integration**: Full integration with PostNord Business API
- **Multi-language Support**: Supports multiple languages and currencies
- **Mobile Responsive**: Works on all devices and screen sizes

## Installation

1. **Download and Upload**
   - Download the module files
   - Upload the `postnord` folder to your PrestaShop `/modules/` directory

2. **Install via Admin Panel**
   - Go to Modules > Module Manager
   - Search for "PostNord"
   - Click "Install"

3. **Configure API Settings**
   - After installation, click "Configure"
   - Enter your PostNord API credentials:
     - API Key
     - API Secret  
     - Customer Number
   - Set Test Mode (enabled for testing, disabled for production)

## API Credentials Setup

To use this module, you need PostNord Business API credentials:

1. **Register for PostNord Business**
   - Visit [PostNord Business Portal](https://www.postnord.com/business)
   - Register for a business account

2. **Get API Access**
   - Request API access through your account manager
   - Obtain your API Key, Secret, and Customer Number

3. **Documentation**
   - Refer to [PostNord Developer Guide](https://guide.developer.postnord.com/)

## Configuration

### Basic Settings

Navigate to `Modules > PostNord > Configure`:

- **API Key**: Your PostNord API key
- **API Secret**: Your PostNord API secret
- **Customer Number**: Your PostNord customer number
- **Test Mode**: Enable for testing, disable for production

### Carrier Setup

The module automatically creates PostNord carriers:
- PostNord MyPack (with delivery points)
- PostNord Home Delivery

You can configure shipping costs and zones in `Shipping > Carriers`.

## Usage

### Frontend (Customer)

1. **Delivery Point Selection**
   - During checkout, customers can select PostNord carriers
   - If MyPack is selected, a delivery point selector appears
   - Customers can search by postal code and select their preferred pickup point

### Backend (Admin)

1. **Label Creation**
   - Go to Orders > View order
   - In the PostNord panel, click "Create Shipping Label"
   - The label will be generated and can be downloaded as PDF

2. **Tracking**
   - View tracking information directly in the order panel
   - Track shipment status and delivery progress

## File Structure

```
postnord/
├── postnord.php                          # Main module file
├── config.xml                            # Module configuration
├── classes/
│   ├── PostNordAPI.php                   # API communication class
│   └── PostNordInstaller.php             # Installation helper
├── controllers/
│   └── front/
│       ├── ajax.php                      # Frontend AJAX controller
│       └── admin.php                     # Admin AJAX controller
├── views/
│   ├── templates/
│   │   ├── hook/
│   │   │   └── displayCarrier.tpl        # Delivery point selection template
│   │   └── admin/
│   │       └── order.tpl                 # Admin order template
│   ├── css/
│   │   └── postnord.css                  # Module styles
│   └── js/
│       └── postnord.js                   # Module JavaScript
└── README.md                             # This file
```

## API Integration

The module integrates with the following PostNord APIs:

### Business Location API
- Find delivery points by postal code
- Get delivery point details and opening hours

### Shipment API  
- Create shipments and generate labels
- Track shipment status
- Generate tracking URLs

### Tracking API
- Get detailed tracking information
- Retrieve delivery events and status updates

## Database Tables

The module creates several database tables:

- `ps_postnord_shipments`: Store shipment data and tracking numbers
- `ps_postnord_delivery_points`: Cache delivery point information
- `ps_postnord_tracking_events`: Store tracking events
- `ps_postnord_order_delivery_points`: Map orders to selected delivery points
- `ps_postnord_api_log`: Log API requests for debugging

## Hooks Used

- `displayCarrier`: Show delivery point selection
- `validateOrder`: Save delivery point selection
- `actionOrderStatusUpdate`: Update tracking information
- `displayAdminOrder`: Show admin order panel
- `displayHeader`: Include CSS and JavaScript

## Requirements

- PrestaShop 9.0 or higher
- PHP 7.4 or higher
- cURL extension enabled
- Valid PostNord Business API credentials

## Troubleshooting

### Common Issues

1. **"No API key provided" warning**
   - Solution: Configure your API credentials in module settings

2. **"No delivery points found"**
   - Check if postal code is valid
   - Verify API credentials are correct
   - Check if test mode is properly configured

3. **Label creation fails**
   - Verify customer number is correct
   - Check order address is complete
   - Ensure API credentials have shipment creation permissions

### Debug Mode

Enable API logging by adding this to your `config/defines.inc.php`:

```php
define('POSTNORD_DEBUG', true);
```

This will log all API requests to the `ps_postnord_api_log` table.

### API Testing

Test your API connection using the PostNord API documentation:
- [Business Location API](https://guide.developer.postnord.com/business-location-api)
- [Shipment API](https://guide.developer.postnord.com/shipment-api)

## Support

For technical support:
1. Check the troubleshooting section above
2. Review PostNord API documentation
3. Contact your PostNord account manager for API-related issues

## License

This module is released under the MIT License.

## Changelog

### Version 1.0.0
- Initial release
- Label printing functionality
- Delivery point selection
- Order tracking integration
- Admin order management panel

---

**Note**: This module requires active PostNord Business API credentials. Contact PostNord to set up your business account and API access.
