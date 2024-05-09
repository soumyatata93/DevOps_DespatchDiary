<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;

class TriggerController extends Controller
{
    //
    public function handleUpdates(Request $request)
{
    // Process the order update received from BC Nav
    // You can access the data sent by BC Nav using $request->all() or $request->input('key')

    // Example: Log the received data
    Log::info('Order update received:', $request->all());

    // Add your custom logic to handle the order update

    return response()->json(['message' => 'Order update received'], 200);
}
}
