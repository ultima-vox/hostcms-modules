<?php

/**
 * Page Optimizer administration entry point.
 */
require_once('../../bootstrap.php');

Core_Auth::authorization($sModule = 'page_optimizer');

require_once CMS_FOLDER . 'modules/page_optimizer/PageOptimizer_Settings.php';
require_once CMS_FOLDER . 'modules/page_optimizer/PageOptimizer_Controller_Index.php';

$oController = new PageOptimizer_Controller_Index();
$oController->execute();
