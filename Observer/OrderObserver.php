<?php
namespace Pakettikauppa\Logistics\Observer;

use Magento\Framework\Event\ObserverInterface;
use Magento\Framework\Event\Observer;
use Magento\Sales\Api\Data\OrderInterface;
use Magento\Checkout\Model\Cart;
use Pakettikauppa\Logistics\Helper\Api;
use Pakettikauppa\Logistics\Helper\Data;
use Psr\Log\LoggerInterface;


class OrderObserver implements ObserverInterface
{
    /**
     * custom event handler
     *
     * @param \Magento\Framework\Event\Observer $observer
     * @return void
     */
    protected $_order;

    public function __construct(
        OrderInterface $order,
        LoggerInterface $logger,
        Cart $cart,
        Api $apiHelper,
        Data $dataHelper
    ) {
        $this->_order = $order;
        $this->logger = $logger;
        $this->cart = $cart;
        $this->apiHelper = $apiHelper;
        $this->dataHelper = $dataHelper;
    }
    public function execute(Observer $observer)
    {
        try{
            $quote = $this->cart->getQuote();
            $order = $observer->getOrder();
            $shipping_method_code = $quote->getShippingAddress()->getShippingMethod();

            if($this->dataHelper->isPakettikauppa($shipping_method_code)){

                $method = $this->dataHelper->getMethod($shipping_method_code);
                $homedelivery_methods = $this->apiHelper->getHomeDelivery(true);
                $method_available = false;

                if($method == 'pktkp_pickuppoint'){
                   $zip = $this->dataHelper->getZip();
                   $pickup_methods = $this->apiHelper->getPickuppoints($zip);
                   $pickuppoint_zip = $quote->getData('pickuppoint_zip');

                   foreach($pickup_methods as $pickup_method){
                     if('pktkp_pickuppoint_'.$pickup_method->pickup_point_id == $shipping_method_code){
                        $order->setData('pickuppoint_zip', $pickuppoint_zip);
                        $order->setData('pickup_point_provider', $pickup_method->provider);
                        $order->setData('pickup_point_id', $pickup_method->pickup_point_id);
                        $order->setData('pickup_point_name', $pickup_method->name);
                        $order->setData('pickup_point_street_address', $pickup_method->street_address);
                        $order->setData('pickup_point_postcode', $pickup_method->postcode);
                        $order->setData('pickup_point_city', $pickup_method->city);
                        $order->setData('pickup_point_country', $pickup_method->country);
                        $order->setData('pickup_point_description', $pickup_method->description);
                        $pktkp_smc = $this->dataHelper->getPickupPointServiceCode($homedelivery_methods, $pickup_method->provider);
                        $order->setData('paketikauppa_smc', $pktkp_smc);
                        $method_available = true;
                     }
                   }
                 }

                 // HOME DELIVERY
                 if($method == 'pktkp_homedelivery'){
                   foreach($homedelivery_methods as $homedelivery_method){
                     if('pktkp_homedelivery_'.$homedelivery_method->shipping_method_code == $shipping_method_code){
                       $order->setData('home_delivery_service_provider', $homedelivery_method->service_provider);
                       $order->setData('paketikauppa_smc', $homedelivery_method->shipping_method_code);
                       $method_available = true;
                     }
                   }
                 }

                 if(!$method_available){
                   $this->logger->critical('Method error, please choose another method.');
                 }
           }
        }catch(Exception $e){
            $this->logger->critical($e->getMessage());
        }
    }
}
