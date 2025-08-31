<?php
/**
 * PostNord Module Installation Helper
 * Additional installation and upgrade functions
 */

class PostNordInstaller
{
    public static function install()
    {
        return self::createTables() && self::addOrderStates() && self::createCarriers();
    }

    public static function uninstall()
    {
        return self::deleteTables() && self::removeOrderStates();
    }

    private static function createTables()
    {
        $sql = [];

        // Extended shipment tracking table
        $sql[] = 'CREATE TABLE IF NOT EXISTS `' . _DB_PREFIX_ . 'postnord_tracking_events` (
            `id_event` int(11) NOT NULL AUTO_INCREMENT,
            `id_shipment` int(11) NOT NULL,
            `event_code` varchar(50) NOT NULL,
            `event_description` text NOT NULL,
            `event_time` datetime NOT NULL,
            `location_name` varchar(255),
            `location_code` varchar(50),
            `date_add` datetime NOT NULL,
            PRIMARY KEY (`id_event`),
            KEY `id_shipment` (`id_shipment`)
        ) ENGINE=' . _MYSQL_ENGINE_ . ' DEFAULT CHARSET=utf8;';

        // Order delivery point mapping
        $sql[] = 'CREATE TABLE IF NOT EXISTS `' . _DB_PREFIX_ . 'postnord_order_delivery_points` (
            `id_order` int(11) NOT NULL,
            `service_point_id` varchar(255) NOT NULL,
            `service_point_name` varchar(255) NOT NULL,
            `service_point_address` text NOT NULL,
            `date_add` datetime NOT NULL,
            PRIMARY KEY (`id_order`)
        ) ENGINE=' . _MYSQL_ENGINE_ . ' DEFAULT CHARSET=utf8;';

        // API log table for debugging
        $sql[] = 'CREATE TABLE IF NOT EXISTS `' . _DB_PREFIX_ . 'postnord_api_log` (
            `id_log` int(11) NOT NULL AUTO_INCREMENT,
            `request_type` varchar(50) NOT NULL,
            `request_data` text,
            `response_data` text,
            `response_code` int(3),
            `execution_time` decimal(10,3),
            `date_add` datetime NOT NULL,
            PRIMARY KEY (`id_log`),
            KEY `request_type` (`request_type`),
            KEY `date_add` (`date_add`)
        ) ENGINE=' . _MYSQL_ENGINE_ . ' DEFAULT CHARSET=utf8;';

        foreach ($sql as $query) {
            if (!Db::getInstance()->execute($query)) {
                return false;
            }
        }

        return true;
    }

    private static function deleteTables()
    {
        $sql = [
            'DROP TABLE IF EXISTS `' . _DB_PREFIX_ . 'postnord_tracking_events`',
            'DROP TABLE IF EXISTS `' . _DB_PREFIX_ . 'postnord_order_delivery_points`',
            'DROP TABLE IF EXISTS `' . _DB_PREFIX_ . 'postnord_api_log`'
        ];

        foreach ($sql as $query) {
            if (!Db::getInstance()->execute($query)) {
                return false;
            }
        }

        return true;
    }

    private static function addOrderStates()
    {
        // Create custom order states for PostNord
        $states = [
            [
                'name' => 'Label Created',
                'color' => '#32CD32',
                'logable' => true,
                'shipped' => false,
                'paid' => false,
                'invoice' => false,
                'delivery' => false,
                'send_email' => false,
                'module_name' => 'postnord'
            ],
            [
                'name' => 'In Transit',
                'color' => '#FF8C00',
                'logable' => true,
                'shipped' => true,
                'paid' => false,
                'invoice' => false,
                'delivery' => false,
                'send_email' => true,
                'module_name' => 'postnord'
            ]
        ];

        foreach ($states as $state_data) {
            $state = new OrderState();
            $state->name = [];
            
            foreach (Language::getLanguages() as $language) {
                $state->name[$language['id_lang']] = $state_data['name'];
            }
            
            $state->color = $state_data['color'];
            $state->logable = $state_data['logable'];
            $state->shipped = $state_data['shipped'];
            $state->paid = $state_data['paid'];
            $state->invoice = $state_data['invoice'];
            $state->delivery = $state_data['delivery'];
            $state->send_email = $state_data['send_email'];
            $state->module_name = $state_data['module_name'];

            if (!$state->add()) {
                return false;
            }

            // Save state ID for later use
            Configuration::updateValue('POSTNORD_OS_' . strtoupper(str_replace(' ', '_', $state_data['name'])), $state->id);
        }

        return true;
    }

    private static function removeOrderStates()
    {
        $states = [
            'POSTNORD_OS_LABEL_CREATED',
            'POSTNORD_OS_IN_TRANSIT'
        ];

        foreach ($states as $state_config) {
            $state_id = Configuration::get($state_config);
            if ($state_id) {
                $state = new OrderState($state_id);
                if (Validate::isLoadedObject($state)) {
                    $state->delete();
                }
                Configuration::deleteByName($state_config);
            }
        }

        return true;
    }

    private static function createCarriers()
    {
        // Create PostNord carriers
        $carriers = [
            [
                'name' => 'PostNord MyPack',
                'delay' => '2-4 business days',
                'grade' => 1,
                'url' => 'https://www.postnord.no/sporing/sportokollipakke/@',
                'active' => true,
                'shipping_handling' => Carrier::SHIPPING_METHOD_WEIGHT,
                'range_behavior' => 0,
                'is_module' => true,
                'external_module_name' => 'postnord',
                'need_range' => true,
                'shipping_method' => 2
            ],
            [
                'name' => 'PostNord Home Delivery',
                'delay' => '1-3 business days',
                'grade' => 2,
                'url' => 'https://www.postnord.no/sporing/sportokollipakke/@',
                'active' => true,
                'shipping_handling' => Carrier::SHIPPING_METHOD_WEIGHT,
                'range_behavior' => 0,
                'is_module' => true,
                'external_module_name' => 'postnord',
                'need_range' => true,
                'shipping_method' => 2
            ]
        ];

        foreach ($carriers as $carrier_data) {
            $carrier = new Carrier();
            $carrier->name = $carrier_data['name'];
            $carrier->delay = [];
            
            foreach (Language::getLanguages() as $language) {
                $carrier->delay[$language['id_lang']] = $carrier_data['delay'];
            }
            
            $carrier->grade = $carrier_data['grade'];
            $carrier->url = $carrier_data['url'];
            $carrier->active = $carrier_data['active'];
            $carrier->shipping_handling = $carrier_data['shipping_handling'];
            $carrier->range_behavior = $carrier_data['range_behavior'];
            $carrier->is_module = $carrier_data['is_module'];
            $carrier->external_module_name = $carrier_data['external_module_name'];
            $carrier->need_range = $carrier_data['need_range'];
            $carrier->shipping_method = $carrier_data['shipping_method'];

            if ($carrier->add()) {
                // Add carrier to all groups and zones
                $groups = Group::getGroups(true);
                foreach ($groups as $group) {
                    Db::getInstance()->insert('carrier_group', [
                        'id_carrier' => $carrier->id,
                        'id_group' => $group['id_group']
                    ]);
                }

                // Add weight ranges
                $range_weight = new RangeWeight();
                $range_weight->id_carrier = $carrier->id;
                $range_weight->delimiter1 = '0';
                $range_weight->delimiter2 = '30';
                $range_weight->add();

                // Save carrier ID
                $config_name = 'POSTNORD_CARRIER_' . strtoupper(str_replace(' ', '_', $carrier_data['name']));
                Configuration::updateValue($config_name, $carrier->id);
            }
        }

        return true;
    }

    public static function updateModule($version)
    {
        // Handle module updates based on version
        switch ($version) {
            case '1.0.1':
                // Add any 1.0.1 specific updates
                break;
            case '1.1.0':
                // Add any 1.1.0 specific updates
                break;
        }

        return true;
    }
}
