<?php

return [
    [
        'title' => 'messages.dashboard',
        'icon' => 'icons.story',
        'type' => 'menu-item',
        'url' => 'admin.dashboard',
        'active' => ['admin.dashboard'],
    ],
    ['type' => 'separator'],
    [
        'title' => 'messages.film.management',
        'type' => 'menu-item',
        'icon' => 'icons.folder',
        'dropdown' => true,
        'active' => ['admin.film.*'],
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
        'active' => ['admin.genres.*'],
        'children' => [
            ['title' => 'messages.create.new', 'url' => 'admin.genres.create', 'icon' => 'icons.note'],
            ['title' => 'messages.list', 'url' => 'admin.genres.index', 'icon' => 'icons.list'],
        ],
    ],
    [
        'title' => 'messages.country',
        'type' => 'menu-item',
        'icon' => 'icons.flag',
        'dropdown' => true,
        'active' => ['admin.countries.*'],
        'children' => [
            ['title' => 'messages.create.new', 'url' => 'admin.countries.create', 'icon' => 'icons.note'],
            ['title' => 'messages.list', 'url' => 'admin.countries.index', 'icon' => 'icons.list'],
        ],
    ],
    [
        'title' => 'messages.user.management',
        'url' => 'admin.users.index',
        'active' => ['admin.users.*'],
        'type' => 'menu-item',
        'icon' => 'icons.user',
    ],
    [
        'title' => 'messages.general.information',
        'url' => 'admin.system.info',
        'active' => ['admin.system.*'],
        'type' => 'menu-item',
        'icon' => 'icons.setting',
    ],
];
