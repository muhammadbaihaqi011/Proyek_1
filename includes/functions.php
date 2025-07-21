<?php

function esc($string) {
    return htmlspecialchars($string, ENT_QUOTES, 'UTF-8');
}

function redirect($url) {
    header("Location: " . $url);
    exit;
}

// Tambahkan tgl_indo_edlink di sini jika Anda ingin membuatnya global dan konsisten
// agar tidak perlu didefinisikan ulang di dashboard.php
/*
if (!function_exists('tgl_indo_edlink')) {
    function tgl_indo_edlink($date)
    {
        $hari = ['Min', 'Sen', 'Sel', 'Rab', 'Kam', 'Jum', 'Sab'];
        $bulan = ['Jan', 'Feb', 'Mar', 'Apr', 'Mei', 'Jun', 'Jul', 'Agu', 'Sep', 'Okt', 'Nov', 'Des'];
        $d = date('w', strtotime($date));
        $day = $hari[$d];
        $tgl = date('d', strtotime($date));
        $bln = $bulan[(int)date('m', strtotime($date)) - 1];
        $thn = date('Y', strtotime($date));
        return "$day, $tgl $bln $thn";
    }
}
*/
?>