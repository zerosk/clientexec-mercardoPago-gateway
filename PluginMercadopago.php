<?php
require_once 'modules/admin/models/GatewayPlugin.php';
require_once 'modules/billing/models/class.gateway.plugin.php';
require_once 'modules/billing/models/Currency.php';
require_once 'plugins/gateways/mercadopago/vendor/autoload.php';


/**
* @package Plugins
*/
class PluginMercadopago extends GatewayPlugin
{
    function getVariables()
    {
        $variables = array(
            lang('Plugin Name') => array(
                'type'        => 'hidden',
                'description' => lang('How CE sees this plugin (not to be confused with the Signup Name)'),
                'value'       => lang('Mercadopago')
            ),
            lang('Invoice After Signup') => array(
                'type'        => 'yesno',
                'description' => lang('Select YES if you want an invoice sent to the client after signup is complete.'),
                'value'       => '1'
            ),
            lang('Access Token') => array(
                'type'          => 'text',
                'description'   => 'Access Token',
                'value'         =>''
            ),
            lang('Public Key') => array(
                'type'          => 'text',
                'description'   => 'Public Key',
                'value'         =>''
            ),
            lang('Client ID') => array(
                'type'          => 'text',
                'description'   => 'ID de cliente',
                'value'         =>''
            ),
            lang('Client Secret') => array(
                'type'          => 'text',
                'description'   => 'Client password',
                'value'         =>''
            ),
            lang('Modo de prueba') => array(
                'type'        => 'yesno',
                'description' => lang('Activar modo de pruebas'),
                'value'       => ''
            ),
            lang('Test Token') => array(
                'type'        => 'text',
                'description' => lang('Token de prueba'),
                'value'       => ''
            ),
            lang('Test public Key') => array(
                'type'          => 'text',
                'description'   => 'Test public Key',
                'value'         =>''
            ),
            lang('Form') => array(
                'type'        => 'hidden',
                'description' => lang('Has a form to be loaded?  1 = YES, 0 = NO'),
                'value'       => '1'
            )            

        );

        return $variables;
    }


    
    function credit($params)
    {
    }

    function singlePayment($params)
    {

    }


    function singlePaymentold ($params)
    {

        include_once 'modules/billing/models/Currency.php';

        $return_url = mb_substr($params['clientExecURL'], -1, 1) == "//" ? $params['clientExecURL']."plugins/gateways/mercadopago/callback.php" : $params['clientExecURL']."/plugins/gateways/mercadopago/callback.php";
        
        
        if ($this->getVariable('Test Mode') == '1') {
            $accessToken = $this->getVariable('Test Token');
        } else {
            $accessToken = $this->getVariable('Live Secret Key');
        }
        
        $accessToken =  $this->getVariable('Access Token');
        MercadoPago\SDK::setAccessToken($accessToken);

        // Crea un objeto de preferencia
        $preference = new MercadoPago\Preference();

        // Crea un ítem en la preferencia
        $item = new MercadoPago\Item();
        $item->title = $params['companyName']." Invoice · " . $params['invoiceNumber'];
        $item->quantity = 1;
        $item->unit_price = sprintf("%01.2f", round($params['invoiceTotal'], 2));
        $preference->items = array($item);
        $preference->auto_return = 'all';
        $preference->binary_mode = true;
        $preference->external_reference = $params['invoiceNumber'];
        $preference->notification_url = $return_url;
        $preference->player = [
            'name' => $params["userFirstName"],
            'surname' => $params["userLastName"],
            'email' => $params["userEmail"],
            'phone' => [
                'area_code' => "+52",
                'number' => $params["userPhone"]
            ],
            'address' => [
                'street_name' => $params["userAddress"],
                'street_number' => "",
                'zip_code' => $params["userZipcode"]
            ]
        ];
        $preference->back_urls = array(
            "success" => "http://localhost:8080/feedback",
            "failure" => "http://localhost:8080/feedback", 
            "pending" => "http://localhost:8080/feedback"
        );
        

        $preference->save();
        $publicKey = $params['plugin_mercadopago_Public Key'];
        $clientId = $params['plugin_mercadopago_Client ID'];
        //
        //2475335432168087
        //APP_USR-2daeb1d3-64ad-4df1-9f85-57b08e48a6cf
  
    }

    public function getForm($params)
    {

        if ($this->getVariable('Modo de prueba') == '1') {
            $accessToken = $this->getVariable('Test Token');
            $this->view->publicKey = $this->getVariable('Test public Key');
        } else {
            $accessToken = $this->getVariable('Live Secret Key');
            $this->view->publicKey = $this->getVariable('Public Key');
        }


        $notification_url =  CE_Lib::getSoftwareURL() . '/plugins/gateways/mercadopago/callback.php?source_news=webhooks';

        MercadoPago\SDK::setAccessToken($accessToken);
        MercadoPago\SDK::setIntegratorId("dev_f299a72e3c2611ecabdf0242ac130004");

        if( isset($_GET['collection_status']) && isset($_GET['external_reference'])) {

            try {
                $payment = MercadoPago\Payment::find_by_id($_GET['payment_id']);
            } catch (Exception $e) {
                CE_Lib::log(1, $e->getMessage());
                return $this->user->lang("There was an error with Mercadopago.")." ".$e->getMessage();
            }

            $cPlugin = new Plugin($params['invoiceId'], 'mercadopago', $this->user);
            $cPlugin->m_TransactionID = $payment->id;
            $cPlugin->setAction('charge');
            $cPlugin->setAmount($payment->transaction_amount);
            if ($payment->status == 'approved') {
                $transaction = "MercadoPago Payment of {$payment->transaction_amount} was accepted, trasaction {$payment->id}";
                $cPlugin->PaymentAccepted($payment->transaction_amount, $transaction);
            } else {
                $transaction = "Payment rejected - Reason: ".$error;
                $cPlugin->PaymentRejected($payment->transaction_amount, false);
            }

            //Need to check to see if user is coming from signup
            if ($params['isSignup'] == 1) {
                if ($this->settings->get('Signup Completion URL') != '') {
                    if ($success === true) {
                        $returnURL = $this->settings->get('Signup Completion URL').'?success=1';
                    } else {
                        $returnURL = $this->settings->get('Signup Completion URL');
                    }
                } else {
                    if ($success === true) {
                        $returnURL = CE_Lib::getSoftwareURL()."/order.php?step=complete&pass=1";
                    } else {
                        $returnURL = CE_Lib::getSoftwareURL()."/order.php?step=3";
                    }
                }
                header("Location: " . $returnURL);
            } 

        }
  

        $tempInvoice = new Invoice($params['invoiceId']);
        $player = new User($tempInvoice->getUserID());


        // Crea un objeto de preferencia
        $preference = new MercadoPago\Preference();

        // Crea un ítem en la preferencia
        $item = new MercadoPago\Item();
        $item->title = $params['companyName']." Invoice - " . $params['invoiceId'];
        $item->quantity = 1;
        //$item->currency_id = "MXN";
        $item->description = "Factura de servicios por Altavy #" .  $params['invoiceId'];
        $item->unit_price = sprintf("%01.2f", round($params['invoiceBalanceDue'], 2));
        

        $preference->items = array($item);
        $preference->auto_return = 'approved';
        $preference->binary_mode = true;
        //$preference->purpose ="wallet_purchase";
        //$preference->marketplace_fee= 1;
        $preference->external_reference = $params['invoiceId'];
        $preference->notification_url = $notification_url;
       //$this->user->customFields->customFields[8] // Objeto guardado para despues
        $preference->player = [
            'name' => $player->firstname,
            'surname' => $player->lastname,
            'email' => $player->email,
        ];
        $preference->back_urls = array(
            "success" => CE_Lib::getSoftwareURL()."/index.php?fuse=billing&paid=1&controller=invoice&view=invoice&id=".$params['invoiceId'],
            "failure" => CE_Lib::getSoftwareURL()."/index.php?fuse=billing&cancel=1&controller=invoice&view=invoice&id=".$params['invoiceId'], 
            "pending" => CE_Lib::getSoftwareURL()."/index.php?fuse=billing&cancel=1&controller=invoice&view=invoice&id=".$params['invoiceId']
        );
        $preference->payment_methods = array(
            "excluded_payment_methods" => array(
              array("id" => "amex"),

            ),
            "excluded_payment_types" => array(
              array("id" => "digital_currency"),
              array("id" => "atm")
            ),
            "installments" => 1
        );
        

        $preference->save();
        $this->view->pregerenceId = $preference->id;
        //$this->view->urlmp = $preference->init_point;
        //$this->view->urlmpb = $preference->sandbox_init_point;
        

        return $this->view->render('form.phtml');
    }


}

