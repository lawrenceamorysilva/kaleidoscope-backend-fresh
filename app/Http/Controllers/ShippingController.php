<?php
namespace App\Http\Controllers;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class ShippingController extends Controller
{
    public function getShippingCost(Request $request)
    {
        $postcode = $request->input('postcode');
        $suburb = strtoupper(trim($request->input('suburb')));
        $weight = ceil((float)$request->input('weight'));

        // Validate postcode, suburb, and weight match
        $suburbExists = DB::table('shipping_costs')
            ->where('postcode', $postcode)
            ->where('suburb', $suburb)
            ->where('weight_kg', $weight)
            ->exists();

        if (!$suburbExists) {
            return response()->json([
                'error' => "No price found for postcode $postcode, suburb $suburb, and weight $weight kg"
            ], 404);
        }

        // Fetch costs for specific weight
        $costs = DB::table('shipping_costs')
            ->where('postcode', $postcode)
            ->where('suburb', $suburb)
            ->where('weight_kg', $weight)
            ->select('courier', 'suburb', 'cost_aud as cost')
            ->get()
            ->map(function ($item) use ($suburb) {
                return [
                    'courier' => $item->courier,
                    'suburb' => $suburb,
                    'cost' => number_format((float)$item->cost, 2, '.', '')
                ];
            })
            ->sortBy('cost')
            ->values()
            ->toArray();

        if (empty($costs)) {
            return response()->json([
                'error' => "No shipping costs found for postcode $postcode, suburb $suburb, and weight $weight kg"
            ], 404);
        }

        $default = $costs[0];

        return response()->json([
            'postcode' => $postcode,
            'suburb' => $suburb,
            'weight' => $weight,
            'options' => $costs,
            'default' => $default
        ]);
    }
}