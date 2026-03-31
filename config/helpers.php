<?php
// config/helpers.php — вспомогательные функции Poisq

// Генерация ЧПУ slug из названия сервиса
function serviceUrl($id, $name) {
    $slug = mb_strtolower($name, 'UTF-8');
    $ru = ['а','б','в','г','д','е','ё','ж','з','и','й','к','л','м','н','о','п','р','с','т','у','ф','х','ц','ч','ш','щ','ъ','ы','ь','э','ю','я'];
    $en = ['a','b','v','g','d','e','yo','zh','z','i','y','k','l','m','n','o','p','r','s','t','u','f','h','ts','ch','sh','sch','','y','','e','yu','ya'];
    $slug = str_replace($ru, $en, $slug);
    $slug = preg_replace('/[^a-z0-9]+/', '-', $slug);
    $slug = trim($slug, '-');
    $slug = substr($slug, 0, 60);
    return '/service/' . $id . '-' . $slug;
}

// Генерация ЧПУ slug из названия статьи
function articleSlug($title) {
    $slug = mb_strtolower($title, 'UTF-8');
    $ru = ['а','б','в','г','д','е','ё','ж','з','и','й','к','л','м','н','о','п','р','с','т','у','ф','х','ц','ч','ш','щ','ъ','ы','ь','э','ю','я'];
    $en = ['a','b','v','g','d','e','yo','zh','z','i','y','k','l','m','n','o','p','r','s','t','u','f','h','ts','ch','sh','sch','','y','','e','yu','ya'];
    $slug = str_replace($ru, $en, $slug);
    $slug = preg_replace('/[^a-z0-9]+/', '-', $slug);
    $slug = trim($slug, '-');
    return substr($slug, 0, 80);
}

// URL статьи: /article/fr/slug
function articleUrl($countryCode, $slug) {
    $cc = ($countryCode === 'all') ? 'all' : preg_replace('/[^a-z]/', '', strtolower($countryCode));
    return '/article/' . $cc . '/' . $slug;
}
