<?php
require_once __DIR__ . '/supabase.php';

function supa_mode() { return SUPABASE_MODE === true; }

function supa_select($table, $filters = [], $columns = '*', $limit = null, $order = null) {
    $path = "/rest/v1/" . urlencode($table) . "?select=" . urlencode($columns);
    foreach ($filters as $k => $v) {
        $path .= "&" . urlencode($k) . "=eq." . urlencode($v);
    }
    if ($order) {
        $path .= "&order=" . urlencode($order);
    }
    if ($limit !== null) {
        $path .= "&limit=" . intval($limit);
    }
    $res = supabaseRequest($path, 'GET', null, []);
    return is_array($res['data'] ?? null) ? $res['data'] : [];
}

function supa_insert($table, $rows) {
    $res = supabaseRequest("/rest/v1/" . urlencode($table), 'POST', $rows, ['Prefer: return=representation']);
    return $res['data'] ?? [];
}

function supa_update($table, $filters, $patch) {
    $path = "/rest/v1/" . urlencode($table) . "?";
    $q = [];
    foreach ($filters as $k => $v) $q[] = urlencode($k) . "=eq." . urlencode($v);
    $path .= implode('&', $q);
    $res = supabaseRequest($path, 'PATCH', $patch, ['Prefer: return=representation']);
    return $res['data'] ?? [];
}

function supa_delete($table, $filters) {
    $path = "/rest/v1/" . urlencode($table) . "?";
    $q = [];
    foreach ($filters as $k => $v) $q[] = urlencode($k) . "=eq." . urlencode($v);
    $path .= implode('&', $q);
    $res = supabaseRequest($path, 'DELETE', null, []);
    return ($res['status'] ?? 0) >= 200 && ($res['status'] ?? 0) < 300;
}

function db_count_supabase($table, $filters = []) {
    $rows = supa_select($table, $filters, '*', null, null);
    return count($rows);
}

function db_sum_supabase($table, $column, $filters = []) {
    $rows = supa_select($table, $filters, $column, null, null);
    $sum = 0;
    foreach ($rows as $r) $sum += (float)($r[$column] ?? 0);
    return $sum;
}
