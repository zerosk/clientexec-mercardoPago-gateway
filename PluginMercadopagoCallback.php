<?php
require_once 'modules/admin/models/PluginCallback.php';
require_once 'modules/admin/models/StatusAliasGateway.php';
require_once 'modules/billing/models/class.gateway.plugin.php';
require_once 'modules/billing/models/Invoice_EventLog.php';
require_once 'modules/billing/models/Invoice.php';
require_once 'modules/admin/models/Error_EventLog.php';
require_once 'plugins/gateways/mercadopago/vendor/autoload.php';


class PluginMercadopagoCallback extends PluginCallback
{
    function processCallback()
    {

        if ($this->settings->get('plugin_mercadopago_Modo de prueba') == '1') {
            $accessToken = $this->settings->get('plugin_mercadopago_Test Token');
            
        } else {
            $accessToken = $this->settings->get('plugin_mercadopago_Live Secret Key');
        }
        //echo '<pre>';
        //print_r($this->settings);
        //echo $accessToken;
        //echo "\n -----\n";
        CE_Lib::log(1, 'Mercadopago callback invoked');
        MercadoPago\SDK::setAccessToken($accessToken);
        //MercadoPago\SDK::setPlatformId("PLATFORM_ID");

        $response = file_get_contents("php://input");
        $response = json_decode($response, true);
        //print_r($response);
        //exit();
        CE_Lib::log(4, "Mercadopago Response: {$response}");

        if ($response['type'] == 'payment') {
            //echo $response['action'];

            try {
                $payment = MercadoPago\Payment::find_by_id($response['id']);
            } catch (Exception $e) {
                CE_Lib::log(1, $e->getMessage());
                return $this->user->lang("There was an error with Mercadopago.") . " " . $e->getMessage();
            }

            switch ($response['action']) {

                case 'payment.created':
                    CE_Lib::log(1, "Procesando pago de mercadopago");
                    $cPlugin = new Plugin();
                    $invoice = $cPlugin->retrieveInvoiceForTransaction($payment->id);
                    echo $cPlugin->retrieveInvoiceForTransaction($payment->id);
                    if ($invoice->m_ID != $payment->external_reference || is_null($invoice->m_ID)) {
                        $msg = " Error de comparacion de ID Factura {$invoice->m_ID} con ID MercadoPago {$payment->external_reference}";
                        CE_Lib::log(1, $msg);
                        die();
                    }
                    if ($invoice->IsUnpaid()) {

                        $cPlugin->m_TransactionID = $payment->id;
                        $cPlugin->setAction('charge');
                        $cPlugin->setAmount($payment->transaction_amount);
                        if ($payment->status == 'approved') {
                            $transaction = "MercadoPago Payment of {$payment->external_reference} was accepted, trasaction {$payment->id}";
                            $cPlugin->PaymentAccepted($payment->transaction_amount, $transaction);
                            $msg = "MercadoPago: Pago acreditado por a la factura {$payment->external_reference}";
                        } else {
                            $transaction = "Payment rejected - Reason: " . $payment->status ;
                            $cPlugin->PaymentRejected($payment->transaction_amount, false);
                            $msg = "MercadoPago: Se registro un rechazo del pago para la factura {$payment->external_reference}";
                        }
                        CE_Lib::log(1, $msg);
                        return 'OK';
                    } else {
                        $msg = "MercadoPago: Se registro un pago ya acreditado para factura {$payment->external_reference}";
                        CE_Lib::log(1, $msg);
                        return 'OK';
                    }

                    break;

                case 'payment.updated':
                    break;

                    echo "Hi";
                    $this->logmp();
            }
            CE_Lib::log(1, 'Salida Mercadopago Response: ');
        }

       

    }

    public function logmp()
    {
        $data1 = json_decode(file_get_contents('php://input'), true);
        $data = file_get_contents('php://input');

        print_r($data);

        print_r($_POST);

        $myfile = fopen("log.txt", "a+") or die("Unable to open file!");
        $txt = "----------------\n";
        fwrite($myfile, $txt);
        fwrite($myfile, $data);
        $txt = "\n----------------";
        fwrite($myfile, $txt);
        fclose($myfile);

    }
}
?>