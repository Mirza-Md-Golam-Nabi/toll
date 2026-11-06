<?php

return [
    'user_role' => [
        'super_admin' => 'Super Admin',
        'plaza_manager' => 'Plaza Manager',
        'booth_operator' => 'Booth Operator',
    ],
    'transaction_status' => [
        'paid' => 'Paid',
        'exempt' => 'Exempt',
        'voided' => 'Voided',
    ],
    'plate_source' => [
        'none' => 'Not captured',
        'manual' => 'Manual',
        'anpr' => 'ANPR camera',
    ],
    'shift_status' => [
        'open' => 'Open',
        'closed' => 'Closed',
    ],
    'direction' => [
        'inbound' => 'Inbound',
        'outbound' => 'Outbound',
    ],
    'audit_event' => [
        'created' => 'Created',
        'updated' => 'Updated',
        'deleted' => 'Deleted',
        'login' => 'Login',
        'logout' => 'Logout',
        'shift_opened' => 'Shift opened',
        'shift_closed' => 'Shift closed',
        'ticket_issued' => 'Ticket issued',
        'ticket_voided' => 'Ticket voided',
        'category_corrected' => 'Category corrected',
        'ticket_reprinted' => 'Ticket reprinted',
    ],
    'navigation_group' => [
        'Operations' => 'Toll Operations',
        'Reports' => 'Reports',
        'Configuration' => 'Configuration',
    ],
];
