<?php

namespace App\Http\Controllers;
use App\Models\DiaryRecords;
use Illuminate\Http\Request;
use Carbon\Carbon;
use Carbon\CarbonPeriod;
use Illuminate\Support\Facades\Http;


class BcUpdateLocalDbController extends Controller
{
     /** New BC NAV */

     // Change to take current date
     // get data for all branches
    public function bcNAVRequest($currentDate){
        /**Without handling single thread mechansim */

        try{
            $tempArray = array();
            $grant_type="client_credentials";
            $clientId="65602c4e-a6b4-4aaa-b7a2-c09acc338408";
            $clientSecret=".QJ8Q~XePlCMWoE1iAfNSDFjXlmzKuVlMa-nGaDb";
            $scope="https://api.businesscentral.dynamics.com/.default";
            $tenantID="2739a659-2d71-434e-9bcc-29f7df81ca59";
            $loginURL="https://login.microsoftonline.com/".$tenantID."/oauth2/v2.0/token";
            $response=Http::asForm()->post($loginURL,[
                'grant_type'=>$grant_type,
                'client_id'=>$clientId,
                'client_secret'=>$clientSecret,
                'scope'=>$scope
            ]);
            $responseResult=$response->json();

            
    
           
            if(isset($responseResult['error'])){
                print_r($responseResult['error']);
                
            }else{
                $access_token=$responseResult['access_token'];
                $token_type=$responseResult['token_type'];
                $expiry_time=$responseResult['expires_in'];
                $baseUrl = 'https://api.businesscentral.dynamics.com/v2.0/Production/api/Dunsterhouse/webSite/v2.0/';
                //https://api.businesscentral.dynamics.com/v2.0/Production/api/Dunsterhouse/webSite/v2.0/companies(b94a976f-5933-ee11-bdf5-000d3ad51ae4)/deliveryDiary?$filter=promisedDeliveryDate gt $currentDate
                // $companyId = 'b94a976f-5933-ee11-bdf5-000d3ad51ae'; incorrect company ID to test nav going down 
                $companyId = 'b94a976f-5933-ee11-bdf5-000d3ad51ae4';
                $endpoint = "companies($companyId)/deliveryDiary";
                // $filter = "locationCode eq '$getBranchCode' and promisedDeliveryDate ge $weekStartDate and promisedDeliveryDate le $weekEndDate";
                $filter = "promisedDeliveryDate ge $currentDate";
                $url = $baseUrl . $endpoint . '?$filter=' . $filter;
                
                $response = Http::withOptions([
                    'timeout' => 60, // Increase timeout to 60 seconds
                ])->withHeaders([
                    'Authorization' => $token_type.' '.$access_token,
                ])->get($url);
                
                if ($response->successful()) {
                    $getDataFromNav = $response->json();
                    //print_r($getDataFromNav);
                    
                    foreach ($getDataFromNav['value'] as $itemNav) {
                        $result = array(
                            'order_no' => $itemNav['no'],
                            'ship_to_name' => $itemNav['shipToName'],
                            'ship_to_post_code' => $itemNav['shipToPostCode'],
                            'ship_to_county' => $itemNav['shipToCounty'],
                            'ship_to_region_code' => $itemNav['shipToCountryRegionCode'],
                            'type_of_supply_code' => $itemNav['typeOfSupplyCodeTNP'],
                            'order_weight' => $itemNav['Weight'],
                            'order_amount' => $itemNav['orderAmountTNP'],
                            'currency_code' => $itemNav['currencyCode'],
                            'location_code' => $itemNav['locationCode'],
                            'shipping_agent_code' => $itemNav['shippingAgentCode'],
                            'shipping_agent_service_code' => $itemNav['shippingAgentServiceCode'],
                            'shipment_type' => $itemNav['shipmentTypeTNP'],
                            'promised_delivery_date' => date('Y-m-d', strtotime($itemNav['promisedDeliveryDate'])),
                            'delivery_confirmed' => $itemNav['deliveryConfirmedTNP'],
                            'balance_amount' => $itemNav['balanceAmountTNP'],
                            'last_shipping_no' => $itemNav['lastShippingNo'],
                            'ship_status' => $itemNav['ShipStatus'],
                            'completed' => $itemNav['completedTNP'],
                            'ship_to_city' => TRIM($itemNav['shipToCity']),
                            'dispatch_change' => -1,
                            'dispatch_requested_date' => date('Y-m-d', strtotime($itemNav['dispatchRequestedDateTNP']))
                        );
                        array_push($tempArray, $result);
                    }
                    return $tempArray;
                    // Process the response data here
                } else {
                    // Handle the request error
                    $statusCode = $response->status();
                    $errorMessage = $response->body();
                    print_r("else");
                    print_r($errorMessage);
                    return null;
                    // Handle the error accordingly
                }
            }           
            
            
        }catch(ClientException $e){
            print_r($e.getResponse());
        }
    }

    // compare data from bcNAVRequest with database 
    // check for any new records and update database
    public function compareData(){
        
        $selectedDate = date('Y-m-d', strtotime(Carbon::now()->subDays(30)));
        $newSelectedDateFormat = new Carbon($selectedDate);
        $currentDate = $newSelectedDateFormat->startOfWeek()->format('Y-m-d');

        $navData = $this->bcNAVRequest($currentDate);
        //print_r($navData);
        $getDiaryData=DiaryRecords::where('promised_delivery_date','>=',$currentDate)->get();        

        $finalDiaryData=json_decode(json_encode($getDiaryData), true);
        //print_r($finalDiaryData);
        /**Remove if any order not exist in nav data */
        foreach($finalDiaryData as $key=>$dbData){
            $order_status=false;
            foreach($navData as $key2=>$itemInNav){
                if($dbData['order_no']==$itemInNav['order_no']){
                    $order_status=true;
                    break;
                }
            }
            if($order_status==false){
                $deleteRecord=DiaryRecords::where('order_no',$dbData["order_no"])->delete();
            }
        }

        
        foreach (array_chunk($navData,1000) as $t)  
        {
            // print_r("update");
            // print_r($t);
            DiaryRecords::upsert($t, 'order_no'); 
        }   

        return 'database Updated with Nav Data';
    }
}
