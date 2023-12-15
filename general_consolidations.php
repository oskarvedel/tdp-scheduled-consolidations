<?php

function general_consolidations()
{
    set_gd_places_within_radiuses_for_all_geolocations([1, 3, 5, 8, 10, 15, 20, 25, 30, 50]);
    set_all_gd_places_sorted_by_distance_list_for_all_geolocations();
    find_duplicate_geolocations();
    set_nearest_geolocations_with_gd_places_for_all_geolocations();
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

function set_nearest_geolocations_with_gd_places_for_all_geolocations()
{
    $geolocations_ids = get_posts(array('post_type' => 'geolocations', 'posts_per_page' => -1, 'fields' => 'ids'));
    if (empty($geolocations_ids)) {
        trigger_error("No geolocations found", E_USER_WARNING);
        return;
    }


    foreach ($geolocations_ids as $geolocation_id) {
        $nearest_geolocations = get_nearest_geolocations_with_gd_places_for_single_geolocation($geolocations_ids, $geolocation_id, 5);

        $nearest_geolocation_ids = array_keys($nearest_geolocations);
        update_post_meta($geolocation_id, 'nearest_geolocations', $nearest_geolocation_ids);

        unset($nearest_geolocations, $nearest_geolocations_keys);
    }
}

function get_nearest_geolocations_with_gd_places_for_single_geolocation($geolocations_ids, $current_geolocation_id, $limit = 5)
{
    $distances = array();

    $current_lat = get_post_meta($current_geolocation_id, 'latitude', true);
    $current_long = get_post_meta($current_geolocation_id, 'longitude', true);

    $geolocations_ids = array_diff($geolocations_ids, [$current_geolocation_id]);

    foreach ($geolocations_ids as $geolocation_id) {
        $gd_places = get_post_meta($geolocation_id, 'gd_place_list', true);
        if (empty($gd_places)) {
            continue;
        }
        unset($gd_places);

        $lat = get_post_meta($geolocation_id, 'latitude', true);
        $long = get_post_meta($geolocation_id, 'longitude', true);

        $theta = $current_long - $long;

        $dist = sin(deg2rad($current_lat)) * sin(deg2rad($lat)) +  cos(deg2rad($current_lat)) * cos(deg2rad($lat)) * cos(deg2rad($theta));
        $dist = acos($dist);
        $dist = rad2deg($dist);
        $kilometers = $dist * 60 * 1.852;

        $distances[$geolocation_id] = $kilometers;
        unset($lat, $long, $kilometers, $theta, $dist);
    }

    asort($distances);
    $distances = array_slice($distances, 0, $limit, true);

    return $distances;
}


function set_all_gd_places_sorted_by_distance_list_for_all_geolocations()
{
    global $wpdb;
    $geodir_gd_place_detail_table = $wpdb->get_results("SELECT * FROM {$wpdb->prefix}geodir_gd_place_detail", OBJECT);

    $all_gd_places = get_posts(array('post_type' => 'gd_place', 'posts_per_page' => -1));

    $filtered_geodir_gd_place_detail_table = [];
    foreach ($all_gd_places as $gd_place) {
        foreach ($geodir_gd_place_detail_table as $gd_place_detail) {
            if ($gd_place_detail->post_id == $gd_place->ID) {
                $filtered_geodir_gd_place_detail_table[] = $gd_place_detail;
            }
        }
    }

    $geolocations_ids = get_posts(array('post_type' => 'geolocations', 'posts_per_page' => -1, 'fields' => 'ids'));

    if (empty($filtered_geodir_gd_place_detail_table)) {
        trigger_error("No gd_places_ids found", E_USER_WARNING);
        return;
    }

    if (empty($geolocations_ids)) {
        trigger_error("No geolocations_ids found", E_USER_WARNING);
        return;
    }

    foreach ($geolocations_ids as $current_geolocation_id) {
        $gd_places_in_current_geodir = get_post_meta($current_geolocation_id, 'gd_place_list', false);

        if ($gd_places_in_current_geodir === null) {
            $gd_places_in_current_geodir = array();
        }

        $gd_places_not_in_current_geodir = array_filter($filtered_geodir_gd_place_detail_table, function ($gd_place) use ($gd_places_in_current_geodir) {
            return !in_array($gd_place->post_id, $gd_places_in_current_geodir);
        });

        $near_gd_place_list = get_all_gd_places_sorted_by_distance_list_for_single_geolocation($gd_places_not_in_current_geodir, $current_geolocation_id);
        //$near_gd_place_list = array_keys($nearest_geolocations);
        update_post_meta($current_geolocation_id, 'all_gd_places_sorted_by_distance_list', $near_gd_place_list);
        $all_gd_places_sorted_by_distance_list = get_post_meta($current_geolocation_id, 'all_gd_places_sorted_by_distance_list', true);
        unset($nearest_geolocations, $nearest_geolocations_keys);
    }
}

function get_all_gd_places_sorted_by_distance_list_for_single_geolocation($filtered_geodir_gd_place_detail_table, $current_geolocation_id)
{
    $distances = array();

    $current_geolocation_lat = get_post_meta($current_geolocation_id, 'latitude', true);
    $current_geolocation_long = get_post_meta($current_geolocation_id, 'longitude', true);

    foreach ($filtered_geodir_gd_place_detail_table as $gd_place) {
        $lat = $gd_place->latitude;
        $long = $gd_place->longitude;

        $theta = $current_geolocation_long - $long;

        $dist = sin(deg2rad($current_geolocation_lat)) * sin(deg2rad($lat)) +  cos(deg2rad($current_geolocation_lat)) * cos(deg2rad($lat)) * cos(deg2rad($theta));
        $dist = acos($dist);
        $dist = rad2deg($dist);
        $kilometers = $dist * 60 * 1.852;

        $distances[$gd_place->post_id] = $kilometers;
        unset($lat, $long, $kilometers, $theta, $dist);
    }

    asort($distances);

    return $distances;
}

function set_gd_places_within_radiuses_for_all_geolocations($radiuses)
{
    $geolocations_ids = get_posts(array('post_type' => 'geolocations', 'posts_per_page' => -1, 'fields' => 'ids'));
    foreach ($geolocations_ids as $current_geolocation_id) {
        $all_gd_places_sorted_by_distance_list = get_post_meta($current_geolocation_id, 'all_gd_places_sorted_by_distance_list', true);
        foreach ($radiuses as $radius) {
            $within_radius = array_filter($all_gd_places_sorted_by_distance_list, function ($distance) use ($radius) {
                return $distance <= $radius;
            });
            update_post_meta($current_geolocation_id, 'gd_places_within_' . $radius . '_km', $within_radius);
            $within = get_post_meta($current_geolocation_id, 'gd_places_within_' . $radius . '_km', true);
        }
    }
}
