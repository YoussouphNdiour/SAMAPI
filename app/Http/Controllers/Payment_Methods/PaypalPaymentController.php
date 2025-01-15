<?php

namespace App\Http\Controllers\Payment_Methods;

use App\Models\PaymentRequest;
use App\Traits\Processor;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Redirect;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Http;

class PaypalPaymentController extends Controller
{
    use Processor;

    private $config_values;
    private $base_url;

    private PaymentRequest $payment;
   public function getAccessToken()
    {
        $url = "https://api.orange-sonatel.com/oauth/token";
        $data = [
            "client_id" => "6169a61e-d6bb-48be-b899-0f9dbfe78b05",
            "client_secret" => "3e5833e0-3151-449a-800c-4a322d7d268b",
            "grant_type" => "client_credentials",
        ];

        {
        $hearders_token = array(
            'Content-Type' =>'application/x-www-form-urlencoded',
            'Accept' => 'application/json',
        );
        try {
            $response = Http::asForm()->post($url, $data);
            $token = $response->json()["access_token"];
            return $token;
        } catch (\Throwable $th) {
            return $th;
        }
    }
    }
    public function __construct(PaymentRequest $payment)
    {
        $config = $this->payment_config('paypal', 'payment_config');
        if (!is_null($config) && $config->mode == 'live') {
            $this->config_values = json_decode($config->live_values);
        } elseif (!is_null($config) && $config->mode == 'test') {
            $this->config_values = json_decode($config->test_values);
        }

        if($config){
            $this->base_url = ($config->mode == 'test') ? 'https://api-m.sandbox.paypal.com' : 'https://api-m.paypal.com';
        }
        $this->payment = $payment;
    }

    public function token(){
        $ch = curl_init();

        curl_setopt($ch, CURLOPT_URL, $this->base_url.'/v1/oauth2/token');
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
        curl_setopt($ch, CURLOPT_POST, 1);
        curl_setopt($ch, CURLOPT_POSTFIELDS, "grant_type=client_credentials");
        curl_setopt($ch, CURLOPT_USERPWD, $this->config_values->client_id . ':' . $this->config_values->client_secret);

        $headers = array();
        $headers[] = 'Content-Type: application/x-www-form-urlencoded';
        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);

        $accessToken = curl_exec($ch);
        if (curl_errno($ch)) {
            echo 'Error:' . curl_error($ch);
        }
        curl_close($ch);
        return $accessToken;
    }

    /**
     * Responds with a welcome message with instructions
     *
     */
    public function payment(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'payment_id' => 'required|uuid'
        ]);

        if ($validator->fails()) {
            return response()->json($this->response_formatter(GATEWAYS_DEFAULT_400, null, $this->error_processor($validator)), 400);
        }

        $data = $this->payment::where(['id' => $request['payment_id']])->where(['is_paid' => 0])->first();
        if (!isset($data)) {
            return response()->json($this->response_formatter(GATEWAYS_DEFAULT_204), 200);
        }

        if ($data['additional_data'] != null) {
            $business = json_decode($data['additional_data']);
            $business_name = $business->business_name ?? "my_business";
        } else {
            $business_name = "my_business";
        }

        //$accessToken = json_decode($this->token(),true);
        $token = $this->getAccessToken();
        if($token != null){
            try {
                $headers = array(
                    "Authorization" => "Bearer ".$token,  // access token from login
                   // 'Content-Type' =>'application/json',
                     'Accept' => 'application/json',
                );
    
                $paylod = array(
                    "amount" => array(
                        "unit" => "XOF",
                        "value" =>round($data->payment_amount, 2)
                    ),
                     "metadata" => array(
                        'payment_id' => $data->id
                    ),
                    "callbackCancelUrl" => route('paypal.cancel',['payment_id' => $data->id]),
                    "callbackSuccessUrl" => route('paypal.success',['payment_id' => $data->id]),
                    "code" => "536639",
                    "name"=> "SEN AGRO MARKET",
                    "validity"=> 1500
                );
    
                // echo json_encode($data, JSON_PRETTY_PRINT);
                $response = Http::asJson()->withToken($token)->post('https://api.orange-sonatel.com/api/eWallet/v4/qrcode', $paylod);
                $linkOM = $response->json()["deepLink"];
                $link = $response->json()["deepLinks"];
                $linkmaxit = $link['MAXIT'];
                $linkqrId = $response->json()['qrCode'];
                
                // $data->payment_platform;
                if($data->payment_platform == "web"){
                    //je veux afficher le qrcode sur une vue jai deja fait la transfromation
                    //return $qrCodeImage;
                    return  '<!DOCTYPE html>
                                <html lang="en">
                                <head>
                                  <title>SEN AGRO MARKET</title>
                                  <meta charset="utf-8">
                                  <meta name="viewport" content="width=device-width, initial-scale=1">
                                  <meta http-equiv="refresh" content="160;url='. $data->external_redirect_link .'">
                                  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.2.3/dist/css/bootstrap.min.css" rel="stylesheet">
                                  <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.2.3/dist/js/bootstrap.bundle.min.js"></script>
                                </head>
                                <body>
                                
                                <div class="container-fluid p-5 bg-primary text-white text-center">
                                  <h1>Paiement avec Orange money</h1>
                                    <img src="https://apishop.jaymagadegui.sn/storage/app/public/business/2024-02-26-65dc56cec59bf.png" style="max-width:25%; height:auto;" />
                                </div>
                                  
                                <div class="container mt-5 ">
                                  <div class="row ">
                                  
                                     <div class="col-sm">
                                     .
                                  </div>
                                    <div class="col-auto justify-content-center">
                                      <h3>Scannez le Code QR valable une minute</h3>
                                      <img style="min-width:50%;height:auto;" src="data:image/png;base64,'.$linkqrId.'"/> 
                                      
                                    </div>
                                     <div class="col-sm">
                                  .
                                  </div>
                                
                                    </div>
                                  </div>
                                </div>
                                
                                </body>
                                </html>';
                    '<img style="max-width:100%;height:auto;" src="data:image/png;base64,'.$linkqrId.'"/>       ';
                   
                }
                else{
                    // c'est le button maxit si la platform est mobile
                   // $linkmaxit = str_replace('https://sugu.orange-sonatel.com', 'sameaosnapp:', $linkmaxit);

                 return  redirect()->away($linkmaxit);
                }
            } catch(\Throwable $e) {
                return $e;
            }
        }else{
            return 'Token is null '.$token;
        }
//         if ( isset($accessToken['access_token'])) {
//             $accessToken = $accessToken['access_token'];
//             $payment_data = [];
//             $payment_data['purchase_units'] = [
//                 [
//                     'reference_id' => $data->id,
//                     'name' => $business_name,
//                     'desc'  => 'payment ID :' . $data->id,
//                     'amount' => [
//                         'currency_code' => $data->currency_code,
//                         'value' => round($data->payment_amount, 2)
//                     ]
//                 ]
//             ];

//             $payment_data['invoice_id'] = $data->id;
//             $payment_data['invoice_description'] = "Order #{$payment_data['invoice_id']} Invoice";
//             $payment_data['total'] = round($data->payment_amount, 2);
//             $payment_data['intent'] = 'CAPTURE';
//             $payment_data['application_context'] = [
//                 'return_url' => route('paypal.success',['payment_id' => $data->id]),
//                 'cancel_url' => route('paypal.cancel',['payment_id' => $data->id])
//             ];
//             $ch = curl_init();

//             curl_setopt($ch, CURLOPT_URL, $this->base_url.'/v2/checkout/orders');
//             curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
//             curl_setopt($ch, CURLOPT_POST, 1);
//             curl_setopt($ch, CURLOPT_POSTFIELDS,  json_encode($payment_data));

//             $headers = array();
//             $headers[] = 'Content-Type: application/json';
//             $headers[] = "Authorization: Bearer $accessToken";
//             $headers[] = "Paypal-Request-Id:".Str::uuid();
//             curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);

//             $response = curl_exec($ch);
//             if (curl_errno($ch)) {
//                 echo 'Error:' . curl_error($ch);
//             }
//             curl_close($ch);
//         }else{
//             return response()->json($this->response_formatter(GATEWAYS_DEFAULT_204), 200);
//         }
// ;
//         $response = json_decode($response);

//         $links = $response->links;
//         return Redirect::away($links[1]->href);

//         return 0;

    }

    /**
     * Responds with a welcome message with instructions
     */
    public function cancel(Request $request)
    {
        $data = $this->payment::where(['id' => $request['payment_id']])->first();
        return $this->payment_response($data,'cancel');
    }

    /**
     * Responds with a welcome message with instructions
     */
     public function success(Request $request)
    {
        $payment_data = $this->payment::where(['id' => $request['payment_id'], 'is_paid' => 1])->first();
        
        // Si la commande est déjà enregistrée, éviter une double inscription
        if ($payment_data) {
            \Log::info('Commande déjà payée, aucune action supplémentaire nécessaire.', ['payment_id' => $request['payment_id']]);
            return $this->payment_response($payment_data, 'success');
        }
    
        $this->payment::where(['id' => $request['payment_id']])->update([
            'payment_method' => 'Paytech',
            'is_paid' => 1,
            'transaction_id' => $request['payment_id'],
        ]);
    
        $payment_data = $this->payment::where(['id' => $request['payment_id']])->first();
    
        if (isset($payment_data) && function_exists($payment_data->success_hook)) {
            call_user_func($payment_data->success_hook, $payment_data);
        }
    
        return $this->payment_response($payment_data, 'success');
    }
    // public function success(Request $request)
    // {

    //     $accessToken = json_decode($this->token(),true);
    //     $accessToken = $accessToken['access_token'];

    //     $ch = curl_init();

    //     curl_setopt($ch, CURLOPT_URL, $this->base_url."/v2/checkout/orders/{$request->token}/capture");
    //     curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
    //     curl_setopt($ch, CURLOPT_POST, 1);

    //     $headers = array();
    //     $headers[] = 'Content-Type: application/json';
    //     $headers[] = "Authorization: Bearer  $accessToken";
    //     $headers[] = 'Paypal-Request-Id:'.Str::uuid();
    //     curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);

    //     $result = curl_exec($ch);
    //     if (curl_errno($ch)) {
    //         echo 'Error:' . curl_error($ch);
    //     }
    //     curl_close($ch);

    //     $response = json_decode($result);

    //     if($response->status === 'COMPLETED'){
    //         $this->payment::where(['id' => $request['payment_id']])->update([
    //             'payment_method' => 'paypal',
    //             'is_paid' => 1,
    //             'transaction_id' => $response->id,
    //         ]);

    //         $data = $this->payment::where(['id' => $request['payment_id']])->first();

    //         if (isset($data) && function_exists($data->success_hook)) {
    //             call_user_func($data->success_hook, $data);
    //         }

    //         return $this->payment_response($data,'success');
    //     }
    //     $payment_data = $this->payment::where(['id' => $request['payment_id']])->first();
    //     if (isset($payment_data) && function_exists($payment_data->failure_hook)) {
    //         call_user_func($payment_data->failure_hook, $payment_data);
    //     }
    //     return $this->payment_response($payment_data,'fail');
    // }
}
