<?php
// Salin file ini menjadi conf.php dan isi dengan kredensial yang sesuai
// cp conf/conf.example.php conf/conf.php

session_start();
date_default_timezone_set('Asia/Jakarta');

require_once(__DIR__ . '/../libs/aes-encrypt/function.php');

// koneksi database
$db_hostname = "localhost";       // host database
$db_username = "your_db_user";   // username database
$db_password = "your_db_pass";   // password database
$db_port     = 3306;             // port database (MySQL default: 3306, ProxySQL: 6033)
$db_name     = "your_db_name";   // nama database
