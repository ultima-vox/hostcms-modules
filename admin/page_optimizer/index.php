<?php

/**
 * Page Optimizer administration entry point.
 */
require_once('../../bootstrap.php');

Core_Auth::authorization($sModule = 'page_optimizer');

$oController = new PageOptimizer_Controller_Index();
$oController->execute();
