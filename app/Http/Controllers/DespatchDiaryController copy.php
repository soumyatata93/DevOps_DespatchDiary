<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Collection;
use Carbon\Carbon;
use Carbon\CarbonPeriod;
use App\Postcode;
use App\Models\Vehicle;
use App\Models\DiaryRecords;
use App\Models\DiaryComments;
use App\Models\VehicleBookingStatus;
use App\Models\APIRouting;
use App\Models\Branch;
use \Exception;

class DespatchDiaryController extends Controller
{
    private $error_msg=''; 
    /**
     * Get api routing order_nos for van per day
     */
    public function getAPIOrderNumbers($uniqueId)
    {
        try {
            $order_numbers = APIRouting::where('unique_id', $uniqueId)->value('order_nos');

        } catch (\Exception $e) {
            $this->error_msg = "error";
            die("Could not connect to the database. Please check your configuration. error:" . $e);
        }
        return json_decode($order_numbers, true);
    }
    /**
     * Get api routing details for van per day
     */
    public function getAPIRoutingDetails($uniqueId)
    {
        $routing_details = '';
        try {
            $routing_details = APIRouting::select('time_seconds', 'distance_miles', 'original_route', 'display_orders_position')
            ->where('unique_id', $uniqueId)->get();
        } catch (\Exception $e) {
            $this->error_msg = "error";
            die("Could not connect to the database. Please check your configuration. error:" . $e);
        }
        return $routing_details;
    }

    /**
     * update routing records in table
     *
     */
    public function setAPIRoutingDetails($api_routing, $promised_delivery_date, $uniqueId, $selected_branch_id,
    $allOrderNos, $originalSequence, $vannumbers, $journeyDistanceInMiles, $travelTimeInSeconds )
    {

        $api_routing['promised_delivery_date'] = $promised_delivery_date;
        $api_routing['unique_id'] = $uniqueId;
        $api_routing['branch_id'] = $selected_branch_id;
        $api_routing['order_nos'] = json_encode($allOrderNos);
        $api_routing['original_route'] = serialize($originalSequence);
        $api_routing['vehicle_id'] = $vannumbers->shipping_agent_service_code;
        $api_routing['optimized_route'] = serialize($originalSequence);
        $api_routing['distance_miles'] = $journeyDistanceInMiles;
        $api_routing['time_seconds'] = $travelTimeInSeconds;
        $api_routing['display_orders_position'] = serialize($originalSequence);
        $api_routing['error_status'] = 0;

        try {

            DB::connection('mysql')->table('api_routing')
                        ->upsert($api_routing, 'unique_id');
        } catch (\Exception $e) {
            $this->error_msg = "error";
            die("Could not connect to the database. Please check your configuration. error:" . $e);
        }
    }

    public function getGmapsResponse($originalSequence, $selected_branch_postcode){
        $route='';
        $gMapResponse = null;
        
        try {
            foreach ($originalSequence as $oneTableRoute)
            {
                $route .= $oneTableRoute.'|';
            }
            $route  = rtrim($route, "|");
            $GmapsDirectionsUrl = 'https://maps.googleapis.com/maps/api/directions/json?destination='.
            $selected_branch_postcode.'&origin='.$selected_branch_postcode.'&waypoints=optimize%3Atrue%7C';
            
            $apiKey = "&key=AIzaSyCXntRD8HoRjEc_1lto4Zd8MQnuGCAQqQg";
            //.env('GMAPS_API_KEY', null);    



            $apiaddress = $GmapsDirectionsUrl.$route.$apiKey;
            $apiaddress  = str_replace(' ', '', $apiaddress);

            // print_r($apiaddress);

            $ch = curl_init($apiaddress);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER,TRUE);
            curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 0);
            curl_setopt($ch, CURLOPT_TIMEOUT, 1400);
   
            $gMapResponse = curl_exec($ch);        
            curl_close($ch);   
        }       
        catch (\Illuminate\Http\Client\ConnectionException $e) {
            $this->error_msg = 'Error';
            echo "Exception: " . $e->getMessage();
            return "Exception: " . $e->getMessage();
         } catch (\Exception $e) {
            $this->error_msg = 'Error';
            echo "Exception: " . $e->getMessage();
            return "Exception: " . $e->getMessage();
        }
        
        return $gMapResponse; 
    }

    function handleGmapsResponse($gMapResponse,$uniqueId){
        
        $result=[];        
        $totalDuration=0;
        $totalDistance=0;

      

   
        if(!preg_match('/status"\s:\s"ZERO_RESULTS/', $gMapResponse))
        {
            $res = explode('"routes" :', $gMapResponse);             
            $legs = explode('"legs" :', $res[1]);            
            $regex =  '/{\n\s*"\D*\d+\.?\d+?\s.*\s.*\n.*\n.*\n.*\n.*\n.*\n.*\n.*\n.*\n.*\n.*\n.*\n.+}/';
            
            foreach($legs as $key=>$value)
            {
                if($key==1&&$value!="")
                {
                    preg_match_all($regex, $value, $res);
                        
                    foreach($res as $key1 => $val){
                            
                        array_push($result, $uniqueId);

                        for($i =1;$i<=sizeof($val);$i++){
                                
                            $result[$i] = json_decode($val[$i-1].'}');
                        }
                    }

                } 
            }

            if($result!==NULL)
            {
                foreach($result as $key=>$value){
                    if($key!=0 )
                    {
                        if($value!=NULL)
                        {
                            $totalDistance += $value->distance->value;
                            $totalDuration += $value->duration->value; 
                        }   
                    } 
                }    
                $totalDistance = (float)number_format((float)$totalDistance/1609, 2, '.', ''); 
            }

            return  [
                        'uniqueId' => $uniqueId,
                        'totalDistance' => $totalDistance, 
                        'totalDuration' => $totalDuration,
                        'ApiError' => '',
                    ];
           
        }
        else{
            //wrong api calculation
            return  [
                'uniqueId' => '',
                'totalDistance' => '', 
                'totalDuration' => '',
                'ApiError' => 'wrong api calculation',
            ];
        }
    }
    function convertPostcodesToLatLng($originalSequence){

        $newOriginalSequence = [];

        foreach ($originalSequence as $postcode){

            $latLong = $this->sendPostcodeToApi($postcode);

           array_push($newOriginalSequence, $latLong); 
        }
        return $newOriginalSequence; 
    }
    /**
     * Function is geting lat and long from postcode
     * if we don't find postcode in database go to third part API to update our database
     *
     * @postcode -> MK40 1EQ
     *
     * return -> lat and long
     */
    public function sendPostcodeToApi($postcode)
    {
        try {
            $latLongDB = DB::connection('mysql')->table('postcodes')->select('latitude', 'longitude')->where('postcode', $postcode)->get();
            if (count($latLongDB) == 0 || $latLongDB->count() == 0) {
                $apiResponse = json_decode(file_get_contents("https://api.postcodes.io/postcodes/$postcode"));
                if ($apiResponse->status == 200 && ($apiResponse->result->latitude != null
                        && $apiResponse->result->longitude != null)) {

                    $latLong = $apiResponse->result->latitude . '%2C' . $apiResponse->result->longitude;
                    $data = array(
                        'postcode' => $postcode,
                        'latitude' => $apiResponse->result->latitude,
                        'longitude' => $apiResponse->result->longitude
                    );
                    $this->updateLatLongInPostcode($data);
                } else {
                    $latLong = '';
                }
            } else {
                $latLong = number_format($latLongDB[0]->latitude, 8) . '%2C' . number_format($latLongDB[0]->longitude, 8);
            }

            return $latLong;
        } catch (\Throwable $th) {
            $latLong = '';
            return $latLong;
        }
    }
     /**
     * update latitude and longitude in postcodes table
     */
    public function updateLatLongInPostcode($data)
    {

        try {
            $updateOrInsert = DB::connection('mysql')->table('postcodes')
                ->updateOrInsert(['postcode' => $data['postcode']], $data);
        } catch (\Exception $e) {
            $this->error_msg = "error";
            die("Could not connect to the database. Please check your configuration. error:" . $e);
        }

    }
    /**
     * Fetch BranchCode based on branchId
     * @param integer $branchID
     * @return single column string result
     */
    public function getBranchShippingCode($branch_id)
    {
        try {
            return Branch::where('branch_id', $branch_id)->value('shipping_agent_code');
        } catch (\Exception $e) {
            die("Could not connect to the database. Please check your configuration. error:" . $e);
            $this->error_msg = 'Could not connect to the database' . $e;
        }
    }
    
    /**
     * Fetch all BranchCodes based on branchId
     * @param integer $branchID
     * @return single column string result
     */
    public function getAllBranchShippingCodes()
    {
        try {
            return Branch::pluck('shipping_agent_code')->toArray();
        } catch (\Exception $e) {
            die("Could not connect to the database. Please check your configuration. error:" . $e);
            $this->error_msg = 'Could not connect to the database' . $e;
        }
    }
    /**
     * Fetch BranchCode based on branchId
     * @param integer $branchID
     * @return single column string result
     */
    public function getBranchCode($branch_id)
    {
        try {
            return Branch::where('branch_id', $branch_id)->value('branch_code');
        } catch (\Exception $e) {
            die("Could not connect to the database. Please check your configuration. error:" . $e);
            $this->error_msg = 'Could not connect to the database' . $e;
        }
    }
     /**
     * Get count of branches
     */
    public function getBranchIdsList()
    {
        try {
            return Branch::pluck('branch_id');
        } catch (\Exception $e) {
            die("Could not connect to the database. Please check your configuration. error:" . $e);
            $this->error_msg = 'Could not connect to the database' . $e;
        }
    }
     /**
     * Get postcode for selected branch
     */
    public function getSelectedBranchPostCode($branch_id)
    {
        try {
            return DB::connection('mysql')->table('branch')->where('branch_id', $branch_id)->value('branch_postcode');
        } catch (\Exception $e) {
            die("Could not connect to the database. Please check your configuration. error:" . $e);
            $this->error_msg = 'Could not connect to the database' . $e;
        }
    }
    /**
     * Get count of branches
     */
    public function getDistinctCountOfBranches()
    {
        try {
            return Branch::distinct()->count('shipping_agent_code');
        } catch (\Exception $e) {
            die("Could not connect to the database. Please check your configuration. error:" . $e);
            $this->error_msg = 'Could not connect to the database' . $e;
        }
    }
    /**
     * Get branch list
     */
    public function getAllBranchDetails()
    {
        try {
            return Branch::select('branch_id', 'branch_location')->get();
        } catch (\Exception $e) {
            die("Could not connect to the database. Please check your configuration. error:" . $e);
            $this->error_msg = 'Could not connect to the database' . $e;
        }
    }
    /**
     * Get lat and longitude for selected branch
     */
    public function getBranchLatAndLong($branch_id)
    {
        try {
            $branchLatAndLong = Branch::select('latitude', 'longitude')->where('branch_id', $branch_id)->get();
        } catch (\Exception $e) {
            die("Could not connect to the database. Please check your configuration. error:" . $e);
            $this->error_msg = 'Could not connect to the database' . $e;
        }
        return $branchLatAndLong;
    }
    /**
     * get comments list
     */
    public function getCommentsIndependently($dateFrom, $dateTo)
    {
        try {
            $commentsIndependently = DiaryComments::from('vehicle_diary_comments as vdc')
                ->whereBetween('vdc.vehicle_diary_date',[$dateFrom, $dateTo])
                ->join('vehicle as v', 'vdc.vehicle_id', 'v.vehicle_id')
                ->select('v.shipping_agent_service_code', 'vdc.vehicle_diary_date', 'vdc.comments')
                ->get();
            return $commentsIndependently;
        } catch (\Exception $e) {
            die("Could not connect to the database. Please check your configuration. error:" . $e);
        }

    }
    public function getVehicleIdForCode($shipping_agent_code){
        try{
            return Vehicle::where('shipping_agent_service_code',$shipping_agent_code)
                    ->value('vehicle_id');
           
        }catch (\Exception $e) {
            die("Could not connect to the database. Please check your configuration. error:" . $e );
        }
    }
    /**
     * Inputs -comment content,vehicle id & comment date from comments textarea
     *
     */
    public function sendCommentsToDB(Request $request)
    {

        try {
            $selectedDate = date('Y-m-d', strtotime($request->selectedDate));
            $newSelectedDateFormat = new Carbon($selectedDate);
            $comment_date = $newSelectedDateFormat->format('Y-m-d');
            $comment_content = $request->updatedComments;
            $getVehicleId = $request->selectedVehicleNo;
            $getVehicleId = $this->getVehicleIdForCode($request->selectedVehicleNo);

            if(DB::connection('mysql')->table('vehicle_diary_comments')
            ->where([
            'vehicle_diary_date' => $comment_date, 
            'vehicle_id' => $getVehicleId])->count()>1){
                $deleteDBStatus=DB::connection('mysql')->table('vehicle_diary_comments')
                ->where([
                'vehicle_diary_date' => $comment_date, 
                'vehicle_id' => $getVehicleId])->delete();

                $updateCommentResponse = DB::connection('mysql')->table('vehicle_diary_comments')
                ->updateOrInsert(['vehicle_diary_date' => $comment_date, 'vehicle_id' => $getVehicleId],
                    ['comments' => $comment_content, 'vehicle_diary_date' => $comment_date, 'vehicle_id' => $getVehicleId]);
            }
            else{
                $updateCommentResponse = DB::connection('mysql')->table('vehicle_diary_comments')
                ->updateOrInsert(['vehicle_diary_date' => $comment_date, 'vehicle_id' => $getVehicleId],
                    ['comments' => $comment_content, 'vehicle_diary_date' => $comment_date, 'vehicle_id' => $getVehicleId]);
            }
            return $updateCommentResponse;
            
        } catch (\Exception $e) {
            die("Could not connect to the database. Please check your configuration. error:" . $e);
        }
    }
    /**Fetch van details
     * @param start_date
     * branch_id
     */
    public function getVanDetails($selected_branch_id,$date){
        try {
            $getVanDetails='';
            
            if($selected_branch_id!=0){
                $getVanDetails=Vehicle::select('shipping_agent_service_code', 
                                'collapse_status', 'parent_collapse', 'vehicle_bookable', 
                                'branch_id', 'registration_number', 'vehicle_type','delivery_capacity', 
                                'target_amount', 'vehicle_id','van_number',
                                'start_date', 'end_date', 'display_order')
                    ->where([
                        ['vehicle_status','=',1],
                        ['start_date','<=',$date],
                        ['end_date','>=',$date],
                        ['branch_id','=',$selected_branch_id]
                    ])
                    ->orderBy('display_order','ASC')
                    ->get();
                
            }
            // else{
            //     $getVanDetails=Vehicle::select('shipping_agent_service_code', 
            //                     'collapse_status', 'parent_collapse', 'vehicle_bookable', 
            //                     'branch_id', 'registration_number', 'vehicle_type','delivery_capacity', 
            //                     'target_amount', 'vehicle_id','van_number',
            //                     'start_date', 'end_date', 'display_order')
            //         ->where([
            //             ['vehicle_status','=',1],
            //             ['start_date','<=',$date],
            //             ['end_date','>=',$date]
            //         ])
            //         ->orderBy('display_order','ASC')
            //         ->get();
            // }
            
            return $getVanDetails;
        } catch (\Exception $e) {
            die("Could not connect to the database. Please check your configuration. error:" . $e);
        }
    }

    public function getDistinctVanDetails($selected_branch_id,$date){
        
        try {
            $getVanDetails='';
            
            if($selected_branch_id!=0){
                $getVanDetails=Vehicle::select(
                'branch_id', 'registration_number','parent_collapse' ,'vehicle_type','delivery_capacity', 
                'target_amount','van_number')
                    ->where([
                        ['vehicle_status','=',1],
                        ['start_date','<=',$date],
                        ['end_date','>=',$date],
                        ['branch_id','=',$selected_branch_id]
                    ])
                    ->distinct('van_number')
                    ->orderBy('display_order','ASC')
                    ->get();
                
            }
            
            // else{
            //     $getVanDetails=Vehicle::select('branch_id', 'parent_collapse','registration_number', 'vehicle_type','delivery_capacity', 
            //     'target_amount','van_number')
            //         ->where([
            //             ['vehicle_status','=',1],
            //             ['start_date','<=',$date],
            //             ['end_date','>=',$date]
            //         ])
            //         ->distinct('van_number')
            //         ->orderBy('display_order','ASC')
            //         ->get();
            // }
            
            return $getVanDetails;
        } catch (\Exception $e) {
            die("Could not connect to the database. Please check your configuration. error:" . $e);
        }
    }
    /**
     * Fetch bookstatus based on dates for selected branch
     * @param integer $selectedBranch
     * @param date $fromDate ,$toDate
     * @return single column string result
     */

     public function getVehicleIdByNo($vehicleNo)
     {
         $this->error_msg = '';
         try {
             return DB::connection('mysql')->table('vehicle')
                 ->whereRaw('shipping_agent_service_code=?', $vehicleNo)
                 ->value('vehicle_id');
         } catch (\Exception $e) {
             die("Could not connect to the database. Please check your configuration. error:" . $e);
             $this->error_msg = 'Could not connect to the database' . $e;
         }
     }
    public function getBookableStatus($selected_branch_id, $fromDate, $toDate)
    {
        $this->error_msg = '';
        $getBookableValues='';
        try {
            // if ($selected_branch_id == 0) {
            //     $getBookableValues = VehicleBookingStatus::from('vehicle_booking as vb')
            //         ->select('v.shipping_agent_service_code', 'vb.branch_id', 'vb.vehicle_booking_date', 
            //                 'vb.vehicle_booking_status')
            //         ->join('vehicle as v', 'v.vehicle_id', 'vb.vehicle_id')
            //         ->whereBetween('vb.vehicle_booking_date',[$fromDate, $toDate])->get();
            // } else {
                $getBookableValues = VehicleBookingStatus::from('vehicle_booking as vb')
                ->select('v.shipping_agent_service_code', 'vb.branch_id', 'vb.vehicle_booking_date', 
                        'vb.vehicle_booking_status')
                ->join('vehicle as v', 'v.vehicle_id', 'vb.vehicle_id')
                ->whereBetween('vb.vehicle_booking_date',[$fromDate, $toDate])
                ->where('vb.branch_id','=',$selected_branch_id)
                ->get();
           // }
            return $getBookableValues;
        } catch (\Exception $e) {
            die("Could not connect to the database. Please check your configuration. error:" . $e);
            $this->error_msg = 'Could not connect to the database' . $e;
        }
    }
    public function sendBookableValueToDB(Request $request){
        $this->error_msg='';
        $updateBook='';
        try{
            $vehicle_id=$this->getVehicleIdByNo($request->vehicleNo);
            $selectedDate = date('Y-m-d',strtotime($request->selectedDate));
            $newSelectedDateFormat= new Carbon($selectedDate);
            $updateDate = $newSelectedDateFormat->format('Y-m-d');
            $updateBook=DB::connection('mysql')->table('vehicle_booking')
                    ->whereRaw('vehicle_id=? AND vehicle_booking_date=?',array($vehicle_id,$updateDate))
                    ->update(['vehicle_booking_status'=>$request->bookableValue]);
            
        }catch (\Exception $e) {
            die("Could not connect to the database. Please check your configuration. error:" . $e );
            $this->error_msg='Could not connect to the database' . $e ;
        }
        return response()->json([
            "bookableValue"=>$request->bookableValue,
            "updateStatus"=>$updateBook,
            "error_msg"=>$this->error_msg
        ]);
        
    }

    /**
     * update hide or show van tab collpase
     */    
     public function updateParentCollapseForVehicle(Request $request){
        
        $this->error_msg='';

        try{
            $updateParentCollapseQuery=DB::connection('mysql')->table('vehicle')            
            ->whereRaw('shipping_agent_service_code LIKE ?',array($request->vanNum . '%'))
            ->update(['parent_collapse'=>$request->parentHideOrShowStatus]);
            
            $getParentCollapseQuery=DB::connection('mysql')->table('vehicle')
            ->select('shipping_agent_service_code','parent_collapse')
            ->whereRaw('shipping_agent_service_code LIKE ?',array($request->vanNum . '%'))
            ->get();
        }
        catch (\Exception $e) {
            die("Could not connect to the database. Please check your configuration. error:" . $e );
            $this->error_msg='Could not connect to the database' . $e ;
        }
        return response()->json([
            "parentHideOrShowStatus"=>$request->parentHideOrShowStatus,
            "getParentCollapseQuery"=>$getParentCollapseQuery,
             "updateStatus"=>$updateParentCollapseQuery,
             "error_msg"=>$this->error_msg
        ]);
    }

    public function updateVehicleTabStatus(Request $request){
        $this->error_msg='';
       
        try{
            $updateQuery=DB::connection('mysql')->table('vehicle')
            ->updateOrInsert(['shipping_agent_service_code'=>$request->vanNo],['collapse_status'=>$request->hideOrShowStatus]);
            
        }catch (\Exception $e) {
            die("Could not connect to the database. Please check your configuration. error:" . $e );
            $this->error_msg='Could not connect to the database' . $e ;
        }
        return response()->json([
            "hideOrShowStatus"=>$request->hideOrShowStatus,
            "updateStatus"=>$updateQuery,
            "error_msg"=>$this->error_msg
        ]);
        
    }
    /**get collection orders */
    public function getCollectionData($selectedBranch, $fromDate, $toDate)
    {
        $this->error_msg = '';
        try {
            if ($selectedBranch == 'All') {
                $collection = DiaryRecords::select('diary_id', 'order_no', 'ship_to_name', 'ship_to_post_code','ship_to_city',
                        'type_of_supply_code', 'order_weight','order_amount','location_code', 'shipping_agent_code', 'shipment_type', 'promised_delivery_date',
                        'delivery_confirmed', 'updated_at', 'balance_amount','ship_status','dispatch_requested_date')
                    ->where('shipping_agent_service_code','=','COLLECTION')
                    ->whereBetween('promised_delivery_date',[$fromDate,$toDate])
                    ->orderBy('promised_delivery_date', 'ASC')
                    ->get();
            } else {
                $collection = DiaryRecords::select('diary_id', 'order_no', 'ship_to_name', 'ship_to_post_code','ship_to_city',
                    'type_of_supply_code', 'order_weight','order_amount','location_code', 'shipping_agent_code', 'shipment_type', 'promised_delivery_date',
                    'delivery_confirmed', 'updated_at', 'balance_amount','ship_status','dispatch_requested_date')
                    ->where([
                        ['shipping_agent_service_code','=','COLLECTION'],
                        ['location_code','=',$selectedBranch]
                    ])
                    ->whereBetween('promised_delivery_date',[$fromDate,$toDate])
                    ->orderBy('promised_delivery_date', 'ASC')
                    ->get();
            }
            return $collection;
        } catch (\Exception $e) {
            $this->error_msg = "error";
            die("Could not connect to the database. Please check your configuration. error:" . $e);
        }
    }
    /**get post order */
    public function getPostData($selectedBranch, $fromDate, $toDate)
    {
        $this->error_msg = '';
        $post = array();
        try {
            if ($selectedBranch == 'All') {
                $post = DiaryRecords::select('diary_id', 'order_no', 'ship_to_name', 'ship_to_post_code','ship_to_city',
                        'type_of_supply_code', 'order_weight','order_amount','location_code', 'shipping_agent_code', 'shipment_type', 'promised_delivery_date',
                        'delivery_confirmed', 'updated_at', 'balance_amount','ship_status','dispatch_requested_date')
                        ->where('shipping_agent_service_code','=','POST')
                        ->whereBetween('promised_delivery_date',[$fromDate,$toDate])
                        ->orderBy('promised_delivery_date', 'ASC')
                        ->get();
            } else {
                $post = DiaryRecords::select('diary_id', 'order_no', 'ship_to_name', 'ship_to_post_code','ship_to_city',
                        'type_of_supply_code', 'order_weight','order_amount','location_code', 'shipping_agent_code', 'shipment_type', 'promised_delivery_date',
                        'delivery_confirmed', 'updated_at', 'balance_amount','ship_status','dispatch_requested_date')
                        ->where([
                            ['shipping_agent_service_code','=','POST'],
                            ['location_code','=',$selectedBranch]
                        ])
                        ->whereBetween('promised_delivery_date',[$fromDate,$toDate])
                        ->orderBy('promised_delivery_date', 'ASC')
                        ->get();
            }
            return $post;
        } catch (\Exception $e) {
            $this->error_msg = "error";
            die("Could not connect to the database. Please check your configuration. error:" . $e);
        }
    }
    /**Get courier data */
    public function getCourierData($selectedBranch, $fromDate, $toDate)
    {
        $this->error_msg = '';
        $courier = array();
        try {
            if ($selectedBranch == 'All') {
                $courier = DiaryRecords::select('diary_id', 'order_no', 'ship_to_name', 'ship_to_post_code','ship_to_city',
                            'type_of_supply_code', 'order_weight','order_amount','location_code', 'shipping_agent_code', 'shipment_type', 'promised_delivery_date',
                            'delivery_confirmed', 'updated_at', 'balance_amount','ship_status','dispatch_requested_date')
                        ->where('shipping_agent_service_code','=','COURIER')
                        ->whereBetween('promised_delivery_date',[$fromDate,$toDate])
                        ->orderBy('promised_delivery_date', 'ASC')
                        ->get();
            } else {
                $courier =  DiaryRecords::select('diary_id', 'order_no', 'ship_to_name', 'ship_to_post_code','ship_to_city',
                            'type_of_supply_code', 'order_weight','order_amount','location_code', 'shipping_agent_code', 'shipment_type', 'promised_delivery_date',
                            'delivery_confirmed', 'updated_at', 'balance_amount','ship_status','dispatch_requested_date')
                            ->where([
                                ['shipping_agent_service_code','=','COURIER'],
                                ['location_code','=',$selectedBranch]
                            ])
                            ->whereBetween('promised_delivery_date',[$fromDate,$toDate])
                            ->orderBy('promised_delivery_date', 'ASC')
                            ->get();
            }
            return $courier;
        } catch (\Exception $e) {
            $this->error_msg = "error";
            die("Could not connect to the database. Please check your configuration. error:" . $e);
        }
    }
    /**
     * Total value and weight for all runs in a van for single day
     */
    public function getEachDayTotalValueAndWt($vanNo, $startDate, $endDate)
    {
        try {
            return DB::connection('mysql')->table('diary')
                ->select('promised_delivery_date',
                    DB::raw('SUM(order_weight) as totalDayWeight'),
                    DB::raw('SUM(order_amount) as totalDayValue'))
                ->whereRaw("shipping_agent_service_code LIKE ? AND promised_delivery_date BETWEEN ? AND ?", array($vanNo . '%', $startDate, $endDate))
                ->groupBy('promised_delivery_date')->get();
        } catch (\Exception $e) {
            die("Could not connect to the database. Please check your configuration. error:" . $e);
        }
    }

    public function getEachDayTotalMileage($vanNo, $startDate, $endDate)
    {
        try {
            return DB::connection('mysql')->table('api_routing')
                ->select('promised_delivery_date',
                    DB::raw('SUM(time_seconds) as totalTravelTime'),
                    DB::raw('SUM(distance_miles) as totalJourneyDistance'))
                ->whereRaw("vehicle_id LIKE ? AND promised_delivery_date BETWEEN ? AND ?", array($vanNo . '%', $startDate, $endDate))
                ->groupBy('promised_delivery_date')->get();
        } catch (\Exception $e) {
            die("Could not connect to the database. Please check your configuration. error:" . $e);
        }
    }
    /**
     * Get total orders weight and amount for each van
     */
    public function totalWeekWeightAndValueForSingleVan($vanId, $dateFrom, $dateTo)
    {
        try {

            return DB::connection('mysql')->table('diary as d')
                ->select('d.shipping_agent_service_code as van_number',
                    DB::raw('SUM(d.order_weight) as totalWeight'),
                    DB::raw('SUM(d.order_amount) as totalValue'))
                ->whereRaw("d.shipping_agent_service_code LIKE ? AND d.promised_delivery_date BETWEEN ? AND ?", array($vanId, $dateFrom, $dateTo))
                ->groupBy('d.shipping_agent_service_code')->get();
        } catch (\Exception $e) {
            die("Could not connect to the database. Please check your configuration. error:" . $e);
        }
    }
    /**
     * Get total orders weight and amount for each day in selected week.
     * @params selected branch id
     * location code
     * shipping agent code
     * selected week start and end date
     */
    public function totalWeekWeightAndValueForEachDay($selected_branch_id,$fromDate,$toDate)
    {        
        try {
            $service_list=['COURIER','COLLECTION','POST'];
            if($selected_branch_id!=0){
                $getBranchCode=$this->getBranchCode($selected_branch_id);
                $getSelectedBranchShippingCode=$this->getBranchShippingCode($selected_branch_id);
                array_push($service_list,$getSelectedBranchShippingCode);

                return DiaryRecords::select('shipping_agent_code as shipping_service',
                        'promised_delivery_date as date',
                        DB::raw('COALESCE(SUM(order_weight), 0) as totalWeightPerDay'),
                        DB::raw('COALESCE(sum(order_amount), 0) as totalValuePerDay'))
                        ->whereBetween('promised_delivery_date',[$fromDate,$toDate])
                        ->where('location_code','=',$getBranchCode)
                        ->whereIn('shipping_agent_code',$service_list)
                        ->groupBy('shipping_agent_code','promised_delivery_date')
                        ->orderBy('shipping_agent_code','ASC')
                        ->get();
            }
            // else{
            //     return DiaryRecords::select('shipping_agent_code as shipping_service',
            //             'promised_delivery_date as date',
            //             DB::raw('COALESCE(SUM(order_weight), 0) as totalWeightPerDay'),
            //             DB::raw('COALESCE(sum(order_amount), 0) as totalValuePerDay'))
            //             ->whereBetween('promised_delivery_date',[$fromDate,$toDate])
            //             ->whereIn('shipping_agent_code',$service_list)
            //             ->where('shipping_agent_code','LIKE','DH%')
            //             ->groupBy('shipping_agent_code','promised_delivery_date')
            //             ->orderBy('shipping_agent_code','ASC')
            //             ->get();
                
            // }
        } catch (\Exception $e) {
            die("Could not connect to the database. Please check your configuration. error:" . $e);
        }
    }

    /*New orders refresh based on large refresh cookie value*/
    public function checkOrderUpdates(Request $request)
    {
        $new_orders = '';
        try {
            $tableName = "diary";
            $lastRefresh = $request->cookieValue;
            $selected_branch_id = $request->selectedBranch;
            $selectedDate = $request->selectedDate;
            $selectedDate = date('Y-m-d', strtotime($selectedDate));
            $newSelectedDateFormat = new Carbon($selectedDate);
            $weekStartDate = $newSelectedDateFormat->startOfWeek()->format('Y-m-d');
            $weekEndDate = $newSelectedDateFormat->endOfWeek()->format('Y-m-d');
            $interval_period = CarbonPeriod::create($weekStartDate, $weekEndDate);
            $getBranchCode = $this->getBranchCode($selected_branch_id);
            //if ($selected_branch_id != 0) {
                //Get location code with branch_id
                

                //comment this
            //    $getNavData = $this->bcNAVRequest($getBranchCode, $weekStartDate, $weekEndDate);
            //    DiaryRecords::upsert($getNavData, 'order_no');
            //}
            //  else {
            //     $countofBranches = $this->getCountOfBranches();
            //     for ($i = 1; $i <= $countofBranches; $i++) {
            //         $getBranchCode = $this->getBranchCode($i);
            //         if($getBranchCode!=''){
            //             // $getNavData = $this->bcNAVRequest($getBranchCode, $weekStartDate, $weekEndDate);
            //             // DiaryRecords::upsert($getNavData, 'order_no');
            //         }
            //     }
            // }

            // print_r($request);
            $exclude = ['COURIER', 'POST', 'COLLECTION'];
            $new_orders = DB::connection('mysql')->table('diary as d')
                // ->select('d.diary_id', 'd.order_no', 'd.ship_to_name', 'd.ship_to_post_code', 'd.ship_to_county', 'd.ship_to_city', 'd.ship_to_region_code', 'd.type_of_supply_code', 'd.order_weight',
                //     'd.order_amount', 'd.currency_code', 'd.location_code', 'd.shipping_agent_code', 'd.shipping_agent_service_code', 'd.shipment_type', 'd.promised_delivery_date',
                //     'd.delivery_confirmed', 'd.last_update', 'd.balance_amount', 'd.last_shipping_no', 'd.ship_status', 'd.completed', 'd.dispatch_requested_date')

                ->select('*')

                ->whereRaw("d.last_update > ? AND d.location_code=? AND d.promised_delivery_date BETWEEN ? AND ?", array($lastRefresh, $getBranchCode, $weekStartDate, $weekEndDate))

                ->whereNotIn("d.shipping_agent_service_code", array('COURIER', 'POST', 'COLLECTION'))

                ->get();


        } catch (\Exception $e) {
            $this->error_msg = "error";
            die("Could not connect to the database. Please check your configuration. error:" . $e);
        }
        return response()->json([
            'error_msg' => $this->error_msg,
            'new_orders' => $new_orders->toArray()
        ]);
    }
    public function getDataFromViewAction(Request $request)
    {
        $selected_branch_id = $request->selectedBranch;
        $selectedDate = $request->selectedDate;
        $selectedDate = date('Y-m-d', strtotime($selectedDate));
        $newSelectedDateFormat = new Carbon($selectedDate);
        $weekStartDate = $newSelectedDateFormat->startOfWeek()->format('Y-m-d');
        $weekEndDate = $newSelectedDateFormat->endOfWeek()->format('Y-m-d');
        return $this->callAPIRequestForOrders($selected_branch_id, $weekStartDate, $weekEndDate);
    }
    /*get data from view branch id,selected date*/
   
    public function callAPIRequestForOrders($selected_branch_id, $weekStartDate, $weekEndDate)
    {
       
        $this->error_msg='';
        try{
            $interval_period = CarbonPeriod::create($weekStartDate, $weekEndDate);
            $exclude = ['COURIER', 'POST', 'COLLECTION'];
           
            /**Get selected week comments */
            $getComments=$this->getCommentsIndependently($weekStartDate, $weekEndDate);
            $getBranchList=$this->getAllBranchDetails();
            /**Get selected van details */
            $selectedBranchVanNumbers = $this->getVanDetails($selected_branch_id,$weekStartDate);
           
            $selectedWeekVansWithoutRuns=$this->getDistinctVanDetails($selected_branch_id,$weekStartDate);
            $weeklyWeightAndValueForSingleVan = array();
            // foreach ($selectedBranchVanNumbers as $key => $value) {
            //     $weeklyWeightAndValueForSingleVan[$value->shipping_agent_service_code] = $this->totalWeekWeightAndValueForSingleVan($value, $weekStartDate, $weekEndDate);
            // }
            $collectionData='';
            /*selected branch*/
            if($selected_branch_id!=0){
                /**get bookable status details for selected week and branch */
                $getBookableValues = $this->getBookableStatus($selected_branch_id, $weekStartDate, $weekEndDate);
                //Get location code with branch_id
                $getBranchCode = $this->getBranchCode($selected_branch_id);
                /**Collection orders */
                $collectionData = $this->getCollectionData($getBranchCode, $weekStartDate, $weekEndDate);
                /**Post Orders */
                $postData = $this->getPostData($getBranchCode, $weekStartDate, $weekEndDate);
                /**Courier Orders */
                $courierData = $this->getCourierData($getBranchCode, $weekStartDate, $weekEndDate);
                /**VAN Orders */
                //    $getNavData=$this->bcNAVRequest($getBranchCode, $weekStartDate, $weekEndDate);
              
                /**Compare nav and diary data and modify the diary data if any changes required using upsert
                 * Here promised_delivery_date,location_code,shipping_agent_service_code ares index key
                 * Order_no is unique_key
                 * 
                 */
                $getDiaryData=DiaryRecords::where('location_code','=',$getBranchCode)
                ->whereBetween('promised_delivery_date',[$weekStartDate,$weekEndDate])
                ->get();
                
                $finalDiaryData=json_decode(json_encode($getDiaryData), true);

                /**Remove if any order not exist in nav data */
                // foreach($finalDiaryData as $key=>$dbData){
                //     $order_status=false;
                //     foreach($getNavData as $key2=>$itemInNav){
                //         if($dbData['order_no']==$itemInNav['order_no']){
                //             $order_status=true;
                //             break;
                //         }
                //     }
                //     if($order_status==false){
                //         $deleteRecord=DiaryRecords::where('order_no',$dbData["order_no"])->delete();
                //     }
                // }
                // DiaryRecords::upsert($getNavData, 'order_no');
                /**Now fetch final diary table data for selected branch and week */
                $getSelectedVehicleData = Vehicle::from('vehicle as v')
                ->join('diary as d', 'v.shipping_agent_service_code', 'd.shipping_agent_service_code')
                ->select('v.shipping_agent_service_code as van_number',
                    'v.delivery_capacity', 'v.target_amount', 'v.registration_number', 'v.vehicle_type',
                    'd.diary_id', 'd.order_no', 'd.ship_to_name', 'd.ship_to_post_code', 
                    'd.ship_to_county', 'd.ship_to_city', 'd.ship_to_region_code', 'd.type_of_supply_code', 
                    'd.order_weight','d.order_amount', 'd.currency_code', 'd.location_code', 
                    'd.shipping_agent_code', 'd.shipment_type', 'd.promised_delivery_date',
                    'd.delivery_confirmed', 'd.updated_at', 'd.balance_amount', 'd.last_shipping_no', 
                    'd.ship_status', 'd.completed', 'd.dispatch_requested_date'

                    // DB::raw("(SELECT vdc.comments FROM vehicle_diary_comments vdc  WHERE
                    // vdc.vehicle_id = v.vehicle_id AND vdc.vehicle_diary_date = d.promised_delivery_date) AS comments")
                )
                ->where('location_code','=',$getBranchCode)
                ->whereBetween('promised_delivery_date',[$weekStartDate,$weekEndDate])
                ->whereNotIn('d.shipping_agent_service_code', $exclude)
                ->orderBy('d.promised_delivery_date', 'ASC')
                ->orderBy('d.shipping_agent_service_code', 'ASC')->get();
                
            }
            /*All branches*/
            // else{
               
            //     /**get bookable status details for selected week and all branches */
            //     $getBookableValues = $this->getBookableStatus(0, $weekStartDate, $weekEndDate);
            //     /**Collection orders */
            //     $collectionData = $this->getCollectionData('All', $weekStartDate, $weekEndDate);
            //     /**Post Orders */
            //     $postData = $this->getPostData('All', $weekStartDate, $weekEndDate);
            //     /**Courier Orders */
            //     $courierData = $this->getCourierData('All', $weekStartDate, $weekEndDate);

            //     $getTotalWtAndValuePerDay=$this->totalWeekWeightAndValueForEachDay($selected_branch_id,$weekStartDate,$weekEndDate);
              
            //     $getBranchIds = $this->getBranchIdsList();
                
            //     //$getNavData ='';
            //     foreach($getBranchIds as $branch_id){
            //         $getBranchCode = $this->getBranchCode($branch_id);
            //         //$getNavData = $this->_getSetNavData($getBranchCode, $weekStartDate, $weekEndDate);
            //         // $getNavData=$this->bcNAVRequest($getBranchCode, $weekStartDate, $weekEndDate);
            //         /**Compare nav and diary data and modify the diary data if any changes required using upsert
            //          * Here promised_delivery_date,location_code,shipping_agent_service_code ares index key
            //          * Order_no is unique_key
            //          * 
            //          */
            //         $getDiaryData=DiaryRecords::where('location_code','=',$getBranchCode)
            //         ->whereBetween('promised_delivery_date',[$weekStartDate,$weekEndDate])
            //         ->get();
            //         $finalDiaryData=json_decode(json_encode($getDiaryData), true);
            //         /**Remove if any order not exist in nav data */
            //         // foreach($finalDiaryData as $key=>$dbData){
            //         //     $order_status=false;
            //         //     foreach($getNavData as $key2=>$itemInNav){
            //         //         if($dbData['order_no']==$itemInNav['order_no']){
            //         //             $order_status=true;
            //         //             break;
            //         //         }
            //         //     }
            //         //     if($order_status==false){
            //         //         $deleteRecord=DiaryRecords::where('order_no',$dbData["order_no"])->delete();
            //         //     }
            //         // }
            
            //     }
            //     /**Now fetch final diary table data for all branches and selected week */
            //     //DiaryRecords::upsert($getNavData, 'order_no');
            //     /**Now fetch final diary table data for selected branch and week */
            //     $getSelectedVehicleData = Vehicle::from('vehicle as v')
            //     ->join('diary as d', 'v.shipping_agent_service_code', 'd.shipping_agent_service_code')
            //     ->select('v.shipping_agent_service_code as van_number',
            //         'v.delivery_capacity', 'v.target_amount', 'v.registration_number', 'v.vehicle_type',
            //         'd.diary_id', 'd.order_no', 'd.ship_to_name', 'd.ship_to_post_code', 
            //         'd.ship_to_county', 'd.ship_to_city', 'd.ship_to_region_code', 'd.type_of_supply_code', 
            //         'd.order_weight','d.order_amount', 'd.currency_code', 'd.location_code', 
            //         'd.shipping_agent_code', 'd.shipment_type', 'd.promised_delivery_date',
            //         'd.delivery_confirmed', 'd.updated_at', 'd.balance_amount', 'd.last_shipping_no', 
            //         'd.ship_status', 'd.completed', 'd.dispatch_requested_date',
            //         DB::raw("(SELECT vdc.comments FROM vehicle_diary_comments vdc  WHERE 
            //         vdc.vehicle_id = v.vehicle_id AND vdc.vehicle_diary_date = d.promised_delivery_date) AS comments"))
            //     ->where('location_code','=',$getBranchCode)
            //     ->whereBetween('promised_delivery_date',[$weekStartDate,$weekEndDate])
            //     ->whereNotIn('d.shipping_agent_service_code', $exclude)
            //     ->orderBy('d.promised_delivery_date', 'ASC')
            //     ->orderBy('d.shipping_agent_service_code', 'ASC')->get();           
                
            // }

            //combine collection and post data
            $collectionPostData = array_merge($collectionData->toArray(), $postData->toArray());
            $dayWeightAndValueForAllVans = array();
            $collectionValueBooked = 0.0;
            $collectionBookedWeight = 0.0;
            $postValueBooked = 0.0;
            $postBookedWeight = 0.0;
            $courierValueBooked = 0.0;
            $courierBookedWeight = 0.0;


            /**
             * It will get each day total weight and value for Courier,Post,Collection and Vans.
             * Also entire selected week orders for collection,post and courier with weight and value.
             */
            foreach ($interval_period as $date) {
                $totalValue = 0.0;
                $totalWeight = 0.0;
                $totalWtPostColl = 0.0;
                $totalValuePostColl = 0.0;
                $totalCourierValue = 0.0;
                $totalCourierWeight = 0.0;
                $orders_date = $date->format('l');
                $selectedOrdersDate = $date->format('d-m-Y');
                $dummy_array = array();
                /*All vehicles for one day total weight and value*/
                foreach ($getSelectedVehicleData as $key2 => $value1) {
                    if ($date == $value1->promised_delivery_date) {
                        $totalWeight += $value1->order_weight;
                        $totalValue += $value1->order_amount;

                    }
                }
                $dayWeightAndValueForAllVans[$selectedOrdersDate]['weight'] = number_format($totalWeight, 2, '.', '');
                $dayWeightAndValueForAllVans[$selectedOrdersDate]['value'] = number_format($totalValue, 2, '.', '');
                /*All collection and post orders for one day total weight and value*/
                foreach ($collectionPostData as $key2 => $value1) {
                    
                    if ($date->format('Y-m-d 00:00:00') == $value1['promised_delivery_date']) {
                        $totalWtPostColl += $value1['order_weight'];
                        $totalValuePostColl += $value1['order_amount'];
                    }
                }
                $dayWeightAndValueForAllVans[$selectedOrdersDate]['weightPostCollection'] = $totalWtPostColl;
                $dayWeightAndValueForAllVans[$selectedOrdersDate]['valuePostCollection'] = $totalValuePostColl;
                /*Collection data*/
                $collectionDataForSelectedWeek[$orders_date] = '';
                foreach ($collectionData as $key => $value1) {
                    if ($date == $value1->promised_delivery_date) {
                        array_push($dummy_array, $value1);
                        $collectionValueBooked += $value1->order_amount;
                        $collectionBookedWeight += $value1->order_weight;

                    }
                }
                $dummy_array['date'] = $selectedOrdersDate;
                $collectionDataForSelectedWeek[$orders_date] = $dummy_array;
                /*Post data*/
                $dummy_array = array();
                $postDataForSelectedWeek[$orders_date] = '';
                foreach ($postData as $key => $value1) {
                    if ($date == $value1->promised_delivery_date) {
                        array_push($dummy_array, $value1);
                        $postValueBooked += $value1->order_amount;
                        $postBookedWeight += $value1->order_weight;
                    }
                }
                $dummy_array['date'] = $selectedOrdersDate;
                $postDataForSelectedWeek[$orders_date] = $dummy_array;
                /*All courier orders for one day total weight and value*/
                $dummy_array = array();
                $courierDataForSelectedWeek[$orders_date] = '';
                foreach ($courierData as $key2 => $value1) {
                    if ($date == $value1->promised_delivery_date) {
                        $totalCourierWeight += $value1->order_weight;
                        $totalCourierValue += $value1->order_amount;
                        array_push($dummy_array, $value1);
                        $courierValueBooked += $value1->order_amount;
                        $courierBookedWeight += $value1->order_weight;
                    }
                }
                $dummy_array['date'] = $selectedOrdersDate;
                $courierDataForSelectedWeek[$orders_date] = $dummy_array;
                $dayWeightAndValueForAllVans[$selectedOrdersDate]['weightCourier'] = $totalCourierWeight;
                $dayWeightAndValueForAllVans[$selectedOrdersDate]['valueCourier'] = $totalCourierValue;
                
                $collectionDataForSelectedWeek["collectionValueBooked"] = $collectionValueBooked;
                $collectionDataForSelectedWeek["collectionBookedWeight"] = $collectionBookedWeight;
                $postDataForSelectedWeek["postValueBooked"] = $postValueBooked;
                $postDataForSelectedWeek["postBookedWeight"] = $postBookedWeight;
                $courierDataForSelectedWeek["courierValueBooked"] = $courierValueBooked;
                $courierDataForSelectedWeek["courierBookedWeight"] = $courierBookedWeight;
            }
            
            /**
            * All Orders for selected week
            */
            $allOrdersForSelectedWeek = array();
        
            foreach ($selectedBranchVanNumbers as $key => $value) {
                foreach ($interval_period as $date) {
                    $finalDate = $date->format('l');
                    foreach ($getSelectedVehicleData as $key1 => $value1) {
                        if ($value->shipping_agent_service_code == $value1->van_number) {
                            $allOrdersForSelectedWeek[$value->shipping_agent_service_code][$finalDate] = "";
                            
                        }
                    }
                    $allOrdersForSelectedWeek[$value->shipping_agent_service_code]["registration_number"] = "";
                    $allOrdersForSelectedWeek[$value->shipping_agent_service_code]["hideOrShowVehicle"] = "";
                    $allOrdersForSelectedWeek[$value->shipping_agent_service_code]["totalWeight"] = "";
                    $allOrdersForSelectedWeek[$value->shipping_agent_service_code]["totalValue"] = "";
                }
            }
            // /**For selected branch */
            if ($selected_branch_id != 0) {
                /** Get selected branch post code,latitude and longitude */

                $selected_branch_postcode = $this->getSelectedBranchPostCode($selected_branch_id);
                foreach ($selectedBranchVanNumbers as $key => $vannumbers) {
                    // For Each Van
                    $targetAmount = 0;
                    $targetWeight = 0;
                    foreach ($interval_period as $date) {
                        //interval_period = week For each Day
                        $check2 = array();
                        $api_routing = array();
                        $additional_data = array();
                        $originalSequence = array();
                       $optimizeRouteSequence=array();
                        $displayOrdersPosition = array();

                        $gMapPostCodesString = '';

                        $allOrderNos = array();
                        $valueBooked = 0;
                        $bookedWeight = 0;
                        $i=0;
                      
                        $uniqueId = $selected_branch_id . '/' . $vannumbers->shipping_agent_service_code . '/' . $date->format('Y-m-d');

                        foreach ($getSelectedVehicleData as $key1 => $order) {
                            
                            if($vannumbers->shipping_agent_service_code == $order->van_number
                            AND $date->format('Y-m-d 00:00:00')==$order->promised_delivery_date){

                                $promised_delivery_date = $order->promised_delivery_date;
                                $allOrderNos[] = $order->order_no;
                                //total values
                                $valueBooked += $order->order_amount;
                                $bookedWeight += $order->order_weight;
                                //traget values
                                $targetAmount = $order->target_amount;
                                $targetWeight = $order->delivery_capacity;

                                $check2[] = $order;
                                $originalSequence[$i++] = $order->ship_to_post_code;

                                if ($order->ship_to_post_code != '') {
                                    // populate gmaps postcode string with postcodes 
                                    $gMapPostCodesString .= $order->ship_to_post_code."|";
                                } else {
                                    $gMapPostCodesString = '';
                                }
                            }                        
                        }
                        
                        if($gMapPostCodesString!=''){
                            // check if order is in database if it is then take the data already stored there
                            //if not then call the GMaps API 
                                $order_nos = $this->getAPIOrderNumbers($uniqueId);
                                
                                if($order_nos != null){
                                    sort($order_nos);
                                    sort($allOrderNos);
                                }               
                                // $gMapRes = $this->getGmapsResponse($originalSequence,$selected_branch_postcode);
                                // print_r($gMapRes);

                                // print_r($originalSequence);
                                // print_r($order_nos);
                                // print_r($allOrderNos === $order_nos);

                                // $gMapRes = $this->getGmapsResponse($originalSequence,$selected_branch_postcode);
                                // print_r($gMapRes);
                            if($order_nos == null || $order_nos != $allOrderNos){
                               
                                 try{

                                    $gMapRes = $this->getGmapsResponse($originalSequence,$selected_branch_postcode);
                                    $response = $this->handleGmapsResponse($gMapRes,$uniqueId);
    
                                    if($response['ApiError'] == "wrong api calculation")
                                    {
                                        $newseq = $this->convertPostcodesToLatLng($originalSequence); 
                                        $gMapRes = $this->getGmapsResponse($newseq,$selected_branch_postcode);
                                        $response = $this->handleGmapsResponse($gMapRes,$uniqueId);
                                    }

                                    $journeyDistanceInMiles = (float)$response['totalDistance'];
                                    $travelTimeInSeconds = (int)$response['totalDuration'];

                                    $this->setAPIRoutingDetails($api_routing, $promised_delivery_date, $uniqueId, $selected_branch_id,
                                    $allOrderNos, $originalSequence, $vannumbers, $journeyDistanceInMiles, $travelTimeInSeconds );
    
                                }catch( Exception $error){
                                    print_r($error);
                                }

                            }else{

                                $api_routing_table = $this->getAPIRoutingDetails($uniqueId);
                                $travelTimeInSeconds = $api_routing_table[0]->time_seconds;
                                $journeyDistanceInMiles = $api_routing_table[0]->distance_miles;
                            }                            
                            $additional_data["travelTime"] =  $travelTimeInSeconds != 0 ? gmdate("H:i:s", $travelTimeInSeconds) : 0;
                            $additional_data["journeyDistance"] = number_format($journeyDistanceInMiles, 2, '.', '');  
                        }

                        $additional_data["comments"] = "";
                        foreach ($getComments as $value) {
                            if ($value->shipping_agent_service_code == $vannumbers->shipping_agent_service_code && $value->vehicle_diary_date == $date->format('Y-m-d')) {
                                $additional_data["comments"] = str_replace('&amp;', '&', $value->comments);
                            }
                        }
                        /** Bookable status */
                        foreach ($getBookableValues as $key => $bookingStatus) {
                            if ($bookingStatus->shipping_agent_service_code == $vannumbers->shipping_agent_service_code && $bookingStatus->vehicle_booking_date == $date->format('Y-m-d')) {
                                $bookableStatus = $bookingStatus->vehicle_booking_status;
                                $additional_data["bookable_status"] = $bookableStatus;
                            }
                        }
                        $additional_data["value_booked"] = number_format($valueBooked, 2, '.', '');
                        $additional_data["to_book"] = number_format($targetAmount - $valueBooked, 2, '.', '');
                        $additional_data["booked_weight"] = number_format($bookedWeight, 2, '.', '');
                        $additional_data["remaining_weight"] = number_format($targetWeight - $bookedWeight, 2, '.', '');
                        $additional_data["date"] = $date->format('d-m-Y');
                        
                        $allOrdersForSelectedWeek[$vannumbers->shipping_agent_service_code][$date->format('l')] = array_merge($check2, $additional_data);
                    }
                    $allOrdersForSelectedWeek[$vannumbers->shipping_agent_service_code]["registration_number"] = $vannumbers->registration_number;
                    $allOrdersForSelectedWeek[$vannumbers->shipping_agent_service_code]["hideOrShowVehicle"] = $vannumbers->collapse_status;
                    $allOrdersForSelectedWeek[$vannumbers->shipping_agent_service_code]["totalWeight"] = $vannumbers->delivery_capacity;
                    $allOrdersForSelectedWeek[$vannumbers->shipping_agent_service_code]["totalValue"] = $vannumbers->target_amount;
                    foreach ($weeklyWeightAndValueForSingleVan as $key => $currentVan) {
                        foreach ($currentVan as $key3 => $data) {
                            if ($vannumbers->shipping_agent_service_code == $data->van_number) {
                                $allOrdersForSelectedWeek[$vannumbers->shipping_agent_service_code]["totalWeight"] = $data->totalWeight;
                                $allOrdersForSelectedWeek[$vannumbers->shipping_agent_service_code]["totalValue"] = $data->totalValue;
                            }
                        }
                    }
                }
            }
            /**For all branches */
           
            // else{
            //     $countofBranches =$this->getDistinctCountOfBranches();
            //     for ($branchIdForAll = 1; $branchIdForAll <= $countofBranches; $branchIdForAll++) {
            //         /** Get selected branch post code,latitude and longitude */
            //         $selected_branch_postcode = $this->getSelectedBranchPostCode($branchIdForAll);
            //         $selectedBranchDetails = $this->getBranchLatAndLong($branchIdForAll);
            //         $selectedLocationCode = $this->getBranchCode($branchIdForAll);
            //         if($selectedLocationCode!=''){
            //             $branchLatLong = $selectedBranchDetails[0]->latitude . ',' . $selectedBranchDetails[0]->longitude;
            //         }
            //         foreach ($selectedBranchVanNumbers as $key => $vannumbers) {
            //             if ($vannumbers->branch_id == $branchIdForAll) {
            //                 $targetAmount = 0;
            //                 $targetWeight = 0;
            //                 foreach ($interval_period as $date) {
            //                     $check2 = array();
            //                     $api_routing = array();
            //                     $additional_data = array();
            //                     $originalSequence = array();
            //                     $displayOrdersPosition = array();
            //                     $gMapPostCodesString = ''; 
            //                     $allOrderNos = array();
            //                     $valueBooked = 0;
            //                     $bookedWeight = 0;
            //                     $travelTimeInSeconds = 0;
            //                     $journeyDistanceInMiles = 0;
            //                     $api_status = false;
            //                     $i = 0;
            //                     $originalSequence[$i++] = $selected_branch_postcode;
            //                     $uniqueId = $branchIdForAll . '/' . $vannumbers->shipping_agent_service_code . '/' . $date->format('Y-m-d');
                                
            //                     foreach ($getSelectedVehicleData as $key1 => $order) {
            //                         if($vannumbers->shipping_agent_service_code == $order->van_number
            //                         AND $date->format('Y-m-d 00:00:00')==$order->promised_delivery_date){
            //                             $promised_delivery_date = $order->promised_delivery_date;
            //                             $allOrderNos[] = $order->order_no;
            //                             $valueBooked += $order->order_amount;
            //                             $bookedWeight += $order->order_weight;
            //                             $targetAmount = $order->target_amount;
            //                             $targetWeight = $order->delivery_capacity;

            //                             $check2[] = $order;
            //                             $originalSequence[$i++] = $order->ship_to_post_code;                                      

            //                             if ($order->ship_to_post_code != '') {
            //                                 $gMapPostCodesString .= $order->ship_to_post_code."|";
        
            //                             } else {
            //                                 $gMapPostCodesString = '';
            //                             }
            //                         }                                
            //                     }

            //                     $originalSequence[$i++] = $selected_branch_postcode;
                               
            //                     if($gMapPostCodesString!=''){
            //                         // check if order is in database if it is then take the data already 
            //                         //stored there if not then call the GMaps API  

            //                         $order_nos = $this->getAPIOrderNumbers($uniqueId);  

            //                         if($order_nos != null){
            //                             sort($order_nos);
            //                             sort($allOrderNos);
            //                         }                        

            //                         if($order_nos == null || $order_nos!=$allOrderNos){
        
            //                             try{

            //                                 $gMapRes = $this->getGmapsResponse($originalSequence,$selected_branch_postcode);
            //                                 $response = $this->handleGmapsResponse($gMapRes,$uniqueId);
            
            //                                 if($response['ApiError'] == "wrong api calculation")
            //                                 {
            //                                     $newseq = $this->convertPostcodesToLatLng($originalSequence);        
            //                                     $gMapRes = $this->getGmapsResponse($newseq,$selected_branch_postcode);
            //                                     $response = $this->handleGmapsResponse($gMapRes,$uniqueId);
            //                                 }        
            //                                 $journeyDistanceInMiles = (float)$response['totalDistance'];
            //                                 $travelTimeInSeconds = (int)$response['totalDuration'];

            //                                 $this->setAPIRoutingDetails($api_routing, $promised_delivery_date, $uniqueId, $selected_branch_id,
            //                                 $allOrderNos, $originalSequence, $vannumbers, $journeyDistanceInMiles, $travelTimeInSeconds );
            
            //                             }catch( Exception $error){
            //                                 print_r($error);
            //                             }                                        
            //                         }
            //                         else{        
            //                             $api_routing_table = $this->getAPIRoutingDetails($uniqueId);        
            //                             $travelTimeInSeconds = $api_routing_table[0]->time_seconds;
            //                             $journeyDistanceInMiles = $api_routing_table[0]->distance_miles;
            //                         }       
                                    
            //                         $additional_data["travelTime"] =  $travelTimeInSeconds != 0 ? gmdate("H:i:s", $travelTimeInSeconds) : 0;
            //                         $additional_data["journeyDistance"] = number_format($journeyDistanceInMiles, 2, '.', '');  
            //                     }
            //                     $additional_data['display_order_position'] = $displayOrdersPosition;
            //                     /**Adding comments to end of each day for van */
            //                     $additional_data["comments"] = "";
            //                     foreach ($getComments as $value) {
            //                         if (Str::contains($vannumbers->shipping_agent_service_code,$value->shipping_agent_service_code) && $value->vehicle_diary_date ==$date->format('Y-m-d')) {
            //                             $additional_data["comments"] = str_replace('&amp;', '&', $value->comments);
            //                         }
            //                     }
            //                     /** Bookable status */
            //                     foreach ($getBookableValues as $key => $bookingStatus) {
            //                         if ($bookingStatus->shipping_agent_service_code == $vannumbers->shipping_agent_service_code && $bookingStatus->vehicle_booking_date == $date->format('Y-m-d')) {
            //                             $bookableStatus = $bookingStatus->vehicle_booking_status;
            //                             $additional_data["bookable_status"] = $bookableStatus;
            //                         }
            //                     }
            //                     $additional_data["value_booked"] = number_format($valueBooked, 2, '.', '');
            //                     $additional_data["to_book"] = number_format($targetAmount - $valueBooked, 2, '.', '');
            //                     $additional_data["booked_weight"] = number_format($bookedWeight, 2, '.', '');
            //                     $additional_data["remaining_weight"] = number_format($targetWeight - $bookedWeight, 2, '.', '');
            //                     $additional_data["date"] = $date->format('d-m-Y');
            //                     if(count($check2)>0){
            //                         if(count($check2)==count($displayOrdersPosition))
            //                             array_multisort($check2,$displayOrdersPosition);
            //                     }
            //                     $allOrdersForSelectedWeek[$vannumbers->shipping_agent_service_code][$date->format('l')] = array_merge($check2, $additional_data);
            //                 }
            //                 $allOrdersForSelectedWeek[$vannumbers->shipping_agent_service_code]["registration_number"] = $vannumbers->registration_number;
            //                 $allOrdersForSelectedWeek[$vannumbers->shipping_agent_service_code]["hideOrShowVehicle"] = $vannumbers->collapse_status;
                        
            //                 foreach ($weeklyWeightAndValueForSingleVan as $key => $currentVan) {
            //                     foreach ($currentVan as $key3 => $data) {
            //                         if ($vannumbers->shipping_agent_service_code == $data->van_number) {
            //                             $allOrdersForSelectedWeek[$vannumbers->shipping_agent_service_code]["totalWeight"] = $data->totalWeight;
            //                             $allOrdersForSelectedWeek[$vannumbers->shipping_agent_service_code]["totalValue"] = $data->totalValue;
            
            //                         }
            //                     }
            //                 }
            //             }
            //         }
            //     }
            // }
            /**Seggrate data with each Van with sub runs */
            $forallRuns = array();
            
            foreach ($selectedWeekVansWithoutRuns as $van_details) {
                
                $singleVan = array();
                $totalDayWtAndValueForAllRuns = array();
                
                foreach ($allOrdersForSelectedWeek as $key => $value) {
                    if (Str::contains($van_details->registration_number, $value["registration_number"])) {
                        $singleVan[$key] = $value;
                    }
                }
               
                $result = $this->getEachDayTotalValueAndWt($van_details->van_number, $weekStartDate, $weekEndDate);
                $getMileage = $this->getEachDayTotalMileage($van_details->van_number, $weekStartDate, $weekEndDate);

                foreach ($interval_period as $date) {
                    $setDate = $date->format('l');
                    $checkDate = $date->format('Y-m-d');
                    $data_status = false;
                    $data_status1 = false;
                    foreach ($result as $key => $total) {

                        if ($total->promised_delivery_date == $date) {
                            $totalDayWtAndValueForAllRuns[$setDate]["totalDayWeight"] = $total->totalDayWeight;
                            $totalDayWtAndValueForAllRuns[$setDate]["totalDayValue"] = $total->totalDayValue;
                            $data_status = true;
                            break;
                        }
                    }
                    foreach ($getMileage as $key => $total) {

                        if ($total->promised_delivery_date == $checkDate) {

                            $totalDayWtAndValueForAllRuns[$setDate]["totalTravelTime"] = gmdate("H:i:s", $total->totalTravelTime);
                            $totalDayWtAndValueForAllRuns[$setDate]["totalJourneyDistance"] = $total->totalJourneyDistance;
                            $data_status1 = true;
                            break;
                        }
                    }

                    if ($data_status == false) {
                        $totalDayWtAndValueForAllRuns[$setDate]["totalDayWeight"] = 0.0;
                        $totalDayWtAndValueForAllRuns[$setDate]["totalDayValue"] = 0.0;

                    }
                    if ($data_status1 == false) {
                        $totalDayWtAndValueForAllRuns[$setDate]["totalTravelTime"] = 0.0;
                        $totalDayWtAndValueForAllRuns[$setDate]["totalJourneyDistance"] = 0.0;
                    }
                }

                $forallRuns[$van_details->van_number] = $singleVan;
                $forallRuns[$van_details->van_number]["parent_collapse_status"] = $van_details->parent_collapse;
                $forallRuns[$van_details->van_number]["delivery_capacity"] = $van_details->delivery_capacity;
                $forallRuns[$van_details->van_number]["registration_number"] = $van_details->registration_number;
                $forallRuns[$van_details->van_number]["vehicle_type"] = $van_details->vehicle_type;
                $forallRuns[$van_details->van_number]["target_amount"] = $van_details->target_amount;
                $forallRuns[$van_details->van_number]['totalDayWtAndValue'] = $totalDayWtAndValueForAllRuns;

            }
        }catch (\Exception $e) {
            die("Could not connect to the database. Please check your configuration. error:" . $e);
            $this->error_msg='Could not connect to the database' . $e ;
        } 
        return response()->json([
            'dairyData'=>$getSelectedVehicleData,
            'dayWeightAndValueForAllDeliveries' => $dayWeightAndValueForAllVans,
            'collectionOrdersForSelectedWeek' => $collectionDataForSelectedWeek,
            'postOrdersForSelectedWeek' => $postDataForSelectedWeek,
            'courierOrdersForSelectedWeek' => $courierDataForSelectedWeek,
            'vehicleOrdersWithRuns' => $forallRuns,
            'branch_list'=>$getBranchList,
            'selectedBranchVanNumbers'=>$selectedBranchVanNumbers,
            'allOrdersForSelectedWeek'=>$allOrdersForSelectedWeek
        ]);
    }

    /**
     * processing MAPQuest API
     */
    // private function processAPIRoutingDetails(&$api_status, &$travelTimeInSeconds, &$journeyDistanceInMiles, &$optimizeRouteSequence, &$displayOrdersPosition, $order_nos, $allOrderNos, $uniqueId)
   
    private function processAPIRoutingDetails(&$api_status, &$optimizeRouteSequence, &$displayOrdersPosition, $order_nos, $allOrderNos, $uniqueId)
    {
        if ($order_nos != '') {
            /**record exist but order no's mismatch call api request */
            if (sort($order_nos) != sort($allOrderNos)) {
                $api_status = false;
            } else {
                /**If order no's same get the required details */
               $api_routing_table = $this->getAPIRoutingDetails($uniqueId);
               
               if ($api_routing_table != '') {
                    $api_status = true;
                    $travelTimeInSeconds = $api_routing_table[0]->time_seconds;
                    $journeyDistanceInMiles = $api_routing_table[0]->distance_miles;

                    $optimizeRouteSequence = unserialize($api_routing_table[0]->original_route);
                    $displayOrdersPosition = unserialize($api_routing_table[0]->display_orders_position);
                } else {
                    /**else call again MapQuest API */
                    $api_status = false;
                }
            }
        }
    }
    /** New BC NAV */
    // public function bcNAVRequest($getBranchCode, $weekStartDate, $weekEndDate){
    //     /**Without handling single thread mechansim */
    //     try{
    //         $tempArray = array();
    //         $grant_type="client_credentials";
    //         $clientId="65602c4e-a6b4-4aaa-b7a2-c09acc338408";
    //         $clientSecret=".QJ8Q~XePlCMWoE1iAfNSDFjXlmzKuVlMa-nGaDb";
    //         $scope="https://api.businesscentral.dynamics.com/.default";
    //         $tenantID="2739a659-2d71-434e-9bcc-29f7df81ca59";
    //         $loginURL="https://login.microsoftonline.com/".$tenantID."/oauth2/v2.0/token";
    //         $response=Http::asForm()->post($loginURL,[
    //             'grant_type'=>$grant_type,
    //             'client_id'=>$clientId,
    //             'client_secret'=>$clientSecret,
    //             'scope'=>$scope
    //         ]);
    //         $responseResult=$response->json();
    
           
    //         if(isset($responseResult['error'])){
    //             print_r($responseResult['error']);
                
    //         }else{
    //             $access_token=$responseResult['access_token'];
    //             $token_type=$responseResult['token_type'];
    //             $expiry_time=$responseResult['expires_in'];
    //             $baseUrl = 'https://api.businesscentral.dynamics.com/v2.0/Production/api/Dunsterhouse/webSite/v2.0/';
    //             $companyId = 'b94a976f-5933-ee11-bdf5-000d3ad51ae4';
    //             $endpoint = "companies($companyId)/deliveryDiary";
    //             $filter = "locationCode eq '$getBranchCode' and promisedDeliveryDate ge $weekStartDate and promisedDeliveryDate le $weekEndDate";
                
    //             $url = $baseUrl . $endpoint . '?$filter=' . $filter;
    //             $response = Http::withOptions([
    //                 'timeout' => 60, // Increase timeout to 60 seconds
    //             ])->withHeaders([
    //                 'Authorization' => $token_type.' '.$access_token,
    //             ])->get($url);

    //             if ($response->successful()) {
    //                 $getDataFromNav = $response->json();
                 
    //                 foreach ($getDataFromNav['value'] as $itemNav) {
    //                     $result = array(
    //                         'order_no' => $itemNav['no'],
    //                         'ship_to_name' => $itemNav['shipToName'],
    //                         'ship_to_post_code' => $itemNav['shipToPostCode'],
    //                         'ship_to_county' => $itemNav['shipToCounty'],
    //                         'ship_to_region_code' => $itemNav['shipToCountryRegionCode'],
    //                         'type_of_supply_code' => $itemNav['typeOfSupplyCodeTNP'],
    //                         'order_weight' => $itemNav['Weight'],
    //                         'order_amount' => $itemNav['orderAmountTNP'],
    //                         'currency_code' => $itemNav['currencyCode'],
    //                         'location_code' => $itemNav['locationCode'],
    //                         'shipping_agent_code' => $itemNav['shippingAgentCode'],
    //                         'shipping_agent_service_code' => $itemNav['shippingAgentServiceCode'],
    //                         'shipment_type' => $itemNav['shipmentTypeTNP'],
    //                         'promised_delivery_date' => date('Y-m-d', strtotime($itemNav['promisedDeliveryDate'])),
    //                         'delivery_confirmed' => $itemNav['deliveryConfirmedTNP'],
    //                         'balance_amount' => $itemNav['balanceAmountTNP'],
    //                         'last_shipping_no' => $itemNav['lastShippingNo'],
    //                         'ship_status' => $itemNav['ShipStatus'],
    //                         'completed' => $itemNav['completedTNP'],
    //                         'ship_to_city' => TRIM($itemNav['shipToCity']),
    //                         'dispatch_change' => -1,
    //                         'dispatch_requested_date' => date('Y-m-d', strtotime($itemNav['dispatchRequestedDateTNP']))
    //                     );
    //                     array_push($tempArray, $result);
    //                 }
    //                 // Process the response data here
    //             } else {
    //                 // Handle the request error
    //                 $statusCode = $response->status();
    //                 $errorMessage = $response->body();
    //                 print_r("else");
    //                 print_r($errorMessage);
    //                 // Handle the error accordingly
    //             }
    //         }           
            
    //         return $tempArray;
    //     }catch(ClientException $e){
    //         print_r($e.getResponse());
    //     }
    // }
}
