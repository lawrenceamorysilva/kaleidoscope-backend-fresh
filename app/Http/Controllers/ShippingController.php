<?php
namespace App\Http\Controllers;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class ShippingController extends Controller
{
    public function getShippingCost(Request $request)
    {
        $postcode = $request->input('postcode');
        $weight = ceil($request->input('weight', 1)); // Round up to nearest kg

        $costs = DB::table('shipping_costs')
            ->where('postcode', $postcode)
            ->where('weight_kg', $weight)
            ->select('courier', 'suburb', 'cost_aud as cost')
            ->get();

        if ($costs->isEmpty()) {
            return response()->json(['error' => 'No shipping data for postcode or weight'], 404);
        }

        $minCost = $costs->min('cost');
        $default = $costs->firstWhere('cost', $minCost);

        return response()->json([
            'postcode' => $postcode,
            'weight' => $weight,
            'options' => $costs,
            'default' => [
                'courier' => $default->courier,
                'suburb' => $default->suburb,
                'cost' => $default->cost
            ]
        ]);
    }
}