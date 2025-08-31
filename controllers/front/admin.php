<?php
/**
 * PostNord Admin Controller
 * Handles admin functions like label generation
 */

class PostNordAdminModuleFrontController extends ModuleFrontController
{
    public function init()
    {
        parent::init();
        
        // Check if user is admin
        if (!$this->context->employee || !$this->context->employee->id) {
            Tools::redirect('index.php');
        }
        
        if (!$this->ajax) {
            Tools::redirect('index.php');
        }
    }

    public function displayAjax()
    {
        $action = Tools::getValue('action');
        
        switch ($action) {
            case 'createLabel':
                $this->createLabel();
                break;
            case 'downloadLabel':
                $this->downloadLabel();
                break;
            case 'trackShipment':
                $this->trackShipment();
                break;
            default:
                $this->ajaxResponse(['error' => 'Invalid action']);
        }
    }

    private function createLabel()
    {
        $id_order = (int) Tools::getValue('id_order');
        
        if (!$id_order) {
            $this->ajaxResponse(['error' => 'Order ID is required']);
        }

        $order = new Order($id_order);
        if (!Validate::isLoadedObject($order)) {
            $this->ajaxResponse(['error' => 'Invalid order']);
        }

        // Check if label already exists
        $existing_shipment = $this->module->getShipmentByOrderId($id_order);
        if ($existing_shipment && !empty($existing_shipment['tracking_number'])) {
            $this->ajaxResponse(['error' => 'Label already exists for this order']);
        }

        // Get delivery point from order (if selected during checkout)
        $service_point_id = $this->getOrderDeliveryPoint($id_order);

        // Create shipment
        $result = $this->module->createShipment($id_order, $service_point_id);
        
        if ($result) {
            $shipment = $this->module->getShipmentByOrderId($id_order);
            $this->ajaxResponse([
                'success' => true,
                'tracking_number' => $shipment['tracking_number'],
                'label_url' => $shipment['label_url']
            ]);
        } else {
            $this->ajaxResponse(['error' => 'Failed to create shipment']);
        }
    }

    private function downloadLabel()
    {
        $id_order = (int) Tools::getValue('id_order');
        
        if (!$id_order) {
            $this->ajaxResponse(['error' => 'Order ID is required']);
        }

        $shipment = $this->module->getShipmentByOrderId($id_order);
        
        if (!$shipment || empty($shipment['label_url'])) {
            $this->ajaxResponse(['error' => 'No label found for this order']);
        }

        // Redirect to label URL or serve the PDF
        header('Location: ' . $shipment['label_url']);
        exit;
    }

    private function trackShipment()
    {
        $tracking_number = Tools::getValue('tracking_number');
        
        if (!$tracking_number) {
            $this->ajaxResponse(['error' => 'Tracking number is required']);
        }

        $api = new PostNordAPI();
        $tracking_info = $api->trackShipment($tracking_number);

        if ($tracking_info) {
            $this->ajaxResponse(['success' => true, 'tracking_info' => $tracking_info]);
        } else {
            $this->ajaxResponse(['error' => 'Failed to track shipment']);
        }
    }

    private function getOrderDeliveryPoint($id_order)
    {
        // Try to get delivery point from order meta or custom field
        $sql = 'SELECT `value` FROM `' . _DB_PREFIX_ . 'order_detail_meta` 
                WHERE `id_order` = ' . (int) $id_order . ' AND `key` = "postnord_delivery_point_id"';
        
        $result = Db::getInstance()->getValue($sql);
        
        return $result ?: null;
    }

    private function ajaxResponse($data)
    {
        header('Content-Type: application/json');
        echo json_encode($data);
        exit;
    }
}
