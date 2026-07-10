<?php

use App\Core\Services\GameHooks;

GameHooks::listen('admin.sidebar', function (array $sections): array {
    $sections[] = [
        'id' => 'peptide-vendors',
        'label' => 'Peptide Vendors',
        'icon' => 'BoltIcon',
        'order' => 80,
        'plugin' => 'peptidevendorsbot',
        'children' => [
            ['type' => 'separator', 'label' => 'Overview'],
            ['route' => '/peptide-vendors/dashboard', 'label' => 'Dashboard', 'icon' => 'ChartBarIcon', 'plugin' => 'peptidevendorsbot'],
            ['type' => 'separator', 'label' => 'Moderation'],
            ['route' => '/peptide-vendors/permissions', 'label' => 'Topic Permissions', 'icon' => 'LockClosedIcon', 'plugin' => 'peptidevendorsbot'],
            ['route' => '/peptide-vendors/verifications', 'label' => 'Verified Members', 'icon' => 'CheckBadgeIcon', 'plugin' => 'peptidevendorsbot'],
            ['route' => '/peptide-vendors/questions', 'label' => 'Daily Questions', 'icon' => 'ChatBubbleBottomCenterTextIcon', 'plugin' => 'peptidevendorsbot'],
            ['type' => 'separator', 'label' => 'Activity'],
            ['route' => '/peptide-vendors/welcome-messages', 'label' => 'Welcome Messages', 'icon' => 'ChatBubbleLeftRightIcon', 'plugin' => 'peptidevendorsbot'],
            ['route' => '/peptide-vendors/webhook-updates', 'label' => 'Webhook Logs', 'icon' => 'CommandLineIcon', 'plugin' => 'peptidevendorsbot'],
        ],
    ];

    
    $sections[] = [
        'id' => 'iptv-lines',
        'label' => 'IPTV Lines',
        'icon' => 'TvIcon',
        'order' => 85,
        'plugin' => 'peptidevendorsbot',
        'children' => [
            ['route' => '/peptide-vendors/iptv-lines', 'label' => 'All Lines', 'icon' => 'CircleStackIcon', 'plugin' => 'peptidevendorsbot'],
        ],
    ];

    return $sections;
}, 10);
