<?php

namespace Wpbootstrap\Providers;

class CliUtilsWrapper
{
    /**
     * Output items in a table, JSON, CSV, ids, or the total count
     *
     * @param string        $format     Format to use: 'table', 'json', 'csv', 'ids', 'count'
     * @param array         $items      Data to output
     * @param array|string  $fields     Named fields for each item of data. Can be array or comma-separated list
     */
    public function format_items($format, $items, $fields)
    {
        \WP_CLI\Utils\format_items($format, $items, $fields);
    }
}