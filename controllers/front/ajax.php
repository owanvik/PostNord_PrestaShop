<?php
/**
 * PostNord Frontend Controller
 * Handles AJAX requests for delivery point selection
 */

class PostNordAjaxModuleFrontController extends ModuleFrontController
{
    public function init()
    {
        parent::init();
        
        if (!$this->ajax) {
            Tools::redirect('index.php');
        }
    }

    public function displayAjax()
    {
        $action = Tools::getValue('action');
        
        switch ($action) {
            case 'getDeliveryPoints':
                $this->getDeliveryPoints();
                break;
            case 'selectDeliveryPoint':
                $this->selectDeliveryPoint();
                break;
            default:
                $this->ajaxResponse(['error' => 'Invalid action']);
        }
    }

    private function getDeliveryPoints()
    {
        $postal_code = Tools::getValue('postal_code');
        $country_code = Tools::getValue('country_code', 'NO');

        if (!$postal_code) {
            $this->ajaxResponse(['error' => 'Postal code is required']);
        }

        $api = new PostNordAPI();
        $delivery_points = $api->findDeliveryPoints($postal_code, $country_code);

        if (empty($delivery_points)) {
            $this->ajaxResponse(['error' => 'No delivery points found']);
        }

        $this->ajaxResponse(['success' => true, 'delivery_points' => $delivery_points]);
    }

    private function selectDeliveryPoint()
    {
        $delivery_point_id = Tools::getValue('delivery_point_id');
        $delivery_point_name = Tools::getValue('delivery_point_name');
        $delivery_point_address = Tools::getValue('delivery_point_address');

        if (!$delivery_point_id) {
            $this->ajaxResponse(['error' => 'Delivery point ID is required']);
        }

        // Store selected delivery point in session
        $this->context->cookie->postnord_delivery_point_id = $delivery_point_id;
        $this->context->cookie->postnord_delivery_point_name = $delivery_point_name;
        $this->context->cookie->postnord_delivery_point_address = $delivery_point_address;
        $this->context->cookie->write();

        $this->ajaxResponse(['success' => true]);
    }

    private function ajaxResponse($data)
    {
        header('Content-Type: application/json');
        echo json_encode($data);
        exit;
    }
}
