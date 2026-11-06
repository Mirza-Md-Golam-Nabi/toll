<?php

return [
    'user_role' => [
        'super_admin' => 'সুপার অ্যাডমিন',
        'plaza_manager' => 'প্লাজা ম্যানেজার',
        'booth_operator' => 'বুথ অপারেটর',
    ],
    'transaction_status' => [
        'paid' => 'পরিশোধিত',
        'exempt' => 'ছাড়প্রাপ্ত',
        'voided' => 'বাতিল',
    ],
    'plate_source' => [
        'none' => 'নেওয়া হয়নি',
        'manual' => 'ম্যানুয়াল',
        'anpr' => 'ANPR ক্যামেরা',
    ],
    'shift_status' => [
        'open' => 'চলমান',
        'closed' => 'বন্ধ',
    ],
    'direction' => [
        'inbound' => 'আগমন',
        'outbound' => 'বহির্গমন',
    ],
    'audit_event' => [
        'created' => 'তৈরি',
        'updated' => 'পরিবর্তন',
        'deleted' => 'মুছে ফেলা',
        'login' => 'লগইন',
        'logout' => 'লগআউট',
        'shift_opened' => 'শিফট শুরু',
        'shift_closed' => 'শিফট বন্ধ',
        'ticket_issued' => 'টিকিট ইস্যু',
        'ticket_voided' => 'টিকিট বাতিল',
        'category_corrected' => 'ক্যাটাগরি সংশোধন',
        'ticket_reprinted' => 'টিকিট পুনঃপ্রিন্ট',
    ],
    'navigation_group' => [
        'Operations' => 'টোল অপারেশন',
        'Reports' => 'রিপোর্ট',
        'Configuration' => 'কনফিগারেশন',
    ],
];
