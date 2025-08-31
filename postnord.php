<?php
/**
 * PostNord Module for PrestaShop 9.0
 * 
 * @author Your Name
 * @version 1.0.0
 * @license MIT
 */

if (!defined('_PS_VERSION_')) {
    exit;
}

class PostNord extends Module
{
    public function __construct()
    {
        $this->name = 'postnord';
        $this->tab = 'shipping_logistics';
        $this->version = '1.0.0';
        $this->author = 'Your Company';
        $this->need_instance = 0;
        $this->ps_versions_compliancy = ['min' => '9.0.0', 'max' => _PS_VERSION_];
        $this->bootstrap = true;

        parent::__construct();

        $this->displayName = $this->l('PostNord Shipping');
        $this->description = $this->l('Integrate PostNord shipping services with label printing and delivery points.');
        $this->confirmUninstall = $this->l('Are you sure you want to uninstall PostNord module?');

        if (!Configuration::get('POSTNORD_API_KEY')) {
            $this->warning = $this->l('No API key provided');
        }
    }

    public function install()
    {
        if (Shop::isFeatureActive()) {
            Shop::setContext(Shop::CONTEXT_ALL);
        }

        return parent::install() &&
            $this->registerHook('displayCarrier') &&
            $this->registerHook('validateOrder') &&
            $this->registerHook('actionOrderStatusUpdate') &&
            $this->registerHook('displayAdminOrder') &&
            $this->registerHook('displayHeader') &&
            $this->createTables();
    }

    public function uninstall()
    {
        return parent::uninstall() &&
            Configuration::deleteByName('POSTNORD_API_KEY') &&
            Configuration::deleteByName('POSTNORD_API_SECRET') &&
            Configuration::deleteByName('POSTNORD_CUSTOMER_NUMBER') &&
            Configuration::deleteByName('POSTNORD_TEST_MODE') &&
            $this->deleteTables();
    }

    private function createTables()
    {
        $sql = [];

        // Table for storing delivery points
        $sql[] = 'CREATE TABLE IF NOT EXISTS `' . _DB_PREFIX_ . 'postnord_delivery_points` (
            `id_delivery_point` int(11) NOT NULL AUTO_INCREMENT,
            `service_point_id` varchar(255) NOT NULL,
            `name` varchar(255) NOT NULL,
            `address` text NOT NULL,
            `postal_code` varchar(10) NOT NULL,
            `city` varchar(100) NOT NULL,
            `country_code` varchar(2) NOT NULL,
            `opening_hours` text,
            `coordinates` varchar(50),
            `distance` decimal(10,2),
            PRIMARY KEY (`id_delivery_point`),
            UNIQUE KEY `service_point_id` (`service_point_id`)
        ) ENGINE=' . _MYSQL_ENGINE_ . ' DEFAULT CHARSET=utf8;';

        // Table for storing shipment data
        $sql[] = 'CREATE TABLE IF NOT EXISTS `' . _DB_PREFIX_ . 'postnord_shipments` (
            `id_shipment` int(11) NOT NULL AUTO_INCREMENT,
            `id_order` int(11) NOT NULL,
            `shipment_id` varchar(255),
            `tracking_number` varchar(255),
            `label_url` text,
            `service_point_id` varchar(255),
            `status` varchar(50) DEFAULT "created",
            `date_add` datetime NOT NULL,
            `date_upd` datetime NOT NULL,
            PRIMARY KEY (`id_shipment`),
            KEY `id_order` (`id_order`)
        ) ENGINE=' . _MYSQL_ENGINE_ . ' DEFAULT CHARSET=utf8;';

        foreach ($sql as $query) {
            if (!Db::getInstance()->execute($query)) {
                return false;
            }
        }

        return true;
    }

    private function deleteTables()
    {
        $sql = [
            'DROP TABLE IF EXISTS `' . _DB_PREFIX_ . 'postnord_delivery_points`',
            'DROP TABLE IF EXISTS `' . _DB_PREFIX_ . 'postnord_shipments`'
        ];

        foreach ($sql as $query) {
            if (!Db::getInstance()->execute($query)) {
                return false;
            }
        }

        return true;
    }

    public function getContent()
    {
        $output = null;

        if (Tools::isSubmit('submit' . $this->name)) {
            $api_key = (string) Tools::getValue('POSTNORD_API_KEY');
            $api_secret = (string) Tools::getValue('POSTNORD_API_SECRET');
            $customer_number = (string) Tools::getValue('POSTNORD_CUSTOMER_NUMBER');
            $test_mode = (bool) Tools::getValue('POSTNORD_TEST_MODE');

            if (!$api_key || empty($api_key) || !Validate::isGenericName($api_key)) {
                $output .= $this->displayError($this->l('Invalid API Key'));
            } else {
                Configuration::updateValue('POSTNORD_API_KEY', $api_key);
                Configuration::updateValue('POSTNORD_API_SECRET', $api_secret);
                Configuration::updateValue('POSTNORD_CUSTOMER_NUMBER', $customer_number);
                Configuration::updateValue('POSTNORD_TEST_MODE', $test_mode);
                $output .= $this->displayConfirmation($this->l('Settings updated'));
            }
        }

        return $output . $this->displayForm();
    }

    public function displayForm()
    {
        $default_lang = (int) Configuration::get('PS_LANG_DEFAULT');

        $fields_form[0]['form'] = [
            'legend' => [
                'title' => $this->l('PostNord Settings'),
            ],
            'input' => [
                [
                    'type' => 'text',
                    'label' => $this->l('API Key'),
                    'name' => 'POSTNORD_API_KEY',
                    'size' => 20,
                    'required' => true
                ],
                [
                    'type' => 'text',
                    'label' => $this->l('API Secret'),
                    'name' => 'POSTNORD_API_SECRET',
                    'size' => 20,
                    'required' => true
                ],
                [
                    'type' => 'text',
                    'label' => $this->l('Customer Number'),
                    'name' => 'POSTNORD_CUSTOMER_NUMBER',
                    'size' => 20,
                    'required' => true
                ],
                [
                    'type' => 'switch',
                    'label' => $this->l('Test Mode'),
                    'name' => 'POSTNORD_TEST_MODE',
                    'is_bool' => true,
                    'values' => [
                        [
                            'id' => 'active_on',
                            'value' => true,
                            'label' => $this->l('Enabled')
                        ],
                        [
                            'id' => 'active_off',
                            'value' => false,
                            'label' => $this->l('Disabled')
                        ]
                    ],
                ]
            ],
            'submit' => [
                'title' => $this->l('Save'),
                'class' => 'btn btn-default pull-right'
            ]
        ];

        $helper = new HelperForm();

        $helper->module = $this;
        $helper->name_controller = $this->name;
        $helper->token = Tools::getAdminTokenLite('AdminModules');
        $helper->currentIndex = AdminController::$currentIndex . '&configure=' . $this->name;

        $helper->default_form_language = $default_lang;
        $helper->allow_employee_form_lang = $default_lang;

        $helper->title = $this->displayName;
        $helper->show_toolbar = true;
        $helper->toolbar_scroll = true;
        $helper->submit_action = 'submit' . $this->name;
        $helper->toolbar_btn = [
            'save' => [
                'desc' => $this->l('Save'),
                'href' => AdminController::$currentIndex . '&configure=' . $this->name . '&save' . $this->name .
                '&token=' . Tools::getAdminTokenLite('AdminModules'),
            ],
            'back' => [
                'href' => AdminController::$currentIndex . '&token=' . Tools::getAdminTokenLite('AdminModules'),
                'desc' => $this->l('Back to list')
            ]
        ];

        $helper->fields_value['POSTNORD_API_KEY'] = Configuration::get('POSTNORD_API_KEY');
        $helper->fields_value['POSTNORD_API_SECRET'] = Configuration::get('POSTNORD_API_SECRET');
        $helper->fields_value['POSTNORD_CUSTOMER_NUMBER'] = Configuration::get('POSTNORD_CUSTOMER_NUMBER');
        $helper->fields_value['POSTNORD_TEST_MODE'] = Configuration::get('POSTNORD_TEST_MODE');

        return $helper->generateForm($fields_form);
    }

    public function hookDisplayCarrier($params)
    {
        $carrier = $params['carrier'];
        
        // Only show delivery points for PostNord carriers
        if (strpos(strtolower($carrier['name']), 'postnord') === false) {
            return '';
        }

        $this->context->smarty->assign([
            'postnord_ajax_url' => $this->context->link->getModuleLink($this->name, 'ajax'),
        ]);

        return $this->display(__FILE__, 'views/templates/hook/displayCarrier.tpl');
    }

    public function hookDisplayAdminOrder($params)
    {
        $id_order = $params['id_order'];
        $order = new Order($id_order);
        
        // Get shipment data
        $shipment = $this->getShipmentByOrderId($id_order);
        
        $this->context->smarty->assign([
            'order' => $order,
            'shipment' => $shipment,
            'can_create_label' => !$shipment || empty($shipment['tracking_number']),
            'module_dir' => $this->_path,
            'ajax_url' => $this->context->link->getModuleLink($this->name, 'admin'),
        ]);

        return $this->display(__FILE__, 'views/templates/admin/order.tpl');
    }

    public function hookDisplayHeader()
    {
        $this->context->controller->addCSS($this->_path . 'views/css/postnord.css');
        $this->context->controller->addJS($this->_path . 'views/js/postnord.js');
    }

    private function getShipmentByOrderId($id_order)
    {
        $sql = 'SELECT * FROM `' . _DB_PREFIX_ . 'postnord_shipments` WHERE `id_order` = ' . (int) $id_order;
        return Db::getInstance()->getRow($sql);
    }

    public function createShipment($id_order, $service_point_id = null)
    {
        $order = new Order($id_order);
        $address = new Address($order->id_address_delivery);
        $customer = new Customer($order->id_customer);

        $api = new PostNordAPI();
        $result = $api->createShipment($order, $address, $customer, $service_point_id);

        if ($result['success']) {
            // Save shipment data
            $sql = 'INSERT INTO `' . _DB_PREFIX_ . 'postnord_shipments` 
                    (`id_order`, `shipment_id`, `tracking_number`, `label_url`, `service_point_id`, `date_add`, `date_upd`)
                    VALUES (' . (int) $id_order . ', "' . pSQL($result['shipment_id']) . '", 
                    "' . pSQL($result['tracking_number']) . '", "' . pSQL($result['label_url']) . '", 
                    "' . pSQL($service_point_id) . '", NOW(), NOW())';
            
            return Db::getInstance()->execute($sql);
        }

        return false;
    }
}
