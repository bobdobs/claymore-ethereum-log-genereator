#!/usr/bin/php
<?php

define('DEBUG_MODE', false);
define('SERVICE_URL', 'http://localhost');
define('SERVICE_PORT', 3333);
define('RUN_INDEFINITELY', false); // If set to true script will loop indefinitely and continuously append to logs
define('UPDATE_FREQ', 10); // Scan and generate log every 10s if RUN_INDEFINITELY=true
define('MACHINE_NAME', 'minr');
define('FILE_LOG_GPUS', '/home/scripts/claymore-ethereum-log-genereator/gpu.log');
define('FILE_LOG_TOTALS', '/home/scripts/claymore-ethereum-log-genereator/totals.log');
define('DUAL_CURRENCY', 'DCR'); // Just leave as-is if not dual mining
define('DUAL_CURRENCY_LOWER', strtolower(DUAL_CURRENCY)); // Leave as-is
define('INFLUXDB_ENABLED', true);
define('INFLUXDB_DB', 'telegraf');
define('INFLUXDB_SERVICE_URL', 'http://localhost');
define('INFLUXDB_SERVICE_PORT', 8086);

date_default_timezone_set('UTC');

while(true) {

    $dual_mining_detected = false;

    // Fetch data from Claymore API via cURL:
    $curl_url = SERVICE_URL.':'.SERVICE_PORT;
    $arr_curl_result = fetch_via_curl($curl_url);

    $arr_log_line = array();

    if ($arr_curl_result['quick_status'] == 'ok' && strlen($arr_curl_result['curl_exec']) > 0) {

        // cURL-result ok. Strip HTML-tags:
        $clean = strip_tags($arr_curl_result['curl_exec']);

        // Reverse array
        $arr_lines = array_reverse(explode("\n", $clean));

        // Instantiate arrays that holds output data
        $arr_gpu_info = $arr_totals_info = array();

        $i = 0;
        foreach ($arr_lines as $key => $line) {

            if (substr($line, 0, 4) == 'GPU ') {
                // If we encounter 'GPU ' we are done and can stop
                break;
            }

            # ------------------------------------------------------------------ #
            # Individual GPU's: id, temperatures, and fan speed
            # ------------------------------------------------------------------ #

            // Get temp. and fans of individual cards - GPU0 t=57C fan=23%, GPU1 t=67C fan=40%, GPU2 t=64C fan=70%, GPU3 t=73C fan=70%
            if (substr($line, 0, 4) == 'GPU0') {
                $arr_gpu_temp_fan = explode(', ', $line);

                $gpu_number = 0;
                foreach ($arr_gpu_temp_fan as $k_gpu => $gpu_info) {
                    $arr_gpu = explode(' ', $gpu_info);

                    $arr_gpu_info[$gpu_number] = array(
                        'id' => $arr_gpu[0],
                        'temp' => str_replace(array('t=', 'C'), "", $arr_gpu[1]),
                        'fan' => str_replace(array('fan=', '%'), "", $arr_gpu[2]),
                    );

                    $gpu_number++;
                }

            }

            # ------------------------------------------------------------------ #
            # DUAL CURRENCY: Get mining speed of individual cards
            # ------------------------------------------------------------------ #

            // &nbsp; DCR: GPU0 336.523 Mh/s, GPU1 352.541 Mh/s, GPU2 off, GPU3 off
            if (stristr($line, DUAL_CURRENCY.': GPU') !== false) {

                // It seems we are dual mining
                $dual_mining_detected = true;

                $clean_line = substr($line, 12);
                $arr_gpu_speed = explode(', ', $clean_line);

                $gpu_number = 0;
                foreach ($arr_gpu_speed as $k_gpu => $gpu_info) {
                    $arr_gpu = explode(' ', $gpu_info);

                    if ($arr_gpu[1] == 'off') {
                        $arr_gpu[1] = 0;
                    }

                    $arr_gpu_info[$gpu_number][DUAL_CURRENCY_LOWER.'_speed'] = $arr_gpu[1];

                    $gpu_number++;
                }

            }

            # ------------------------------------------------------------------ #
            # DUAL CURRENCY: Get totals
            # ------------------------------------------------------------------ #

            // &nbsp; DCR - Total Speed: 685.462 Mh/s, Total Shares: 4831, Rejected: 69
            if (stristr($line, DUAL_CURRENCY.' - Total Speed:') !== false) {

                // It seems we are dual mining
                $dual_mining_detected = true;

                $clean_line = substr($line, 12);

                $arr_totals = explode(' ', $clean_line);
                $shares = str_replace(array('),', '('), array('', '+'), $arr_totals[7]);
                $arr_shares = explode('+', $shares);

                $arr_totals_info[DUAL_CURRENCY_LOWER.'_total_speed'] = $arr_totals[3];
                $arr_totals_info[DUAL_CURRENCY_LOWER.'_total_shares'] = $arr_shares[0];
                $arr_totals_info[DUAL_CURRENCY_LOWER.'_total_rejected'] = intval($arr_totals[9]);

                // Shares for individual cards
                $arr_card_shares = array_slice($arr_shares, 1, count($arr_shares));

                if (!empty($arr_card_shares)) {
                    foreach ($arr_card_shares as $k_gpu => $gpu_shares) {
                        $arr_gpu_info[$k_gpu][DUAL_CURRENCY_LOWER.'_shares'] = $gpu_shares;
                    }
                }

                // Let's calculate some averages for the totals array and set share percentage per card
                if (!empty($arr_gpu_info)) {
                    $total_cards = count($arr_gpu_info);

                    $arr_totals_info[DUAL_CURRENCY_LOWER.'_avg_speed_per_card']  = round($arr_totals_info[DUAL_CURRENCY_LOWER.'_total_speed'] / $total_cards, 2);
                    $arr_totals_info[DUAL_CURRENCY_LOWER.'_avg_shares_per_card'] = round($arr_totals_info[DUAL_CURRENCY_LOWER.'_total_shares'] / $total_cards, 2);

                    $temp_total = $fan_total = 0;
                    foreach ($arr_gpu_info as $k_gpu => $gpu) {
                        if ($arr_totals_info[DUAL_CURRENCY_LOWER.'_total_shares'] > 0) {
                            $arr_gpu_info[$k_gpu][DUAL_CURRENCY_LOWER.'_shares_pct'] = round(100 * ($gpu[DUAL_CURRENCY_LOWER.'_shares'] / $arr_totals_info[DUAL_CURRENCY_LOWER.'_total_shares']), 2);
                        } else {
                            $arr_gpu_info[$k_gpu][DUAL_CURRENCY_LOWER.'_shares_pct'] = 0;
                        }
                    }

                }

            }


            # ------------------------------------------------------------------ #
            # ETH: Get mining speed of individual cards
            # ------------------------------------------------------------------ #

            // ETH: GPU0 28.780 Mh/s, GPU1 29.179 Mh/s, GPU2 24.029 Mh/s, GPU3 28.896 Mh/s
            if (substr($line, 0, 8) == 'ETH: GPU') {

                $clean_line = substr($line, 5);

                $arr_gpu_speed = explode(', ', $clean_line);

                $gpu_number = 0;
                foreach ($arr_gpu_speed as $k_gpu => $gpu_info) {
                    $arr_gpu = explode(' ', $gpu_info);

                    if ($arr_gpu[1] == 'off') {
                        $arr_gpu[1] = 0;
                    }

                    $arr_gpu_info[$gpu_number]['eth_speed'] = $arr_gpu[1];

                    $gpu_number++;
                }

            }

            // TODO "Incorrect ETH shares: GPU0 6, GPU1 6, GPU2 3, GPU3 23" + "Incorrect ETH shares: none"

            # ------------------------------------------------------------------ #
            # ETH: Get totals and also calculate avg. temperature and avg. fan
            # ------------------------------------------------------------------ #

            // ETH - Total Speed: 110.884 Mh/s, Total Shares: 271(78+84+57+57), Rejected: 0, Time: 02:47
            if (substr($line, 0, strlen('ETH - Total Speed')) == 'ETH - Total Speed') {

                $clean_line = substr($line, 5);

                $arr_totals = explode(' ', $clean_line);
                $shares = str_replace(array('),', '('), array('', '+'), $arr_totals[7]);
                $arr_shares = explode('+', $shares);

                $arr_totals_info['eth_total_speed'] = $arr_totals[3];
                $arr_totals_info['eth_total_shares'] = intval(str_replace(",", "", $arr_shares[0]));
                $arr_totals_info['eth_total_rejected'] = intval($arr_totals[9]);

                // Shares for individual cards
                $arr_card_shares = array_slice($arr_shares, 1, count($arr_shares));

                if (!empty($arr_card_shares)) {
                    foreach ($arr_card_shares as $k_gpu => $gpu_shares) {
                        $arr_gpu_info[$k_gpu]['eth_shares'] = $gpu_shares;
                    }
                }

                // Let's calculate some averages for the totals array and set share percentage per card
                if (!empty($arr_gpu_info)) {
                    $total_cards = count($arr_gpu_info);

                    $arr_totals_info['eth_avg_speed_per_card']  = round($arr_totals_info['eth_total_speed'] / $total_cards, 2);
                    $arr_totals_info['eth_avg_shares_per_card'] = round($arr_totals_info['eth_total_shares'] / $total_cards, 2);

                    $temp_total = $fan_total = 0;
                    foreach ($arr_gpu_info as $k_gpu => $gpu) {

                        $temp_total += $gpu['temp'];
                        $fan_total  += $gpu['fan'];

                        if ($arr_totals_info['eth_total_shares'] > 0) {
                            $arr_gpu_info[$k_gpu]['eth_shares_pct'] = round(100 * ($gpu['eth_shares'] / $arr_totals_info['eth_total_shares']), 2);
                            if (count($arr_gpu_info) === 1) {
                                // Fix: If there is only one card in rig
                                $arr_gpu_info[$k_gpu]['eth_shares_pct'] = 100;
                            }
                        } else {
                            $arr_gpu_info[$k_gpu]['eth_shares_pct'] = 0;
                        }

                    }

                    $arr_totals_info['avg_temp'] = round($temp_total / $total_cards, 2);
                    $arr_totals_info['avg_fan']  = round($fan_total / $total_cards, 2);

                }

            }

            $i++;

        }

        # ------------------------------------------------------------------ #
        # Sorting totals array before output
        # ------------------------------------------------------------------ #

        // Sort order
        $arr_totals_order = array('avg_temp', 'avg_fan', 'eth_total_speed', 'eth_total_shares', 'eth_total_rejected', 'eth_avg_speed_per_card', 'eth_avg_shares_per_card');
        if ($dual_mining_detected === true) {
            // We are doing dual mining, so there are a few more keys we need to sort
            $arr_totals_order = array_merge($arr_totals_order, array(DUAL_CURRENCY_LOWER.'_total_speed', DUAL_CURRENCY_LOWER.'_total_shares', DUAL_CURRENCY_LOWER.'_total_rejected', DUAL_CURRENCY_LOWER.'_avg_speed_per_card', DUAL_CURRENCY_LOWER.'_avg_shares_per_card'));
        }
        // Now sort the totals array:
        $arr_totals_info = array_merge(array_flip($arr_totals_order), $arr_totals_info);


        # ------------------------------------------------------------------ #
        # Sorting GPU arrays before output
        # ------------------------------------------------------------------ #

        // Sort order
        $arr_gpu_order = array('id', 'temp', 'fan', 'eth_speed', 'eth_shares', 'eth_shares_pct');
        if ($dual_mining_detected === true) {
            // We are doing dual mining, so there are a few more keys we need to sort
            $arr_gpu_order = array_merge($arr_gpu_order, array(DUAL_CURRENCY_LOWER.'_speed', DUAL_CURRENCY_LOWER.'_shares', DUAL_CURRENCY_LOWER.'_shares_pct'));
        }

        foreach ($arr_gpu_info as $gpu_k => $gpu) {
            // Sort each GPU array
            $arr_gpu_info[$gpu_k] = array_merge(array_flip($arr_gpu_order), $gpu);
        }


        # ------------------------------------------------------------------ #
        # Log time and machine name
        # ------------------------------------------------------------------ #

        $objDateTime = new DateTime('NOW');
        $time = str_replace('+', 'Z', $objDateTime->format(DateTime::RFC3339));


        # ------------------------------------------------------------------ #
        # Write to totals log file
        # ------------------------------------------------------------------ #

        $log_entry_total = $time.', machine: '.MACHINE_NAME;

        foreach ($arr_totals_info as $label => $v) {
            $log_entry_total .= ', '.$label.': '.$v;
        }

        file_put_contents(FILE_LOG_TOTALS, $log_entry_total."\n", FILE_APPEND);


        # ------------------------------------------------------------------ #
        # Write to gpu log file
        # ------------------------------------------------------------------ #

        $log_entry_gpu = '';
        foreach ($arr_gpu_info as $gpu_k => $gpu) {

            $log_entry_gpu .= $time.', machine: '.MACHINE_NAME;
            foreach ($gpu as $label => $v) {
                $log_entry_gpu .= ', '.$label.': '.$v;
            }

            $log_entry_gpu .= "\n";

        }

        file_put_contents(FILE_LOG_GPUS, $log_entry_gpu, FILE_APPEND);

        if (INFLUXDB_ENABLED === true) {

            /**
             * Influx line format looks something like this:
             * curl -i -XPOST 'http://localhost:8086/write?db=mydb' --data-binary 'cpu_load_short,host=server01,region=us-west value=0.64 1434055562000000000'
             * See: https://docs.influxdata.com/influxdb/v1.3/guides/writing_data/
             */

            $influxdb_url = INFLUXDB_SERVICE_URL.INFLUXDB_SERVICE_PORT.'/write?db='.INFLUXDB_DB;

            // Totals for influxdb
            $binary_data_totals = 'eth_totals,host='.MACHINE_NAME.' ';
            foreach ($arr_totals_info as $label => $v) {
                $binary_data_totals.= $label.'='.$v.',';
            }
            $binary_data_totals = substr($binary_data_totals, 0, -1);
            $binary_data_totals.= ' '.nano_sec_since_unix_epoc(); // Add time in nano seconds

            // GPU's for influxdb
            $arr_string_fields = array('id');
            $binary_data_gpu = '';
            foreach ($arr_gpu_info as $gpu_k => $gpu) {

                $binary_data_gpu = 'eth_gpu,host='.MACHINE_NAME.',gpu='.$gpu['id'].' ';
                foreach ($gpu as $label => $v) {
                    if (in_array($label, $arr_string_fields)) {
                        // String-fields needs to be in qoutes
                        $binary_data_gpu.= $label.'="'.$v.'",';
                    } else {
                        $binary_data_gpu.= $label.'='.$v.',';
                    }
                }
                $binary_data_gpu = substr($binary_data_gpu, 0, -1);
                $binary_data_gpu.= "\n";

            }
            $binary_data_gpu = substr($binary_data_gpu, 0, -1);
            $binary_data_gpu.= ' '.nano_sec_since_unix_epoc(); // Add time in nano seconds

            $influx_binary_data = $binary_data_totals."\n".$binary_data_gpu;

            curl_setopt($ch, CURLOPT_URL, $influxdb_url);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
            curl_setopt($ch, CURLOPT_POSTFIELDS, $influx_binary_data);
            curl_setopt($ch, CURLOPT_POST, 1);

            $headers = array();
            $headers[] = "Content-Type: application/x-www-form-urlencoded";
            curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);

            $result = curl_exec($ch);
            if (curl_errno($ch)) {
                echo 'Error:' . curl_error($ch);
            }
            curl_close ($ch);

        }


        # ------------------------------------------------------------------ #
        # Output debug-info. if enabled
        # ------------------------------------------------------------------ #

        if (DEBUG_MODE === true) {
            print "===========================================================================\n\n";
            print $log_entry_total . "\n";
            print $log_entry_gpu . "\n";
            print "===========================================================================\n\n";
            print_r($arr_totals_info) . "\n";
            print "===========================================================================\n\n";
            print_r($arr_gpu_info) . "\n";
            print "===========================================================================\n\n";
            print_r($arr_lines) . "\n";
        }

    } else if (DEBUG_MODE === true) {

        // Error
        print 'Could not reach Claymore API on '.SERVICE_URL.' port '.SERVICE_PORT;

    }

    if (RUN_INDEFINITELY === false) {
        exit;
    }

    sleep(UPDATE_FREQ);

}


function nano_sec_since_unix_epoc() {
    return intval(array_sum(explode(' ', microtime()))).'000000000';
}


/**
 * This function fetches data via curl
 * @param $url
 * @return array
 */
function fetch_via_curl($url) {

    $arr_curl_result = array();

    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
    curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
    curl_setopt($ch, CURLOPT_TIMEOUT, 4);

    $arr_curl_result['curl_exec'] = curl_exec($ch);
    $arr_curl_result['curl_getinfo'] = curl_getinfo($ch);

    if (array_key_exists('http_code', $arr_curl_result['curl_getinfo']) === true) {

        if ($arr_curl_result['curl_getinfo']['http_code'] == '200') {
            $quick_status = 'ok';
        } else {
            $quick_status = 'error';
        }

        $http_code = $arr_curl_result['curl_getinfo']['http_code'];

    } else {

        $quick_status = 'error';
        $http_code = 'None';

    }

    $arr_curl_result['quick_status'] = $quick_status;
    $arr_curl_result['http_code']    = $http_code;

    curl_close($ch);

    return $arr_curl_result;

}

?>