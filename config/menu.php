<?php

return [
    [
        'title' => 'messages.dashboard',
        'icon' => 'icons.story',
        'type' => 'menu-item',
        'url' => 'admin.dashboard',
    ],
    ['type' => 'separator'],
    [
        'title' => 'messages.film.management',
        'type' => 'menu-item',
        'icon' => 'icons.folder',
        'dropdown' => true,
            'children' => [
            ['title' => 'messages.create.new', 'url' => 'admin.film.create', 'icon' => 'icons.note'],
            ['title' => 'messages.list', 'url' => 'admin.film.management', 'icon' => 'icons.list'],
        ],
    ],
    [
        'title' => 'messages.genres',
        'type' => 'menu-item',
        'icon' => 'icons.book',
        'dropdown' => true,
        'children' => [
            ['title' => 'messages.create.new', 'url' => 'admin.dashboard', 'icon' => 'icons.note'],
            ['title' => 'messages.list', 'url' => 'admin.dashboard', 'icon' => 'icons.list'],
        ],
    ],
    [
        'title' => 'messages.country',
        'type' => 'menu-item',
        'icon' => 'icons.flag',
        'dropdown' => true,
        'children' => [
            ['title' => 'messages.create.new', 'url' => 'admin.dashboard', 'icon' => 'icons.note'],
            ['title' => 'messages.list', 'url' => 'admin.dashboard', 'icon' => 'icons.list'],
        ],
    ],
    [
        'title' => 'messages.general.information',
        'url' => 'admin.dashboard',
        'type' => 'menu-item',
        'icon' => 'icons.setting',
    ],
];
