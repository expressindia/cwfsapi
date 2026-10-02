<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;

class ProductsController extends Controller
{
    /**
     * Display the Products page.
     */
    public function index(Request $request)
    {
        /*
        |--------------------------------------------------------------------------
        | Temporary UI data
        |--------------------------------------------------------------------------
        |
        | Step 1 only:
        | We are using sample products so we can build and verify the
        | exact layout first.
        |
        | In Step 2 this will be replaced with live Fullscript data.
        |
        */

        $products = [
            [
                'id' => '1',
                'title' => 'MetaGlycemX™ 60 Capsules',
                'brand' => 'Designs for Health',
                'sku' => 'DFH-12345',
                'image' => null,
                'availability' => 'In Stock',
                'shopify_status' => 'Exists',
                'shopify_status_text' => 'Will update',
                'updated_at' => 'Sep 28, 2026 10:30 AM',
                'action' => 'update',
            ],

            [
                'id' => '2',
                'title' => 'Vitamin D Supreme 120 Capsules',
                'brand' => 'Designs for Health',
                'sku' => 'DFH-23456',
                'image' => null,
                'availability' => 'In Stock',
                'shopify_status' => 'Not Found',
                'shopify_status_text' => 'Will create',
                'updated_at' => null,
                'action' => 'push',
            ],

            [
                'id' => '3',
                'title' => 'PurePaleo™ Protein Chocolate 810 g',
                'brand' => 'Designs for Health',
                'sku' => 'DFH-34567',
                'image' => null,
                'availability' => 'Backordered',
                'shopify_status' => 'Exists',
                'shopify_status_text' => 'Will update',
                'updated_at' => 'Sep 25, 2026 02:15 PM',
                'action' => 'update',
            ],

            [
                'id' => '4',
                'title' => 'GI Revive™ 225 g',
                'brand' => 'Designs for Health',
                'sku' => 'DFH-45678',
                'image' => null,
                'availability' => 'In Stock',
                'shopify_status' => 'Exists',
                'shopify_status_text' => 'Up to date',
                'updated_at' => 'Sep 20, 2026 11:20 AM',
                'action' => 'view',
            ],

            [
                'id' => '5',
                'title' => 'OmegaAvail™ Ultra 120 Softgels',
                'brand' => 'Designs for Health',
                'sku' => 'DFH-56789',
                'image' => null,
                'availability' => 'In Stock',
                'shopify_status' => 'Not Found',
                'shopify_status_text' => 'Will create',
                'updated_at' => null,
                'action' => 'push',
            ],

            [
                'id' => '6',
                'title' => 'Magnesium Glycinate 120 Capsules',
                'brand' => 'Designs for Health',
                'sku' => 'DFH-67890',
                'image' => null,
                'availability' => 'Out of Stock',
                'shopify_status' => 'Exists',
                'shopify_status_text' => 'Will update',
                'updated_at' => 'Sep 18, 2026 09:45 AM',
                'action' => 'update',
            ],

            [
                'id' => '7',
                'title' => 'Adrenal Support™ 60 Capsules',
                'brand' => 'Designs for Health',
                'sku' => 'DFH-78901',
                'image' => null,
                'availability' => 'In Stock',
                'shopify_status' => 'Error',
                'shopify_status_text' => 'Sync failed',
                'updated_at' => 'Sep 15, 2026 04:10 PM',
                'action' => 'retry',
            ],

            [
                'id' => '8',
                'title' => 'Berberine Synergy™ 60 Capsules',
                'brand' => 'Designs for Health',
                'sku' => 'DFH-89012',
                'image' => null,
                'availability' => 'In Stock',
                'shopify_status' => 'Exists',
                'shopify_status_text' => 'Up to date',
                'updated_at' => 'Sep 12, 2026 01:25 PM',
                'action' => 'view',
            ],

            [
                'id' => '9',
                'title' => 'Vitamin K2 + D3 60 Capsules',
                'brand' => 'Designs for Health',
                'sku' => 'DFH-90123',
                'image' => null,
                'availability' => 'In Stock',
                'shopify_status' => 'Not Found',
                'shopify_status_text' => 'Will create',
                'updated_at' => null,
                'action' => 'push',
            ],
        ];

        $brands = [
            'All Brands',
            'Designs for Health',
            'Allergy Research Group',
            'A.C. Grace',
            'Nordic Naturals',
            'Thorne',
            'Metagenics',
        ];

        return view('products.index', [
            'products' => $products,
            'brands' => $brands,
            'totalProducts' => 1248,
            'perPage' => 25,
            'selectedCount' => 3,
            'lastSyncedAt' => 'Sep 30, 2026 10:15 AM',
        ]);
    }
}