<?php

namespace App\Controllers;

use App\Core\Controller;
use App\Models\Inventory;
use App\Models\Product;

class HomeController extends Controller
{
    public function index(): void
    {
        $stats = Inventory::countTotal();
        $plans = Product::getAllActive(true);

        $this->render('home/index', [
            'stats' => $stats,
            'plans' => $plans,
        ]);
    }
}
