<?php

// Files that broke rules A13-A17 when the rules were adopted (ADR 0001). The list only shrinks:
// tests/Architecture/PresentationRulesTest.php fails on a new offender and on an entry that no longer
// offends. Fix a file, then delete its line here.
return [
    'A13' => [
        'resources/js/components/baskets/BasketCollectionHistory.vue',
        'resources/js/pages/Finance/Baskets/Index.vue',
        'resources/js/pages/Finance/Baskets/Show.vue',
        'resources/js/pages/Member/Baskets/Index.vue',
        'resources/js/pages/Member/Baskets/Show.vue',
        // Starter-kit flash status, not a domain state; leaves when the page receives a flag.
        'resources/js/pages/settings/Profile.vue',
    ],
    'A14' => [
        'resources/js/lib/orderTotal.ts',
    ],
    'A15' => [
        'src/Sales/Application/BasketLineData.php',
        'src/Sales/Application/BasketWeeklyLineData.php',
    ],
    'A16' => [
        'app/Http/Controllers/DashboardController.php',
        'app/Http/Controllers/Finance/BasketController.php',
        'app/Http/Controllers/Manage/CycleController.php',
        'app/Http/Controllers/Manage/HouseholdController.php',
        'app/Http/Controllers/Manage/PickupController.php',
        'app/Http/Controllers/Manage/ReceptionController.php',
    ],
    'A17' => [],
];
