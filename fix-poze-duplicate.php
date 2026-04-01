<?php
/**
 * Plugin Name: Fix Poze Duplicate
 * Description: Elimina automat pozele duplicate consecutive din articole
 * Version: 1.0
 */

add_filter('the_content', 'fix_poze_duplicate', 20);

function fix_poze_duplicate($content) {
    // Gaseste toate tagurile <img> si <figure> consecutive duplicate
    // Pattern: acelasi src apare de 2 ori la rand

    // Elimina <img> duplicate consecutive (cu sau fara <p>, <figure> wrapper)
    $content = preg_replace(
        '#(<(?:p|figure)[^>]*>\s*<img([^>]+)>\s*</(?:p|figure)>)\s*<(?:p|figure)[^>]*>\s*<img\2>\s*</(?:p|figure)>#i',
        '$1',
        $content
    );

    // Elimina <img> duplicate consecutive simple (fara wrapper)
    $content = preg_replace(
        '#(<img([^>]+)>)\s*<img\2>#i',
        '$1',
        $content
    );

    // Metoda mai agresiva: cauta orice 2 imagini identice consecutive
    $content = preg_replace_callback(
        '#(<(?:p|figure|div)[^>]*>\s*<img\s[^>]*src=["\']([^"\']+)["\'][^>]*>\s*</(?:p|figure|div)>)\s*(<(?:p|figure|div)[^>]*>\s*<img\s[^>]*src=["\']([^"\']+)["\'][^>]*>\s*</(?:p|figure|div)>)#i',
        function($matches) {
            // Daca src-ul e acelasi, pastreaza doar prima imagine
            if ($matches[2] === $matches[4]) {
                return $matches[1];
            }
            return $matches[0];
        },
        $content
    );

    return $content;
}
