<?php

function general_consolidations()
{
    find_duplicate_geolocations();
    add_neighbourhoods_gd_places_to_gd_place_list();
    update_num_of_direct_gd_places();
    trigger_error("general consolidations done", E_USER_NOTICE);
}

function find_duplicate_geolocations()
{
    $geolocations = get_posts(array('post_type' => 'geolocations', 'posts_per_page' => -1));
    $emailoutput = "";

    $titles = array_column($geolocations, 'post_title');
    //var_dump($titles);
    $duplicate_titles = array_filter(array_count_values($titles), function ($count) {
        return $count > 1;
    });

    foreach ($duplicate_titles as $title => $count) {
        $message = "Duplicate geolocation titles found: $title, count: $count\n";
        trigger_error($message, E_USER_WARNING);
        $emailoutput .= $message;
    }

    if ($emailoutput != "") {
        send_email($emailoutput, 'Duplicate geolocation(s) found');
    }
}

function update_num_of_direct_gd_places()
{
    $geolocations_ids = get_posts(array('post_type' => 'geolocations', 'posts_per_page' => -1, 'fields' => 'ids'));
    foreach ($geolocations_ids as $current_geolocation_id) {
        $gd_place_ids_list = get_post_meta($current_geolocation_id, 'gd_place_list', false);
        $num_of_gd_places = count($gd_place_ids_list);
        update_post_meta($current_geolocation_id, 'num_of_direct_gd_places', $num_of_gd_places);
    }
}


function add_neighbourhoods_gd_places_to_gd_place_list()
{
    xdebug_break();
    $geolocations_ids = get_posts(array('post_type' => 'geolocations', 'posts_per_page' => -1, 'fields' => 'ids'));
    foreach ($geolocations_ids as $current_geolocation_id) {
        $neighbourhoods = get_post_meta($current_geolocation_id, 'geodir_neighbourhoods', false);
        $neighbourhoods_gd_place_ids = array();
        foreach ($neighbourhoods as $neighbourhood) {
            $neighbourhood_gd_place_ids = get_post_meta($neighbourhood, 'gd_place_list', false);
            $neighbourhoods_gd_place_ids = array_merge($neighbourhoods_gd_place_ids, $neighbourhood_gd_place_ids);
        }
        $neighbourhoods_gd_place_ids = array_unique($neighbourhoods_gd_place_ids);
        $gd_place_ids_list = get_post_meta($current_geolocation_id, 'gd_place_list', false);
        $gd_place_ids_list = array_merge($gd_place_ids_list, $neighbourhoods_gd_place_ids);
        $gd_place_ids_list = array_unique($gd_place_ids_list);
        update_post_meta($current_geolocation_id, 'gd_place_list', $gd_place_ids_list);
    }
}
