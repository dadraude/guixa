<?php

// Files that broke rules A13-A17 when the rules were adopted (ADR 0001). The list only shrinks:
// tests/Architecture/PresentationRulesTest.php fails on a new offender and on an entry that no longer
// offends. Fix a file, then delete its line here.
return [
    'A13' => [
        // Starter-kit flash status, not a domain state; leaves when the page receives a flag.
        'resources/js/pages/settings/Profile.vue',
    ],
    'A14' => [],
    'A15' => [],
    'A16' => [],
    'A17' => [],
];
