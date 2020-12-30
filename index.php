<?php
session_start();
try {
    require_once 'application/bootstrap.php';
} catch (\Exception $e){
    $e->getMessage();
}
