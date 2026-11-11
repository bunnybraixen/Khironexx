<?php

require_once __DIR__ . '/bootstrap.php';
require_once __DIR__ . '/models/OnboardingProcessModel.php';
require_once __DIR__ . '/controllers/OnboardingController.php';

$onboardingController = new OnboardingController(new OnboardingModel($onboardingDb));
$onboardingPage = $onboardingController->resolvePage($_GET['page'] ?? 'dashboard');
$onboardingPostResult = $_SERVER['REQUEST_METHOD'] === 'POST'
	? $onboardingController->handlePost($onboardingPage, $_POST, $_SESSION['user'], $onboardingCanManage)
	: null;
if (!empty($onboardingPostResult['redirect'])) {
	header('Location: ' . $onboardingPostResult['redirect']);
	exit;
}
$onboardingData = $onboardingController->buildPageData($onboardingPage, $_GET);
$onboardingData['canManage'] = $onboardingCanManage;
$onboardingUser = $_SESSION['user'];

require __DIR__ . '/views/layout.php';