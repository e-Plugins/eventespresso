<?php
if (! defined('EVENT_ESPRESSO_VERSION')) {
    exit('No direct script access allowed');
}
require_once (EE_DIGIWALLET_PLUGIN_PATH . '/includes/digiwallet.class.gateway.php');

/**
 * EEG_Afterpay
 *
 * Afterpay Banking plugin for Event Espresso 4
 *
 */
class EEG_Afterpay extends Digiwallet_Gateway
{
    public $tpPaymethodName = 'Afterpay';

    public $tpPaymethodId = 'AFP';
    
    /**
     * Event handler to attach additional parameters.
     *
     * @param $payment
     * @param DigiwalletCore $digiwallet
     */
    public function additionalParameters($payment, $digiwallet)
    {
        $attendee = $payment->get_primary_attendee();
        $transaction = $payment->transaction();
        
        /** @type EE_Line_Item $total_line_item */
        $total_line_item = $transaction->total_line_item();
        
        //only itemize the order if we're paying for the rest of the order's amount
        if( EEH_Money::compare_floats( $payment->amount(), $transaction->total(), '==' ) ) {
            foreach($total_line_item->get_items() as $line_item){
                if ( $line_item instanceof EE_Line_Item ) {
                    $productCode = $line_item->ID();
                    $product_name = $line_item->name() . ' ' . $line_item->desc();
                    $item_quantity = $line_item->quantity();
                    $item_total = $line_item->total();
                    $tax = 0;
                    
                    $invoicelines[] = [
                        'productCode' => (string)$productCode,
                        'productDescription' => $product_name,
                        'quantity' => $item_quantity,
                        'price' => $item_total,
                        'taxCategory' => $digiwallet->getTax($tax),
                    ];
                }
            }
        }
        
        $billingCountry = $shippingCountry = (strtoupper($attendee->country_ID()) == 'BE' ? 'BEL' : 'NLD');
        $streetParts = self::breakDownStreet($attendee->address());
        
        $digiwallet->bindParam('billingstreet', $streetParts['street']);
        $digiwallet->bindParam('billinghousenumber', $streetParts['houseNumber'].$streetParts['houseNumberAdd']);
        $digiwallet->bindParam('billingpostalcode', $attendee->zip());
        $digiwallet->bindParam('billingcity', $attendee->city());
        $digiwallet->bindParam('billingpersonemail', $attendee->email());
        $digiwallet->bindParam('billingpersoninitials', "");
        $digiwallet->bindParam('billingpersongender', "");
        $digiwallet->bindParam('billingpersonbirthdate', "");
        $digiwallet->bindParam('billingpersonsurname', $attendee->lname());
        $digiwallet->bindParam('billingcountrycode', $billingCountry);
        $digiwallet->bindParam('billingpersonlanguagecode', $billingCountry);
        $digiwallet->bindParam('billingpersonphonenumber', self::format_phone($billingCountry, $attendee->phone()));
        
        $digiwallet->bindParam('shippingstreet', $streetParts['street']);
        $digiwallet->bindParam('shippinghousenumber', $streetParts['houseNumber'].$streetParts['houseNumberAdd']);
        $digiwallet->bindParam('shippingpostalcode', $attendee->zip());
        $digiwallet->bindParam('shippingcity', $attendee->city());
        $digiwallet->bindParam('shippingpersonemail', $attendee->email());
        $digiwallet->bindParam('shippingpersoninitials', "");
        $digiwallet->bindParam('shippingpersongender', "");
        $digiwallet->bindParam('shippingpersonbirthdate', "");
        $digiwallet->bindParam('shippingpersonsurname', $attendee->lname());
        $digiwallet->bindParam('shippingcountrycode', $shippingCountry);
        $digiwallet->bindParam('shippingpersonlanguagecode', $shippingCountry);
        $digiwallet->bindParam('shippingpersonphonenumber', self::format_phone($shippingCountry, $attendee->phone()));
        
        $digiwallet->bindParam('invoicelines', json_encode($invoicelines));
        $digiwallet->bindParam('userip', $_SERVER["REMOTE_ADDR"]);
    }
    
    private static function format_phone($country, $phone) {
        $function = 'format_phone_' . strtolower($country);
        if(method_exists('EEG_Afterpay', $function)) {
            return self::$function($phone);
        } else {
            echo "unknown phone formatter for country: ". $function;
            exit;
        }
        return $phone;
    }
    
    private static function format_phone_nld($phone) {
        // note: making sure we have something
        if(!isset($phone{3})) { return ''; }
        // note: strip out everything but numbers
        $phone = preg_replace("/[^0-9]/", "", $phone);
        $length = strlen($phone);
        switch($length) {
            case 9:
                return "+31".$phone;
                break;
            case 10:
                return "+31".substr($phone, 1);
                break;
            case 11:
            case 12:
                return "+".$phone;
                break;
            default:
                return $phone;
                break;
        }
    }
    
    private static function format_phone_bel($phone) {
        // note: making sure we have something
        if(!isset($phone{3})) { return ''; }
        // note: strip out everything but numbers
        $phone = preg_replace("/[^0-9]/", "", $phone);
        $length = strlen($phone);
        switch($length) {
            case 9:
                return "+32".$phone;
                break;
            case 10:
                return "+32".substr($phone, 1);
                break;
            case 11:
            case 12:
                return "+".$phone;
                break;
            default:
                return $phone;
                break;
        }
    }
    
    private static function breakDownStreet($street)
    {
        $out = [];
        $addressResult = null;
        preg_match("/(?P<address>\D+) (?P<number>\d+) (?P<numberAdd>.*)/", $street, $addressResult);
        if(!$addressResult) {
            preg_match("/(?P<address>\D+) (?P<number>\d+)/", $street, $addressResult);
        }
        $out['street'] = array_key_exists('address', $addressResult) ? $addressResult['address'] : null;
        $out['houseNumber'] = array_key_exists('number', $addressResult) ? $addressResult['number'] : null;
        $out['houseNumberAdd'] = array_key_exists('numberAdd', $addressResult) ? trim(strtoupper($addressResult['numberAdd'])) : null;
        return $out;
    }
}
