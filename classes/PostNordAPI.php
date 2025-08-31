<?php
/**
 * PostNord API Class
 * Handles communication with PostNord API services
 */

class PostNordAPI
{
    private $api_key;
    private $api_secret;
    private $customer_number;
    private $test_mode;
    private $base_url;

    public function __construct()
    {
        $this->api_key = Configuration::get('POSTNORD_API_KEY');
        $this->api_secret = Configuration::get('POSTNORD_API_SECRET');
        $this->customer_number = Configuration::get('POSTNORD_CUSTOMER_NUMBER');
        $this->test_mode = Configuration::get('POSTNORD_TEST_MODE');
        
        $this->base_url = $this->test_mode 
            ? 'https://api2.postnord.com/rest/businesslocation/v5'
            : 'https://api2.postnord.com/rest/businesslocation/v5';
    }

    /**
     * Find delivery points near a postal code
     */
    public function findDeliveryPoints($postal_code, $country_code = 'NO', $type = 'servicepoint')
    {
        $url = $this->base_url . '/findNearestByAddress.json';
        
        $params = [
            'apikey' => $this->api_key,
            'countryCode' => $country_code,
            'postalCode' => $postal_code,
            'numberOfServicePoints' => 10,
            'typeId' => $type
        ];

        $response = $this->makeRequest($url, 'GET', $params);
        
        if ($response && isset($response['servicePointInformationResponse']['servicePoints'])) {
            return $this->formatDeliveryPoints($response['servicePointInformationResponse']['servicePoints']);
        }

        return [];
    }

    /**
     * Create a shipment and generate shipping label
     */
    public function createShipment($order, $address, $customer, $service_point_id = null)
    {
        $url = 'https://api2.postnord.com/rest/shipment/v5/shipments';
        
        $shipment_data = [
            'shipments' => [[
                'sender' => $this->getSenderData(),
                'recipient' => $this->getRecipientData($address, $customer),
                'service' => $this->getServiceData($service_point_id),
                'parcels' => $this->getParcelsData($order)
            ]]
        ];

        $headers = [
            'Content-Type: application/json',
            'apikey: ' . $this->api_key,
            'X-Bring-Client-URL: ' . Tools::getShopDomainSsl(true, true)
        ];

        $response = $this->makeRequest($url, 'POST', $shipment_data, $headers);

        if ($response && isset($response['shipments'][0]['packageIds'][0])) {
            $shipment = $response['shipments'][0];
            return [
                'success' => true,
                'shipment_id' => $shipment['shipmentId'],
                'tracking_number' => $shipment['packageIds'][0],
                'label_url' => $this->generateLabelUrl($shipment['packageIds'][0])
            ];
        }

        return [
            'success' => false,
            'error' => isset($response['error']) ? $response['error'] : 'Unknown error'
        ];
    }

    /**
     * Generate label PDF URL
     */
    public function generateLabelUrl($tracking_number)
    {
        return 'https://api2.postnord.com/rest/shipment/v5/labels/' . $tracking_number . '.pdf?apikey=' . $this->api_key;
    }

    /**
     * Track shipment
     */
    public function trackShipment($tracking_number)
    {
        $url = 'https://api2.postnord.com/rest/shipment/v5/trackandtrace/findByIdentifier.json';
        
        $params = [
            'apikey' => $this->api_key,
            'id' => $tracking_number,
            'locale' => 'no'
        ];

        return $this->makeRequest($url, 'GET', $params);
    }

    private function getSenderData()
    {
        return [
            'name' => Configuration::get('PS_SHOP_NAME'),
            'address' => [
                'streetName' => Configuration::get('PS_SHOP_ADDR1'),
                'streetNumber' => '',
                'postalCode' => Configuration::get('PS_SHOP_CODE'),
                'city' => Configuration::get('PS_SHOP_CITY'),
                'countryCode' => Country::getIsoById(Configuration::get('PS_SHOP_COUNTRY_ID'))
            ],
            'contact' => [
                'email' => Configuration::get('PS_SHOP_EMAIL'),
                'phoneNumber' => Configuration::get('PS_SHOP_PHONE')
            ]
        ];
    }

    private function getRecipientData($address, $customer)
    {
        return [
            'name' => $address->firstname . ' ' . $address->lastname,
            'address' => [
                'streetName' => $address->address1,
                'streetNumber' => $address->address2,
                'postalCode' => $address->postcode,
                'city' => $address->city,
                'countryCode' => Country::getIsoById($address->id_country)
            ],
            'contact' => [
                'email' => $customer->email,
                'phoneNumber' => $address->phone ?: $address->phone_mobile
            ]
        ];
    }

    private function getServiceData($service_point_id = null)
    {
        $service = [
            'id' => 'PRIVATE', // Default service
            'customerNumber' => $this->customer_number
        ];

        if ($service_point_id) {
            $service['pickupPoint'] = [
                'id' => $service_point_id
            ];
        }

        return $service;
    }

    private function getParcelsData($order)
    {
        $weight = 0;
        $products = $order->getProducts();
        
        foreach ($products as $product) {
            $weight += $product['weight'] * $product['quantity'];
        }

        // Convert to grams (PostNord expects weight in grams)
        $weight_grams = max(100, $weight * 1000); // Minimum 100g

        return [[
            'dimensions' => [
                'weightInGrams' => (int) $weight_grams,
                'heightInCm' => 10,
                'widthInCm' => 20,
                'lengthInCm' => 30
            ]
        ]];
    }

    private function formatDeliveryPoints($service_points)
    {
        $points = [];
        
        foreach ($service_points as $point) {
            $points[] = [
                'id' => $point['servicePointId'],
                'name' => $point['name'],
                'address' => $point['deliveryAddress']['streetName'] . ' ' . $point['deliveryAddress']['streetNumber'],
                'postal_code' => $point['deliveryAddress']['postalCode'],
                'city' => $point['deliveryAddress']['city'],
                'country_code' => $point['deliveryAddress']['countryCode'],
                'coordinates' => [
                    'latitude' => $point['coordinate']['latitude'],
                    'longitude' => $point['coordinate']['longitude']
                ],
                'distance' => isset($point['distance']) ? $point['distance'] : 0,
                'opening_hours' => isset($point['openingHours']) ? $point['openingHours'] : []
            ];
        }

        return $points;
    }

    private function makeRequest($url, $method = 'GET', $data = null, $headers = [])
    {
        $ch = curl_init();

        $default_headers = [
            'User-Agent: PrestaShop PostNord Module'
        ];

        $headers = array_merge($default_headers, $headers);

        curl_setopt_array($ch, [
            CURLOPT_URL => $method === 'GET' && $data ? $url . '?' . http_build_query($data) : $url,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HTTPHEADER => $headers,
            CURLOPT_TIMEOUT => 30,
            CURLOPT_SSL_VERIFYPEER => false
        ]);

        if ($method === 'POST') {
            curl_setopt($ch, CURLOPT_POST, true);
            if ($data) {
                curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
            }
        }

        $response = curl_exec($ch);
        $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($http_code === 200) {
            return json_decode($response, true);
        }

        return false;
    }
}
