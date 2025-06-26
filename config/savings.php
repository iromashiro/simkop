<?php
// config/savings.php - New configuration file
return [
    // Daily withdrawal limits per savings type (in IDR)
    'daily_withdrawal_limits' => [
        'pokok' => 0, // Cannot withdraw share capital
        'wajib' => 0, // Cannot withdraw mandatory savings while active
        'khusus' => 5000000, // 5M IDR
        'sukarela' => 10000000, // 10M IDR
    ],

    // Maximum transaction amount
    'max_transaction_amount' => 999999999.99,

    // Maximum days back for transaction date
    'max_transaction_days_back' => 365,

    // Minimum balances per type
    'minimum_balances' => [
        'pokok' => 100000, // 100K IDR
        'wajib' => 50000,  // 50K IDR
        'khusus' => 0,
        'sukarela' => 0,
    ],
];
